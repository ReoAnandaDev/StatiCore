<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is student
$auth->checkSession();
$auth->requireRole('siswa');

$conn = $db->getConnection();

// Get student's classes
$stmt = $conn->prepare("
    SELECT k.* FROM kelas k
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE sk.siswa_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

// Get selected class
$selected_class = isset($_GET['class_id']) ? $_GET['class_id'] : null;

// Get materials for selected class
$materials = [];
if ($selected_class) {
    $stmt = $conn->prepare("
        SELECT m.*, u.nama_lengkap as guru_nama
        FROM materi m
        JOIN users u ON m.guru_id = u.id
        WHERE m.kelas_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$selected_class]);
    $materials = $stmt->fetchAll();
}

// Get class name for selected class
$class_name = '';
if ($selected_class) {
    foreach ($classes as $class) {
        if ($class['id'] == $selected_class) {
            $class_name = $class['nama_kelas'] . ' (' . $class['tahun_ajaran'] . ')';
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materi - StatiCore</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c5282;
            --secondary: #4299e1;
            --accent: #f6ad55;
            --light: #f7fafc;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --success: #10B981;
            --warning: #F59E0B;
            --info: #3B82F6;
            --danger: #EF4444;
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: var(--primary);
        }

        .Title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: var(--primary);
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(4px);
        }

        .sidebar h4 {
            font-weight: 600;
            padding-left: 1rem;
            color: white;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: none;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-bottom: none;
            padding: 24px;
            font-weight: 600;
            color: white;
        }

        .card-body {
            padding: 24px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-select, .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-outline {
            background: transparent;
            color: var(--secondary);
            border: 2px solid var(--secondary);
        }

        .btn-outline:hover {
            background: var(--secondary);
            color: var(--white);
        }

        /* Material Cards */
        .material-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }

        .material-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
        }

        .material-card:hover {
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-4px);
        }

        .material-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 20px;
            border-bottom: 1px solid var(--gray-200);
        }

        .material-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            margin: 0 0 8px 0;
            line-height: 1.3;
        }

        .material-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }

        .material-meta i {
            width: 16px;
            text-align: center;
        }

        .material-body {
            padding: 20px;
        }

        .material-description {
            color: var(--gray-700);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .material-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--gray-500);
            margin-bottom: 24px;
        }

        .empty-state h3 {
            color: var(--gray-700);
            margin-bottom: 12px;
            font-size: 1.5rem;
        }

        .empty-state p {
            color: var(--gray-500);
            font-size: 1.1rem;
        }

        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 24px;
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
            border-left: 4px solid var(--info);
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--accent-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* RESPONSIVE STYLES */
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                left: -250px;
                top: 0;
                bottom: 0;
                width: 250px;
                z-index: 1000;
                transition: all 0.3s ease-in-out;
            }

            .sidebar.active {
                left: 0;
            }

            .content-wrapper {
                margin-left: 0 !important;
                padding: 1rem;
            }

            .mobile-menu-toggle {
                display: block !important;
                position: fixed;
                right: 20px;
                bottom: 20px;
                z-index: 1001;
                background-color: #3B3B1A;
                color: white;
                border: none;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            }

            .card-body h5 {
                font-size: 1.25rem;
            }

            .list-unstyled li {
                font-size: 0.95rem;
            }

            .btn-primary {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .Title {
                font-size: 1.25rem;
            }

            .card-title {
                font-size: 1.1rem;
            }

            .list-unstyled li {
                margin-bottom: 0.5rem;
            }

            .badge {
                font-size: 0.85rem;
            }

            .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .btn-secondary {
                width: 100%;
                margin-top: 1rem;
            }
        }

        @media (max-width: 576px) {
            .sidebar h4 {
                font-size: 1.1rem;
            }

            .nav-link i {
                font-size: 1.1rem;
            }

            .card-body {
                padding: 1rem;
            }

            .form-control,
            .form-select {
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
            }

            .btn {
                font-size: 0.85rem;
            }

            .material-card {
                margin-bottom: 1.25rem;
            }

            .material-actions {
                flex-wrap: wrap;
                justify-content: space-between;
            }

            .material-actions .btn {
                width: 48%;
                text-align: center;
            }

            .empty-state-icon {
                font-size: 2.5rem;
            }

            .empty-state h3 {
                font-size: 1.25rem;
            }

            .empty-state p {
                font-size: 0.95rem;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h4><i class="fas fa-chart-line me-2"></i>StatiCore</h4>
                    <hr>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <!-- <li class="nav-item">
                            <a class="nav-link" href="detail_kelas.php">
                                <i class="fas fa-chalkboard me-2"></i>Kelas
                            </a>
                        </li> -->
                        <li class="nav-item">
                            <a class="nav-link active" href="materi.php">
                                <i class="fas fa-book me-2"></i>Materi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="quiz.php">
                                <i class="fas fa-question-circle me-2"></i>Quiz
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tugas.php">
                                <i class="fas fa-tasks me-2"></i>Tugas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="nilai.php">
                                <i class="fas fa-star me-2"></i>Nilai
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../../logout.php" id="logoutBtn">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4 content-wrapper">
                <div class="mb-4 Title">Materi</div>

                <!-- Class Selection -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-filter me-2"></i>Filter Kelas
            </div>
            <div class="card-body">
                <form method="GET" id="classForm">
                    <div class="form-group">
                        <label class="form-label">--Pilih Kelas--</label>
                        <select class="form-select" name="class_id" onchange="this.form.submit()">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['id']); ?>" 
                                        <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['nama_kelas']); ?> 
                                    (<?php echo htmlspecialchars($class['tahun_ajaran']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                
                <?php if ($selected_class): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Menampilkan materi untuk kelas: <strong><?php echo htmlspecialchars($class_name); ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_class): ?>
            <?php if (!empty($materials)): ?>
                <!-- Materials Grid -->
                <div class="material-grid">
                    <?php foreach ($materials as $material): ?>
                        <div class="material-card">
                            <div class="material-header">
                                <h3 class="material-title"><?php echo htmlspecialchars($material['judul']); ?></h3>
                                <div class="material-meta">
                                    <span><i class="fas fa-user"></i><?php echo htmlspecialchars($material['guru_nama']); ?></span>
                                    <span><i class="fas fa-calendar"></i><?php echo date('d M Y', strtotime($material['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="material-body">
                                <p class="material-description">
                                    <?php echo htmlspecialchars($material['deskripsi']); ?>
                                </p>
                                <div class="material-actions">
                                    <a href="../../uploads/materi/<?php echo htmlspecialchars($material['file_path']); ?>" 
                                       class="btn btn-primary btn-sm" 
                                       target="_blank"
                                       title="Unduh Materi">
                                        <i class="fas fa-download me-2"></i>Unduh Materi
                                    </a>
                                    <button class="btn btn-outline btn-sm" 
                                            onclick="viewMaterialInfo('<?php echo htmlspecialchars($material['judul']); ?>', '<?php echo htmlspecialchars($material['deskripsi']); ?>', '<?php echo htmlspecialchars($material['guru_nama']); ?>', '<?php echo date('d M Y H:i', strtotime($material['created_at'])); ?>')"
                                            title="Lihat Detail">
                                        <i class="fas fa-info-circle me-2"></i>Detail
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3>Belum Ada Materi</h3>
                    <p>Materi untuk kelas ini belum tersedia. Silakan periksa kembali nanti.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- No Class Selected -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>Pilih Kelas</h3>
                <p>Silakan pilih kelas untuk melihat materi pembelajaran yang tersedia.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Mobile sidebar toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('show');
            
            if (window.innerWidth <= 768) {
                document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : 'auto';
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        mobileToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        // Close sidebar when clicking nav links on mobile
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeSidebar();
                document.body.style.overflow = 'auto';
            }
        });

        // Material info modal function
        function viewMaterialInfo(title, description, teacher, date) {
            alert(`Judul: ${title}\n\nDeskripsi: ${description}\n\nDosen: ${teacher}\n\nTanggal Upload: ${date}`);
        }

        // Form submission loading state
        document.getElementById('classForm').addEventListener('submit', function() {
            const select = this.querySelector('select');
            select.disabled = true;
            select.innerHTML = '<option>Loading...</option>';
        });

        // Smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading animation to download buttons
        document.querySelectorAll('a[href*="uploads"]').forEach(button => {
            button.addEventListener('click', function() {
                const icon = this.querySelector('i');
                const originalClass = icon.className;
                icon.className = 'fas fa-spinner fa-spin';
                
                setTimeout(() => {
                    icon.className = originalClass;
                }, 2000);
            });
        });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 

    <!-- SweetAlert Confirmation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const logoutLink = document.getElementById('logoutBtn');
            if (logoutLink) {
                logoutLink.addEventListener('click', function (e) {
                    e.preventDefault();

                    Swal.fire({
                        title: 'Apakah Anda ingin keluar?',
                        text: "Anda akan meninggalkan sesi ini.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#333446',
                        cancelButtonColor: '#7F8CAA',
                        confirmButtonText: 'Ya, Keluar',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '../../logout.php';
                        }
                    });
                });
            }

            // Confirm Delete Function
            window.confirmDelete = function(materialId) {
                Swal.fire({
                    title: 'Yakin hapus materi ini?',
                    text: "Data akan dihapus permanen!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#333446',
                    cancelButtonColor: '#7F8CAA',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `delete_materi.php?id=${materialId}`;
                    }
                });
            };
        });
    </script>
</body>
</html>
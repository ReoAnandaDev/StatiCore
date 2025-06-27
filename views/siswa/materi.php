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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c5282;
            --secondary: #4299e1;
            --info: #3B82F6;
            --white: #FFFFFF;
            --gray-200: #E5E7EB;
            --gray-500: #6B7280;
            --gray-700: #374151;
            --border-radius-sm: 8px;
            --border-radius-lg: 16px;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: var(--primary);
        }
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
        }
        .card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        .card:hover {
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        .material-card:hover {
            transform: translateY(-4px);
        }
        .main-content {
            padding: 1.5rem;
        }
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }
        .material-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        /* BARU: CSS untuk Hamburger & Overlay */
        .mobile-menu-toggle { display: none; }
        .sidebar-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040; /* Di bawah sidebar */
            opacity: 0; visibility: hidden;
            transition: all 0.3s ease;
        }
        .sidebar-overlay.show { opacity: 1; visibility: visible; }
        
        /* RESPONSIVE STYLES */
        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                height: 100%;
                width: 280px;
                z-index: 1050; /* Paling atas */
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                width: 100%;
                margin-left: 0 !important;
                padding: 1rem;
            }
            /* DIUBAH: Posisi tombol hamburger ke kiri atas */
            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1100;
                background-color: var(--primary);
                color: white;
                border: none;
                border-radius: 50%;
                width: 44px;
                height: 44px;
                font-size: 1rem;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            }
            .page-title {
                margin-top: 3.5rem;
                font-size: 1.5rem;
            }
            .material-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="container-fluid">
        <div class="row">
            <div id="sidebar" class="col-md-3 col-lg-2 px-0 sidebar"> <div class="p-3">
                    <h4 class="px-2 my-3"><i class="fas fa-chart-line me-2"></i>StatiCore</h4>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="materi.php"><i class="fas fa-book me-2"></i>Materi</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="quiz.php"><i class="fas fa-question-circle me-2"></i>Quiz</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tugas.php"><i class="fas fa-tasks me-2"></i>Tugas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="nilai.php"><i class="fas fa-star me-2"></i>Nilai</a>
                        </li>
                        <li class="nav-item mt-auto">
                            <a class="nav-link" href="../../logout.php" id="logoutBtn"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                        </li>
                    </ul>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content"> <h1 class="page-title">Materi Pembelajaran</h1>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Kelas</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" id="classForm">
                            <div class="form-group">
                                <label for="class_id" class="form-label">Pilih Kelas untuk Melihat Materi</label>
                                <select class="form-select" name="class_id" id="class_id" onchange="this.form.submit()">
                                    <option value="">-- Semua Kelas --</option>
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
                    </div>
                </div>

                <?php if ($selected_class): ?>
                    <?php if (!empty($materials)): ?>
                        <div class="material-grid">
                            <?php foreach ($materials as $material): ?>
                                <div class="card material-card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($material['judul']); ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted">Oleh: <?php echo htmlspecialchars($material['guru_nama']); ?></h6>
                                        <p class="card-text small text-muted">Diupload: <?php echo date('d M Y', strtotime($material['created_at'])); ?></p>
                                        <p class="card-text"><?php echo substr(htmlspecialchars($material['deskripsi']), 0, 100) . '...'; ?></p>
                                        <a href="detail_materi.php?id=<?php echo $material['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye me-2"></i>Lihat Detail</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state card">
                            <div class="card-body">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h3>Belum Ada Materi</h3>
                                <p>Materi untuk kelas ini belum tersedia.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state card">
                         <div class="card-body">
                             <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h3>Pilih Kelas</h3>
                            <p>Silakan pilih kelas dari daftar di atas untuk melihat materi.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // DIHAPUS: Skrip lama yang tidak berfungsi
        
        // BARU: Skrip sidebar yang berfungsi dan konsisten
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const menuToggle = document.getElementById('mobileToggle');

        if(sidebar && overlay && menuToggle) {
            const toggleSidebar = () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('show');
            };
            menuToggle.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth > 767.98 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                overlay.classList.remove('show');
            }
        });
        
        // Skrip konfirmasi logout
        const logoutLink = document.getElementById('logoutBtn');
        if (logoutLink) {
            logoutLink.addEventListener('click', function (e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Apakah Anda ingin keluar?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--primary)',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Keluar',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = logoutLink.href;
                    }
                });
            });
        }
    });
    </script>
</body>
</html>
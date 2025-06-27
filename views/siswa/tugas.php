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

// Get all tasks for the student
$stmt = $conn->prepare("
    SELECT t.*, k.nama_kelas, jt.nama as jenis_tugas, u.nama_lengkap as nama_guru,
           (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id = t.id AND pt.siswa_id = ?) as sudah_dikumpul,
           t.file_path
    FROM tugas t
    JOIN kelas k ON t.kelas_id = k.id
    JOIN jenis_tugas jt ON t.jenis_tugas_id = jt.id
    JOIN users u ON t.guru_id = u.id
    JOIN siswa_kelas sk ON t.kelas_id = sk.kelas_id
    WHERE sk.siswa_id = ?
    ORDER BY t.batas_pengumpulan DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugas - StatiCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c5282;
            --secondary: #4299e1;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-800: #1F2937;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --border-radius-sm: 8px;
            --border-radius-lg: 16px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
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

        .main-content {
            padding: 1.5rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }

        .task-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .task-card .card-header {
            background-color: transparent;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: var(--primary);
        }

        /* BARU: CSS untuk Hamburger & Overlay */
        .mobile-menu-toggle {
            display: none;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                left: -280px;
                height: 100%;
                width: 280px;
                z-index: 1050;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                width: 100%;
                margin-left: 0 !important;
                padding: 1rem;
            }

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
            }

            .page-title {
                margin-top: 3.5rem;
                font-size: 1.5rem;
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
            <div id="sidebar" class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h4 class="px-2 my-3"><i class="fas fa-chart-line me-2"></i>StatiCore</h4>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="materi.php"><i class="fas fa-book me-2"></i>Materi</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="quiz.php"><i class="fas fa-question-circle me-2"></i>Quiz</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="tugas.php"><i class="fas fa-tasks me-2"></i>Tugas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="nilai.php"><i class="fas fa-star me-2"></i>Nilai</a>
                        </li>
                        <li class="nav-item mt-auto">
                            <a class="nav-link" href="../../logout.php" id="logoutBtn"><i
                                    class="fas fa-sign-out-alt me-2"></i>Logout</a>
                        </li>
                    </ul>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <h1 class="page-title">Daftar Tugas</h1>

                <?php if (empty($tasks)): ?>
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                            <h4>Tidak Ada Tugas</h4>
                            <p class="text-muted">Saat ini tidak ada tugas yang perlu dikerjakan.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card card">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo htmlspecialchars($task['judul']); ?></h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo htmlspecialchars($task['deskripsi']); ?></p>
                                <ul class="list-group list-group-flush mb-3">
                                    <li class="list-group-item"><strong>Kelas:</strong>
                                        <?php echo htmlspecialchars($task['nama_kelas']); ?></li>
                                    <li class="list-group-item"><strong>Dosen:</strong>
                                        <?php echo htmlspecialchars($task['nama_guru']); ?></li>
                                    <li class="list-group-item"><strong>Batas Waktu:</strong> <span
                                            class="text-danger"><?php echo date('d M Y, H:i', strtotime($task['batas_pengumpulan'])); ?></span>
                                    </li>
                                </ul>
                                <?php
                                $status_class = $task['sudah_dikumpul'] > 0 ? 'success' : (new DateTime() > new DateTime($task['batas_pengumpulan']) ? 'danger' : 'warning');
                                $status_text = $task['sudah_dikumpul'] > 0 ? 'Sudah Dikumpulkan' : (new DateTime() > new DateTime($task['batas_pengumpulan']) ? 'Terlambat' : 'Belum Dikumpulkan');
                                ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    <a href="kumpul_tugas.php?id=<?php echo $task['id']; ?>" class="btn btn-primary btn-sm">
                                        <?php echo $task['sudah_dikumpul'] > 0 ? 'Lihat Pengumpulan' : 'Kumpulkan Tugas'; ?>
                                        <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // BARU: Logika untuk Hamburger Menu
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('mobileToggle');

            if (sidebar && overlay && menuToggle) {
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
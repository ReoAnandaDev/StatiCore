<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is student
$auth->checkSession();
$auth->requireRole('siswa');

$conn = $db->getConnection();

// Get material ID from URL
$materi_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$materi_id) {
    header('Location: dashboard.php');
    exit;
}

// Get material details
$stmt = $conn->prepare("
    SELECT m.*, k.nama_kelas, u.nama_lengkap as guru_nama
    FROM materi m
    JOIN kelas k ON m.kelas_id = k.id
    JOIN users u ON m.guru_id = u.id
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE m.id = ? AND sk.siswa_id = ?
");
$stmt->execute([$materi_id, $_SESSION['user_id']]);
$materi = $stmt->fetch();

if (!$materi) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Materi - StatiCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c5282;
            --secondary: #4299e1;
            --accent: #f6ad55;
            --light: #f7fafc;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--gray-100);
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

        /* Main Content */
        .content-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            padding: 20px 24px;
            border-bottom: none;
        }

        .card-body {
            padding: 24px;
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

        .material-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-200);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-600);
        }

        .meta-item i {
            color: var(--primary);
        }

        .material-content {
            color: var(--gray-700);
            line-height: 1.7;
        }

        .material-content img {
            max-width: 100%;
            height: auto;
            border-radius: var(--border-radius-md);
            margin: 16px 0;
        }

        .btn {
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        /* CSS untuk Hamburger & Overlay */
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
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* Responsive Design */
        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                height: 100%;
                width: 280px;
                z-index: 1000;
                transition: left 0.3s ease-in-out;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                width: 100%;
                margin-left: 0 !important;
                padding: 1rem;
            }

            .material-meta {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            /* DIUBAH: Mengubah posisi tombol hamburger ke kiri atas */
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
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            }

            /* BARU: Memberi jarak agar judul tidak tertutup tombol */
            .page-title {
                margin-top: 3rem;
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <button class="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay"></div>

    <div class="container-fluid">
        <div class="row">
            <div id="sidebar" class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h4 class="px-2 my-3"><i class="fas fa-chart-line me-2"></i>StatiCore</h4>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
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
                        <li class="nav-item mt-auto">
                            <a class="nav-link" href="../../logout.php" id="logoutBtn">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <h1 class="page-title">Detail Materi</h1>

                <div class="content-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0 fs-5">
                            <i class="fas fa-book-open me-2"></i>
                            <?php echo htmlspecialchars($materi['judul']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="material-meta">
                            <div class="meta-item">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span>Kelas: <b><?php echo htmlspecialchars($materi['nama_kelas']); ?></b></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-user-tie"></i>
                                <span>Dosen: <b><?php echo htmlspecialchars($materi['guru_nama']); ?></b></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Diupload:
                                    <b><?php echo date('d M Y, H:i', strtotime($materi['created_at'])); ?></b></span>
                            </div>
                        </div>

                        <div class="material-content">
                            <?php echo nl2br(htmlspecialchars($materi['deskripsi'])); ?>

                            <?php if (!empty($materi['file_path'])): ?>
                                <div class="mt-4">
                                    <a href="../../uploads/materi/<?php echo htmlspecialchars($materi['file_path']); ?>"
                                        class="btn btn-primary" target="_blank">
                                        <i class="fas fa-download me-2"></i>
                                        Download Lampiran
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="materi.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Kembali ke Daftar Materi
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Logika untuk Hamburger Menu
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const menuToggle = document.querySelector('.mobile-menu-toggle');

            const toggleSidebar = () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('show');
            };

            if (menuToggle) {
                menuToggle.addEventListener('click', toggleSidebar);
            }
            if (overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth > 767.98) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('show');
                }
            });

            // Logika untuk konfirmasi logout
            const logoutLink = document.getElementById('logoutBtn');
            if (logoutLink) {
                logoutLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Apakah Anda ingin keluar?',
                        text: "Anda akan meninggalkan sesi ini.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--primary)',
                        cancelButtonColor: 'var(--gray-600)',
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
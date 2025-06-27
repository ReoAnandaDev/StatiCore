<?php
// Set zona waktu Indonesia
date_default_timezone_set('Asia/Jakarta');

require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());
$auth->checkSession();
$auth->requireRole('siswa');
$conn = $db->getConnection();

// Get quiz ID from URL
$quiz_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$quiz_id) {
    header('Location: quiz.php');
    exit;
}

// Get quiz details
$stmt = $conn->prepare("
    SELECT q.*, k.nama_kelas, k.tahun_ajaran, u.nama_lengkap as guru_nama
    FROM quiz q
    JOIN kelas k ON q.kelas_id = k.id
    JOIN users u ON q.guru_id = u.id
    WHERE q.id = ?
");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: quiz.php');
    exit;
}

// Verify if the student is in the class associated with the quiz
$stmt = $conn->prepare("SELECT COUNT(*) FROM siswa_kelas WHERE siswa_id = ? AND kelas_id = ?");
$stmt->execute([$_SESSION['user_id'], $quiz['kelas_id']]);
$is_student_in_class = $stmt->fetchColumn();

if (!$is_student_in_class) {
    // For now, we'll just allow viewing basic info, but prevent starting quiz
}

// Check if student has already attempted the quiz
$stmt = $conn->prepare("SELECT COUNT(*) FROM jawaban_siswa js JOIN soal_quiz sq ON js.soal_id = sq.id WHERE js.siswa_id = ? AND sq.quiz_id = ?");
$stmt->execute([$_SESSION['user_id'], $quiz_id]);
$already_done = $stmt->fetchColumn();

// Check quiz time frame
$now = new DateTime();
$start = new DateTime($quiz['waktu_mulai']);
$end = new DateTime($quiz['waktu_selesai']);

$status = '';
$status_class = '';
$can_start = false;

if ($now < $start) {
    $status = 'Belum Dimulai';
    $status_class = 'warning';
} elseif ($now > $end) {
    $status = 'Selesai';
    $status_class = 'secondary';
} else {
    // Quiz is currently active
    $status = 'Sedang Berlangsung';
    $status_class = 'success';
    if (!$already_done) {
        $can_start = true;
    }
}

// Get total questions for this quiz
$stmt = $conn->prepare("SELECT COUNT(*) FROM soal_quiz WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$total_questions = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Quiz - StatiCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c5282;
            --secondary: #4299e1;
            --light: #f7fafc;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-200: #E5E7EB;
            --gray-600: #4B5563;
            --gray-700: #374151;
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

        .main-content {
            padding: 1.5rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Quiz Detail Card */
        .quiz-detail-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 24px;
            overflow: hidden;
        }

        .quiz-detail-card .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-bottom: none;
            padding: 24px;
        }

        .quiz-detail-card .card-body {
            padding: 24px;
        }

        .quiz-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .quiz-info-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .quiz-info-list li:last-child {
            border-bottom: none;
        }

        .quiz-info-list i {
            color: var(--primary);
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        /* Status Badge */
        .status-badge {
            padding: 8px 16px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-badge.warning {
            background: #FEF3C7;
            color: #92400E;
        }

        .status-badge.success {
            background: #D1FAE5;
            color: #065F46;
        }

        .status-badge.secondary {
            background: #E5E7EB;
            color: #374151;
        }

        .status-badge.danger {
            background: #FEE2E2;
            color: #991B1B;
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
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* Responsive Styles */
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

            .page-title {
                margin-top: 3rem;
                font-size: 1.5rem;
            }

            .quiz-detail-card .card-body {
                padding: 1rem;
            }

            .header-flex-mobile {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 1rem;
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
                            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="materi.php"><i class="fas fa-book me-2"></i>Materi</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="quiz.php"><i
                                    class="fas fa-question-circle me-2"></i>Quiz</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tugas.php"><i class="fas fa-tasks me-2"></i>Tugas</a>
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
                <div class="d-flex justify-content-between align-items-center mb-4 header-flex-mobile">
                    <h1 class="page-title mb-0">Detail Quiz</h1>
                    <a href="quiz.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali
                    </a>
                </div>

                <div class="quiz-detail-card">
                    <div class="card-header">
                        <h5 class="mb-0 fs-5"><?php echo htmlspecialchars($quiz['judul']); ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text mb-4 text-muted"><?php echo htmlspecialchars($quiz['deskripsi']); ?></p>
                        <ul class="quiz-info-list">
                            <li>
                                <i class="fas fa-chalkboard"></i>
                                <span><b>Kelas:</b> <?php echo htmlspecialchars($quiz['nama_kelas']); ?>
                                    (<?php echo htmlspecialchars($quiz['tahun_ajaran']); ?>)</span>
                            </li>
                            <li>
                                <i class="fas fa-user-tie"></i>
                                <span><b>Dosen:</b> <?php echo htmlspecialchars($quiz['guru_nama']); ?></span>
                            </li>
                            <li>
                                <i class="fas fa-stopwatch"></i>
                                <span><b>Durasi:</b> <?php echo htmlspecialchars($quiz['durasi']); ?> menit</span>
                            </li>
                            <li>
                                <i class="fas fa-list-ol"></i>
                                <span><b>Jumlah Soal:</b> <?php echo $total_questions; ?></span>
                            </li>
                            <li>
                                <i class="fas fa-calendar-alt"></i>
                                <span><b>Mulai:</b>
                                    <?php echo date('d M Y, H:i', strtotime($quiz['waktu_mulai'])); ?></span>
                            </li>
                            <li>
                                <i class="fas fa-calendar-check"></i>
                                <span><b>Selesai:</b>
                                    <?php echo date('d M Y, H:i', strtotime($quiz['waktu_selesai'])); ?></span>
                            </li>
                            <li>
                                <i class="fas fa-info-circle"></i>
                                <span><b>Status:</b> <span
                                        class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span></span>
                            </li>
                        </ul>

                        <?php if ($is_student_in_class && $can_start): ?>
                            <div class="mt-4 text-center">
                                <a href="kerjakan_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary btn-lg">
                                    <i class="fas fa-play me-2"></i>Mulai Kerjakan Quiz
                                </a>
                            </div>
                        <?php elseif ($is_student_in_class && $already_done): ?>
                            <div class="mt-4">
                                <div class="alert alert-success d-flex align-items-center" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <div>
                                        Anda sudah mengerjakan quiz ini.
                                    </div>
                                </div>
                            </div>
                        <?php elseif (!$is_student_in_class): ?>
                            <div class="mt-4">
                                <div class="alert alert-danger d-flex align-items-center" role="alert">
                                    <i class="fas fa-times-circle me-2"></i>
                                    <div>
                                        Anda tidak terdaftar pada kelas untuk quiz ini.
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // BARU: Logika untuk Hamburger Menu
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
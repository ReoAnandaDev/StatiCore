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
    // Optional: Redirect or show error if student is not in the class
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
            background-color: var(--gray-50);
            color: var(--gray-800);
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

        /* Action Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: var(--gray-600);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--gray-700);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .quiz-detail-card .card-body {
                padding: 20px;
            }

            .quiz-info-list li {
                padding: 10px 0;
            }
        }

        @media (max-width: 768px) {
            .Title {
                font-size: 1.25rem;
            }

            .quiz-detail-card .card-header {
                padding: 20px;
            }

            .btn {
                width: 100%;
                margin-top: 8px;
            }

            .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
            }
        }

        @media (max-width: 576px) {
            .quiz-detail-card .card-body {
                padding: 16px;
            }

            .quiz-info-list li {
                font-size: 0.9rem;
            }

            .status-badge {
                font-size: 0.8rem;
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
                            <a class="nav-link" href="materi.php">
                                <i class="fas fa-book me-2"></i>Materi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="quiz.php">
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
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="Title">Detail Quiz</div>
                    <a href="quiz.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali
                    </a>
                </div>

                <div class="quiz-detail-card">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo htmlspecialchars($quiz['judul']); ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text mb-4"><?php echo htmlspecialchars($quiz['deskripsi']); ?></p>
                        <ul class="quiz-info-list">
                            <li>
                                <i class="fas fa-chalkboard"></i>
                                <span>Kelas: <?php echo htmlspecialchars($quiz['nama_kelas']); ?>
                                    (<?php echo htmlspecialchars($quiz['tahun_ajaran']); ?>)</span>
                            </li>
                            <li>
                                <i class="fas fa-user"></i>
                                <span>Dosen: <?php echo htmlspecialchars($quiz['guru_nama']); ?></span>
                            </li>
                            <li>
                                <i class="fas fa-clock"></i>
                                <span>Durasi: <?php echo htmlspecialchars($quiz['durasi']); ?> menit</span>
                            </li>
                            <li>
                                <i class="fas fa-question-circle"></i>
                                <span>Jumlah Soal: <?php echo $total_questions; ?></span>
                            </li>
                            <li>
                                <i class="fas fa-calendar"></i>
                                <span>Mulai: <?php echo date('d/m/Y H:i', strtotime($quiz['waktu_mulai'])); ?></span>
                            </li>
                            <li>
                                <i class="fas fa-calendar-check"></i>
                                <span>Selesai:
                                    <?php echo date('d/m/Y H:i', strtotime($quiz['waktu_selesai'])); ?></span>
                            </li>
                            <li>
                                <i class="fas fa-info-circle"></i>
                                <span>Status: <span
                                        class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span></span>
                            </li>
                        </ul>

                        <?php if ($is_student_in_class && $can_start): ?>
                            <div class="mt-4">
                                <a href="kerjakan_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-play me-2"></i>Mulai Quiz
                                </a>
                            </div>
                        <?php elseif ($is_student_in_class && $already_done): ?>
                            <div class="mt-4">
                                <div class="status-badge success">
                                    <i class="fas fa-check-circle me-2"></i>Anda Sudah Mengerjakan Quiz Ini
                                </div>
                            </div>
                        <?php elseif (!$is_student_in_class): ?>
                            <div class="mt-4">
                                <div class="status-badge danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>Anda Tidak Terdaftar di Kelas Ini
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        confirmButtonColor: '#2c5282',
                        cancelButtonColor: '#6B7280',
                        confirmButtonText: 'Ya, Keluar',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '../../logout.php';
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>
<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is teacher
$auth->checkSession();
$auth->requireRole('guru');

$conn = $db->getConnection();
$message = '';
$message_type = 'info';

// Get quiz ID from URL
$quiz_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$quiz_id) {
    header('Location: kelola_quiz.php');
    exit;
}

// Get quiz details
try {
    $stmt = $conn->prepare("
        SELECT q.*, k.nama_kelas, k.tahun_ajaran
        FROM quiz q
        JOIN kelas k ON q.kelas_id = k.id
        WHERE q.id = ? AND q.guru_id = ?
    ");
    $stmt->execute([$quiz_id, $_SESSION['user_id']]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        header('Location: kelola_quiz.php');
        exit;
    }
} catch (PDOException $e) {
    $message = "Error mengambil data quiz: " . $e->getMessage();
    $message_type = 'danger';
}

// Get quiz questions
try {
    $stmt = $conn->prepare("
        SELECT sq.*, 
               (SELECT COUNT(*) FROM jawaban_siswa js WHERE js.soal_id = sq.id) as total_jawaban
        FROM soal_quiz sq
        WHERE sq.quiz_id = ?
        ORDER BY sq.id
    ");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error mengambil soal quiz: " . $e->getMessage();
    $message_type = 'danger';
    $questions = [];
}

// Get students in the class
try {
    $stmt = $conn->prepare("
        SELECT u.id, u.nama_lengkap
        FROM users u
        JOIN siswa_kelas sk ON u.id = sk.siswa_id
        WHERE sk.kelas_id = ?
        ORDER BY u.nama_lengkap
    ");
    $stmt->execute([$quiz['kelas_id']]);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error mengambil data siswa: " . $e->getMessage();
    $message_type = 'danger';
    $students = [];
}

// Get student answers
try {
    $stmt = $conn->prepare("
        SELECT js.*, u.nama_lengkap, sq.pertanyaan, sq.tipe
        FROM jawaban_siswa js
        JOIN users u ON js.siswa_id = u.id
        JOIN soal_quiz sq ON js.soal_id = sq.id
        WHERE sq.quiz_id = ?
        ORDER BY u.nama_lengkap, sq.id
    ");
    $stmt->execute([$quiz_id]);
    $answers = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error mengambil jawaban siswa: " . $e->getMessage();
    $message_type = 'danger';
    $answers = [];
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_grade') {
    $siswa_id = filter_input(INPUT_POST, 'siswa_id', FILTER_SANITIZE_NUMBER_INT);
    $soal_id = filter_input(INPUT_POST, 'soal_id', FILTER_SANITIZE_NUMBER_INT);
    $nilai = filter_input(INPUT_POST, 'nilai', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    // Validasi input
    if (!$siswa_id || !$soal_id) {
        $message = "Data tidak valid.";
        $message_type = 'danger';
    } elseif ($nilai < 0 || $nilai > 100) {
        $message = "Nilai harus antara 0 dan 100.";
        $message_type = 'warning';
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE jawaban_siswa 
                SET nilai = ?, updated_at = NOW()
                WHERE siswa_id = ? AND soal_id = ?
            ");
            $stmt->execute([$nilai, $siswa_id, $soal_id]);

            if ($stmt->rowCount() > 0) {
                $message = "Nilai berhasil diperbarui!";
                $message_type = 'success';

                // Refresh data answers after update
                $stmt = $conn->prepare("
                    SELECT js.*, u.nama_lengkap, sq.pertanyaan, sq.tipe
                    FROM jawaban_siswa js
                    JOIN users u ON js.siswa_id = u.id
                    JOIN soal_quiz sq ON js.soal_id = sq.id
                    WHERE sq.quiz_id = ?
                    ORDER BY u.nama_lengkap, sq.id
                ");
                $stmt->execute([$quiz_id]);
                $answers = $stmt->fetchAll();
            } else {
                $message = "Tidak ada perubahan atau jawaban tidak ditemukan.";
                $message_type = 'warning';
            }
        } catch (PDOException $e) {
            $message = "Error database: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Calculate quiz statistics
$total_questions = count($questions);
$total_students = count($students);
$submitted_count = 0;

foreach ($students as $student) {
    $student_answers = array_filter($answers, function ($a) use ($student) {
        return $a['siswa_id'] == $student['id'];
    });
    if (count($student_answers) > 0) {
        $submitted_count++;
    }
}
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
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-600: #6c757d;
            --gray-800: #343a40;
            --success: #198754;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #0dcaf0;
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

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.875rem;
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

        .btn-secondary {
            background: var(--gray-600);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: var(--gray-800);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.8rem;
        }

        .btn i {
            margin-right: 8px;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: none;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 24px;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            padding: 20px 24px;
            border-bottom: none;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .card-body {
            padding: 24px;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--primary);
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--border-radius-md);
            border: none;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            animation: slideInDown 0.3s ease;
        }

        .alert-success {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border-left: 4px solid var(--warning);
        }

        .alert-info {
            background: rgba(13, 202, 240, 0.1);
            color: #087990;
            border-left: 4px solid var(--info);
        }

        .alert-dismissible {
            padding-right: 50px;
            position: relative;
        }

        .btn-close {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.6;
        }

        .btn-close:hover {
            opacity: 1;
        }

        /* Quiz Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            padding: 24px;
            border-radius: var(--border-radius-lg);
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Quiz Info */
        .quiz-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .info-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
        }

        .info-label {
            font-weight: 600;
            color: var(--primary);
            margin-right: 8px;
        }

        .info-value {
            color: var(--gray-600);
        }

        /* Student Answers Section */
        .student-section {
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius-md);
            margin-bottom: 24px;
            overflow: hidden;
            background: var(--white);
        }

        .student-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
        }

        .student-name {
            font-weight: 600;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .student-name i {
            margin-right: 8px;
            color: var(--accent);
        }

        .question-item {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
        }

        .question-item:last-child {
            border-bottom: none;
        }

        .question-text {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .answer-text {
            color: var(--gray-600);
            margin-bottom: 12px;
            padding: 12px;
            background: var(--gray-100);
            border-radius: var(--border-radius-sm);
            border-left: 4px solid var(--secondary);
        }

        .no-answer {
            color: var(--gray-600);
            font-style: italic;
            background: rgba(220, 53, 69, 0.05);
            border-left-color: var(--danger);
        }

        /* Grade Form */
        .grade-form {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }

        .grade-input-group {
            display: flex;
            align-items: center;
            background: var(--white);
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            padding: 0;
            width: 140px;
            transition: border-color 0.3s ease;
        }

        .grade-input-group:focus-within {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .grade-input {
            border: none;
            padding: 8px 12px;
            width: 80px;
            background: transparent;
            font-weight: 500;
        }

        .grade-input:focus {
            outline: none;
        }

        .grade-suffix {
            padding: 8px 12px;
            background: var(--gray-100);
            border-left: 1px solid var(--gray-300);
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .save-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            border: none;
            padding: 8px 12px;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .save-btn:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: scale(1.05);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -280px;
                width: 280px;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
            }

            .sidebar.active {
                left: 0;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .content-wrapper {
                margin-left: 0 !important;
                padding: 16px;
            }

            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 15px;
                left: 20px;
                z-index: 1001;
                background-color: #3B3B1A;
                color: white;
                border: none;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                text-align: center;
                font-size: 18px;
                cursor: pointer;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-number {
                font-size: 24px;
            }

            .stat-label {
                font-size: 12px;
            }

            .quiz-info-grid {
                grid-template-columns: 1fr;
            }

            .student-section {
                overflow-x: auto;
            }

            .question-item {
                padding: 16px;
            }

            .grade-form {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .grade-input-group {
                width: 100%;
            }

            .save-btn {
                width: 100%;
            }

            .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }

            .stat-number {
                font-size: 20px;
            }

            .card-title {
                font-size: 1.1rem;
            }

            .info-label {
                font-size: 14px;
            }

            .info-value {
                font-size: 14px;
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 260px;
                z-index: 1050;
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(.4, 0, .2, 1);
                box-shadow: 2px 0 16px rgba(44, 82, 130, 0.08);
                display: block;
            }

            .sidebar.drawer-open {
                transform: translateX(0);
            }

            .sidebar .p-3 {
                padding-top: 2.5rem !important;
            }

            .sidebar-backdrop {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.25);
                z-index: 1049;
                opacity: 1;
                transition: opacity 0.3s;
            }

            .content-wrapper,
            .p-4 {
                margin-left: 0 !important;
                padding: 16px !important;
            }

            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1100;
                background: var(--primary);
                color: #fff;
                border: none;
                border-radius: 50%;
                width: 44px;
                height: 44px;
                font-size: 1.5rem;
                box-shadow: 0 2px 8px rgba(44, 82, 130, 0.08);
            }
        }

        @media (max-width: 576px) {

            .card,
            .card-body,
            .card-header {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
        }
    </style>
</head>

<body>
    <button class="mobile-menu-toggle d-lg-none" id="drawerToggle" aria-label="Buka menu">
        <i class="fas fa-bars"></i>
    </button>
    <div id="sidebarBackdrop" class="sidebar-backdrop" style="display:none;"></div>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar" id="drawerSidebar">
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
                            <a class="nav-link" href="upload_materi.php">
                                <i class="fas fa-book me-2"></i>Materi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="kelola_quiz.php">
                                <i class="fas fa-question-circle me-2"></i>Quiz
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="kelola_tugas.php">
                                <i class="fas fa-tasks me-2"></i>Tugas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="nilai_siswa.php">
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
                <div class="mb-4 Title">Detail Quiz</div>

                <!-- Back Button -->
                <div style="margin-top: 32px;">
                    <a href="kelola_quiz.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Kembali
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?= $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Quiz Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_questions; ?></div>
                        <div class="stat-label">Total Pertanyaan</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_students; ?></div>
                        <div class="stat-label">Total Mahasiswa/i</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $submitted_count; ?></div>
                        <div class="stat-label">Sudah Mengerjakan</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_students - $submitted_count; ?></div>
                        <div class="stat-label">Belum Mengerjakan</div>
                    </div>
                </div>

                <!-- Quiz Info -->
                <?php if ($quiz): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5>Informasi Quiz</h5>
                        </div>
                        <div class="card-body">
                            <h3 class="card-title"><?php echo htmlspecialchars($quiz['judul']); ?></h3>
                            <p class="mb-4"><?php echo htmlspecialchars($quiz['deskripsi']); ?></p>
                            <div class="quiz-info-grid">
                                <div class="info-item">
                                    <span class="info-label">Kelas:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($quiz['nama_kelas']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Tahun Ajaran:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($quiz['tahun_ajaran']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Waktu Mulai:</span>
                                    <span
                                        class="info-value"><?php echo date('d/m/Y H:i', strtotime($quiz['waktu_mulai'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Waktu Selesai:</span>
                                    <span
                                        class="info-value"><?php echo date('d/m/Y H:i', strtotime($quiz['waktu_selesai'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Durasi:</span>
                                    <span class="info-value"><?php echo $quiz['durasi']; ?> menit</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Questions and Answers -->
                <div class="card">
                    <div class="card-header">
                        <h5>Pertanyaan dan Jawaban Mahasiswa/i</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                            <div class="loading">
                                <div class="spinner"></div>
                                Tidak ada Mahasiswa/i yang menjawab di kelas ini.
                            </div>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <div class="student-section fade-in">
                                    <div class="student-header">
                                        <h6 class="student-name">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($student['nama_lengkap']); ?>
                                        </h6>
                                    </div>
                                    <div class="student-content">
                                        <?php if (empty($questions)): ?>
                                            <div class="question-item">
                                                <p class="text-muted">Belum ada pertanyaan dalam quiz ini.</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($questions as $index => $question): ?>
                                                <?php
                                                $answer = array_filter($answers, function ($a) use ($student, $question) {
                                                    return $a['siswa_id'] == $student['id'] && $a['soal_id'] == $question['id'];
                                                });
                                                $answer = reset($answer);
                                                ?>
                                                <div class="question-item">
                                                    <div class="question-text">
                                                        <i class="fas fa-question-circle"
                                                            style="color: var(--secondary); margin-right: 8px;"></i>
                                                        Pertanyaan <?php echo $index + 1; ?>:
                                                        <?php echo htmlspecialchars($question['pertanyaan']); ?>
                                                    </div>

                                                    <div class="answer-text <?php echo $answer ? '' : 'no-answer'; ?>">
                                                        <strong>Jawaban:</strong>
                                                        <?php echo $answer ? htmlspecialchars($answer['jawaban']) : 'Belum dijawab'; ?>
                                                    </div>

                                                    <?php if ($answer): ?>
                                                        <form method="POST" class="grade-form">
                                                            <input type="hidden" name="action" value="update_grade">
                                                            <input type="hidden" name="siswa_id" value="<?php echo $student['id']; ?>">
                                                            <input type="hidden" name="soal_id" value="<?php echo $question['id']; ?>">

                                                            <div class="grade-input-group">
                                                                <input type="number" class="grade-input" name="nilai"
                                                                    value="<?php echo $answer['nilai'] ?? '0'; ?>" min="0" max="100"
                                                                    step="0.1" placeholder="0">
                                                                <span class="grade-suffix">/100</span>
                                                            </div>

                                                            <button type="submit" class="save-btn">
                                                                <i class="fas fa-save"></i>
                                                                Simpan
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <div style="color: var(--gray-600); font-style: italic; margin-top: 8px;">
                                                            <i class="fas fa-info-circle"></i>
                                                            Mahasiswa/i belum mengerjakan soal ini
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                </main>
            </div>

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

                    // Drawer sidebar logic
                    const drawerToggle = document.getElementById('drawerToggle');
                    const sidebar = document.getElementById('drawerSidebar');
                    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
                    function openDrawer() {
                        sidebar.classList.add('drawer-open');
                        sidebarBackdrop.style.display = 'block';
                    }
                    function closeDrawer() {
                        sidebar.classList.remove('drawer-open');
                        sidebarBackdrop.style.display = 'none';
                    }
                    drawerToggle.addEventListener('click', function () {
                        openDrawer();
                    });
                    sidebarBackdrop.addEventListener('click', function () {
                        closeDrawer();
                    });
                    // Close drawer on menu click (mobile only)
                    sidebar.querySelectorAll('.nav-link').forEach(function (link) {
                        link.addEventListener('click', function () {
                            if (window.innerWidth < 992) closeDrawer();
                        });
                    });
                    // Close drawer on resize to desktop
                    window.addEventListener('resize', function () {
                        if (window.innerWidth >= 992) closeDrawer();
                    });
                });
            </script>
</body>

</html>
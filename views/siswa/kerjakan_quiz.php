<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is student
$auth->checkSession();
$auth->requireRole('siswa');

$conn = $db->getConnection();

// Get quiz ID from URL
$quiz_id = isset($_GET['id']) ? $_GET['id'] : null;
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

// Check if quiz is still active
$now = new DateTime();
$start = new DateTime($quiz['waktu_mulai']);
$end = new DateTime($quiz['waktu_selesai']);
if ($now < $start || $now > $end) {
    header('Location: quiz.php');
    exit;
}

// Cek apakah siswa sudah pernah mengerjakan quiz ini
$stmt = $conn->prepare("SELECT COUNT(*) FROM jawaban_siswa js JOIN soal_quiz sq ON js.soal_id = sq.id WHERE js.siswa_id = ? AND sq.quiz_id = ?");
$stmt->execute([$_SESSION['user_id'], $quiz_id]);
$already_done = $stmt->fetchColumn();
if ($already_done > 0) {
    header('Location: quiz.php?error=1');
    exit;
}

// Get all questions
$stmt = $conn->prepare("SELECT * FROM soal_quiz WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all options for all questions
$stmt = $conn->prepare("SELECT * FROM pilihan_jawaban WHERE soal_id IN (SELECT id FROM soal_quiz WHERE quiz_id = ?)");
$stmt->execute([$quiz_id]);
$options_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
$options_map = [];
foreach ($options_all as $opt) {
    $options_map[$opt['soal_id']][] = $opt;
}

// Get current question index
$total_questions = count($questions);
$q_index = isset($_GET['q']) ? intval($_GET['q']) : 0;
if ($q_index < 0) $q_index = 0;
if ($q_index >= $total_questions) $q_index = $total_questions - 1;
$current_question = $questions[$q_index];

// Load previous answers from session
if (!isset($_SESSION['quiz_answers'][$quiz_id])) {
    $_SESSION['quiz_answers'][$quiz_id] = [];
}
$answers = $_SESSION['quiz_answers'][$quiz_id];

// Handle answer navigation and submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jawaban = $_POST['answer'] ?? '';
    $soal_id = $current_question['id'];
    $answers[$soal_id] = $jawaban;
    $_SESSION['quiz_answers'][$quiz_id] = $answers;

    if (isset($_POST['next'])) {
        $q_index++;
        header('Location: kerjakan_quiz.php?id=' . $quiz_id . '&q=' . $q_index);
        exit;
    } elseif (isset($_POST['prev'])) {
        $q_index--;
        header('Location: kerjakan_quiz.php?id=' . $quiz_id . '&q=' . $q_index);
        exit;
    } elseif (isset($_POST['submit_quiz'])) {
        // Simpan semua jawaban ke database dan hitung nilai
        try {
            $conn->beginTransaction();
            
            // Hitung total soal dan jawaban benar
            $total_soal = count($questions);
            $jawaban_benar = 0;
            $nilai_per_soal = 100 / $total_soal; // Hitung nilai per soal di awal
            
            foreach ($answers as $soal_id => $jawaban) {
                // Cek jawaban benar untuk soal ini
                $stmt = $conn->prepare("
                    SELECT pilihan, is_benar 
                    FROM pilihan_jawaban 
                    WHERE soal_id = ?
                ");
                $stmt->execute([$soal_id]);
                $pilihan_benar = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Hitung nilai untuk soal ini
                $nilai_soal = 0;
                foreach ($pilihan_benar as $pilihan) {
                    if ($pilihan['is_benar'] == 1 && $pilihan['pilihan'] == $jawaban) {
                        $jawaban_benar++;
                        $nilai_soal = $nilai_per_soal; // Gunakan nilai per soal yang sudah dihitung
                        break;
                    }
                }
                
                // Simpan jawaban dan nilai ke database
                $stmt = $conn->prepare("
                    INSERT INTO jawaban_siswa (siswa_id, soal_id, jawaban, nilai, waktu_mulai, waktu_selesai)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE jawaban = ?, nilai = ?, waktu_selesai = NOW()
                ");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $soal_id, 
                    $jawaban, 
                    $nilai_soal,
                    $jawaban,
                    $nilai_soal
                ]);
            }
            
            $conn->commit();
            unset($_SESSION['quiz_answers'][$quiz_id]);
            // Tampilkan hasil nilai langsung di halaman ini
            $show_result = true;
            $final_score = $jawaban_benar * $nilai_per_soal;
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = "Terjadi kesalahan saat menyimpan jawaban: " . $e->getMessage();
            // Tampilkan pesan error di halaman ini
        }
    }
}

// Get student's previous answer for this question
$student_answer = $answers[$current_question['id']] ?? '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mengerjakan Quiz - StatiCore</title>
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
            min-height: 100vh;
        }

        .Title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 24px;
            color: var(--primary);
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            min-height: 100vh;
            transition: all 0.3s ease;
            position: fixed;
            width: inherit;
            max-width: inherit;
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
        .main-content {
            margin-left: 16.666667%;
            padding: 2rem;
            min-height: 100vh;
            position: relative;
        }

        /* Quiz Timer */
        .quiz-timer {
            position: fixed;
            top: 24px;
            right: 24px;
            background: white;
            padding: 12px 20px;
            border-radius: var(--border-radius-md);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1001;
            border: 2px solid var(--primary);
            font-weight: 600;
            font-size: 16px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quiz-timer.warning {
            background: #fff3cd;
            border-color: var(--warning);
            animation: pulse 1s infinite;
        }

        .quiz-timer.danger {
            background: #f8d7da;
            border-color: var(--danger);
            animation: pulse 0.5s infinite;
        }

        /* Quiz Info Card */
        .quiz-info-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 24px;
        }

        .quiz-info-card .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-bottom: none;
            padding: 20px;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
        }

        .quiz-info-card .card-body {
            padding: 24px;
        }

        .quiz-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .quiz-info-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
        }

        .quiz-info-list i {
            color: var(--primary);
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        /* Question Card */
        .question-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }

        .question-card:hover {
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .question-card .card-body {
            padding: 32px;
        }

        .question-number {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 8px 16px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            display: inline-block;
            margin-bottom: 24px;
        }

        .question-text {
            font-size: 1.1rem;
            color: var(--gray-800);
            margin-bottom: 24px;
            line-height: 1.6;
        }

        /* Answer Options */
        .answer-options {
            display: grid;
            gap: 16px;
        }

        .form-check {
            margin: 0;
            padding: 16px;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-sm);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-check:hover {
            border-color: var(--secondary);
            background: var(--gray-50);
            transform: translateY(-1px);
        }

        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin: 0;
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .form-check-input:checked + .form-check-label {
            color: var(--primary);
            font-weight: 500;
        }

        .form-check-input:checked ~ .form-check {
            border-color: var(--primary);
            background: var(--gray-50);
        }

        .form-check-label {
            font-size: 1rem;
            color: var(--gray-700);
            margin: 0;
            padding-left: 2.5rem;
            cursor: pointer;
            width: 100%;
            position: relative;
        }

        .form-check-label::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1.2em;
            height: 1.2em;
            border: 2px solid var(--gray-300);
            border-radius: 50%;
            background-color: white;
            transition: all 0.2s ease;
        }

        .form-check-input:checked + .form-check-label::before {
            border-color: var(--primary);
            background-color: var(--primary);
            box-shadow: inset 0 0 0 4px white;
        }

        .form-check:hover .form-check-label::before {
            border-color: var(--secondary);
        }

        /* Navigation Buttons */
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-top: 32px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            min-width: 160px;
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

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, var(--success));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Result Alert */
        .result-alert {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            border-radius: var(--border-radius-lg);
            padding: 32px;
            margin-top: 32px;
            text-align: center;
        }

        .result-alert h4 {
            font-size: 1.5rem;
            margin-bottom: 16px;
        }

        .result-alert .score {
            font-size: 3rem;
            font-weight: 700;
            margin: 24px 0;
        }

        .result-alert .stats {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin: 24px 0;
        }

        .result-alert .stat-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 16px 32px;
            border-radius: var(--border-radius-sm);
            font-size: 1.1rem;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                position: static;
                width: 100%;
                max-width: 100%;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .quiz-timer {
                position: static;
                margin-bottom: 24px;
                width: 100%;
                justify-content: center;
            }

            .quiz-info-list {
                grid-template-columns: 1fr;
            }

            .nav-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .Title {
                font-size: 1.25rem;
            }

            .question-card .card-body {
                padding: 24px;
            }

            .question-text {
                font-size: 1rem;
            }

            .result-alert {
                padding: 24px;
            }

            .result-alert .stats {
                flex-direction: column;
                gap: 16px;
            }

            .result-alert .stat-item {
                padding: 12px 24px;
            }
        }

        @media (max-width: 576px) {
            .quiz-info-card .card-body {
                padding: 16px;
            }

            .question-number {
                font-size: 0.9rem;
                padding: 6px 12px;
            }

            .form-check {
                padding: 12px;
            }

            .form-check-label {
                font-size: 0.9rem;
            }

            .result-alert .score {
                font-size: 2.5rem;
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
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Quiz Timer -->
                <div class="quiz-timer">
                    <i class="fas fa-clock"></i>
                    <span id="timer">Loading...</span>
                </div>

                <div class="Title"><?php echo $quiz['judul']; ?></div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Quiz Info Card -->
                <div class="quiz-info-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Quiz</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text mb-4"><?php echo $quiz['deskripsi']; ?></p>
                        <ul class="quiz-info-list">
                            <li>
                                <i class="fas fa-chalkboard"></i>
                                <span>Kelas: <?php echo $quiz['nama_kelas']; ?> (<?php echo $quiz['tahun_ajaran']; ?>)</span>
                            </li>
                            <li>
                                <i class="fas fa-user"></i>
                                <span>Dosen: <?php echo $quiz['guru_nama']; ?></span>
                            </li>
                            <li>
                                <i class="fas fa-clock"></i>
                                <span>Durasi: <?php echo $quiz['durasi']; ?> menit</span>
                            </li>
                            <li>
                                <i class="fas fa-question-circle"></i>
                                <span>Jumlah Soal: <?php echo $total_questions; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <?php if (!isset($show_result)): ?>
                    <form method="POST" id="quizForm">
                        <div class="question-card">
                            <div class="card-body">
                                <div class="question-number">
                                    Pertanyaan <?php echo $q_index + 1; ?> dari <?php echo $total_questions; ?>
                                </div>
                                <p class="question-text"><?php echo $current_question['pertanyaan']; ?></p>

                                <?php if ($current_question['tipe'] === 'pilihan_ganda'): ?>
                                    <div class="answer-options">
                                        <?php foreach ($options_map[$current_question['id']] as $option): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                    name="answer" value="<?php echo $option['pilihan']; ?>" 
                                                    id="answer_<?php echo $option['id']; ?>"
                                                    <?php echo $student_answer === $option['pilihan'] ? 'checked' : ''; ?> required>
                                                <label class="form-check-label" for="answer_<?php echo $option['id']; ?>">
                                                    <?php echo $option['pilihan']; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <textarea class="form-control" name="answer" rows="3" required><?php echo htmlspecialchars($student_answer); ?></textarea>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="nav-buttons">
                            <?php if ($q_index > 0): ?>
                                <button type="submit" name="prev" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Sebelumnya
                                </button>
                            <?php else: ?>
                                <a href="quiz.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali
                                </a>
                            <?php endif; ?>
                            <?php if ($q_index < $total_questions - 1): ?>
                                <button type="submit" name="next" class="btn btn-primary">
                                    Selanjutnya <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            <?php else: ?>
                                <button type="submit" name="submit_quiz" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Simpan Jawaban
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if (isset($show_result) && $show_result): ?>
                    <div class="result-alert">
                        <h4><i class="fas fa-check-circle me-2"></i>Quiz Selesai!</h4>
                        <div class="score"><?php echo number_format($final_score, 1); ?></div>
                        <div class="stats">
                            <div class="stat-item">
                                <i class="fas fa-check me-2"></i>
                                Jawaban Benar: <?php echo $jawaban_benar; ?>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-list me-2"></i>
                                Total Soal: <?php echo $total_soal; ?>
                            </div>
                        </div>
                        <a href="quiz.php" class="btn btn-light mt-3">
                            <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar Quiz
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quiz Timer
        function updateTimer() {
            const endTime = new Date('<?php echo $quiz['waktu_selesai']; ?>').getTime();
            const now = new Date().getTime();
            const distance = endTime - now;

            if (distance < 0) {
                document.getElementById('timer').innerHTML = 'Waktu Habis';
                document.getElementById('quizForm').submit();
                return;
            }

            const hours = Math.floor(distance / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            const timerElement = document.getElementById('timer');
            timerElement.innerHTML = hours.toString().padStart(2, '0') + ':' +
                                   minutes.toString().padStart(2, '0') + ':' +
                                   seconds.toString().padStart(2, '0');

            // Add warning class when less than 5 minutes remaining
            if (minutes < 5) {
                timerElement.parentElement.classList.add('warning');
            }
            // Add danger class when less than 1 minute remaining
            if (minutes < 1) {
                timerElement.parentElement.classList.add('danger');
            }
        }

        setInterval(updateTimer, 1000);
        updateTimer();
    </script>
</body>

</html>
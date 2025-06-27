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
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --success: #10B981;
            --warning: #F59E0B;
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
        .main-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            width: 280px;
            min-height: 100vh;
            transition: all 0.3s ease;
            position: fixed;
            z-index: 1000;
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
            flex-grow: 1;
            padding: 2rem;
            margin-left: 280px; /* Lebar sidebar */
        }
        .quiz-timer {
            position: fixed;
            top: 24px;
            right: 24px;
            background: white;
            padding: 12px 20px;
            border-radius: var(--border-radius-md);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 990;
            border: 2px solid var(--primary);
            font-weight: 600;
            color: var(--primary);
        }
        .question-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-top: 1.5rem;
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
        .form-check {
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-md);
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .form-check:hover {
            border-color: var(--secondary);
            background-color: var(--light);
        }
        .form-check-input:checked + .form-check-label {
            color: var(--primary);
            font-weight: 600;
        }
        .form-check-input:checked + .form-check-label::before {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .result-alert {
             text-align: center;
        }
        
        /* BARU: CSS untuk Hamburger & Overlay */
        .mobile-menu-toggle { display: none; }
        .sidebar-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0; visibility: hidden;
            transition: all 0.3s ease;
        }
        .sidebar-overlay.show { opacity: 1; visibility: visible; }
        
        @media (max-width: 991.98px) {
            .main-content { margin-left: 0; }
            .sidebar { left: -280px; }
            .sidebar.active { left: 0; }
            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 1rem; left: 1rem;
                z-index: 1100;
                background-color: var(--primary);
                color: white;
                border: none;
                border-radius: 50%;
                width: 44px; height: 44px;
                font-size: 1rem;
            }
            .page-title { margin-top: 3.5rem; }
            .quiz-timer {
                position: static;
                width: 100%;
                margin-bottom: 1rem;
                justify-content: center;
                display: flex;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay"></div>
    
    <div class="main-container">
        <div id="sidebar" class="sidebar"> <div class="p-3">
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
                        <a class="nav-link active" href="quiz.php"><i class="fas fa-question-circle me-2"></i>Quiz</a>
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

        <div class="main-content">
            <div class="quiz-timer">
                <i class="fas fa-clock me-2"></i>
                <span id="timer">Memuat...</span>
            </div>
            
            <h1 class="page-title mb-4"><?php echo htmlspecialchars($quiz['judul']); ?></h1>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if (!isset($show_result)): ?>
                <form method="POST" id="quizForm">
                    <div class="question-card card">
                        <div class="card-body">
                            <div class="question-number">
                                Pertanyaan <?php echo $q_index + 1; ?> dari <?php echo $total_questions; ?>
                            </div>
                            <p class="fs-5 mb-4"><?php echo htmlspecialchars($current_question['pertanyaan']); ?></p>

                            <div class="answer-options">
                                <?php foreach ($options_map[$current_question['id']] as $option): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio"
                                            name="answer" value="<?php echo htmlspecialchars($option['pilihan']); ?>" 
                                            id="answer_<?php echo $option['id']; ?>"
                                            <?php echo $student_answer === $option['pilihan'] ? 'checked' : ''; ?> required>
                                        <label class="form-check-label w-100" for="answer_<?php echo $option['id']; ?>">
                                            <?php echo htmlspecialchars($option['pilihan']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <?php if ($q_index > 0): ?>
                            <button type="submit" name="prev" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Sebelumnya</button>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>

                        <?php if ($q_index < $total_questions - 1): ?>
                            <button type="submit" name="next" class="btn btn-primary">Selanjutnya<i class="fas fa-arrow-right ms-2"></i></button>
                        <?php else: ?>
                            <button type="submit" name="submit_quiz" class="btn btn-success"><i class="fas fa-check-circle me-2"></i>Selesaikan Quiz</button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>

            <?php if (isset($show_result) && $show_result): ?>
                <div class="result-alert alert alert-success">
                    <h4 class="alert-heading"><i class="fas fa-trophy me-2"></i>Quiz Selesai!</h4>
                    <p>Skor Akhir Anda:</p>
                    <h1 class="display-1 fw-bold"><?php echo number_format($final_score, 1); ?></h1>
                    <hr>
                    <p class="mb-0">
                        Anda menjawab <b><?php echo $jawaban_benar; ?></b> dari <b><?php echo $total_soal; ?></b> soal dengan benar.
                    </p>
                    <a href="quiz.php" class="btn btn-light mt-4"><i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar Quiz</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Logika untuk Hamburger Menu
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        const menuToggle = document.querySelector('.mobile-menu-toggle');

        if(sidebar && overlay && menuToggle) {
            const toggleSidebar = () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('show');
            };
            menuToggle.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth > 991.98 && sidebar.classList.contains('active')) {
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
    
    <script>
        // Quiz Timer
        function updateTimer() {
            const endTime = new Date('<?php echo $quiz['waktu_selesai']; ?>').getTime();
            const now = new Date().getTime();
            const distance = endTime - now;

            if (distance < 0) {
                document.getElementById('timer').innerHTML = 'Waktu Habis';
                // Otomatis submit form jika waktu habis
                const quizForm = document.getElementById('quizForm');
                if(quizForm) {
                    // tambahkan input hidden untuk menandai bahwa ini adalah submit otomatis
                    let autoSubmitInput = document.createElement('input');
                    autoSubmitInput.type = 'hidden';
                    autoSubmitInput.name = 'submit_quiz';
                    autoSubmitInput.value = '1';
                    quizForm.appendChild(autoSubmitInput);
                    quizForm.submit();
                }
                return;
            }

            const hours = Math.floor(distance / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            const timerElement = document.getElementById('timer');
            timerElement.innerHTML = hours.toString().padStart(2, '0') + ':' +
                                     minutes.toString().padStart(2, '0') + ':' +
                                     seconds.toString().padStart(2, '0');

            const timerContainer = timerElement.closest('.quiz-timer');
            timerContainer.classList.remove('warning', 'danger');
            if (minutes < 1) {
                timerContainer.classList.add('danger');
            } else if (minutes < 5) {
                timerContainer.classList.add('warning');
            }
        }

        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer(); // Panggil sekali saat load
    </script>
</body>
</html>
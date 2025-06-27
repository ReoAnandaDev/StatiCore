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

// Get teacher's classes
$stmt = $conn->prepare("
    SELECT k.* FROM kelas k
    JOIN guru_kelas gk ON k.id = gk.kelas_id
    WHERE gk.guru_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_quiz':
                $judul = filter_input(INPUT_POST, 'judul', FILTER_SANITIZE_STRING);
                $deskripsi = filter_input(INPUT_POST, 'deskripsi', FILTER_SANITIZE_STRING);
                $kelas_id = filter_input(INPUT_POST, 'kelas_id', FILTER_SANITIZE_NUMBER_INT);
                $waktu_mulai = filter_input(INPUT_POST, 'waktu_mulai', FILTER_SANITIZE_STRING);
                $waktu_selesai = filter_input(INPUT_POST, 'waktu_selesai', FILTER_SANITIZE_STRING);
                $durasi = filter_input(INPUT_POST, 'durasi', FILTER_SANITIZE_NUMBER_INT);

                try {
                    $stmt = $conn->prepare("
                        INSERT INTO quiz (judul, deskripsi, kelas_id, guru_id, waktu_mulai, waktu_selesai, durasi)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$judul, $deskripsi, $kelas_id, $_SESSION['user_id'], $waktu_mulai, $waktu_selesai, $durasi]);
                    $quiz_id = $conn->lastInsertId();

                    // Handle quiz questions
                    if (isset($_POST['questions']) && is_array($_POST['questions'])) {
                        foreach ($_POST['questions'] as $index => $question) {
                            $pertanyaan = filter_var($question['text'], FILTER_SANITIZE_STRING);
                            $tipe = filter_var($question['type'], FILTER_SANITIZE_STRING);

                            $stmt = $conn->prepare("
                                INSERT INTO soal_quiz (quiz_id, pertanyaan, tipe)
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$quiz_id, $pertanyaan, $tipe]);
                            $soal_id = $conn->lastInsertId();

                            // Handle multiple choice options
                            if ($tipe === 'pilihan_ganda' && isset($question['options'])) {
                                $correct_index = isset($question['correct']) ? intval($question['correct']) : -1;
                                foreach ($question['options'] as $option_index => $option) {
                                    $pilihan = filter_var($option['text'], FILTER_SANITIZE_STRING);
                                    $is_benar = ($option_index == $correct_index) ? 1 : 0;
                                    $stmt = $conn->prepare("
                                        INSERT INTO pilihan_jawaban (soal_id, pilihan, is_benar)
                                        VALUES (?, ?, ?)
                                    ");
                                    $stmt->execute([$soal_id, $pilihan, $is_benar]);
                                }
                            }
                        }
                    }

                    $message = "Quiz berhasil dibuat";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get teacher's quizzes
$stmt = $conn->prepare("
    SELECT q.*, k.nama_kelas, k.tahun_ajaran,
           (SELECT COUNT(*) FROM soal_quiz WHERE quiz_id = q.id) as total_soal,
           (SELECT COUNT(*) FROM jawaban_siswa js 
            JOIN soal_quiz sq ON js.soal_id = sq.id 
            WHERE sq.quiz_id = q.id) as total_jawaban
    FROM quiz q
    JOIN kelas k ON q.kelas_id = k.id
    WHERE q.guru_id = ?
    ORDER BY q.waktu_mulai DESC
");
$stmt->execute([$_SESSION['user_id']]);
$quizzes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Quiz - StatiCore</title>
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
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--gray-100);
            color: var(--primary);
        }

        .Title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: var(--primary);
            padding: 12px;
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
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
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

        /* Form Controls */
        .form-control,
        .form-select {
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 44px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: var(--gray-600);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--gray-800);
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.75rem;
            min-height: 36px;
        }

        /* Table */
        .table {
            width: 100%;
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            color: var(--primary);
            border-bottom: 2px solid var(--gray-200);
            padding: 16px;
        }

        .table td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-200);
        }

        .table tbody tr:hover {
            background-color: var(--gray-100);
        }

        /* Question Form */
        .question {
            background: var(--gray-100);
            border-radius: var(--border-radius-md);
            padding: 20px;
            margin-bottom: 20px;
        }

        .options {
            margin-top: 16px;
        }

        .option-item {
            background: var(--white);
            border-radius: var(--border-radius-sm);
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid var(--gray-200);
        }

        .input-group-text {
            background: var(--white);
            border: 2px solid var(--gray-300);
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .bg-success {
            background: rgba(25, 135, 84, 0.1) !important;
            color: var(--success) !important;
        }

        .bg-warning {
            background: rgba(255, 193, 7, 0.1) !important;
            color: var(--warning) !important;
        }

        .bg-danger {
            background: rgba(220, 53, 69, 0.1) !important;
            color: var(--danger) !important;
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius-md);
            border: none;
            padding: 16px 20px;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .alert-info {
            background: rgba(13, 202, 240, 0.1);
            color: var(--info);
            border-left: 4px solid var(--info);
        }

        /* Responsive Design */
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="Title">Kelola Quiz</div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Create Quiz Form -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus-circle me-2"></i>Buat Quiz Baru
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="quizForm">
                                    <input type="hidden" name="action" value="create_quiz">
                                    <div class="mb-3">
                                        <label class="form-label">Judul Quiz</label>
                                        <input type="text" class="form-control" name="judul" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Deskripsi</label>
                                        <textarea class="form-control" name="deskripsi" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kelas</label>
                                        <select class="form-select" name="kelas_id" required>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>">
                                                    <?php echo $class['nama_kelas']; ?>
                                                    (<?php echo $class['tahun_ajaran']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Waktu Mulai</label>
                                            <input type="datetime-local" class="form-control" name="waktu_mulai"
                                                required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Waktu Selesai</label>
                                            <input type="datetime-local" class="form-control" name="waktu_selesai"
                                                required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Durasi (menit)</label>
                                        <input type="number" class="form-control" name="durasi" min="1" required>
                                    </div>

                                    <div id="questions">
                                        <h6 class="mb-3">Pertanyaan</h6>
                                        <div class="question mb-3">
                                            <div class="mb-2">
                                                <label class="form-label">Pertanyaan 1</label>
                                                <textarea class="form-control" name="questions[0][text]"
                                                    required></textarea>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">Tipe</label>
                                                <select class="form-select" name="questions[0][type]"
                                                    onchange="toggleOptions(this)">
                                                    <option value="pilihan_ganda">Pilihan Ganda</option>
                                                </select>
                                            </div>
                                            <div class="options">
                                                <div class="mb-2">
                                                    <label class="form-label">Pilihan 1</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control"
                                                            name="questions[0][options][0][text]" required>
                                                        <div class="input-group-text">
                                                            <input type="radio" name="questions[0][correct]" value="0"
                                                                required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Pilihan 2</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control"
                                                            name="questions[0][options][1][text]" required>
                                                        <div class="input-group-text">
                                                            <input type="radio" name="questions[0][correct]" value="1">
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-secondary"
                                                    onclick="addOption(this)">
                                                    <i class="fas fa-plus me-2"></i>Tambah Pilihan
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-secondary mb-3" onclick="addQuestion()">
                                        <i class="fas fa-plus me-2"></i>Tambah Pertanyaan
                                    </button>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Buat Quiz
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Quiz List -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>Daftar Quiz
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Judul</th>
                                                <th>Kelas</th>
                                                <th>Waktu</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($quizzes as $quiz): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-clipboard-list me-2"
                                                                style="color: var(--secondary);"></i>
                                                            <?php echo htmlspecialchars($quiz['judul']); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($quiz['nama_kelas']); ?></td>
                                                    <td>
                                                        <?php
                                                        echo date('d/m/Y H:i', strtotime($quiz['waktu_mulai'])) . ' - ' .
                                                            date('d/m/Y H:i', strtotime($quiz['waktu_selesai']));
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $now = new DateTime();
                                                        $start = new DateTime($quiz['waktu_mulai']);
                                                        $end = new DateTime($quiz['waktu_selesai']);

                                                        if ($now < $start) {
                                                            echo '<span class="badge bg-warning">Belum Mulai</span>';
                                                        } elseif ($now > $end) {
                                                            echo '<span class="badge bg-danger">Selesai</span>';
                                                        } else {
                                                            echo '<span class="badge bg-success">Sedang Berlangsung</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-2">
                                                            <a href="detail_quiz.php?id=<?php echo $quiz['id']; ?>"
                                                                class="btn btn-sm btn-info" title="Detail">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>"
                                                                class="btn btn-sm btn-warning" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button class="btn btn-sm btn-danger"
                                                                onclick="deleteQuiz(<?php echo $quiz['id']; ?>)"
                                                                title="Hapus">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let questionCount = 1;

        function addQuestion() {
            const questionsDiv = document.getElementById('questions');
            const questionDiv = document.createElement('div');
            questionDiv.className = 'question mb-3';
            questionDiv.innerHTML = `
                <div class="mb-2">
                    <label class="form-label">Pertanyaan ${questionCount + 1}</label>
                    <textarea class="form-control" name="questions[${questionCount}][text]" required></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label">Tipe</label>
                    <select class="form-select" name="questions[${questionCount}][type]" onchange="toggleOptions(this)">
                        <option value="pilihan_ganda">Pilihan Ganda</option>
                    </select>
                </div>
                <div class="options">
                    <div class="mb-2">
                        <label class="form-label">Pilihan 1</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="questions[${questionCount}][options][0][text]" required>
                            <div class="input-group-text">
                                <input type="radio" name="questions[${questionCount}][correct]" value="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Pilihan 2</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="questions[${questionCount}][options][1][text]" required>
                            <div class="input-group-text">
                                <input type="radio" name="questions[${questionCount}][correct]" value="1">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addOption(this)">
                        <i class="fas fa-plus me-2"></i>Tambah Pilihan
                    </button>
                </div>
            `;
            questionsDiv.appendChild(questionDiv);
            questionCount++;
        }

        function toggleOptions(select) {
            const optionsDiv = select.parentElement.nextElementSibling;
            if (select.value === 'esai') {
                optionsDiv.style.display = 'none';
                optionsDiv.querySelectorAll('input, textarea').forEach(input => input.required = false);
            } else {
                optionsDiv.style.display = 'block';
                optionsDiv.querySelectorAll('input[type="text"]').forEach(input => input.required = true);
            }
        }

        function addOption(button) {
            const optionsDiv = button.closest('.options');
            const questionIndex = optionsDiv.closest('.question').querySelector('textarea').name.match(/questions\[(\d+)\]/)[1];
            const optionCount = optionsDiv.querySelectorAll('.input-group').length;

            const optionDiv = document.createElement('div');
            optionDiv.className = 'mb-2 option-item';
            optionDiv.innerHTML = `
                <label class="form-label">Pilihan ${optionCount + 1}</label>
                <div class="input-group">
                    <input type="text" class="form-control" name="questions[${questionIndex}][options][${optionCount}][text]" required>
                    <div class="input-group-text">
                        <input type="radio" name="questions[${questionIndex}][correct]" value="${optionCount}">
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeOption(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            optionsDiv.insertBefore(optionDiv, button);
        }

        function removeOption(button) {
            button.closest('.option-item').remove();
            updateOptionNumbers(button.closest('.options'));
        }

        function updateOptionNumbers(optionsDiv) {
            const optionItems = optionsDiv.querySelectorAll('.option-item');
            optionItems.forEach((item, index) => {
                item.querySelector('label').textContent = `Pilihan ${index + 1}`;
                item.querySelectorAll('input').forEach(input => {
                    if (input.type === 'text') {
                        input.name = input.name.replace(/options\[\d+\]/, `options[${index}]`);
                    } else if (input.type === 'radio') {
                        input.value = index;
                    }
                });
            });
        }

        function deleteQuiz(quizId) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Quiz yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2c5282',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete_quiz.php?id=${quizId}`;
                }
            });
        }

        // Logout confirmation
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
                        cancelButtonColor: '#6c757d',
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
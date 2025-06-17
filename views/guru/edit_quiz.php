<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is teacher
$auth->checkSession();
$auth->requireRole('guru');

$conn = $db->getConnection();

// Get quiz ID from URL
$quiz_id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$quiz_id) {
    header('Location: kelola_quiz.php');
    exit;
}

// Get quiz details
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

// Get questions
$stmt = $conn->prepare("
    SELECT * FROM soal_quiz 
    WHERE quiz_id = ?
    ORDER BY id
");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

// Get options for multiple choice questions
foreach ($questions as &$question) {
    if ($question['tipe'] === 'pilihan_ganda') {
        $stmt = $conn->prepare("
            SELECT * FROM pilihan_jawaban 
            WHERE soal_id = ?
            ORDER BY id
        ");
        $stmt->execute([$question['id']]);
        $question['options'] = $stmt->fetchAll();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Update quiz details
        $stmt = $conn->prepare("
            UPDATE quiz 
            SET judul = ?, deskripsi = ?, durasi = ?, 
                waktu_mulai = ?, waktu_selesai = ?
            WHERE id = ? AND guru_id = ?
        ");
        $stmt->execute([
            $_POST['judul'],
            $_POST['deskripsi'],
            $_POST['durasi'],
            $_POST['waktu_mulai'],
            $_POST['waktu_selesai'],
            $quiz_id,
            $_SESSION['user_id']
        ]);

        // Update or insert questions
        foreach ($_POST['questions'] as $index => $question_data) {
            if (isset($question_data['id'])) {
                // Update existing question
                $stmt = $conn->prepare("
                    UPDATE soal_quiz 
                    SET pertanyaan = ?, tipe = ?
                    WHERE id = ? AND quiz_id = ?
                ");
                $stmt->execute([
                    $question_data['pertanyaan'],
                    $question_data['tipe'],
                    $question_data['id'],
                    $quiz_id
                ]);
                $soal_id = $question_data['id'];
            } else {
                // Insert new question
                $stmt = $conn->prepare("
                    INSERT INTO soal_quiz (quiz_id, pertanyaan, tipe)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $quiz_id,
                    $question_data['pertanyaan'],
                    $question_data['tipe']
                ]);
                $soal_id = $conn->lastInsertId();
            }

            // Handle options for multiple choice questions
            if ($question_data['tipe'] === 'pilihan_ganda' && isset($question_data['options'])) {
                // Delete existing options
                $stmt = $conn->prepare("DELETE FROM pilihan_jawaban WHERE soal_id = ?");
                $stmt->execute([$soal_id]);

                // Insert new options
                foreach ($question_data['options'] as $option_data) {
                    $stmt = $conn->prepare("
                        INSERT INTO pilihan_jawaban (soal_id, pilihan, is_benar)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $soal_id,
                        $option_data['text'],
                        $option_data['is_benar'] ?? 0
                    ]);
                }
            }
        }

        // Delete questions that were removed
        if (isset($_POST['deleted_questions'])) {
            $stmt = $conn->prepare("
                DELETE FROM soal_quiz 
                WHERE id IN (" . implode(',', array_fill(0, count($_POST['deleted_questions']), '?')) . ")
                AND quiz_id = ?
            ");
            $params = $_POST['deleted_questions'];
            $params[] = $quiz_id;
            $stmt->execute($params);
        }

        $conn->commit();
        $success_message = "Quiz berhasil diperbarui!";

        // Refresh quiz data
        $stmt = $conn->prepare("
            SELECT q.*, k.nama_kelas, k.tahun_ajaran
            FROM quiz q
            JOIN kelas k ON q.kelas_id = k.id
            WHERE q.id = ? AND q.guru_id = ?
        ");
        $stmt->execute([$quiz_id, $_SESSION['user_id']]);
        $quiz = $stmt->fetch();

        $stmt = $conn->prepare("
            SELECT * FROM soal_quiz 
            WHERE quiz_id = ?
            ORDER BY id
        ");
        $stmt->execute([$quiz_id]);
        $questions = $stmt->fetchAll();

        foreach ($questions as &$question) {
            if ($question['tipe'] === 'pilihan_ganda') {
                $stmt = $conn->prepare("
                    SELECT * FROM pilihan_jawaban 
                    WHERE soal_id = ?
                    ORDER BY id
                ");
                $stmt->execute([$question['id']]);
                $question['options'] = $stmt->fetchAll();
            }
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Terjadi kesalahan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz - StatiCore</title>
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
            background-color: #f8f9fa;
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

        .main-content {
            margin-left: 280px;
            padding: 32px;
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

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 48px;
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

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--secondary);
            color: var(--secondary);
        }

        .btn-outline:hover {
            background: var(--secondary);
            color: white;
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.75rem;
            min-height: 36px;
        }

        .btn i {
            margin-right: 8px;
        }

        /* Question Cards */
        .question-card {
            border-left: 4px solid var(--secondary);
            margin-bottom: 24px;
            position: relative;
        }

        .question-card .card-body {
            padding: 24px;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-200);
        }

        .question-number {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* Options */
        .options-container {
            margin-top: 20px;
        }

        .option-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            padding: 12px;
            background: var(--gray-100);
            border-radius: var(--border-radius-sm);
            transition: all 0.3s ease;
        }

        .option-item:hover {
            background: var(--gray-200);
            transform: translateX(4px);
        }

        .option-radio {
            margin-right: 12px;
            transform: scale(1.2);
        }

        .option-input {
            flex: 1;
            margin-right: 12px;
            border: 1px solid var(--gray-300);
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--border-radius-md);
            margin-bottom: 24px;
            border: none;
            font-weight: 500;
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

        /* Grid System */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -12px;
        }

        .col, .col-md-4, .col-md-6, .col-12 {
            padding: 12px;
        }

        .col-12 { flex: 0 0 100%; }
        .col-md-6 { flex: 0 0 50%; }
        .col-md-4 { flex: 0 0 33.333333%; }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--gray-200);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            /* Sidebar */
            .sidebar {
                position: fixed;
                top: 0;
                left: -280px; /* Sembunyikan di luar layar */
                width: 280px;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
            }

            .sidebar.active {
                left: 0;
            }

            /* Overlay untuk tutup sidebar */
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

            /* Main content untuk mobile */
            .content-wrapper {
                margin-left: 0 !important;
                padding: 16px;
            }

            /* Tombol menu mobile */
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

            /* Grid statistik */
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

            /* Kartu informasi quiz */
            .quiz-info-grid {
                grid-template-columns: 1fr;
            }

            /* Form soal */
            .question-card .card-body {
                padding: 16px;
            }

            .option-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .option-input-group {
                width: 100%;
            }

            .save-btn {
                width: 100%;
            }

            /* Tombol back */
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
                <div class="mb-4 Title">Edit Quiz</div>

                <?php if (isset($success_message)): ?>
                        <div class="alert alert-success"><?= $success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= $error_message; ?></div>
                <?php endif; ?>
                <form method="POST" id="quizForm">

            <!-- Quiz Information -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i>Informasi Quiz
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Judul Quiz</label>
                                <input type="text" class="form-control" name="judul" 
                                       value="<?php echo htmlspecialchars($quiz['judul']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Kelas</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($quiz['nama_kelas'] . ' (' . $quiz['tahun_ajaran'] . ')'); ?>" 
                                       readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Deskripsi</label>
                                <textarea class="form-control" name="deskripsi" rows="3" required><?php echo htmlspecialchars($quiz['deskripsi']); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Durasi (menit)</label>
                                <input type="number" class="form-control" name="durasi" 
                                       value="<?php echo htmlspecialchars($quiz['durasi']); ?>" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Waktu Mulai</label>
                                <input type="datetime-local" class="form-control" name="waktu_mulai" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($quiz['waktu_mulai'])); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Waktu Selesai</label>
                                <input type="datetime-local" class="form-control" name="waktu_selesai" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($quiz['waktu_selesai'])); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions Section -->
            <div class="card">
                <div class="card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fas fa-question-circle me-2"></i>Daftar Pertanyaan</span>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addQuestion()">
                            <i class="fas fa-plus"></i>Tambah Pertanyaan
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="questions">
                        <?php foreach ($questions as $index => $question): ?>
                                <div class="question-card card fade-in">
                                    <div class="card-body">
                                        <div class="question-header">
                                            <span class="question-number">Pertanyaan <?php echo $index + 1; ?></span>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteQuestion(this)">
                                                <i class="fas fa-trash"></i>Hapus
                                            </button>
                                        </div>

                                        <input type="hidden" name="questions[<?php echo $index; ?>][id]" 
                                               value="<?php echo htmlspecialchars($question['id']); ?>">

                                        <div class="form-group">
                                            <label class="form-label">Pertanyaan</label>
                                            <textarea class="form-control" name="questions[<?php echo $index; ?>][pertanyaan]" 
                                                      rows="3" required><?php echo htmlspecialchars($question['pertanyaan']); ?></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Tipe Soal</label>
                                            <select class="form-control form-select" name="questions[<?php echo $index; ?>][tipe]" 
                                                    onchange="toggleOptions(this)">
                                                <option value="pilihan_ganda" <?php echo $question['tipe'] === 'pilihan_ganda' ? 'selected' : ''; ?>>
                                                    Pilihan Ganda
                                                </option>
                                                <option value="esai" <?php echo $question['tipe'] === 'esai' ? 'selected' : ''; ?>>
                                                    Esai
                                                </option>
                                            </select>
                                        </div>

                                        <?php if ($question['tipe'] === 'pilihan_ganda'): ?>
                                                <div class="options-container">
                                                    <label class="form-label">Pilihan Jawaban</label>
                                                    <div class="options">
                                                        <?php foreach ($question['options'] as $option_index => $option): ?>
                                                                <div class="option-item">
                                                                    <input type="radio" class="option-radio" 
                                                                           name="questions[<?php echo $index; ?>][correct_option]" 
                                                                           value="<?php echo $option_index; ?>" 
                                                                           <?php echo $option['is_benar'] ? 'checked' : ''; ?>>
                                                                    <input type="text" class="form-control option-input" 
                                                                           name="questions[<?php echo $index; ?>][options][<?php echo $option_index; ?>][text]" 
                                                                           value="<?php echo htmlspecialchars($option['pilihan']); ?>" 
                                                                           placeholder="Masukkan pilihan jawaban..." required>
                                                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteOption(this)">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </div>
                                                        <?php endforeach; ?>
                                                        <button type="button" class="btn btn-outline btn-sm" onclick="addOption(this)">
                                                            <i class="fas fa-plus"></i>Tambah Pilihan
                                                        </button>
                                                    </div>
                                                </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="kelola_quiz.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>Kembali
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    <script>
        let questionCount = <?php echo count($questions); ?>;
        let deletedQuestions = [];

        // Toggle sidebar for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });

        // Add new question
        function addQuestion() {
            const questionsDiv = document.getElementById('questions');
            const questionCard = document.createElement('div');
            questionCard.className = 'question-card card fade-in';
            
            questionCard.innerHTML = `
                <div class="card-body">
                    <div class="question-header">
                        <span class="question-number">Pertanyaan ${questionCount + 1}</span>
                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteQuestion(this)">
                            <i class="fas fa-trash"></i>Hapus
                        </button>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pertanyaan</label>
                        <textarea class="form-control" name="questions[${questionCount}][pertanyaan]" 
                                  rows="3" placeholder="Masukkan pertanyaan..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tipe Soal</label>
                        <select class="form-control form-select" name="questions[${questionCount}][tipe]" 
                                onchange="toggleOptions(this)">
                            <option value="pilihan_ganda">Pilihan Ganda</option>
                            <option value="esai">Esai</option>
                        </select>
                    </div>

                    <div class="options-container">
                        <label class="form-label">Pilihan Jawaban</label>
                        <div class="options">
                            <div class="option-item">
                                <input type="radio" class="option-radio" 
                                       name="questions[${questionCount}][correct_option]" 
                                       value="0" checked>
                                <input type="text" class="form-control option-input" 
                                       name="questions[${questionCount}][options][0][text]" 
                                       placeholder="Masukkan pilihan jawaban..." required>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteOption(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="option-item">
                                <input type="radio" class="option-radio" 
                                       name="questions[${questionCount}][correct_option]" 
                                       value="1">
                                <input type="text" class="form-control option-input" 
                                       name="questions[${questionCount}][options][1][text]" 
                                       placeholder="Masukkan pilihan jawaban..." required>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteOption(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" onclick="addOption(this)">
                                <i class="fas fa-plus"></i>Tambah Pilihan
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            questionsDiv.appendChild(questionCard);
            questionCount++;
            
            // Scroll to new question
            questionCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Delete question
        function deleteQuestion(button) {
            if (!confirm('Apakah Anda yakin ingin menghapus pertanyaan ini?')) {
                return;
            }

            const questionCard = button.closest('.question-card');
            const questionId = questionCard.querySelector('input[name$="[id]"]');
            
            if (questionId && questionId.value) {
                deletedQuestions.push(questionId.value);
                
                // Add hidden input for deleted questions
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'deleted_questions[]';
                input.value = questionId.value;
                document.getElementById('quizForm').appendChild(input);
            }
            
            // Animate removal
            questionCard.style.transform = 'translateX(-100%)';
            questionCard.style.opacity = '0';
            
            setTimeout(() => {
                questionCard.remove();
                updateQuestionNumbers();
            }, 300);
        }

        // Update question numbers after deletion
        function updateQuestionNumbers() {
            const questions = document.querySelectorAll('.question-card');
            questions.forEach((question, index) => {
                const numberSpan = question.querySelector('.question-number');
                numberSpan.textContent = `Pertanyaan ${index + 1}`;
                
                // Update form field names
                const inputs = question.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace(/questions\[\d+\]/, `questions[${index}]`);
                    }
                });
            });
        }

        // Toggle options visibility based on question type
        function toggleOptions(select) {
            const optionsContainer = select.closest('.card-body').querySelector('.options-container');
            if (select.value === 'pilihan_ganda') {
                optionsContainer.style.display = 'block';
                // Make options required
                const optionInputs = optionsContainer.querySelectorAll('.option-input');
                optionInputs.forEach(input => input.required = true);
            } else {
                optionsContainer.style.display = 'none';
                // Remove required from options
                const optionInputs = optionsContainer.querySelectorAll('.option-input');
                optionInputs.forEach(input => input.required = false);
            }
        }

        // Add new option to multiple choice question
        function addOption(button) {
            const optionsDiv = button.closest('.options');
            const questionCard = button.closest('.question-card');
            const questionIndex = questionCard.querySelector('textarea[name*="pertanyaan"]').name.match(/questions\[(\d+)\]/)[1];
            const optionCount = optionsDiv.querySelectorAll('.option-item').length;
            
            const optionItem = document.createElement('div');
            optionItem.className = 'option-item';
            optionItem.style.opacity = '0';
            optionItem.style.transform = 'translateY(-10px)';
            
            optionItem.innerHTML = `
                <input type="radio" class="option-radio" 
                       name="questions[${questionIndex}][correct_option]" 
                       value="${optionCount}">
                <input type="text" class="form-control option-input" 
                       name="questions[${questionIndex}][options][${optionCount}][text]" 
                       placeholder="Masukkan pilihan jawaban..." required>
                <button type="button" class="btn btn-danger btn-sm" onclick="deleteOption(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            button.parentNode.insertBefore(optionItem, button);
            
            // Animate in
            setTimeout(() => {
                optionItem.style.opacity = '1';
                optionItem.style.transform = 'translateY(0)';
            }, 10);
        }

        // Delete option from multiple choice question
        function deleteOption(button) {
            const optionItem = button.closest('.option-item');
            const optionsDiv = optionItem.closest('.options');
            const optionCount = optionsDiv.querySelectorAll('.option-item').length;
            
            if (optionCount <= 2) {
                alert('Minimal harus ada 2 pilihan jawaban!');
                return;
            }
            
            // Animate removal
            optionItem.style.transform = 'translateX(-100%)';
            optionItem.style.opacity = '0';
            
            setTimeout(() => {
                optionItem.remove();
                updateOptionNumbers(optionsDiv);
            }, 300);
        }

        // Update option numbers and names after deletion
        function updateOptionNumbers(optionsDiv) {
            const options = optionsDiv.querySelectorAll('.option-item');
            const questionIndex = optionsDiv.closest('.question-card').querySelector('textarea[name*="pertanyaan"]').name.match(/questions\[(\d+)\]/)[1];
            
            options.forEach((option, index) => {
                const radio = option.querySelector('input[type="radio"]');
                const textInput = option.querySelector('input[type="text"]');
                
                radio.value = index;
                radio.name = `questions[${questionIndex}][correct_option]`;
                textInput.name = `questions[${questionIndex}][options][${index}][text]`;
            });
        }

        // Form validation
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            const questions = document.querySelectorAll('.question-card');
            let valid = true;
            let errors = [];

            // Check if there are questions
            if (questions.length === 0) {
                errors.push('Minimal harus ada 1 pertanyaan!');
                valid = false;
            }

            // Validate each question
            questions.forEach((question, index) => {
                const questionText = question.querySelector('textarea[name*="pertanyaan"]');
                const questionType = question.querySelector('select[name*="tipe"]');
                
                if (!questionText.value.trim()) {
                    errors.push(`Pertanyaan ${index + 1} tidak boleh kosong!`);
                    valid = false;
                }

                if (questionType.value === 'pilihan_ganda') {
                    const options = question.querySelectorAll('.option-input');
                    const correctOption = question.querySelector('input[name*="correct_option"]:checked');
                    
                    let filledOptions = 0;
                    options.forEach(option => {
                        if (option.value.trim()) filledOptions++;
                    });
                    
                    if (filledOptions < 2) {
                        errors.push(`Pertanyaan ${index + 1} harus memiliki minimal 2 pilihan jawaban!`);
                        valid = false;
                    }
                    
                    if (!correctOption) {
                        errors.push(`Pertanyaan ${index + 1} harus memiliki jawaban yang benar!`);
                        valid = false;
                    }
                }
            });

            // Validate quiz timing
            const startTime = new Date(document.querySelector('input[name="waktu_mulai"]').value);
            const endTime = new Date(document.querySelector('input[name="waktu_selesai"]').value);
            
            if (startTime >= endTime) {
                errors.push('Waktu selesai harus lebih besar dari waktu mulai!');
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
                alert('Terdapat kesalahan:\n\n' + errors.join('\n'));
                return false;
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Menyimpan...';
            submitBtn.disabled = true;
            
            // Re-enable after delay (in case of errors)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });

        // Initialize tooltips and other interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-resize textareas
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });

            // Initialize existing question options display
            const typeSelects = document.querySelectorAll('select[name*="tipe"]');
            typeSelects.forEach(select => {
                toggleOptions(select);
            });

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl+S to save
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    document.getElementById('quizForm').requestSubmit();
                }
                
                // Ctrl+Q to add question
                if (e.ctrlKey && e.key === 'q') {
                    e.preventDefault();
                    addQuestion();
                }
            });
        });

        // Auto-save draft functionality (optional)
        let autoSaveTimeout;
        const formElements = document.querySelectorAll('input, textarea, select');
        
        formElements.forEach(element => {
            element.addEventListener('input', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    // Here you could implement auto-save to localStorage or send to server
                    console.log('Auto-saving draft...');
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
        });
    </script>

</body>
</html>
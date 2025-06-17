<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Inisialisasi session dan koneksi
$db = new Database();
$auth = new Auth($db->getConnection());

// Cek login dan role siswa
$auth->checkSession();
$auth->requireRole('siswa');

$conn = $db->getConnection();

// Ambil kelas siswa
$stmt = $conn->prepare("
    SELECT k.* FROM kelas k
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE sk.siswa_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

// Kelas yang dipilih
$selected_class = isset($_GET['class_id']) ? $_GET['class_id'] : null;

// Inisialisasi variabel statistik
$total_quizzes = 0;
$completed_quizzes = 0;
$total_score = 0;
$average_score = 0;
$results = [];

if ($selected_class) {
    // AUTO-GRADE: Update nilai otomatis untuk soal yang belum dinilai
    $stmtCheck = $conn->prepare("
        SELECT js.id AS jawaban_id, js.soal_id, js.jawaban 
        FROM jawaban_siswa js
        JOIN soal_quiz sq ON js.soal_id = sq.id
        JOIN quiz q ON sq.quiz_id = q.id
        WHERE js.siswa_id = ? AND js.nilai IS NULL AND q.kelas_id = ?
    ");
    $stmtCheck->execute([$_SESSION['user_id'], $selected_class]);

    if ($stmtCheck->rowCount() > 0) {
        try {
            $conn->beginTransaction();
            while ($jawaban = $stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                $soal_id = $jawaban['soal_id'];
                $jawaban_siswa = strtoupper(trim($jawaban['jawaban'])); // Normalisasi input

                // Ambil jawaban benar
                $stmtSoal = $conn->prepare("SELECT pilihan FROM pilihan_jawaban WHERE soal_id = ? AND is_benar = 1 LIMIT 1");
                $stmtSoal->execute([$soal_id]);
                $soal = $stmtSoal->fetch(PDO::FETCH_ASSOC);

                $nilai = 0;
                if ($soal && !empty($jawaban_siswa)) {
                    $benar = array_map('trim', explode(',', strtoupper($soal['pilihan'])));
                    $jawab = array_map('trim', explode(',', $jawaban_siswa));
                    sort($benar); sort($jawab);
                    if ($benar === $jawab) {
                        $nilai = 1;
                    }
                }

                // Update nilai di jawaban_siswa
                $stmtUpdate = $conn->prepare("UPDATE jawaban_siswa SET nilai = ?, waktu_selesai = NOW() WHERE id = ?");
                $stmtUpdate->execute([$nilai, $jawaban['jawaban_id']]);
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Auto-grade failed: " . $e->getMessage());
        }
    }

    // Ambil hasil quiz setelah update nilai
    $stmt = $conn->prepare("
        SELECT 
            q.id AS quiz_id,
            q.judul,
            q.deskripsi,
            q.waktu_mulai,
            q.waktu_selesai,
            u.nama_lengkap AS guru_nama,
            COUNT(sq.id) AS total_soal,
            SUM(js.nilai) AS total_nilai,
            MAX(js.waktu_selesai) AS waktu_submit
        FROM quiz q
        JOIN users u ON q.guru_id = u.id
        JOIN soal_quiz sq ON q.id = sq.quiz_id
        LEFT JOIN jawaban_siswa js ON sq.id = js.soal_id AND js.siswa_id = ?
        WHERE q.kelas_id = ?
        GROUP BY q.id
        ORDER BY q.waktu_mulai DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $selected_class]);
    $results = $stmt->fetchAll();

    // Hitung statistik
    $total_quizzes = count($results);
    foreach ($results as $result) {
        if ($result['total_soal'] > 0 && $result['total_nilai'] !== null) {
            $completed_quizzes++;
            $total_score += $result['total_nilai'];
        }
    }

    $average_score = $completed_quizzes > 0 ? $total_score / $completed_quizzes : 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nilai - StatiCore</title>
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

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            padding: 24px;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            color: var(--gray-700);
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            padding: 24px;
            border-radius: var(--border-radius-lg);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            opacity: 0.1;
            border-radius: 50%;
            transform: translate(20px, -20px);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            text-align: center;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            text-align: center;
            color: var(--gray-600);
        }

        .stat-icon {
            position: absolute;
            top: 24px;
            right: 24px;
            font-size: 24px;
            color: var(--primary);
            opacity: 0.7;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-sm);
            background: var(--white);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        /* Quiz Cards */
        .quiz-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }

        .quiz-card {
            position: relative;
            transition: all 0.3s ease;
        }

        .quiz-card:hover {
            transform: translateY(-8px);
        }

        .quiz-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .quiz-title {
            font-size: 20px;
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
        }

        .quiz-description {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }

        .quiz-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .quiz-meta-item {
            display: flex;
            align-items: center;
            color: var(--gray-600);
            font-size: 14px;
        }

        .quiz-meta-item i {
            margin-right: 8px;
            color: var(--primary);
        }

        /* Score Badge */
        .score-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
        }

        .score-excellent {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .score-good {
            background: linear-gradient(135deg, var(--info), #1D4ED8);
            color: white;
        }

        .score-fair {
            background: linear-gradient(135deg, var(--warning), #D97706);
            color: white;
        }

        .score-poor {
            background: linear-gradient(135deg, var(--danger), #DC2626);
            color: white;
        }

        .score-pending {
            background: linear-gradient(135deg, var(--gray-500), var(--gray-600));
            color: white;
        }

        /* Status Indicators */
        .status-completed {
            color: var(--success);
        }

        .status-pending {
            color: var(--warning);
        }

        .status-not-attempted {
            color: var(--gray-500);
        }

        /* Date Display */
        .quiz-date {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--gray-200);
            font-size: 13px;
            color: var(--gray-500);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .empty-state-icon {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 16px;
        }

        .empty-state-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
        }

        .empty-state-text {
            color: var(--gray-500);
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #859F3D;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* RESPONSIVE STYLES */
        @media (max-width: 992px) {
            .question-card {
                margin-bottom: 1.5rem;
            }

            .quiz-meta-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .quiz-meta-item i {
                margin-right: 4px;
                margin-bottom: 4px;
            }

            .score-badge {
                font-size: 13px;
                padding: 6px 12px;
            }

            .btn {
                width: 100%;
                margin-top: 0.5rem;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }

        @media (max-width: 768px) {
            .Title {
                font-size: 1.25rem;
            }

            .question-text {
                font-size: 1rem;
            }

            .answer-text {
                font-size: 0.95rem;
            }

            .card-body {
                padding: 1rem;
            }

            .quiz-meta-item {
                font-size: 0.85rem;
            }

            .alert {
                font-size: 0.85rem;
                padding: 0.75rem 1rem;
            }
        }

        @media (max-width: 576px) {
            .question-card {
                padding: 1rem;
            }

            .question-text {
                font-size: 0.95rem;
            }

            .form-control,
            .form-select {
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
            }

            .btn-primary {
                font-size: 0.85rem;
                padding: 0.6rem 1rem;
            }

            .empty-state i {
                font-size: 36px;
            }

            .empty-state-title {
                font-size: 16px;
            }

            .empty-state-text {
                font-size: 13px;
            }

            .score-badge {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?></div>
    <?php elseif (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>
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
                        <a class="nav-link active" href="nilai.php">
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
        <div class="col-md-9 col-lg-10 p-4 content-wrapper">
            <div class="mb-4 Title">Nilai Quiz</div>

            <!-- Class Selection -->
            <div class="card" style="margin-bottom: 32px;">
                <div class="card-body">
                    <form method="GET" id="classForm">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-graduation-cap" style="margin-right: 8px; color: #859F3D;"></i>
                                Pilih Kelas
                            </label>
                            <select class="form-select" name="class_id" onchange="document.getElementById('classForm').submit()">
                                <option value="">--Pilih Kelas--</option>
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
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_quizzes; ?></div>
                    <div class="stat-label">Total Quiz</div>
                    <i class="fas fa-tasks stat-icon"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $completed_quizzes; ?></div>
                    <div class="stat-label">Quiz Selesai</div>
                    <i class="fas fa-check-circle stat-icon"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($average_score, 1); ?></div>
                    <div class="stat-label">Rata-rata Nilai</div>
                    <i class="fas fa-star stat-icon"></i>
                </div>
            </div>

            <?php if (!empty($results)): ?>
                <!-- Quiz Results -->
                <div class="quiz-grid">
                    <?php foreach ($results as $result): ?>
                        <div class="card quiz-card">
                            <div class="quiz-header">
                                <h3 class="quiz-title"><?php echo htmlspecialchars($result['judul']); ?></h3>
                                <p class="quiz-description"><?php echo htmlspecialchars($result['deskripsi']); ?></p>
                            </div>
                            <div class="card-body">
                                <div class="quiz-meta">
                                    <div class="quiz-meta-item">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($result['guru_nama']); ?>
                                    </div>
                                    <div class="quiz-meta-item">
                                        <i class="fas fa-question-circle"></i>
                                        <?php echo $result['total_soal']; ?> Soal
                                    </div>
                                </div>

                                <!-- Score Display -->
                                <?php 
                                // Hitung persentase jawaban
                                $jumlah_jawaban = $result['total_soal'] > 0 ? round(($result['total_nilai'] / $result['total_soal']) * 100, 1) : 0;
                                $sudah_dijawab = $result['total_soal'] > 0 && $result['total_nilai'] !== null && $result['total_nilai'] > 0;
                                $semua_dijawab = $result['total_soal'] > 0 && $result['total_nilai'] === $result['total_soal'];
                                $selesai = $result['total_soal'] > 0 && $result['total_nilai'] === $result['total_soal'];
                                ?>

                                <?php 
                                    $score = $jumlah_jawaban;
                                    $scoreClass = 'score-pending';
                                    if ($score >= 85) $scoreClass = 'score-excellent';
                                    elseif ($score >= 70) $scoreClass = 'score-good';
                                    elseif ($score >= 60) $scoreClass = 'score-fair';
                                    else $scoreClass = 'score-poor';
                                ?>

                                <?php if ($sudah_dijawab): ?>
                                    <div style="margin-bottom: 16px;">
                                        <span class="score-badge <?= $scoreClass ?>">
                                            <i class="fas fa-star" style="margin-right: 4px;"></i>
                                            Nilai: <?= $result['total_nilai']?>
                                        </span>
                                    </div>

                                    <div class="quiz-meta-item">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Jawaban Benar: <?= $result['total_nilai'] ?> / <?= $result['total_soal'] ?>
                                    </div>

                                    <div class="quiz-meta-item">
                                        <i class="fas fa-clock me-2"></i>
                                        Selesai pada <?= date('d/m/Y H:i', strtotime($result['waktu_submit'])); ?>
                                    </div>

                                <?php else: ?>
                                    <div style="margin-bottom: 16px;">
                                        <span class="score-badge score-pending">
                                            <i class="fas fa-exclamation-circle me-2" style="margin-right: 4px;"></i>
                                            Belum Dikerjakan
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="quiz-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('d F Y, H:i', strtotime($result['waktu_mulai'])); ?> - 
                                    <?php echo date('d F Y, H:i', strtotime($result['waktu_selesai'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list empty-state-icon"></i>
                    <h3 class="empty-state-title">Belum Ada Quiz</h3>
                    <p class="empty-state-text">Belum ada quiz yang tersedia untuk kelas ini.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap empty-state-icon"></i>
                <h3 class="empty-state-title">Pilih Kelas</h3>
                <p class="empty-state-text">Silakan pilih kelas untuk melihat nilai quiz Anda.</p>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        });

        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });

        // Add smooth loading animation when form is submitted
        document.getElementById('classForm').addEventListener('submit', function() {
            const select = this.querySelector('select');
            if (select.value) {
                select.style.opacity = '0.7';
                select.insertAdjacentHTML('afterend', '<div class="loading" style="margin-left: 8px;"></div>');
            }
        });

        // Add hover effects to cards
        document.querySelectorAll('.quiz-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Auto-refresh every 30 seconds if a class is selected
        <?php if ($selected_class): ?>
        setInterval(function() {
            // Only refresh if user hasn't interacted recently
            if (document.visibilityState === 'visible') {
                const lastInteraction = localStorage.getItem('lastInteraction') || 0;
                const now = Date.now();
                if (now - lastInteraction > 30000) { // 30 seconds
                    location.reload();
                }
            }
        }, 30000);

        // Track user interactions
        document.addEventListener('click', function() {
            localStorage.setItem('lastInteraction', Date.now());
        });
        <?php endif; ?>
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

            // Confirm Delete Function
            window.confirmDelete = function(materialId) {
                Swal.fire({
                    title: 'Yakin hapus materi ini?',
                    text: "Data akan dihapus permanen!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#333446',
                    cancelButtonColor: '#7F8CAA',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `delete_materi.php?id=${materialId}`;
                    }
                });
            };
        });
    </script>
</body>
</html>
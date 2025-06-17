<?php
// Set zona waktu
date_default_timezone_set('Asia/Jakarta');

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

// Get selected class
$selected_class = isset($_GET['class_id']) ? $_GET['class_id'] : null;

// Get quizzes for selected class
$quizzes = [];
if ($selected_class) {
    $stmt = $conn->prepare("
        SELECT q.*, 
               (SELECT COUNT(*) FROM soal_quiz WHERE quiz_id = q.id) as total_soal,
               (SELECT COUNT(*) FROM jawaban_siswa js 
                JOIN soal_quiz sq ON js.soal_id = sq.id 
                WHERE sq.quiz_id = q.id AND js.siswa_id = ?) as jawaban_count
        FROM quiz q
        WHERE q.kelas_id = ?
        ORDER BY q.waktu_mulai DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $selected_class]);
    $quizzes = $stmt->fetchAll();
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $quiz_id = $_POST['quiz_id'];
    $answers = $_POST['answers'];
    
    try {
        $conn->beginTransaction();
        
        foreach ($answers as $soal_id => $jawaban) {
            $stmt = $conn->prepare("
                INSERT INTO jawaban_siswa (siswa_id, soal_id, jawaban)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE jawaban = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $soal_id, $jawaban, $jawaban]);
        }
        
        $conn->commit();
        $success_message = "Jawaban quiz berhasil disimpan!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Terjadi kesalahan saat menyimpan jawaban: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - StatiCore</title>
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

        .quiz-card {
            transition: transform 0.2s;
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: none;
            overflow: hidden;
        }

        .quiz-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            padding: 24px;
        }

        .card-title {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 16px;
        }

        .card-text {
            color: var(--gray-600);
            margin-bottom: 20px;
        }

        .list-unstyled li {
            color: var(--gray-600);
            margin-bottom: 8px;
        }

        .list-unstyled li i {
            color: var(--primary);
            margin-right: 8px;
        }

        .badge {
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 500;
        }

        .badge.bg-success {
            background: linear-gradient(135deg, var(--success), #059669) !important;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, var(--warning), #D97706) !important;
        }

        .badge.bg-secondary {
            background: linear-gradient(135deg, var(--gray-500), var(--gray-600)) !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 12px 24px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .alert {
            border-radius: var(--border-radius-sm);
            border: none;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
            border-left: 4px solid var(--info);
        }

        .form-label {
            color: var(--gray-700);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-select {
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-sm);
            padding: 12px 16px;
            color: var(--gray-700);
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        /* RESPONSIVE STYLES */
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                left: -250px;
                top: 0;
                bottom: 0;
                width: 250px;
                z-index: 1000;
                transition: all 0.3s ease-in-out;
            }

            .sidebar.active {
                left: 0;
            }

            .content-wrapper {
                margin-left: 0 !important;
                padding: 1rem;
            }

            .mobile-menu-toggle {
                display: block !important;
                position: fixed;
                right: 20px;
                bottom: 20px;
                z-index: 1001;
                background-color: #3B3B1A;
                color: white;
                border: none;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            }

            .card-body h5 {
                font-size: 1.25rem;
            }

            .list-unstyled li {
                font-size: 0.95rem;
            }

            .btn-primary {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .Title {
                font-size: 1.25rem;
            }

            .card-title {
                font-size: 1.1rem;
            }

            .list-unstyled li {
                margin-bottom: 0.5rem;
            }

            .badge {
                font-size: 0.85rem;
            }

            .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .btn-secondary {
                width: 100%;
                margin-top: 1rem;
            }
        }

        @media (max-width: 576px) {
            .sidebar h4 {
                font-size: 1.1rem;
            }

            .nav-link i {
                font-size: 1.1rem;
            }

            .card-body {
                padding: 1rem;
            }

            .form-control,
            .form-select {
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
            }

            .btn {
                font-size: 0.85rem;
            }

            .material-card {
                margin-bottom: 1.25rem;
            }

            .material-actions {
                flex-wrap: wrap;
                justify-content: space-between;
            }

            .material-actions .btn {
                width: 48%;
                text-align: center;
            }

            .empty-state-icon {
                font-size: 2.5rem;
            }

            .empty-state h3 {
                font-size: 1.25rem;
            }

            .empty-state p {
                font-size: 0.95rem;
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
            <div class="col-md-9 col-lg-10 p-4 content-wrapper">
                <div class="mb-4 Title">Quiz</div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Nilai Anda telah dihitung secara otomatis. Silakan lihat hasilnya di halaman Nilai.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Anda sudah pernah mengerjakan quiz ini dan tidak dapat mengerjakan ulang.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Class Selection -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Pilih Kelas</label>
                                <select class="form-select" name="class_id" onchange="this.form.submit()">
                                    <option value="">Pilih Kelas</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" 
                                                <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo $class['nama_kelas']; ?> 
                                            (<?php echo $class['tahun_ajaran']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($selected_class && !empty($quizzes)): ?>
                    <!-- Quizzes List -->
                    <div class="row">
                        <?php foreach ($quizzes as $quiz): ?>
                            <?php
                            $now = new DateTime();
                            $start = new DateTime($quiz['waktu_mulai']);
                            $end = new DateTime($quiz['waktu_selesai']);
                            $status = '';
                            $status_class = '';
                            
                            if ($now < $start) {
                                $status = 'Belum Dimulai';
                                $status_class = 'warning';
                            } elseif ($now > $end) {
                                $status = 'Selesai';
                                $status_class = 'secondary';
                            } else {
                                $status = 'Sedang Berlangsung';
                                $status_class = 'success';
                            }
                            // Cek apakah siswa sudah pernah mengerjakan quiz ini
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM jawaban_siswa js JOIN soal_quiz sq ON js.soal_id = sq.id WHERE js.siswa_id = ? AND sq.quiz_id = ?");
                            $stmt->execute([$_SESSION['user_id'], $quiz['id']]);
                            $already_done = $stmt->fetchColumn();
                            ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <a href="detail_quiz.php?id=<?php echo $quiz['id']; ?>" class="text-decoration-none">
                                    <div class="card quiz-card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo $quiz['judul']; ?></h5>
                                            <p class="card-text"><?php echo $quiz['deskripsi']; ?></p>
                                            <ul class="list-unstyled">
                                                <li><i class="fas fa-clock me-2"></i>Durasi: <?php echo $quiz['durasi']; ?> menit</li>
                                                <li><i class="fas fa-question-circle me-2"></i>Jumlah Soal: <?php echo $quiz['total_soal']; ?></li>
                                                <li><i class="fas fa-calendar me-2"></i>Mulai: <?php echo date('d/m/Y H:i', strtotime($quiz['waktu_mulai'])); ?></li>
                                                <li><i class="fas fa-calendar-check me-2"></i>Selesai: <?php echo date('d/m/Y H:i', strtotime($quiz['waktu_selesai'])); ?></li>
                                            </ul>
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status; ?></span>
                                                <?php if ($already_done > 0): ?>
                                                    <span class="badge bg-success" style="font-size:1em;">Sudah Mengerjakan</span>
                                                <?php elseif ($now >= $start && $now <= $end): ?>
                                                    <span class="badge bg-primary" style="font-size:1em;">Mulai Quiz</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($selected_class): ?>
                    <div class="alert alert-info">
                        Belum ada quiz untuk kelas ini.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
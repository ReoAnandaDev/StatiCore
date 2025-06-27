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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c5282;
            --secondary: #4299e1;
            --success: #10B981;
            --warning: #F59E0B;
            --info: #3B82F6;
            --danger: #EF4444;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --border-radius-sm: 8px;
            --border-radius-lg: 16px;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
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
        .quiz-card {
            transition: transform 0.2s;
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .quiz-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* BARU: CSS untuk Hamburger & Overlay */
        .mobile-menu-toggle { display: none; }
        .sidebar-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            opacity: 0; visibility: hidden;
            transition: all 0.3s ease;
        }
        .sidebar-overlay.show { opacity: 1; visibility: visible; }
        
        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                left: -280px;
                height: 100%;
                width: 280px;
                z-index: 1050;
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
            }
            .page-title {
                margin-top: 3.5rem;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="container-fluid">
        <div class="row">
            <div id="sidebar" class="col-md-3 col-lg-2 px-0 sidebar"> <div class="p-3">
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

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content"> <h1 class="page-title">Daftar Quiz</h1>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
                    <div class="alert alert-warning">Anda sudah mengerjakan quiz ini.</div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <label for="class_id" class="form-label"><strong>Pilih Kelas</strong></label>
                                <select class="form-select" name="class_id" id="class_id" onchange="this.form.submit()">
                                    <option value="">-- Tampilkan Quiz untuk Kelas --</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['nama_kelas']); ?> (<?php echo htmlspecialchars($class['tahun_ajaran']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($selected_class): ?>
                    <div class="row">
                        <?php if (!empty($quizzes)): ?>
                            <?php foreach ($quizzes as $quiz): ?>
                                <?php
                                $now = new DateTime();
                                $start = new DateTime($quiz['waktu_mulai']);
                                $end = new DateTime($quiz['waktu_selesai']);
                                $can_start = ($now >= $start && $now <= $end && $quiz['jawaban_count'] == 0);
                                $status_text = 'Telah Selesai';
                                $status_class = 'secondary';
                                if ($now < $start) {
                                    $status_text = 'Akan Datang';
                                    $status_class = 'warning text-dark';
                                } elseif ($can_start) {
                                    $status_text = 'Tersedia';
                                    $status_class = 'success';
                                } elseif ($quiz['jawaban_count'] > 0) {
                                    $status_text = 'Sudah Dikerjakan';
                                    $status_class = 'info';
                                }
                                ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card quiz-card h-100">
                                        <div class="card-body d-flex flex-column">
                                            <h5 class="card-title"><?php echo htmlspecialchars($quiz['judul']); ?></h5>
                                            <p class="card-text text-muted small flex-grow-1"><?php echo htmlspecialchars($quiz['deskripsi']); ?></p>
                                            <ul class="list-unstyled small text-muted">
                                                <li><i class="fas fa-list-ol"></i> <?php echo $quiz['total_soal']; ?> Soal</li>
                                                <li><i class="fas fa-clock"></i> <?php echo $quiz['durasi']; ?> Menit</li>
                                                <li><i class="fas fa-calendar-alt"></i> Dibuka: <?php echo $start->format('d M Y, H:i'); ?></li>
                                            </ul>
                                            <a href="detail_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary mt-auto">
                                                Lihat Detail
                                            </a>
                                            <div class="mt-2 text-center">
                                                <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="card text-center py-5">
                                    <div class="card-body">
                                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                        <h4>Belum Ada Quiz</h4>
                                        <p class="text-muted">Tidak ada quiz yang tersedia untuk kelas ini saat ini.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card text-center py-5">
                         <div class="card-body">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h4>Pilih Kelas</h4>
                            <p class="text-muted">Silakan pilih kelas untuk melihat daftar quiz yang tersedia.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // BARU: Logika untuk Hamburger Menu
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('mobileToggle');

            if(sidebar && overlay && menuToggle) {
                const toggleSidebar = () => {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('show');
                };
                menuToggle.addEventListener('click', toggleSidebar);
                overlay.addEventListener('click', toggleSidebar);
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth > 767.98 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('show');
                }
            });
            
            // Skrip konfirmasi logout
            const logoutLink = document.getElementById('logoutBtn');
            if (logoutLink) {
                logoutLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Apakah Anda ingin keluar?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--primary)',
                        cancelButtonColor: '#6c757d',
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
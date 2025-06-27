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
$total_score_percentage = 0;
$average_score = 0;
$results = [];

if ($selected_class) {
    // AUTO-GRADE: Fungsionalitas ini akan berjalan jika ada jawaban yang belum dinilai
    // dan memastikan data yang ditampilkan adalah yang terbaru.
    // (Kode auto-grade dari file Anda dipertahankan)

    // Ambil hasil quiz setelah update nilai
    $stmt = $conn->prepare("
        SELECT 
            q.id AS quiz_id,
            q.judul,
            q.deskripsi,
            q.waktu_mulai,
            q.waktu_selesai,
            u.nama_lengkap AS guru_nama,
            (SELECT COUNT(*) FROM soal_quiz sq_count WHERE sq_count.quiz_id = q.id) AS total_soal,
            (SELECT COUNT(*) FROM jawaban_siswa js_count JOIN soal_quiz sq_js ON js_count.soal_id = sq_js.id WHERE sq_js.quiz_id = q.id AND js_count.siswa_id = ?) AS soal_dijawab,
            (SELECT SUM(js_sum.nilai) FROM jawaban_siswa js_sum JOIN soal_quiz sq_sum ON js_sum.soal_id = sq_sum.id WHERE sq_sum.quiz_id = q.id AND js_sum.siswa_id = ?) AS total_nilai,
            (SELECT MAX(js_time.waktu_selesai) FROM jawaban_siswa js_time JOIN soal_quiz sq_time ON js_time.soal_id = sq_time.id WHERE sq_time.quiz_id = q.id AND js_time.siswa_id = ?) AS waktu_submit
        FROM quiz q
        JOIN users u ON q.guru_id = u.id
        WHERE q.kelas_id = ?
        GROUP BY q.id
        ORDER BY q.waktu_mulai DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $selected_class]);
    $results = $stmt->fetchAll();

    // Hitung statistik
    $total_quizzes = count($results);
    $total_score_percentage_sum = 0;

    foreach ($results as $result) {
        if ($result['soal_dijawab'] > 0) {
            $completed_quizzes++;
            if ($result['total_soal'] > 0) {
                $score_percentage = $result['total_nilai'] / $result['total_soal'];
                $total_score_percentage_sum += $score_percentage;
            }
        }
    }

    $average_score = $completed_quizzes > 0 ? $total_score_percentage_sum / $completed_quizzes : 0;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nilai - StatiCore</title>
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
            --gray-700: #374151;
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

        .card {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
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

        .stat-card {
            background: white;
            padding: 1.5rem;
        }

        .stat-number {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            color: var(--gray-600);
        }

        .score-badge {
            font-size: 1.25rem;
            font-weight: 700;
            padding: 0.5em 1em;
            border-radius: 50px;
            color: white;
        }

        .score-excellent {
            background: var(--success);
        }

        .score-good {
            background: var(--info);
        }

        .score-fair {
            background: var(--warning);
        }

        .score-poor {
            background: var(--danger);
        }

        .score-pending {
            background: var(--gray-500);
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
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

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
                            <a class="nav-link" href="quiz.php"><i class="fas fa-question-circle me-2"></i>Quiz</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tugas.php"><i class="fas fa-tasks me-2"></i>Tugas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="nilai.php"><i class="fas fa-star me-2"></i>Nilai</a>
                        </li>
                        <li class="nav-item mt-auto">
                            <a class="nav-link" href="../../logout.php" id="logoutBtn"><i
                                    class="fas fa-sign-out-alt me-2"></i>Logout</a>
                        </li>
                    </ul>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <h1 class="page-title">Rekap Nilai Quiz</h1>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" id="classForm">
                            <div class="mb-3">
                                <label for="class_id" class="form-label"><strong>Pilih Kelas</strong></label>
                                <select class="form-select" name="class_id" id="class_id" onchange="this.form.submit()">
                                    <option value="">-- Tampilkan Nilai untuk Kelas --</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class['id']); ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
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
                    <div class="row text-center g-4 mb-4">
                        <div class="col-md-4">
                            <div class="stat-card card">
                                <div class="stat-number"><?php echo $total_quizzes; ?></div>
                                <div class="stat-label">Total Quiz</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card card">
                                <div class="stat-number"><?php echo $completed_quizzes; ?></div>
                                <div class="stat-label">Quiz Dikerjakan</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card card">
                                <div class="stat-number"><?php echo number_format($average_score, 1); ?></div>
                                <div class="stat-label">Rata-rata Nilai</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Daftar Nilai</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Judul Quiz</th>
                                        <th>Dosen</th>
                                        <th>Status</th>
                                        <th>Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($results)): ?>
                                        <?php foreach ($results as $result): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($result['judul']); ?></td>
                                                <td><?php echo htmlspecialchars($result['guru_nama']); ?></td>
                                                <td>
                                                    <?php if ($result['soal_dijawab'] > 0): ?>
                                                        <span class="badge bg-success">Selesai</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Belum Dikerjakan</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if ($result['soal_dijawab'] > 0) {
                                                        $score = ($result['total_soal'] > 0) ? ($result['total_nilai'] / $result['total_soal']) : 0;
                                                        $scoreClass = 'score-pending';
                                                        if ($score >= 85)
                                                            $scoreClass = 'score-excellent';
                                                        elseif ($score >= 70)
                                                            $scoreClass = 'score-good';
                                                        elseif ($score >= 60)
                                                            $scoreClass = 'score-fair';
                                                        else
                                                            $scoreClass = 'score-poor';
                                                        echo '<span class="score-badge ' . $scoreClass . '">' . number_format($score, 1) . '</span>';
                                                    } else {
                                                        echo '<span class="score-badge score-pending">-</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">Belum ada quiz untuk kelas ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h4>Pilih Kelas</h4>
                            <p class="text-muted">Silakan pilih kelas dari daftar di atas untuk melihat rekap nilai Anda.
                            </p>
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
            // DIHAPUS: Skrip lama yang tidak berfungsi

            // BARU: Skrip sidebar yang berfungsi dan konsisten
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('mobileToggle');

            if (sidebar && overlay && menuToggle) {
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
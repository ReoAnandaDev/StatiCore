<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is student
$auth->checkSession();
$auth->requireRole('siswa');

$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Get student's classes
$stmt_kelas = $conn->prepare("
    SELECT k.*, 
           (SELECT COUNT(*) FROM materi m WHERE m.kelas_id = k.id) as total_materi,
           (SELECT COUNT(*) FROM quiz q WHERE q.kelas_id = k.id) as total_quiz
    FROM kelas k
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE sk.siswa_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt_kelas->execute([$user_id]);
$classes = $stmt_kelas->fetchAll(PDO::FETCH_ASSOC);

// Get recent materials
$stmt_materi = $conn->prepare("
    SELECT m.*, k.nama_kelas, u.nama_lengkap as guru_nama
    FROM materi m
    JOIN kelas k ON m.kelas_id = k.id
    JOIN users u ON m.guru_id = u.id
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE sk.siswa_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt_materi->execute([$user_id]);
$recent_materials = $stmt_materi->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming quizzes
$stmt_quiz = $conn->prepare("
    SELECT q.*, k.nama_kelas, u.nama_lengkap as guru_nama,
           (SELECT COUNT(*) FROM soal_quiz sq WHERE sq.quiz_id = q.id) as total_soal,
           (SELECT COUNT(*) FROM jawaban_siswa js 
            JOIN soal_quiz sq ON js.soal_id = sq.id 
            WHERE sq.quiz_id = q.id AND js.siswa_id = ?) as total_jawaban
    FROM quiz q
    JOIN kelas k ON q.kelas_id = k.id
    JOIN users u ON q.guru_id = u.id
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE sk.siswa_id = ? AND q.waktu_selesai > NOW()
    ORDER BY q.waktu_mulai ASC
    LIMIT 5
");
$stmt_quiz->execute([$user_id, $user_id]);
$upcoming_quizzes = $stmt_quiz->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming tasks
$stmt_tugas = $conn->prepare("
    SELECT t.*, k.nama_kelas, jt.nama as jenis_tugas, u.nama_lengkap as nama_guru
    FROM tugas t
    JOIN kelas k ON t.kelas_id = k.id
    JOIN jenis_tugas jt ON t.jenis_tugas_id = jt.id
    JOIN users u ON t.guru_id = u.id
    JOIN siswa_kelas sk ON t.kelas_id = sk.kelas_id
    WHERE sk.siswa_id = ? AND t.batas_pengumpulan > NOW()
    ORDER BY t.batas_pengumpulan ASC
    LIMIT 5
");
$stmt_tugas->execute([$user_id]);
$upcoming_tasks = $stmt_tugas->fetchAll(PDO::FETCH_ASSOC);

// Get recent quiz results
$stmt_hasil = $conn->prepare("
    SELECT 
        q.id,
        q.judul,
        k.nama_kelas,
        (
            SELECT ROUND((SUM(js.nilai) / COUNT(sq.id)), 1)
            FROM jawaban_siswa js
            JOIN soal_quiz sq ON js.soal_id = sq.id
            WHERE sq.quiz_id = q.id AND js.siswa_id = ?
        ) as nilai
    FROM quiz q
    JOIN kelas k ON q.kelas_id = k.id
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE sk.siswa_id = ? AND q.waktu_selesai < NOW()
      AND EXISTS (
          SELECT 1 FROM jawaban_siswa js 
          JOIN soal_quiz sq ON js.soal_id = sq.id 
          WHERE sq.quiz_id = q.id AND js.siswa_id = ?
      )
    ORDER BY q.waktu_selesai DESC
    LIMIT 5
");
$stmt_hasil->execute([$user_id, $user_id, $user_id]);
$quiz_results = $stmt_hasil->fetchAll(PDO::FETCH_ASSOC);

// Calculate total materials and quizzes
$total_materi_count = 0;
$total_quiz_count = 0;
foreach ($classes as $class) {
    $total_materi_count += $class['total_materi'];
    $total_quiz_count += $class['total_quiz'];
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa - StatiCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5282;
            --secondary-color: #4299e1;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --gray-text: #6c757d;
            --border-color: #e5e7eb;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --border-radius: 0.75rem;
            --success: #10B981;
            --info: #3B82F6;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
        }

        /* --- Sidebar --- */
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
        }

        .sidebar h4 {
            font-weight: 600;
            color: white;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.8rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.2s ease-in-out;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--white);
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        /* --- Main Content --- */
        .main-content {
            padding: 1.5rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        /* --- Stat Cards --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--white);
            flex-shrink: 0;
        }

        .stat-icon.bg-primary {
            background-color: var(--primary-color);
        }

        .stat-icon.bg-success {
            background-color: var(--success);
        }

        .stat-icon.bg-info {
            background-color: var(--info);
        }

        .stat-info .stat-title {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-text);
            margin: 0;
        }

        .stat-info .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1.2;
        }

        /* --- Content Cards --- */
        .content-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .content-card .card-header {
            background-color: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .content-card .card-body {
            padding: 0.5rem;
        }

        /* --- List Group Customization --- */
        .list-group-item {
            display: flex;
            flex-wrap: wrap;
            justify-content-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
        }

        .list-group-item small {
            color: var(--gray-text);
            display: block;
            margin-top: 0.25rem;
        }

        /* --- Empty State --- */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--gray-text);
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* --- Mobile Responsiveness --- */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
        }

        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1049;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 280px;
                z-index: 1050;
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }

            .sidebar.is-open {
                transform: translateX(0);
            }

            .sidebar-backdrop.is-visible {
                display: block;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .main-content {
                padding: 1rem;
            }

            .page-title {
                margin-top: 3.5rem;
                font-size: 1.5rem;
            }

            .list-group-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .list-group-item .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <button class="mobile-menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
            <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

            <div class="col-md-3 col-lg-2 px-0 sidebar" id="sidebar">
                <div class="p-3">
                    <h4 class="px-2 my-3"><i class="fas fa-chart-line me-2"></i>StatiCore</h4>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i
                                    class="fas fa-home fa-fw"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="materi.php"><i
                                    class="fas fa-book fa-fw"></i>Materi</a></li>
                        <li class="nav-item"><a class="nav-link" href="quiz.php"><i
                                    class="fas fa-question-circle fa-fw"></i>Quiz</a></li>
                        <li class="nav-item"><a class="nav-link" href="tugas.php"><i
                                    class="fas fa-tasks fa-fw"></i>Tugas</a></li>
                        <li class="nav-item"><a class="nav-link" href="nilai.php"><i
                                    class="fas fa-star fa-fw"></i>Nilai</a></li>
                        <li class="nav-item mt-auto"><a class="nav-link" href="../../logout.php" id="logoutBtn"><i
                                    class="fas fa-sign-out-alt fa-fw"></i>Logout</a></li>
                    </ul>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <h1 class="page-title">Selamat Datang, <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>!</h1>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-info">
                            <p class="stat-title">Total Kelas</p>
                            <h3 class="stat-value"><?php echo count($classes); ?></h3>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-success"><i class="fas fa-book"></i></div>
                        <div class="stat-info">
                            <p class="stat-title">Total Materi</p>
                            <h3 class="stat-value"><?php echo $total_materi_count; ?></h3>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-info"><i class="fas fa-question-circle"></i></div>
                        <div class="stat-info">
                            <p class="stat-title">Total Quiz</p>
                            <h3 class="stat-value"><?php echo $total_quiz_count; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="content-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Aktivitas Mendatang</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcoming_quizzes) && empty($upcoming_tasks)): ?>
                                    <div class="empty-state"><i class="fas fa-check-circle"></i>
                                        <p>Tidak ada quiz atau tugas mendatang.</p>
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($upcoming_quizzes as $quiz): ?>
                                            <li class="list-group-item">
                                                <div>
                                                    <strong><i
                                                            class="fas fa-clipboard-question text-info me-2"></i><?php echo htmlspecialchars($quiz['judul']); ?></strong>
                                                    <small>Quiz | Batas:
                                                        <?php echo date('d M Y, H:i', strtotime($quiz['waktu_selesai'])); ?></small>
                                                </div>
                                                <a href="kerjakan_quiz.php?id=<?php echo $quiz['id']; ?>"
                                                    class="btn btn-sm btn-primary">Kerjakan</a>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php foreach ($upcoming_tasks as $task): ?>
                                            <li class="list-group-item">
                                                <div>
                                                    <strong><i
                                                            class="fas fa-file-alt text-success me-2"></i><?php echo htmlspecialchars($task['judul']); ?></strong>
                                                    <small>Tugas | Batas:
                                                        <?php echo date('d M Y, H:i', strtotime($task['batas_pengumpulan'])); ?></small>
                                                </div>
                                                <a href="kumpul_tugas.php?id=<?php echo $task['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">Lihat Tugas</a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="content-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-book-open me-2"></i>Materi Terbaru</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_materials)): ?>
                                    <div class="empty-state"><i class="fas fa-box-open"></i>
                                        <p>Belum ada materi terbaru.</p>
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($recent_materials as $material): ?>
                                            <li class="list-group-item">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($material['judul']); ?></strong>
                                                    <small>Di kelas:
                                                        <?php echo htmlspecialchars($material['nama_kelas']); ?></small>
                                                </div>
                                                <a href="detail_materi.php?id=<?php echo $material['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">Lihat Materi</a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="content-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-poll me-2"></i>Hasil Quiz Terbaru</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($quiz_results)): ?>
                                    <div class="empty-state"><i class="fas fa-chart-pie"></i>
                                        <p>Belum ada hasil quiz yang bisa ditampilkan.</p>
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($quiz_results as $result): ?>
                                            <li class="list-group-item">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($result['judul']); ?></strong>
                                                    <small>Kelas: <?php echo htmlspecialchars($result['nama_kelas']); ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge fs-6 rounded-pill text-bg-primary">
                                                        Nilai:
                                                        <?php echo $result['nilai'] !== null ? htmlspecialchars($result['nilai']) : 'N/A'; ?>
                                                    </span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mobile Sidebar Toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');

            const openSidebar = () => {
                sidebar.classList.add('is-open');
                sidebarBackdrop.classList.add('is-visible');
            };
            const closeSidebar = () => {
                sidebar.classList.remove('is-open');
                sidebarBackdrop.classList.remove('is-visible');
            };

            if (menuToggle) menuToggle.addEventListener('click', openSidebar);
            if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeSidebar);

            // Logout Confirmation
            const logoutLink = document.getElementById('logoutBtn');
            if (logoutLink) {
                logoutLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Apakah Anda yakin?',
                        text: "Anda akan keluar dari sesi ini.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--primary-color)',
                        cancelButtonColor: 'var(--gray-text)',
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
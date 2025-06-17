<?php
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
    SELECT k.*, 
           (SELECT COUNT(*) FROM materi m WHERE m.kelas_id = k.id) as total_materi,
           (SELECT COUNT(*) FROM quiz q WHERE q.kelas_id = k.id) as total_quiz
    FROM kelas k
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE sk.siswa_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

// Get recent materials
$stmt = $conn->prepare("
    SELECT m.*, k.nama_kelas, u.nama_lengkap as guru_nama
    FROM materi m
    JOIN kelas k ON m.kelas_id = k.id
    JOIN users u ON m.guru_id = u.id
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE sk.siswa_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_materials = $stmt->fetchAll();

// Get upcoming quizzes
$stmt = $conn->prepare("
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
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$upcoming_quizzes = $stmt->fetchAll();

// Get quiz results
$stmt = $conn->prepare("
    SELECT q.*, k.nama_kelas, u.nama_lengkap as guru_nama,
           (SELECT AVG(nilai) FROM jawaban_siswa js 
            JOIN soal_quiz sq ON js.soal_id = sq.id 
            WHERE sq.quiz_id = q.id AND js.siswa_id = ?) as nilai_rata_rata
    FROM quiz q
    JOIN kelas k ON q.kelas_id = k.id
    JOIN users u ON q.guru_id = u.id
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE sk.siswa_id = ? AND q.waktu_selesai < NOW()
    ORDER BY q.waktu_selesai DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$quiz_results = $stmt->fetchAll();

// Get total tasks for the student
$stmt = $conn->prepare("
    SELECT COUNT(t.id) as total_tugas
    FROM tugas t
    JOIN siswa_kelas sk ON t.kelas_id = sk.kelas_id
    WHERE sk.siswa_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$total_tugas = $stmt->fetchColumn();

// Get upcoming tasks
$stmt = $conn->prepare("
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
$stmt->execute([$_SESSION['user_id']]);
$upcoming_tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa/i - StatiCore</title>
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            font-size: 20px;
            color: white;
        }

        .stat-icon.primary {
            background: var(--primary);
        }

        .stat-icon.success {
            background: var(--success);
        }

        .stat-icon.info {
            background: var(--info);
        }

        .stat-title {
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-600);
            margin: 0;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .content-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .content-card:hover {
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

        .bg-info {
            background: rgba(13, 202, 240, 0.1) !important;
            color: var(--info) !important;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                bottom: 0;

                width: 280px;
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
                            <a class="nav-link active" href="dashboard.php">
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
            <div class="col-md-9 col-lg-10 p-4">
                <div class="mb-4 Title">Dashboard</div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon primary">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div>
                                <p class="stat-title">Total Kelas</p>
                                <h3 class="stat-value"><?php echo count($classes); ?></h3>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon success">
                                <i class="fas fa-book"></i>
                            </div>
                            <div>
                                <p class="stat-title">Total Materi</p>
                                <h3 class="stat-value">
                                    <?php
                                    $total_materi_count = 0;
                                    foreach ($classes as $class) {
                                        $total_materi_count += $class['total_materi'];
                                    }
                                    echo $total_materi_count;
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon info">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div>
                                <p class="stat-title">Total Quiz</p>
                                <h3 class="stat-value">
                                    <?php
                                    $total_quiz_count = 0;
                                    foreach ($classes as $class) {
                                        $total_quiz_count += $class['total_quiz'];
                                    }
                                    echo $total_quiz_count;
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon primary">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div>
                                <p class="stat-title">Total Tugas</p>
                                <h3 class="stat-value"><?php echo $total_tugas; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content-grid">
                    <div class="content-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-book-open me-2"></i>Materi Terbaru</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_materials)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-box-open"></i>
                                    <p>Belum ada materi terbaru.</p>
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent_materials as $material): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($material['judul']); ?></strong>
                                                <br><small class="text-muted">Kelas:
                                                    <?php echo htmlspecialchars($material['nama_kelas']); ?> - Guru:
                                                    <?php echo htmlspecialchars($material['guru_nama']); ?></small>
                                            </div>
                                            <a href="detail_materi.php?id=<?php echo $material['id']; ?>"
                                                class="btn btn-sm btn-primary">Lihat</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-calendar-alt me-2"></i>Quiz Mendatang</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($upcoming_quizzes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-question"></i>
                                    <p>Tidak ada quiz mendatang.</p>
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($upcoming_quizzes as $quiz): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($quiz['judul']); ?></strong>
                                                <br><small class="text-muted">Kelas:
                                                    <?php echo htmlspecialchars($quiz['nama_kelas']); ?> | Mulai:
                                                    <?php echo date('d M H:i', strtotime($quiz['waktu_mulai'])); ?> | Durasi:
                                                    <?php echo htmlspecialchars($quiz['durasi']); ?> menit</small>
                                            </div>
                                            <a href="kerjakan_quiz.php?id=<?php echo $quiz['id']; ?>"
                                                class="btn btn-sm btn-primary">Kerjakan</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-tasks me-2"></i>Tugas Mendatang</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($upcoming_tasks)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <p>Tidak ada tugas mendatang.</p>
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($upcoming_tasks as $task): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($task['judul']); ?></strong>
                                                <br><small class="text-muted">Kelas:
                                                    <?php echo htmlspecialchars($task['nama_kelas']); ?> | Batas:
                                                    <?php echo date('d M H:i', strtotime($task['batas_pengumpulan'])); ?></small>
                                            </div>
                                            <a href="kumpul_tugas.php?id=<?php echo $task['id']; ?>"
                                                class="btn btn-sm btn-primary">Detail</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="content-card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-graduation-cap me-2"></i>Hasil Quiz Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($quiz_results)): ?>
                            <div class="empty-state">
                                <i class="fas fa-poll"></i>
                                <p>Belum ada hasil quiz.</p>
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($quiz_results as $result): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($result['judul']); ?></strong>
                                            <br><small class="text-muted">Kelas:
                                                <?php echo htmlspecialchars($result['nama_kelas']); ?> | Nilai:
                                                <?php echo number_format($result['nilai_rata_rata'], 2); ?></small>
                                        </div>
                                        <a href="detail_quiz.php?id=<?php echo $result['id']; ?>"
                                            class="btn btn-sm btn-secondary">Detail</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
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
        });
    </script>
</body>

</html>
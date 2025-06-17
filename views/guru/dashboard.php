<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is teacher
$auth->checkSession();
$auth->requireRole('guru');

$conn = $db->getConnection();

// Get teacher's statistics
$teacher_id = $_SESSION['user_id'];
$stats = [
    'total_kelas' => $conn->query("SELECT COUNT(DISTINCT kelas_id) FROM guru_kelas WHERE guru_id = $teacher_id")->fetchColumn(),
    'total_materi' => $conn->query("SELECT COUNT(*) FROM materi WHERE guru_id = $teacher_id")->fetchColumn(),
    'total_quiz' => $conn->query("SELECT COUNT(*) FROM quiz WHERE guru_id = $teacher_id")->fetchColumn(),
    'total_siswa' => $conn->query("
        SELECT COUNT(DISTINCT sk.siswa_id) 
        FROM siswa_kelas sk 
        JOIN guru_kelas gk ON sk.kelas_id = gk.kelas_id 
        WHERE gk.guru_id = $teacher_id
    ")->fetchColumn()
];

// Get teacher's classes
$stmt = $conn->prepare("
    SELECT k.* FROM kelas k
    JOIN guru_kelas gk ON k.id = gk.kelas_id
    WHERE gk.guru_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();

// Get recent materials
$stmt = $conn->prepare("
    SELECT m.*, k.nama_kelas 
    FROM materi m 
    JOIN kelas k ON m.kelas_id = k.id 
    WHERE m.guru_id = ? 
    ORDER BY m.created_at DESC 
    LIMIT 5
");
$stmt->execute([$teacher_id]);
$recent_materi = $stmt->fetchAll();

// Get recent quizzes
$stmt = $conn->prepare("
    SELECT q.*, k.nama_kelas 
    FROM quiz q 
    JOIN kelas k ON q.kelas_id = k.id 
    WHERE q.guru_id = ? 
    ORDER BY q.created_at DESC 
    LIMIT 5
");
$stmt->execute([$teacher_id]);
$recent_quiz = $stmt->fetchAll();

// Get recent tasks
$stmt = $conn->prepare("
    SELECT t.*, k.nama_kelas, jt.nama as jenis_tugas
    FROM tugas t 
    JOIN kelas k ON t.kelas_id = k.id 
    JOIN jenis_tugas jt ON t.jenis_tugas_id = jt.id
    WHERE t.guru_id = ? 
    ORDER BY t.created_at DESC 
    LIMIT 5
");
$stmt->execute([$teacher_id]);
$recent_tugas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen - StatiCore</title>
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--white);
        }

        .stat-icon.primary {
            background: var(--primary);
        }

        .stat-icon.success {
            background: var(--success);
        }

        .stat-icon.warning {
            background: var(--warning);
        }

        .stat-icon.secondary {
            background: var(--secondary);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--gray-500);
            font-size: 14px;
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-300);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: white;
            margin: 0;
        }

        .card-body {
            padding: 24px;
        }

        /* List Items */
        .list-item {
            padding: 16px 0;
            border-bottom: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .list-item:hover {
            background: var(--gray-50);
            margin: 0 -24px;
            padding: 16px 24px;
            border-radius: var(--border-radius-sm);
        }

        .list-item-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .list-item-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .list-item-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--gray-500);
            font-size: 14px;
        }

        .list-item-meta i {
            margin-right: 4px;
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

        /* Loading Skeleton */
        .skeleton {
            background: linear-gradient(90deg, var(--gray-200) 25%, var(--gray-100) 50%, var(--gray-200) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        /* Responsive Design */
        @media (max-width: 768px) {

            /* Sidebar */
            .sidebar {
                position: fixed;
                top: 0;
                left: -280px;
                /* Sembunyikan di luar layar */
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

            /* Statistik card */
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

            /* Card body */
            .card-body,
            .card-header {
                padding: 16px;
            }

            .card-title {
                font-size: 16px;
            }

            /* Grid konten utama */
            .content-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            /* List item */
            .list-item-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
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
                            <a class="nav-link" href="upload_materi.php">
                                <i class="fas fa-book me-2"></i>Materi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="kelola_quiz.php">
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
                <div class="mb-4 Title">Dashboard</div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-number"><?php echo number_format($stats['total_kelas']); ?></div>
                                <div class="stat-label">Total Kelas</div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="fas fa-chalkboard"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-number"><?php echo number_format($stats['total_materi']); ?></div>
                                <div class="stat-label">Total Materi</div>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-book"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-number"><?php echo number_format($stats['total_quiz']); ?></div>
                                <div class="stat-label">Total Quiz</div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="fas fa-tasks"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-number"><?php echo number_format($stats['total_siswa']); ?></div>
                                <div class="stat-label">Total Mahasiswa/i</div>
                            </div>
                            <div class="stat-icon secondary">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- My Classes -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Kelas Saya</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($classes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-chalkboard"></i>
                                    <p>Belum ada kelas yang diampu</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($classes as $class): ?>
                                    <div class="list-item">
                                        <a href="detail_kelas.php?id=<?php echo htmlspecialchars($class['id']); ?>"
                                            class="list-item-link">
                                            <div class="list-item-title"><?php echo htmlspecialchars($class['nama_kelas']); ?>
                                            </div>
                                            <div class="list-item-meta">
                                                <span><i
                                                        class="fas fa-calendar"></i><?php echo htmlspecialchars($class['tahun_ajaran']); ?></span>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Materials -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Materi Terbaru</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_materi)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-book"></i>
                                    <p>Belum ada materi yang diunggah</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_materi as $materi): ?>
                                    <div class="list-item">
                                        <div class="list-item-title"><?php echo htmlspecialchars($materi['judul']); ?></div>
                                        <div class="list-item-meta">
                                            <span><i
                                                    class="fas fa-chalkboard"></i><?php echo htmlspecialchars($materi['nama_kelas']); ?></span>
                                            <span><i
                                                    class="fas fa-clock"></i><?php echo date('d/m/Y H:i', strtotime($materi['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Quizzes -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quiz Terbaru</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_quiz)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-tasks"></i>
                                    <p>Belum ada quiz yang dibuat</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_quiz as $quiz): ?>
                                    <div class="list-item">
                                        <div class="list-item-title"><?php echo htmlspecialchars($quiz['judul']); ?></div>
                                        <div class="list-item-meta">
                                            <span><i
                                                    class="fas fa-chalkboard"></i><?php echo htmlspecialchars($quiz['nama_kelas']); ?></span>
                                            <span><i
                                                    class="fas fa-clock"></i><?php echo date('d/m/Y H:i', strtotime($quiz['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Tasks -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Tugas Terbaru</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_tugas)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-tasks"></i>
                                    <p>Belum ada tugas yang dibuat</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_tugas as $tugas): ?>
                                    <div class="list-item">
                                        <div class="list-item-title"><?php echo htmlspecialchars($tugas['judul']); ?></div>
                                        <div class="list-item-meta">
                                            <span><i
                                                    class="fas fa-chalkboard"></i><?php echo htmlspecialchars($tugas['nama_kelas']); ?></span>
                                            <span><i
                                                    class="fas fa-tag"></i><?php echo htmlspecialchars($tugas['jenis_tugas']); ?></span>
                                            <span><i
                                                    class="fas fa-clock"></i><?php echo date('d/m/Y H:i', strtotime($tugas['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
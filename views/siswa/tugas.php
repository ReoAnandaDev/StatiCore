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
    SELECT k.* FROM kelas k
    JOIN siswa_kelas sk ON k.id = sk.kelas_id
    WHERE sk.siswa_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

// Get all tasks for the student
$stmt = $conn->prepare("
    SELECT t.*, k.nama_kelas, jt.nama as jenis_tugas, u.nama_lengkap as nama_guru,
           (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id = t.id AND pt.siswa_id = ?) as sudah_dikumpul
    FROM tugas t
    JOIN kelas k ON t.kelas_id = k.id
    JOIN jenis_tugas jt ON t.jenis_tugas_id = jt.id
    JOIN users u ON t.guru_id = u.id
    JOIN siswa_kelas sk ON t.kelas_id = sk.kelas_id
    WHERE sk.siswa_id = ?
    ORDER BY t.batas_pengumpulan DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugas - StatiCore</title>
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
            background-color: var(--gray-50);
            color: var(--gray-800);
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

        /* Task Cards */
        .task-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }

        .task-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .task-card .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-bottom: none;
            padding: 20px;
        }

        .task-card .card-body {
            padding: 24px;
        }

        .task-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .task-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
        }

        .task-info-item i {
            color: var(--primary);
            font-size: 1.2rem;
        }

        .task-description {
            color: var(--gray-600);
            margin-bottom: 24px;
            line-height: 1.6;
        }

        /* Status Badge */
        .status-badge {
            padding: 8px 16px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-badge.success {
            background: #D1FAE5;
            color: #065F46;
        }

        .status-badge.warning {
            background: #FEF3C7;
            color: #92400E;
        }

        .status-badge.danger {
            background: #FEE2E2;
            color: #991B1B;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: var(--gray-600);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--gray-700);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
            .Title {
                font-size: 1.25rem;
            }

            .task-card .card-body {
                padding: 20px;
            }

            .task-info {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
                margin-top: 8px;
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
                            <a class="nav-link" href="quiz.php">
                                <i class="fas fa-question-circle me-2"></i>Quiz
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="tugas.php">
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
                <div class="Title">Daftar Tugas</div>

                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <p>Belum ada tugas yang tersedia.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo htmlspecialchars($task['judul']); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="task-info">
                                    <div class="task-info-item">
                                        <i class="fas fa-chalkboard"></i>
                                        <span>Kelas: <?php echo htmlspecialchars($task['nama_kelas']); ?></span>
                                    </div>
                                    <div class="task-info-item">
                                        <i class="fas fa-user"></i>
                                        <span>Guru: <?php echo htmlspecialchars($task['nama_guru']); ?></span>
                                    </div>
                                    <div class="task-info-item">
                                        <i class="fas fa-tag"></i>
                                        <span>Jenis: <?php echo htmlspecialchars($task['jenis_tugas']); ?></span>
                                    </div>
                                    <div class="task-info-item">
                                        <i class="fas fa-clock"></i>
                                        <span>Batas:
                                            <?php echo date('d M Y H:i', strtotime($task['batas_pengumpulan'])); ?></span>
                                    </div>
                                </div>

                                <p class="task-description"><?php echo htmlspecialchars($task['deskripsi']); ?></p>

                                <?php
                                $now = new DateTime();
                                $deadline = new DateTime($task['batas_pengumpulan']);
                                $status = '';
                                $status_class = '';

                                if ($task['sudah_dikumpul'] > 0) {
                                    $status = 'Sudah Dikumpul';
                                    $status_class = 'success';
                                } elseif ($now > $deadline) {
                                    $status = 'Terlambat';
                                    $status_class = 'danger';
                                } else {
                                    $status = 'Belum Dikumpul';
                                    $status_class = 'warning';
                                }
                                ?>

                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                    <a href="kumpul_tugas.php?id=<?php echo $task['id']; ?>" class="btn btn-primary">
                                        <?php echo $task['sudah_dikumpul'] > 0 ? 'Lihat Detail' : 'Kumpul Tugas'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is admin
$auth->checkSession();
$auth->requireRole('admin');

$conn = $db->getConnection();

// Get statistics
$stats = [
    'total_kelas' => $conn->query("SELECT COUNT(*) FROM kelas")->fetchColumn(),
    'total_guru' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'guru'")->fetchColumn(),
    'total_siswa' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'")->fetchColumn(),
    'total_materi' => $conn->query("SELECT COUNT(*) FROM materi")->fetchColumn(),
    'total_quiz' => $conn->query("SELECT COUNT(*) FROM quiz")->fetchColumn(),
    'total_tugas' => $conn->query("SELECT COUNT(*) FROM tugas")->fetchColumn(),
    'total_pengumpulan' => $conn->query("SELECT COUNT(*) FROM pengumpulan_tugas")->fetchColumn(),
    'active_users' => $conn->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn()
];

// Get recent activities with more details
$recent_activities = $conn->query("
    (SELECT 'materi' as type, m.created_at, m.judul as title, u.nama_lengkap as user_name, k.nama_kelas
    FROM materi m 
    JOIN users u ON m.guru_id = u.id 
    JOIN kelas k ON m.kelas_id = k.id)
    UNION ALL
    (SELECT 'quiz' as type, q.created_at, q.judul as title, u.nama_lengkap as user_name, k.nama_kelas
    FROM quiz q 
    JOIN users u ON q.guru_id = u.id 
    JOIN kelas k ON q.kelas_id = k.id)
    UNION ALL
    (SELECT 'tugas' as type, t.created_at, t.judul as title, u.nama_lengkap as user_name, k.nama_kelas
    FROM tugas t 
    JOIN users u ON t.guru_id = u.id 
    JOIN kelas k ON t.kelas_id = k.id)
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// Get system status
$system_status = [
    'disk_usage' => disk_free_space('/') / disk_total_space('/') * 100,
    'php_version' => PHP_VERSION,
    'mysql_version' => $conn->query('SELECT VERSION()')->fetchColumn(),
    'server_software' => $_SERVER['SERVER_SOFTWARE']
];

// Get class statistics
$class_stats = $conn->query("
    SELECT k.nama_kelas, 
           COUNT(DISTINCT sk.siswa_id) as total_siswa,
           COUNT(DISTINCT m.id) as total_materi,
           COUNT(DISTINCT q.id) as total_quiz,
           COUNT(DISTINCT t.id) as total_tugas
    FROM kelas k
    LEFT JOIN siswa_kelas sk ON k.id = sk.kelas_id
    LEFT JOIN materi m ON k.id = m.kelas_id
    LEFT JOIN quiz q ON k.id = q.kelas_id
    LEFT JOIN tugas t ON k.id = t.kelas_id
    GROUP BY k.id
    ORDER BY k.nama_kelas
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - StatiCore</title>
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
        .stat-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 0.75rem;
        }

        /* Recent Activity */
        .recent-card {
            border-radius: var(--border-radius-md);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }

        .recent-card .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-bottom: none;
            padding: 1rem;
        }

        .recent-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s ease;
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-item:hover {
            background-color: #f8f9fa;
        }

        /* Responsive Adjustments */
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

            .content-wrapper {
                margin-left: 0 !important;
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

            .stat-card,
            .card,
            .card-body,
            .card-header {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }

            .Title {
                font-size: 1.1rem;
                margin-bottom: 18px;
            }

            .stat-icon {
                font-size: 1.5rem;
            }
        }

        .system-stat {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .system-stat i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .recent-activities {
            max-height: 500px;
            overflow-y: auto;
        }

        .table th {
            font-weight: 600;
            color: var(--gray-700);
        }

        .table td {
            vertical-align: middle;
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
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_classes.php">
                                <i class="fas fa-users me-2"></i>Kelola Kelas & Pengguna
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="detail_kelas.php">
                                <i class="fa-solid fa-book me-2"></i>Detail Kelas
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
                <div class="mb-4 Title">Dashboard Admin</div>

                <!-- System Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Status Sistem</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="system-stat">
                                    <i class="fas fa-server"></i>
                                    <span>PHP Version: <?php echo $system_status['php_version']; ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="system-stat">
                                    <i class="fas fa-database"></i>
                                    <span>MySQL Version: <?php echo $system_status['mysql_version']; ?></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="system-stat">
                                    <i class="fas fa-hdd"></i>
                                    <span>Disk Usage: <?php echo round(100 - $system_status['disk_usage'], 2); ?>%
                                        Free</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="system-stat">
                                    <i class="fas fa-users"></i>
                                    <span>Active Users (24h): <?php echo $stats['active_users']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-chalkboard stat-icon"></i>
                                <h5 class="card-title">Total Kelas</h5>
                                <h2 class="card-number"><?php echo $stats['total_kelas']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-tie stat-icon"></i>
                                <h5 class="card-title">Total Guru</h5>
                                <h2 class="card-number"><?php echo $stats['total_guru']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-graduate stat-icon"></i>
                                <h5 class="card-title">Total Siswa</h5>
                                <h2 class="card-number"><?php echo $stats['total_siswa']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-tasks stat-icon"></i>
                                <h5 class="card-title">Total Tugas</h5>
                                <h2 class="card-number"><?php echo $stats['total_tugas']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Class Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Statistik Kelas</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Kelas</th>
                                        <th>Total Siswa</th>
                                        <th>Total Materi</th>
                                        <th>Total Quiz</th>
                                        <th>Total Tugas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($class_stats as $class): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($class['nama_kelas']); ?></td>
                                            <td><?php echo $class['total_siswa']; ?></td>
                                            <td><?php echo $class['total_materi']; ?></td>
                                            <td><?php echo $class['total_quiz']; ?></td>
                                            <td><?php echo $class['total_tugas']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Aktivitas Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <div class="recent-activities">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="recent-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i
                                                class="fas fa-<?php echo $activity['type'] === 'materi' ? 'book' : ($activity['type'] === 'quiz' ? 'question-circle' : 'tasks'); ?> me-2"></i>
                                            <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                            <span class="text-muted ms-2">(<?php echo ucfirst($activity['type']); ?>)</span>
                                        </div>
                                        <div class="text-muted">
                                            <?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Oleh: <?php echo htmlspecialchars($activity['user_name']); ?> |
                                            Kelas: <?php echo htmlspecialchars($activity['nama_kelas']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
            // Logout SweetAlert
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
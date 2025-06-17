<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());
$auth->checkSession();
$auth->requireRole('siswa');
$conn = $db->getConnection();

// Get class ID from URL
$kelas_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$kelas_id) {
    header('Location: dashboard.php');
    exit;
}

// Get class info
$stmt = $conn->prepare("SELECT * FROM kelas WHERE id = ?");
$stmt->execute([$kelas_id]);
$kelas = $stmt->fetch();
if (!$kelas) {
    header('Location: dashboard.php');
    exit;
}

// Get students in class
$stmt = $conn->prepare("SELECT u.id, u.nama_lengkap FROM users u JOIN siswa_kelas sk ON u.id = sk.siswa_id WHERE sk.kelas_id = ? ORDER BY u.nama_lengkap");
$stmt->execute([$kelas_id]);
$siswa = $stmt->fetchAll();

// Get quizzes for class
$stmt = $conn->prepare("SELECT * FROM quiz WHERE kelas_id = ? ORDER BY waktu_mulai DESC");
$stmt->execute([$kelas_id]);
$quizzes = $stmt->fetchAll();

// Get materials for class
$stmt = $conn->prepare("SELECT m.*, u.nama_lengkap as guru_nama FROM materi m JOIN users u ON m.guru_id = u.id WHERE m.kelas_id = ? ORDER BY m.created_at DESC");
$stmt->execute([$kelas_id]);
$materi = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Kelas - StatiCore</title>
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

        /* Content Area */
        .content-area {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem 1rem;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            padding: 1.25rem 1.5rem;
            border: none;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-body.p-0 {
            padding: 0;
        }

        /* Class Info Card */
        .class-info-card {
            background: linear-gradient(135deg, var(--light), var(--white));
            border: 2px solid var(--primary);
        }

        .class-info-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .class-info-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .class-info-list li:last-child {
            border-bottom: none;
        }

        .class-info-label {
            font-weight: 600;
            color: var(--primary);
        }

        .class-info-value {
            color: var(--gray-700);
            font-weight: 500;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
            border-radius: var(--border-radius-md);
        }

        .table {
            margin: 0;
            font-size: 0.875rem;
        }

        .table thead th {
            background: var(--gray-50);
            color: var(--gray-700);
            font-weight: 600;
            border: none;
            padding: 1rem;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .table tbody td {
            padding: 1rem;
            border-color: var(--gray-200);
            color: var(--gray-700);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--gray-50);
        }

        .table tbody tr:nth-child(even) {
            background-color: rgba(248, 249, 250, 0.5);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h6 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            font-size: 0.875rem;
            margin: 0;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-md);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 0.875rem;
            min-height: 44px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: var(--gray-600);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: var(--gray-700);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn i {
            margin-right: 0.5rem;
        }

        /* Badge */
        .badge {
            background: var(--primary);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar .nav-link {
                font-size: 0.9rem;
                padding: 0.5rem 1rem;
            }

            .Title {
                font-size: 1.25rem;
            }

            .class-info-list li {
                flex-direction: column;
                align-items: flex-start;
            }

            .class-info-label,
            .class-info-value {
                font-size: 0.9rem;
            }

            .card-body {
                padding: 1rem;
            }

            .btn {
                font-size: 0.85rem;
                padding: 0.6rem 1rem;
            }

            .form-control,
            .form-select {
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
            }
        }

        @media (max-width: 768px) {
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

            .stat-card {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .stat-icon {
                margin-bottom: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .Title {
                font-size: 1.1rem;
            }

            .class-info-label,
            .class-info-value {
                font-size: 0.85rem;
            }

            .btn-sm {
                font-size: 0.75rem;
                padding: 0.4rem 0.8rem;
            }

            .card-body {
                padding: 0.75rem;
            }

            .table-container table tbody tr td::before {
                font-size: 0.75rem;
            }

            .alert {
                font-size: 0.9rem;
                padding: 0.75rem 1rem;
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
                        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i
                                    class="fas fa-home me-2"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="materi.php"><i
                                    class="fas fa-book me-2"></i>Materi</a></li>
                        <li class="nav-item"><a class="nav-link" href="quiz.php"><i
                                    class="fas fa-tasks me-2"></i>Quiz</a></li>
                        <li class="nav-item"><a class="nav-link" href="nilai.php"><i
                                    class="fas fa-chart-bar me-2"></i>Nilai</a></li>
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
                <h2 class="mb-4 Title">Detail Kelas: <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                    (<?php echo htmlspecialchars($kelas['tahun_ajaran']); ?>)</h2>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Informasi Kelas</h5>
                        <ul class="list-unstyled mb-0">
                            <li><strong>Nama Kelas:</strong> <?php echo htmlspecialchars($kelas['nama_kelas']); ?></li>
                            <li><strong>Tahun Ajaran:</strong> <?php echo htmlspecialchars($kelas['tahun_ajaran']); ?>
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- Stats Overview -->
                <div class="stats-grid fade-in-up">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?php echo count($siswa); ?></div>
                        <div class="stat-label">Total Mahasiswa/i</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-number"><?php echo count($quizzes); ?></div>
                        <div class="stat-label">Quiz Tersedia</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-number"><?php echo count($materi); ?></div>
                        <div class="stat-label">Materi Pembelajaran</div>
                    </div>
                </div>

                <!-- Class Information -->
                <div class="card class-info-card fade-in-up">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>
                        Informasi Kelas
                    </div>
                    <div class="card-body">
                        <ul class="class-info-list">
                            <li>
                                <span class="class-info-label">Nama Kelas</span>
                                <span
                                    class="class-info-value"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></span>
                            </li>
                            <li>
                                <span class="class-info-label">Tahun Ajaran</span>
                                <span
                                    class="class-info-value"><?php echo htmlspecialchars($kelas['tahun_ajaran']); ?></span>
                            </li>
                            <li>
                                <span class="class-info-label">Status</span>
                                <span class="class-info-value">
                                    <span class="badge"
                                        style="background: var(--accent-green); color: var(--white); padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem;">
                                        Aktif
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="row">
                    <!-- Students List -->
                    <div class="col-lg-6 mb-4">
                        <div class="card fade-in-up">
                            <div class="card-header">
                                <i class="fas fa-users me-2"></i>
                                Daftar Mahasiswa/i
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($siswa)): ?>
                                    <div class="table-container">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 60px;">No</th>
                                                    <th>Nama Lengkap</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($siswa as $i => $s): ?>
                                                    <tr>
                                                        <td>
                                                            <span
                                                                style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: var(--accent-green); color: var(--white); border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                                                <?php echo $i + 1; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div style="display: flex; align-items: center;">
                                                                <div
                                                                    style="width: 32px; height: 32px; background: var(--light-cream); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; font-weight: 600; color: var(--primary-green);">
                                                                    <?php echo strtoupper(substr($s['nama_lengkap'], 0, 1)); ?>
                                                                </div>
                                                                <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h6>Belum Ada Mahasiswa/i</h6>
                                        <p>Kelas ini belum memiliki Mahasiswa/i yang terdaftar.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quizzes List -->
                    <div class="col-lg-6 mb-4">
                        <div class="card fade-in-up">
                            <div class="card-header">
                                <i class="fas fa-tasks me-2"></i>
                                Daftar Quiz
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($quizzes)): ?>
                                    <div class="table-container">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 60px;">No</th>
                                                    <th>Judul Quiz</th>
                                                    <th>Waktu Mulai</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($quizzes as $i => $q): ?>
                                                    <tr>
                                                        <td>
                                                            <span
                                                                style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: var(--accent-green); color: var(--white); border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                                                <?php echo $i + 1; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div style="font-weight: 500;">
                                                                <?php echo htmlspecialchars($q['judul']); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div style="font-size: 0.875rem; color: var(--gray-600);">
                                                                <i class="fas fa-calendar-alt me-1"></i>
                                                                <?php echo date('d/m/Y H:i', strtotime($q['waktu_mulai'])); ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-tasks"></i>
                                        <h6>Belum Ada Quiz</h6>
                                        <p>Belum ada quiz yang tersedia untuk kelas ini.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Materials List -->
                <div class="card fade-in-up">
                    <div class="card-header">
                        <i class="fas fa-book me-2"></i>
                        Daftar Materi Pembelajaran
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($materi)): ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">No</th>
                                            <th>Judul Materi</th>
                                            <th>Dosen Pengajar</th>
                                            <th>Tanggal Upload</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materi as $i => $m): ?>
                                            <tr>
                                                <td>
                                                    <span
                                                        style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: var(--accent-green); color: var(--white); border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                                        <?php echo $i + 1; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 500; margin-bottom: 0.25rem;">
                                                        <?php echo htmlspecialchars($m['judul']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="display: flex; align-items: center;">
                                                        <div
                                                            style="width: 32px; height: 32px; background: var(--primary-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; font-weight: 600; color: var(--white); font-size: 0.75rem;">
                                                            <?php echo strtoupper(substr($m['guru_nama'], 0, 1)); ?>
                                                        </div>
                                                        <?php echo htmlspecialchars($m['guru_nama']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="font-size: 0.875rem; color: var(--gray-600);">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        <?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-book"></i>
                                <h6>Belum Ada Materi</h6>
                                <p>Belum ada materi pembelajaran yang tersedia untuk kelas ini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Dashboard
                    </a>
                    <a href="materi.php" class="btn btn-primary">
                        <i class="fas fa-book"></i>
                        Lihat Semua Materi
                    </a>
                    <a href="quiz.php" class="btn btn-primary">
                        <i class="fas fa-tasks"></i>
                        Lihat Semua Quiz
                    </a>
                </div>
            </div>
        </div>

        <script>
            // Mobile Sidebar Toggle
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.querySelector('.sidebar-overlay');

                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            }

            // Close sidebar when clicking on overlay
            document.querySelector('.sidebar-overlay').addEventListener('click', function () {
                toggleSidebar();
            });

            // Close sidebar on window resize if mobile
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 768) {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.querySelector('.sidebar-overlay');
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });

            // Add active class to current nav item
            document.addEventListener('DOMContentLoaded', function () {
                const currentPath = window.location.pathname;
                const navLinks = document.querySelectorAll('.nav-link');

                navLinks.forEach(link => {
                    if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
                        link.classList.add('active');
                    }
                });

                // Add fade-in animation to cards
                const cards = document.querySelectorAll('.card');
                cards.forEach((card, index) => {
                    card.style.animationDelay = `${index * 0.1}s`;
                });
            });

            // Loading state simulation
            function showLoadingState() {
                const tables = document.querySelectorAll('.table tbody');
                tables.forEach(table => {
                    table.innerHTML = `
                    <tr>
                        <td colspan="100%">
                            <div style="display: flex; align-items: center; justify-content: center; padding: 2rem;">
                                <div style="width: 20px; height: 20px; border: 2px solid var(--gray-300); border-top: 2px solid var(--accent-green); border-radius: 50%; animation: spin 1s linear infinite; margin-right: 0.5rem;"></div>
                                Memuat data...
                            </div>
                        </td>
                    </tr>
                `;
                });
            }

            // Add spin animation for loading
            const style = document.createElement('style');
            style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
            document.head.appendChild(style);

            // Smooth scroll behavior
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Add hover effects to table rows
            document.addEventListener('DOMContentLoaded', function () {
                const tableRows = document.querySelectorAll('.table tbody tr');
                tableRows.forEach(row => {
                    row.addEventListener('mouseenter', function () {
                        this.style.transform = 'scale(1.01)';
                        this.style.transition = 'all 0.2s ease';
                    });

                    row.addEventListener('mouseleave', function () {
                        this.style.transform = 'scale(1)';
                    });
                });
            });

            // Add click ripple effect to buttons
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function (e) {
                    const ripple = document.createElement('span');
                    const rect = button.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;

                    ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    background-color: rgba(255,255,255,0.5);
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                `;

                    button.style.position = 'relative';
                    button.style.overflow = 'hidden';
                    button.appendChild(ripple);

                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Add ripple animation
            const rippleStyle = document.createElement('style');
            rippleStyle.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
            document.head.appendChild(rippleStyle);

            // Enhanced table interactions
            document.querySelectorAll('.table tbody tr').forEach(row => {
                row.style.cursor = 'pointer';
                row.addEventListener('click', function () {
                    // Add click feedback
                    this.style.backgroundColor = 'var(--light-cream)';
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 200);
                });
            });

            // Search functionality (if needed in future)
            function initSearch() {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        const filter = this.value.toLowerCase();
                        const rows = document.querySelectorAll('.table tbody tr');

                        rows.forEach(row => {
                            const text = row.textContent.toLowerCase();
                            row.style.display = text.includes(filter) ? '' : 'none';
                        });
                    });
                }
            }

            // Initialize all interactive features
            document.addEventListener('DOMContentLoaded', function () {
                initSearch();

                // Add loading animation to page
                document.body.style.opacity = '0';
                document.body.style.transition = 'opacity 0.3s ease';

                setTimeout(() => {
                    document.body.style.opacity = '1';
                }, 100);
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

                // Confirm Delete Function
                window.confirmDelete = function (materialId) {
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
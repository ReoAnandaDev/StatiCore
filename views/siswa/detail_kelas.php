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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Responsive Styles */
        .mobile-menu-toggle {
            display: none;
            /* Sembunyikan di desktop */
        }

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

        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                left: -280px;
                /* Lebar sidebar */
                top: 0;
                height: 100%;
                width: 280px;
                z-index: 1000;
                transition: left 0.3s ease-in-out;
            }

            .sidebar.active {
                left: 0;
            }

            .content-wrapper {
                margin-left: 0 !important;
                width: 100%;
            }

            .mobile-menu-toggle {
                display: block !important;
                position: fixed;
                right: 20px;
                bottom: 20px;
                z-index: 1001;
                background-color: var(--primary);
                color: white;
                border: none;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            }

            .class-info-list li {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
        }
    </style>
</head>

<body>
    <button class="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay"></div>

    <div class="container-fluid">
        <div class="row">
            <div id="sidebar" class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h4><i class="fas fa-chart-line me-2"></i>StatiCore</h4>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i
                                    class="fas fa-home me-2"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="materi.php"><i
                                    class="fas fa-book me-2"></i>Materi</a></li>
                        <li class="nav-item"><a class="nav-link" href="quiz.php"><i
                                    class="fas fa-question-circle me-2"></i>Quiz</a></li>
                        <li class="nav-item"><a class="nav-link" href="tugas.php"><i
                                    class="fas fa-tasks me-2"></i>Tugas</a></li>
                        <li class="nav-item"><a class="nav-link" href="nilai.php"><i
                                    class="fas fa-chart-bar me-2"></i>Nilai</a></li>
                        <li class="nav-item mt-auto">
                            <a class="nav-link" href="../../logout.php" id="logoutBtn">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 p-4 content-wrapper">
                <h2 class="mb-4 Title">Detail Kelas: <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                    (<?php echo htmlspecialchars($kelas['tahun_ajaran']); ?>)</h2>

                <div class="stats-grid">
                    <div class="stat-card card">
                        <div class="card-body d-flex align-items-center">
                            <i class="fas fa-users fa-2x text-primary me-3"></i>
                            <div>
                                <div class="stat-number"><?php echo count($siswa); ?></div>
                                <div class="stat-label">Total Mahasiswa</div>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card card">
                        <div class="card-body d-flex align-items-center">
                            <i class="fas fa-question-circle fa-2x text-info me-3"></i>
                            <div>
                                <div class="stat-number"><?php echo count($quizzes); ?></div>
                                <div class="stat-label">Quiz Tersedia</div>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card card">
                        <div class="card-body d-flex align-items-center">
                            <i class="fas fa-book fa-2x text-success me-3"></i>
                            <div>
                                <div class="stat-number"><?php echo count($materi); ?></div>
                                <div class="stat-label">Materi</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header"><i class="fas fa-users me-2"></i>Daftar Mahasiswa/i</div>
                            <div class="card-body p-0">
                                <?php if (!empty($siswa)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <tbody>
                                                <?php foreach ($siswa as $s): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar me-3">
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
                                    <div class="empty-state"><i class="fas fa-users"></i>
                                        <h6>Belum Ada Mahasiswa/i</h6>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header"><i class="fas fa-question-circle me-2"></i>Daftar Quiz</div>
                            <div class="card-body p-0">
                                <?php if (!empty($quizzes)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <tbody>
                                                <?php foreach ($quizzes as $q): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($q['judul']); ?></td>
                                                        <td class="text-muted text-end">
                                                            <small><?php echo date('d M Y', strtotime($q['waktu_mulai'])); ?></small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state"><i class="fas fa-question-circle"></i>
                                        <h6>Belum Ada Quiz</h6>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="fas fa-book me-2"></i>Daftar Materi Pembelajaran</div>
                    <div class="card-body p-0">
                        <?php if (!empty($materi)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Judul Materi</th>
                                            <th>Dosen Pengajar</th>
                                            <th>Tanggal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materi as $m): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($m['judul']); ?></td>
                                                <td><?php echo htmlspecialchars($m['guru_nama']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($m['created_at'])); ?></td>
                                                <td>
                                                    <a href="detail_materi.php?id=<?php echo $m['id']; ?>"
                                                        class="btn btn-sm btn-info text-white"><i class="fas fa-eye"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-book"></i>
                                <h6>Belum Ada Materi</h6>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        /* MODIFIKASI: JavaScript untuk Hamburger Menu */
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const menuToggle = document.querySelector('.mobile-menu-toggle');

            const toggleSidebar = () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('show');
            };

            if (menuToggle) {
                menuToggle.addEventListener('click', toggleSidebar);
            }
            if (overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth > 767.98) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('show');
                }
            });

            // Script logout
            const logoutLink = document.getElementById('logoutBtn');
            if (logoutLink) {
                logoutLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Apakah Anda ingin keluar?',
                        text: "Anda akan meninggalkan sesi ini.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--primary)',
                        cancelButtonColor: 'var(--gray-500)',
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
<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());
$auth->checkSession();
$auth->requireRole('guru');
$conn = $db->getConnection();

// Get class ID from URL
$kelas_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$kelas_id) {
    header('Location: kelola_quiz.php');
    exit;
}

// Get class info
$stmt = $conn->prepare("SELECT * FROM kelas WHERE id = ?");
$stmt->execute([$kelas_id]);
$kelas = $stmt->fetch();
if (!$kelas) {
    header('Location: kelola_quiz.php');
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

// Get tasks for class
$stmt = $conn->prepare("
    SELECT t.*, jt.nama as jenis_tugas,
           (SELECT COUNT(*) FROM pengumpulan_tugas WHERE tugas_id = t.id) as total_pengumpulan
    FROM tugas t
    JOIN jenis_tugas jt ON t.jenis_tugas_id = jt.id
    WHERE t.kelas_id = ?
    ORDER BY t.batas_pengumpulan DESC
");
$stmt->execute([$kelas_id]);
$tugas = $stmt->fetchAll();
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
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
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

        .subtitle {
            color: var(--secondary);
            font-size: 16px;
            opacity: 0.8;
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

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .card-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: between;
            align-items: center;
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

        .info-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            margin-bottom: 32px;
        }

        .info-card .card-header {
            background: rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .info-card .card-title {
            color: var(--white);
        }

        .info-list {
            list-style: none;
            margin: 0;
        }

        .info-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-list li:last-child {
            border-bottom: none;
        }

        .info-list strong {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
        }

        /* Grid Layout */
        .grid {
            display: grid;
            gap: 24px;
            margin-bottom: 32px;
        }

        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table th {
            background: var(--gray-100);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 2px solid var(--gray-200);
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
        }

        .table tbody tr:hover {
            background: var(--gray-50);
        }

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

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-info {
            background: var(--secondary);
            color: var(--white);
            padding: 8px 16px;
            font-size: 12px;
        }

        .btn-info:hover {
            background: var(--primary);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            padding: 24px;
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 14px;
            font-weight: 500;
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

            /* Grid layout untuk mobile */
            .grid-2,
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                gap: 16px;
            }

            /* Kartu info */
            .info-card .card-header h2 {
                font-size: 16px;
            }

            /* Tabel */
            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 600px;
            }

            /* Judul dan tombol */
            .Title {
                font-size: 1.25rem;
            }

            .btn {
                width: 100%;
                justify-content: center;
                margin-bottom: 8px;
            }

            .btn-sm {
                width: auto;
                padding: 6px 12px;
            }
        }

        .badge-danger {
            background-color: var(--danger);
        }

        /* Task List Styles */
        .task-list {
            margin-top: 24px;
        }

        .task-item {
            background: var(--white);
            border-radius: var(--border-radius-md);
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .task-item:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .task-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }

        .task-meta {
            display: flex;
            gap: 16px;
            color: var(--gray-600);
            font-size: 14px;
        }

        .task-description {
            color: var(--gray-700);
            margin-bottom: 16px;
        }

        .task-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--gray-200);
        }

        .task-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .task-actions {
            display: flex;
            gap: 8px;
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
                            <a class="nav-link active" href="detail_kelas.php">
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
                <div class="mb-4 Title">Detail Kelas</div>
                <div class="mb-2 subtitle">Kelola informasi kelas, Mahasiswa/i, quiz, dan materi</div>

                <!-- Class Info Card -->
                <div class="card info-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chalkboard-teacher"></i>
                            Informasi Kelas
                        </h2>
                    </div>
                    <div class="card-body">
                        <ul class="info-list">
                            <li><strong>Nama Kelas:</strong> <?php echo htmlspecialchars($kelas['nama_kelas']); ?></li>
                            <li><strong>Tahun Ajaran:</strong> <?php echo htmlspecialchars($kelas['tahun_ajaran']); ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($siswa); ?></div>
                        <div class="stat-label">Total Mahasiswa/i</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($quizzes); ?></div>
                        <div class="stat-label">Total Quiz</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($materi); ?></div>
                        <div class="stat-label">Total Materi</div>
                    </div>
                </div>

                <!-- Task List Section -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Daftar Tugas</h5>
                        <a href="kelola_tugas.php" class="btn btn-light btn-sm">
                            <i class="fas fa-plus me-2"></i>Tambah Tugas
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tugas)): ?>
                            <div class="empty-state">
                                <i class="fas fa-tasks"></i>
                                <p>Belum ada tugas untuk kelas ini</p>
                            </div>
                        <?php else: ?>
                            <div class="task-list">
                                <?php foreach ($tugas as $t): ?>
                                    <div class="task-item">
                                        <div class="task-header">
                                            <h6 class="task-title"><?php echo htmlspecialchars($t['judul']); ?></h6>
                                            <div class="task-meta">
                                                <span><i
                                                        class="fas fa-tag me-1"></i><?php echo htmlspecialchars($t['jenis_tugas']); ?></span>
                                                <span><i class="fas fa-clock me-1"></i>Batas:
                                                    <?php echo date('d M Y H:i', strtotime($t['batas_pengumpulan'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="task-description">
                                            <?php echo nl2br(htmlspecialchars($t['deskripsi'])); ?>
                                        </div>
                                        <div class="task-footer">
                                            <div class="task-status">
                                                <span class="badge bg-info">
                                                    <i class="fas fa-users me-1"></i>
                                                    <?php echo $t['total_pengumpulan']; ?> Pengumpulan
                                                </span>
                                            </div>
                                            <div class="task-actions">
                                                <a href="detail_tugas.php?id=<?php echo $t['id']; ?>"
                                                    class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i>Detail
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Students and Quizzes Grid -->
                <div class="grid grid-2">
                    <!-- Students Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users me-2"></i>
                                Daftar Mahasiswa/i
                            </h3>
                        </div>
                        <div class="table-container">
                            <?php if (!empty($siswa)): ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Lengkap</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($siswa as $i => $s): ?>
                                            <tr>
                                                <td><?php echo $i + 1; ?></td>
                                                <td><?php echo htmlspecialchars($s['nama_lengkap']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-slash"></i>
                                    <p>Belum ada Mahasiswa/i terdaftar di kelas ini</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quizzes Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-question-circle me-2"></i>
                                Daftar Quiz
                            </h3>
                        </div>
                        <div class="table-container">
                            <?php if (!empty($quizzes)): ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Judul</th>
                                            <th>Waktu Mulai</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quizzes as $i => $q): ?>
                                            <tr>
                                                <td><?php echo $i + 1; ?></td>
                                                <td><?php echo htmlspecialchars($q['judul']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($q['waktu_mulai'])); ?></td>
                                                <td>
                                                    <a href="detail_quiz.php?id=<?php echo $q['id']; ?>"
                                                        class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                        Detail
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-question"></i>
                                    <p>Belum ada quiz tersedia</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Materials Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-book me-2"></i>
                            Daftar Materi Pembelajaran
                        </h3>
                    </div>
                    <div class="table-container">
                        <?php if (!empty($materi)): ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Judul Materi</th>
                                        <th>Dosen Pengajar</th>
                                        <th>Tanggal Upload</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materi as $i => $m): ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td><?php echo htmlspecialchars($m['judul']); ?></td>
                                            <td><?php echo htmlspecialchars($m['guru_nama']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-book-open"></i>
                                <p>Belum ada materi pembelajaran tersedia</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Back Button -->
                <div style="margin-top: 32px;">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Dashboard
                    </a>
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
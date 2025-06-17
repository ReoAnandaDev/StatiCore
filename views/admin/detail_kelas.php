<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$conn = $db->getConnection();

$auth = new Auth($conn);
$auth->checkSession();
$auth->requireRole('admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: daftar_kelas.php");
    exit();
}

$id_kelas = intval($_GET['id']);

// Ambil info kelas
$stmt_kelas = $conn->prepare("SELECT id, nama_kelas, tahun_ajaran FROM kelas WHERE id = ?");
$stmt_kelas->execute([$id_kelas]);
$kelas = $stmt_kelas->fetch(PDO::FETCH_ASSOC);

if (!$kelas) {
    die("Kelas tidak ditemukan.");
}

// Ambil guru pengampu
$stmt_guru = $conn->prepare("
    SELECT u.nama_lengkap 
    FROM guru_kelas gk
    JOIN users u ON gk.guru_id = u.id
    WHERE gk.kelas_id = ?
");
$stmt_guru->execute([$id_kelas]);
$guru = $stmt_guru->fetch(PDO::FETCH_ASSOC);
$kelas['nama_guru'] = $guru ? $guru['nama_lengkap'] : 'Belum ditentukan';

// Ambil siswa dalam kelas
$stmt_siswa = $conn->prepare("
    SELECT u.nama_lengkap 
    FROM siswa_kelas sk
    JOIN users u ON sk.siswa_id = u.id
    WHERE sk.kelas_id = ?
");
$stmt_siswa->execute([$id_kelas]);
$list_siswa = $stmt_siswa->fetchAll(PDO::FETCH_ASSOC);

// Get class details with more comprehensive statistics
$stmt = $conn->prepare("
    SELECT k.*, 
           COUNT(DISTINCT sk.siswa_id) as total_siswa,
           COUNT(DISTINCT m.id) as total_materi,
           COUNT(DISTINCT q.id) as total_quiz,
           COUNT(DISTINCT t.id) as total_tugas,
           COUNT(DISTINCT pt.id) as total_pengumpulan,
           AVG(pt.nilai) as rata_rata_nilai
    FROM kelas k
    LEFT JOIN siswa_kelas sk ON k.id = sk.kelas_id
    LEFT JOIN materi m ON k.id = m.kelas_id
    LEFT JOIN quiz q ON k.id = q.kelas_id
    LEFT JOIN tugas t ON k.id = t.kelas_id
    LEFT JOIN pengumpulan_tugas pt ON t.id = pt.tugas_id
    WHERE k.id = ?
    GROUP BY k.id
");
$stmt->execute([$id_kelas]);
$kelas = $stmt->fetch();

// Get class activities
$activities = $conn->query("
    (SELECT 'materi' as type, m.created_at, m.judul as title, u.nama_lengkap as user_name
    FROM materi m 
    JOIN users u ON m.guru_id = u.id 
    WHERE m.kelas_id = $id_kelas)
    UNION ALL
    (SELECT 'quiz' as type, q.created_at, q.judul as title, u.nama_lengkap as user_name
    FROM quiz q 
    JOIN users u ON q.guru_id = u.id 
    WHERE q.kelas_id = $id_kelas)
    UNION ALL
    (SELECT 'tugas' as type, t.created_at, t.judul as title, u.nama_lengkap as user_name
    FROM tugas t 
    JOIN users u ON t.guru_id = u.id 
    WHERE t.kelas_id = $id_kelas)
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// Get student performance
$student_performance = $conn->query("
    SELECT u.id, u.nama_lengkap,
           COUNT(DISTINCT pt.id) as total_pengumpulan,
           AVG(pt.nilai) as rata_rata_nilai,
           COUNT(DISTINCT CASE WHEN pt.status = 'dinilai' THEN pt.id END) as tugas_dinilai
    FROM siswa_kelas sk
    JOIN users u ON sk.siswa_id = u.id
    LEFT JOIN pengumpulan_tugas pt ON u.id = pt.siswa_id
    WHERE sk.kelas_id = $id_kelas
    GROUP BY u.id
    ORDER BY rata_rata_nilai DESC
")->fetchAll();
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

        /* Main Content */
        .main-content {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        /* Card Styling */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease;
            background-color: white;
        }

        .card:hover {
            transform: translateY(-4px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            font-weight: bold;
            padding: 15px;
        }

        .card-body {
            padding: 20px;
        }

        .list-group-item {
            cursor: pointer;
            transition: background-color 0.2s ease;
            border-left: 4px solid transparent;
        }

        .list-group-item:hover {
            background-color: var(--light);
            border-left-color: var(--primary);
        }

        .list-group-item.active {
            background-color: var(--light);
            color: var(--primary);
            border-left-color: var(--primary);
        }

        /* Button */
        .btn-secondary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
        }

        /* Alert */
        .alert-info {
            background-color: #B8CFCE;
            color: #3B3B1A;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .col-md-6 {
                width: 100%;
            }
        }

        /* Add these styles to your existing CSS */
        .stat-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .recent-activities {
            max-height: 500px;
            overflow-y: auto;
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
                        <li class="nav-item">
                            <a class="nav-link" href="manage_classes.php">
                                <i class="fas fa-users me-2"></i>Kelola Kelas & Pengguna
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="detail_kelas.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="Title mb-0">Detail Kelas: <?php echo htmlspecialchars($kelas['nama_kelas']); ?></h2>
                    <a href="manage_classes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>

                <!-- Class Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-graduate stat-icon"></i>
                                <h5 class="card-title">Total Siswa</h5>
                                <h2 class="card-number"><?php echo $kelas['total_siswa']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-book stat-icon"></i>
                                <h5 class="card-title">Total Materi</h5>
                                <h2 class="card-number"><?php echo $kelas['total_materi']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-tasks stat-icon"></i>
                                <h5 class="card-title">Total Tugas</h5>
                                <h2 class="card-number"><?php echo $kelas['total_tugas']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line stat-icon"></i>
                                <h5 class="card-title">Rata-rata Nilai</h5>
                                <h2 class="card-number"><?php echo round($kelas['rata_rata_nilai'], 1); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student Performance -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Performa Siswa</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Siswa</th>
                                        <th>Total Pengumpulan</th>
                                        <th>Tugas Dinilai</th>
                                        <th>Rata-rata Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($student_performance as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['nama_lengkap']); ?></td>
                                            <td><?php echo $student['total_pengumpulan']; ?></td>
                                            <td><?php echo $student['tugas_dinilai']; ?></td>
                                            <td>
                                                <?php if ($student['rata_rata_nilai']): ?>
                                                    <?php echo round($student['rata_rata_nilai'], 1); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
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
                            <?php foreach ($activities as $activity): ?>
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
                                            Oleh: <?php echo htmlspecialchars($activity['user_name']); ?>
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
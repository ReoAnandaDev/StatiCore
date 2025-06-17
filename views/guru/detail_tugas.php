<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is teacher
$auth->checkSession();
$auth->requireRole('guru');

$conn = $db->getConnection();
$message = '';

// Get task ID from URL
$tugas_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$tugas_id) {
    header('Location: kelola_tugas.php');
    exit;
}

// Get task details
$stmt = $conn->prepare("
    SELECT t.*, k.nama_kelas, k.tahun_ajaran, jt.nama as jenis_tugas,
           u.nama_lengkap as nama_guru
    FROM tugas t
    JOIN kelas k ON t.kelas_id = k.id
    JOIN jenis_tugas jt ON t.jenis_tugas_id = jt.id
    JOIN users u ON t.guru_id = u.id
    WHERE t.id = ? AND t.guru_id = ?
");
$stmt->execute([$tugas_id, $_SESSION['user_id']]);
$tugas = $stmt->fetch();

if (!$tugas) {
    header('Location: kelola_tugas.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_nilai':
                $pengumpulan_id = filter_input(INPUT_POST, 'pengumpulan_id', FILTER_SANITIZE_NUMBER_INT);
                $nilai = filter_input(INPUT_POST, 'nilai', FILTER_SANITIZE_NUMBER_INT);
                $feedback = filter_input(INPUT_POST, 'feedback', FILTER_SANITIZE_STRING);

                try {
                    $stmt = $conn->prepare("
                        UPDATE pengumpulan_tugas 
                        SET nilai = ?, feedback = ?, status = 'dinilai'
                        WHERE id = ? AND tugas_id = ?
                    ");
                    $stmt->execute([$nilai, $feedback, $pengumpulan_id, $tugas_id]);
                    $message = "Nilai berhasil diperbarui";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get submissions
$stmt = $conn->prepare("
    SELECT pt.*, u.nama_lengkap as nama_siswa
    FROM pengumpulan_tugas pt
    JOIN users u ON pt.siswa_id = u.id
    WHERE pt.tugas_id = ?
    ORDER BY pt.waktu_pengumpulan DESC
");
$stmt->execute([$tugas_id]);
$pengumpulan = $stmt->fetchAll();

// Get total students in class
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_siswa
    FROM siswa_kelas
    WHERE kelas_id = ?
");
$stmt->execute([$tugas['kelas_id']]);
$total_siswa = $stmt->fetch()['total_siswa'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Tugas - StatiCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--gray-100);
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

        /* Cards */
        .card {
            background: var(--white);
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            padding: 20px 24px;
            border-bottom: none;
        }

        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius-sm);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 2px solid var(--gray-200);
        }

        .table td {
            vertical-align: middle;
            color: var(--gray-600);
        }

        .badge {
            padding: 8px 12px;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
        }

        .badge-success {
            background-color: var(--success);
        }

        .badge-warning {
            background-color: var(--warning);
        }

        .badge-danger {
            background-color: var(--danger);
        }

        .badge-info {
            background-color: var(--info);
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
                            <a class="nav-link active" href="kelola_tugas.php">
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
            <div class="col-md-9 col-lg-10 px-4 py-3">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="Title mb-0">Detail Tugas</h2>
                    <a href="kelola_tugas.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Task Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Informasi Tugas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Judul:</strong> <?php echo htmlspecialchars($tugas['judul']); ?></p>
                                <p><strong>Jenis Tugas:</strong> <?php echo htmlspecialchars($tugas['jenis_tugas']); ?>
                                </p>
                                <p><strong>Kelas:</strong>
                                    <?php echo htmlspecialchars($tugas['nama_kelas'] . ' (' . $tugas['tahun_ajaran'] . ')'); ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Batas Pengumpulan:</strong>
                                    <?php echo date('d M Y H:i', strtotime($tugas['batas_pengumpulan'])); ?></p>
                                <p><strong>Total Siswa:</strong> <?php echo $total_siswa; ?></p>
                                <p><strong>Total Pengumpulan:</strong> <?php echo count($pengumpulan); ?></p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p><strong>Deskripsi:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($tugas['deskripsi'])); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Submissions List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Daftar Pengumpulan</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nama Siswa</th>
                                        <th>Waktu Pengumpulan</th>
                                        <th>File</th>
                                        <th>Status</th>
                                        <th>Nilai</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pengumpulan as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['nama_siswa']); ?></td>
                                            <td><?php echo date('d M Y H:i', strtotime($p['waktu_pengumpulan'])); ?></td>
                                            <td>
                                                <a href="<?php echo htmlspecialchars($p['file_path']); ?>"
                                                    class="btn btn-sm btn-info" target="_blank">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                $status_text = '';
                                                switch ($p['status']) {
                                                    case 'dikumpulkan':
                                                        $status_class = 'info';
                                                        $status_text = 'Dikumpulkan';
                                                        break;
                                                    case 'dinilai':
                                                        $status_class = 'success';
                                                        $status_text = 'Dinilai';
                                                        break;
                                                    case 'ditolak':
                                                        $status_class = 'danger';
                                                        $status_text = 'Ditolak';
                                                        break;
                                                }
                                                ?>
                                                <span
                                                    class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                            </td>
                                            <td><?php echo $p['nilai'] ? $p['nilai'] : '-'; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning"
                                                    onclick="openNilaiModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['nama_siswa']); ?>', <?php echo $p['nilai'] ? $p['nilai'] : 'null'; ?>, '<?php echo htmlspecialchars($p['feedback']); ?>')">
                                                    <i class="fas fa-star"></i> Nilai
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Single Modal for Nilai -->
                <div class="modal fade" id="nilaiModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Nilai Tugas - <span id="studentName"></span></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <form action="" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="update_nilai">
                                    <input type="hidden" name="pengumpulan_id" id="pengumpulanId">

                                    <div class="mb-3">
                                        <label for="nilai" class="form-label">Nilai</label>
                                        <input type="number" class="form-control" id="nilai" name="nilai" min="0"
                                            max="100" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="feedback" class="form-label">Feedback</label>
                                        <textarea class="form-control" id="feedback" name="feedback"
                                            rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openNilaiModal(id, studentName, nilai, feedback) {
            document.getElementById('pengumpulanId').value = id;
            document.getElementById('studentName').textContent = studentName;
            document.getElementById('nilai').value = nilai || '';
            document.getElementById('feedback').value = feedback || '';

            const modal = new bootstrap.Modal(document.getElementById('nilaiModal'));
            modal.show();
        }
    </script>
</body>

</html>
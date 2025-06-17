<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is student
$auth->checkSession();
$auth->requireRole('siswa');

$conn = $db->getConnection();
$message = '';

// Get task ID from URL
$tugas_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$tugas_id) {
    header('Location: tugas.php');
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
    WHERE t.id = ?
");
$stmt->execute([$tugas_id]);
$tugas = $stmt->fetch();

if (!$tugas) {
    header('Location: tugas.php');
    exit;
}

// Check if student is in the class
$stmt = $conn->prepare("
    SELECT 1 FROM siswa_kelas
    WHERE siswa_id = ? AND kelas_id = ?
");
$stmt->execute([$_SESSION['user_id'], $tugas['kelas_id']]);
if (!$stmt->fetch()) {
    header('Location: tugas.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'submit_tugas':
                $catatan = filter_input(INPUT_POST, 'catatan', FILTER_SANITIZE_STRING);

                // Handle file upload
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['file'];
                    $allowed_types = [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'application/zip',
                        'application/x-zip-compressed'
                    ];

                    if (!in_array($file['type'], $allowed_types)) {
                        $message = "Error: Tipe file tidak didukung. Gunakan file PDF, Word, Excel, PowerPoint, atau ZIP.";
                    } else {
                        $upload_dir = '../../uploads/tugas/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $file_name = time() . '_' . basename($file['name']);
                        $file_path = $upload_dir . $file_name;

                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            try {
                                $stmt = $conn->prepare("
                                    INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, file_path, catatan, waktu_pengumpulan)
                                    VALUES (?, ?, ?, ?, NOW())
                                ");
                                $stmt->execute([$tugas_id, $_SESSION['user_id'], 'uploads/tugas/' . $file_name, $catatan]);
                                $message = "Tugas berhasil dikumpulkan";
                            } catch (PDOException $e) {
                                $message = "Error: " . $e->getMessage();
                                unlink($file_path); // Delete uploaded file if database insert fails
                            }
                        } else {
                            $message = "Error: Gagal mengupload file";
                        }
                    }
                } else {
                    $message = "Error: File harus diupload";
                }
                break;
        }
    }
}

// Get student's submission
$stmt = $conn->prepare("
    SELECT * FROM pengumpulan_tugas
    WHERE tugas_id = ? AND siswa_id = ?
");
$stmt->execute([$tugas_id, $_SESSION['user_id']]);
$pengumpulan = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kumpul Tugas - StatiCore</title>
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
            padding: 12px;
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            min-height: 100vh;
            transition: all 0.3s ease;
            padding: 12px;
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
            <div class="col-md-3 col-lg-2 px-0 position-fixed sidebar">
                <h4 class="mt-4 mb-4">StatiCore</h4>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <a class="nav-link" href="kelas.php">
                        <i class="fas fa-chalkboard"></i>
                        Kelas
                    </a>
                    <a class="nav-link" href="materi.php">
                        <i class="fas fa-book"></i>
                        Materi
                    </a>
                    <a class="nav-link" href="quiz.php">
                        <i class="fas fa-question-circle"></i>
                        Quiz
                    </a>
                    <a class="nav-link active" href="tugas.php">
                        <i class="fas fa-tasks"></i>
                        Tugas
                    </a>
                    <a class="nav-link" href="nilai.php">
                        <i class="fas fa-star"></i>
                        Nilai
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-auto px-4 py-3">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="Title mb-0">Kumpul Tugas</h2>
                    <a href="tugas.php" class="btn btn-secondary">
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
                                <p><strong>Dosen:</strong> <?php echo htmlspecialchars($tugas['nama_guru']); ?></p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p><strong>Deskripsi:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($tugas['deskripsi'])); ?></p>
                        </div>
                    </div>
                </div>

                <?php if ($pengumpulan): ?>
                    <!-- Submission Details -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Status Pengumpulan</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Waktu Pengumpulan:</strong>
                                        <?php echo date('d M Y H:i', strtotime($pengumpulan['waktu_pengumpulan'])); ?></p>
                                    <p><strong>Status:</strong>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($pengumpulan['status']) {
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
                                    </p>
                                    <?php if ($pengumpulan['nilai']): ?>
                                        <p><strong>Nilai:</strong> <?php echo $pengumpulan['nilai']; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>File:</strong></p>
                                    <a href="<?php echo htmlspecialchars($pengumpulan['file_path']); ?>"
                                        class="btn btn-info" target="_blank">
                                        <i class="fas fa-download"></i> Download File
                                    </a>
                                    <?php if ($pengumpulan['catatan']): ?>
                                        <p class="mt-3"><strong>Catatan:</strong></p>
                                        <p><?php echo nl2br(htmlspecialchars($pengumpulan['catatan'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($pengumpulan['feedback']): ?>
                                <div class="mt-3">
                                    <p><strong>Feedback Dosen:</strong></p>
                                    <p><?php echo nl2br(htmlspecialchars($pengumpulan['feedback'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Submission Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Kumpul Tugas</h5>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="submit_tugas">

                                <div class="mb-3">
                                    <label for="file" class="form-label">File Tugas</label>
                                    <input type="file" class="form-control" id="file" name="file" required>
                                    <div class="form-text">Format yang didukung: PDF, Word, Excel, PowerPoint, ZIP (Max:
                                        10MB)</div>
                                </div>

                                <div class="mb-3">
                                    <label for="catatan" class="form-label">Catatan (Opsional)</label>
                                    <textarea class="form-control" id="catatan" name="catatan" rows="3"></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Kumpul Tugas
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
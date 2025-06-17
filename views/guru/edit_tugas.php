<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$conn = $db->getConnection();

$auth = new Auth($conn);
$auth->checkSession();
$auth->requireRole('guru');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: kelola_tugas.php");
    exit();
}

$tugas_id = intval($_GET['id']);

// Get task details
$stmt = $conn->prepare("
    SELECT t.*, k.nama_kelas, jt.nama as jenis_tugas
    FROM tugas t
    JOIN kelas k ON t.kelas_id = k.id
    JOIN jenis_tugas jt ON t.jenis_tugas_id = jt.id
    WHERE t.id = ? AND t.guru_id = ?
");
$stmt->execute([$tugas_id, $_SESSION['user_id']]);
$tugas = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tugas) {
    header("Location: kelola_tugas.php");
    exit();
}

// Get task types
$jenis_tugas = $conn->query("SELECT * FROM jenis_tugas ORDER BY nama")->fetchAll();

// Get classes
$stmt_kelas = $conn->prepare("
    SELECT k.* 
    FROM kelas k
    JOIN guru_kelas gk ON k.id = gk.kelas_id
    WHERE gk.guru_id = ?
    ORDER BY k.nama_kelas
");
$stmt_kelas->execute([$_SESSION['user_id']]);
$kelas = $stmt_kelas->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = trim($_POST['judul']);
    $deskripsi = trim($_POST['deskripsi']);
    $kelas_id = intval($_POST['kelas_id']);
    $jenis_tugas_id = intval($_POST['jenis_tugas_id']);
    $batas_pengumpulan = $_POST['batas_pengumpulan'];

    // Validate input
    $errors = [];
    if (empty($judul)) {
        $errors[] = "Judul tugas harus diisi";
    }
    if (empty($deskripsi)) {
        $errors[] = "Deskripsi tugas harus diisi";
    }
    if (empty($kelas_id)) {
        $errors[] = "Kelas harus dipilih";
    }
    if (empty($jenis_tugas_id)) {
        $errors[] = "Jenis tugas harus dipilih";
    }
    if (empty($batas_pengumpulan)) {
        $errors[] = "Batas pengumpulan harus diisi";
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE tugas 
                SET judul = ?, deskripsi = ?, kelas_id = ?, jenis_tugas_id = ?,
                    batas_pengumpulan = ?
                WHERE id = ? AND guru_id = ?
            ");
            $stmt->execute([
                $judul,
                $deskripsi,
                $kelas_id,
                $jenis_tugas_id,
                $batas_pengumpulan,
                $tugas_id,
                $_SESSION['user_id']
            ]);

            header("Location: detail_tugas.php?id=" . $tugas_id);
            exit();
        } catch (PDOException $e) {
            $errors[] = "Gagal mengupdate tugas: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tugas - StatiCore</title>
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

        /* Form Styling */
        .form-control {
            border-radius: var(--border-radius-sm);
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(44, 82, 130, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: var(--primary);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
        }

        .btn-secondary {
            background: #e2e8f0;
            border: none;
            color: var(--primary);
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            color: var(--primary);
        }

        .card {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0 !important;
            padding: 1rem 1.5rem;
        }

        .alert {
            border-radius: var(--border-radius-sm);
            border: none;
        }

        .alert-danger {
            background-color: #fed7d7;
            color: #c53030;
        }

        .file-preview {
            background: #f8f9fa;
            border: 1px dashed #cbd5e0;
            border-radius: var(--border-radius-sm);
            padding: 1rem;
            margin-top: 0.5rem;
        }

        .file-preview a {
            color: var(--primary);
            text-decoration: none;
        }

        .file-preview a:hover {
            text-decoration: underline;
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
                            <a class="nav-link" href="kelola_tugas.php">
                                <i class="fas fa-tasks me-2"></i>Kelola Tugas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="upload_materi.php">
                                <i class="fas fa-book me-2"></i>Upload Materi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="kelola_quiz.php">
                                <i class="fas fa-question-circle me-2"></i>Kelola Quiz
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
                    <h2 class="Title mb-0">Edit Tugas</h2>
                    <a href="detail_tugas.php?id=<?php echo $tugas_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Form Edit Tugas</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="judul" class="form-label">Judul Tugas</label>
                                    <input type="text" class="form-control" id="judul" name="judul"
                                        value="<?php echo htmlspecialchars($tugas['judul']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="kelas_id" class="form-label">Kelas</label>
                                    <select class="form-select" id="kelas_id" name="kelas_id" required>
                                        <?php foreach ($kelas as $k): ?>
                                            <option value="<?php echo $k['id']; ?>" <?php echo $k['id'] == $tugas['kelas_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($k['nama_kelas']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="jenis_tugas_id" class="form-label">Jenis Tugas</label>
                                    <select class="form-select" id="jenis_tugas_id" name="jenis_tugas_id" required>
                                        <?php foreach ($jenis_tugas as $jt): ?>
                                            <option value="<?php echo $jt['id']; ?>" <?php echo $jt['id'] == $tugas['jenis_tugas_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($jt['nama']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="batas_pengumpulan" class="form-label">Batas Pengumpulan</label>
                                    <input type="datetime-local" class="form-control" id="batas_pengumpulan"
                                        name="batas_pengumpulan"
                                        value="<?php echo date('Y-m-d\TH:i', strtotime($tugas['batas_pengumpulan'])); ?>"
                                        required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="deskripsi" class="form-label">Deskripsi Tugas</label>
                                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"
                                    required><?php echo htmlspecialchars($tugas['deskripsi']); ?></textarea>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="detail_tugas.php?id=<?php echo $tugas_id; ?>"
                                    class="btn btn-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        </form>
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
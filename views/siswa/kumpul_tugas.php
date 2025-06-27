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
                                // Simpan path relatif dari direktori utama aplikasi
                                $db_file_path = 'uploads/tugas/' . $file_name;
                                $stmt->execute([$tugas_id, $_SESSION['user_id'], $db_file_path, $catatan]);
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
            --gray-50: #F9FAFB;
            --gray-800: #1F2937;
            --success: #10B981;
            --info: #3B82F6;
            --border-radius-sm: 8px;
            --border-radius-lg: 16px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
        }

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
        }

        .card {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border-radius: var(--border-radius-lg);
            border: none;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .main-content {
            padding: 1.5rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }

        /* BARU: CSS untuk Hamburger & Overlay */
        .mobile-menu-toggle {
            display: none;
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
                height: 100%;
                width: 280px;
                z-index: 1000;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                width: 100%;
                margin-left: 0 !important;
                padding: 1rem;
            }

            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1100;
                background-color: var(--primary);
                color: white;
                border: none;
                border-radius: 50%;
                width: 44px;
                height: 44px;
                font-size: 1rem;
            }

            .page-title {
                margin-top: 3.5rem;
                font-size: 1.5rem;
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
                    <h4 class="px-2 my-3"><i class="fas fa-chart-line me-2"></i>StatiCore</h4>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="materi.php"><i class="fas fa-book me-2"></i>Materi</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="quiz.php"><i class="fas fa-question-circle me-2"></i>Quiz</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="tugas.php"><i class="fas fa-tasks me-2"></i>Tugas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="nilai.php"><i class="fas fa-star me-2"></i>Nilai</a>
                        </li>
                        <li class="nav-item mt-auto">
                            <a class="nav-link" href="../../logout.php" id="logoutBtn"><i
                                    class="fas fa-sign-out-alt me-2"></i>Logout</a>
                        </li>
                    </ul>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <h1 class="page-title">Pengumpulan Tugas</h1>

                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-file-alt me-2"></i>Informasi Tugas</h5>
                    </div>
                    <div class="card-body">
                        <h4 class="mb-3"><?php echo htmlspecialchars($tugas['judul']); ?></h4>
                        <div class="row">
                            <div class="col-md-6 mb-2"><strong>Kelas:</strong>
                                <?php echo htmlspecialchars($tugas['nama_kelas'] . ' (' . $tugas['tahun_ajaran'] . ')'); ?>
                            </div>
                            <div class="col-md-6 mb-2"><strong>Dosen:</strong>
                                <?php echo htmlspecialchars($tugas['nama_guru']); ?></div>
                            <div class="col-md-6 mb-2"><strong>Jenis:</strong> <span
                                    class="badge bg-secondary"><?php echo htmlspecialchars($tugas['jenis_tugas']); ?></span>
                            </div>
                            <div class="col-md-6 mb-2"><strong>Batas Waktu:</strong> <span
                                    class="badge bg-danger"><?php echo date('d M Y, H:i', strtotime($tugas['batas_pengumpulan'])); ?></span>
                            </div>
                        </div>
                        <hr>
                        <p><strong>Deskripsi:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($tugas['deskripsi'])); ?></p>
                        <?php if ($tugas['file_path']): ?>
                            <a href="../../<?php echo htmlspecialchars($tugas['file_path']); ?>" target="_blank"
                                class="btn btn-info mt-2">
                                <i class="fas fa-download me-2"></i> Download Lampiran Tugas
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($pengumpulan): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-check-circle me-2"></i>Status Pengumpulan Anda</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Waktu Pengumpulan:</strong>
                                <?php echo date('d M Y, H:i', strtotime($pengumpulan['waktu_pengumpulan'])); ?></p>
                            <p><strong>Status:</strong>
                                <?php
                                $status_class = 'secondary';
                                if ($pengumpulan['status'] == 'dinilai')
                                    $status_class = 'success';
                                if (strtotime($pengumpulan['waktu_pengumpulan']) > strtotime($tugas['batas_pengumpulan']))
                                    $status_text = 'Terlambat';
                                else
                                    $status_text = 'Tepat Waktu';
                                ?>
                                <span
                                    class="badge bg-<?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($pengumpulan['status'])); ?>
                                    (<?php echo $status_text; ?>)</span>
                            </p>
                            <?php if ($pengumpulan['nilai']): ?>
                                <p><strong>Nilai:</strong> <span
                                        class="badge bg-primary fs-5"><?php echo htmlspecialchars($pengumpulan['nilai']); ?></span>
                                </p>
                            <?php endif; ?>
                            <p><strong>File Anda:</strong>
                                <a href="../../<?php echo htmlspecialchars($pengumpulan['file_path']); ?>" target="_blank"
                                    class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i> Lihat File
                                </a>
                            </p>
                            <?php if ($pengumpulan['catatan']): ?>
                                <p><strong>Catatan Anda:</strong><br><?= nl2br(htmlspecialchars($pengumpulan['catatan'])); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($pengumpulan['feedback']): ?>
                                <hr>
                                <p><strong>Feedback Dosen:</strong><br><?= nl2br(htmlspecialchars($pengumpulan['feedback'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="fas fa-upload me-2"></i>Form Pengumpulan</h5>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="submit_tugas">
                                <div class="mb-3">
                                    <label for="file" class="form-label"><strong>Upload File Jawaban</strong></label>
                                    <input type="file" class="form-control" id="file" name="file" required>
                                    <div class="form-text">Format: PDF, Word, Excel, PowerPoint, ZIP.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="catatan" class="form-label"><strong>Catatan (Opsional)</strong></label>
                                    <textarea class="form-control" id="catatan" name="catatan" rows="3"
                                        placeholder="Tambahkan catatan untuk dosen..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Kumpulkan Tugas</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="tugas.php" class="btn btn-secondary mt-4"><i class="fas fa-arrow-left me-2"></i> Kembali</a>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // BARU: Logika untuk Hamburger Menu
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const menuToggle = document.querySelector('.mobile-menu-toggle');

            if (sidebar && overlay && menuToggle) {
                const toggleSidebar = () => {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('show');
                };
                menuToggle.addEventListener('click', toggleSidebar);
                overlay.addEventListener('click', toggleSidebar);
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth > 767.98 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('show');
                }
            });

            // Logika untuk konfirmasi logout
            const logoutLink = document.getElementById('logoutBtn');
            if (logoutLink) {
                logoutLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Apakah Anda ingin keluar?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--primary)',
                        cancelButtonColor: '#6c757d',
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
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

// Check for messages from URL parameters (for delete operations)
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $message_type = isset($_GET['type']) ? $_GET['type'] : 'info';
}

// Create uploads directory if it doesn't exist
$upload_dir = '../../uploads/tugas/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Get teacher's classes
$stmt = $conn->prepare("
    SELECT k.* FROM kelas k
    JOIN guru_kelas gk ON k.id = gk.kelas_id
    WHERE gk.guru_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

// Get jenis tugas
$stmt = $conn->query("SELECT * FROM jenis_tugas ORDER BY nama");
$jenis_tugas = $stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_tugas':
                $judul = filter_input(INPUT_POST, 'judul', FILTER_SANITIZE_STRING);
                $deskripsi = filter_input(INPUT_POST, 'deskripsi', FILTER_SANITIZE_STRING);
                $jenis_tugas_id = filter_input(INPUT_POST, 'jenis_tugas_id', FILTER_SANITIZE_NUMBER_INT);
                $kelas_id = filter_input(INPUT_POST, 'kelas_id', FILTER_SANITIZE_NUMBER_INT);
                $batas_pengumpulan = filter_input(INPUT_POST, 'batas_pengumpulan', FILTER_SANITIZE_STRING);
                $file_path = null;

                // Handle file upload
                if (isset($_FILES['file_tugas']) && $_FILES['file_tugas']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['file_tugas'];
                    $file_name = time() . '_' . basename($file['name']);
                    $file_path_destination = $upload_dir . $file_name;

                    // Check file type (allow common document types)
                    $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];
                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (!in_array($file_extension, $allowed_types)) {
                        $message = "Error: Tipe file tidak diizinkan. File yang diizinkan: " . implode(', ', $allowed_types);
                    } else if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
                        $message = "Error: Ukuran file terlalu besar. Maksimal 10MB.";
                    } else if (move_uploaded_file($file['tmp_name'], $file_path_destination)) {
                        $file_path = 'uploads/tugas/' . $file_name;
                    } else {
                        $message = "Error: Gagal mengupload file";
                    }
                }

                if (empty($message)) {
                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO tugas (judul, deskripsi, file_path, jenis_tugas_id, kelas_id, guru_id, batas_pengumpulan)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$judul, $deskripsi, $file_path, $jenis_tugas_id, $kelas_id, $_SESSION['user_id'], $batas_pengumpulan]);
                        $message = "Tugas berhasil dibuat";
                    } catch (PDOException $e) {
                        $message = "Error: " . $e->getMessage();
                        // Delete uploaded file if database insert fails
                        if ($file_path && file_exists('../../' . $file_path)) {
                            unlink('../../' . $file_path);
                        }
                    }
                }
                break;
        }
    }
}

// Get teacher's tasks
$stmt = $conn->prepare("
    SELECT t.*, k.nama_kelas, k.tahun_ajaran, jt.nama as jenis_tugas,
           (SELECT COUNT(*) FROM pengumpulan_tugas WHERE tugas_id = t.id) as total_pengumpulan
    FROM tugas t
    JOIN kelas k ON t.kelas_id = k.id
    JOIN jenis_tugas jt ON t.jenis_tugas_id = jt.id
    WHERE t.guru_id = ?
    ORDER BY t.batas_pengumpulan DESC
");
$stmt->execute([$_SESSION['user_id']]);
$tugas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tugas - StatiCore</title>
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

        .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--info);
            font-size: 0.9rem;
        }

        .file-info i {
            color: var(--primary);
        }

        /* Responsive Design */
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

            .content-wrapper,
            .p-4 {
                margin-left: 0 !important;
                padding: 16px !important;
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

            .card,
            .card-body,
            .card-header {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
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
                <h2 class="Title">Kelola Tugas</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo isset($message_type) ? $message_type : 'info'; ?> alert-dismissible fade show"
                        role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Create Task Button -->
                <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal"
                    data-bs-target="#createTaskModal">
                    <i class="fas fa-plus"></i> Buat Tugas Baru
                </button>

                <!-- Tasks List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Daftar Tugas</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Judul</th>
                                        <th>Jenis</th>
                                        <th>Kelas</th>
                                        <th>File</th>
                                        <th>Batas Pengumpulan</th>
                                        <th>Total Pengumpulan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tugas as $t): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($t['judul']); ?></td>
                                            <td><?php echo htmlspecialchars($t['jenis_tugas']); ?></td>
                                            <td><?php echo htmlspecialchars($t['nama_kelas'] . ' (' . $t['tahun_ajaran'] . ')'); ?>
                                            </td>
                                            <td>
                                                <?php if ($t['file_path']): ?>
                                                    <div class="file-info">
                                                        <i class="fas fa-file"></i>
                                                        <a href="../../<?php echo $t['file_path']; ?>" target="_blank"
                                                            class="text-decoration-none">
                                                            Lihat File
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Tidak ada file</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d M Y H:i', strtotime($t['batas_pengumpulan'])); ?></td>
                                            <td><?php echo $t['total_pengumpulan']; ?> siswa</td>
                                            <td>
                                                <a href="detail_tugas.php?id=<?php echo $t['id']; ?>"
                                                    class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_tugas.php?id=<?php echo $t['id']; ?>"
                                                    class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete_tugas.php?id=<?php echo $t['id']; ?>"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Apakah Anda yakin ingin menghapus tugas ini?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTaskModalLabel">Buat Tugas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_tugas">

                        <div class="mb-3">
                            <label for="judul" class="form-label">Judul Tugas</label>
                            <input type="text" class="form-control" id="judul" name="judul" required>
                        </div>

                        <div class="mb-3">
                            <label for="jenis_tugas_id" class="form-label">Jenis Tugas</label>
                            <select class="form-select" id="jenis_tugas_id" name="jenis_tugas_id" required>
                                <option value="">Pilih Jenis Tugas</option>
                                <?php foreach ($jenis_tugas as $jt): ?>
                                    <option value="<?php echo $jt['id']; ?>"><?php echo htmlspecialchars($jt['nama']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="kelas_id" class="form-label">Kelas</label>
                            <select class="form-select" id="kelas_id" name="kelas_id" required>
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['nama_kelas'] . ' (' . $class['tahun_ajaran'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="file_tugas" class="form-label">File Tugas (Opsional)</label>
                            <input type="file" class="form-control" id="file_tugas" name="file_tugas"
                                accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar">
                            <div class="form-text">
                                Tipe file yang diizinkan: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, ZIP, RAR. Maksimal
                                10MB.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="batas_pengumpulan" class="form-label">Batas Pengumpulan</label>
                            <input type="datetime-local" class="form-control" id="batas_pengumpulan"
                                name="batas_pengumpulan" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Buat Tugas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        });
    </script>
</body>

</html>
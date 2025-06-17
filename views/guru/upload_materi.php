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
$message_type = '';

// Get teacher's classes
$stmt = $conn->prepare("
    SELECT k.* FROM kelas k
    JOIN guru_kelas gk ON k.id = gk.kelas_id
    WHERE gk.guru_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'upload_materi') {
        $judul = filter_input(INPUT_POST, 'judul', FILTER_SANITIZE_STRING);
        $deskripsi = filter_input(INPUT_POST, 'deskripsi', FILTER_SANITIZE_STRING);
        $kelas_id = filter_input(INPUT_POST, 'kelas_id', FILTER_SANITIZE_NUMBER_INT);

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];

            // Validate file
            $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_types)) {
                $message = "Tipe file tidak diizinkan. Hanya file PDF, DOC, DOCX, PPT, PPTX, dan TXT yang diperbolehkan.";
                $message_type = 'error';
            } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
                $message = "Ukuran file terlalu besar. Maksimal 10MB.";
                $message_type = 'error';
            } else {
                try {
                    // Create uploads directory if it doesn't exist
                    $upload_dir = '../../uploads/materi/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    // Generate unique filename
                    $filename = uniqid() . '_' . $file['name'];
                    $filepath = $upload_dir . $filename;

                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Save to database
                        $stmt = $conn->prepare("
                            INSERT INTO materi (judul, deskripsi, file_path, kelas_id, guru_id)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$judul, $deskripsi, $filename, $kelas_id, $_SESSION['user_id']]);
                        $message = "Materi berhasil diupload!";
                        $message_type = 'success';
                    } else {
                        $message = "Gagal mengupload file. Silakan coba lagi.";
                        $message_type = 'error';
                    }
                } catch (PDOException $e) {
                    $message = "Terjadi kesalahan database: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
        } else {
            $message = "Silakan pilih file untuk diupload.";
            $message_type = 'error';
        }
    }

    // Handle delete material
    if (isset($_POST['action']) && $_POST['action'] === 'delete_materi') {
        $material_id = filter_input(INPUT_POST, 'material_id', FILTER_SANITIZE_NUMBER_INT);

        try {
            // Get file path before deleting from database
            $stmt = $conn->prepare("SELECT file_path FROM materi WHERE id = ? AND guru_id = ?");
            $stmt->execute([$material_id, $_SESSION['user_id']]);
            $material = $stmt->fetch();

            if ($material) {
                // Delete from database
                $stmt = $conn->prepare("DELETE FROM materi WHERE id = ? AND guru_id = ?");
                $stmt->execute([$material_id, $_SESSION['user_id']]);

                // Delete file from server
                $file_path = '../../uploads/materi/' . $material['file_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }

                $message = "Materi berhasil dihapus!";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = "Gagal menghapus materi: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get teacher's materials
$stmt = $conn->prepare("
    SELECT m.*, k.nama_kelas, k.tahun_ajaran
    FROM materi m
    JOIN kelas k ON m.kelas_id = k.id
    WHERE m.guru_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$materials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Materi - StatiCore</title>
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

        .card-body {
            padding: 24px;
        }

        /* Form Controls */
        .form-control,
        .form-select {
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        /* File Upload Area */
        .file-upload-area {
            border: 2px dashed var(--gray-300);
            border-radius: var(--border-radius-md);
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: var(--gray-100);
        }

        .file-upload-area:hover {
            border-color: var(--secondary);
            background: rgba(66, 153, 225, 0.05);
        }

        .file-upload-area.dragover {
            border-color: var(--secondary);
            background: rgba(66, 153, 225, 0.1);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 44px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: var(--gray-600);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--gray-800);
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.75rem;
            min-height: 36px;
        }

        /* Table */
        .table {
            width: 100%;
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            color: var(--primary);
            border-bottom: 2px solid var(--gray-200);
            padding: 16px;
        }

        .table td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-200);
        }

        .table tbody tr:hover {
            background-color: var(--gray-100);
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .bg-success {
            background: rgba(25, 135, 84, 0.1) !important;
            color: var(--success) !important;
        }

        .bg-warning {
            background: rgba(255, 193, 7, 0.1) !important;
            color: var(--warning) !important;
        }

        .bg-danger {
            background: rgba(220, 53, 69, 0.1) !important;
            color: var(--danger) !important;
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius-md);
            border: none;
            padding: 16px 20px;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .alert-info {
            background: rgba(13, 202, 240, 0.1);
            color: var(--info);
            border-left: 4px solid var(--info);
        }

        /* Loading Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
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
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: white;
                border: none;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
                text-align: center;
                line-height: 50px;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            }
        }

        @media (max-width: 768px) {
            .file-upload-area {
                padding: 1rem;
            }

            .file-upload-area p {
                font-size: 12px;
            }

            .alert {
                font-size: 13px;
                padding: 0.75rem 1rem;
            }

            .table-responsive table thead th {
                display: none;
            }

            .table-responsive table tbody tr td {
                display: block;
                padding: 0.75rem 1rem;
                border-bottom: 1px solid var(--gray-200);
            }

            .table-responsive table tbody tr td::before {
                content: attr(data-label);
                font-weight: bold;
                display: inline-block;
                width: 120px;
                color: var(--gray-600);
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
                            <a class="nav-link active" href="upload_materi.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="Title">Upload Materi</div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Upload Form -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus-circle me-2"></i>Upload Materi Baru
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                    <input type="hidden" name="action" value="upload_materi">

                                    <div class="mb-3">
                                        <label class="form-label">Judul Materi *</label>
                                        <input type="text" class="form-control" name="judul" required
                                            placeholder="Masukkan judul materi">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Deskripsi</label>
                                        <textarea class="form-control" name="deskripsi" rows="3"
                                            placeholder="Masukkan deskripsi materi (opsional)"></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Kelas *</label>
                                        <select class="form-select" name="kelas_id" required>
                                            <option value="">Pilih Kelas</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>">
                                                    <?php echo htmlspecialchars($class['nama_kelas']); ?>
                                                    (<?php echo htmlspecialchars($class['tahun_ajaran']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">File Materi *</label>
                                        <div class="file-upload-area"
                                            onclick="document.getElementById('fileInput').click()">
                                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"
                                                style="color: var(--secondary);"></i>
                                            <p class="mb-1"><strong>Klik untuk memilih file</strong> atau drag & drop
                                            </p>
                                            <p class="form-text mb-0">PDF, DOC, DOCX, PPT, PPTX, TXT (Max. 10MB)</p>
                                        </div>
                                        <input type="file" class="form-control" name="file" id="fileInput"
                                            style="display: none;" required accept=".pdf,.doc,.docx,.ppt,.pptx,.txt">
                                        <div id="fileName" class="form-text mt-2"></div>
                                    </div>

                                    <button type="submit" class="btn btn-primary" id="uploadBtn">
                                        <i class="fas fa-upload me-2"></i>Upload Materi
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Materials List -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-folder-open me-2"></i>Daftar Materi
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($materials)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-folder-open fa-3x mb-3" style="color: var(--gray-300);"></i>
                                        <p class="text-muted">Belum ada materi yang diupload</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Judul</th>
                                                    <th>Kelas</th>
                                                    <th>Tanggal</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($materials as $material): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <i class="fas fa-file-alt me-2"
                                                                    style="color: var(--secondary);"></i>
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars($material['judul']); ?></strong>
                                                                    <?php if ($material['deskripsi']): ?>
                                                                        <br><small
                                                                            class="text-muted"><?php echo htmlspecialchars($material['deskripsi']); ?></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary">
                                                                <?php echo htmlspecialchars($material['nama_kelas']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small><?php echo date('d M Y', strtotime($material['created_at'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-2">
                                                                <a href="../../uploads/materi/<?php echo htmlspecialchars($material['file_path']); ?>"
                                                                    class="btn btn-sm btn-info" target="_blank"
                                                                    title="Download">
                                                                    <i class="fas fa-download"></i>
                                                                </a>
                                                                <button class="btn btn-sm btn-danger"
                                                                    onclick="deleteMaterial(<?php echo $material['id']; ?>)"
                                                                    title="Hapus">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // File upload handling
        const fileInput = document.getElementById('fileInput');
        const fileName = document.getElementById('fileName');
        const fileUploadArea = document.querySelector('.file-upload-area');
        const uploadForm = document.getElementById('uploadForm');
        const uploadBtn = document.getElementById('uploadBtn');

        fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                fileName.innerHTML = `<i class="fas fa-file me-1"></i><strong>File dipilih:</strong> ${file.name} (${formatFileSize(file.size)})`;
                fileName.style.color = 'var(--secondary)';

                // Validate file type
                const allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt'];
                const fileExt = file.name.split('.').pop().toLowerCase();

                if (!allowedTypes.includes(fileExt)) {
                    fileName.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i><strong>Error:</strong> Tipe file tidak diizinkan`;
                    fileName.style.color = 'var(--danger)';
                    return;
                }

                if (file.size > 10 * 1024 * 1024) {
                    fileName.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i><strong>Error:</strong> File terlalu besar (max 10MB)`;
                    fileName.style.color = 'var(--danger)';
                    return;
                }
            }
        });

        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', function (e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', function (e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', function (e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });

        // Form submission with loading state
        uploadForm.addEventListener('submit', function (e) {
            const file = fileInput.files[0];
            if (!file) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error!',
                    text: 'Silakan pilih file untuk diupload',
                    icon: 'error',
                    confirmButtonColor: '#2c5282'
                });
                return;
            }

            // Show loading state
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<div class="spinner me-2"></div>Mengupload...';
        });

        // Delete material function
        function deleteMaterial(materialId) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Materi yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2c5282',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete_materi">
                        <input type="hidden" name="material_id" value="${materialId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Utility functions
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Logout confirmation
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
                        confirmButtonColor: '#2c5282',
                        cancelButtonColor: '#6c757d',
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
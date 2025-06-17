<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Check if user is logged in and is admin
$auth->checkSession();
$auth->requireRole('admin');

$conn = $db->getConnection();
$message = '';
$message_type = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['csrf_token'])) {
        // Simple CSRF token validation (you should implement proper token generation)
        if ($_POST['csrf_token'] === session_id()) {
            switch ($_POST['action']) {
                case 'add_class':
                    $nama_kelas = trim(filter_input(INPUT_POST, 'nama_kelas', FILTER_SANITIZE_STRING));
                    $tahun_ajaran = trim(filter_input(INPUT_POST, 'tahun_ajaran', FILTER_SANITIZE_STRING));

                    if (empty($nama_kelas) || empty($tahun_ajaran)) {
                        $message = "Semua field harus diisi";
                        $message_type = 'error';
                        break;
                    }

                    try {
                        // Check if class already exists
                        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM kelas WHERE nama_kelas = ? AND tahun_ajaran = ?");
                        $check_stmt->execute([$nama_kelas, $tahun_ajaran]);

                        if ($check_stmt->fetchColumn() > 0) {
                            $message = "Kelas dengan nama dan tahun ajaran yang sama sudah ada";
                            $message_type = 'error';
                        } else {
                            $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, tahun_ajaran, created_at) VALUES (?, ?, NOW())");
                            $stmt->execute([$nama_kelas, $tahun_ajaran]);
                            $message = "Kelas berhasil ditambahkan";
                            $message_type = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = "Terjadi kesalahan saat menambahkan kelas";
                        $message_type = 'error';
                        error_log("Database error: " . $e->getMessage());
                    }
                    break;

                case 'add_user':
                    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING));
                    $password = $_POST['password'];
                    $nama_lengkap = trim(filter_input(INPUT_POST, 'nama_lengkap', FILTER_SANITIZE_STRING));
                    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

                    if (empty($username) || empty($password) || empty($nama_lengkap) || empty($role)) {
                        $message = "Semua field harus diisi";
                        $message_type = 'error';
                        break;
                    }

                    if (strlen($password) < 6) {
                        $message = "Password minimal 6 karakter";
                        $message_type = 'error';
                        break;
                    }

                    try {
                        // Check if username already exists
                        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                        $check_stmt->execute([$username]);

                        if ($check_stmt->fetchColumn() > 0) {
                            $message = "Username sudah digunakan";
                            $message_type = 'error';
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                            $stmt->execute([$username, $hashed_password, $nama_lengkap, $role]);
                            $message = "Pengguna berhasil ditambahkan";
                            $message_type = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = "Terjadi kesalahan saat menambahkan pengguna";
                        $message_type = 'error';
                        error_log("Database error: " . $e->getMessage());
                    }
                    break;

                case 'assign_user':
                    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                    $kelas_id = filter_input(INPUT_POST, 'kelas_id', FILTER_SANITIZE_NUMBER_INT);
                    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

                    if (empty($user_id) || empty($kelas_id) || empty($role)) {
                        $message = "Semua field harus diisi";
                        $message_type = 'error';
                        break;
                    }

                    try {
                        $table = ($role === 'guru') ? 'guru_kelas' : 'siswa_kelas';
                        $user_field = ($role === 'guru') ? 'guru_id' : 'siswa_id';

                        // Check if user is already assigned to this class
                        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM {$table} WHERE {$user_field} = ? AND kelas_id = ?");
                        $check_stmt->execute([$user_id, $kelas_id]);

                        if ($check_stmt->fetchColumn() > 0) {
                            $message = "Pengguna sudah terdaftar di kelas ini";
                            $message_type = 'error';
                        } else {
                            $stmt = $conn->prepare("INSERT INTO {$table} ({$user_field}, kelas_id, created_at) VALUES (?, ?, NOW())");
                            $stmt->execute([$user_id, $kelas_id]);
                            $message = "Pengguna berhasil ditambahkan ke kelas";
                            $message_type = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = "Terjadi kesalahan saat menambahkan pengguna ke kelas";
                        $message_type = 'error';
                        error_log("Database error: " . $e->getMessage());
                    }
                    break;
            }
        } else {
            $message = "Token keamanan tidak valid";
            $message_type = 'error';
        }
    }
}

// Get all classes with user counts
$classes_query = "
    SELECT k.*, 
           COUNT(DISTINCT gk.guru_id) as guru_count,
           COUNT(DISTINCT sk.siswa_id) as siswa_count
    FROM kelas k
    LEFT JOIN guru_kelas gk ON k.id = gk.kelas_id
    LEFT JOIN siswa_kelas sk ON k.id = sk.kelas_id
    GROUP BY k.id
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
";
$classes = $conn->query($classes_query)->fetchAll();

// Get all users
$users = $conn->query("SELECT * FROM users WHERE role != 'admin' ORDER BY role, nama_lengkap")->fetchAll();

// Get user statistics
$stats_query = $conn->query("
    SELECT 
        role,
        COUNT(*) as count
    FROM users 
    WHERE role != 'admin'
    GROUP BY role
")->fetchAll(PDO::FETCH_KEY_PAIR);

$csrf_token = session_id();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kelas & Pengguna - StatiCore</title>
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

        /* Main Content */
        .main-content {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            border: 1px solid rgba(133, 159, 61, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #3B3B1A;
            line-height: 1;
        }

        .stat-label {
            color: #8A784E;
            font-size: 14px;
            font-weight: 500;
            margin-top: 8px;
        }

        /* Card Styles */
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

        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 20px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #3B3B1A;
            font-size: 14px;
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(133, 159, 61, 0.2);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background-color: #ffffff;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: #8A784E;
            box-shadow: 0 0 0 3px rgba(133, 159, 61, 0.1);
        }

        /* Button Styles */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #AEC8A4 0%, #8A784E 100%);
            color: #ffffff;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
        }

        /* Alert Styles */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            animation: slideInDown 0.3s ease;
        }

        .alert-success {
            background-color: rgba(34, 197, 94, 0.1);
            color: #22c95e;
            border-left: 4px solid #22c95e;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }

        .alert i {
            margin-right: 12px;
            font-size: 18px;
        }

        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid rgba(133, 159, 61, 0.1);
        }

        .table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table th {
            background: #EAEFEF;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #8A784E;
            border-bottom: 2px solid rgba(133, 159, 61, 0.1);
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid rgba(133, 159, 61, 0.1);
            color: #3B3B1A;
        }

        .table tbody tr:hover {
            background-color: rgba(133, 159, 61, 0.05);
        }

        /* Grid Layout */
        .grid {
            display: grid;
            gap: 24px;
        }

        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: spin 1s ease-in-out infinite;
        }

        /* Alert */
        .alert-info {
            background-color: #B8CFCE;
            color: #3B3B1A;
        }

        /* Animations */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 100%;
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--color-green);
            color: var(--color-white);
            border: none;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            box-shadow: var(--shadow-md);
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            background: var(--color-green);
            color: var(--color-white);
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
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
                            <a class="nav-link active" href="manage_classes.php">
                                <i class="fas fa-users me-2"></i>Kelola Kelas & Pengguna
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="detail_kelas.php">
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
                <div class="mb-4 Title">Kelola Kelas & Pengguna</div>
                <div class="subtitle mb-4">Tambah kelas baru, pengguna, dan atur keanggotaan kelas.</div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($classes); ?></div>
                        <div class="stat-label">Total Kelas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats_query['guru'] ?? 0; ?></div>
                        <div class="stat-label">Total Dosen</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats_query['siswa'] ?? 0; ?></div>
                        <div class="stat-label">Total Mahasiswa/i</div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?= $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Add Class Form -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-plus-circle me-2"></i>Tambah Kelas Baru
                            </div>
                            <div class="card-body">
                                <form method="POST" id="addClassForm">
                                    <input type="hidden" name="action" value="add_class">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                                    <div class="form-group">
                                        <label class="form-label">Nama Kelas *</label>
                                        <input type="text" class="form-control" name="nama_kelas"
                                            placeholder="Contoh: 7A, 8B, 9C" required maxlength="50">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Tahun Ajaran *</label>
                                        <input type="text" class="form-control" name="tahun_ajaran"
                                            placeholder="2024/2025" required pattern="[0-9]{4}/[0-9]{4}"
                                            title="Format: YYYY/YYYY">
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Tambah Kelas
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Add User Form -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-user-plus me-2"></i>Tambah Pengguna Baru
                            </div>
                            <div class="card-body">
                                <form method="POST" id="addUserForm">
                                    <input type="hidden" name="action" value="add_user">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                                    <div class="form-group">
                                        <label class="form-label">Username *</label>
                                        <input type="text" class="form-control" name="username" required minlength="3"
                                            maxlength="50" placeholder="Username unik">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Password *</label>
                                        <input type="password" class="form-control" name="password" required
                                            minlength="6" placeholder="Minimal 6 karakter">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Nama Lengkap *</label>
                                        <input type="text" class="form-control" name="nama_lengkap" required
                                            maxlength="100" placeholder="Nama lengkap pengguna">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Role *</label>
                                        <select class="form-select" name="role" required>
                                            <option value="">Pilih Role</option>
                                            <option value="guru">Dosen</option>
                                            <option value="siswa">Mahasiswa/i</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-user-plus me-2"></i>Tambah Pengguna
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Assign User to Class -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-link me-2"></i>Tambahkan Pengguna ke Kelas
                            </div>
                            <div class="card-body">
                                <form method="POST" id="assignUserForm">
                                    <input type="hidden" name="action" value="assign_user">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                                    <div class="form-group">
                                        <label class="form-label">Pengguna *</label>
                                        <select class="form-select" name="user_id" required id="userSelect">
                                            <option value="">Pilih Pengguna</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>"
                                                    data-role="<?php echo $user['role']; ?>">
                                                    <?php echo htmlspecialchars($user['nama_lengkap']); ?>
                                                    <!-- <span class="badge"><?php echo ucfirst($user['role']); ?></span> -->
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
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

                                    <input type="hidden" name="role" id="user_role">

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-user-check me-2"></i>Tambahkan ke Kelas
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Class List -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-list me-2"></i>Daftar Kelas
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Nama Kelas</th>
                                                <th>Tahun Ajaran</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($classes as $class): ?>
                                                <tr>
                                                    <td><?= $class['nama_kelas']; ?></td>
                                                    <td><?= $class['tahun_ajaran']; ?></td>
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

    <!-- Role Change Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const userSelect = document.querySelector('select[name="user_id"]');
            const roleInput = document.getElementById('user_role');
            if (userSelect && roleInput) {
                userSelect.addEventListener('change', function () {
                    const selectedOption = this.options[this.selectedIndex];
                    const role = selectedOption.getAttribute('data-role');
                    roleInput.value = role;
                });

                // Set initial role value
                if (userSelect.options.length > 0) {
                    const initialRole = userSelect.options[0].getAttribute('data-role');
                    roleInput.value = initialRole;
                }
            }
        });
    </script>
</body>

</html>
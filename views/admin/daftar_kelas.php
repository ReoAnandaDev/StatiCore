<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$conn = $db->getConnection();

$auth = new Auth($conn);
$auth->checkSession();
$auth->requireRole('admin');

// Ambil semua kelas
$stmt_kelas_list = $conn->query("SELECT id, nama_kelas, tahun_ajaran FROM kelas ORDER BY tahun_ajaran DESC");
$list_kelas = $stmt_kelas_list->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Kelas - StatiCore</title>
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

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .col-md-8 {
                width: 100%;
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
                <div class="mb-4 Title">Daftar Kelas</div>

                <div class="row">
                    <div class="col-md-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Pilih Kelas</h5>
                            </div>
                            <div class="card-body overflow-auto" style="max-height: 500px;">
                                <?php if (!empty($list_kelas)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($list_kelas as $k): ?>
                                            <li class="list-group-item"
                                                onclick="window.location.href='detail_kelas.php?id=<?= $k['id'] ?>'">
                                                <?= htmlspecialchars($k['nama_kelas']) ?>
                                                (<?= htmlspecialchars($k['tahun_ajaran']) ?>)
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted text-center my-4">Tidak ada kelas tersedia.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="dashboard.php" class="btn btn-secondary mt-3">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
                </a>
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
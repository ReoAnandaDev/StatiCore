<?php
// --- PHP LOGIC (No changes, same as before) ---
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());
$auth->checkSession();
$auth->requireRole('guru');

$conn = $db->getConnection();

$message = '';
$selected_class = isset($_GET['class_id']) ? filter_var($_GET['class_id'], FILTER_SANITIZE_NUMBER_INT) : null;
$selected_quiz = isset($_GET['quiz_id']) ? filter_var($_GET['quiz_id'], FILTER_SANITIZE_NUMBER_INT) : null;

// ... (Your existing PHP logic remains unchanged) ...

// Get classes that belong to the logged-in teacher
$stmt_kelas = $conn->prepare("
    SELECT DISTINCT k.* FROM kelas k
    JOIN quiz q ON k.id = q.kelas_id
    WHERE q.guru_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt_kelas->execute([$_SESSION['user_id']]);
$kelas_list = $stmt_kelas->fetchAll(PDO::FETCH_ASSOC);

$quizzes = [];
if ($selected_class) {
    $stmt_quiz = $conn->prepare("
        SELECT q.* FROM quiz q 
        WHERE q.kelas_id = ? AND q.guru_id = ? 
        ORDER BY q.waktu_mulai DESC
    ");
    $stmt_quiz->execute([$selected_class, $_SESSION['user_id']]);
    $quizzes = $stmt_quiz->fetchAll(PDO::FETCH_ASSOC);
}

$quiz_info = null;
if ($selected_quiz) {
    $stmt_quiz_info = $conn->prepare("
        SELECT q.*, k.nama_kelas FROM quiz q
        JOIN kelas k ON q.kelas_id = k.id
        WHERE q.id = ? AND q.guru_id = ?
    ");
    $stmt_quiz_info->execute([$selected_quiz, $_SESSION['user_id']]);
    $quiz_info = $stmt_quiz_info->fetch(PDO::FETCH_ASSOC);
}

$grades = [];
if ($selected_quiz && $quiz_info) {
    $stmt = $conn->prepare("
        SELECT 
            u.id AS siswa_id, u.nama_lengkap, k.nama_kelas, q.judul,
            COUNT(sq.id) AS total_soal,
            COALESCE(SUM(js.nilai), 0) AS total_nilai,
            MAX(js.waktu_selesai) AS waktu_submit,
            CASE 
                WHEN COUNT(sq.id) > 0 THEN 
                    ROUND((COALESCE(SUM(js.nilai), 0) / COUNT(sq.id)) * 100, 1)
                ELSE 0 
            END AS persentase_nilai
        FROM users u
        JOIN siswa_kelas sk ON u.id = sk.siswa_id
        JOIN kelas k ON k.id = sk.kelas_id
        JOIN quiz q ON q.kelas_id = k.id
        JOIN soal_quiz sq ON sq.quiz_id = q.id
        LEFT JOIN jawaban_siswa js ON sq.id = js.soal_id AND js.siswa_id = u.id
        WHERE q.id = ? AND k.id = ? AND q.guru_id = ?
        GROUP BY u.id, u.nama_lengkap, k.nama_kelas, q.judul
        ORDER BY u.nama_lengkap
    ");
    $stmt->execute([$selected_quiz, $selected_class, $_SESSION['user_id']]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nilai Siswa - StatiCore</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary-color: #2c5282;
            --secondary-color: #4299e1;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --gray-text: #6c757d;
            --border-color: #dee2e6;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --border-radius: 0.75rem;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
        }

        /* --- Sidebar (Original Styling Restored) --- */
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.8rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--white);
            background-color: rgba(255, 255, 255, 0.15);
        }
        .sidebar h4 {
            font-weight: 600;
            padding-left: 1rem;
            color: white;
        }

        /* --- Main Content (Responsive Improvements Kept) --- */
        .main-content {
            padding: 1.5rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .page-subtitle {
            color: var(--gray-text);
            font-size: 1rem;
        }
        
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: var(--white);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary-color);
        }
        .card-header.bg-gradient-primary {
             background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
             color: var(--white);
             border-bottom: none;
        }
        
        .table-responsive {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        .table {
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        .table thead th {
            background-color: var(--light-bg);
            color: var(--primary-color);
            font-weight: 600;
        }
        .table td, .table th {
            vertical-align: middle;
            padding: 1rem;
        }
        .student-name {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .student-avatar {
            width: 36px;
            height: 36px;
            background-color: var(--secondary-color);
            color: var(--white);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .grade-badge {
            font-weight: 600;
            padding: 0.4em 0.8em;
            border-radius: 50px;
        }
        .grade-a { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
        .grade-b { background-color: rgba(13, 202, 240, 0.15); color: #0dcaf0; }
        .grade-c { background-color: rgba(255, 193, 7, 0.15); color: #ffc107; }
        .grade-d { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-text);
        }
        .empty-state i {
            font-size: 3.5rem;
            color: var(--border-color);
            margin-bottom: 1rem;
        }

        /* --- Responsive Section (Original Sidebar Logic) --- */
        @media (max-width: 991.98px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 260px;
                z-index: 1050;
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(.4, 0, .2, 1);
            }
            .sidebar.drawer-open {
                transform: translateX(0);
            }
            .sidebar-backdrop {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1049;
            }
            .sidebar-backdrop.is-visible {
                display: block;
            }
            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1100;
                background: var(--primary-color);
                color: #fff;
                border: none;
                border-radius: 50%;
                width: 44px;
                height: 44px;
                font-size: 1.5rem;
                box-shadow: 0 2px 8px rgba(44, 82, 130, 0.08);
            }
            /* Adjustments for content */
            .main-content {
                padding: 1rem;
            }
            .page-title { font-size: 1.5rem; }
            .page-subtitle { font-size: 0.95rem; }
            .card-header, .card-body { padding: 1rem; }
            .table td, .table th { padding: 0.75rem; }
            .student-avatar { display: none; }
        }

        @media (min-width: 992px) {
            .mobile-menu-toggle {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" id="drawerToggle" aria-label="Buka menu">
        <i class="fas fa-bars"></i>
    </button>
    <div id="sidebarBackdrop" class="sidebar-backdrop"></div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0 sidebar" id="drawerSidebar">
                <div class="p-3">
                    <h4><i class="fas fa-chart-line me-2"></i>StatiCore</h4>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php"><i class="fas fa-home fa-fw"></i>Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="upload_materi.php"><i class="fas fa-book fa-fw"></i>Materi</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="kelola_quiz.php"><i class="fas fa-question-circle fa-fw"></i>Quiz</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="kelola_tugas.php"><i class="fas fa-tasks fa-fw"></i>Tugas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="nilai_siswa.php"><i class="fas fa-star fa-fw"></i>Nilai</a>
                        </li>
                        <li class="nav-item mt-auto">
                            <a class="nav-link" href="../../logout.php" id="logoutBtn"><i class="fas fa-sign-out-alt fa-fw"></i>Logout</a>
                        </li>
                    </ul>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <header class="page-header">
                    <h1 class="page-title">Nilai Siswa</h1>
                    <p class="page-subtitle">Pantau dan kelola nilai quiz siswa secara real-time.</p>
                </header>

                <div class="card">
                    <div class="card-header"><i class="fas fa-filter me-2"></i> Filter Data</div>
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label for="class_id" class="form-label fw-semibold">Pilih Kelas</label>
                                <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($kelas_list as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['nama_kelas']); ?>
                                            </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($selected_class): ?>
                                    <div class="col-md-6">
                                        <label for="quiz_id" class="form-label fw-semibold">Pilih Quiz</label>
                                        <select name="quiz_id" id="quiz_id" class="form-select" onchange="this.form.submit()">
                                            <option value="">-- Pilih Quiz --</option>
                                            <?php foreach ($quizzes as $quiz): ?>
                                                    <option value="<?php echo $quiz['id']; ?>" <?php echo $selected_quiz == $quiz['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($quiz['judul']); ?>
                                                    </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <?php if ($selected_quiz && $quiz_info): ?>
                        <div class="card">
                            <div class="card-header bg-gradient-primary">
                                <i class="fas fa-graduation-cap me-2"></i> Hasil Quiz: <?php echo htmlspecialchars($quiz_info['judul']); ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($grades)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-user-graduate"></i>
                                            <h3>Belum Ada Data</h3>
                                            <p>Belum ada siswa yang mengerjakan quiz ini.</p>
                                        </div>
                                <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Nama Siswa</th>
                                                        <th>Nilai Akhir</th>
                                                        <th>Detail</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($grades as $grade): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="student-name">
                                                                        <span class="student-avatar"><i class="fas fa-user"></i></span>
                                                                        <span><?php echo htmlspecialchars($grade['nama_lengkap']); ?></span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <?php
                                                                    $score = $grade['persentase_nilai'];
                                                                    $scoreClass = 'grade-d';
                                                                    if ($score >= 85)
                                                                        $scoreClass = 'grade-a';
                                                                    elseif ($score >= 75)
                                                                        $scoreClass = 'grade-b';
                                                                    elseif ($score >= 65)
                                                                        $scoreClass = 'grade-c';
                                                                    ?>
                                                                    <span class="grade-badge <?php echo $scoreClass; ?>"><?php echo $score; ?>%</span>
                                                                </td>
                                                                <td><?php echo $grade['total_nilai']; ?> / <?php echo $grade['total_soal']; ?> Benar</td>
                                                                <td>
                                                                    <?php if ($grade['waktu_submit']): ?>
                                                                            <span class="badge text-bg-success">Selesai</span>
                                                                    <?php else: ?>
                                                                            <span class="badge text-bg-warning">Mengerjakan</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                <?php endif; ?>
                            </div>
                        </div>
                <?php else: ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="empty-state">
                                    <i class="fas fa-hand-pointer"></i>
                                    <h3>Pilih Kelas dan Quiz</h3>
                                    <p>Silakan pilih kelas dan quiz untuk menampilkan data nilai.</p>
                                </div>
                            </div>
                        </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // --- Original Sidebar and Logout Script ---
        document.addEventListener('DOMContentLoaded', function () {
            // Logout confirmation
            const logoutLink = document.getElementById('logoutBtn');
            if (logoutLink) {
                logoutLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Apakah Anda ingin keluar?',
                        text: "Anda akan meninggalkan sesi ini.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--primary-color)',
                        cancelButtonColor: 'var(--gray-text)',
                        confirmButtonText: 'Ya, Keluar',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = logoutLink.href;
                        }
                    });
                });
            }
            
            // Drawer sidebar logic (Original)
            const drawerToggle = document.getElementById('drawerToggle');
            const sidebar = document.getElementById('drawerSidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');

            function openDrawer() {
                sidebar.classList.add('drawer-open');
                sidebarBackdrop.classList.add('is-visible');
            }
            function closeDrawer() {
                sidebar.classList.remove('drawer-open');
                sidebarBackdrop.classList.remove('is-visible');
            }

            drawerToggle.addEventListener('click', function () {
                if (sidebar.classList.contains('drawer-open')) {
                    closeDrawer();
                } else {
                    openDrawer();
                }
            });

            sidebarBackdrop.addEventListener('click', closeDrawer);

            sidebar.querySelectorAll('.nav-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.innerWidth < 992) {
                        closeDrawer();
                    }
                });
            });
        });
    </script>
</body>
</html>
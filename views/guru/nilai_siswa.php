<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());
$auth->checkSession();
$auth->requireRole('guru');

$conn = $db->getConnection();

$message = '';
$selected_class = isset($_GET['class_id']) ? $_GET['class_id'] : null;
$selected_quiz = isset($_GET['quiz_id']) ? $_GET['quiz_id'] : null;

// Handle form submission for auto-grade all students
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'auto_grade_all') {
    $quiz_id = filter_input(INPUT_POST, 'quiz_id', FILTER_SANITIZE_NUMBER_INT);

    // Verify if the quiz belongs to the logged-in teacher
    $stmt = $conn->prepare("SELECT id FROM quiz WHERE id = ? AND guru_id = ?");
    $stmt->execute([$quiz_id, $_SESSION['user_id']]);
    if (!$stmt->fetchColumn()) {
        $message = "Anda tidak memiliki akses ke quiz ini.";
        $message_type = 'danger';
    } else {
        // Ambil semua jawaban siswa untuk quiz ini
        $stmt = $conn->prepare("
            SELECT js.siswa_id, js.soal_id, js.jawaban 
            FROM jawaban_siswa js
            JOIN soal_quiz sq ON js.soal_id = sq.id
            WHERE sq.quiz_id = ?
        ");
        $stmt->execute([$quiz_id]);
        $jawaban_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        try {
            $conn->beginTransaction();

            foreach ($jawaban_list as $js) {
                $soal_id = $js['soal_id'];
                $jawaban = strtoupper(trim($js['jawaban']));

                // Ambil jawaban benar
                $stmtBenar = $conn->prepare("SELECT pilihan FROM pilihan_jawaban WHERE soal_id = ? AND is_benar = 1");
                $stmtBenar->execute([$soal_id]);
                $benar = $stmtBenar->fetchColumn();

                $nilai = 0;
                if ($benar && !empty($jawaban)) {
                    $arrBenar = array_map('trim', explode(',', $benar));
                    $arrJawab = array_map('trim', explode(',', $jawaban));
                    sort($arrBenar);
                    sort($arrJawab);
                    if ($arrBenar === $arrJawab) {
                        $nilai = 1;
                    }
                }

                // Update nilai otomatis
                $stmtUpdate = $conn->prepare("UPDATE jawaban_siswa SET nilai = ? WHERE siswa_id = ? AND soal_id = ?");
                $stmtUpdate->execute([$nilai, $js['siswa_id'], $soal_id]);
            }

            $conn->commit();
            $message = "Nilai berhasil diperbarui otomatis untuk semua siswa.";
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollBack();
            $message = "Gagal memperbarui nilai: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get classes that belong to the logged-in teacher
$stmt_kelas = $conn->prepare("
    SELECT DISTINCT k.* 
    FROM kelas k
    JOIN quiz q ON k.id = q.kelas_id
    WHERE q.guru_id = ?
    ORDER BY k.tahun_ajaran DESC, k.nama_kelas
");
$stmt_kelas->execute([$_SESSION['user_id']]);
$kelas_list = $stmt_kelas->fetchAll(PDO::FETCH_ASSOC);

$quizzes = [];
if ($selected_class) {
    // Get quizzes for the selected class
    $stmt_quiz = $conn->prepare("
        SELECT q.* 
        FROM quiz q 
        WHERE q.kelas_id = ? AND q.guru_id = ? 
        ORDER BY q.waktu_mulai DESC
    ");
    $stmt_quiz->execute([$selected_class, $_SESSION['user_id']]);
    $quizzes = $stmt_quiz->fetchAll(PDO::FETCH_ASSOC);
}

$quiz_info = null;
if ($selected_quiz) {
    // Get quiz info
    $stmt_quiz_info = $conn->prepare("
        SELECT q.*, k.nama_kelas 
        FROM quiz q
        JOIN kelas k ON q.kelas_id = k.id
        WHERE q.id = ? AND q.guru_id = ?
    ");
    $stmt_quiz_info->execute([$selected_quiz, $_SESSION['user_id']]);
    $quiz_info = $stmt_quiz_info->fetch(PDO::FETCH_ASSOC);
}

// Get student grades for selected quiz
$grades = [];
if ($selected_quiz && $quiz_info) {
    // Load grades with proper calculation
    $stmt = $conn->prepare("
        SELECT 
            u.id AS siswa_id,
            u.nama_lengkap,
            k.nama_kelas,
            q.judul,
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
    <title>Nilai Mahasiswa/i - StatiCore</title>
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
            background-color: #f8f9fa;
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

        /* Grade Badges */
        .grade-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .grade-a {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success);
        }

        .grade-b {
            background: rgba(13, 202, 240, 0.1);
            color: var(--info);
        }

        .grade-c {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .grade-d {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        /* Search and Filter */
        .search-filter {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .search-input {
            flex: 1;
            position: relative;
        }

        .search-input input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .search-input input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .search-input i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
        }

        .filter-select {
            min-width: 200px;
            padding: 12px 16px;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--gray-400);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray-700);
        }

        .empty-state p {
            margin-bottom: 24px;
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
                            <a class="nav-link" href="kelola_tugas.php">
                                <i class="fas fa-tasks me-2"></i>Tugas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="nilai_siswa.php">
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
                <div class="Title">Nilai Mahasiswa/i</div>

                <!-- Class and Quiz Selection -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Pilih Kelas</label>
                                <select name="class_id" class="form-select" onchange="this.form.submit()">
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
                                    <label class="form-label">Pilih Quiz</label>
                                    <select name="quiz_id" class="form-select" onchange="this.form.submit()">
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
                    <!-- Quiz Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Informasi Quiz</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Judul:</strong> <?php echo htmlspecialchars($quiz_info['judul']); ?></p>
                                    <p><strong>Kelas:</strong> <?php echo htmlspecialchars($quiz_info['nama_kelas']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Waktu Mulai:</strong>
                                        <?php echo date('d/m/Y H:i', strtotime($quiz_info['waktu_mulai'])); ?></p>
                                    <p><strong>Waktu Selesai:</strong>
                                        <?php echo date('d/m/Y H:i', strtotime($quiz_info['waktu_selesai'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grades Table -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-graduation-cap me-2"></i>Daftar Nilai
                        </div>
                        <div class="card-body">
                            <?php if (empty($grades)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-graduate"></i>
                                    <h3>Belum ada data nilai</h3>
                                    <p>Belum ada siswa yang mengerjakan quiz ini</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Nama Mahasiswa/i</th>
                                                <th>Kelas</th>
                                                <th>Quiz</th>
                                                <th>Nilai</th>
                                                <th>Status</th>
                                                <!-- <th>Aksi</th> -->
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($grades as $grade): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-user-circle me-2" style="color: var(--secondary);"></i>
                                                            <?php echo htmlspecialchars($grade['nama_lengkap']); ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($grade['nama_kelas']); ?></td>
                                                    <td><?php echo htmlspecialchars($grade['judul']); ?></td>
                                                    <td>
                                                        <?php
                                                        $score = $grade['persentase_nilai'];
                                                        $scoreClass = '';
                                                        if ($score >= 85) {
                                                            $scoreClass = 'grade-a';
                                                        } elseif ($score >= 75) {
                                                            $scoreClass = 'grade-b';
                                                        } elseif ($score >= 65) {
                                                            $scoreClass = 'grade-c';
                                                        } else {
                                                            $scoreClass = 'grade-d';
                                                        }
                                                        ?>
                                                        <span class="grade-badge <?php echo $scoreClass; ?>">
                                                            <?php echo $grade['total_nilai']; ?> /
                                                            <?php echo $grade['total_soal']; ?>
                                                            <!-- (<?php echo $score; ?>%) -->
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($grade['waktu_submit']): ?>
                                                            <span class="badge bg-success">Selesai</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Belum Selesai</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <!-- <td>
                                                        <a href="detail_nilai.php?id=<?php echo $grade['siswa_id']; ?>&quiz_id=<?php echo $selected_quiz; ?>"
                                                            class="btn btn-primary btn-sm">
                                                            <i class="fas fa-eye me-2"></i>Detail
                                                        </a>
                                                    </td> -->
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>Pilih Kelas dan Quiz</h3>
                        <p>Silakan pilih kelas dan quiz untuk melihat nilai siswa</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Filter students
        function filterStudents() {
            const searchInput = document.getElementById('searchInput');
            const classFilter = document.getElementById('classFilter');
            const rows = document.querySelectorAll('tbody tr');

            const searchTerm = searchInput.value.toLowerCase();
            const classTerm = classFilter.value;

            rows.forEach(row => {
                const name = row.querySelector('td:first-child').textContent.toLowerCase();
                const classCell = row.querySelector('td:nth-child(2)').textContent;

                const matchesSearch = name.includes(searchTerm);
                const matchesClass = !classTerm || classCell.includes(classTerm);

                row.style.display = matchesSearch && matchesClass ? '' : 'none';
            });
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
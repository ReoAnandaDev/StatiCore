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

// Get quiz ID from URL
$quiz_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$quiz_id) {
    header('Location: kelola_quiz.php');
    exit;
}

try {
    $conn->beginTransaction();

    // Verify if the quiz belongs to the logged-in teacher
    $stmt = $conn->prepare("SELECT id FROM quiz WHERE id = ? AND guru_id = ?");
    $stmt->execute([$quiz_id, $_SESSION['user_id']]);
    $quiz_exists = $stmt->fetchColumn();

    if (!$quiz_exists) {
        throw new Exception("Quiz tidak ditemukan atau Anda tidak berhak menghapusnya.");
    }

    // Get question IDs for this quiz
    $stmt = $conn->prepare("SELECT id FROM soal_quiz WHERE quiz_id = ?");
    $stmt->execute([$quiz_id]);
    $soal_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Delete student answers for questions in this quiz
    if (!empty($soal_ids)) {
        $placeholders = implode(',', array_fill(0, count($soal_ids), '?'));
        $stmt = $conn->prepare("DELETE FROM jawaban_siswa WHERE soal_id IN ($placeholders)");
        $stmt->execute($soal_ids);

        // Delete options for questions in this quiz
        $stmt = $conn->prepare("DELETE FROM pilihan_jawaban WHERE soal_id IN ($placeholders)");
        $stmt->execute($soal_ids);
    }

    // Delete questions for this quiz
    $stmt = $conn->prepare("DELETE FROM soal_quiz WHERE quiz_id = ?");
    $stmt->execute([$quiz_id]);

    // Delete the quiz
    $stmt = $conn->prepare("DELETE FROM quiz WHERE id = ?");
    $stmt->execute([$quiz_id]);

    $conn->commit();
    $message = "Quiz berhasil dihapus.";
    $message_type = 'success';

} catch (Exception $e) {
    $conn->rollBack();
    $message = "Gagal menghapus quiz: " . $e->getMessage();
    $message_type = 'danger';
}

// Redirect back to kelola_quiz.php with message
header('Location: kelola_quiz.php?message=' . urlencode($message) . '&type=' . $message_type);
exit;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Quiz - StatiCore</title>
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

        .card-body {
            padding: 24px;
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--border-radius-md);
            margin-bottom: 24px;
            border: none;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
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

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-1px);
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
                            <a class="nav-link" href="upload_materi.php">
                                <i class="fas fa-upload me-2"></i>Upload Materi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="kelola_quiz.php">
                                <i class="fas fa-tasks me-2"></i>Kelola Quiz
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="nilai_siswa.php">
                                <i class="fas fa-chart-bar me-2"></i>Nilai Siswa
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
                <div class="mb-4 Title">Hapus Quiz</div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </div>
                    <div class="card-body">
                        <p>Apakah Anda yakin ingin menghapus quiz ini? Tindakan ini tidak dapat dibatalkan.</p>
                        <div class="d-flex gap-2">
                            <a href="kelola_quiz.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <a href="delete_quiz.php?id=<?php echo $quiz_id; ?>&confirm=1" class="btn btn-danger">
                                <i class="fas fa-trash me-2"></i>Hapus Quiz
                            </a>
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
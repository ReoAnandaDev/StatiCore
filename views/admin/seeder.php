<?php
// =================================================================
// IMPORTER DATA SISWA VIA ANTARMUKA WEB
// =================================================================

// Tingkatkan batas waktu eksekusi script menjadi 5 menit (300 detik)
set_time_limit(300);

// Sertakan file-file yang diperlukan
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Inisialisasi
$db = new Database();
$auth = new Auth($db->getConnection());
$conn = $db->getConnection();

// Autentikasi: Hanya Admin yang bisa mengakses halaman ini
$auth->checkSession();
$auth->requireRole('admin');

// Array untuk menampung pesan feedback
$feedback_messages = [];

// Proses Importer jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Opsi untuk menghapus data siswa dan relasinya sebelum impor
        $truncate_data = isset($_POST['truncate_data']);
        if ($truncate_data) {
            $feedback_messages[] = "Menghapus data siswa lama...";
            $conn->exec('SET FOREIGN_KEY_CHECKS=0;');

            // PENTING: Hanya hapus user dengan role 'siswa' untuk menjaga data admin dan guru
            $conn->exec("DELETE FROM users WHERE role = 'siswa'");

            // Hapus relasi siswa di kelas
            $conn->exec("TRUNCATE TABLE siswa_kelas");

            $conn->exec('SET FOREIGN_KEY_CHECKS=1;');
            $feedback_messages[] = "Data siswa lama berhasil dihapus.";
        }

        // PROSES FILE SISWA
        if (isset($_FILES['file_siswa']) && $_FILES['file_siswa']['error'] === UPLOAD_ERR_OK) {
            $feedback_messages[] = "Memproses file siswa...";
            $file = fopen($_FILES['file_siswa']['tmp_name'], 'r');
            fgetcsv($file); // Abaikan baris header CSV

            $stmt_user = $conn->prepare("INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, 'siswa')");
            $stmt_siswa_kelas = $conn->prepare("INSERT INTO siswa_kelas (siswa_id, kelas_id) VALUES (?, ?)");

            // Asumsi: Kelas sudah ada di database. Kita hanya perlu mencari ID-nya.
            $stmt_find_kelas = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ? LIMIT 1");

            $count = 0;
            while (($data = fgetcsv($file)) !== FALSE) {
                // Pastikan data CSV memiliki 4 kolom yang diharapkan
                if (count($data) < 4) {
                    $feedback_messages[] = "<span class='text-warning'>Peringatan: Satu baris dilewati karena format tidak lengkap.</span>";
                    continue;
                }

                $nama_lengkap = $data[0];
                $username = $data[1];
                $password = $data[2];
                $nama_kelas_tujuan = $data[3];

                // 1. Cari kelas berdasarkan nama
                $stmt_find_kelas->execute([$nama_kelas_tujuan]);
                $kelas = $stmt_find_kelas->fetch();

                if ($kelas) {
                    // 2. Jika kelas ditemukan, buat user siswa baru
                    $stmt_user->execute([$nama_lengkap, $username, password_hash($password, PASSWORD_BCRYPT)]);
                    $siswa_id = $conn->lastInsertId();

                    // 3. Masukkan siswa ke dalam kelas tersebut
                    $stmt_siswa_kelas->execute([$siswa_id, $kelas['id']]);
                    $count++;
                } else {
                    // Beri peringatan jika kelas tidak ditemukan
                    $feedback_messages[] = "<span class='text-warning'>Peringatan: Kelas dengan nama '{$nama_kelas_tujuan}' tidak ditemukan untuk siswa '{$nama_lengkap}'. Baris ini dilewati.</span>";
                }
            }
            fclose($file);
            $feedback_messages[] = "$count siswa berhasil diimpor.";
        } else {
            // Beri pesan jika tidak ada file yang diunggah
            if (isset($_FILES['file_siswa']) && $_FILES['file_siswa']['error'] !== UPLOAD_ERR_NO_FILE) {
                $feedback_messages[] = "<span class='text-danger'>Error saat mengunggah file siswa.</span>";
            }
        }

        $conn->commit();
        if (isset($_FILES['file_siswa']) && $_FILES['file_siswa']['error'] === UPLOAD_ERR_OK) {
            $feedback_messages[] = "<strong>PROSES IMPOR SELESAI!</strong>";
        }

    } catch (Exception $e) {
        $conn->rollBack();
        $feedback_messages[] = "<strong class='text-danger'>TERJADI ERROR:</strong> " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Importer Siswa - StatiCore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container my-5" style="max-width: 800px;">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h3 class="mb-0">Bulk Data Importer - Siswa</h3>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">Gunakan form ini untuk mengimpor data siswa dari file CSV. Pastikan kelas tujuan untuk setiap siswa sudah ada di dalam sistem.</p>

                <?php if (!empty($feedback_messages)): ?>
                        <div class="alert alert-secondary">
                            <strong>Log Proses Impor:</strong><br>
                            <?php echo implode('<br>', $feedback_messages); ?>
                        </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <fieldset class="border p-3 mb-4">
                        <legend class="w-auto px-2 fs-5">Impor Data Siswa</legend>
                        <div class="mb-3">
                            <label for="file_siswa" class="form-label">File Siswa (.csv)</label>
                            <input type="file" class="form-control" id="file_siswa" name="file_siswa" accept=".csv" required>
                            <div class="form-text">Format CSV: `nama_lengkap`, `username`, `password`, `nama_kelas_tujuan`</div>
                        </div>
                    </fieldset>

                    <hr class="my-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="truncate_data" id="truncate_data">
                        <label class="form-check-label text-danger fw-bold" for="truncate_data">
                            HAPUS SEMUA DATA SISWA LAMA SEBELUM IMPOR! (Data Guru dan Admin aman).
                        </label>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 py-2">Proses File yang Diunggah</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
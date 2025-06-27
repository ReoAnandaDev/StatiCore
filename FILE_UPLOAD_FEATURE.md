# Fitur Upload File Tugas

## Deskripsi

Fitur ini memungkinkan guru untuk menyematkan file tugas ketika membuat tugas baru. File yang diupload akan dapat diakses oleh siswa untuk diunduh dan digunakan sebagai referensi dalam mengerjakan tugas.

## Fitur yang Ditambahkan

### 1. Upload File saat Membuat Tugas

- Form pembuatan tugas sekarang memiliki field untuk upload file
- File yang diizinkan: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, ZIP, RAR
- Ukuran maksimal file: 10MB
- File akan disimpan di folder `uploads/tugas/`

### 2. Edit File Tugas

- Guru dapat mengganti file tugas yang sudah ada
- File lama akan dihapus otomatis ketika file baru diupload
- Preview file yang sudah ada ditampilkan di form edit

### 3. Tampilan File di Berbagai Halaman

- **Halaman Kelola Tugas**: Menampilkan link download file di tabel tugas
- **Halaman Detail Tugas**: Menampilkan file tugas di informasi tugas
- **Halaman Edit Tugas**: Preview file yang sudah ada dan opsi upload file baru
- **Halaman Tugas Siswa**: Menampilkan file tugas yang dapat diunduh siswa
- **Halaman Kumpul Tugas**: Menampilkan file tugas untuk referensi siswa
- **Halaman Detail Kelas**: Menampilkan file tugas di daftar tugas kelas

### 4. Penghapusan File

- Ketika tugas dihapus, file fisik juga akan dihapus otomatis
- Mencegah penumpukan file yang tidak terpakai

## Perubahan Database

### Menambahkan Kolom `file_path`

```sql
ALTER TABLE tugas ADD COLUMN file_path VARCHAR(255) NULL AFTER deskripsi;
```

### Struktur Kolom

- **Nama**: `file_path`
- **Tipe**: VARCHAR(255)
- **Null**: YES
- **Default**: NULL
- **Deskripsi**: Menyimpan path relatif ke file tugas yang diupload

## File yang Dimodifikasi

### 1. `views/guru/kelola_tugas.php`

- Menambahkan field upload file di modal pembuatan tugas
- Menangani upload file dan validasi
- Menampilkan link download file di tabel tugas
- Menambahkan kolom File di tabel

### 2. `views/guru/detail_tugas.php`

- Menampilkan file tugas di informasi tugas
- Menambahkan CSS untuk styling file info

### 3. `views/guru/edit_tugas.php`

- Menambahkan field upload file di form edit
- Menangani penggantian file tugas
- Preview file yang sudah ada
- Menghapus file lama ketika upload file baru

### 4. `views/guru/delete_tugas.php`

- Menghapus file fisik ketika tugas dihapus
- Mencegah penumpukan file yang tidak terpakai

### 5. `views/siswa/tugas.php`

- Menampilkan file tugas di kartu tugas
- Link download untuk siswa

### 6. `views/siswa/kumpul_tugas.php`

- Menampilkan file tugas di informasi tugas
- Referensi untuk siswa saat mengumpulkan tugas

### 7. `views/guru/detail_kelas.php`

- Menampilkan file tugas di daftar tugas kelas
- Link download untuk guru

## Struktur Folder Upload

```
uploads/
└── tugas/
    ├── 1234567890_document.pdf
    ├── 1234567891_presentation.pptx
    └── ...
```

## Validasi File

- **Tipe File**: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, ZIP, RAR
- **Ukuran Maksimal**: 10MB
- **Nama File**: Timestamp + nama file asli untuk menghindari konflik

## Keamanan

- Validasi tipe file untuk mencegah upload file berbahaya
- Pembatasan ukuran file untuk menghemat storage
- Sanitasi nama file untuk mencegah path traversal
- Penghapusan file otomatis ketika tugas dihapus

## Cara Penggunaan

### Untuk Guru:

1. Buka halaman "Kelola Tugas"
2. Klik "Buat Tugas Baru"
3. Isi informasi tugas seperti biasa
4. Upload file tugas (opsional)
5. Klik "Buat Tugas"

### Untuk Siswa:

1. Buka halaman "Tugas"
2. Lihat daftar tugas yang tersedia
3. Klik "Download" pada file tugas yang ingin diunduh
4. File akan dibuka di tab baru atau diunduh

## Catatan Penting

- Pastikan folder `uploads/tugas/` memiliki permission yang tepat (777 untuk development)
- Backup database sebelum menjalankan script SQL
- Monitor penggunaan storage untuk mencegah disk penuh
- Pertimbangkan implementasi CDN untuk file besar di production

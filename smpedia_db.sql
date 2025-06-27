-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 27, 2025 at 07:13 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smpedia_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `guru_kelas`
--

CREATE TABLE `guru_kelas` (
  `id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `kelas_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guru_kelas`
--

INSERT INTO `guru_kelas` (`id`, `guru_id`, `kelas_id`, `created_at`) VALUES
(3, 37, 4, '2025-06-27 03:19:21');

-- --------------------------------------------------------

--
-- Table structure for table `jawaban_siswa`
--

CREATE TABLE `jawaban_siswa` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `soal_id` int(11) NOT NULL,
  `jawaban` text DEFAULT NULL,
  `nilai` int(11) DEFAULT NULL,
  `waktu_mulai` datetime NOT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jenis_tugas`
--

CREATE TABLE `jenis_tugas` (
  `id` int(11) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jenis_tugas`
--

INSERT INTO `jenis_tugas` (`id`, `nama`, `created_at`) VALUES
(1, 'UTS', '2025-06-17 04:24:43'),
(2, 'UAS', '2025-06-17 04:24:43'),
(3, 'LKM Project', '2025-06-17 04:24:43'),
(4, 'Jurnal Pembelajaran', '2025-06-17 04:24:43');

-- --------------------------------------------------------

--
-- Table structure for table `kelas`
--

CREATE TABLE `kelas` (
  `id` int(11) NOT NULL,
  `nama_kelas` varchar(50) NOT NULL,
  `tahun_ajaran` varchar(9) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kelas`
--

INSERT INTO `kelas` (`id`, `nama_kelas`, `tahun_ajaran`, `created_at`) VALUES
(1, 'Statistika 22 A', '2022/2023', '2025-06-17 04:25:56'),
(4, 'PSPM F', '2022/2023', '2025-06-27 03:18:38');

-- --------------------------------------------------------

--
-- Table structure for table `materi`
--

CREATE TABLE `materi` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `kelas_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengumpulan_tugas`
--

CREATE TABLE `pengumpulan_tugas` (
  `id` int(11) NOT NULL,
  `tugas_id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `catatan` text DEFAULT NULL,
  `status` enum('dikumpulkan','dinilai','ditolak') DEFAULT 'dikumpulkan',
  `nilai` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `waktu_pengumpulan` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pilihan_jawaban`
--

CREATE TABLE `pilihan_jawaban` (
  `id` int(11) NOT NULL,
  `soal_id` int(11) NOT NULL,
  `pilihan` text NOT NULL,
  `is_benar` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz`
--

CREATE TABLE `quiz` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `kelas_id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `waktu_mulai` datetime NOT NULL,
  `waktu_selesai` datetime NOT NULL,
  `durasi` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `siswa_kelas`
--

CREATE TABLE `siswa_kelas` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `kelas_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `siswa_kelas`
--

INSERT INTO `siswa_kelas` (`id`, `siswa_id`, `kelas_id`, `created_at`) VALUES
(89, 96, 4, '2025-06-27 03:29:36'),
(90, 97, 4, '2025-06-27 03:29:36'),
(91, 98, 4, '2025-06-27 03:29:36'),
(92, 99, 4, '2025-06-27 03:29:36'),
(93, 100, 4, '2025-06-27 03:29:36'),
(94, 101, 4, '2025-06-27 03:29:36'),
(95, 102, 4, '2025-06-27 03:29:36'),
(96, 103, 4, '2025-06-27 03:29:36'),
(97, 104, 4, '2025-06-27 03:29:36'),
(98, 105, 4, '2025-06-27 03:29:37'),
(99, 106, 4, '2025-06-27 03:29:37'),
(100, 107, 4, '2025-06-27 03:29:37'),
(101, 108, 4, '2025-06-27 03:29:37'),
(102, 109, 4, '2025-06-27 03:29:37'),
(103, 110, 4, '2025-06-27 03:29:37'),
(104, 111, 4, '2025-06-27 03:29:37'),
(105, 112, 4, '2025-06-27 03:29:37'),
(106, 113, 4, '2025-06-27 03:29:37'),
(107, 114, 4, '2025-06-27 03:29:37'),
(108, 115, 4, '2025-06-27 03:29:37'),
(109, 116, 4, '2025-06-27 03:29:37'),
(110, 117, 4, '2025-06-27 03:29:37'),
(111, 118, 4, '2025-06-27 03:29:37'),
(112, 119, 4, '2025-06-27 03:29:37'),
(113, 120, 4, '2025-06-27 03:29:37'),
(114, 121, 4, '2025-06-27 03:29:37'),
(115, 122, 4, '2025-06-27 03:29:38'),
(116, 123, 4, '2025-06-27 03:29:38'),
(117, 124, 4, '2025-06-27 03:29:38'),
(118, 125, 4, '2025-06-27 03:29:38'),
(119, 126, 4, '2025-06-27 03:29:38'),
(120, 127, 4, '2025-06-27 03:29:38'),
(121, 128, 4, '2025-06-27 03:29:38'),
(122, 129, 4, '2025-06-27 03:29:38'),
(123, 130, 4, '2025-06-27 03:29:38');

-- --------------------------------------------------------

--
-- Table structure for table `soal_quiz`
--

CREATE TABLE `soal_quiz` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `pertanyaan` text NOT NULL,
  `tipe` enum('pilihan_ganda','essay') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tugas`
--

CREATE TABLE `tugas` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `jenis_tugas_id` int(11) NOT NULL,
  `kelas_id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `batas_pengumpulan` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','guru','siswa') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', '2025-06-17 04:24:43'),
(37, 'prihatin', '$2y$10$G2PRl/deyHepyJAWOl9pK.l7zX2mUgZYDfS24.ej1qwcHQtMuFcuC', 'Prihatin Ningsih Sagala, S.Pd., M.Si', 'guru', '2025-06-27 03:19:16'),
(96, 'afta', '$2y$10$MOaMbIncjyQl75nv43pFIuztIftXclL4EJ9UNoDve11f4fjqOP5rW', 'Afta Geosasmita saragih', 'siswa', '2025-06-27 03:29:36'),
(97, 'aini', '$2y$10$qB443aV5H7WzVQfYmoOr.uE828BPAh0UH3usMNYkuY1rsWxfo6oXa', 'Aini Wardana', 'siswa', '2025-06-27 03:29:36'),
(98, 'azra', '$2y$10$VaZkNnqBqyguS3ZPZafXtOIp27hS0JxtmkhhDEM/i.TB5yRaX.WhS', 'Azra Khumairah', 'siswa', '2025-06-27 03:29:36'),
(99, 'cut', '$2y$10$SK0qj0SM1G4F5CK9AhLj1uAsULjdAfeCgnCkzeHjXy.c/cSHxzBgy', 'Cut Raini Andidi', 'siswa', '2025-06-27 03:29:36'),
(100, 'dwi', '$2y$10$AEj4GiPiYgW2Q9Q6AiZrhuTQLNbT7GQc3oBqWed/2ccnb0TLVwgNG', 'Dwi Ayu Febrianti', 'siswa', '2025-06-27 03:29:36'),
(101, 'eka', '$2y$10$9t8s2o9sc1sk6yDl6BfPxOTMVXej0f8llr0kz6BQ.1bR7pdmIOSNG', 'Eka Finanti Septiana Simamora', 'siswa', '2025-06-27 03:29:36'),
(102, 'engeli', '$2y$10$YqUBvcNwwNofzF.ACe/DuuHPirqY6tO.lxx0fthr9vxqY8bIlaBkK', 'Engeli Emmanuel BR Tambunan', 'siswa', '2025-06-27 03:29:36'),
(103, 'fertianus', '$2y$10$DpDMmYgNjM56p.SpEP2N0ufpn90fVv0hkV6TlRmH6UaXlNHaqi9ai', 'fertianus waruwe', 'siswa', '2025-06-27 03:29:36'),
(104, 'gebriel', '$2y$10$7D3nR5SXbWReE7ItKI0tOeZW/FSxndyrPILFB.Q4kYMb0Hn0UecPS', 'gebriel saron silaban', 'siswa', '2025-06-27 03:29:36'),
(105, 'gita', '$2y$10$xc8yFEylq6UyZTipIVGHne3EidT./Bk6vox0Lr5u3RREcWyRCS6f2', 'gita helena tarigan', 'siswa', '2025-06-27 03:29:37'),
(106, 'imelda', '$2y$10$Itp3XRy1wBPqfp39XuhQ3ekWMKG60l.xEQA5pKujdb5YUT1BLzMtS', 'imelda putri', 'siswa', '2025-06-27 03:29:37'),
(107, 'imel', '$2y$10$gVi3tw4go6G4JskbWD05WOBz7/.p96V2noJMZfrxNOMzPM4Zc.NFi', 'imel simanungkalit', 'siswa', '2025-06-27 03:29:37'),
(108, 'iren', '$2y$10$.RFb2rUFJ7ga1Eb.IiD.V.2WliEEpW3lN/WSq8Kzm/LMSPeXWJ2Ma', 'iren dwi adinda sitepu', 'siswa', '2025-06-27 03:29:37'),
(109, 'jesiska', '$2y$10$nZhvTyhjrIi0qS6DcbR1xuy/tRSYaIPwODag.UNWTqIRMv0eqG/Iq', 'jesiska anjelin siagian', 'siswa', '2025-06-27 03:29:37'),
(110, 'joel', '$2y$10$us9rBtdKnZRz7S.k.6H.nOXujM7Pgu3Jbc.2vZrkacF1KbnqrqJKC', 'joel shintong naibaho', 'siswa', '2025-06-27 03:29:37'),
(111, 'julisa', '$2y$10$sc1JRER16nvIxdtkwIBPbOAPiuxnFqMC64erD6GhDlUJzZ/wFRTKm', 'julisa ayu lestari', 'siswa', '2025-06-27 03:29:37'),
(112, 'marthin', '$2y$10$sIN2EZ.K7jVobkvHr1aHk.5inAhKcARGVqkPkcDWcc6AX38sjzKGC', 'marthin marbun', 'siswa', '2025-06-27 03:29:37'),
(113, 'mikhah', '$2y$10$rxB1QjQ7tGYcJ1Yc8wY6Ievn9cfGSrk1//oaEGKwNdWrd7y.yLcK6', 'mikhah adillah zendrato', 'siswa', '2025-06-27 03:29:37'),
(114, 'ndor', '$2y$10$nucHBIKB2W0Y.KRf.j.rf.Upjrc0cIHjJx4mjEnzNuYxL9JvXLfS.', 'ndor damayanti silalahi', 'siswa', '2025-06-27 03:29:37'),
(115, 'nurcahaya', '$2y$10$jG.4gcJOY4x2ysQu/1b3deZq8IN8NMJiQ1qgGXzgiXGmUPaCIQfRC', 'nurcahaya br zandrato', 'siswa', '2025-06-27 03:29:37'),
(116, 'novita', '$2y$10$uWzQ3oorp6orX7HYrSCZ/.2vrCo9x0NX6oziM/oe6s/B21x8CJZPG', 'novita sari maria br manullang', 'siswa', '2025-06-27 03:29:37'),
(117, 'putririzki', '$2y$10$BQHXLRVewUucL4kAnF3yteRd2Yd.07KfSRcx2VwvTN3kJUug7WEES', 'putri rizki', 'siswa', '2025-06-27 03:29:37'),
(118, 'putri', '$2y$10$gSsy3KfzoWev0Uaa222ptu9L0pajlS21SLcohhaGu.BJxxW0j5F9G', 'putri br tarigan', 'siswa', '2025-06-27 03:29:37'),
(119, 'raissya', '$2y$10$kx93PLMuhoopCst/37joceIGkCrWOURHxDiVqAqj88E5pcesJyFsW', 'raissya adhawina', 'siswa', '2025-06-27 03:29:37'),
(120, 'rival', '$2y$10$hbR7Pa7/UbJ11e6JCHeV3.4n/09/Q6Wpf4nQF.eIe5Fxi1LCVFK1m', 'rival ananda gisty', 'siswa', '2025-06-27 03:29:37'),
(121, 'ruth', '$2y$10$5Zp3V8fh8FoxnboOuo8ZQ.pELgYn7qCnY7Taq9nsYrgPxN5RQaBbC', 'ruth sahanaya manik', 'siswa', '2025-06-27 03:29:37'),
(122, 'shepia', '$2y$10$k6wnCbQULDs8WXOAsAe1deLXWbSMlikhQm87V.Mjyv0f7Y.mBUWBe', 'shepia anggraini', 'siswa', '2025-06-27 03:29:38'),
(123, 'siti', '$2y$10$qckafrHYP/ysAucNvYuCwuIKSZrUzpXoInrgLjYwJMWFLeyXIVHRu', 'siti khafifah f', 'siswa', '2025-06-27 03:29:38'),
(124, 'stepani', '$2y$10$B19Hy4GY1fT9qBEsPf9Ns.OD3fz39zCDg8gzfHh8fjA64x05ZGlYq', 'stepani theresa vania tampubolon', 'siswa', '2025-06-27 03:29:38'),
(125, 'stavania', '$2y$10$..VOOyZKiEuxsMPpPqIszeVIqFp6clln83VxawpJ1OMLCbJE5RAiC', 'stavania sri debbye br simbolon', 'siswa', '2025-06-27 03:29:38'),
(126, 'sutan', '$2y$10$.Wr.msKDwkxy8u0wglRydu1/vI2TgBOQHkJLfj2oIC7CeKy6mAR6m', 'sutan ismail akbar rafsanjani lubis', 'siswa', '2025-06-27 03:29:38'),
(127, 'vico', '$2y$10$d9KHLe9IJOPw4sphlhjtZe98a4WEsNLxndv8XCkvGU1TcnzB6PKB2', 'vico putra sidauruk', 'siswa', '2025-06-27 03:29:38'),
(128, 'yoga', '$2y$10$aGYY9EWOrHnAJzxitbPbvObdHTbRcV.qtkh60DNsalIQZpkMOlK4i', 'yoga aulia saputra', 'siswa', '2025-06-27 03:29:38'),
(129, 'angelikaraibaho', '$2y$10$w0Len6KaJxAnbUJJfceVY.muxI/8UozkVwVhNYCFsg9cAnDNXqa3y', 'angelika naibaho', 'siswa', '2025-06-27 03:29:38'),
(130, 'angelika', '$2y$10$Vkibb6ScOfQ0MiHcJxZFJ.MMHxTgDFQ.ngpAur/S2R2jRZqRL56a6', 'angelika dameria sitinjak', 'siswa', '2025-06-27 03:29:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `guru_kelas`
--
ALTER TABLE `guru_kelas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guru_id` (`guru_id`),
  ADD KEY `kelas_id` (`kelas_id`);

--
-- Indexes for table `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `soal_id` (`soal_id`);

--
-- Indexes for table `jenis_tugas`
--
ALTER TABLE `jenis_tugas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `materi`
--
ALTER TABLE `materi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guru_id` (`guru_id`),
  ADD KEY `kelas_id` (`kelas_id`);

--
-- Indexes for table `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tugas_id` (`tugas_id`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indexes for table `pilihan_jawaban`
--
ALTER TABLE `pilihan_jawaban`
  ADD PRIMARY KEY (`id`),
  ADD KEY `soal_id` (`soal_id`);

--
-- Indexes for table `quiz`
--
ALTER TABLE `quiz`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indexes for table `siswa_kelas`
--
ALTER TABLE `siswa_kelas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `kelas_id` (`kelas_id`);

--
-- Indexes for table `soal_quiz`
--
ALTER TABLE `soal_quiz`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `tugas`
--
ALTER TABLE `tugas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jenis_tugas_id` (`jenis_tugas_id`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `guru_kelas`
--
ALTER TABLE `guru_kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=213;

--
-- AUTO_INCREMENT for table `jenis_tugas`
--
ALTER TABLE `jenis_tugas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `materi`
--
ALTER TABLE `materi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `pilihan_jawaban`
--
ALTER TABLE `pilihan_jawaban`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `quiz`
--
ALTER TABLE `quiz`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `siswa_kelas`
--
ALTER TABLE `siswa_kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT for table `soal_quiz`
--
ALTER TABLE `soal_quiz`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tugas`
--
ALTER TABLE `tugas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `guru_kelas`
--
ALTER TABLE `guru_kelas`
  ADD CONSTRAINT `guru_kelas_ibfk_1` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guru_kelas_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  ADD CONSTRAINT `jawaban_siswa_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jawaban_siswa_ibfk_2` FOREIGN KEY (`soal_id`) REFERENCES `soal_quiz` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `materi`
--
ALTER TABLE `materi`
  ADD CONSTRAINT `materi_ibfk_1` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `materi_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  ADD CONSTRAINT `pengumpulan_tugas_ibfk_1` FOREIGN KEY (`tugas_id`) REFERENCES `tugas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pengumpulan_tugas_ibfk_2` FOREIGN KEY (`siswa_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pilihan_jawaban`
--
ALTER TABLE `pilihan_jawaban`
  ADD CONSTRAINT `pilihan_jawaban_ibfk_1` FOREIGN KEY (`soal_id`) REFERENCES `soal_quiz` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz`
--
ALTER TABLE `quiz`
  ADD CONSTRAINT `quiz_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_ibfk_2` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `siswa_kelas`
--
ALTER TABLE `siswa_kelas`
  ADD CONSTRAINT `siswa_kelas_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `siswa_kelas_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `soal_quiz`
--
ALTER TABLE `soal_quiz`
  ADD CONSTRAINT `soal_quiz_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quiz` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tugas`
--
ALTER TABLE `tugas`
  ADD CONSTRAINT `tugas_ibfk_1` FOREIGN KEY (`jenis_tugas_id`) REFERENCES `jenis_tugas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tugas_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tugas_ibfk_3` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 30, 2026 at 06:13 AM
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
-- Database: `sistem_diklat_satpam`
--

-- --------------------------------------------------------

--
-- Table structure for table `akun`
--

CREATE TABLE `akun` (
  `id_akun` int(11) NOT NULL,
  `username` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('siswa','admin','publikasi','kepala_keamanan','polda','ceo') NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `akun`
--

INSERT INTO `akun` (`id_akun`, `username`, `password`, `role`, `dibuat_pada`) VALUES
(1, 'siswa01', 'Gemilang1', 'siswa', '2026-03-27 17:40:04'),
(2, 'admin01', 'Gemilang1', 'admin', '2026-03-27 17:40:04'),
(3, 'publikasi01', 'Gemilang1', 'publikasi', '2026-03-27 17:40:04'),
(4, 'polda01', 'Gemilang1', 'polda', '2026-03-27 17:40:04'),
(5, 'kepala01', 'Gemilang1', 'kepala_keamanan', '2026-03-27 17:40:04'),
(6, 'ceo01', 'Gemilang1', 'ceo', '2026-03-27 17:40:04'),
(7, 'siswa2026P1001', 'Jawa01', 'siswa', '2026-03-27 17:40:04');

-- --------------------------------------------------------

--
-- Table structure for table `dokumen_pendaftaran`
--

CREATE TABLE `dokumen_pendaftaran` (
  `id_dokumen` int(11) NOT NULL,
  `jenis` enum('ktp','ijazah','kk','pembayaran','skck','surat_kesehatan') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status_verifikasi` enum('pending','valid','revisi') NOT NULL,
  `catatan_admin` text DEFAULT NULL,
  `tgl_upload` datetime NOT NULL,
  `tgl_revisi` datetime DEFAULT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dokumen_pendaftaran`
--

INSERT INTO `dokumen_pendaftaran` (`id_dokumen`, `jenis`, `file_path`, `status_verifikasi`, `catatan_admin`, `tgl_upload`, `tgl_revisi`, `id_siswa`, `created_at`) VALUES
(1, 'ktp', 'uploads/1/ktp.jpg', 'valid', NULL, '2026-02-20 14:10:42', NULL, 1, '2026-02-20 07:28:36'),
(2, 'ijazah', 'uploads/1/ijazah.jpg', 'valid', NULL, '2026-02-20 14:10:42', NULL, 1, '2026-02-20 07:28:36'),
(3, 'kk', 'uploads/1/kk.jpg', 'valid', NULL, '2026-02-20 14:10:42', NULL, 1, '2026-02-20 07:28:36'),
(4, 'pembayaran', 'uploads/1/bayar.jpg', 'pending', 'Menunggu konfirmasi', '2026-02-20 14:10:42', NULL, 1, '2026-02-20 07:28:36'),
(9, 'ktp', 'uploads/4/ktp.pdf', 'valid', '', '2026-03-26 01:05:08', NULL, 4, '2026-03-26 00:05:08'),
(10, 'ijazah', 'uploads/4/ijazah.pdf', 'valid', '', '2026-03-26 01:05:08', NULL, 4, '2026-03-26 00:05:08'),
(11, 'skck', 'uploads/4/skck.pdf', 'valid', '', '2026-03-26 01:05:08', NULL, 4, '2026-03-26 00:05:08'),
(12, 'pembayaran', 'uploads/4/pembayaran.pdf', 'valid', '', '2026-03-26 01:05:08', NULL, 4, '2026-03-26 00:05:08');

-- --------------------------------------------------------

--
-- Table structure for table `evaluasi`
--

CREATE TABLE `evaluasi` (
  `id_evaluasi` int(11) NOT NULL,
  `nilai_teori` decimal(5,2) NOT NULL,
  `nilai_fisik` decimal(5,2) NOT NULL,
  `nilai_disiplin` decimal(5,2) NOT NULL,
  `nilai_praktik` decimal(5,2) NOT NULL,
  `rata_rata` decimal(5,2) NOT NULL,
  `hasil` enum('lulus','tidak_lulus') NOT NULL,
  `catatan` text DEFAULT NULL,
  `tgl_input` date NOT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `id_periode` int(11) DEFAULT NULL,
  `dikonfirmasi_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = belum dikonfirmasi, 1 = sudah dikonfirmasi admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluasi`
--

INSERT INTO `evaluasi` (`id_evaluasi`, `nilai_teori`, `nilai_fisik`, `nilai_disiplin`, `nilai_praktik`, `rata_rata`, `hasil`, `catatan`, `tgl_input`, `id_siswa`, `id_periode`, `dikonfirmasi_admin`) VALUES
(1, 85.00, 80.00, 90.00, 0.00, 85.00, 'lulus', NULL, '2026-03-18', 1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `informasi_diklat`
--

CREATE TABLE `informasi_diklat` (
  `id_info` int(11) NOT NULL,
  `id_periode` int(11) NOT NULL,
  `brosur_path` varchar(255) DEFAULT NULL,
  `tempat` varchar(150) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `informasi_diklat`
--

INSERT INTO `informasi_diklat` (`id_info`, `id_periode`, `brosur_path`, `tempat`, `dibuat_pada`, `diperbarui_pada`) VALUES
(1, 1, 'uploads/brosur_diklat1.jpg', 'Pusdiklat Bandung', '2026-03-27 17:43:38', '2026-03-27 17:43:38');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_diklat`
--

CREATE TABLE `jadwal_diklat` (
  `id_jadwal` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `kegiatan` varchar(150) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `id_periode` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jadwal_diklat`
--

INSERT INTO `jadwal_diklat` (`id_jadwal`, `tanggal`, `kegiatan`, `keterangan`, `id_periode`) VALUES
(1, '2026-01-12', 'Pembukaan Diklat', 'Upacara pembukaan', 1),
(2, '2026-01-13', 'Latihan Fisik', 'Lapangan utama', 1),
(3, '2026-01-15', 'Materi Hukum', 'Ruang kelas', 1);

-- --------------------------------------------------------

--
-- Table structure for table `laporan`
--

CREATE TABLE `laporan` (
  `id_laporan` int(11) NOT NULL,
  `tgl_generate` datetime NOT NULL,
  `dikonfirmasi_kepala` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = belum, 1 = sudah dikonfirmasi kepala keamanan',
  `tgl_konfirmasi_kepala` datetime DEFAULT NULL,
  `dilihat_ceo` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = belum dilihat CEO, 1 = sudah dilihat',
  `tgl_dilihat_ceo` datetime DEFAULT NULL,
  `id_periode` int(11) DEFAULT NULL,
  `file_laporan_polda` varchar(255) DEFAULT NULL COMMENT 'File laporan kegiatan dari Polda',
  `file_laporan_penilaian` varchar(255) DEFAULT NULL COMMENT 'File laporan penilaian dari admin',
  `file_surat_pernyataan` varchar(255) DEFAULT NULL COMMENT 'Surat pernyataan selesai dari kepala keamanan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laporan`
--

INSERT INTO `laporan` (`id_laporan`, `tgl_generate`, `dikonfirmasi_kepala`, `tgl_konfirmasi_kepala`, `dilihat_ceo`, `tgl_dilihat_ceo`, `id_periode`, `file_laporan_polda`, `file_laporan_penilaian`, `file_surat_pernyataan`) VALUES
(1, '2026-03-25 00:00:00', 0, NULL, 0, NULL, 1, NULL, NULL, NULL),
(2, '2026-03-28 00:48:39', 0, NULL, 0, NULL, 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `laporan_polda`
--

CREATE TABLE `laporan_polda` (
  `id_laporan_polda` int(11) NOT NULL,
  `id_periode` int(11) NOT NULL,
  `id_akun_polda` int(11) NOT NULL,
  `pengeluaran` bigint(20) NOT NULL DEFAULT 0,
  `pemasukan` bigint(20) NOT NULL DEFAULT 0,
  `deskripsi_kegiatan` text DEFAULT NULL,
  `file_laporan` varchar(255) DEFAULT NULL COMMENT 'Upload file PDF laporan dari Polda',
  `tgl_input` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id_notifikasi` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `jenis` enum('revisi','terverifikasi','hasil_nilai') NOT NULL,
  `pesan` text NOT NULL,
  `status_kirim` enum('terkirim','gagal') NOT NULL DEFAULT 'terkirim',
  `tgl_kirim` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `periode_diklat`
--

CREATE TABLE `periode_diklat` (
  `id_periode` int(11) NOT NULL,
  `tahun` year(4) NOT NULL,
  `gelombang` tinyint(4) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `biaya` int(11) NOT NULL,
  `lokasi_spesifik` varchar(150) NOT NULL,
  `lokasi_fasilitas` varchar(200) DEFAULT NULL COMMENT 'Lokasi pengambilan fasilitas diklat',
  `fasilitas` text NOT NULL,
  `info_kebutuhan` text DEFAULT NULL COMMENT 'Informasi barang/kebutuhan yang perlu dibawa peserta',
  `batas_verifikasi` date DEFAULT NULL COMMENT 'Batas waktu verifikasi admin — setelah ini status otomatis jadi peserta',
  `status` enum('pendaftaran','berjalan','selesai') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `periode_diklat`
--

INSERT INTO `periode_diklat` (`id_periode`, `tahun`, `gelombang`, `tanggal_mulai`, `tanggal_selesai`, `biaya`, `lokasi_spesifik`, `lokasi_fasilitas`, `fasilitas`, `info_kebutuhan`, `batas_verifikasi`, `status`) VALUES
(1, '2026', 1, '2026-01-10', '2026-03-20', 3500000, 'Bandung', NULL, 'Seragam, Modul, Konsumsi', NULL, '2026-02-28', 'berjalan'),
(2, '2026', 2, '2026-04-10', '2026-06-20', 3500000, 'Bandung', NULL, 'Seragam, Modul, Konsumsi', NULL, '2026-05-15', 'pendaftaran');

-- --------------------------------------------------------

--
-- Table structure for table `peserta_periode`
--

CREATE TABLE `peserta_periode` (
  `id_peserta_periode` int(11) NOT NULL,
  `tanggal_terima` date NOT NULL,
  `id_peserta` int(11) DEFAULT NULL,
  `id_periode` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `peserta_periode`
--

INSERT INTO `peserta_periode` (`id_peserta_periode`, `tanggal_terima`, `id_peserta`, `id_periode`, `created_at`) VALUES
(1, '2026-01-05', 1, 1, '2026-02-20 07:28:36');

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id_peserta` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `no_telp` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `tgl_lahir` date NOT NULL,
  `agama` varchar(20) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `status` enum('calon','terverifikasi','peserta','lulus','tidak_lulus') DEFAULT NULL,
  `batas_revisi` date DEFAULT NULL,
  `id_akun` int(11) DEFAULT NULL,
  `tinggi_badan` smallint(6) NOT NULL,
  `berat_badan` smallint(6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`id_peserta`, `nama`, `alamat`, `no_telp`, `email`, `tgl_lahir`, `agama`, `jenis_kelamin`, `status`, `batas_revisi`, `id_akun`, `tinggi_badan`, `berat_badan`, `created_at`) VALUES
(1, 'Budi Santoso', 'Bandung', '08123456789', 'budi@gmail.com', '2002-05-12', 'Islam', 'L', 'peserta', NULL, 1, 170, 65, '2026-02-20 07:28:36'),
(2, 'Andi Wijaya', 'Garut', '08222222222', 'andi@gmail.com', '2001-02-02', 'Islam', 'L', 'terverifikasi', NULL, NULL, 168, 60, '2026-02-20 07:28:36'),
(4, 'Shofa Azmy', 'Kudus', '087735215545', 'shofaazmy27@gmail.com', '2006-02-16', 'Islam', 'L', 'peserta', NULL, 7, 170, 60, '2026-03-26 00:05:08');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `akun`
--
ALTER TABLE `akun`
  ADD PRIMARY KEY (`id_akun`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `dokumen_pendaftaran`
--
ALTER TABLE `dokumen_pendaftaran`
  ADD PRIMARY KEY (`id_dokumen`),
  ADD UNIQUE KEY `unique_dokumen` (`id_siswa`,`jenis`);

--
-- Indexes for table `evaluasi`
--
ALTER TABLE `evaluasi`
  ADD PRIMARY KEY (`id_evaluasi`),
  ADD UNIQUE KEY `unique_evaluasi` (`id_siswa`),
  ADD KEY `fk_evaluasi_periode` (`id_periode`);

--
-- Indexes for table `informasi_diklat`
--
ALTER TABLE `informasi_diklat`
  ADD PRIMARY KEY (`id_info`),
  ADD UNIQUE KEY `unique_info_periode` (`id_periode`);

--
-- Indexes for table `jadwal_diklat`
--
ALTER TABLE `jadwal_diklat`
  ADD PRIMARY KEY (`id_jadwal`),
  ADD UNIQUE KEY `unique_jadwal` (`id_periode`,`tanggal`,`kegiatan`);

--
-- Indexes for table `laporan`
--
ALTER TABLE `laporan`
  ADD PRIMARY KEY (`id_laporan`),
  ADD KEY `fk_laporan_periode` (`id_periode`);

--
-- Indexes for table `laporan_polda`
--
ALTER TABLE `laporan_polda`
  ADD PRIMARY KEY (`id_laporan_polda`),
  ADD UNIQUE KEY `unique_laporan_polda_periode` (`id_periode`),
  ADD KEY `fk_laporanpolda_akun` (`id_akun_polda`);

--
-- Indexes for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id_notifikasi`),
  ADD KEY `fk_notifikasi_siswa` (`id_siswa`);

--
-- Indexes for table `periode_diklat`
--
ALTER TABLE `periode_diklat`
  ADD PRIMARY KEY (`id_periode`);

--
-- Indexes for table `peserta_periode`
--
ALTER TABLE `peserta_periode`
  ADD PRIMARY KEY (`id_peserta_periode`),
  ADD UNIQUE KEY `unique_peserta_periode` (`id_peserta`,`id_periode`),
  ADD KEY `fk_pp_periode` (`id_periode`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id_peserta`),
  ADD UNIQUE KEY `id_akun` (`id_akun`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `akun`
--
ALTER TABLE `akun`
  MODIFY `id_akun` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `dokumen_pendaftaran`
--
ALTER TABLE `dokumen_pendaftaran`
  MODIFY `id_dokumen` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `evaluasi`
--
ALTER TABLE `evaluasi`
  MODIFY `id_evaluasi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `informasi_diklat`
--
ALTER TABLE `informasi_diklat`
  MODIFY `id_info` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `jadwal_diklat`
--
ALTER TABLE `jadwal_diklat`
  MODIFY `id_jadwal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `laporan`
--
ALTER TABLE `laporan`
  MODIFY `id_laporan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `laporan_polda`
--
ALTER TABLE `laporan_polda`
  MODIFY `id_laporan_polda` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id_notifikasi` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `periode_diklat`
--
ALTER TABLE `periode_diklat`
  MODIFY `id_periode` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `peserta_periode`
--
ALTER TABLE `peserta_periode`
  MODIFY `id_peserta_periode` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id_peserta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dokumen_pendaftaran`
--
ALTER TABLE `dokumen_pendaftaran`
  ADD CONSTRAINT `fk_dokumen_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_peserta`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `evaluasi`
--
ALTER TABLE `evaluasi`
  ADD CONSTRAINT `fk_evaluasi_periode` FOREIGN KEY (`id_periode`) REFERENCES `periode_diklat` (`id_periode`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evaluasi_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_peserta`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `informasi_diklat`
--
ALTER TABLE `informasi_diklat`
  ADD CONSTRAINT `informasi_diklat_ibfk_1` FOREIGN KEY (`id_periode`) REFERENCES `periode_diklat` (`id_periode`) ON DELETE CASCADE;

--
-- Constraints for table `jadwal_diklat`
--
ALTER TABLE `jadwal_diklat`
  ADD CONSTRAINT `fk_jadwal_periode` FOREIGN KEY (`id_periode`) REFERENCES `periode_diklat` (`id_periode`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `laporan`
--
ALTER TABLE `laporan`
  ADD CONSTRAINT `fk_laporan_periode` FOREIGN KEY (`id_periode`) REFERENCES `periode_diklat` (`id_periode`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `laporan_polda`
--
ALTER TABLE `laporan_polda`
  ADD CONSTRAINT `fk_laporanpolda_akun` FOREIGN KEY (`id_akun_polda`) REFERENCES `akun` (`id_akun`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_laporanpolda_periode` FOREIGN KEY (`id_periode`) REFERENCES `periode_diklat` (`id_periode`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `fk_notifikasi_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_peserta`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `peserta_periode`
--
ALTER TABLE `peserta_periode`
  ADD CONSTRAINT `fk_pp_periode` FOREIGN KEY (`id_periode`) REFERENCES `periode_diklat` (`id_periode`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pp_peserta` FOREIGN KEY (`id_peserta`) REFERENCES `siswa` (`id_peserta`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `fk_siswa_akun` FOREIGN KEY (`id_akun`) REFERENCES `akun` (`id_akun`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

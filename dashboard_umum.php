<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diklat Satpam Profesional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/dashboard_umum.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="dashboard_umum-page">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <div class="container">

    <!-- Logo -->
    <a class="navbar-brand d-flex align-items-center" href="dashboard_umum.php">
      <img src="img/logo.png" alt="Logo" class="logo-navbar">
      <span class="fw-bold ms-2">Gemilang</span>
    </a>

    <!-- Button Hamburger -->
    <button class="navbar-toggler" type="button" 
            data-bs-toggle="collapse" 
            data-bs-target="#navmenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Menu yang akan muncul -->
    <div class="collapse navbar-collapse" id="navmenu">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="dashboard_umum.php">Beranda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="publikasi.php">Publikasi</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-warning" href="login.php">Login</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero d-flex align-items-center text-center text-white">
    <img src="img/home.jpg" alt="Hero Image" class="hero-img">
    <div class="container">
        <h1 class="fw-bold text-white">Pusat Pelatihan Satpam Profesional & Bersertifikat</h1>
        <p class="lead mt-3 text-warning">
            Siapkan karier keamanan Anda bersama pelatihan resmi dan terstandarisasi.
        </p>
        <a href="daftar.php" class="btn btn-warning btn-lg mt-4 px-4">Daftar Sekarang</a>
    </div>
</section>

<!-- Statistik -->
<section class="py-5 stats">
    <div class="container text-center">
        <div class="row g-4">

            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <i class="bi bi-people-fill stat-icon"></i>
                    <h2 class="fw-bold text-primary">1000+</h2>
                    <p>Alumni Lulus</p>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <i class="bi bi-calendar-check-fill stat-icon"></i>
                    <h2 class="fw-bold text-primary">25+</h2>
                    <p>Angkatan</p>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <i class="bi bi-person-badge-fill stat-icon"></i>
                    <h2 class="fw-bold text-primary">7+</h2>
                    <p>Instruktur Profesional</p>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <i class="bi bi-shield-check stat-icon"></i>
                    <h2 class="fw-bold text-primary">100%</h2>
                    <p>Legal & Resmi</p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Keunggulan -->
<section class="py-5 bg-light">
    <div class="container text-center">
        <h2 class="fw-bold mb-5">Mengapa Memilih Kami?</h2>
        <div class="row g-4">

            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <i class="bi bi-award-fill fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold">Sertifikat Resmi</h5>
                    <p>Diakui dan sesuai standar regulasi kepolisian.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <i class="bi bi-journal-check fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold">Pelatihan Terstruktur</h5>
                    <p>Kurikulum teori & praktik sesuai prosedur nasional.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4 h-100">
                    <i class="bi bi-briefcase-fill fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold">Penyaluran Kerja</h5>
                    <p>Terhubung dengan berbagai perusahaan mitra.</p>
                </div>
            </div>

        </div>
    </div>
</section>

<section class="py-5 testimonial-section text-white">
    <div class="container">
        <h2 class="fw-bold text-center mb-5 text-black">Testimoni Alumni</h2>

        <div class="row g-4">

            <div class="col-md-4">
                <div class="testimonial-card p-4 h-100">
                    <i class="bi bi-quote fs-1 mb-3"></i>
                    <p>
                        Setelah lulus dari pelatihan ini, saya langsung diterima bekerja di perusahaan swasta nasional.
                    </p>
                    <h6 class="fw-bold mt-3 mb-0">Ahmad Rizky</h6>
                    <small>Alumni 2023</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="testimonial-card p-4 h-100">
                    <i class="bi bi-quote fs-1 mb-3"></i>
                    <p>
                        Instruktur sangat profesional dan materi pelatihannya lengkap serta terstruktur.
                    </p>
                    <h6 class="fw-bold mt-3 mb-0">Budi Santoso</h6>
                    <small>Alumni 2022</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="testimonial-card p-4 h-100">
                    <i class="bi bi-quote fs-1 mb-3"></i>
                    <p>
                        Proses sertifikasi jelas dan resmi. Saya merasa lebih percaya diri saat melamar kerja.
                    </p>
                    <h6 class="fw-bold mt-3 mb-0">Dimas Pratama</h6>
                    <small>Alumni 2024</small>
                </div>
            </div>

        </div>
    </div>
</section>

<section class="py-5 partnership-section text-white">
    <div class="container text-center">

        <h2 class="fw-bold mb-4">Legalitas & Kemitraan</h2>

        <p class="mb-5 text-light">
            Lembaga kami telah memiliki izin operasional resmi dan bekerja sama dengan berbagai perusahaan nasional.
        </p>

        <div class="row justify-content-center align-items-center g-4">

            <div class="col-6 col-md-3">
                <img src="img/cafe.png" class="img-fluid partner-logo" alt="Partner 1">
            </div>

            <div class="col-6 col-md-3">
                <img src="img/manufaktur.jpg" class="img-fluid partner-logo" alt="Partner 2">
            </div>

            <div class="col-6 col-md-3">
                <img src="img/hotel.png" class="img-fluid partner-logo" alt="Partner 3">
            </div>

        </div>

    </div>
</section>

<!-- CTA -->
<section class="cta-section text-white text-center d-flex align-items-center">
    <div class="container">
        <h2 class="fw-bold mb-3">Siap Memulai Karier Anda?</h2>
        <p class="mb-4">Daftar sekarang dan jadilah bagian dari tenaga keamanan profesional.</p>
        <a href="#" class="btn btn-warning btn-lg px-4">Daftar Sekarang</a>
    </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-white text-center py-3">
    <p class="mb-0">© 2026 Sistem Informasi Manajemen Diklat Satpam</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

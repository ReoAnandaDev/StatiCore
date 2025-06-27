<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Redirect to dashboard if already logged in
if ($auth->isLoggedIn()) {
    header("Location: /Staticore/views/" . $_SESSION['role'] . "/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StatiCore - Platform Pembelajaran Digital Statistika</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Open+Sans&display=swap"
        rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #2c5282, #4299e1);
            --secondary-gradient: linear-gradient(135deg, #4299e1, #63b3ed);
            --accent-gradient: linear-gradient(135deg, #f6ad55, #f6e05e);
            --card-hover-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            --accent-color: #f6ad55;
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --bg-light: #f7fafc;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            scroll-behavior: smooth;
            overflow-x: hidden;
            color: var(--text-primary);
            background-color: var(--bg-light);
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Inter', sans-serif;
            font-weight: 700;
        }

        /* Modern Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .navbar.scrolled {
            padding: 0.5rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary) !important;
        }

        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover img {
            transform: scale(1.05);
        }

        .nav-link {
            color: var(--text-secondary) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary-gradient);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-link.active {
            color: var(--text-primary) !important;
        }

        .nav-link.active::after {
            width: 100%;
        }

        /* Hero Section */
        .hero-section {
            background: var(--primary-gradient);
            color: white;
            padding: 180px 0 150px;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.1;
        }

        .hero-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            display: inline-block;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        /* Feature Cards */
        .feature-card {
            border: none;
            border-radius: 20px;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            overflow: hidden;
            position: relative;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--accent-gradient);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .feature-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            font-size: 2.5rem;
            background: var(--secondary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
        }

        /* Team Section */
        .team-member {
            position: relative;
            margin-bottom: 2rem;
        }

        .team-img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 20px;
            transition: all 0.4s ease;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .team-member:hover .team-img {
            transform: scale(1.05);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .team-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.5rem;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
            color: white;
            border-radius: 0 0 20px 20px;
            transform: translateY(100%);
            transition: all 0.4s ease;
        }

        .team-member:hover .team-info {
            transform: translateY(0);
        }

        .social-links {
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            display: flex;
            justify-content: center;
            gap: 1rem;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease;
        }

        .team-member:hover .social-links {
            opacity: 1;
            transform: translateY(0);
        }

        /* Contact Section */
        .contact-info-wrapper {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            height: 100%;
            transition: all 0.3s ease;
        }

        .contact-info-wrapper:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .contact-info-list {
            margin-bottom: 2rem;
        }

        .contact-info-item {
            padding: 1rem;
            border-radius: 15px;
            background: var(--bg-light);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .contact-info-item:hover {
            transform: translateX(10px);
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .contact-icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .contact-info-item:hover .contact-icon-wrapper {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(44, 82, 130, 0.2);
        }

        .contact-info-content {
            margin-left: 1rem;
        }

        .contact-info-content h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .contact-info-content p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.95rem;
        }

        .social-links {
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .social-links h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .social-icon {
            width: 45px;
            height: 45px;
            border-radius: 15px;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }

        .social-icon:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(44, 82, 130, 0.2);
            color: white;
        }

        /* Buttons */
        .btn-primary-gradient {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-primary-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transition: width 0.3s ease;
            z-index: -1;
        }

        .btn-primary-gradient:hover::before {
            width: 100%;
        }

        .btn-outline-light {
            border: 2px solid rgba(255, 255, 255, 0.5);
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.4s ease;
        }

        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
            transform: translateY(-3px);
        }

        /* Section Titles */
        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 3rem;
            font-size: 2.5rem;
            font-weight: 800;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--accent-gradient);
            border-radius: 2px;
        }

        /* Footer */
        footer {
            background: #1a202c;
            padding: 80px 0 30px;
            position: relative;
            color: white;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--accent-gradient);
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 1rem;
        }

        .footer-links a {
            color: #a0aec0;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }

        /* Floating Button */
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--accent-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(246, 173, 85, 0.3);
            transition: all 0.4s ease;
            z-index: 1000;
            opacity: 0;
            transform: translateY(20px);
        }

        .floating-btn.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .floating-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(246, 173, 85, 0.4);
            color: white;
        }

        /* Modern Section Transitions */
        .section-fade {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease;
        }

        .section-fade.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .form-floating>.form-control,
        .form-floating>.form-select {
            height: calc(3.5rem + 2px);
            line-height: 1.25;
        }

        .form-floating>textarea.form-control {
            height: 150px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #4299e1;
            box-shadow: 0 0 0 0.25rem rgba(66, 153, 225, 0.25);
        }

        .map-wrapper {
            transition: all 0.3s ease;
        }

        .map-wrapper:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
        }

        /* About Section Styles */
        .feature-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto;
            transition: all 0.3s ease;
        }

        .feature-icon-wrapper:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(44, 82, 130, 0.2);
        }

        .card {
            transition: all 0.3s ease;
            border-radius: 15px;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
        }

        .text-muted li {
            margin-bottom: 0.5rem;
        }

        .text-muted li:last-child {
            margin-bottom: 0;
        }

        /* Vision & Mission Styles */
        .vision-mission-wrapper {
            position: relative;
            padding: 3rem 0;
            background: linear-gradient(135deg, rgba(66, 153, 225, 0.05), rgba(44, 82, 130, 0.05));
            border-radius: 20px;
            overflow: hidden;
        }

        .vision-mission-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" fill="rgba(44, 82, 130, 0.05)"/></svg>');
            opacity: 0.5;
        }

        .vision-mission-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            height: 100%;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .vision-mission-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .vision-mission-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .vision-mission-card:hover::before {
            transform: scaleX(1);
        }

        .vision-mission-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.4s ease;
        }

        .vision-mission-card:hover .vision-mission-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 10px 20px rgba(44, 82, 130, 0.2);
        }

        .vision-mission-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            position: relative;
            padding-bottom: 1rem;
        }

        .vision-mission-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--accent-gradient);
            border-radius: 2px;
        }

        .vision-mission-content {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.8;
        }

        .mission-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .mission-list li {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .mission-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #4299e1;
            font-weight: bold;
        }

        .mission-list li:last-child {
            margin-bottom: 0;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="assets/logo/logo.png" alt="Logo"> StatiCore
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active" href="#about">Tentang</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Fitur</a></li>
                    <li class="nav-item"><a class="nav-link" href="#program">Program</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Kontak</a></li>
                </ul>
            </div>
            <div class="d-flex">
                <a href="/StatiCore/login.php" class="btn btn-primary-gradient">
                    <i class="fas fa-sign-in-alt me-2"></i> Masuk
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container hero-content">
            <span class="hero-badge">
                <i class="fas fa-chart-line me-2"></i>Platform Pembelajaran Digital Statistika
            </span>
            <h1 class="hero-title">Selamat Datang di StatiCore</h1>
            <p class="hero-subtitle">Platform pembelajaran digital untuk mahasiswa program studi Statistika.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="/StatiCore/login.php" class="btn btn-primary-gradient btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Masuk ke Platform
                </a>
                <a href="#about" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-info-circle me-2"></i>Pelajari Lebih Lanjut
                </a>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5 section-fade">
        <div class="container">
            <div class="row align-items-center mb-5">
                <div class="col-md-6" data-aos="fade-right">
                    <h2 class="mb-3">Tentang Kami</h2>
                    <p class="text-muted">
                        StatiCore adalah platform pembelajaran digital yang dirancang khusus untuk mahasiswa program
                        studi Statistika.
                        Kami menyediakan berbagai fitur seperti upload tugas, quiz interaktif, dan materi pembelajaran
                        yang dapat diakses kapan saja.
                        Dengan fokus pada pengembangan kompetensi statistika, kami membantu mahasiswa dalam proses
                        belajar mengajar secara efektif dan efisien.
                    </p>
                </div>
                <div class="col-md-6 text-center" data-aos="fade-left">
                    <img src="assets/images/ilustrasi.png" alt="Ilustrasi Statistika"
                        class="img-fluid rounded shadow-sm">
                </div>
            </div>

            <!-- Vision & Mission -->
            <div class="vision-mission-wrapper mb-5">
                <div class="container">
                    <div class="row g-4">
                        <div class="col-md-6" data-aos="fade-up">
                            <div class="vision-mission-card">
                                <div class="vision-mission-icon">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <h3 class="vision-mission-title">Visi</h3>
                                <p class="vision-mission-content">
                                    Menjadi platform pembelajaran digital terdepan dalam bidang statistika yang
                                    mendukung pengembangan kompetensi mahasiswa melalui teknologi modern dan metode
                                    pembelajaran yang inovatif.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                            <div class="vision-mission-card">
                                <div class="vision-mission-icon">
                                    <i class="fas fa-bullseye"></i>
                                </div>
                                <h3 class="vision-mission-title">Misi</h3>
                                <ul class="mission-list">
                                    <li>Menyediakan platform pembelajaran yang mudah diakses dan user-friendly untuk
                                        mendukung proses belajar mengajar yang efektif</li>
                                    <li>Mengembangkan konten pembelajaran yang berkualitas dan selalu up-to-date sesuai
                                        dengan perkembangan ilmu statistika</li>
                                    <li>Mendukung proses pembelajaran melalui fitur interaktif dan kolaboratif antar
                                        mahasiswa dan dosen</li>
                                    <li>Mengintegrasikan teknologi modern dalam proses pembelajaran untuk meningkatkan
                                        kualitas pendidikan statistika</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Features -->
            <div class="row g-4">
                <div class="col-12 text-center mb-4" data-aos="fade-up">
                    <h3>Keunggulan Kami</h3>
                </div>
                <div class="col-md-3" data-aos="fade-up">
                    <div class="text-center">
                        <div class="feature-icon-wrapper mb-3">
                            <i class="fas fa-book-reader"></i>
                        </div>
                        <h5>Materi Lengkap</h5>
                        <p class="text-muted">Konten pembelajaran yang komprehensif dan terstruktur untuk setiap mata
                            kuliah statistika</p>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="text-center">
                        <div class="feature-icon-wrapper mb-3">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h5>Quiz Interaktif</h5>
                        <p class="text-muted">Latihan soal dengan berbagai tingkat kesulitan dan feedback langsung</p>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="text-center">
                        <div class="feature-icon-wrapper mb-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5>Kolaborasi</h5>
                        <p class="text-muted">Fitur diskusi dan berbagi materi antar mahasiswa dan dosen</p>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="text-center">
                        <div class="feature-icon-wrapper mb-3">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h5>Akses Fleksibel</h5>
                        <p class="text-muted">Platform yang responsif dan dapat diakses dari berbagai perangkat</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light section-fade">
        <div class="container">
            <h2 class="text-center section-title">Fitur Utama</h2>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up">
                    <div class="card feature-card p-4 shadow-sm h-100">
                        <div class="text-center">
                            <i class="fas fa-file-upload feature-icon"></i>
                            <h5 class="card-title">Upload Tugas</h5>
                            <p class="card-text">Upload UTS, UAS, LKM Project, dan Jurnal Pembelajaran dengan mudah dan
                                terorganisir.</p>
                            <a href="#" class="btn btn-sm btn-outline-primary mt-3">Lihat Detail</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card feature-card p-4 shadow-sm h-100">
                        <div class="text-center">
                            <i class="fas fa-tasks feature-icon"></i>
                            <h5 class="card-title">Quiz Interaktif</h5>
                            <p class="card-text">Kerjakan quiz dengan berbagai tipe soal statistika dan dapatkan
                                penilaian langsung.</p>
                            <a href="#" class="btn btn-sm btn-outline-primary mt-3">Coba Quiz</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card feature-card p-4 shadow-sm h-100">
                        <div class="text-center">
                            <i class="fas fa-book feature-icon"></i>
                            <h5 class="card-title">Materi Pembelajaran</h5>
                            <p class="card-text">Akses materi pembelajaran statistika dalam berbagai format yang dapat
                                diunduh.</p>
                            <a href="#" class="btn btn-sm btn-outline-primary mt-3">Lihat Materi</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Academic Program Section -->
    <section id="program" class="py-5 section-fade">
        <div class="container">
            <h2 class="text-center section-title">Program Akademik</h2>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up">
                    <div class="card feature-card p-4 shadow-sm h-100">
                        <div class="text-center">
                            <i class="fas fa-graduation-cap feature-icon"></i>
                            <h5 class="card-title">Statistika</h5>
                            <p class="card-text">Program studi yang mempelajari pengumpulan, analisis, dan interpretasi
                                data untuk pengambilan keputusan.</p>
                            <a href="#" class="btn btn-sm btn-outline-primary mt-3">Lihat Detail</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card feature-card p-4 shadow-sm h-100">
                        <div class="text-center">
                            <i class="fas fa-chart-bar feature-icon"></i>
                            <h5 class="card-title">Statistika Bisnis</h5>
                            <p class="card-text">Fokus pada penerapan statistika dalam bidang bisnis dan manajemen untuk
                                pengambilan keputusan strategis.</p>
                            <a href="#" class="btn btn-sm btn-outline-primary mt-3">Lihat Detail</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card feature-card p-4 shadow-sm h-100">
                        <div class="text-center">
                            <i class="fas fa-dna feature-icon"></i>
                            <h5 class="card-title">Statistika Terapan</h5>
                            <p class="card-text">Penerapan metode statistika dalam berbagai bidang seperti kesehatan,
                                lingkungan, dan teknologi.</p>
                            <a href="#" class="btn btn-sm btn-outline-primary mt-3">Lihat Detail</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="card feature-card p-4 shadow-sm h-100">
                        <div class="text-center">
                            <i class="fas fa-laptop-code feature-icon"></i>
                            <h5 class="card-title">Komputasi Statistika</h5>
                            <p class="card-text">Integrasi statistika dengan teknologi komputasi untuk analisis data
                                kompleks dan big data.</p>
                            <a href="#" class="btn btn-sm btn-outline-primary mt-3">Lihat Detail</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="card feature-card p-4 shadow-sm h-100">
                        <div class="text-center">
                            <i class="fas fa-brain feature-icon"></i>
                            <h5 class="card-title">Statistika Industri</h5>
                            <p class="card-text">Penerapan statistika dalam pengendalian kualitas dan optimasi proses
                                industri.</p>
                            <a href="#" class="btn btn-sm btn-outline-primary mt-3">Lihat Detail</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5 bg-light section-fade">
        <div class="container">
            <h2 class="text-center section-title">Hubungi Kami</h2>
            <div class="row g-4">
                <!-- Contact Information -->
                <div class="col-lg-4">
                    <div class="contact-info-wrapper">
                        <h4 class="mb-4">Informasi Kontak</h4>
                        <div class="contact-info-list">
                            <div class="contact-info-item d-flex align-items-center">
                                <div class="contact-icon-wrapper">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="contact-info-content">
                                    <h6>Email</h6>
                                    <p>statiCore@gmail.com</p>
                                </div>
                            </div>
                            <div class="contact-info-item d-flex align-items-center">
                                <div class="contact-icon-wrapper">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="contact-info-content">
                                    <h6>Telepon</h6>
                                    <p>+62812 3456 7890</p>
                                </div>
                            </div>
                            <div class="contact-info-item d-flex align-items-center">
                                <div class="contact-icon-wrapper">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="contact-info-content">
                                    <h6>Alamat</h6>
                                    <p>Jalan Willem Iskandar, Pasar V Medan Estate, Percut Sei Tuan, Deli Serdang</p>
                                </div>
                            </div>
                            <div class="contact-info-item d-flex align-items-center">
                                <div class="contact-icon-wrapper">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="contact-info-content">
                                    <h6>Jam Operasional</h6>
                                    <p>Senin - Jumat: 08:00 - 17:00</p>
                                </div>
                            </div>
                        </div>
                        <div class="social-links">
                            <h6>Ikuti Kami</h6>
                            <div class="d-flex gap-3">
                                <a href="#" class="social-icon" title="Facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="#" class="social-icon" title="Twitter">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="#" class="social-icon" title="Instagram">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="#" class="social-icon" title="LinkedIn">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="col-lg-8">
                    <div class="contact-form-wrapper p-4 rounded-4 bg-white shadow-sm">
                        <h4 class="mb-4">Kirim Pesan</h4>
                        <form id="contactForm" class="needs-validation" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="name" placeholder="Nama Lengkap"
                                            required>
                                        <label for="name">Nama Lengkap</label>
                                        <div class="invalid-feedback">
                                            Mohon masukkan nama lengkap Anda
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" placeholder="Email"
                                            required>
                                        <label for="email">Email</label>
                                        <div class="invalid-feedback">
                                            Mohon masukkan email yang valid
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="tel" class="form-control" id="phone" placeholder="Nomor Telepon">
                                        <label for="phone">Nomor Telepon</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="subject" required>
                                            <option value="">Pilih Subjek</option>
                                            <option value="general">Pertanyaan Umum</option>
                                            <option value="academic">Pertanyaan Akademik</option>
                                            <option value="technical">Bantuan Teknis</option>
                                            <option value="other">Lainnya</option>
                                        </select>
                                        <label for="subject">Subjek</label>
                                        <div class="invalid-feedback">
                                            Mohon pilih subjek
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="message" placeholder="Pesan"
                                            style="height: 150px" required></textarea>
                                        <label for="message">Pesan</label>
                                        <div class="invalid-feedback">
                                            Mohon masukkan pesan Anda
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary-gradient w-100 py-3">
                                        <i class="fas fa-paper-plane me-2"></i>Kirim Pesan
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Map Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="map-wrapper rounded-4 overflow-hidden shadow-sm">
                        <iframe class="w-100" height="400" style="border:0"
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3972.0071999999997!2d98.7143972!3d3.6082101!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x303131714c40fccd%3A0x17660a6371985d8c!2sState%20University%20of%20Medan!5e0!3m2!1sid!2sid!4v1709798400000!5m2!1sid!2sid"
                            allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">statiCore</h5>
                    <p class="text-muted">Platform pembelajaran digital untuk siswa dan guru Sekolah Menengah Pertama.
                    </p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">Tautan Cepat</h5>
                    <ul class="footer-links">
                        <li><a href="#about">Tentang Kami</a></li>
                        <li><a href="#features">Fitur</a></li>
                        <li><a href="#program">Program</a></li>
                        <li><a href="#contact">Kontak</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">Newsletter</h5>
                    <p class="text-muted">Dapatkan informasi terbaru tentang StatiCore</p>
                    <div class="input-group mb-3">
                        <input type="email" class="form-control" placeholder="Email Anda">
                        <button class="btn btn-primary-gradient" type="button">Subscribe</button>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> StatiCore. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Floating Action Button -->
    <a href="#" class="floating-btn" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </a>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });
        // Section fade in animation
        const sections = document.querySelectorAll('.section-fade');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });

        sections.forEach(section => {
            observer.observe(section);
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function () {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Active nav link
        const navSections = document.querySelectorAll('section[id]');
        window.addEventListener('scroll', () => {
            let current = '';
            navSections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (pageYOffset >= sectionTop - 200) {
                    current = section.getAttribute('id');
                }
            });

            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').slice(1) === current) {
                    link.classList.add('active');
                }
            });
        });

        // Back to top button
        const backToTop = document.getElementById('backToTop');
        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });

        backToTop.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Add smooth scroll for all anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>

</body>

</html>
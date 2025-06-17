<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StatiCore - Platform Pembelajaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f5f6fa;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--secondary-color);
        }

        .navbar {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: bold;
        }

        .nav-link {
            color: #333 !important;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .content-wrapper {
            padding: 2rem;
            margin-top: 1rem;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="/">StatiCore</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/views/admin/kelas.php">Kelola Kelas</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/views/admin/users.php">Kelola Pengguna</a>
                            </li>
                        <?php elseif (isGuru()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/views/guru/materi.php">Materi</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/views/guru/quiz.php">Quiz</a>
                            </li>
                        <?php elseif (isSiswa()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/views/siswa/materi.php">Materi</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/views/siswa/quiz.php">Quiz</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container content-wrapper">
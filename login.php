<?php
session_start();
require "koneksi.php";

/* ===============================
   AUTO REDIRECT JIKA SUDAH LOGIN
================================= */
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':           header("Location: admin/dashboard_admin.php");   break;
        case 'siswa':           header("Location: siswa/dashboard_peserta.php"); break;
        case 'publikasi':       header("Location: publikasi/dashboard_publikasi.php"); break;
        case 'kepala_keamanan': header("Location: kepala/dashboard_kepala.php"); break;
        case 'polda':           header("Location: polda/dashboard_polda.php");   break;
        case 'ceo':             header("Location: ceo/dashboard_ceo.php");     break;
    }
    exit();
}

/* ===============================
   PROSES LOGIN
================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = trim($_POST['password']);

    $query = mysqli_query($conn,
        "SELECT * FROM akun WHERE username='$username' LIMIT 1"
    );

    if (mysqli_num_rows($query) === 1) {

        $akun = mysqli_fetch_assoc($query);

        /*
         * CEK PASSWORD:
         * Sistem ini menyimpan password sebagai plain text yang di-generate otomatis.
         * Ketika peserta mengganti password via ganti_password.php,
         * password baru tetap disimpan plain text.
         *
         * Catatan keamanan: untuk keamanan lebih lanjut, bisa diupgrade ke
         * password_hash() + password_verify() di versi berikutnya.
         */
        if ($password === $akun['password']) {

            $_SESSION['id_akun']  = $akun['id_akun'];
            $_SESSION['username'] = $akun['username'];
            $_SESSION['role']     = $akun['role'];

            switch ($akun['role']) {
                case 'admin':           header("Location: admin/dashboard_admin.php");   break;
                case 'siswa':           header("Location: siswa/dashboard_peserta.php"); break;
                case 'publikasi':       header("Location: publikasi/dashboard_publikasi.php"); break;
                case 'kepala_keamanan': header("Location: kepala/dashboard_kepala.php"); break;
                case 'polda':           header("Location: polda/dashboard_polda.php");   break;
                case 'ceo':             header("Location: ceo/dashboard_ceo.php");     break;
                default:
                    session_destroy();
                    header("Location: login.php");
            }
            exit();

        } else {
            $error = "Username atau password salah!";
        }

    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Sistem Satpam</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/login.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="login-page">

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard_umum.php">
            <img src="img/logo.png" class="me-2">
            <span class="fw-bold">Gemilang</span>
        </a>
        <div class="ms-auto">
            <a href="dashboard_umum.php" class="btn btn-sm btn-outline-warning">Kembali</a>
        </div>
    </div>
</nav>

<div class="login-wrapper">
    <div class="login-box">
        <h3>Login Sistem</h3>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control"
                       placeholder="contoh: siswa2026P1001" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-login w-100">Login</button>
        </form>

    </div>
</div>

</body>
</html>
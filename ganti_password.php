<?php
session_start();
include "koneksi.php";

// Hanya siswa yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: login.php");
    exit;
}

$id_akun = $_SESSION['id_akun'];
$pesan   = '';
$tipe    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password_lama  = trim($_POST['password_lama']);
    $password_baru  = trim($_POST['password_baru']);
    $konfirmasi     = trim($_POST['konfirmasi']);

    /* ===================================================
       1. AMBIL PASSWORD SEKARANG
    ==================================================== */
    $akun = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT password FROM akun WHERE id_akun='$id_akun' LIMIT 1"
    ));

    /* ===================================================
       2. VALIDASI PASSWORD LAMA
    ==================================================== */
    if ($password_lama !== $akun['password']) {
        $pesan = "Password lama tidak sesuai!";
        $tipe  = "danger";

    /* ===================================================
       3. VALIDASI PASSWORD BARU
    ==================================================== */
    } elseif (strlen($password_baru) < 6) {
        $pesan = "Password baru minimal 6 karakter!";
        $tipe  = "danger";

    } elseif (!preg_match('/[A-Z]/', $password_baru)) {
        $pesan = "Password baru harus mengandung minimal 1 huruf kapital!";
        $tipe  = "danger";

    } elseif (!preg_match('/[0-9]/', $password_baru)) {
        $pesan = "Password baru harus mengandung minimal 1 angka!";
        $tipe  = "danger";

    } elseif ($password_baru !== $konfirmasi) {
        $pesan = "Konfirmasi password tidak cocok!";
        $tipe  = "danger";

    } elseif ($password_baru === $password_lama) {
        $pesan = "Password baru tidak boleh sama dengan password lama!";
        $tipe  = "danger";

    } else {
        /* ===================================================
           4. UPDATE PASSWORD (plain text)
        ==================================================== */
        $password_aman = mysqli_real_escape_string($conn, $password_baru);
        mysqli_query($conn,
            "UPDATE akun SET password='$password_aman' WHERE id_akun='$id_akun'"
        );

        $pesan = "Password berhasil diubah!";
        $tipe  = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ganti Password - Gemilang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <style>
        body { background: #f0f2f5; }

        .ganti-card {
            max-width: 480px;
            margin: 60px auto;
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }

        .ganti-card h4 {
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 6px;
        }

        .ganti-card .subtitle {
            font-size: 13px;
            color: #888;
            margin-bottom: 28px;
        }

        .form-label { font-weight: 600; font-size: 14px; }

        .syarat-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #555;
        }

        .syarat-box ul { margin: 6px 0 0; padding-left: 18px; }
        .syarat-box li { margin-bottom: 4px; }

        .btn-ganti {
            background-color: var(--navy);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            padding: 10px;
            width: 100%;
            transition: 0.2s;
        }

        .btn-ganti:hover {
            background-color: var(--yellow);
            color: #111;
        }

        .toggle-pw {
            cursor: pointer;
            font-size: 13px;
            color: #0d6efd;
            text-decoration: none;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark" style="background-color: var(--navy);">
    <div class="container-fluid">
        <span class="navbar-brand fw-bold">Ganti Password</span>
        <a href="siswa/dashboard_peserta.php" class="btn btn-sm btn-outline-warning">
            Kembali
        </a>
    </div>
</nav>

<div class="container">
    <div class="ganti-card">

        <h4>🔒 Ganti Password</h4>
        <p class="subtitle">Halo, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>

        <?php if ($pesan): ?>
            <div class="alert alert-<?php echo $tipe; ?> py-2">
                <?php echo htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <div class="syarat-box">
            <strong>Syarat password baru:</strong>
            <ul>
                <li>Minimal 6 karakter</li>
                <li>Mengandung minimal 1 huruf kapital</li>
                <li>Mengandung minimal 1 angka</li>
                <li>Tidak sama dengan password lama</li>
            </ul>
        </div>

        <form method="POST" autocomplete="off">

            <div class="mb-3">
                <label class="form-label">Password Lama</label>
                <input type="password" name="password_lama" id="pw_lama"
                       class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password Baru</label>
                <input type="password" name="password_baru" id="pw_baru"
                       class="form-control" required>
            </div>

            <div class="mb-4">
                <label class="form-label">Konfirmasi Password Baru</label>
                <input type="password" name="konfirmasi" id="pw_konfirm"
                       class="form-control" required>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="lihatPassword">
                <label class="form-check-label" for="lihatPassword" style="font-size:13px;">
                    Tampilkan password
                </label>
            </div>

            <button type="submit" class="btn-ganti">Simpan Password Baru</button>

        </form>

    </div>
</div>

<script>
// Toggle show/hide password
document.getElementById('lihatPassword').addEventListener('change', function () {
    const type = this.checked ? 'text' : 'password';
    ['pw_lama', 'pw_baru', 'pw_konfirm'].forEach(id => {
        document.getElementById(id).type = type;
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
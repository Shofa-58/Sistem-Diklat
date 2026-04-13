<?php
session_start();

// Proteksi: hanya bisa diakses setelah daftar berhasil
if (!isset($_SESSION['daftar_sukses'])) {
    header("Location: daftar.php");
    exit;
}

$nama           = $_SESSION['daftar_nama'];
$email          = $_SESSION['daftar_email'];
$emailTerkirim  = $_SESSION['email_terkirim'];

// Bersihkan session pendaftaran
unset($_SESSION['daftar_sukses'], $_SESSION['daftar_nama'],
      $_SESSION['daftar_email'], $_SESSION['email_terkirim']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pendaftaran Berhasil - Gemilang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <style>
        body { background: linear-gradient(135deg, #0d1b2a, #1b263b); min-height: 100vh;
               display: flex; align-items: center; justify-content: center; }
        .sukses-card { background: #fff; border-radius: 20px; padding: 50px 40px;
                       max-width: 520px; width: 100%; text-align: center;
                       box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .icon-sukses { font-size: 64px; margin-bottom: 20px; }
        .email-box { background: #f0f4ff; border-radius: 10px; padding: 16px 20px;
                     border-left: 4px solid #0d1b2a; text-align: left; margin: 20px 0; }
        .email-box p { margin: 0; font-size: 14px; color: #444; }
        .email-box strong { color: #0d1b2a; }
    </style>
</head>
<body>
<div class="sukses-card">
    <div class="icon-sukses">✅</div>
    <h3 class="fw-bold text-success mb-2">Pendaftaran Berhasil!</h3>
    <p class="text-muted mb-4">
        Terima kasih, <strong><?php echo htmlspecialchars($nama); ?></strong>.<br>
        Data Anda telah kami terima dan sedang diproses.
    </p>

    <?php if ($emailTerkirim): ?>
        <div class="email-box">
            <p>📧 Informasi akun (username & password) telah dikirim ke:</p>
            <p class="mt-1"><strong><?php echo htmlspecialchars($email); ?></strong></p>
            <p class="mt-2 text-muted" style="font-size:13px;">
                Cek folder <em>Inbox</em> atau <em>Spam</em> jika email belum muncul.
            </p>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-start">
            ⚠️ Pendaftaran berhasil, namun email gagal dikirim.<br>
            Silakan hubungi admin untuk mendapatkan informasi akun Anda.
        </div>
    <?php endif; ?>

    <a href="login.php" class="btn btn-dark w-100 mt-3">
        Pergi ke Halaman Login
    </a>
</div>
</body>
</html>
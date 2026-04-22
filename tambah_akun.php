<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_POST['submit'])) {

    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    // FIX BUG: Sistem login menggunakan plain text comparison
    // Jadi password disimpan plain text, konsisten dengan helpers.php
    $password = mysqli_real_escape_string($conn, trim($_POST['password']));
    $role     = $_POST['role'];

    // Validasi panjang password minimal
    if (strlen(trim($_POST['password'])) < 6) {
        $_SESSION['error'] = "Password minimal 6 karakter!";
        header("Location: tambah_akun.php");
        exit;
    }

    $cek = mysqli_query($conn, "SELECT * FROM akun WHERE username='$username'");

    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['error'] = "Username sudah digunakan!";
        header("Location: tambah_akun.php");
        exit;
    } else {
        mysqli_query($conn,
            "INSERT INTO akun (username, password, role)
             VALUES ('$username', '$password', '$role')"
        );
        $_SESSION['success'] = "Akun berhasil dibuat! Username: $username";
        header("Location: tambah_akun.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Akun</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/tambah_akun.css">
<link rel="stylesheet" href="css/dashboard_layout.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body class="tambah_akun-page">

<div class="dashboard-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">⚙️ Admin Panel</div>
        <ul class="sidebar-menu">
            <li><a href="admin/dashboard_admin.php"><span>👥</span> Data Siswa</a></li>
            <li><a href="tambah_akun.php" class="active"><span>👤</span> Buat Akun</a></li>
        </ul>
        <div class="sidebar-footer">
            <button type="button" class="btn-logout" id="btnLogout">
                <span>🚪</span> Logout
            </button>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" id="menuToggle">☰</button>
                <h1 class="page-title">Buat Akun Pengguna</h1>
            </div>
            <div>
                <span style="font-size:13px;color:var(--text-muted);font-weight:500;">
                    Admin: <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
        </header>

        <div class="content-body">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="card-form shadow-lg">
                        <h4 class="text-center mb-4 text-warning fw-bold">Buat Akun Pengguna</h4>

                        <div class="alert alert-info" style="font-size:13px;border-radius:10px;">
                            ℹ️ Untuk akun <strong>Siswa</strong>, gunakan halaman pendaftaran publik
                            (<code>/daftar.php</code>) agar username & password dikirim otomatis via email.
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control"
                                       placeholder="Contoh: polda02" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control"
                                       placeholder="Minimal 6 karakter" required minlength="6">
                                <div class="form-text" style="color:#adb5bd;">
                                    Password disimpan sesuai format sistem (plain text).
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="publikasi">Publikasi</option>
                                    <option value="kepala_keamanan">Kepala Keamanan</option>
                                    <option value="polda">Polda DIY</option>
                                    <option value="admin">Admin</option>
                                    <option value="ceo">CEO</option>
                                </select>
                            </div>
                            <button type="submit" name="submit" class="btn btn-primary w-100">
                                Buat Akun
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php if(isset($_SESSION['success'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    Swal.fire({icon:'success',title:'Berhasil!',text:'<?= addslashes($_SESSION['success']); ?>',confirmButtonColor:'#ffd60a'});
});
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    Swal.fire({icon:'error',title:'Gagal!',text:'<?= addslashes($_SESSION['error']); ?>',confirmButtonColor:'#ffd60a'});
});
</script>
<?php unset($_SESSION['error']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && !sidebar.contains(e.target) && e.target !== menuToggle)
        sidebar.classList.remove('open');
});
document.getElementById('btnLogout').addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title:'Keluar dari Sistem?',text:"Anda akan mengakhiri sesi. Lanjutkan?",icon:'warning',
        showCancelButton:true,confirmButtonColor:'#dc3545',cancelButtonColor:'#6c757d',
        confirmButtonText:'Ya, Logout',cancelButtonText:'Batal',reverseButtons:true
    }).then((result) => { if(result.isConfirmed) window.location.href='logout.php'; });
});
</script>
</body>
</html>
<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_POST['submit'])) {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = $_POST['role'];

    $cek = mysqli_query($conn, "SELECT * FROM akun WHERE username='$username'");

    if (mysqli_num_rows($cek) > 0) {

        $_SESSION['error'] = "Username sudah digunakan!";
        header("Location: tambah_akun.php");
        exit;

    } else {

        mysqli_query($conn, "INSERT INTO akun (username, password, role)
                             VALUES ('$username', '$password', '$role')");

        $_SESSION['success'] = "Akun berhasil dibuat!";
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body class="tambah_akun-page">

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="dashboard_umum.php">Admin Panel</a>
  </div>
</nav>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-5 col-md-7">

      <div class="card-form shadow-lg">

        <h4 class="text-center mb-4 text-warning fw-bold">
          Buat Akun Peserta
        </h4>

        <form method="POST">

          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>

          <div class="mb-4">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" required>
              <option value="siswa">Siswa</option>
              <option value="publikasi">Publikasi</option>
              <option value="kepala_keamanan">Kepala Keamanan</option>
              <option value="admin">Admin</option>
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

<?php if(isset($_SESSION['success'])) : ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: '<?= $_SESSION['success']; ?>',
    confirmButtonColor: '#ffd60a'
});
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if(isset($_SESSION['error'])) : ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Gagal!',
    text: '<?= $_SESSION['error']; ?>',
    confirmButtonColor: '#ffd60a'
});
</script>
<?php unset($_SESSION['error']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
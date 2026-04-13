<?php
include "koneksi.php";
include "helpers.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $nama          = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $alamat        = mysqli_real_escape_string($conn, trim($_POST['alamat']));
    $no_telp       = mysqli_real_escape_string($conn, trim($_POST['no_telp']));
    $email         = mysqli_real_escape_string($conn, trim($_POST['email']));
    $tgl_lahir     = $_POST['tgl_lahir'];
    $agama         = $_POST['agama'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tinggi_badan  = (int) $_POST['tinggi_badan'];
    $berat_badan   = (int) $_POST['berat_badan'];

    /* Cek email duplikat */
    $cekEmail = mysqli_query($conn,
        "SELECT id_peserta FROM siswa WHERE email='$email' LIMIT 1"
    );
    if (mysqli_num_rows($cekEmail) > 0) {
        $error_msg = "Email sudah terdaftar. Gunakan email lain.";
        goto tampilForm;
    }

    /* Generate username & password */
    $username = generateUsername($conn);
    $password = generatePassword(9);

    /* Simpan akun */
    $insertAkun = mysqli_query($conn,
        "INSERT INTO akun (username, password, role)
         VALUES ('$username', '$password', 'siswa')"
    );
    if (!$insertAkun) {
        $error_msg = "Gagal membuat akun: " . mysqli_error($conn);
        goto tampilForm;
    }
    $id_akun = mysqli_insert_id($conn);

    /* Simpan data siswa */
    $insertSiswa = mysqli_query($conn,
        "INSERT INTO siswa
            (nama, alamat, no_telp, email, tgl_lahir, agama,
             jenis_kelamin, status, id_akun, tinggi_badan, berat_badan)
         VALUES
            ('$nama','$alamat','$no_telp','$email','$tgl_lahir','$agama',
             '$jenis_kelamin','calon','$id_akun','$tinggi_badan','$berat_badan')"
    );
    if (!$insertSiswa) {
        mysqli_query($conn, "DELETE FROM akun WHERE id_akun='$id_akun'");
        $error_msg = "Gagal menyimpan data: " . mysqli_error($conn);
        goto tampilForm;
    }
    $id_siswa = mysqli_insert_id($conn);

    /* Upload dokumen */
    $folder = "uploads/" . $id_siswa;
    if (!is_dir($folder)) mkdir($folder, 0777, true);

    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];

    function uploadDokumen($input_name, $jenis, $id_siswa, $folder, $conn, $allowed_ext) {
        if (!empty($_FILES[$input_name]['name'])) {
            $file_name = $_FILES[$input_name]['name'];
            $tmp       = $_FILES[$input_name]['tmp_name'];
            $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_ext)) {
                $new_name  = $jenis . "." . $ext;
                $file_path = $folder . "/" . $new_name;
                move_uploaded_file($tmp, $file_path);
                $tgl = date("Y-m-d H:i:s");
                mysqli_query($conn,
                    "INSERT INTO dokumen_pendaftaran
                        (jenis, file_path, status_verifikasi, tgl_upload, id_siswa)
                     VALUES
                        ('$jenis','$file_path','pending','$tgl','$id_siswa')"
                );
            }
        }
    }

    uploadDokumen("ktp",        "ktp",        $id_siswa, $folder, $conn, $allowed_ext);
    uploadDokumen("ijazah",     "ijazah",      $id_siswa, $folder, $conn, $allowed_ext);
    uploadDokumen("kk",         "kk",          $id_siswa, $folder, $conn, $allowed_ext);
    uploadDokumen("skck",       "skck",        $id_siswa, $folder, $conn, $allowed_ext);
    uploadDokumen("pembayaran", "pembayaran",  $id_siswa, $folder, $conn, $allowed_ext);

    /* Kirim email */
    $emailTerkirim = kirimEmailAkun($email, $nama, $username, $password);

    session_start();
    $_SESSION['daftar_sukses']  = true;
    $_SESSION['daftar_nama']    = $nama;
    $_SESSION['daftar_email']   = $email;
    $_SESSION['email_terkirim'] = $emailTerkirim;

    header("Location: daftar_sukses.php");
    exit;
}

tampilForm:
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pendaftaran - Gemilang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/daftar.css">
</head>
<body class="daftar-page">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container daftar-container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard_umum.php">
            <img src="img/logo.png" alt="Logo" class="logo-navbar">
            <span class="ms-2">Gemilang</span>
        </a>
    </div>
</nav>

<section class="py-5">
    <div class="container daftar-container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-7">
                <div class="form-card">

                    <h3 class="form-title text-center mb-4">Form Pendaftaran</h3>

                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error_msg); ?>
                        </div>
                    <?php endif; ?>

                    <form action="daftar.php" method="POST" enctype="multipart/form-data">

                        <h5 class="section-label">Data Diri</h5>

                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nomor HP</label>
                            <input type="text" name="no_telp" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                            <div class="form-text">Username & password akan dikirimkan ke email ini.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Lahir</label>
                            <input type="date" name="tgl_lahir" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label d-block">Jenis Kelamin</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jenis_kelamin" value="L" required>
                                <label class="form-check-label">Laki-laki</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jenis_kelamin" value="P" required>
                                <label class="form-check-label">Perempuan</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tinggi Badan (cm)</label>
                            <input type="number" name="tinggi_badan" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Berat Badan (kg)</label>
                            <input type="number" name="berat_badan" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Agama</label>
                            <select name="agama" class="form-select" required>
                                <option value="">Pilih Agama</option>
                                <option>Islam</option>
                                <option>Kristen</option>
                                <option>Katolik</option>
                                <option>Hindu</option>
                                <option>Buddha</option>
                                <option>Konghucu</option>
                            </select>
                        </div>

                        <hr>

                        <h5 class="section-label">Upload Dokumen</h5>
                        <div class="form-text mb-3">
                            Format yang diterima: JPG, PNG, PDF. Maksimal 5MB per file.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">KTP</label>
                            <input type="file" name="ktp" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ijazah Terakhir</label>
                            <input type="file" name="ijazah" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kartu Keluarga (KK)</label>
                            <input type="file" name="kk" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SKCK</label>
                            <input type="file" name="skck" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Bukti Pembayaran DP</label>
                            <input type="file" name="pembayaran" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Kirim Pendaftaran
                        </button>

                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

</body>
</html>
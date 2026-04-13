<?php
session_start();
include "../koneksi.php";
include "../auto_update_status.php";

if(!isset($_SESSION['role']) || $_SESSION['role'] != "admin"){
    header("location:../login.php");
    exit;
}

if(isset($_POST['update_status'])){

    $id          = mysqli_real_escape_string($conn, $_POST['id_siswa']);
    $status_baru = mysqli_real_escape_string($conn, $_POST['status']);

    $ambil = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT status FROM siswa WHERE id_peserta='$id'"
    ));
    $status_lama = $ambil['status'];
    $boleh = false;

    if($status_lama == 'calon'        && $status_baru == 'terverifikasi') $boleh = true;
    if($status_lama == 'terverifikasi' && $status_baru == 'peserta')      $boleh = true;
    if($status_lama == 'peserta'      &&
        ($status_baru == 'lulus' || $status_baru == 'tidak_lulus'))       $boleh = true;

    if($boleh){
        mysqli_query($conn,
            "UPDATE siswa SET status='$status_baru' WHERE id_peserta='$id'"
        );
        $pesan = "Status berhasil diperbarui.";
    } else {
        $pesan = "Transisi status tidak diperbolehkan!";
    }

    echo "<script>
            alert('$pesan');
            window.location='admin_status_siswa.php';
          </script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Status Siswa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/admin_status_siswa.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--navy);">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="#">Admin Gemilang</a>
        <div class="ms-auto">
            <a href="dashboard_admin.php" class="btn btn-sm btn-light">Dashboard</a>
            <a href="../logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-body">

            <h4 class="mb-4">Kelola Status Siswa</h4>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Status Sekarang</th>
                            <th>Batas Revisi</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $data = mysqli_query($conn,
                        "SELECT * FROM siswa ORDER BY id_peserta DESC"
                    );
                    while($d = mysqli_fetch_array($data)){
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['nama']); ?></td>
                        <td><?php echo htmlspecialchars($d['email']); ?></td>
                        <td>
                            <span class="badge status_badge <?php echo $d['status']; ?>">
                                <?php echo ucfirst(str_replace('_',' ', $d['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo $d['batas_revisi'] ?? '-'; ?></td>
                        <td>
                            <form method="POST" class="d-flex justify-content-center gap-2">
                                <input type="hidden" name="id_siswa" value="<?php echo $d['id_peserta']; ?>">
                                <select name="status" class="form-select form-select-sm w-auto" required>
                                    <option value="">Pilih</option>
                                    <?php
                                    if($d['status'] == 'calon'){
                                        echo "<option value='terverifikasi'>Terverifikasi</option>";
                                    } elseif($d['status'] == 'terverifikasi'){
                                        echo "<option value='peserta'>Peserta</option>";
                                    } elseif($d['status'] == 'peserta'){
                                        echo "<option value='lulus'>Lulus</option>";
                                        echo "<option value='tidak_lulus'>Tidak Lulus</option>";
                                    }
                                    ?>
                                </select>
                                <button type="submit" name="update_status"
                                    class="btn btn-warning btn-sm">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
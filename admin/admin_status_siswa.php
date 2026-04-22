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

        /* Jika berubah jadi peserta, pastikan masuk ke peserta_periode jika belum */
        if ($status_baru == 'peserta') {
            $q_cek = mysqli_query($conn, "SELECT id_peserta FROM peserta_periode WHERE id_peserta='$id'");
            if (mysqli_num_rows($q_cek) === 0) {
                // Cari periode yang paling cocok
                $q_match = mysqli_query($conn,
                    "SELECT pd.id_periode 
                     FROM periode_diklat pd
                     JOIN informasi_diklat id ON pd.id_periode = id.id_periode
                     JOIN siswa s ON s.id_peserta = '$id'
                     WHERE id.dibuat_pada <= s.created_at 
                       AND pd.tanggal_mulai >= s.created_at
                       AND pd.status = 'pendaftaran'
                     ORDER BY pd.tanggal_mulai ASC
                     LIMIT 1"
                );
                if ($row_p = mysqli_fetch_assoc($q_match)) {
                    $id_p_auto = $row_p['id_periode'];
                    mysqli_query($conn,
                        "INSERT IGNORE INTO peserta_periode (id_peserta, id_periode, tanggal_terima)
                         VALUES ('$id', '$id_p_auto', CURDATE())"
                    );
                }
            }
        }

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
    <link rel="stylesheet" href="../css/dashboard_layout.css">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body>

<div class="dashboard-wrapper">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            ⚙️ Admin Panel
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_admin.php" class="active">
                    <span>👥</span> Data Siswa
                </a>
            </li>
            <li>
                <a href="admin_persiapan_diklat.php">
                    <span>📅</span> Persiapan Diklat
                </a>
            </li>
            <li>
                <a href="admin_evaluasi.php">
                    <span>📝</span> Evaluasi Nilai
                </a>
            </li>
            <li>
                <a href="../arsip_laporan.php">
                    <span>🗂️</span> Arsip Laporan
                </a>
            </li>
            <li>
                <a href="../ganti_password.php">
                    <span>🔒</span> Ganti Password
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <button type="button" class="btn-logout" id="btnLogout" data-url="../logout.php">
                <span>🚪</span> Logout
            </button>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" id="menuToggle">☰</button>
                <h1 class="page-title">Kelola Status Siswa</h1>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                    Admin: <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
        </header>

        <div class="content-body">

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

        </div> <!-- End content-body -->
    </main>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="../js/dashboard.js"></script>
</body>
</html>
<?php
session_start();
include "../koneksi.php";
include "../auto_update_status.php";

if(!isset($_SESSION['role']) || $_SESSION['role'] != "admin"){
    header("location:../login.php");
    exit;
}

if(isset($_POST['tambah_periode'])){
    $tahun            = mysqli_real_escape_string($conn, $_POST['tahun']);
    $gelombang        = mysqli_real_escape_string($conn, $_POST['gelombang']);
    $tanggal_mulai    = $_POST['tanggal_mulai'];
    $tanggal_selesai  = $_POST['tanggal_selesai'];
    $biaya            = (int) $_POST['biaya'];
    $lokasi_spesifik  = mysqli_real_escape_string($conn, $_POST['lokasi_spesifik']);
    $lokasi_fasilitas = mysqli_real_escape_string($conn, $_POST['lokasi_fasilitas']);
    $fasilitas        = mysqli_real_escape_string($conn, $_POST['fasilitas']);
    $info_kebutuhan   = mysqli_real_escape_string($conn, $_POST['info_kebutuhan']);
    $batas_verifikasi = $_POST['batas_verifikasi'];
    $status           = $_POST['status'];

    mysqli_query($conn,"
        INSERT INTO periode_diklat
        (tahun, gelombang, tanggal_mulai, tanggal_selesai, biaya,
         lokasi_spesifik, lokasi_fasilitas, fasilitas, info_kebutuhan,
         batas_verifikasi, status)
        VALUES
        ('$tahun','$gelombang','$tanggal_mulai','$tanggal_selesai','$biaya',
         '$lokasi_spesifik','$lokasi_fasilitas','$fasilitas','$info_kebutuhan',
         '$batas_verifikasi','$status')
    ");

    header("location:admin_persiapan_diklat.php");
    exit;
}

if(isset($_POST['tambah_jadwal'])){
    $tanggal    = $_POST['tanggal'];
    $kegiatan   = mysqli_real_escape_string($conn, $_POST['kegiatan']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $id_periode = (int) $_POST['id_periode'];

    mysqli_query($conn,"
        INSERT INTO jadwal_diklat (tanggal, kegiatan, keterangan, id_periode)
        VALUES ('$tanggal','$kegiatan','$keterangan','$id_periode')
    ");

    header("location:admin_persiapan_diklat.php");
    exit;
}

if(isset($_GET['hapus_periode'])){
    $id = (int) $_GET['hapus_periode'];
    mysqli_query($conn, "DELETE FROM periode_diklat WHERE id_periode='$id'");
    header("location:admin_persiapan_diklat.php");
    exit;
}

if(isset($_GET['hapus_jadwal'])){
    $id = (int) $_GET['hapus_jadwal'];
    mysqli_query($conn, "DELETE FROM jadwal_diklat WHERE id_jadwal='$id'");
    header("location:admin_persiapan_diklat.php");
    exit;
}

$periode = mysqli_query($conn,
    "SELECT * FROM periode_diklat ORDER BY tahun DESC, gelombang DESC"
);

$jadwal = mysqli_query($conn,"
    SELECT j.*, p.tahun, p.gelombang
    FROM jadwal_diklat j
    LEFT JOIN periode_diklat p ON j.id_periode = p.id_periode
    ORDER BY j.tanggal ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Persiapan Diklat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/admin_persiapan_diklat.css">
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
                <a href="dashboard_admin.php">
                    <span>👥</span> Data Siswa
                </a>
            </li>
            <li>
                <a href="admin_persiapan_diklat.php" class="active">
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
            <button type="button" class="btn-logout" id="btnLogout">
                <span>🚪</span> Logout
            </button>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" id="menuToggle">☰</button>
                <h1 class="page-title">Persiapan Diklat</h1>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                    Admin: <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
        </header>

        <div class="content-body">

    <div class="row g-4">

        <!-- Form Tambah Periode -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Tambah Periode Diklat</h5>
                    <form method="POST">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Tahun</label>
                                <input type="number" name="tahun" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Gelombang</label>
                                <input type="number" name="gelombang" class="form-control" min="1" max="4" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Tanggal Mulai</label>
                                <input type="date" name="tanggal_mulai" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Tanggal Selesai</label>
                                <input type="date" name="tanggal_selesai" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>Batas Verifikasi Admin</label>
                            <input type="date" name="batas_verifikasi" class="form-control" required>
                            <div class="form-text">Setelah tanggal ini, status siswa otomatis menjadi peserta.</div>
                        </div>

                        <div class="mb-3">
                            <label>Biaya Diklat (Rp)</label>
                            <input type="number" name="biaya" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label>Lokasi Spesifik</label>
                            <input type="text" name="lokasi_spesifik" class="form-control"
                                   placeholder="Contoh: Pusdiklat Polda DIY, Jl. Ringroad Utara No.1">
                        </div>

                        <div class="mb-3">
                            <label>Lokasi Pengambilan Fasilitas</label>
                            <input type="text" name="lokasi_fasilitas" class="form-control"
                                   placeholder="Contoh: Gudang Logistik Lt.1, Gedung B">
                        </div>

                        <div class="mb-3">
                            <label>Fasilitas yang Diberikan</label>
                            <textarea name="fasilitas" class="form-control" rows="3"
                                      placeholder="Contoh: Seragam PDH, Modul, Konsumsi 1x/hari"></textarea>
                        </div>

                        <div class="mb-3">
                            <label>Informasi Kebutuhan Peserta</label>
                            <textarea name="info_kebutuhan" class="form-control" rows="3"
                                      placeholder="Contoh: Pakaian olahraga 2 stel, Sepatu PDH hitam, Perlengkapan mandi"></textarea>
                        </div>

                        <div class="mb-3">
                            <label>Status</label>
                            <select name="status" class="form-select">
                                <option value="pendaftaran">Pendaftaran</option>
                                <option value="berjalan">Berjalan</option>
                                <option value="selesai">Selesai</option>
                            </select>
                        </div>

                        <button class="btn btn-warning w-100" name="tambah_periode">
                            Simpan Periode
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Form Tambah Jadwal -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Tambah Rundown Diklat</h5>
                    <form method="POST">

                        <div class="mb-3">
                            <label>Pilih Periode</label>
                            <select name="id_periode" class="form-select">
                                <?php
                                /* reset pointer periode */
                                $periode2 = mysqli_query($conn,
                                    "SELECT * FROM periode_diklat ORDER BY tahun DESC, gelombang DESC"
                                );
                                while($p = mysqli_fetch_assoc($periode2)){
                                    echo "<option value='{$p['id_periode']}'>
                                            {$p['tahun']} - Gelombang {$p['gelombang']}
                                          </option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label>Tanggal</label>
                            <input type="date" name="tanggal" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label>Kegiatan</label>
                            <input type="text" name="kegiatan" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"></textarea>
                        </div>

                        <button class="btn btn-warning w-100" name="tambah_jadwal">
                            Tambah Jadwal
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- Tabel Periode Diklat -->
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h5 class="mb-3">Daftar Periode Diklat</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Tahun</th>
                            <th>Gelombang</th>
                            <th>Tanggal Pelaksanaan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($p = mysqli_fetch_assoc($periode)){ ?>
                        <tr>
                            <td><?php echo $p['tahun']; ?></td>
                            <td><?php echo $p['gelombang']; ?></td>
                            <td><?php echo $p['tanggal_mulai'] . " s/d " . $p['tanggal_selesai']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo ($p['status']=='selesai')?'success':(($p['status']=='berjalan')?'primary':'warning'); ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="#" onclick="konfirmasiHapus('?hapus_periode=<?php echo $p['id_periode']; ?>')" class="btn btn-sm btn-danger">Hapus</a>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabel Rundown -->
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h5 class="mb-3">Rundown Diklat</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Tanggal</th>
                            <th>Kegiatan</th>
                            <th>Keterangan</th>
                            <th>Periode</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($j = mysqli_fetch_assoc($jadwal)){ ?>
                        <tr>
                            <td><?php echo $j['tanggal']; ?></td>
                            <td><?php echo htmlspecialchars($j['kegiatan']); ?></td>
                            <td><?php echo htmlspecialchars($j['keterangan']); ?></td>
                            <td><?php echo $j['tahun'] . " - G" . $j['gelombang']; ?></td>
                            <td>
                                <a href="#" onclick="konfirmasiHapus('?hapus_jadwal=<?php echo $j['id_jadwal']; ?>')" class="btn btn-sm btn-danger">Hapus</a>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    </div> <!-- End container -->

        </div> <!-- End content-body -->
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
// Sidebar toggle (Mobile)
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(e.target) && e.target !== menuToggle) {
            sidebar.classList.remove('open');
        }
    }
});

// SweetAlert Logout Confirmation
document.getElementById('btnLogout').addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Keluar dari Sistem?',
        text: "Anda akan mengakhiri sesi. Lanjutkan?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Logout',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../logout.php';
        }
    })
});

function konfirmasiHapus(url) {
    Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: "Data yang dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
</script>
</body>
</html>
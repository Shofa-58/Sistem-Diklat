<?php
session_start();
include "../koneksi.php";
include "../auto_update_status.php";

if(!isset($_SESSION['role']) || $_SESSION['role'] != "admin"){
    header("location:../login.php");
    exit;
}

/* ============================================================
   CEK APAKAH ADA PERIODE YANG MASIH AKTIF
   Periode aktif = status 'pendaftaran' atau 'berjalan'
   Jika masih ada, admin tidak boleh buat periode baru
   kecuali periode aktif sudah dikonfirmasi selesai oleh Kepala
   ============================================================ */
$periode_aktif_cek = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT pd.id_periode, pd.tahun, pd.gelombang, pd.status,
            l.dikonfirmasi_kepala
     FROM periode_diklat pd
     LEFT JOIN laporan l ON l.id_periode = pd.id_periode
     WHERE pd.status IN ('pendaftaran','berjalan')
     ORDER BY pd.tahun DESC, pd.gelombang DESC
     LIMIT 1"
));

$ada_periode_aktif     = ($periode_aktif_cek !== null);
$periode_belum_selesai = $ada_periode_aktif && !($periode_aktif_cek['dikonfirmasi_kepala'] ?? false);

/* ============================================================
   PROSES: TAMBAH PERIODE BARU
   ============================================================ */
if(isset($_POST['tambah_periode'])){

    // Validasi: tidak boleh buat periode baru jika masih ada yang aktif
    // dan belum dikonfirmasi selesai oleh Kepala Keamanan
    if($periode_belum_selesai){
        $_SESSION['error_periode'] = "Tidak dapat membuat periode baru. Periode "
            . $periode_aktif_cek['tahun'] . " Gelombang " . $periode_aktif_cek['gelombang']
            . " masih berstatus <strong>" . $periode_aktif_cek['status'] . "</strong> "
            . "dan belum dikonfirmasi selesai oleh Kepala Keamanan.";
        header("location:admin_persiapan_diklat.php");
        exit;
    }

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
    $status           = 'pendaftaran'; // selalu mulai dari pendaftaran

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

/* ============================================================
   PROSES: TAMBAH JADWAL / RUNDOWN
   ============================================================ */
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

/* ============================================================
   PROSES: HAPUS
   ============================================================ */
if(isset($_GET['hapus_periode'])){
    $id = (int) $_GET['hapus_periode'];
    // Hanya boleh hapus periode yang sudah selesai atau belum dimulai
    $cek_hapus = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT status FROM periode_diklat WHERE id_periode='$id'"
    ));
    if($cek_hapus && $cek_hapus['status'] === 'berjalan'){
        $_SESSION['error_periode'] = "Tidak dapat menghapus periode yang sedang berjalan.";
    } else {
        mysqli_query($conn, "DELETE FROM periode_diklat WHERE id_periode='$id'");
    }
    header("location:admin_persiapan_diklat.php");
    exit;
}

if(isset($_GET['hapus_jadwal'])){
    $id = (int) $_GET['hapus_jadwal'];
    mysqli_query($conn, "DELETE FROM jadwal_diklat WHERE id_jadwal='$id'");
    header("location:admin_persiapan_diklat.php");
    exit;
}

/* ============================================================
   PROSES: UPDATE STATUS PERIODE (MANUAL)
   ============================================================ */
if(isset($_GET['set_status'])){
    $id     = (int) $_GET['set_status'];
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    
    mysqli_query($conn, "UPDATE periode_diklat SET status='$status' WHERE id_periode='$id'");
    
    header("location:admin_persiapan_diklat.php");
    exit;
}



/* ============================================================
   LOAD DATA
   ============================================================ */
$periode = mysqli_query($conn,
    "SELECT pd.*,
            l.dikonfirmasi_kepala,
            (SELECT COUNT(*) FROM peserta_periode pp WHERE pp.id_periode = pd.id_periode) AS jml_peserta
     FROM periode_diklat pd
     LEFT JOIN laporan l ON l.id_periode = pd.id_periode
     ORDER BY pd.tahun DESC, pd.gelombang DESC"
);

$jadwal = mysqli_query($conn,"
    SELECT j.*, p.tahun, p.gelombang
    FROM jadwal_diklat j
    LEFT JOIN periode_diklat p ON j.id_periode = p.id_periode
    ORDER BY j.tanggal ASC
");

$semua_periode_form = mysqli_query($conn,
    "SELECT * FROM periode_diklat ORDER BY tahun DESC, gelombang DESC"
);
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
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .info-alur {
            background: #e8f4fd;
            border-left: 4px solid #0d6efd;
            border-radius: 0 10px 10px 0;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #084298;
        }
        .info-alur strong { display: block; margin-bottom: 6px; font-size: 14px; }
        .info-alur ol { margin: 0; padding-left: 18px; }
        .info-alur li { margin-bottom: 3px; }

        .blocked-form {
            opacity: 0.5;
            pointer-events: none;
            user-select: none;
        }
        .alert-blocked {
            background: #f8d7da;
            border: 1px solid #f5c2c7;
            border-left: 4px solid #dc3545;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #842029;
        }

        /* Status badge periode */
        .badge-pendaftaran { background:#fff3cd;color:#664d03; }
        .badge-berjalan    { background:#cff4fc;color:#055160; }
        .badge-selesai     { background:#d1e7dd;color:#0a3622; }

        .periode-row-selesai { opacity: 0.7; }
        .konfirmasi-kk { font-size:12px; }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">⚙️ Admin Panel</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard_admin.php"><span>👥</span> Data Siswa</a></li>
            <li><a href="admin_persiapan_diklat.php" class="active"><span>📅</span> Persiapan Diklat</a></li>
            <li><a href="admin_evaluasi.php"><span>📝</span> Evaluasi Nilai</a></li>
            <li><a href="../arsip_laporan.php"><span>🗂️</span> Arsip Laporan</a></li>
            <li><a href="../ganti_password.php"><span>🔒</span> Ganti Password</a></li>
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
                <h1 class="page-title">Persiapan Diklat</h1>
            </div>
            <div>
                <span style="font-size:13px;color:var(--text-muted);font-weight:500;">
                    Admin: <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
        </header>

        <div class="content-body">

    <!-- Info alur periode -->
    <div class="info-alur">
        <strong>📋 Alur Pergantian Periode Diklat</strong>
        <ol>
            <li>Admin buat periode baru → status <strong>Pendaftaran</strong></li>
            <li>Tanggal mulai tiba → otomatis <strong>Berjalan</strong></li>
            <li>Kegiatan selesai → <strong>Kepala Keamanan</strong> konfirmasi selesai</li>
            <li>Baru Admin bisa buat periode berikutnya</li>
        </ol>
    </div>

    <?php if(isset($_SESSION['error_periode'])): ?>
    <div class="alert-blocked">
        ⚠️ <?php echo $_SESSION['error_periode']; unset($_SESSION['error_periode']); ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Form Tambah Periode -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-1">Tambah Periode Diklat</h5>

                    <?php if($periode_belum_selesai): ?>
                    <div class="alert-blocked mb-3">
                        🔒 Tidak dapat membuat periode baru.<br>
                        Periode <strong>
                            <?php echo $periode_aktif_cek['tahun']; ?> —
                            Gelombang <?php echo $periode_aktif_cek['gelombang']; ?>
                        </strong>
                        masih <strong><?php echo $periode_aktif_cek['status']; ?></strong>.<br>
                        Tunggu sampai Kepala Keamanan mengkonfirmasi selesai.
                    </div>
                    <?php else: ?>
                    <p style="font-size:12px;color:#198754;margin-bottom:16px;">
                        ✅ Tidak ada periode aktif. Anda dapat membuat periode baru.
                    </p>
                    <?php endif; ?>

                    <form method="POST"
                          class="<?php echo $periode_belum_selesai ? 'blocked-form' : ''; ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Tahun</label>
                                <input type="number" name="tahun" class="form-control"
                                       placeholder="2026" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Gelombang</label>
                                <input type="number" name="gelombang" class="form-control"
                                       min="1" max="4" placeholder="1" required>
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
                            <div class="form-text">Batas admin menyelesaikan verifikasi dokumen.</div>
                        </div>

                        <div class="mb-3">
                            <label>Biaya Diklat (Rp)</label>
                            <input type="number" name="biaya" class="form-control" placeholder="3500000">
                        </div>

                        <div class="mb-3">
                            <label>Lokasi Spesifik</label>
                            <input type="text" name="lokasi_spesifik" class="form-control"
                                   placeholder="Contoh: Pusdiklat Polda DIY">
                        </div>

                        <div class="mb-3">
                            <label>Lokasi Pengambilan Fasilitas</label>
                            <input type="text" name="lokasi_fasilitas" class="form-control"
                                   placeholder="Contoh: Gudang Logistik Lt.1">
                        </div>

                        <div class="mb-3">
                            <label>Fasilitas yang Diberikan</label>
                            <textarea name="fasilitas" class="form-control" rows="3"
                                      placeholder="Contoh: Seragam PDH, Modul, Konsumsi 1x/hari"></textarea>
                        </div>

                        <div class="mb-4">
                            <label>Informasi Kebutuhan Peserta</label>
                            <textarea name="info_kebutuhan" class="form-control" rows="3"
                                      placeholder="Contoh: Pakaian olahraga 2 stel, Sepatu PDH hitam"></textarea>
                        </div>

                        <button class="btn btn-warning w-100 fw-bold" name="tambah_periode">
                            + Buat Periode Baru
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Form Tambah Jadwal -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Tambah Rundown Kegiatan</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label>Pilih Periode</label>
                            <select name="id_periode" class="form-select">
                                <?php
                                mysqli_data_seek($semua_periode_form, 0);
                                while($p = mysqli_fetch_assoc($semua_periode_form)):
                                ?>
                                <option value="<?php echo $p['id_periode']; ?>">
                                    <?php echo $p['tahun']; ?> — Gelombang <?php echo $p['gelombang']; ?>
                                    (<?php echo ucfirst($p['status']); ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Tanggal</label>
                            <input type="date" name="tanggal" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>Kegiatan</label>
                            <input type="text" name="kegiatan" class="form-control"
                                   placeholder="Contoh: Latihan Fisik">
                        </div>
                        <div class="mb-3">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"
                                      placeholder="Lokasi, instruktur, dll."></textarea>
                        </div>
                        <button class="btn btn-warning w-100 fw-bold" name="tambah_jadwal">
                            + Tambah Jadwal
                        </button>

                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- Tabel Daftar Periode -->
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
                            <th>Peserta</th>
                            <th>Status</th>
                            <th>Konfirmasi KK</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    mysqli_data_seek($periode, 0);
                    while($p = mysqli_fetch_assoc($periode)):
                        $row_class = $p['status'] === 'selesai' ? 'periode-row-selesai' : '';
                    ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><strong><?php echo $p['tahun']; ?></strong></td>
                            <td><?php echo $p['gelombang']; ?></td>
                            <td style="font-size:12px;">
                                <?php echo $p['tanggal_mulai']; ?> s/d <?php echo $p['tanggal_selesai']; ?>
                            </td>
                            <td><?php echo $p['jml_peserta']; ?> orang</td>
                            <td>
                                <span class="badge badge-<?php echo $p['status']; ?>"
                                      style="padding:5px 12px;border-radius:12px;font-size:12px;font-weight:600;">
                                    <?php
                                    $icons = ['pendaftaran'=>'📋','berjalan'=>'🟢','selesai'=>'✅'];
                                    echo ($icons[$p['status']]??'') . ' ' . ucfirst($p['status']);
                                    ?>
                                </span>
                            </td>
                            <td class="konfirmasi-kk">
                                <?php if($p['dikonfirmasi_kepala']): ?>
                                    <span style="color:#198754;font-weight:600;">✅ Sudah</span>
                                <?php elseif($p['status'] === 'selesai'): ?>
                                    <span style="color:#dc3545;">⏳ Belum</span>
                                <?php else: ?>
                                    <span style="color:#adb5bd;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <?php if($p['status'] === 'pendaftaran'): ?>
                                    <a href="?set_status=<?php echo $p['id_periode']; ?>&status=berjalan"
                                       class="btn btn-sm btn-success"
                                       title="Mulai Diklat Manual">▶️ Mulai</a>
                                    <?php elseif($p['status'] === 'berjalan'): ?>
                                    <a href="?set_status=<?php echo $p['id_periode']; ?>&status=selesai"
                                       class="btn btn-sm btn-primary"
                                       title="Selesaikan Diklat Manual">✅ Selesai</a>
                                    <?php endif; ?>
                                    <?php if($p['status'] !== 'berjalan'): ?>
                                    <a href="#"
                                       onclick="konfirmasiHapus('?hapus_periode=<?php echo $p['id_periode']; ?>')"
                                       class="btn btn-sm btn-danger">🗑️</a>
                                    <?php endif; ?>
                                </div>
                            </td>

                        </tr>
                    <?php endwhile; ?>
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
                    <?php while($j = mysqli_fetch_assoc($jadwal)): ?>
                        <tr>
                            <td style="font-size:13px;"><?php echo $j['tanggal']; ?></td>
                            <td><?php echo htmlspecialchars($j['kegiatan']); ?></td>
                            <td style="font-size:12px;color:#6c757d;">
                                <?php echo htmlspecialchars($j['keterangan']); ?>
                            </td>
                            <td><?php echo $j['tahun']; ?> — G<?php echo $j['gelombang']; ?></td>
                            <td>
                                <a href="#"
                                   onclick="konfirmasiHapus('?hapus_jadwal=<?php echo $j['id_jadwal']; ?>')"
                                   class="btn btn-sm btn-danger">Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar    = document.getElementById('sidebar');
menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && !sidebar.contains(e.target) && e.target !== menuToggle)
        sidebar.classList.remove('open');
});
document.getElementById('btnLogout').addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title:'Keluar dari Sistem?', text:"Anda akan mengakhiri sesi. Lanjutkan?", icon:'warning',
        showCancelButton:true, confirmButtonColor:'#dc3545', cancelButtonColor:'#6c757d',
        confirmButtonText:'Ya, Logout', cancelButtonText:'Batal', reverseButtons:true
    }).then((r) => { if(r.isConfirmed) window.location.href='../logout.php'; });
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
        if (result.isConfirmed) window.location.href = url;
    });
}
</script>
</body>
</html>
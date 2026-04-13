<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'polda') {
    header("Location: ../login.php");
    exit;
}

$id_akun_polda = (int) $_SESSION['id_akun'];

$semua_periode = mysqli_query($conn,
    "SELECT * FROM periode_diklat ORDER BY tahun DESC, gelombang DESC"
);

$id_periode_aktif = isset($_GET['periode']) ? (int) $_GET['periode'] : 0;
if (!$id_periode_aktif) {
    $cari = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_periode FROM periode_diklat
         WHERE status='berjalan'
         ORDER BY tahun DESC, gelombang DESC LIMIT 1"
    ));
    if (!$cari) {
        $cari = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT id_periode FROM periode_diklat ORDER BY tahun DESC, gelombang DESC LIMIT 1"
        ));
    }
    $id_periode_aktif = $cari ? (int) $cari['id_periode'] : 0;
}

$periode = null;
if ($id_periode_aktif) {
    $periode = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM periode_diklat WHERE id_periode='$id_periode_aktif'"
    ));
}

$pesan_sukses = '';
$pesan_error  = '';

/* ============================================================
   PROSES: SIMPAN/UPDATE LAPORAN LPJ
   ============================================================ */
if (isset($_POST['simpan_laporan'])) {
    $id_p        = (int) $_POST['id_periode'];
    $pengeluaran = (int) preg_replace('/\D/', '', $_POST['pengeluaran']);
    $pemasukan   = (int) preg_replace('/\D/', '', $_POST['pemasukan']);
    $deskripsi   = mysqli_real_escape_string($conn, trim($_POST['deskripsi_kegiatan']));
    $file_path   = '';

    if (!empty($_FILES['file_laporan']['name'])) {
        $ext = strtolower(pathinfo($_FILES['file_laporan']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
            $folder = "../uploads/laporan_polda";
            if (!is_dir($folder)) mkdir($folder, 0777, true);
            $fname = "laporan_polda_{$id_p}_" . time() . ".$ext";
            if (move_uploaded_file($_FILES['file_laporan']['tmp_name'], "$folder/$fname")) {
                $file_path = "$folder/$fname";
            }
        } else {
            $pesan_error = "Format file harus PDF, JPG, atau PNG.";
            goto tampilForm;
        }
    }

    $file_safe = mysqli_real_escape_string($conn, $file_path);
    $existing  = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_laporan_polda FROM laporan_polda WHERE id_periode='$id_p'"
    ));

    if ($existing) {
        $upd_file = $file_path ? ", file_laporan='$file_safe'" : "";
        mysqli_query($conn,
            "UPDATE laporan_polda
             SET pengeluaran='$pengeluaran', pemasukan='$pemasukan',
                 deskripsi_kegiatan='$deskripsi' $upd_file
             WHERE id_periode='$id_p'"
        );
    } else {
        mysqli_query($conn,
            "INSERT INTO laporan_polda
                (id_periode,id_akun_polda,pengeluaran,pemasukan,deskripsi_kegiatan,file_laporan)
             VALUES
                ('$id_p','$id_akun_polda','$pengeluaran','$pemasukan','$deskripsi','$file_safe')"
        );
    }

    /* Pastikan record laporan utama ada */
    $cek_lap = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_laporan FROM laporan WHERE id_periode='$id_p'"
    ));
    if (!$cek_lap) {
        mysqli_query($conn,
            "INSERT INTO laporan (tgl_generate, id_periode) VALUES (NOW(), '$id_p')"
        );
    }

    header("Location: dashboard_polda.php?periode=$id_p&ok=1");
    exit;
}

tampilForm:

/* Load laporan yang sudah ada */
$laporan_polda = null;
if ($id_periode_aktif) {
    $laporan_polda = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM laporan_polda WHERE id_periode='$id_periode_aktif'"
    ));
}

/* Statistik peserta periode ini (untuk info) */
$total_peserta = 0;
if ($id_periode_aktif) {
    $r = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS jml FROM peserta_periode WHERE id_periode='$id_periode_aktif'"
    ));
    $total_peserta = (int) $r['jml'];
}

if (isset($_GET['ok'])) $pesan_sukses = "Laporan kegiatan berhasil disimpan.";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Polda DIY — Laporan Diklat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/fase4b.css">
    <link rel="stylesheet" href="../css/dashboard_layout.css">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body>

<div class="dashboard-wrapper">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            🚔 Polda DIY 
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_polda.php" class="active">
                    <span>📋</span> Laporan Kegiatan
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
                <h1 class="page-title">Input Laporan Kegiatan</h1>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                    Polda: <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
        </header>

        <div class="content-body">

<div class="container pb-5">

    <?php if ($pesan_sukses): ?>
    <div class="alert-konfirmasi-done mb-3">✅ <?php echo htmlspecialchars($pesan_sukses); ?></div>
    <?php endif; ?>
    <?php if ($pesan_error): ?>
    <div class="alert alert-danger" style="border-radius:10px;font-size:14px;">
        ⚠️ <?php echo htmlspecialchars($pesan_error); ?>
    </div>
    <?php endif; ?>

    <!-- Tab Periode -->
    <div class="periode-tabs">
        <?php
        mysqli_data_seek($semua_periode, 0);
        while ($p = mysqli_fetch_assoc($semua_periode)):
            $active = ($p['id_periode'] == $id_periode_aktif) ? 'active' : '';
            $icon = ['pendaftaran'=>'📋','berjalan'=>'🟢','selesai'=>'✅'][$p['status']] ?? '';
        ?>
        <a href="?periode=<?php echo $p['id_periode']; ?>"
           class="periode-tab <?php echo $active; ?>">
            <?php echo "$icon {$p['tahun']} — G{$p['gelombang']}"; ?>
        </a>
        <?php endwhile; ?>
    </div>

    <?php if (!$id_periode_aktif || !$periode): ?>
    <div class="card4b text-center py-5 text-muted">
        <p>Belum ada periode diklat. Hubungi admin.</p>
    </div>
    <?php else: ?>

    <!-- Info periode -->
    <div class="laporan-info-card mb-4">
        <div class="info-row">
            <span class="info-label">Periode</span>
            <span class="info-val"><?php echo $periode['tahun']; ?> Gelombang <?php echo $periode['gelombang']; ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Tanggal</span>
            <span class="info-val"><?php echo $periode['tanggal_mulai']; ?> s/d <?php echo $periode['tanggal_selesai']; ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Lokasi</span>
            <span class="info-val"><?php echo htmlspecialchars($periode['lokasi_spesifik'] ?: '-'); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Total Peserta</span>
            <span class="info-val"><strong><?php echo $total_peserta; ?> orang</strong></span>
        </div>
        <div class="info-row">
            <span class="info-label">Status Periode</span>
            <span class="info-val"><strong style="text-transform:capitalize"><?php echo $periode['status']; ?></strong></span>
        </div>
    </div>

    <div class="row g-4">

        <!-- Form LPJ -->
        <div class="col-lg-7">
            <div class="card4b">
                <div class="card-header">📄 Form Laporan Pertanggungjawaban (LPJ)</div>
                <div class="card-body">

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id_periode" value="<?php echo $id_periode_aktif; ?>">

                        <div class="section-lbl">Keuangan Kegiatan</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold" style="font-size:13px;">
                                    Pemasukan (Rp)
                                </label>
                                <input type="number" name="pemasukan" class="form-control"
                                       placeholder="Contoh: 50000000" style="border-radius:10px"
                                       value="<?php echo $laporan_polda ? $laporan_polda['pemasukan'] : ''; ?>"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold" style="font-size:13px;">
                                    Pengeluaran (Rp)
                                </label>
                                <input type="number" name="pengeluaran" class="form-control"
                                       placeholder="Contoh: 45000000" style="border-radius:10px"
                                       value="<?php echo $laporan_polda ? $laporan_polda['pengeluaran'] : ''; ?>"
                                       required>
                            </div>
                        </div>

                        <div class="section-lbl">Deskripsi & Ringkasan Kegiatan</div>
                        <div class="mb-3">
                            <textarea name="deskripsi_kegiatan" class="form-control" rows="7"
                                      placeholder="Isi ringkasan pelaksanaan diklat, jumlah peserta yang hadir, kendala, hasil kegiatan, catatan lapangan, dll."
                                      style="border-radius:10px;font-size:13px;" required><?php
                                echo $laporan_polda ? htmlspecialchars($laporan_polda['deskripsi_kegiatan']) : '';
                            ?></textarea>
                        </div>

                        <div class="section-lbl">Upload File Laporan</div>
                        <div class="upload-zone mb-3"
                             id="uzLaporan"
                             onclick="document.getElementById('fileLaporan').click()">
                            <input type="file" name="file_laporan" id="fileLaporan"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   onchange="previewFile(this)">
                            <div class="uz-icon">📎</div>
                            <div class="uz-text" id="uzText">
                                <?php if ($laporan_polda && $laporan_polda['file_laporan']): ?>
                                    File sudah ada — klik untuk ganti
                                <?php else: ?>
                                    Klik untuk pilih file PDF/JPG/PNG · Maks 10MB
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($laporan_polda && $laporan_polda['file_laporan']): ?>
                        <div class="mb-3">
                            <a href="<?php echo htmlspecialchars($laporan_polda['file_laporan']); ?>"
                               target="_blank" style="font-size:13px;">
                               📄 Lihat file laporan yang sudah diupload
                            </a>
                        </div>
                        <?php endif; ?>

                        <button type="submit" name="simpan_laporan" class="btn-konfirmasi">
                            💾 <?php echo $laporan_polda ? 'Perbarui Laporan' : 'Simpan Laporan'; ?>
                        </button>
                    </form>

                </div>
            </div>
        </div>

        <!-- Panel kanan: ringkasan & panduan -->
        <div class="col-lg-5">

            <?php if ($laporan_polda): ?>
            <!-- Ringkasan laporan tersimpan -->
            <div class="card4b mb-3">
                <div class="card-header">📊 Ringkasan Laporan Tersimpan</div>
                <div class="card-body">
                    <div class="laporan-info-card">
                        <div class="info-row">
                            <span class="info-label">Pemasukan</span>
                            <span class="info-val" style="color:#198754;font-weight:700;">
                                Rp <?php echo number_format($laporan_polda['pemasukan'], 0, ',', '.'); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Pengeluaran</span>
                            <span class="info-val" style="color:#dc3545;font-weight:700;">
                                Rp <?php echo number_format($laporan_polda['pengeluaran'], 0, ',', '.'); ?>
                            </span>
                        </div>
                        <?php
                        $selisih = $laporan_polda['pemasukan'] - $laporan_polda['pengeluaran'];
                        $warna   = $selisih >= 0 ? '#198754' : '#dc3545';
                        $label   = $selisih >= 0 ? 'Surplus' : 'Defisit';
                        ?>
                        <div class="info-row">
                            <span class="info-label">Selisih</span>
                            <span class="info-val">
                                <strong style="color:<?php echo $warna; ?>">
                                    Rp <?php echo number_format(abs($selisih), 0, ',', '.'); ?>
                                    (<?php echo $label; ?>)
                                </strong>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Terakhir diperbarui</span>
                            <span class="info-val"><?php echo $laporan_polda['tgl_input']; ?></span>
                        </div>
                    </div>

                    <div style="background:#d1e7dd;border-radius:10px;padding:12px 16px;font-size:13px;color:#0a3622;">
                        ✅ Laporan sudah tersimpan dan dapat dilihat oleh Admin serta Kepala Keamanan.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Panduan pengisian -->
            <div class="card4b">
                <div class="card-header">📋 Panduan Pengisian LPJ</div>
                <div class="card-body">
                    <div class="section-lbl">Yang harus dilaporkan</div>
                    <ol style="font-size:13px;color:#495057;padding-left:18px;margin:0;">
                        <li class="mb-2">
                            <strong>Pemasukan:</strong> Total dana yang masuk untuk kegiatan
                            (biaya peserta, sponsorship, dll.)
                        </li>
                        <li class="mb-2">
                            <strong>Pengeluaran:</strong> Total biaya yang dikeluarkan
                            (fasilitas, konsumsi, instruktur, dll.)
                        </li>
                        <li class="mb-2">
                            <strong>Deskripsi:</strong> Ringkasan pelaksanaan, jumlah kehadiran,
                            kendala, dan hasil kegiatan
                        </li>
                        <li class="mb-2">
                            <strong>File laporan:</strong> Upload dokumen LPJ resmi dalam format
                            PDF (disarankan)
                        </li>
                    </ol>
                    <hr style="margin:16px 0;">
                    <div class="section-lbl">Alur setelah laporan disubmit</div>
                    <ol style="font-size:12px;color:#6c757d;padding-left:18px;margin:0;">
                        <li class="mb-1">Admin mereview laporan</li>
                        <li class="mb-1">Admin menginput nilai per siswa</li>
                        <li class="mb-1">Kepala Keamanan mengkonfirmasi & menutup periode</li>
                        <li>CEO melihat laporan akhir</li>
                    </ol>
                </div>
            </div>

        </div>
    </div>

    <?php endif; ?>

</div>

    </div> <!-- End container -->

        </div> <!-- End content-body -->
    </main>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
function previewFile(input) {
    const uz = document.getElementById('uzLaporan');
    const txt = document.getElementById('uzText');
    if (input.files && input.files[0]) {
        const f = input.files[0];
        if (f.size > 10 * 1024 * 1024) {
            alert('File terlalu besar. Maksimal 10MB.');
            input.value = '';
            return;
        }
        uz.classList.add('has-file');
        txt.textContent = '✅ ' + f.name + ' (' + (f.size/1024).toFixed(0) + ' KB)';
    }
}

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
</script>
</body>
</html>
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'kepala_keamanan') {
    header("Location: ../login.php");
    exit;
}

$semua_periode = mysqli_query($conn,
    "SELECT * FROM periode_diklat ORDER BY tahun DESC, gelombang DESC"
);

$id_periode_aktif = isset($_GET['periode']) ? (int) $_GET['periode'] : 0;
if (!$id_periode_aktif) {
    $tmp = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_periode FROM periode_diklat ORDER BY tahun DESC, gelombang DESC LIMIT 1"
    ));
    $id_periode_aktif = $tmp ? (int) $tmp['id_periode'] : 0;
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
   PROSES: KONFIRMASI SELESAI PERIODE + UPLOAD SURAT PERNYATAAN
   ============================================================ */
if (isset($_POST['konfirmasi_selesai'])) {
    $id_p        = (int) $_POST['id_periode'];
    $file_surat  = '';

    if (!empty($_FILES['surat_pernyataan']['name'])) {
        $ext = strtolower(pathinfo($_FILES['surat_pernyataan']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf','jpg','jpeg','png'])) {
            $folder = "../uploads/surat_pernyataan";
            if (!is_dir($folder)) mkdir($folder, 0777, true);
            $fname = "surat_pernyataan_periode_{$id_p}_" . time() . ".$ext";
            if (move_uploaded_file($_FILES['surat_pernyataan']['tmp_name'], "$folder/$fname")) {
                $file_surat = "$folder/$fname";
            }
        }
    }

    $tgl_konfirmasi = date('Y-m-d H:i:s');
    $file_surat_esc = mysqli_real_escape_string($conn, $file_surat);

    /* Update tabel laporan */
    $cek_lap = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_laporan FROM laporan WHERE id_periode='$id_p'"
    ));

    if ($cek_lap) {
        $upd_surat = $file_surat ? ", file_surat_pernyataan='$file_surat_esc'" : "";
        mysqli_query($conn,
            "UPDATE laporan
             SET dikonfirmasi_kepala=1,
                 tgl_konfirmasi_kepala='$tgl_konfirmasi'
                 $upd_surat
             WHERE id_periode='$id_p'"
        );
    } else {
        mysqli_query($conn,
            "INSERT INTO laporan
                (tgl_generate, id_periode, dikonfirmasi_kepala,
                 tgl_konfirmasi_kepala, file_surat_pernyataan)
             VALUES
                (NOW(), '$id_p', 1, '$tgl_konfirmasi', '$file_surat_esc')"
        );
    }

    /* Update status periode menjadi selesai */
    mysqli_query($conn,
        "UPDATE periode_diklat SET status='selesai' WHERE id_periode='$id_p'"
    );

    header("Location: dashboard_kepala.php?periode=$id_p&ok=1");
    exit;
}

/* ============================================================
   LOAD DATA
   ============================================================ */
$laporan = null;
$laporan_polda = null;
$daftar_siswa = [];
$laporan_dikonfirmasi = false;

if ($id_periode_aktif) {
    $laporan = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM laporan WHERE id_periode='$id_periode_aktif' ORDER BY id_laporan DESC LIMIT 1"
    ));
    $laporan_polda = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT lp.*, a.username AS akun_polda
         FROM laporan_polda lp
         LEFT JOIN akun a ON lp.id_akun_polda = a.id_akun
         WHERE lp.id_periode='$id_periode_aktif'"
    ));

    $q = mysqli_query($conn,
        "SELECT s.nama, s.email,
                e.nilai_fisik, e.nilai_disiplin, e.nilai_teori, e.nilai_praktik,
                e.rata_rata, e.hasil, e.catatan, e.tgl_input, e.dikonfirmasi_admin
         FROM siswa s
         JOIN peserta_periode pp ON s.id_peserta = pp.id_peserta
         LEFT JOIN evaluasi e ON e.id_siswa = s.id_peserta AND e.id_periode='$id_periode_aktif'
         WHERE pp.id_periode='$id_periode_aktif'
         AND s.status IN ('peserta','lulus','tidak_lulus')
         ORDER BY s.nama ASC"
    );
    while ($r = mysqli_fetch_assoc($q)) $daftar_siswa[] = $r;

    $laporan_dikonfirmasi = $laporan && $laporan['dikonfirmasi_kepala'];
}

/* Stats */
$total   = count($daftar_siswa);
$lulus   = 0; $tidak_lulus = 0; $belum_nilai = 0; $dikonfirmasi_admin = 0;
foreach ($daftar_siswa as $ds) {
    if (!$ds['id_evaluasi'] ?? !$ds['tgl_input'])  $belum_nilai++;
    if ($ds['hasil'] === 'lulus')                  $lulus++;
    if ($ds['hasil'] === 'tidak_lulus')            $tidak_lulus++;
    if ($ds['dikonfirmasi_admin'])                 $dikonfirmasi_admin++;
}

$semua_dikonfirmasi = ($total > 0 && $dikonfirmasi_admin === $total);
$ada_laporan_polda  = ($laporan_polda !== null);

if (isset($_GET['ok'])) $pesan_sukses = "Periode berhasil dikonfirmasi selesai. Laporan tersedia untuk CEO.";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Kepala Keamanan</title>
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
            🛡️ Ka. Keamanan
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_kepala.php" class="active">
                    <span>📋</span> Laporan Diklat
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
                <h1 class="page-title">Konfirmasi Hasil Diklat</h1>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                    Kepala: <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
        </header>

        <div class="content-body">

<div class="container pb-5">

    <?php if ($pesan_sukses): ?>
    <div class="alert-konfirmasi-done mb-3">✅ <?php echo htmlspecialchars($pesan_sukses); ?></div>
    <?php endif; ?>

    <!-- Tab periode -->
    <div class="periode-tabs">
        <?php
        mysqli_data_seek($semua_periode, 0);
        while ($p = mysqli_fetch_assoc($semua_periode)):
            $active = ($p['id_periode'] == $id_periode_aktif) ? 'active' : '';
            $icon   = ['pendaftaran'=>'📋','berjalan'=>'🟢','selesai'=>'✅'][$p['status']] ?? '';
        ?>
        <a href="?periode=<?php echo $p['id_periode']; ?>"
           class="periode-tab <?php echo $active; ?>">
            <?php echo "$icon {$p['tahun']} — G{$p['gelombang']}"; ?>
        </a>
        <?php endwhile; ?>
    </div>

    <?php if (!$id_periode_aktif || !$periode): ?>
    <div class="card4b text-center py-5 text-muted"><p>Belum ada periode diklat.</p></div>
    <?php else: ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box4b">
            <div class="stat-n"><?php echo $total; ?></div>
            <div class="stat-lbl">Total Peserta</div>
        </div>
        <div class="stat-box4b">
            <div class="stat-n" style="color:#198754"><?php echo $lulus; ?></div>
            <div class="stat-lbl">Lulus</div>
        </div>
        <div class="stat-box4b">
            <div class="stat-n" style="color:#dc3545"><?php echo $tidak_lulus; ?></div>
            <div class="stat-lbl">Tidak Lulus</div>
        </div>
        <div class="stat-box4b">
            <div class="stat-n" style="color:#0d6efd"><?php echo $dikonfirmasi_admin; ?></div>
            <div class="stat-lbl">Dikonfirmasi Admin</div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Kolom kiri: LPJ + konfirmasi -->
        <div class="col-lg-4">

            <!-- LPJ Polda -->
            <div class="card4b mb-3">
                <div class="card-header">📄 Laporan LPJ Polda DIY</div>
                <div class="card-body">
                    <?php if ($laporan_polda): ?>
                    <div class="laporan-info-card">
                        <div class="info-row">
                            <span class="info-label">Pemasukan</span>
                            <span class="info-val">Rp <?php echo number_format($laporan_polda['pemasukan'],0,',','.'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Pengeluaran</span>
                            <span class="info-val">Rp <?php echo number_format($laporan_polda['pengeluaran'],0,',','.'); ?></span>
                        </div>
                        <?php
                        $selisih = $laporan_polda['pemasukan'] - $laporan_polda['pengeluaran'];
                        ?>
                        <div class="info-row">
                            <span class="info-label">Selisih</span>
                            <span class="info-val">
                                <strong style="color:<?php echo $selisih>=0?'#198754':'#dc3545'; ?>">
                                    Rp <?php echo number_format(abs($selisih),0,',','.'); ?>
                                    (<?php echo $selisih>=0?'surplus':'defisit'; ?>)
                                </strong>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Oleh</span>
                            <span class="info-val"><?php echo htmlspecialchars($laporan_polda['akun_polda']??'-'); ?></span>
                        </div>
                    </div>
                    <?php if ($laporan_polda['deskripsi_kegiatan']): ?>
                    <div class="mb-3">
                        <div class="section-lbl">Deskripsi Kegiatan</div>
                        <p style="font-size:13px;color:#495057;line-height:1.6;
                                  background:#f8f9fa;padding:10px;border-radius:8px;margin:0;">
                            <?php echo nl2br(htmlspecialchars($laporan_polda['deskripsi_kegiatan'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    <?php if ($laporan_polda['file_laporan']): ?>
                    <a href="<?php echo htmlspecialchars($laporan_polda['file_laporan']); ?>"
                       target="_blank" class="btn btn-sm btn-outline-primary w-100" style="border-radius:8px;">
                        📎 Unduh File LPJ
                    </a>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <p style="font-size:13px;">Laporan LPJ belum dikirim Polda.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Konfirmasi selesai -->
            <?php if (!$laporan_dikonfirmasi): ?>
            <div class="card4b">
                <div class="card-header">✅ Konfirmasi Selesai Periode</div>
                <div class="card-body">

                    <?php if (!$ada_laporan_polda): ?>
                    <div style="background:#fff3cd;border-radius:8px;padding:12px;font-size:13px;color:#664d03;margin-bottom:12px;">
                        ⚠️ LPJ dari Polda belum tersedia. Pastikan Polda sudah mengirim laporan.
                    </div>
                    <?php endif; ?>

                    <?php if ($dikonfirmasi_admin < $total && $total > 0): ?>
                    <div style="background:#cff4fc;border-radius:8px;padding:12px;font-size:13px;color:#055160;margin-bottom:12px;">
                        ℹ️ <?php echo $total - $dikonfirmasi_admin; ?> nilai siswa belum dikonfirmasi admin.
                    </div>
                    <?php endif; ?>

                    <p style="font-size:13px;color:#495057;margin-bottom:16px;">
                        Konfirmasi bahwa seluruh kegiatan diklat periode ini telah selesai.
                        Upload surat pernyataan resmi sebagai dokumen penutup.
                    </p>

                    <form method="POST" enctype="multipart/form-data"
                          onsubmit="return confirm('Konfirmasi selesainya periode diklat ini?\n\nTindakan ini akan mengunci periode dan dapat dilihat oleh CEO.')">
                        <input type="hidden" name="id_periode" value="<?php echo $id_periode_aktif; ?>">

                        <div class="mb-3">
                            <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">
                                Upload Surat Pernyataan Selesai
                                <span style="font-weight:400;color:#6c757d;">(opsional)</span>
                            </label>
                            <div class="upload-zone" onclick="document.getElementById('fileSurat').click()">
                                <input type="file" name="surat_pernyataan" id="fileSurat"
                                       accept=".pdf,.jpg,.jpeg,.png"
                                       onchange="previewSurat(this)">
                                <div class="uz-icon">📝</div>
                                <div class="uz-text" id="suratText">
                                    Klik untuk upload surat pernyataan
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="konfirmasi_selesai" class="btn-konfirmasi">
                            ✅ Konfirmasi Periode Selesai
                        </button>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- Sudah dikonfirmasi -->
            <div class="card4b">
                <div class="card-body" style="background:#d1e7dd;border-radius:14px;">
                    <p style="font-size:14px;font-weight:700;color:#0a3622;margin-bottom:8px;">
                        ✅ Periode Sudah Dikonfirmasi Selesai
                    </p>
                    <p style="font-size:13px;color:#155724;margin-bottom:8px;">
                        Dikonfirmasi pada: <?php echo $laporan['tgl_konfirmasi_kepala']; ?>
                    </p>
                    <?php if ($laporan['file_surat_pernyataan']): ?>
                    <a href="<?php echo htmlspecialchars($laporan['file_surat_pernyataan']); ?>"
                       target="_blank" class="btn btn-sm btn-success w-100" style="border-radius:8px;">
                        📝 Lihat Surat Pernyataan
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Kolom kanan: tabel penilaian siswa -->
        <div class="col-lg-8">
            <div class="card4b">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>📊 Hasil Penilaian Siswa</span>
                    <small class="text-muted" style="font-size:12px;font-weight:400;">
                        Lulus ≥ 80
                    </small>
                </div>
                <div class="card-body p-0">

                    <?php if (empty($daftar_siswa)): ?>
                    <div class="text-center py-5 text-muted">
                        <div style="font-size:36px;margin-bottom:12px;">👥</div>
                        <p>Belum ada peserta di periode ini.</p>
                    </div>
                    <?php else: ?>

                    <div class="table-scroll p-3">
                    <table class="table table4b table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th class="text-center">Fisik</th>
                                <th class="text-center">Disiplin</th>
                                <th class="text-center">Teori</th>
                                <th class="text-center">Praktik</th>
                                <th class="text-center">Rata-rata</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Konfirmasi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($daftar_siswa as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($s['nama']); ?></div>
                                <div style="font-size:11px;color:#6c757d;"><?php echo htmlspecialchars($s['email']); ?></div>
                            </td>
                            <td class="text-center"><?php echo $s['nilai_fisik'] ?? '—'; ?></td>
                            <td class="text-center"><?php echo $s['nilai_disiplin'] ?? '—'; ?></td>
                            <td class="text-center"><?php echo $s['nilai_teori'] ?? '—'; ?></td>
                            <td class="text-center"><?php echo $s['nilai_praktik'] ?? '—'; ?></td>
                            <td class="text-center">
                                <strong><?php echo $s['rata_rata'] ?? '—'; ?></strong>
                            </td>
                            <td class="text-center">
                                <?php if ($s['hasil'] === 'lulus'): ?>
                                    <span class="badge-status badge-lulus">✅ Lulus</span>
                                <?php elseif ($s['hasil'] === 'tidak_lulus'): ?>
                                    <span class="badge-status badge-tidak-lulus">❌ Tidak Lulus</span>
                                <?php else: ?>
                                    <span class="badge-status badge-belum">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($s['dikonfirmasi_admin']): ?>
                                    <span class="badge-status badge-dikonfirmasi">✓</span>
                                <?php else: ?>
                                    <span style="font-size:12px;color:#6c757d;">Belum</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <!-- Ringkasan persentase -->
                    <?php if ($total > 0): ?>
                    <div style="background:#f8f9fa;padding:16px 20px;border-top:1px solid #eee;
                                display:flex;gap:24px;font-size:13px;">
                        <div>
                            <span style="color:#198754;font-weight:700;"><?php echo $lulus; ?></span>
                            <span style="color:#6c757d;"> lulus</span>
                        </div>
                        <div>
                            <span style="color:#dc3545;font-weight:700;"><?php echo $tidak_lulus; ?></span>
                            <span style="color:#6c757d;"> tidak lulus</span>
                        </div>
                        <div>
                            <span style="color:#0d6efd;font-weight:700;">
                                <?php echo $total > 0 ? round($lulus/$total*100) : 0; ?>%
                            </span>
                            <span style="color:#6c757d;"> tingkat kelulusan</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

</div>

    </div> <!-- End container pb-5 -->

        </div> <!-- End content-body -->
    </main>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
function previewSurat(input) {
    var txt = document.getElementById('suratText');
    var uz  = document.querySelector('.upload-zone');
    if (input.files && input.files[0]) {
        var f = input.files[0];
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
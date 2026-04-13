<?php
session_start();
include "../koneksi.php";
include "../helpers.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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
   PROSES 1: SIMPAN / UPDATE NILAI SATU SISWA
   ============================================================ */
if (isset($_POST['simpan_nilai'])) {
    $id_s           = (int) $_POST['id_siswa'];
    $id_p           = (int) $_POST['id_periode'];
    $nilai_fisik    = max(0, min(100, (float) $_POST['nilai_fisik']));
    $nilai_disiplin = max(0, min(100, (float) $_POST['nilai_disiplin']));
    $nilai_teori    = max(0, min(100, (float) $_POST['nilai_teori']));
    $nilai_praktik  = max(0, min(100, (float) $_POST['nilai_praktik']));
    $catatan        = mysqli_real_escape_string($conn, trim($_POST['catatan'] ?? ''));
    $rata_rata      = round(($nilai_fisik + $nilai_disiplin + $nilai_teori + $nilai_praktik) / 4, 2);
    $hasil          = $rata_rata >= 80 ? 'lulus' : 'tidak_lulus'; /* threshold 80 */
    $tgl_input      = date('Y-m-d');

    $cek = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_evaluasi FROM evaluasi WHERE id_siswa='$id_s' AND id_periode='$id_p'"
    ));

    if ($cek) {
        mysqli_query($conn,
            "UPDATE evaluasi
             SET nilai_fisik='$nilai_fisik', nilai_disiplin='$nilai_disiplin',
                 nilai_teori='$nilai_teori', nilai_praktik='$nilai_praktik',
                 rata_rata='$rata_rata', hasil='$hasil', catatan='$catatan',
                 tgl_input='$tgl_input', dikonfirmasi_admin=0
             WHERE id_siswa='$id_s' AND id_periode='$id_p'"
        );
    } else {
        mysqli_query($conn,
            "INSERT INTO evaluasi
                (nilai_fisik,nilai_disiplin,nilai_teori,nilai_praktik,
                 rata_rata,hasil,catatan,tgl_input,id_siswa,id_periode,dikonfirmasi_admin)
             VALUES
                ('$nilai_fisik','$nilai_disiplin','$nilai_teori','$nilai_praktik',
                 '$rata_rata','$hasil','$catatan','$tgl_input','$id_s','$id_p',0)"
        );
    }

    header("Location: admin_evaluasi.php?periode=$id_p&ok=nilai");
    exit;
}

/* ============================================================
   PROSES 2: KONFIRMASI NILAI SATU SISWA
   ============================================================ */
if (isset($_POST['konfirmasi_nilai'])) {
    $id_s = (int) $_POST['id_siswa'];
    $id_p = (int) $_POST['id_periode'];

    $ev = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM evaluasi WHERE id_siswa='$id_s' AND id_periode='$id_p' LIMIT 1"
    ));

    if (!$ev) {
        $pesan_error = "Data evaluasi belum diinput.";
        goto tampilForm;
    }

    mysqli_query($conn,
        "UPDATE evaluasi SET dikonfirmasi_admin=1
         WHERE id_siswa='$id_s' AND id_periode='$id_p'"
    );

    $status_baru = ($ev['hasil'] === 'lulus') ? 'lulus' : 'tidak_lulus';
    mysqli_query($conn,
        "UPDATE siswa SET status='$status_baru' WHERE id_peserta='$id_s'"
    );

    header("Location: admin_evaluasi.php?periode=$id_p&ok=konfirmasi");
    exit;
}

/* ============================================================
   PROSES 3: KONFIRMASI SEMUA SEKALIGUS
   ============================================================ */
if (isset($_POST['konfirmasi_semua'])) {
    $id_p = (int) $_POST['id_periode'];

    $q_ev = mysqli_query($conn,
        "SELECT id_evaluasi,id_siswa,hasil FROM evaluasi
         WHERE id_periode='$id_p' AND dikonfirmasi_admin=0"
    );

    $count = 0;
    while ($ev = mysqli_fetch_assoc($q_ev)) {
        mysqli_query($conn,
            "UPDATE evaluasi SET dikonfirmasi_admin=1
             WHERE id_evaluasi='{$ev['id_evaluasi']}'"
        );
        $status_baru = ($ev['hasil'] === 'lulus') ? 'lulus' : 'tidak_lulus';
        mysqli_query($conn,
            "UPDATE siswa SET status='$status_baru' WHERE id_peserta='{$ev['id_siswa']}'"
        );
        $count++;
    }

    /* Update laporan record */
    $cek_lap = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_laporan FROM laporan WHERE id_periode='$id_p'"
    ));
    if (!$cek_lap) {
        mysqli_query($conn,
            "INSERT INTO laporan (tgl_generate,id_periode) VALUES (NOW(),'$id_p')"
        );
    } else {
        mysqli_query($conn,
            "UPDATE laporan SET file_laporan_penilaian='confirmed'
             WHERE id_periode='$id_p'"
        );
    }

    header("Location: admin_evaluasi.php?periode=$id_p&ok=semua&n=$count");
    exit;
}

tampilForm:

/* ============================================================
   LOAD DATA TAMPILAN
   ============================================================ */
$laporan_polda = null;
if ($id_periode_aktif) {
    $laporan_polda = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT lp.*, a.username AS akun_polda
         FROM laporan_polda lp
         LEFT JOIN akun a ON lp.id_akun_polda = a.id_akun
         WHERE lp.id_periode='$id_periode_aktif'"
    ));
}

$daftar_siswa = [];
if ($id_periode_aktif) {
    $q = mysqli_query($conn,
        "SELECT s.id_peserta, s.nama, s.email,
                e.id_evaluasi, e.nilai_fisik, e.nilai_disiplin,
                e.nilai_teori, e.nilai_praktik, e.rata_rata,
                e.hasil, e.catatan, e.dikonfirmasi_admin, e.tgl_input
         FROM siswa s
         JOIN peserta_periode pp ON s.id_peserta = pp.id_peserta
         LEFT JOIN evaluasi e ON e.id_siswa = s.id_peserta AND e.id_periode='$id_periode_aktif'
         WHERE pp.id_periode='$id_periode_aktif'
         AND s.status IN ('peserta','lulus','tidak_lulus')
         ORDER BY s.nama ASC"
    );
    while ($r = mysqli_fetch_assoc($q)) $daftar_siswa[] = $r;
}

$total        = count($daftar_siswa);
$dinilai      = 0;
$dikonfirmasi = 0;
$lulus        = 0;
foreach ($daftar_siswa as $ds) {
    if ($ds['id_evaluasi'])        $dinilai++;
    if ($ds['dikonfirmasi_admin']) $dikonfirmasi++;
    if ($ds['hasil'] === 'lulus')  $lulus++;
}
$bisa_konfirmasi_semua = ($dinilai > 0 && $dinilai > $dikonfirmasi);

if (isset($_GET['ok'])) {
    if ($_GET['ok'] === 'nilai')     $pesan_sukses = "Nilai berhasil disimpan.";
    if ($_GET['ok'] === 'konfirmasi') $pesan_sukses = "Nilai berhasil dikonfirmasi. Status siswa diperbarui.";
    if ($_GET['ok'] === 'semua') {
        $n = (int) ($_GET['n'] ?? 0);
        $pesan_sukses = "$n nilai berhasil dikonfirmasi. Kepala Keamanan dapat melihat laporan penilaian.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin — Input & Evaluasi Nilai</title>
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
            ⚙️ Admin Panel
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_admin.php">
                    <span>👥</span> Data Siswa
                </a>
            </li>
            <li>
                <a href="admin_persiapan_diklat.php">
                    <span>📅</span> Persiapan Diklat
                </a>
            </li>
            <li>
                <a href="admin_evaluasi.php" class="active">
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
                <h1 class="page-title">Input & Evaluasi Nilai</h1>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                    Admin: <?php echo htmlspecialchars($_SESSION['username']); ?>
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

    <!-- Tab periode -->
    <div class="periode-tabs">
        <?php
        mysqli_data_seek($semua_periode, 0);
        while ($p = mysqli_fetch_assoc($semua_periode)):
            $active = ($p['id_periode'] == $id_periode_aktif) ? 'active' : '';
        ?>
        <a href="?periode=<?php echo $p['id_periode']; ?>"
           class="periode-tab <?php echo $active; ?>">
            <?php echo "{$p['tahun']} — G{$p['gelombang']}"; ?>
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
            <div class="stat-n"><?php echo $dinilai; ?></div>
            <div class="stat-lbl">Sudah Dinilai</div>
        </div>
        <div class="stat-box4b">
            <div class="stat-n" style="color:#0d6efd"><?php echo $dikonfirmasi; ?></div>
            <div class="stat-lbl">Dikonfirmasi</div>
        </div>
        <div class="stat-box4b">
            <div class="stat-n" style="color:#198754"><?php echo $lulus; ?></div>
            <div class="stat-lbl">Lulus (≥80)</div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Kolom kiri: LPJ Polda + panduan -->
        <div class="col-lg-4">

            <div class="card4b mb-3">
                <div class="card-header">📄 Laporan Polda DIY (LPJ)</div>
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
                        $warna   = $selisih >= 0 ? '#198754' : '#dc3545';
                        ?>
                        <div class="info-row">
                            <span class="info-label">Selisih</span>
                            <span class="info-val">
                                <strong style="color:<?php echo $warna; ?>">
                                    Rp <?php echo number_format(abs($selisih),0,',','.'); ?>
                                    (<?php echo $selisih >= 0 ? 'surplus' : 'defisit'; ?>)
                                </strong>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Dikirim oleh</span>
                            <span class="info-val"><?php echo htmlspecialchars($laporan_polda['akun_polda'] ?? '-'); ?></span>
                        </div>
                    </div>

                    <?php if ($laporan_polda['deskripsi_kegiatan']): ?>
                    <div class="mb-3">
                        <div class="section-lbl">Deskripsi Kegiatan</div>
                        <p style="font-size:13px;color:#495057;line-height:1.6;
                                  background:#f8f9fa;padding:12px;border-radius:8px;margin:0;">
                            <?php echo nl2br(htmlspecialchars($laporan_polda['deskripsi_kegiatan'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if ($laporan_polda['file_laporan']): ?>
                    <a href="<?php echo htmlspecialchars($laporan_polda['file_laporan']); ?>"
                       target="_blank" class="btn btn-sm btn-outline-primary w-100" style="border-radius:8px;">
                        📎 Unduh File LPJ Polda
                    </a>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <div style="font-size:36px;margin-bottom:10px;">⏳</div>
                        <p class="mb-0" style="font-size:13px;">
                            Laporan LPJ dari Polda DIY belum tersedia.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Konfirmasi semua -->
            <?php if ($bisa_konfirmasi_semua): ?>
            <div class="card4b mb-3">
                <div class="card-body"
                     style="background:#fff3cd;border-radius:14px;border-left:4px solid #ffc107;">
                    <p style="font-size:13px;font-weight:700;color:#664d03;margin-bottom:8px;">
                        ⚡ Konfirmasi Semua Sekaligus
                    </p>
                    <p style="font-size:12px;color:#856404;margin-bottom:12px;">
                        <?php echo $dinilai - $dikonfirmasi; ?> siswa sudah dinilai namun belum dikonfirmasi.
                    </p>
                    <form method="POST"
                          onsubmit="return confirm('Konfirmasi semua nilai?\nTindakan ini tidak bisa dibatalkan.')">
                        <input type="hidden" name="id_periode" value="<?php echo $id_periode_aktif; ?>">
                        <button type="submit" name="konfirmasi_semua" class="btn-konfirmasi"
                                style="background:#ffc107;color:#000;font-weight:700;">
                            ✅ Konfirmasi Semua (<?php echo $dinilai - $dikonfirmasi; ?> siswa)
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Panduan alur -->
            <div class="card4b">
                <div class="card-body">
                    <div class="section-lbl">Alur Input Nilai</div>
                    <ol style="font-size:12px;color:#495057;margin:0;padding-left:18px;">
                        <li class="mb-2">Lihat LPJ dari Polda DIY (kolom kiri)</li>
                        <li class="mb-2">Input nilai per siswa: fisik, disiplin, teori, praktik (0–100)</li>
                        <li class="mb-2">Lulus otomatis jika rata-rata ≥ <strong>80</strong></li>
                        <li class="mb-2">Simpan nilai → konfirmasi per siswa atau semua sekaligus</li>
                        <li>Kepala Keamanan dapat melihat laporan final</li>
                    </ol>
                </div>
            </div>

        </div>

        <!-- Kolom kanan: daftar siswa + form nilai -->
        <div class="col-lg-8">
            <div class="card4b">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>📊 Input Nilai Siswa — Periode <?php echo $periode['tahun']; ?> G<?php echo $periode['gelombang']; ?></span>
                    <small class="text-muted" style="font-weight:400;font-size:12px;">
                        Lulus ≥ 80 rata-rata
                    </small>
                </div>
                <div class="card-body p-0">

                    <?php if (empty($daftar_siswa)): ?>
                    <div class="text-center py-5 text-muted">
                        <div style="font-size:36px;margin-bottom:12px;">👥</div>
                        <p class="mb-0">Belum ada peserta di periode ini.</p>
                    </div>

                    <?php else: ?>
                    <div class="p-3">
                    <?php foreach ($daftar_siswa as $siswa): ?>

                    <div class="siswa-eval-card"
                         style="<?php echo $siswa['dikonfirmasi_admin'] ? 'border-top:4px solid #198754;' : ''; ?>">

                        <!-- Header siswa -->
                        <div class="siswa-info">
                            <div class="siswa-avatar">
                                <?php echo strtoupper(mb_substr($siswa['nama'], 0, 1)); ?>
                            </div>
                            <div>
                                <div class="siswa-nama"><?php echo htmlspecialchars($siswa['nama']); ?></div>
                                <div class="siswa-meta"><?php echo htmlspecialchars($siswa['email']); ?></div>
                            </div>
                            <div class="ms-auto text-end">
                                <?php if ($siswa['dikonfirmasi_admin']): ?>
                                    <span class="badge-status badge-dikonfirmasi">✓ Dikonfirmasi</span>
                                <?php elseif ($siswa['id_evaluasi']): ?>
                                    <span class="badge-status" style="background:#fff3cd;color:#664d03;">
                                        ⏳ Menunggu konfirmasi
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status badge-belum">Belum dinilai</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($siswa['dikonfirmasi_admin']): ?>
                        <!-- Sudah dikonfirmasi: tampilkan read-only -->
                        <div class="nilai-display">
                            <div class="nd-item">
                                <div class="nd-n"><?php echo $siswa['nilai_fisik']; ?></div>
                                <div class="nd-lbl">Fisik</div>
                                <div class="nilai-bar-wrap">
                                    <div class="nilai-bar" style="width:<?php echo $siswa['nilai_fisik']; ?>%;
                                        background:<?php echo $siswa['nilai_fisik']>=80?'#198754':($siswa['nilai_fisik']>=60?'#ffc107':'#dc3545'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="nd-item">
                                <div class="nd-n"><?php echo $siswa['nilai_disiplin']; ?></div>
                                <div class="nd-lbl">Disiplin</div>
                                <div class="nilai-bar-wrap">
                                    <div class="nilai-bar" style="width:<?php echo $siswa['nilai_disiplin']; ?>%;
                                        background:<?php echo $siswa['nilai_disiplin']>=80?'#198754':($siswa['nilai_disiplin']>=60?'#ffc107':'#dc3545'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="nd-item">
                                <div class="nd-n"><?php echo $siswa['nilai_teori']; ?></div>
                                <div class="nd-lbl">Teori</div>
                                <div class="nilai-bar-wrap">
                                    <div class="nilai-bar" style="width:<?php echo $siswa['nilai_teori']; ?>%;
                                        background:<?php echo $siswa['nilai_teori']>=80?'#198754':($siswa['nilai_teori']>=60?'#ffc107':'#dc3545'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="nd-item">
                                <div class="nd-n"><?php echo $siswa['nilai_praktik']; ?></div>
                                <div class="nd-lbl">Praktik</div>
                                <div class="nilai-bar-wrap">
                                    <div class="nilai-bar" style="width:<?php echo $siswa['nilai_praktik']; ?>%;
                                        background:<?php echo $siswa['nilai_praktik']>=80?'#198754':($siswa['nilai_praktik']>=60?'#ffc107':'#dc3545'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rata-badge mb-2">
                            <span class="rata-label">Rata-rata</span>
                            <span class="rata-nilai"><?php echo $siswa['rata_rata']; ?></span>
                            <span class="rata-hasil <?php echo $siswa['hasil']==='lulus'?'hasil-lulus':'hasil-tidak-lulus'; ?>">
                                <?php echo strtoupper(str_replace('_',' ',$siswa['hasil'])); ?>
                            </span>
                        </div>
                        <div style="font-size:12px;color:#6c757d;">
                            🔒 Dikonfirmasi — <?php echo $siswa['tgl_input']; ?>
                        </div>

                        <?php else: ?>
                        <!-- Belum dikonfirmasi: form input nilai -->
                        <form method="POST" id="form_<?php echo $siswa['id_peserta']; ?>">
                            <input type="hidden" name="id_siswa"   value="<?php echo $siswa['id_peserta']; ?>">
                            <input type="hidden" name="id_periode" value="<?php echo $id_periode_aktif; ?>">

                            <div class="nilai-grid">
                                <?php
                                $fields = [
                                    'nilai_fisik'    => 'Nilai Fisik',
                                    'nilai_disiplin' => 'Nilai Disiplin',
                                    'nilai_teori'    => 'Nilai Teori',
                                    'nilai_praktik'  => 'Nilai Praktik',
                                ];
                                foreach ($fields as $fname => $flabel):
                                ?>
                                <div class="nilai-item">
                                    <label><?php echo $flabel; ?></label>
                                    <input type="number" name="<?php echo $fname; ?>"
                                           min="0" max="100" step="0.5"
                                           value="<?php echo $siswa[$fname] ?? ''; ?>"
                                           placeholder="0–100"
                                           class="nilai-input"
                                           data-form="<?php echo $siswa['id_peserta']; ?>"
                                           oninput="hitungRata(<?php echo $siswa['id_peserta']; ?>); warnaNilai(this)">
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Rata-rata live -->
                            <div class="rata-badge" id="rata_<?php echo $siswa['id_peserta']; ?>">
                                <span class="rata-label">Rata-rata</span>
                                <span class="rata-nilai" id="rataVal_<?php echo $siswa['id_peserta']; ?>">
                                    <?php echo $siswa['rata_rata'] ?? '—'; ?>
                                </span>
                                <span class="rata-hasil" id="rataHsl_<?php echo $siswa['id_peserta']; ?>">
                                    <?php if ($siswa['rata_rata'] !== null): ?>
                                        <span class="<?php echo $siswa['rata_rata']>=80?'hasil-lulus':'hasil-tidak-lulus'; ?>">
                                            <?php echo $siswa['rata_rata']>=80?'LULUS':'TIDAK LULUS'; ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="mb-3">
                                <label style="font-size:12px;font-weight:700;color:#495057;margin-bottom:5px;display:block;">
                                    Catatan (opsional)
                                </label>
                                <textarea name="catatan" class="form-control" rows="2"
                                          style="font-size:13px"
                                          placeholder="Catatan khusus..."><?php
                                    echo htmlspecialchars($siswa['catatan'] ?? '');
                                ?></textarea>
                            </div>

                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <!-- Tombol simpan -->
                                <button type="submit" name="simpan_nilai"
                                        class="btn btn-sm"
                                        style="background:var(--navy);color:#fff;border:none;
                                               border-radius:8px;padding:8px 16px;font-weight:600;font-size:13px;">
                                    💾 Simpan Nilai
                                </button>

                                <!-- Tombol konfirmasi (hanya jika sudah ada nilai tersimpan) -->
                                <?php if ($siswa['id_evaluasi']): ?>
                                <button type="submit" name="konfirmasi_nilai"
                                        onclick="return confirm('Konfirmasi nilai <?php echo htmlspecialchars($siswa['nama']); ?>?\nNilai akan dikunci dan status diperbarui.')"
                                        class="btn btn-sm"
                                        style="background:#198754;color:#fff;border:none;
                                               border-radius:8px;padding:8px 16px;font-weight:600;font-size:13px;">
                                    ✅ Konfirmasi
                                </button>
                                <?php endif; ?>
                            </div>

                        </form>
                        <?php endif; ?>

                    </div><!-- /siswa-eval-card -->
                    <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

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
<script src="../js/dashboard.js"></script>

<script>
function hitungRata(id) {
    var form   = document.getElementById('form_' + id);
    var inputs = form ? form.querySelectorAll('input.nilai-input') : [];
    var total  = 0, count = 0;
    inputs.forEach(function(inp) {
        var v = parseFloat(inp.value);
        if (!isNaN(v)) { total += v; count++; }
    });
    var elVal = document.getElementById('rataVal_' + id);
    var elHsl = document.getElementById('rataHsl_' + id);
    if (!elVal) return;
    if (count === 4) {
        var rata = (total / 4).toFixed(2);
        elVal.textContent = rata;
        elHsl.innerHTML = rata >= 80
            ? '<span class="hasil-lulus">LULUS</span>'
            : '<span class="hasil-tidak-lulus">TIDAK LULUS</span>';
    } else {
        elVal.textContent = '—';
        elHsl.innerHTML   = '';
    }
}

function warnaNilai(inp) {
    var v = parseFloat(inp.value);
    inp.classList.remove('nilai-ok','nilai-warn','nilai-fail');
    if (isNaN(v) || inp.value === '') return;
    if (v >= 80)      inp.classList.add('nilai-ok');
    else if (v >= 60) inp.classList.add('nilai-warn');
    else              inp.classList.add('nilai-fail');
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input.nilai-input').forEach(function(inp) {
        if (inp.value) warnaNilai(inp);
    });
});
</script>
</body>
</html>
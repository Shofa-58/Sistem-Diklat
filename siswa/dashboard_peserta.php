<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("location: ../login.php");
    exit;
}

$id_akun = $_SESSION['id_akun'];

$siswa = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT s.* FROM siswa s WHERE s.id_akun='$id_akun' LIMIT 1"
));

if (!$siswa) { echo "Data tidak ditemukan."; exit; }

$id_siswa = $siswa['id_peserta'];

/* Periode siswa */
$periode = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT pd.* FROM peserta_periode pp
     JOIN periode_diklat pd ON pp.id_periode = pd.id_periode
     WHERE pp.id_peserta='$id_siswa'
     ORDER BY pp.created_at DESC LIMIT 1"
));

/* Jadwal */
$jadwal = null;
if ($periode) {
    $jadwal = mysqli_query($conn,
        "SELECT * FROM jadwal_diklat
         WHERE id_periode='{$periode['id_periode']}'
         ORDER BY tanggal ASC"
    );
}

/* Hasil evaluasi (hanya yang sudah dikonfirmasi admin) */
$evaluasi = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM evaluasi
     WHERE id_siswa='$id_siswa' AND dikonfirmasi_admin=1
     LIMIT 1"
));

/* Dokumen & notifikasi revisi */
$dokRevisi = mysqli_query($conn,
    "SELECT jenis, catatan_admin FROM dokumen_pendaftaran
     WHERE id_siswa='$id_siswa' AND status_verifikasi='revisi'"
);
$adaRevisi    = mysqli_num_rows($dokRevisi) > 0;
$jmlDokValid  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS jml FROM dokumen_pendaftaran
     WHERE id_siswa='$id_siswa' AND status_verifikasi='valid'"
))['jml'];
$jmlDokTotal  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS jml FROM dokumen_pendaftaran WHERE id_siswa='$id_siswa'"
))['jml'];

/* Notifikasi terbaru */
$notif = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM notifikasi WHERE id_siswa='$id_siswa'
     ORDER BY tgl_kirim DESC LIMIT 1"
));

/* Timeline status */
$timeline_steps = [
    'calon'        => ['label' => 'Mendaftar',    'icon' => '📝', 'desc' => 'Data diterima, menunggu verifikasi admin'],
    'terverifikasi'=> ['label' => 'Terverifikasi','icon' => '✅', 'desc' => 'Dokumen valid, menunggu periode diklat'],
    'peserta'      => ['label' => 'Peserta',      'icon' => '🎓', 'desc' => 'Aktif mengikuti kegiatan diklat'],
    'lulus'        => ['label' => 'Lulus',        'icon' => '🏆', 'desc' => 'Dinyatakan lulus program diklat'],
];
$status_order = ['calon', 'terverifikasi', 'peserta', 'lulus'];
$status_now   = $siswa['status'] === 'tidak_lulus' ? 'lulus' : $siswa['status'];
$current_idx  = array_search($status_now, $status_order);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Siswa — Gemilang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <style>
        body { background: #f0f2f5; font-family: Arial, sans-serif; }

        /* Navbar */
        .siswa-nav {
            background: var(--navy);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .siswa-nav .brand { color: var(--yellow); font-weight: 700; font-size: 16px; }
        .siswa-nav .nav-btns { display: flex; gap: 8px; }
        .siswa-nav .nav-btns a {
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-ganti { background: var(--yellow); color: var(--navy); }
        .btn-logout { background: #dc354533; color: #dc3545; border: 1px solid #dc354555; }

        /* Container */
        .siswa-container { max-width: 640px; margin: 0 auto; padding: 16px; }

        /* Card umum */
        .s-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
        }
        .s-card h6 {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f2f5;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Alert revisi */
        .revisi-alert {
            background: #f8d7da;
            border: 1px solid #f5c2c7;
            border-left: 4px solid #dc3545;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .revisi-alert .alert-title {
            font-weight: 700;
            color: #842029;
            font-size: 15px;
            margin-bottom: 8px;
        }
        .revisi-alert .alert-list {
            font-size: 13px;
            color: #6a1922;
            margin: 0 0 10px;
            padding-left: 18px;
        }
        .btn-revisi {
            display: inline-block;
            background: #dc3545;
            color: #fff;
            border-radius: 10px;
            padding: 10px 18px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
        }

        /* Profil siswa */
        .profil-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }
        .profil-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--navy);
            color: var(--yellow);
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .profil-nama { font-size: 17px; font-weight: 700; color: var(--navy); }
        .profil-username { font-size: 12px; color: #6c757d; }

        /* Timeline status */
        .timeline {
            position: relative;
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline li {
            position: relative;
            padding: 0 0 18px 50px;
        }
        .timeline li:last-child { padding-bottom: 0; }
        .timeline .step-dot {
            position: absolute;
            left: 10px;
            top: 2px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            z-index: 1;
        }
        .step-dot.done    { background: #198754; color: #fff; }
        .step-dot.current { background: #0d6efd; color: #fff; box-shadow: 0 0 0 3px #cfe2ff; }
        .step-dot.future  { background: #e9ecef; color: #6c757d; }
        .step-dot.fail    { background: #dc3545; color: #fff; }

        .timeline .step-label {
            font-size: 14px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 2px;
        }
        .timeline .step-label.future { color: #adb5bd; font-weight: 400; }
        .timeline .step-label.current { color: #0d6efd; }
        .timeline .step-desc {
            font-size: 12px;
            color: #6c757d;
        }

        /* Status badge besar */
        .big-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .big-badge.calon         { background: #e9ecef; color: #495057; }
        .big-badge.terverifikasi  { background: #cfe2ff; color: #084298; }
        .big-badge.peserta        { background: #ffe5d0; color: #7d3b08; }
        .big-badge.lulus          { background: #d1e7dd; color: #0a3622; }
        .big-badge.tidak_lulus    { background: #f8d7da; color: #842029; }

        /* Dokumen bar */
        .dok-bar-wrap { height: 8px; background: #e9ecef; border-radius: 4px; margin-bottom: 6px; }
        .dok-bar-fill { height: 100%; border-radius: 4px; background: #198754; transition: width 0.4s; }

        /* Info row */
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 6px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .info-lbl { color: #6c757d; }
        .info-row .info-val { font-weight: 600; color: var(--navy); text-align: right; }

        /* Nilai grid */
        .nilai-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }
        .nilai-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
        }
        .nilai-box .nval { font-size: 24px; font-weight: 800; color: var(--navy); line-height: 1; }
        .nilai-box .nlbl { font-size: 11px; color: #6c757d; margin-top: 3px; }

        /* Jadwal */
        .jadwal-item {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f2f5;
            font-size: 13px;
        }
        .jadwal-item:last-child { border-bottom: none; }
        .jadwal-tanggal {
            min-width: 80px;
            color: var(--navy);
            font-weight: 700;
            font-size: 12px;
        }
        .jadwal-kegiatan { color: #495057; }
        .jadwal-ket { font-size: 12px; color: #6c757d; }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #6c757d;
        }
        .empty-state .icon { font-size: 40px; margin-bottom: 10px; }
        .empty-state p { font-size: 13px; margin: 0; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="siswa-nav">
    <span class="brand">🛡️ Gemilang</span>
    <div class="nav-btns">
        <a href="../ganti_password.php" class="btn-ganti">Ganti Password</a>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="siswa-container">

    <!-- ===== ALERT REVISI ===== -->
    <?php if ($adaRevisi): ?>
    <div class="revisi-alert">
        <div class="alert-title">⚠️ Dokumen Perlu Direvisi!</div>
        <ul class="alert-list">
        <?php
        mysqli_data_seek($dokRevisi, 0);
        while ($r = mysqli_fetch_assoc($dokRevisi)) {
            echo "<li><strong>" . strtoupper($r['jenis']) . "</strong>"
               . ($r['catatan_admin'] ? ": " . htmlspecialchars($r['catatan_admin']) : '') . "</li>";
        }
        ?>
        </ul>
        <?php if ($siswa['batas_revisi']): ?>
        <p style="font-size:13px;color:#6a1922;margin-bottom:10px;">
            Batas revisi: <strong><?php echo $siswa['batas_revisi']; ?></strong>
        </p>
        <?php endif; ?>
        <a href="revisi_dokumen.php" class="btn-revisi">Perbaiki Sekarang →</a>
    </div>
    <?php endif; ?>

    <!-- ===== PROFIL & STATUS ===== -->
    <div class="s-card">
        <div class="profil-header">
            <div class="profil-avatar">
                <?php echo strtoupper(mb_substr($siswa['nama'], 0, 1)); ?>
            </div>
            <div>
                <div class="profil-nama"><?php echo htmlspecialchars($siswa['nama']); ?></div>
                <div class="profil-username">@<?php echo htmlspecialchars($_SESSION['username']); ?></div>
            </div>
        </div>

        <!-- Badge status -->
        <div>
            <?php
            $badge_icon = [
                'calon'         => '📝',
                'terverifikasi' => '✅',
                'peserta'       => '🎓',
                'lulus'         => '🏆',
                'tidak_lulus'   => '❌',
            ][$siswa['status']] ?? '❓';
            ?>
            <span class="big-badge <?php echo $siswa['status']; ?>">
                <?php echo $badge_icon; ?>
                <?php echo ucfirst(str_replace('_', ' ', $siswa['status'])); ?>
            </span>
        </div>

        <!-- Timeline -->
        <h6 style="margin-top:4px;">Progres Pendaftaran</h6>

        <?php if ($siswa['status'] === 'tidak_lulus'): ?>
        <div style="background:#f8d7da;border-radius:10px;padding:12px;font-size:13px;color:#842029;margin-bottom:12px;">
            ❌ Anda dinyatakan <strong>tidak lulus</strong> pada program diklat ini.
            Silakan hubungi admin untuk informasi lebih lanjut.
        </div>
        <?php endif; ?>

        <ul class="timeline">
        <?php foreach ($status_order as $idx => $st): ?>
            <?php
            if ($siswa['status'] === 'tidak_lulus' && $st === 'lulus') {
                $dot_class   = 'fail';
                $label_class = '';
                $icon        = '❌';
                $desc        = 'Tidak lulus pada program diklat ini';
            } elseif ($idx < $current_idx) {
                $dot_class   = 'done';
                $label_class = '';
                $icon        = '✓';
                $desc        = $timeline_steps[$st]['desc'];
            } elseif ($idx === $current_idx) {
                $dot_class   = 'current';
                $label_class = 'current';
                $icon        = $timeline_steps[$st]['icon'];
                $desc        = $timeline_steps[$st]['desc'];
            } else {
                $dot_class   = 'future';
                $label_class = 'future';
                $icon        = '○';
                $desc        = $timeline_steps[$st]['desc'];
            }
            ?>
            <li>
                <div class="step-dot <?php echo $dot_class; ?>"><?php echo $icon; ?></div>
                <div class="step-label <?php echo $label_class; ?>">
                    <?php echo $timeline_steps[$st]['label']; ?>
                    <?php if ($idx === $current_idx && $siswa['status'] !== 'tidak_lulus'): ?>
                    <span style="font-size:11px;background:#0d6efd;color:#fff;
                                 border-radius:10px;padding:2px 7px;margin-left:4px;">
                        Saat ini
                    </span>
                    <?php endif; ?>
                </div>
                <div class="step-desc"><?php echo $desc; ?></div>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>

    <!-- ===== STATUS DOKUMEN ===== -->
    <?php if (in_array($siswa['status'], ['calon','terverifikasi'])): ?>
    <div class="s-card">
        <h6>Dokumen Pendaftaran</h6>
        <?php
        $pct_dok = $jmlDokTotal > 0 ? round($jmlDokValid / $jmlDokTotal * 100) : 0;
        ?>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
            <span style="color:#6c757d;"><?php echo $jmlDokValid; ?> / <?php echo $jmlDokTotal; ?> dokumen valid</span>
            <span style="font-weight:700;color:<?php echo $pct_dok==100?'#198754':'#0d6efd'; ?>">
                <?php echo $pct_dok; ?>%
            </span>
        </div>
        <div class="dok-bar-wrap">
            <div class="dok-bar-fill" style="width:<?php echo $pct_dok; ?>%"></div>
        </div>

        <?php if ($siswa['status'] === 'calon' && !$adaRevisi): ?>
        <p style="font-size:12px;color:#6c757d;margin:8px 0 0;">
            ⏳ Admin sedang memverifikasi dokumen Anda. Harap tunggu.
        </p>
        <?php elseif ($siswa['status'] === 'terverifikasi'): ?>
        <p style="font-size:12px;color:#198754;margin:8px 0 0;">
            ✅ Dokumen Anda telah diverifikasi. Menunggu penetapan periode diklat.
        </p>
        <?php endif; ?>

        <?php if ($adaRevisi): ?>
        <div style="margin-top:10px;">
            <a href="revisi_dokumen.php" style="font-size:13px;color:#dc3545;font-weight:700;">
                ⚠️ Perbaiki dokumen yang perlu revisi →
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== INFO DIKLAT ===== -->
    <?php if ($periode): ?>
    <div class="s-card">
        <h6>Informasi Diklat</h6>
        <div class="info-row">
            <span class="info-lbl">Periode</span>
            <span class="info-val"><?php echo $periode['tahun']; ?> — Gel. <?php echo $periode['gelombang']; ?></span>
        </div>
        <div class="info-row">
            <span class="info-lbl">Tanggal</span>
            <span class="info-val"><?php echo $periode['tanggal_mulai']; ?> s/d <?php echo $periode['tanggal_selesai']; ?></span>
        </div>
        <div class="info-row">
            <span class="info-lbl">Lokasi</span>
            <span class="info-val"><?php echo htmlspecialchars($periode['lokasi_spesifik'] ?: '-'); ?></span>
        </div>
        <?php if ($periode['lokasi_fasilitas']): ?>
        <div class="info-row">
            <span class="info-lbl">Lokasi Ambil Fasilitas</span>
            <span class="info-val"><?php echo htmlspecialchars($periode['lokasi_fasilitas']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($periode['fasilitas']): ?>
        <div style="margin-top:12px;">
            <div style="font-size:12px;font-weight:700;color:#6c757d;margin-bottom:4px;">FASILITAS</div>
            <p style="font-size:13px;color:#495057;margin:0;line-height:1.6;">
                <?php echo nl2br(htmlspecialchars($periode['fasilitas'])); ?>
            </p>
        </div>
        <?php endif; ?>
        <?php if ($periode['info_kebutuhan']): ?>
        <div style="margin-top:12px;background:#fff3cd;border-radius:10px;padding:12px;">
            <div style="font-size:12px;font-weight:700;color:#664d03;margin-bottom:4px;">
                🎒 YANG PERLU DIBAWA
            </div>
            <p style="font-size:13px;color:#856404;margin:0;line-height:1.6;">
                <?php echo nl2br(htmlspecialchars($periode['info_kebutuhan'])); ?>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Rundown -->
    <?php if ($jadwal && mysqli_num_rows($jadwal) > 0): ?>
    <div class="s-card">
        <h6>Rundown Kegiatan</h6>
        <?php while ($j = mysqli_fetch_assoc($jadwal)): ?>
        <div class="jadwal-item">
            <div class="jadwal-tanggal">
                <?php echo date('d M', strtotime($j['tanggal'])); ?><br>
                <span style="color:#6c757d;font-weight:400;"><?php echo date('Y', strtotime($j['tanggal'])); ?></span>
            </div>
            <div>
                <div class="jadwal-kegiatan"><?php echo htmlspecialchars($j['kegiatan']); ?></div>
                <?php if ($j['keterangan']): ?>
                <div class="jadwal-ket"><?php echo htmlspecialchars($j['keterangan']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <?php elseif (in_array($siswa['status'], ['peserta','terverifikasi'])): ?>
    <div class="s-card">
        <div class="empty-state">
            <div class="icon">📋</div>
            <p>Informasi diklat akan muncul setelah admin menetapkan periode untuk Anda.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== HASIL EVALUASI ===== -->
    <?php if ($evaluasi): ?>
    <div class="s-card">
        <h6>Hasil Evaluasi Diklat</h6>

        <div class="nilai-grid">
            <div class="nilai-box">
                <div class="nval"><?php echo $evaluasi['nilai_fisik']; ?></div>
                <div class="nlbl">Fisik</div>
            </div>
            <div class="nilai-box">
                <div class="nval"><?php echo $evaluasi['nilai_disiplin']; ?></div>
                <div class="nlbl">Disiplin</div>
            </div>
            <div class="nilai-box">
                <div class="nval"><?php echo $evaluasi['nilai_teori']; ?></div>
                <div class="nlbl">Teori</div>
            </div>
            <div class="nilai-box">
                <div class="nval"><?php echo $evaluasi['nilai_praktik']; ?></div>
                <div class="nlbl">Praktik</div>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;
                    background:#f8f9fa;border-radius:10px;padding:12px 16px;">
            <div>
                <div style="font-size:12px;color:#6c757d;">Rata-rata</div>
                <div style="font-size:28px;font-weight:800;color:var(--navy);line-height:1;">
                    <?php echo $evaluasi['rata_rata']; ?>
                </div>
            </div>
            <div>
                <?php if ($evaluasi['hasil'] === 'lulus'): ?>
                <span style="background:#d1e7dd;color:#0a3622;padding:10px 20px;
                             border-radius:50px;font-weight:700;font-size:15px;">
                    🏆 LULUS
                </span>
                <?php else: ?>
                <span style="background:#f8d7da;color:#842029;padding:10px 20px;
                             border-radius:50px;font-weight:700;font-size:15px;">
                    ❌ TIDAK LULUS
                </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($evaluasi['catatan']): ?>
        <div style="margin-top:12px;background:#f8f9fa;border-radius:10px;padding:12px;font-size:13px;color:#495057;">
            <strong>Catatan:</strong> <?php echo htmlspecialchars($evaluasi['catatan']); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php elseif (in_array($siswa['status'], ['peserta','lulus','tidak_lulus'])): ?>
    <div class="s-card">
        <h6>Hasil Evaluasi Diklat</h6>
        <div class="empty-state">
            <div class="icon">⏳</div>
            <p>Nilai sedang dalam proses input oleh admin.<br>Akan muncul setelah dikonfirmasi.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Jarak bawah -->
    <div style="height:30px;"></div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
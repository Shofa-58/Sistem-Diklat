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
    <link rel="stylesheet" href="../css/dashboard_layout.css">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        /* Sisa s-card dan timeline styling yang spesifik */
        .s-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid var(--gray-border);
        }
        .s-card h6 {
            font-size: 14px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-bg);
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
            border-radius: 8px;
            padding: 10px 18px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-revisi:hover { background: #bb2d3b; color:#fff; }

        /* Profil siswa */
        .profil-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .profil-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--navy);
            color: var(--yellow);
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(13,27,42,0.15);
        }
        .profil-nama { font-size: 18px; font-weight: 700; color: var(--navy); }
        .profil-username { font-size: 13px; color: var(--text-muted); }

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
            background: var(--gray-border);
        }
        .timeline li {
            position: relative;
            padding: 0 0 20px 50px;
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
            font-size: 11px;
            z-index: 1;
        }
        .step-dot.done    { background: #198754; color: #fff; }
        .step-dot.current { background: #0d6efd; color: #fff; box-shadow: 0 0 0 3px #cfe2ff; }
        .step-dot.future  { background: var(--gray-border); color: var(--text-muted); }
        .step-dot.fail    { background: #dc3545; color: #fff; }

        .timeline .step-label {
            font-size: 14px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 3px;
        }
        .timeline .step-label.future { color: var(--text-muted); font-weight: 500; }
        .timeline .step-label.current { color: #0d6efd; }
        .timeline .step-desc {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Status badge besar */
        .big-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .big-badge.calon         { background: #e9ecef; color: #495057; }
        .big-badge.terverifikasi  { background: #cfe2ff; color: #084298; }
        .big-badge.peserta        { background: #ffe5d0; color: #7d3b08; }
        .big-badge.lulus          { background: #d1e7dd; color: #0a3622; }
        .big-badge.tidak_lulus    { background: #f8d7da; color: #842029; }

        /* Dokumen bar */
        .dok-bar-wrap { height: 8px; background: var(--gray-border); border-radius: 4px; margin-bottom: 8px; }
        .dok-bar-fill { height: 100%; border-radius: 4px; background: #198754; transition: width 0.4s; }

        /* Info row */
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-bg);
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .info-lbl { color: var(--text-muted); }
        .info-row .info-val { font-weight: 600; color: var(--navy); text-align: right; }

        /* Nilai grid */
        .nilai-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 15px;
        }
        .nilai-box {
            background: var(--gray-bg);
            border-radius: 10px;
            padding: 15px 12px;
            text-align: center;
        }
        .nilai-box .nval { font-size: 26px; font-weight: 800; color: var(--navy); line-height: 1; }
        .nilai-box .nlbl { font-size: 12px; color: var(--text-muted); margin-top: 5px; font-weight:500;}

        /* Jadwal */
        .jadwal-item {
            display: flex;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-bg);
            font-size: 13px;
        }
        .jadwal-item:last-child { border-bottom: none; }
        .jadwal-tanggal {
            min-width: 85px;
            color: var(--navy);
            font-weight: 700;
            font-size: 13px;
        }
        .jadwal-kegiatan { color: var(--text-main); font-weight: 600; margin-bottom: 2px; }
        .jadwal-ket { font-size: 12px; color: var(--text-muted); }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-state .icon { font-size: 45px; margin-bottom: 15px; }
        .empty-state p { font-size: 14px; margin: 0; line-height: 1.5; }

        /* Khusus kontainer siswa ditaruh rapih di tengah untuk layar lebar */
        .siswa-content-wrap {
            max-width: 700px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            🛡️ Gemilang
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_peserta.php" class="active">
                    <span>🏠</span> Dashboard
                </a>
            </li>
            <li>
                <a href="../ganti_password.php">
                    <span>🔑</span> Ganti Password
                </a>
            </li>
            <?php if ($adaRevisi): ?>
            <li>
                <a href="revisi_dokumen.php" style="color: #ff6b6b;">
                    <span>⚠️</span> Revisi Dokumen
                </a>
            </li>
            <?php endif; ?>
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
                <h1 class="page-title">Dashboard Siswa</h1>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                    Halo, <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
        </header>

        <div class="content-body">
            <div class="siswa-content-wrap">

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

    </div> <!-- End siswa-content-wrap -->
        </div> <!-- End content-body -->
    </main>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="../js/dashboard.js"></script>
</body>
</html>
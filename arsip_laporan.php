<?php
session_start();
include "koneksi.php";

/* Akses: kepala keamanan dan CEO bisa lihat arsip */
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['kepala_keamanan', 'ceo', 'admin'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];

/* Ambil semua laporan yang sudah selesai (konfirmasi kepala atau tidak) */
$arsip = mysqli_query($conn,
    "SELECT
        pd.id_periode, pd.tahun, pd.gelombang,
        pd.tanggal_mulai, pd.tanggal_selesai,
        pd.lokasi_spesifik, pd.biaya, pd.status AS status_periode,
        l.id_laporan, l.tgl_generate, l.dikonfirmasi_kepala,
        l.tgl_konfirmasi_kepala, l.dilihat_ceo,
        l.file_laporan_polda, l.file_laporan_penilaian, l.file_surat_pernyataan,
        lp.pengeluaran, lp.pemasukan, lp.file_laporan AS file_lpj,
        COUNT(DISTINCT pp.id_peserta) AS jml_peserta,
        SUM(CASE WHEN s.status='lulus' THEN 1 ELSE 0 END) AS jml_lulus,
        SUM(CASE WHEN s.status='tidak_lulus' THEN 1 ELSE 0 END) AS jml_tidak_lulus,
        COUNT(DISTINCT CASE WHEN e.dikonfirmasi_admin=1 THEN e.id_evaluasi END) AS jml_nilai_confirmed
     FROM periode_diklat pd
     LEFT JOIN laporan l ON l.id_periode = pd.id_periode
     LEFT JOIN laporan_polda lp ON lp.id_periode = pd.id_periode
     LEFT JOIN peserta_periode pp ON pp.id_periode = pd.id_periode
     LEFT JOIN siswa s ON s.id_peserta = pp.id_peserta
     LEFT JOIN evaluasi e ON e.id_siswa = s.id_peserta AND e.id_periode = pd.id_periode
     GROUP BY pd.id_periode
     ORDER BY pd.tahun DESC, pd.gelombang DESC"
);

/* Stats keseluruhan */
$stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        COUNT(DISTINCT pd.id_periode) AS total_periode,
        SUM(CASE WHEN pd.status='selesai' THEN 1 ELSE 0 END) AS periode_selesai,
        COUNT(DISTINCT pp.id_peserta) AS total_peserta,
        SUM(CASE WHEN s.status='lulus' THEN 1 ELSE 0 END) AS total_lulus
     FROM periode_diklat pd
     LEFT JOIN peserta_periode pp ON pp.id_periode = pd.id_periode
     LEFT JOIN siswa s ON s.id_peserta = pp.id_peserta"
));

/* Back link sesuai role */
$back_link = match($role) {
    'kepala_keamanan' => 'kepala/dashboard_kepala.php',
    'ceo'             => 'ceo/dashboard_ceo.php',
    'admin'           => 'admin/dashboard_admin.php',
    default           => 'login.php'
};

$back_label = match($role) {
    'kepala_keamanan' => 'Dashboard Kepala Keamanan',
    'ceo'             => 'Dashboard CEO',
    'admin'           => 'Dashboard Admin',
    default           => 'Login'
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Arsip Laporan Diklat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/fase4b.css">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        /* Arsip card */
        .arsip-card {
            background: #fff;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            overflow: hidden;
            border-left: 5px solid #dee2e6;
            transition: box-shadow 0.2s;
        }
        .arsip-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.12); }
        .arsip-card.selesai    { border-left-color: #198754; }
        .arsip-card.berjalan   { border-left-color: #0d6efd; }
        .arsip-card.pendaftaran { border-left-color: #ffc107; }

        .arsip-header {
            padding: 18px 20px 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 1px solid #f0f2f5;
        }

        .arsip-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--navy);
            margin: 0 0 4px;
        }

        .arsip-subtitle {
            font-size: 13px;
            color: #6c757d;
        }

        .arsip-body { padding: 16px 20px; }

        /* Stats mini */
        .mini-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        @media (max-width: 576px) { .mini-stats { grid-template-columns: repeat(2, 1fr); } }

        .mini-stat {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
        }
        .mini-stat .val { font-size: 22px; font-weight: 800; color: var(--navy); line-height: 1; }
        .mini-stat .lbl { font-size: 11px; color: #6c757d; margin-top: 3px; }

        /* Progress bar kelulusan */
        .lulus-bar-wrap { height: 8px; background: #e9ecef; border-radius: 4px; margin: 6px 0; }
        .lulus-bar-fill { height: 100%; background: linear-gradient(90deg,#198754,#28a745); border-radius: 4px; }

        /* File links */
        .file-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #f0f2f5;
        }
        .file-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .file-btn.polda   { background: #cfe2ff; color: #084298; }
        .file-btn.nilai   { background: #d1e7dd; color: #0a3622; }
        .file-btn.surat   { background: #fff3cd; color: #664d03; }
        .file-btn:hover   { filter: brightness(0.95); transform: translateY(-1px); }
        .file-btn.missing { background: #f8f9fa; color: #adb5bd; cursor: default; }
        .file-btn.missing:hover { filter: none; transform: none; }

        /* Badge status */
        .status-pill {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-pill.selesai     { background: #d1e7dd; color: #0a3622; }
        .status-pill.berjalan    { background: #cff4fc; color: #055160; }
        .status-pill.pendaftaran { background: #fff3cd; color: #664d03; }

        .konfirmasi-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .konfirmasi-badge.ya    { background: #d1e7dd; color: #0a3622; }
        .konfirmasi-badge.belum { background: #f8d7da; color: #842029; }

        /* Filter */
        .filter-bar-arsip {
            background: #fff;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* Empty */
        .empty-arsip {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark nav4b">
    <div class="container-fluid">
        <span class="navbar-brand">🗂️ Arsip Laporan Diklat</span>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <span style="font-size:13px;color:#adb5bd;">
                <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
            <a href="<?php echo $back_link; ?>" class="btn btn-sm btn-outline-light">
                ← <?php echo $back_label; ?>
            </a>
            <button type="button" id="btnLogout" class="btn btn-sm btn-outline-danger">Logout</button>
        </div>
    </div>
</nav>

<div class="page-header">
    <div class="container">
        <h3>Arsip Laporan Kegiatan Diklat</h3>
        <p>Riwayat seluruh periode diklat beserta dokumen laporan terkait</p>
    </div>
</div>

<div class="container pb-5">

    <!-- Stats global -->
    <div class="stats-row mb-4">
        <div class="stat-box4b">
            <div class="stat-n"><?php echo $stats['total_periode'] ?? 0; ?></div>
            <div class="stat-lbl">Total Periode</div>
        </div>
        <div class="stat-box4b">
            <div class="stat-n" style="color:#198754"><?php echo $stats['periode_selesai'] ?? 0; ?></div>
            <div class="stat-lbl">Periode Selesai</div>
        </div>
        <div class="stat-box4b">
            <div class="stat-n"><?php echo $stats['total_peserta'] ?? 0; ?></div>
            <div class="stat-lbl">Total Peserta</div>
        </div>
        <div class="stat-box4b">
            <div class="stat-n" style="color:#198754"><?php echo $stats['total_lulus'] ?? 0; ?></div>
            <div class="stat-lbl">Total Lulus</div>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar-arsip">
        <span style="font-size:13px;font-weight:600;color:var(--navy);">Filter:</span>
        <a href="?filter=" class="btn btn-sm <?php echo !isset($_GET['filter'])||$_GET['filter']===''?'btn-dark':'btn-outline-secondary'; ?>"
           style="border-radius:20px;font-size:12px;">
            Semua
        </a>
        <a href="?filter=selesai" class="btn btn-sm <?php echo ($_GET['filter']??'')==='selesai'?'btn-success':'btn-outline-success'; ?>"
           style="border-radius:20px;font-size:12px;">
            ✅ Selesai
        </a>
        <a href="?filter=berjalan" class="btn btn-sm <?php echo ($_GET['filter']??'')==='berjalan'?'btn-primary':'btn-outline-primary'; ?>"
           style="border-radius:20px;font-size:12px;">
            🟢 Berjalan
        </a>
        <a href="?filter=pendaftaran" class="btn btn-sm <?php echo ($_GET['filter']??'')==='pendaftaran'?'btn-warning':'btn-outline-warning'; ?>"
           style="border-radius:20px;font-size:12px;">
            📋 Pendaftaran
        </a>
        <a href="?filter=belum_konfirmasi" class="btn btn-sm <?php echo ($_GET['filter']??'')==='belum_konfirmasi'?'btn-danger':'btn-outline-danger'; ?>"
           style="border-radius:20px;font-size:12px;">
            ⚠️ Belum Dikonfirmasi
        </a>
    </div>

    <!-- Daftar arsip -->
    <?php
    $filter = $_GET['filter'] ?? '';
    $count_shown = 0;

    mysqli_data_seek($arsip, 0);
    while ($a = mysqli_fetch_assoc($arsip)):

        /* Apply filter */
        if ($filter === 'selesai' && $a['status_periode'] !== 'selesai') continue;
        if ($filter === 'berjalan' && $a['status_periode'] !== 'berjalan') continue;
        if ($filter === 'pendaftaran' && $a['status_periode'] !== 'pendaftaran') continue;
        if ($filter === 'belum_konfirmasi' && $a['dikonfirmasi_kepala']) continue;

        $count_shown++;

        $jml_peserta = (int) ($a['jml_peserta'] ?? 0);
        $jml_lulus   = (int) ($a['jml_lulus'] ?? 0);
        $jml_tdk     = (int) ($a['jml_tidak_lulus'] ?? 0);
        $pct_lulus   = $jml_peserta > 0 ? round($jml_lulus / $jml_peserta * 100) : 0;
        $selisih     = ($a['pemasukan'] ?? 0) - ($a['pengeluaran'] ?? 0);
    ?>

    <div class="arsip-card <?php echo $a['status_periode']; ?>">

        <!-- Header -->
        <div class="arsip-header">
            <div>
                <div class="arsip-title">
                    <?php echo $a['tahun']; ?> — Gelombang <?php echo $a['gelombang']; ?>
                </div>
                <div class="arsip-subtitle">
                    📅 <?php echo $a['tanggal_mulai']; ?> s/d <?php echo $a['tanggal_selesai']; ?>
                    &nbsp;·&nbsp;
                    📍 <?php echo htmlspecialchars($a['lokasi_spesifik'] ?: '-'); ?>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <span class="status-pill <?php echo $a['status_periode']; ?>">
                    <?php
                    $icons = ['selesai'=>'✅','berjalan'=>'🟢','pendaftaran'=>'📋'];
                    echo ($icons[$a['status_periode']] ?? '') . ' ' . ucfirst($a['status_periode']);
                    ?>
                </span>
                <span class="konfirmasi-badge <?php echo $a['dikonfirmasi_kepala'] ? 'ya' : 'belum'; ?>">
                    <?php echo $a['dikonfirmasi_kepala'] ? '🔐 KK Konfirmasi' : '⏳ Belum Konfirmasi KK'; ?>
                </span>
            </div>
        </div>

        <!-- Body -->
        <div class="arsip-body">

            <!-- Mini stats -->
            <div class="mini-stats">
                <div class="mini-stat">
                    <div class="val"><?php echo $jml_peserta; ?></div>
                    <div class="lbl">Peserta</div>
                </div>
                <div class="mini-stat">
                    <div class="val" style="color:#198754"><?php echo $jml_lulus; ?></div>
                    <div class="lbl">Lulus</div>
                </div>
                <div class="mini-stat">
                    <div class="val" style="color:#dc3545"><?php echo $jml_tdk; ?></div>
                    <div class="lbl">Tidak Lulus</div>
                </div>
                <div class="mini-stat">
                    <div class="val"><?php echo $pct_lulus; ?>%</div>
                    <div class="lbl">Kelulusan</div>
                </div>
            </div>

            <!-- Progress bar -->
            <?php if ($jml_peserta > 0): ?>
            <div style="font-size:12px;color:#6c757d;margin-bottom:4px;">
                Tingkat kelulusan: <strong><?php echo $pct_lulus; ?>%</strong>
            </div>
            <div class="lulus-bar-wrap">
                <div class="lulus-bar-fill" style="width:<?php echo $pct_lulus; ?>%"></div>
            </div>
            <?php endif; ?>

            <!-- Keuangan (jika ada LPJ) -->
            <?php if ($a['pemasukan'] || $a['pengeluaran']): ?>
            <div style="display:flex;gap:20px;font-size:13px;margin-top:12px;flex-wrap:wrap;">
                <span>
                    💰 Pemasukan:
                    <strong style="color:#198754;">
                        Rp <?php echo number_format($a['pemasukan'], 0, ',', '.'); ?>
                    </strong>
                </span>
                <span>
                    💸 Pengeluaran:
                    <strong style="color:#dc3545;">
                        Rp <?php echo number_format($a['pengeluaran'], 0, ',', '.'); ?>
                    </strong>
                </span>
                <span>
                    <?php if ($selisih >= 0): ?>
                    ✅ Surplus:
                    <strong style="color:#198754;">
                        Rp <?php echo number_format($selisih, 0, ',', '.'); ?>
                    </strong>
                    <?php else: ?>
                    ⚠️ Defisit:
                    <strong style="color:#dc3545;">
                        Rp <?php echo number_format(abs($selisih), 0, ',', '.'); ?>
                    </strong>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Info konfirmasi -->
            <?php if ($a['dikonfirmasi_kepala'] && $a['tgl_konfirmasi_kepala']): ?>
            <div style="font-size:12px;color:#6c757d;margin-top:8px;">
                🔐 Dikonfirmasi Kepala Keamanan:
                <strong><?php echo date('d M Y H:i', strtotime($a['tgl_konfirmasi_kepala'])); ?></strong>
            </div>
            <?php endif; ?>

            <!-- Dokumen laporan -->
            <div class="file-row">
                <span style="font-size:12px;font-weight:700;color:#6c757d;align-self:center;">
                    Dokumen:
                </span>

                <?php if ($a['file_lpj']): ?>
                <a href="<?php echo htmlspecialchars($a['file_lpj']); ?>"
                   target="_blank" class="file-btn polda">
                    📄 LPJ Polda
                </a>
                <?php else: ?>
                <span class="file-btn missing">📄 LPJ Polda (belum)</span>
                <?php endif; ?>

                <?php if ($a['jml_nilai_confirmed'] > 0): ?>
                <span class="file-btn nilai" style="cursor:default;">
                    ✅ <?php echo $a['jml_nilai_confirmed']; ?> Nilai Terkonfirmasi
                </span>
                <?php else: ?>
                <span class="file-btn missing">📊 Nilai (belum)</span>
                <?php endif; ?>

                <?php if ($a['file_surat_pernyataan']): ?>
                <a href="<?php echo htmlspecialchars($a['file_surat_pernyataan']); ?>"
                   target="_blank" class="file-btn surat">
                    📝 Surat Pernyataan
                </a>
                <?php else: ?>
                <span class="file-btn missing">📝 Surat Pernyataan (belum)</span>
                <?php endif; ?>

                <!-- Link detail -->
                <?php if ($role === 'kepala_keamanan'): ?>
                <a href="kepala/dashboard_kepala.php?periode=<?php echo $a['id_periode']; ?>"
                   class="file-btn" style="background:var(--navy);color:#fff;margin-left:auto;">
                    → Detail
                </a>
                <?php elseif ($role === 'ceo'): ?>
                <a href="ceo/dashboard_ceo.php?periode=<?php echo $a['id_periode']; ?>"
                   class="file-btn" style="background:var(--navy);color:#fff;margin-left:auto;">
                    → Detail
                </a>
                <?php elseif ($role === 'admin'): ?>
                <a href="admin/admin_evaluasi.php?periode=<?php echo $a['id_periode']; ?>"
                   class="file-btn" style="background:var(--navy);color:#fff;margin-left:auto;">
                    → Evaluasi
                </a>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php endwhile; ?>

    <?php if ($count_shown === 0): ?>
    <div class="empty-arsip">
        <div style="font-size:48px;margin-bottom:16px;">📂</div>
        <h5 style="color:var(--navy);">Tidak ada data yang sesuai filter</h5>
        <p>Coba pilih filter lain atau <a href="arsip_laporan.php">tampilkan semua</a>.</p>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
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
            window.location.href = 'logout.php';
        }
    })
});
</script>
</body>
</html>
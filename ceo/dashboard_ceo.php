<?php
session_start();
include "../koneksi.php";
include "../helpers.php";


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ceo') {
    header("Location: ../login.php");
    exit;
}

/* Tandai laporan telah dilihat CEO */
$id_periode_aktif = isset($_GET['periode']) ? (int) $_GET['periode'] : 0;
if ($id_periode_aktif) {
    mysqli_query(
        $conn,
        "UPDATE laporan SET dilihat_ceo=1, tgl_dilihat_ceo=NOW()
         WHERE id_periode='$id_periode_aktif' AND dilihat_ceo=0"
    );
}

/* Ambil semua periode */
$semua_periode = mysqli_query(
    $conn,
    "SELECT * FROM periode_diklat ORDER BY tahun DESC, gelombang DESC"
);

if (!$id_periode_aktif) {
    $tmp = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT id_periode FROM periode_diklat ORDER BY tahun DESC, gelombang DESC LIMIT 1"
    ));
    $id_periode_aktif = $tmp ? (int) $tmp['id_periode'] : 0;
}

$periode = null;
if ($id_periode_aktif) {
    $periode = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT pd.*, l.dikonfirmasi_kepala, l.tgl_konfirmasi_kepala,
                l.dilihat_ceo, l.file_surat_pernyataan
         FROM periode_diklat pd
         LEFT JOIN laporan l ON l.id_periode = pd.id_periode
         WHERE pd.id_periode='$id_periode_aktif'
         ORDER BY l.id_laporan DESC LIMIT 1"
    ));
}

/* Statistik global lintas periode */
$stat_global = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT
        COUNT(DISTINCT pd.id_periode) AS total_periode,
        COUNT(DISTINCT pp.id_peserta) AS total_peserta_all,
        SUM(CASE WHEN s.status='lulus' THEN 1 ELSE 0 END) AS total_lulus,
        SUM(CASE WHEN s.status='tidak_lulus' THEN 1 ELSE 0 END) AS total_tidak_lulus
     FROM periode_diklat pd
     LEFT JOIN peserta_periode pp ON pp.id_periode = pd.id_periode
     LEFT JOIN siswa s ON s.id_peserta = pp.id_peserta"
));

/* Data periode aktif */
$daftar_siswa = [];
$laporan_polda = null;
$laporan_rekap = null;

if ($id_periode_aktif) {
    $q = mysqli_query(
        $conn,
        "SELECT s.nama, s.email,
                e.nilai_fisik, e.nilai_disiplin, e.nilai_teori, e.nilai_praktik,
                e.rata_rata, e.hasil
         FROM siswa s
         JOIN peserta_periode pp ON s.id_peserta = pp.id_peserta
         LEFT JOIN evaluasi e ON e.id_siswa = s.id_peserta AND e.id_periode='$id_periode_aktif'
         WHERE pp.id_periode='$id_periode_aktif'
         AND s.status IN ('peserta','lulus','tidak_lulus')
         ORDER BY e.rata_rata DESC"
    );
    while ($r = mysqli_fetch_assoc($q)) $daftar_siswa[] = $r;

    $laporan_polda = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT * FROM laporan_polda WHERE id_periode='$id_periode_aktif'"
    ));

    $laporan_rekap = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT * FROM laporan WHERE id_periode='$id_periode_aktif'
         ORDER BY id_laporan DESC LIMIT 1"
    ));
}

$total_p    = count($daftar_siswa);
$lulus_p    = array_reduce($daftar_siswa, fn($c, $s) => $c + ($s['hasil'] === 'lulus' ? 1 : 0), 0);
$tdk_lulus  = $total_p - $lulus_p;
$pct_lulus  = $total_p > 0 ? round($lulus_p / $total_p * 100) : 0;

/* Riwayat semua periode (untuk tabel ringkasan) */
$riwayat = mysqli_query(
    $conn,
    "SELECT pd.tahun, pd.gelombang, pd.tanggal_mulai, pd.tanggal_selesai,
            pd.status, pd.lokasi_spesifik,
            COUNT(DISTINCT pp.id_peserta) AS jml_peserta,
            SUM(CASE WHEN s.status='lulus' THEN 1 ELSE 0 END) AS jml_lulus,
            l.dikonfirmasi_kepala
     FROM periode_diklat pd
     LEFT JOIN peserta_periode pp ON pp.id_periode = pd.id_periode
     LEFT JOIN siswa s ON s.id_peserta = pp.id_peserta
     LEFT JOIN laporan l ON l.id_periode = pd.id_periode
     GROUP BY pd.id_periode
     ORDER BY pd.tahun DESC, pd.gelombang DESC"
);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard CEO — Laporan Diklat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/fase4b.css">
    <link rel="stylesheet" href="../css/dashboard_layout.css">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .stat-big {
            background: #fff;
            border-radius: 16px;
            padding: 24px 20px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .stat-big .val {
            font-size: 42px;
            font-weight: 800;
            line-height: 1;
            color: var(--navy);
        }

        .stat-big .lbl {
            font-size: 13px;
            color: #6c757d;
            margin-top: 6px;
        }

        .progress-bar-lulus {
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-bar-lulus .fill {
            height: 100%;
            background: linear-gradient(90deg, #198754, #28a745);
            border-radius: 5px;
            transition: width 0.5s;
        }

        .periode-badge {
            display: inline-block;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        .periode-badge.selesai {
            background: #d1e7dd;
            color: #0a3622;
        }

        .periode-badge.berjalan {
            background: #cff4fc;
            color: #055160;
        }

        .periode-badge.pendaftaran {
            background: #fff3cd;
            color: #664d03;
        }
    </style>
</head>

<body>

    <div class="dashboard-wrapper">
        <!-- SIDEBAR -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                👔 CEO Gemilang
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard_ceo.php" class="active">
                        <span>📊</span> Laporan Diklat
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
                    <h1 class="page-title">Ringkasan Laporan & Statistik</h1>
                </div>
                <div>
                    <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                        CEO: <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                </div>
            </header>

            <div class="content-body">

                <div class="container pb-5">

                    <!-- =========================================================
         STATISTIK GLOBAL
    ========================================================= -->
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="stat-big">
                                <div class="val"><?php echo $stat_global['total_periode'] ?? 0; ?></div>
                                <div class="lbl">Total Periode</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-big">
                                <div class="val"><?php echo $stat_global['total_peserta_all'] ?? 0; ?></div>
                                <div class="lbl">Total Peserta (semua periode)</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-big">
                                <div class="val" style="color:#198754"><?php echo $stat_global['total_lulus'] ?? 0; ?></div>
                                <div class="lbl">Total Lulus</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-big">
                                <div class="val" style="color:#dc3545"><?php echo $stat_global['total_tidak_lulus'] ?? 0; ?></div>
                                <div class="lbl">Total Tidak Lulus</div>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
         RIWAYAT SEMUA PERIODE
    ========================================================= -->
                    <div class="card4b mb-4">
                        <div class="card-header">📋 Riwayat Semua Periode Diklat</div>
                        <div class="card-body p-0">
                            <div class="table-scroll">
                                <table class="table table4b table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Periode</th>
                                            <th>Tanggal</th>
                                            <th>Lokasi</th>
                                            <th class="text-center">Peserta</th>
                                            <th class="text-center">Lulus</th>
                                            <th class="text-center">Kelulusan</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Konfirmasi KK</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($rv = mysqli_fetch_assoc($riwayat)): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo $rv['tahun']; ?></strong>
                                                    <span style="color:#6c757d;font-size:12px;"> G<?php echo $rv['gelombang']; ?></span>
                                                </td>
                                                <td style="font-size:12px;">
                                                    <?php echo $rv['tanggal_mulai']; ?><br>
                                                    <span style="color:#6c757d;">s/d <?php echo $rv['tanggal_selesai']; ?></span>
                                                </td>
                                                <td style="font-size:12px;"><?php echo htmlspecialchars($rv['lokasi_spesifik'] ?: '-'); ?></td>
                                                <td class="text-center"><strong><?php echo $rv['jml_peserta']; ?></strong></td>
                                                <td class="text-center" style="color:#198754;font-weight:700;">
                                                    <?php echo $rv['jml_lulus'] ?? 0; ?>
                                                </td>
                                                <td class="text-center" style="font-size:13px;">
                                                    <?php
                                                    $pct = $rv['jml_peserta'] > 0
                                                        ? round(($rv['jml_lulus'] / $rv['jml_peserta']) * 100)
                                                        : 0;
                                                    echo "$pct%";
                                                    ?>
                                                    <div class="progress-bar-lulus">
                                                        <div class="fill" style="width:<?php echo $pct; ?>%"></div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="periode-badge <?php echo $rv['status']; ?>">
                                                        <?php echo ucfirst($rv['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($rv['dikonfirmasi_kepala']): ?>
                                                        <span style="color:#198754;font-weight:600;font-size:12px;">✅ Ya</span>
                                                    <?php else: ?>
                                                        <span style="color:#6c757d;font-size:12px;">Belum</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
         DETAIL PERIODE TERPILIH
    ========================================================= -->
                    <div class="section-lbl" style="margin-bottom:12px;">Detail Periode</div>

                    <!-- Tab periode -->
                    <div class="periode-tabs">
                        <?php
                        mysqli_data_seek($semua_periode, 0);
                        while ($p = mysqli_fetch_assoc($semua_periode)):
                            $active = ($p['id_periode'] == $id_periode_aktif) ? 'active' : '';
                            $icon   = ['pendaftaran' => '📋', 'berjalan' => '🟢', 'selesai' => '✅'][$p['status']] ?? '';
                        ?>
                            <a href="?periode=<?php echo $p['id_periode']; ?>"
                                class="periode-tab <?php echo $active; ?>">
                                <?php echo "$icon {$p['tahun']} — G{$p['gelombang']}"; ?>
                            </a>
                        <?php endwhile; ?>
                    </div>

                    <?php if ($periode): ?>
                        <div class="row g-4">

                            <!-- Kiri: Info periode + LPJ -->
                            <div class="col-lg-4">

                                <!-- Info periode -->
                                <div class="card4b mb-3">
                                    <div class="card-header">📄 Info Periode</div>
                                    <div class="card-body">
                                        <div class="laporan-info-card">
                                            <div class="info-row">
                                                <span class="info-label">Tahun & Gelombang</span>
                                                <span class="info-val"><?php echo $periode['tahun']; ?> G<?php echo $periode['gelombang']; ?></span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Tanggal</span>
                                                <span class="info-val"><?php echo $periode['tanggal_mulai']; ?> — <?php echo $periode['tanggal_selesai']; ?></span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Lokasi</span>
                                                <span class="info-val"><?php echo htmlspecialchars($periode['lokasi_spesifik'] ?: '-'); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Biaya</span>
                                                <span class="info-val">Rp <?php echo number_format($periode['biaya'], 0, ',', '.'); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Status</span>
                                                <span class="info-val">
                                                    <span class="periode-badge <?php echo $periode['status']; ?>">
                                                        <?php echo ucfirst($periode['status']); ?>
                                                    </span>
                                                </span>
                                            </div>
                                        </div>

                                        <?php if ($periode['dikonfirmasi_kepala']): ?>
                                            <div style="background:#d1e7dd;border-radius:8px;padding:10px 14px;font-size:13px;color:#0a3622;margin-top:12px;">
                                                ✅ Dikonfirmasi Kepala Keamanan<br>
                                                <span style="font-size:12px;"><?php echo $periode['tgl_konfirmasi_kepala']; ?></span>
                                            </div>
                                            <?php if ($periode['file_surat_pernyataan']): ?>
                                                <a href="<?php echo fix_path($periode['file_surat_pernyataan']); ?>"
                                                     target="_blank" class="btn btn-sm btn-success w-100 mt-2" style="border-radius:8px;">
                                                     📝 Lihat Surat Pernyataan
                                                 </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- LPJ Keuangan -->
                                <?php if ($laporan_polda): ?>
                                    <div class="card4b">
                                        <div class="card-header">💰 Keuangan Kegiatan (LPJ)</div>
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
                                                ?>
                                                <div class="info-row">
                                                    <span class="info-label">Selisih</span>
                                                    <span class="info-val">
                                                        <strong style="color:<?php echo $selisih >= 0 ? '#198754' : '#dc3545'; ?>">
                                                            Rp <?php echo number_format(abs($selisih), 0, ',', '.'); ?>
                                                            (<?php echo $selisih >= 0 ? 'Surplus' : 'Defisit'; ?>)
                                                        </strong>
                                                    </span>
                                                </div>
                                            </div>
                                            <?php if ($laporan_polda['file_laporan']): ?>
                                                <a href="<?php echo fix_path($laporan_polda['file_laporan']); ?>"
                                                     target="_blank" class="btn btn-sm btn-outline-primary w-100 mt-2" style="border-radius:8px;">
                                                     📎 Unduh LPJ Polda
                                                 </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>

                            <!-- Kanan: tabel penilaian -->
                            <div class="col-lg-8">

                                <!-- Statistik periode ini -->
                                <div class="stats-row mb-3">
                                    <div class="stat-box4b">
                                        <div class="stat-n"><?php echo $total_p; ?></div>
                                        <div class="stat-lbl">Peserta</div>
                                    </div>
                                    <div class="stat-box4b">
                                        <div class="stat-n" style="color:#198754"><?php echo $lulus_p; ?></div>
                                        <div class="stat-lbl">Lulus</div>
                                    </div>
                                    <div class="stat-box4b">
                                        <div class="stat-n" style="color:#dc3545"><?php echo $tdk_lulus; ?></div>
                                        <div class="stat-lbl">Tidak Lulus</div>
                                    </div>
                                    <div class="stat-box4b">
                                        <div class="stat-n"><?php echo $pct_lulus; ?>%</div>
                                        <div class="stat-lbl">Kelulusan</div>
                                                                   <!-- Progress bar kelulusan -->
                                <?php if ($total_p > 0): ?>
                                    <div style="background:#fff; border-radius:12px; padding:16px; margin-bottom:16px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                                        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
                                            <span style="color:#198754; font-weight:600;">Lulus <?php echo $lulus_p; ?> orang</span>
                                            <span style="color:#dc3545; font-weight:600;">Tidak Lulus <?php echo $tdk_lulus; ?> orang</span>
                                        </div>
                                        <div style="height:12px; background:#e9ecef; border-radius:6px; overflow:hidden;">
                                            <div style="height:100%; width:<?php echo $pct_lulus; ?>%; background:linear-gradient(90deg, #198754, #28a745); border-radius:6px;"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Tabel penilaian -->
                                <div class="card4b">
                                    <div class="card-header">📊 Detail Penilaian Siswa</div>
                                    <div class="card-body p-0">
                                        <?php if (empty($daftar_siswa)): ?>
                                            <div class="text-center py-4 text-muted">
                                                <p>Belum ada peserta di periode ini.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-scroll">
                                                <table class="table table4b table-hover align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Nama</th>
                                                            <th class="text-center">Fisik</th>
                                                            <th class="text-center">Disiplin</th>
                                                            <th class="text-center">Teori</th>
                                                            <th class="text-center">Praktik</th>
                                                            <th class="text-center">Rata-rata</th>
                                                            <th class="text-center">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($daftar_siswa as $i => $s): ?>
                                                            <tr>
                                                                <td style="color:#6c757d;"><?php echo $i + 1; ?></td>
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
                                                                        <span class="badge-status badge-tidak-lulus">❌ Tidak</span>
                                                                    <?php else: ?>
                                                                        <span class="badge-status badge-belum">—</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="../js/dashboard.js"></script>
</body>

</html>
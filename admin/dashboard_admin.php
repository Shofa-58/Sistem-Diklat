<?php
session_start();
include "../koneksi.php";
include "../auto_update_status.php"; /* PBI-030: auto-update status by timer */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("location: ../login.php");
    exit;
}

/* ============================================================
   SEARCH & FILTER (PBI-030)
   ============================================================ */
$search      = isset($_GET['q'])       ? mysqli_real_escape_string($conn, trim($_GET['q']))       : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status'])        : '';
$filter_periode = isset($_GET['periode']) ? (int) $_GET['periode']                                 : 0;

/* Build WHERE */
$where_parts = [];
if ($search !== '') {
    $where_parts[] = "(s.nama LIKE '%$search%' OR s.email LIKE '%$search%' OR s.no_telp LIKE '%$search%')";
}
if ($filter_status !== '') {
    $where_parts[] = "s.status = '$filter_status'";
}
$where_sql = $where_parts ? "WHERE " . implode(' AND ', $where_parts) : '';

/* Join peserta_periode jika filter periode */
$join_sql = '';
if ($filter_periode) {
    $join_sql    = "JOIN peserta_periode pp ON s.id_peserta = pp.id_peserta AND pp.id_periode='$filter_periode'";
}

$data = mysqli_query($conn,
    "SELECT s.*,
            (SELECT COUNT(*) FROM dokumen_pendaftaran WHERE id_siswa=s.id_peserta AND status_verifikasi='revisi') AS jml_revisi,
            (SELECT COUNT(*) FROM dokumen_pendaftaran WHERE id_siswa=s.id_peserta AND status_verifikasi='pending') AS jml_pending
     FROM siswa s
     $join_sql
     $where_sql
     ORDER BY s.created_at DESC"
);

/* Stats ringkas */
$stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        COUNT(*) AS total,
        SUM(status='calon') AS calon,
        SUM(status='terverifikasi') AS terverifikasi,
        SUM(status='peserta') AS peserta,
        SUM(status='lulus') AS lulus,
        SUM(status='tidak_lulus') AS tidak_lulus
     FROM siswa"
));

/* Daftar periode untuk filter dropdown */
$semua_periode = mysqli_query($conn,
    "SELECT * FROM periode_diklat ORDER BY tahun DESC, gelombang DESC"
);

/* Cek evaluasi pending konfirmasi */
$pending_eval = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS jml FROM evaluasi
     WHERE dikonfirmasi_admin=0"
));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin — Gemilang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard_layout.css">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <style>

        /* Navbar */
        .admin-nav { background: var(--navy); }
        .admin-nav .navbar-brand { color: var(--yellow) !important; font-weight: 700; }

        /* Page header */
        .page-header {
            background: var(--navy);
            color: #fff;
            padding: 24px 0 20px;
            margin-bottom: 28px;
        }
        .page-header h4 { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
        .page-header p  { font-size: 13px; color: #adb5bd; margin: 0; }

        /* Stat cards */
        .stat-row { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 24px; }
        @media (max-width: 768px) { .stat-row { grid-template-columns: repeat(3, 1fr); } }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            text-decoration: none;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,0.12); }
        .stat-card .val  { font-size: 28px; font-weight: 800; line-height: 1; color: var(--navy); }
        .stat-card .lbl  { font-size: 11px; color: #6c757d; margin-top: 4px; }

        /* Filter bar */
        .filter-bar {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-bar .form-control,
        .filter-bar .form-select {
            border-radius: 10px;
            border: 2px solid #dee2e6;
            font-size: 13px;
        }
        .filter-bar .form-control:focus,
        .filter-bar .form-select:focus {
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(13,27,42,0.1);
        }
        .filter-bar label { font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #495057; }
        .btn-filter {
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .btn-filter:hover { background: #1a2d42; }
        .btn-reset {
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 13px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-reset:hover { background: #e9ecef; color: #343a40; }

        /* Main card */
        .main-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .main-card .card-header-bar {
            padding: 16px 20px;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .main-card .card-header-bar h5 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--navy);
        }

        /* Table */
        .table-admin { font-size: 13px; }
        .table-admin th { background: var(--navy); color: #fff; font-weight: 600; white-space: nowrap; }
        .table-admin td { vertical-align: middle; }
        .table-admin tbody tr:hover { background: #f8f9fb; }

        /* Status badges */
        .status_badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
        }
        .status_badge.calon         { background: #6c757d; }
        .status_badge.terverifikasi  { background: #0d6efd; }
        .status_badge.peserta        { background: #fd7e14; }
        .status_badge.lulus          { background: #198754; }
        .status_badge.tidak_lulus    { background: #dc3545; }

        /* Doc badges */
        .doc-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        .doc-badge.revisi   { background: #f8d7da; color: #842029; }
        .doc-badge.pending  { background: #fff3cd; color: #664d03; }
        .doc-badge.ok       { background: #d1e7dd; color: #0a3622; }

        /* Action btns */
        .btn-aksi {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }
        .btn-aksi.biodata  { background: #cfe2ff; color: #084298; }
        .btn-aksi.dokumen  { background: #d1e7dd; color: #0a3622; }
        .btn-aksi.status   { background: #fff3cd; color: #664d03; }

        /* Alert eval pending */
        .alert-eval {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #ffc107;
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #664d03;
        }

        /* Quick nav menu */
        .quick-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .quick-nav a {
            background: #fff;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            color: var(--navy);
            text-decoration: none;
            transition: all 0.2s;
        }
        .quick-nav a:hover {
            background: var(--navy);
            color: #fff;
            border-color: var(--navy);
        }
        .quick-nav a.active {
            background: var(--navy);
            color: var(--yellow);
            border-color: var(--navy);
        }

        /* Badge notif */
        .notif-dot {
            display: inline-block;
            width: 18px;
            height: 18px;
            background: #dc3545;
            color: #fff;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
            margin-left: 4px;
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            ⚙️ Admin Gemilang
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_admin.php" class="active">
                    <span>👥</span> Data Peserta
                </a>
            </li>
            <li>
                <a href="admin_evaluasi.php">
                    <span>📊</span> Evaluasi Nilai
                    <?php if ($pending_eval['jml'] > 0): ?>
                    <span class="notif-dot"><?php echo $pending_eval['jml']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="admin_persiapan_diklat.php">
                    <span>📅</span> Persiapan Diklat
                </a>
            </li>
            <li>
                <a href="admin_status_siswa.php">
                    <span>🔄</span> Kelola Status
                </a>
            </li>
            <li>
                <a href="../tambah_akun.php">
                    <span>👤</span> Buat Akun
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
                <h1 class="page-title">Data Peserta & Pendaftaran</h1>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                    Admin: <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
        </header>

        <div class="content-body">

            <!-- Alert evaluasi pending -->
            <?php if ($pending_eval['jml'] > 0): ?>
            <div class="alert-eval">
                ⚠️ Ada <strong><?php echo $pending_eval['jml']; ?> nilai siswa</strong> yang belum dikonfirmasi.
                <a href="admin_evaluasi.php" style="color:#664d03;font-weight:700;">→ Buka Evaluasi</a>
            </div>
            <?php endif; ?>

    <!-- Stat cards -->
    <div class="stat-row">
        <a href="?status=" class="stat-card" style="border-top:3px solid var(--navy);">
            <div class="val"><?php echo $stats['total']; ?></div>
            <div class="lbl">Total</div>
        </a>
        <a href="?status=calon" class="stat-card" style="border-top:3px solid #6c757d;">
            <div class="val" style="color:#6c757d"><?php echo $stats['calon']; ?></div>
            <div class="lbl">Calon</div>
        </a>
        <a href="?status=terverifikasi" class="stat-card" style="border-top:3px solid #0d6efd;">
            <div class="val" style="color:#0d6efd"><?php echo $stats['terverifikasi']; ?></div>
            <div class="lbl">Terverifikasi</div>
        </a>
        <a href="?status=peserta" class="stat-card" style="border-top:3px solid #fd7e14;">
            <div class="val" style="color:#fd7e14"><?php echo $stats['peserta']; ?></div>
            <div class="lbl">Peserta</div>
        </a>
        <a href="?status=lulus" class="stat-card" style="border-top:3px solid #198754;">
            <div class="val" style="color:#198754"><?php echo $stats['lulus']; ?></div>
            <div class="lbl">Lulus</div>
        </a>
        <a href="?status=tidak_lulus" class="stat-card" style="border-top:3px solid #dc3545;">
            <div class="val" style="color:#dc3545"><?php echo $stats['tidak_lulus']; ?></div>
            <div class="lbl">Tidak Lulus</div>
        </a>
    </div>

    <!-- Filter bar (PBI-030) -->
    <form method="GET" action="dashboard_admin.php">
        <div class="filter-bar">
            <div>
                <label>Cari Peserta</label>
                <input type="text" name="q" class="form-control" placeholder="Nama / email / no HP..."
                       value="<?php echo htmlspecialchars($search); ?>" style="min-width:220px;">
            </div>
            <div>
                <label>Filter Status</label>
                <select name="status" class="form-select" style="min-width:160px;">
                    <option value="">Semua Status</option>
                    <option value="calon"         <?php echo $filter_status==='calon'?'selected':''; ?>>Calon</option>
                    <option value="terverifikasi"  <?php echo $filter_status==='terverifikasi'?'selected':''; ?>>Terverifikasi</option>
                    <option value="peserta"        <?php echo $filter_status==='peserta'?'selected':''; ?>>Peserta</option>
                    <option value="lulus"          <?php echo $filter_status==='lulus'?'selected':''; ?>>Lulus</option>
                    <option value="tidak_lulus"    <?php echo $filter_status==='tidak_lulus'?'selected':''; ?>>Tidak Lulus</option>
                </select>
            </div>
            <div>
                <label>Filter Periode</label>
                <select name="periode" class="form-select" style="min-width:180px;">
                    <option value="0">Semua Periode</option>
                    <?php
                    mysqli_data_seek($semua_periode, 0);
                    while ($p = mysqli_fetch_assoc($semua_periode)) {
                        $sel = $filter_periode == $p['id_periode'] ? 'selected' : '';
                        echo "<option value='{$p['id_periode']}' $sel>
                                {$p['tahun']} — Gel. {$p['gelombang']}
                              </option>";
                    }
                    ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;">
                <button type="submit" class="btn-filter">🔍 Cari</button>
                <a href="dashboard_admin.php" class="btn-reset">Reset</a>
            </div>
        </div>
    </form>

    <!-- Tabel peserta -->
    <div class="main-card">
        <div class="card-header-bar">
            <h5>
                Data Pendaftar
                <?php
                $jml = mysqli_num_rows($data);
                echo "<span style='font-size:13px;color:#6c757d;font-weight:400;margin-left:8px;'>
                        ($jml data)
                      </span>";
                ?>
            </h5>
            <?php if ($search || $filter_status || $filter_periode): ?>
            <span style="font-size:12px;color:#0d6efd;">
                Filter aktif
                <?php if ($search): ?> · "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                <?php if ($filter_status): ?> · <?php echo ucfirst(str_replace('_',' ',$filter_status)); ?><?php endif; ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-admin table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="min-width:160px;">Nama</th>
                        <th>Email</th>
                        <th>No HP</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Dokumen</th>
                        <th>Batas Revisi</th>
                        <th>Tgl Daftar</th>
                        <th class="text-center" style="min-width:200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($data) === 0): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <div style="font-size:32px;margin-bottom:8px;">🔍</div>
                        <p class="mb-0">Tidak ada data yang sesuai filter.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php while ($d = mysqli_fetch_assoc($data)): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--navy);">
                            <?php echo htmlspecialchars($d['nama']); ?>
                        </div>
                        <div style="font-size:11px;color:#6c757d;">
                            <?php echo $d['jenis_kelamin'] === 'L' ? '♂ Laki-laki' : '♀ Perempuan'; ?>
                        </div>
                    </td>
                    <td style="font-size:12px;"><?php echo htmlspecialchars($d['email']); ?></td>
                    <td style="font-size:12px;"><?php echo htmlspecialchars($d['no_telp']); ?></td>
                    <td class="text-center">
                        <span class="status_badge <?php echo $d['status']; ?>">
                            <?php echo ucfirst(str_replace('_',' ', $d['status'])); ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <?php if ($d['jml_revisi'] > 0): ?>
                            <span class="doc-badge revisi">⚠️ <?php echo $d['jml_revisi']; ?> revisi</span>
                        <?php elseif ($d['jml_pending'] > 0): ?>
                            <span class="doc-badge pending">⏳ <?php echo $d['jml_pending']; ?> pending</span>
                        <?php else: ?>
                            <span class="doc-badge ok">✓ OK</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;">
                        <?php
                        if ($d['batas_revisi']) {
                            $lewat = strtotime(date('Y-m-d')) > strtotime($d['batas_revisi']);
                            $warna = $lewat ? '#dc3545' : '#198754';
                            echo "<span style='color:$warna'>{$d['batas_revisi']}</span>";
                        } else {
                            echo '<span style="color:#adb5bd;">—</span>';
                        }
                        ?>
                    </td>
                    <td style="font-size:12px;">
                        <?php echo date('d/m/Y', strtotime($d['created_at'])); ?>
                    </td>
                    <td class="text-center">
                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                            <a href="admin_lihat_biodata.php?id=<?php echo $d['id_peserta']; ?>"
                               class="btn-aksi biodata">Biodata</a>
                            <a href="admin_lihat_dokumen.php?id=<?php echo $d['id_peserta']; ?>"
                               class="btn-aksi dokumen">
                                Dokumen
                                <?php if ($d['jml_revisi'] > 0 || $d['jml_pending'] > 0): ?>
                                <span style="display:inline-block;width:6px;height:6px;
                                             background:#dc3545;border-radius:50%;
                                             margin-left:2px;vertical-align:middle;"></span>
                                <?php endif; ?>
                            </a>
                            <a href="admin_status_siswa.php?id=<?php echo $d['id_peserta']; ?>"
                               class="btn-aksi status">Status</a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

        </div> <!-- End content-body -->
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
/* Enter di search langsung submit */
document.querySelector('input[name="q"]')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') this.closest('form').submit();
});

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
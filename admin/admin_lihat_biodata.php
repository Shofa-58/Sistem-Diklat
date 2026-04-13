<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("location: dashboard_admin.php");
    exit;
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$query = mysqli_query($conn,
    "SELECT s.*, pp.tanggal_terima, pd.tahun, pd.gelombang
     FROM siswa s
     LEFT JOIN peserta_periode pp ON s.id_peserta = pp.id_peserta
     LEFT JOIN periode_diklat pd ON pp.id_periode = pd.id_periode
     WHERE s.id_peserta = '$id'
     LIMIT 1"
);

$data = mysqli_fetch_assoc($query);

if (!$data) {
    echo "Data tidak ditemukan.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Biodata Siswa — Gemilang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/admin_biodata.css">
</head>
<body style="background:#f0f2f5;">

<nav class="navbar navbar-dark" style="background-color: var(--navy);">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard_admin.php">⚙️ Admin Gemilang</a>
        <div class="d-flex gap-2">
            <a href="admin_lihat_dokumen.php?id=<?php echo $id; ?>"
               class="btn btn-sm btn-outline-warning">Lihat Dokumen</a>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="card shadow biodata-card">
        <div class="card-body p-4 p-md-5">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1" style="color:var(--navy);font-weight:700;">
                        <?php echo htmlspecialchars($data['nama']); ?>
                    </h4>
                    <p style="font-size:13px;color:#6c757d;margin:0;">
                        Terdaftar sejak <?php echo date('d M Y', strtotime($data['created_at'])); ?>
                    </p>
                </div>
                <span class="badge status_badge <?php echo $data['status']; ?>">
                    <?php echo strtoupper(str_replace('_', ' ', $data['status'])); ?>
                </span>
            </div>

            <?php if ($data['tahun']): ?>
            <div style="background:#cfe2ff;border-radius:10px;padding:10px 16px;
                        font-size:13px;color:#084298;margin-bottom:20px;">
                🎓 Peserta Periode <strong><?php echo $data['tahun']; ?> — Gelombang <?php echo $data['gelombang']; ?></strong>
            </div>
            <?php endif; ?>

            <!-- Data Diri -->
            <h6 style="font-size:12px;font-weight:700;color:#6c757d;text-transform:uppercase;
                       letter-spacing:0.5px;margin-bottom:16px;padding-bottom:8px;
                       border-bottom:2px solid #f0f2f5;">
                Data Diri
            </h6>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Nama Lengkap</label>
                    <div class="info-box"><?php echo htmlspecialchars($data['nama']); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <div class="info-box"><?php echo htmlspecialchars($data['email']); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">No HP</label>
                    <div class="info-box"><?php echo htmlspecialchars($data['no_telp']); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Jenis Kelamin</label>
                    <div class="info-box">
                        <?php echo $data['jenis_kelamin'] === 'L' ? '♂ Laki-laki' : '♀ Perempuan'; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tanggal Lahir</label>
                    <div class="info-box">
                        <?php echo date('d F Y', strtotime($data['tgl_lahir'])); ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Agama</label>
                    <div class="info-box"><?php echo htmlspecialchars($data['agama']); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tinggi Badan</label>
                    <div class="info-box"><?php echo $data['tinggi_badan']; ?> cm</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Berat Badan</label>
                    <div class="info-box"><?php echo $data['berat_badan']; ?> kg</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Alamat</label>
                    <div class="info-box"><?php echo htmlspecialchars($data['alamat']); ?></div>
                </div>
            </div>

            <!-- Informasi Administratif -->
            <h6 style="font-size:12px;font-weight:700;color:#6c757d;text-transform:uppercase;
                       letter-spacing:0.5px;margin-bottom:16px;padding-bottom:8px;
                       border-bottom:2px solid #f0f2f5;">
                Informasi Administratif
            </h6>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Tanggal Daftar</label>
                    <div class="info-box">
                        <?php echo date('d M Y H:i', strtotime($data['created_at'])); ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Batas Revisi Dokumen</label>
                    <div class="info-box">
                        <?php if ($data['batas_revisi']): ?>
                            <?php
                            $lewat = strtotime(date('Y-m-d')) > strtotime($data['batas_revisi']);
                            $warna = $lewat ? '#dc3545' : '#198754';
                            echo "<span style='color:$warna;font-weight:600;'>{$data['batas_revisi']}</span>";
                            if ($lewat) echo " <small style='color:#dc3545;'>(sudah lewat)</small>";
                            ?>
                        <?php else: ?>
                            <span style="color:#adb5bd;">Tidak ada</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tanggal Diterima ke Periode</label>
                    <div class="info-box">
                        <?php echo $data['tanggal_terima'] ? date('d M Y', strtotime($data['tanggal_terima'])) : '—'; ?>
                    </div>
                </div>
            </div>

            <!-- Tombol aksi -->
            <div class="d-flex gap-2 flex-wrap">
                <a href="dashboard_admin.php" class="btn btn-secondary btn-sm px-4">
                    ← Kembali
                </a>
                <a href="admin_lihat_dokumen.php?id=<?php echo $id; ?>"
                   class="btn btn-warning btn-sm px-4">
                    Lihat & Verifikasi Dokumen
                </a>
                <a href="admin_status_siswa.php?id=<?php echo $id; ?>"
                   class="btn btn-outline-primary btn-sm px-4">
                    Ubah Status
                </a>
            </div>

        </div>
    </div>
</div>

</body>
</html>
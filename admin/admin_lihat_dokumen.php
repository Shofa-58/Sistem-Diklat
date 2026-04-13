<?php
session_start();
include "../koneksi.php";
include "../helpers.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("location: dashboard_admin.php");
    exit;
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

/* PROSES UPDATE SEMUA DOKUMEN */
if (isset($_POST['simpan_semua'])) {

    foreach ($_POST['status_verifikasi'] as $id_dokumen => $status) {
        $catatan    = mysqli_real_escape_string($conn, $_POST['catatan_admin'][$id_dokumen]);
        $id_dokumen = (int) $id_dokumen;

        mysqli_query($conn,
            "UPDATE dokumen_pendaftaran
             SET status_verifikasi = '$status',
                 catatan_admin     = '$catatan'
             WHERE id_dokumen = '$id_dokumen'"
        );
    }

    /* CEK STATUS DOKUMEN */
    $cek   = mysqli_query($conn,
        "SELECT status_verifikasi FROM dokumen_pendaftaran WHERE id_siswa='$id'"
    );
    $total  = 0; $valid = 0; $revisi = 0;

    while ($row = mysqli_fetch_assoc($cek)) {
        $total++;
        if ($row['status_verifikasi'] === 'valid')  $valid++;
        if ($row['status_verifikasi'] === 'revisi') $revisi++;
    }

    /* UPDATE STATUS SISWA */
    if ($valid === $total && $total > 0) {

        mysqli_query($conn,
            "UPDATE siswa SET status='terverifikasi', batas_revisi=NULL
             WHERE id_peserta='$id'"
        );
        $pesan = "Semua dokumen valid. Siswa otomatis terverifikasi.";

    } elseif ($revisi > 0) {

        /* Ambil data siswa untuk kirim email */
        $dataSiswa = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT nama, email FROM siswa WHERE id_peserta='$id'"
        ));

        /* Set batas revisi 7 hari dari sekarang */
        $batas_revisi = date('Y-m-d', strtotime('+7 days'));
        mysqli_query($conn,
            "UPDATE siswa SET batas_revisi='$batas_revisi'
             WHERE id_peserta='$id'"
        );

        /* Kumpulkan catatan dokumen revisi */
        $catatanRevisi = mysqli_query($conn,
            "SELECT jenis, catatan_admin
             FROM dokumen_pendaftaran
             WHERE id_siswa='$id' AND status_verifikasi='revisi'"
        );
        $listRevisi = [];
        while ($r = mysqli_fetch_assoc($catatanRevisi)) {
            $listRevisi[] = strtoupper($r['jenis']) . ": " . ($r['catatan_admin'] ?: 'Perlu diperbaiki');
        }
        $pesanRevisi = implode("\n", $listRevisi);

        /* Kirim notifikasi email ke siswa */
        $emailBody = "Dokumen Anda memerlukan revisi:\n\n" . $pesanRevisi
                   . "\n\nBatas waktu revisi: $batas_revisi"
                   . "\n\nSilakan login dan upload ulang dokumen sebelum batas waktu revisi.";

        $terkirim = kirimEmailAkun(
            $dataSiswa['email'],
            $dataSiswa['nama'],
            'REVISI DOKUMEN',
            $emailBody
        );

        /* Simpan ke tabel notifikasi */
        $pesanDB    = mysqli_real_escape_string($conn, $emailBody);
        $statusKirim = $terkirim ? 'terkirim' : 'gagal';
        mysqli_query($conn,
            "INSERT INTO notifikasi (id_siswa, jenis, pesan, status_kirim)
             VALUES ('$id', 'revisi', '$pesanDB', '$statusKirim')"
        );

        /* Reset status siswa ke calon */
        mysqli_query($conn,
            "UPDATE siswa SET status='calon' WHERE id_peserta='$id'"
        );

        $pesan = "Ada dokumen revisi. Batas revisi ditetapkan: $batas_revisi. "
               . "Notifikasi " . ($terkirim ? "berhasil" : "gagal") . " dikirim ke email siswa.";

    } else {
        $pesan = "Perubahan disimpan. Masih ada dokumen dengan status pending.";
    }

    echo "<script>
            alert('$pesan');
            window.location='admin_lihat_dokumen.php?id=$id';
          </script>";
    exit;
}

/* Ambil nama siswa & status */
$siswa = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT nama, status, batas_revisi FROM siswa WHERE id_peserta='$id'"
));

/* Ambil dokumen */
$dokumen = mysqli_query($conn,
    "SELECT * FROM dokumen_pendaftaran
     WHERE id_siswa='$id'
     ORDER BY created_at DESC"
);

$label_map = [
    'ktp'             => 'KTP',
    'ijazah'          => 'Ijazah Terakhir',
    'kk'              => 'Kartu Keluarga',
    'skck'            => 'SKCK',
    'pembayaran'      => 'Bukti Pembayaran',
    'surat_kesehatan' => 'Surat Kesehatan',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dokumen Siswa — Gemilang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/admin_dokumen.css">
    <style>
        body { background: #f0f2f5; }
        .dokumen-card { border-radius: 14px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .info-panel {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            font-size: 13px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark" style="background-color: var(--navy);">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard_admin.php">⚙️ Admin Gemilang</a>
        <div class="d-flex gap-2">
            <a href="admin_lihat_biodata.php?id=<?php echo $id; ?>"
               class="btn btn-sm btn-outline-light">Lihat Biodata</a>
        </div>
    </div>
</nav>

<div class="container my-4">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 style="color:var(--navy);font-weight:700;margin:0;">
                Dokumen: <?php echo htmlspecialchars($siswa['nama']); ?>
            </h4>
            <span style="font-size:13px;color:#6c757d;">
                Status saat ini:
                <strong><?php echo ucfirst(str_replace('_',' ',$siswa['status'])); ?></strong>
            </span>
        </div>
        <a href="dashboard_admin.php" class="btn btn-secondary btn-sm">← Kembali</a>
    </div>

    <!-- Info batas revisi jika ada -->
    <?php if ($siswa['batas_revisi']): ?>
    <div class="info-panel" style="border-left:4px solid #ffc107;background:#fffbf0;">
        ⏰ Batas revisi dokumen untuk siswa ini:
        <strong><?php echo $siswa['batas_revisi']; ?></strong>
        <?php
        $sisa = (int) round((strtotime($siswa['batas_revisi']) - strtotime(date('Y-m-d'))) / 86400);
        if ($sisa < 0)      echo " <span style='color:#dc3545;'>(sudah lewat)</span>";
        elseif ($sisa === 0) echo " <span style='color:#dc3545;'>(hari ini)</span>";
        else                 echo " <span style='color:#198754;'>($sisa hari lagi)</span>";
        ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <?php $jumlah_dok = 0; while ($d = mysqli_fetch_assoc($dokumen)): $jumlah_dok++; ?>
        <div class="card shadow-sm mb-4 dokumen-card">
            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                    <h5 class="mb-2">
                        <?php echo $label_map[$d['jenis']] ?? ucfirst($d['jenis']); ?>
                    </h5>
                    <span class="badge dokumen_badge <?php echo $d['status_verifikasi']; ?>">
                        <?php echo strtoupper($d['status_verifikasi']); ?>
                    </span>
                </div>

                <p class="text-muted small mb-1">
                    Upload: <?php echo date('d M Y H:i', strtotime($d['tgl_upload'])); ?>
                </p>
                <?php if ($d['tgl_revisi']): ?>
                <p class="text-muted small mb-1">
                    Revisi: <?php echo date('d M Y H:i', strtotime($d['tgl_revisi'])); ?>
                </p>
                <?php endif; ?>

                <div class="mb-3">
                    <a href="../<?php echo htmlspecialchars($d['file_path']); ?>"
                       target="_blank"
                       class="btn btn-outline-primary btn-sm">
                       📄 Lihat File
                    </a>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold" style="font-size:13px;">Status Verifikasi</label>
                        <select name="status_verifikasi[<?php echo $d['id_dokumen']; ?>]"
                                class="form-select" required>
                            <option value="pending" <?php echo $d['status_verifikasi']==='pending'?'selected':''; ?>>
                                ⏳ Pending
                            </option>
                            <option value="valid"   <?php echo $d['status_verifikasi']==='valid'?'selected':''; ?>>
                                ✅ Valid
                            </option>
                            <option value="revisi"  <?php echo $d['status_verifikasi']==='revisi'?'selected':''; ?>>
                                ⚠️ Revisi
                            </option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold" style="font-size:13px;">
                            Catatan Admin
                            <span style="font-weight:400;color:#6c757d;">(wajib diisi jika revisi)</span>
                        </label>
                        <textarea name="catatan_admin[<?php echo $d['id_dokumen']; ?>]"
                                  class="form-control" rows="2"
                                  placeholder="Contoh: Foto tidak jelas, KTP expired, dll."
                        ><?php echo htmlspecialchars($d['catatan_admin'] ?? ''); ?></textarea>
                    </div>
                </div>

            </div>
        </div>
        <?php endwhile; ?>

        <?php if ($jumlah_dok === 0): ?>
        <div class="text-center py-5 text-muted">
            <div style="font-size:48px;margin-bottom:12px;">📁</div>
            <p>Belum ada dokumen yang diupload oleh siswa ini.</p>
        </div>
        <?php else: ?>
        <div class="d-flex gap-2 justify-content-end">
            <a href="dashboard_admin.php" class="btn btn-secondary">Batal</a>
            <button type="submit" name="simpan_semua" class="btn btn-warning px-5 fw-bold">
                💾 Simpan & Kirim Notifikasi
            </button>
        </div>
        <p style="font-size:12px;color:#6c757d;text-align:right;margin-top:8px;">
            Jika ada dokumen revisi, batas waktu perbaikan otomatis ditetapkan 7 hari dari sekarang.
        </p>
        <?php endif; ?>

    </form>

</div>

</body>
</html>
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit;
}

$id_akun  = $_SESSION['id_akun'];
$siswa    = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM siswa WHERE id_akun='$id_akun' LIMIT 1"
));

if (!$siswa) { header("Location: dashboard_peserta.php"); exit; }

$id_siswa     = $siswa['id_peserta'];
$batas_revisi = $siswa['batas_revisi'];
$hari_ini     = date('Y-m-d');

$masih_bisa_revisi = true;
$sudah_kadaluarsa  = false;
if ($batas_revisi && strtotime($hari_ini) > strtotime($batas_revisi)) {
    $masih_bisa_revisi = false;
    $sudah_kadaluarsa  = true;
}

$query_revisi  = mysqli_query($conn,
    "SELECT * FROM dokumen_pendaftaran
     WHERE id_siswa='$id_siswa' AND status_verifikasi='revisi'
     ORDER BY tgl_upload DESC"
);
$jumlah_revisi = mysqli_num_rows($query_revisi);

$pesan_sukses = '';
$pesan_error  = '';

/* ============================================================
   PROSES POST
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_revisi'])) {

    if ($sudah_kadaluarsa) {
        $pesan_error = "Masa revisi sudah berakhir.";
        goto tampilForm;
    }

    /* ------ Update biodata ------ */
    $nama         = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $no_telp      = mysqli_real_escape_string($conn, trim($_POST['no_telp']));
    $alamat       = mysqli_real_escape_string($conn, trim($_POST['alamat']));
    $tgl_lahir    = $_POST['tgl_lahir'];
    $agama        = mysqli_real_escape_string($conn, $_POST['agama']);
    $jenis_kelamin= $_POST['jenis_kelamin'];
    $tinggi_badan = (int) $_POST['tinggi_badan'];
    $berat_badan  = (int) $_POST['berat_badan'];

    mysqli_query($conn,
        "UPDATE siswa SET
            nama='$nama', no_telp='$no_telp', alamat='$alamat',
            tgl_lahir='$tgl_lahir', agama='$agama',
            jenis_kelamin='$jenis_kelamin',
            tinggi_badan='$tinggi_badan', berat_badan='$berat_badan'
         WHERE id_peserta='$id_siswa'"
    );

    /* ------ Upload dokumen ulang ------ */
    $allowed_ext = ['jpg','jpeg','png','pdf'];
    $folder      = "../uploads/$id_siswa";
    if (!is_dir($folder)) mkdir($folder, 0777, true);

    $berhasil = 0; $gagal = 0;

    foreach ($_FILES as $field_name => $file_info) {
        if (strpos($field_name, 'file_revisi_') !== 0) continue;
        if (empty($file_info['name'])) continue;

        $jenis = str_replace('file_revisi_', '', $field_name);
        $jenis = mysqli_real_escape_string($conn, $jenis);
        $ext   = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext) || $file_info['size'] > 5 * 1024 * 1024) {
            $gagal++;
            continue;
        }

        $dokLama = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT file_path FROM dokumen_pendaftaran
             WHERE id_siswa='$id_siswa' AND jenis='$jenis' LIMIT 1"
        ));
        if ($dokLama && file_exists($dokLama['file_path'])) {
            @unlink($dokLama['file_path']);
        }

        $new_name  = $jenis . "." . $ext;
        $file_path = "$folder/$new_name";

        if (move_uploaded_file($file_info['tmp_name'], $file_path)) {
            $tgl_revisi    = date("Y-m-d H:i:s");
            $file_path_esc = mysqli_real_escape_string($conn, $file_path);
            mysqli_query($conn,
                "UPDATE dokumen_pendaftaran
                 SET file_path='$file_path_esc', status_verifikasi='pending',
                     catatan_admin=NULL, tgl_revisi='$tgl_revisi'
                 WHERE id_siswa='$id_siswa' AND jenis='$jenis'"
            );
            $berhasil++;
        } else {
            $gagal++;
        }
    }

    if ($berhasil > 0 && $gagal === 0) {
        $pesan_sukses = "Biodata dan $berhasil dokumen berhasil diperbarui.";
    } elseif ($berhasil > 0) {
        $pesan_sukses = "Biodata diperbarui. $berhasil dokumen berhasil, $gagal gagal.";
    } elseif ($gagal === 0) {
        $pesan_sukses = "Biodata berhasil diperbarui.";
    } else {
        $pesan_error = "Gagal mengupload dokumen. Pastikan format JPG/PNG/PDF dan maks 5MB.";
    }

    /* Reload data siswa setelah update */
    $siswa = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM siswa WHERE id_peserta='$id_siswa' LIMIT 1"
    ));
    $query_revisi  = mysqli_query($conn,
        "SELECT * FROM dokumen_pendaftaran
         WHERE id_siswa='$id_siswa' AND status_verifikasi='revisi'
         ORDER BY tgl_upload DESC"
    );
    $jumlah_revisi = mysqli_num_rows($query_revisi);
}

tampilForm:

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
    <title>Revisi Data — Gemilang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/revisi_dokumen.css">
    <link rel="stylesheet" href="../css/dashboard_layout.css">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .biodata-section {
            background: #fff;
            border-radius: 14px;
            padding: 18px 16px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
        }
        .biodata-section h6 {
            font-size: 14px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f2f5;
        }
        .biodata-section .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #495057;
        }
        .biodata-section .form-control,
        .biodata-section .form-select {
            border-radius: 10px;
            font-size: 14px;
            border: 2px solid #dee2e6;
        }
        .biodata-section .form-control:focus,
        .biodata-section .form-select:focus {
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(13,27,42,0.10);
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            Gemilang 👮
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_peserta.php">
                    <span>🏠</span> Dashboard Siswa
                </a>
            </li>
            <li>
                <a href="#" class="active">
                    <span>📝</span> Revisi Dokumen
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
                <h1 class="page-title">Revisi Dokumen</h1>
            </div>
            <div>
                <a href="dashboard_peserta.php" class="btn btn-outline-secondary btn-sm" style="border-radius:20px;">
                    ← Kembali
                </a>
            </div>
        </header>

        <div class="content-body">

<div class="rev-container">

    <!-- Header siswa -->
    <div class="rev-header-card">
        <h5>Perbarui data untuk</h5>
        <p class="siswa-nama"><?php echo htmlspecialchars($siswa['nama']); ?></p>
    </div>

    <!-- Alert -->
    <?php if ($pesan_sukses): ?>
    <div class="rev-alert-sukses">✅ <?php echo htmlspecialchars($pesan_sukses); ?></div>
    <?php endif; ?>
    <?php if ($pesan_error): ?>
    <div class="alert alert-danger" style="border-radius:10px;font-size:14px;">
        ⚠️ <?php echo htmlspecialchars($pesan_error); ?>
    </div>
    <?php endif; ?>

    <!-- Batas waktu -->
    <?php if ($batas_revisi): ?>
        <?php if ($sudah_kadaluarsa): ?>
        <div class="rev-alert-locked">
            <div class="lock-icon">🔒</div>
            <div class="lock-title">Masa Revisi Telah Berakhir</div>
            <div class="lock-desc">
                Batas revisi: <strong><?php echo $batas_revisi; ?></strong>.<br>
                Hubungi admin untuk informasi lebih lanjut.
            </div>
        </div>
        <?php else: ?>
        <div class="rev-alert-deadline">
            <div class="deadline-title">⏰ Batas Waktu Revisi</div>
            <div class="deadline-info">
                Kirim sebelum <strong><?php echo $batas_revisi; ?></strong>.
                <?php
                $sisa = (int) round((strtotime($batas_revisi) - strtotime($hari_ini)) / 86400);
                if ($sisa === 0)      echo "<strong style='color:#dc3545'>Hari ini batas terakhir!</strong>";
                elseif ($sisa === 1)  echo "<strong style='color:#e67e22'>Sisa 1 hari lagi.</strong>";
                else                  echo " Sisa <strong>$sisa hari</strong> lagi.";
                ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$sudah_kadaluarsa): ?>
    <form method="POST" enctype="multipart/form-data" id="formRevisi">

    <!-- ====================================================
         BAGIAN 1: FORM BIODATA
    ==================================================== -->
    <div class="biodata-section">
        <h6>📋 Perbarui Biodata</h6>
        <p style="font-size:12px;color:#6c757d;margin-bottom:14px;">
            Data di bawah sudah terisi. Ubah jika ada yang tidak sesuai.
        </p>

        <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control"
                   value="<?php echo htmlspecialchars($siswa['nama']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Nomor HP</label>
            <input type="text" name="no_telp" class="form-control"
                   value="<?php echo htmlspecialchars($siswa['no_telp']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Alamat</label>
            <textarea name="alamat" class="form-control" rows="3" required
                      style="resize:none;"><?php echo htmlspecialchars($siswa['alamat']); ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Tanggal Lahir</label>
            <input type="date" name="tgl_lahir" class="form-control"
                   value="<?php echo $siswa['tgl_lahir']; ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Jenis Kelamin</label>
            <div class="d-flex gap-3 mt-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="jenis_kelamin"
                           value="L" <?php echo $siswa['jenis_kelamin']==='L'?'checked':''; ?> required>
                    <label class="form-check-label" style="font-size:14px;">Laki-laki</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="jenis_kelamin"
                           value="P" <?php echo $siswa['jenis_kelamin']==='P'?'checked':''; ?>>
                    <label class="form-check-label" style="font-size:14px;">Perempuan</label>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Agama</label>
            <select name="agama" class="form-select" required>
                <?php
                $agama_list = ['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'];
                foreach ($agama_list as $ag) {
                    $sel = $siswa['agama'] === $ag ? 'selected' : '';
                    echo "<option value='$ag' $sel>$ag</option>";
                }
                ?>
            </select>
        </div>

        <div class="row g-2">
            <div class="col-6">
                <label class="form-label">Tinggi Badan (cm)</label>
                <input type="number" name="tinggi_badan" class="form-control"
                       value="<?php echo $siswa['tinggi_badan']; ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">Berat Badan (kg)</label>
                <input type="number" name="berat_badan" class="form-control"
                       value="<?php echo $siswa['berat_badan']; ?>" required>
            </div>
        </div>
    </div>

    <!-- ====================================================
         BAGIAN 2: UPLOAD DOKUMEN REVISI
    ==================================================== -->
    <?php if ($jumlah_revisi > 0): ?>

    <div class="rev-panduan">
        <strong>📋 Dokumen yang perlu direvisi:</strong>
        <p style="font-size:12px;margin:6px 0 0;">
            Upload file baru untuk mengganti dokumen yang ditandai revisi.
            Dokumen yang tidak diupload ulang akan tetap seperti semula.
        </p>
    </div>

    <div class="rev-counter">
        <span><?php echo $jumlah_revisi; ?></span> dokumen perlu direvisi
    </div>

    <?php
    mysqli_data_seek($query_revisi, 0);
    while ($dok = mysqli_fetch_assoc($query_revisi)):
    ?>
    <div class="rev-dok-card">

        <div class="rev-dok-jenis">
            <span class="jenis-nama">
                <?php echo $label_map[$dok['jenis']] ?? ucfirst($dok['jenis']); ?>
            </span>
            <span class="rev-dok-badge revisi">⚠️ Revisi</span>
        </div>

        <?php if ($dok['catatan_admin']): ?>
        <div class="rev-catatan-admin">
            <div class="catatan-label">Catatan Admin</div>
            <p class="catatan-isi"><?php echo htmlspecialchars($dok['catatan_admin']); ?></p>
        </div>
        <?php endif; ?>

        <div class="rev-file-lama">
            <span class="file-icon">📄</span>
            <a href="../<?php echo htmlspecialchars($dok['file_path']); ?>" target="_blank">
                Lihat file sebelumnya
            </a>
        </div>

        <div class="rev-upload-wrap">
            <label>
                Upload file baru untuk
                <em><?php echo $label_map[$dok['jenis']] ?? ucfirst($dok['jenis']); ?></em>
            </label>
            <input type="file"
                   name="file_revisi_<?php echo $dok['jenis']; ?>"
                   accept=".jpg,.jpeg,.png,.pdf"
                   onchange="previewNamaFile(this,'<?php echo $dok['jenis']; ?>')">
            <div class="upload-hint">JPG, PNG, PDF · Maks 5MB · Boleh dikosongkan jika tidak ingin mengubah</div>
            <div id="preview_<?php echo $dok['jenis']; ?>"
                 style="display:none;font-size:13px;margin-top:6px;padding:8px;border-radius:8px;"></div>
        </div>
    </div>
    <?php endwhile; ?>

    <?php else: ?>
    <div style="background:#d1e7dd;border-radius:12px;padding:16px;text-align:center;margin-bottom:20px;">
        <div style="font-size:28px;margin-bottom:8px;">✅</div>
        <p style="font-size:14px;color:#0a3622;margin:0;font-weight:600;">
            Tidak ada dokumen yang perlu direvisi.
        </p>
        <p style="font-size:13px;color:#155724;margin:4px 0 0;">
            Anda dapat memperbarui biodata di atas jika diperlukan.
        </p>
    </div>
    <?php endif; ?>

    <!-- Tombol submit -->
    <button type="submit" name="submit_revisi" class="rev-btn-submit" id="btnSubmit">
        Kirim Perubahan
    </button>

    </form><!-- /formRevisi -->

    <?php else: ?>
    <!-- Kadaluarsa: tampilkan dokumen terkunci -->
    <?php if ($jumlah_revisi > 0):
        mysqli_data_seek($query_revisi, 0);
        while ($dok = mysqli_fetch_assoc($query_revisi)):
    ?>
    <div class="rev-dok-card" style="opacity:0.6;">
        <div class="rev-dok-jenis">
            <span class="jenis-nama"><?php echo $label_map[$dok['jenis']] ?? ucfirst($dok['jenis']); ?></span>
            <span class="rev-dok-badge revisi">🔒 Terkunci</span>
        </div>
        <?php if ($dok['catatan_admin']): ?>
        <div class="rev-catatan-admin">
            <div class="catatan-label">Catatan Admin</div>
            <p class="catatan-isi"><?php echo htmlspecialchars($dok['catatan_admin']); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endwhile; endif; ?>
    <?php endif; ?>

    <a href="dashboard_peserta.php" class="rev-btn-back" style="margin-top:16px;">
        Kembali ke Dashboard
    </a>

    <div style="height:40px;"></div>

</div>

</div>

        </div> <!-- End content-body -->
    </main>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="../js/dashboard.js"></script>
<script>
function previewNamaFile(input, jenis) {
    var el = document.getElementById('preview_' + jenis);
    if (input.files && input.files[0]) {
        var f = input.files[0];
        if (f.size > 5 * 1024 * 1024) {
            el.style.background = '#f8d7da';
            el.style.color      = '#842029';
            el.innerHTML        = '❌ File terlalu besar. Maks 5MB.';
            el.style.display    = 'block';
            input.value         = '';
            return;
        }
        el.style.background = '#d1e7dd';
        el.style.color      = '#0a3622';
        el.innerHTML        = '✅ ' + f.name + ' (' + (f.size/1024).toFixed(0) + ' KB)';
        el.style.display    = 'block';
    }
}

document.getElementById('formRevisi')?.addEventListener('submit', function() {
    var btn = document.getElementById('btnSubmit');
    btn.disabled    = true;
    btn.textContent = 'Menyimpan... mohon tunggu';
});
</script>
</body>
</html>
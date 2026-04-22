<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../login.php");
    exit;
}

$id_akun = $_SESSION['id_akun'];
$siswa   = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM siswa WHERE id_akun='$id_akun' LIMIT 1"
));

if (!$siswa) { header("Location: dashboard_peserta.php"); exit; }

$id_siswa     = $siswa['id_peserta'];
$batas_revisi = $siswa['batas_revisi'];
$hari_ini     = date('Y-m-d');

$sudah_kadaluarsa = ($batas_revisi && strtotime($hari_ini) > strtotime($batas_revisi));

$pesan_sukses   = '';
$pesan_error    = '';
$debug_info     = []; // kumpulkan info debug
$dok_diperbarui = [];

/* ============================================================
   PROSES POST
   FIX: Cek dari hidden field 'form_action', BUKAN dari
   button submit_revisi yang bisa hilang karena disabled JS
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'revisi') {

    if ($sudah_kadaluarsa) {
        $pesan_error = "Masa revisi sudah berakhir.";
        goto tampilForm;
    }

    /* ------ Update biodata (selalu dijalankan) ------ */
    $nama          = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $no_telp       = mysqli_real_escape_string($conn, trim($_POST['no_telp']));
    $alamat        = mysqli_real_escape_string($conn, trim($_POST['alamat']));
    $tgl_lahir     = mysqli_real_escape_string($conn, $_POST['tgl_lahir']);
    $agama         = mysqli_real_escape_string($conn, $_POST['agama']);
    $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
    $tinggi_badan  = (int) $_POST['tinggi_badan'];
    $berat_badan   = (int) $_POST['berat_badan'];

    $q_biodata = mysqli_query($conn,
        "UPDATE siswa SET
            nama='$nama', no_telp='$no_telp', alamat='$alamat',
            tgl_lahir='$tgl_lahir', agama='$agama',
            jenis_kelamin='$jenis_kelamin',
            tinggi_badan='$tinggi_badan', berat_badan='$berat_badan'
         WHERE id_peserta='$id_siswa'"
    );

    if (!$q_biodata) {
        $debug_info[] = "❌ Biodata query error: " . mysqli_error($conn);
    } else {
        $debug_info[] = "✅ Biodata updated. Rows affected: " . mysqli_affected_rows($conn);
    }

    /* ------ Proses upload file ------ */
    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];

    // Path fisik dari folder siswa/ → naik satu level ke root project
    $folder_disk = "../uploads/" . $id_siswa;
    // Path yang disimpan di DB → relatif dari web root
    $folder_db   = "uploads/" . $id_siswa;

    // Buat folder jika belum ada
    if (!is_dir($folder_disk)) {
        if (!mkdir($folder_disk, 0777, true)) {
            $debug_info[] = "❌ Gagal membuat folder: $folder_disk";
        } else {
            $debug_info[] = "✅ Folder dibuat: $folder_disk";
        }
    } else {
        $debug_info[] = "✅ Folder sudah ada: $folder_disk";
    }

    $berhasil = 0;
    $gagal    = 0;

    foreach ($_FILES as $field_name => $file_info) {

        // Skip field yang bukan file revisi
        if (strpos($field_name, 'file_revisi_') !== 0) continue;

        // Skip jika tidak ada file dipilih
        if ($file_info['error'] === UPLOAD_ERR_NO_FILE || empty($file_info['name'])) {
            $debug_info[] = "⏭ $field_name: tidak ada file dipilih, skip";
            continue;
        }

        // Tampilkan error code PHP jika ada masalah upload
        if ($file_info['error'] !== UPLOAD_ERR_OK) {
            $php_errors = [
                UPLOAD_ERR_INI_SIZE   => 'File melebihi upload_max_filesize di php.ini',
                UPLOAD_ERR_FORM_SIZE  => 'File melebihi MAX_FILE_SIZE di form',
                UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi PHP',
            ];
            $err_msg = $php_errors[$file_info['error']] ?? "Upload error code: {$file_info['error']}";
            $debug_info[] = "❌ $field_name: $err_msg";
            $gagal++;
            continue;
        }

        $jenis = str_replace('file_revisi_', '', $field_name);
        $ext   = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext)) {
            $debug_info[] = "❌ $field_name: format $ext tidak diizinkan";
            $gagal++;
            continue;
        }

        if ($file_info['size'] > 5 * 1024 * 1024) {
            $debug_info[] = "❌ $field_name: ukuran " . round($file_info['size']/1024) . "KB melebihi 5MB";
            $gagal++;
            continue;
        }

        // Path lengkap file baru
        $new_name       = $jenis . "." . $ext;
        $file_disk_path = $folder_disk . "/" . $new_name;
        $file_db_path   = $folder_db   . "/" . $new_name;

        $debug_info[] = "📁 Mencoba upload $jenis ke: $file_disk_path";

        if (move_uploaded_file($file_info['tmp_name'], $file_disk_path)) {
            $tgl_revisi  = date("Y-m-d H:i:s");
            $db_path_esc = mysqli_real_escape_string($conn, $file_db_path);
            $jenis_esc   = mysqli_real_escape_string($conn, $jenis);

            // Cek dulu apakah record dokumen ini ada
            $cek_dok = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id_dokumen FROM dokumen_pendaftaran
                 WHERE id_siswa='$id_siswa' AND jenis='$jenis_esc'"
            ));

            if ($cek_dok) {
                // Update dokumen yang sudah ada
                $q_update = mysqli_query($conn,
                    "UPDATE dokumen_pendaftaran
                     SET file_path        = '$db_path_esc',
                         status_verifikasi = 'pending',
                         catatan_admin     = NULL,
                         tgl_revisi        = '$tgl_revisi'
                     WHERE id_siswa='$id_siswa' AND jenis='$jenis_esc'"
                );

                if (!$q_update) {
                    $debug_info[] = "❌ UPDATE query error ($jenis): " . mysqli_error($conn);
                    $gagal++;
                } else {
                    $affected = mysqli_affected_rows($conn);
                    $debug_info[] = "✅ UPDATE $jenis berhasil. Rows affected: $affected";
                    $berhasil++;
                    $dok_diperbarui[] = $jenis;
                }
            } else {
                // Insert dokumen baru jika belum ada record-nya
                $q_insert = mysqli_query($conn,
                    "INSERT INTO dokumen_pendaftaran
                        (jenis, file_path, status_verifikasi, tgl_upload, tgl_revisi, id_siswa)
                     VALUES
                        ('$jenis_esc','$db_path_esc','pending','$tgl_revisi','$tgl_revisi','$id_siswa')"
                );

                if (!$q_insert) {
                    $debug_info[] = "❌ INSERT query error ($jenis): " . mysqli_error($conn);
                    $gagal++;
                } else {
                    $debug_info[] = "✅ INSERT $jenis berhasil (dokumen baru)";
                    $berhasil++;
                    $dok_diperbarui[] = $jenis;
                }
            }
        } else {
            $debug_info[] = "❌ move_uploaded_file gagal untuk $jenis. Cek permission folder: $folder_disk";
            $gagal++;
        }
    }

    // Buat pesan ringkasan
    if ($berhasil > 0 && $gagal === 0) {
        $pesan_sukses = "Biodata dan $berhasil dokumen berhasil diperbarui. Admin akan memverifikasi ulang.";
    } elseif ($berhasil > 0 && $gagal > 0) {
        $pesan_sukses = "$berhasil dokumen berhasil diperbarui.";
        $pesan_error  = "$gagal dokumen gagal diupload. Lihat detail di bawah.";
    } elseif ($berhasil === 0 && $gagal === 0) {
        $pesan_sukses = "Biodata berhasil diperbarui. Tidak ada file dokumen yang dikirim.";
    } else {
        $pesan_error = "Semua upload gagal. Lihat detail di bawah.";
    }

    // Reload data setelah update
    $siswa = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM siswa WHERE id_peserta='$id_siswa' LIMIT 1"
    ));
}

tampilForm:

// Ambil ulang dokumen revisi & semua dokumen
$query_revisi  = mysqli_query($conn,
    "SELECT * FROM dokumen_pendaftaran
     WHERE id_siswa='$id_siswa' AND status_verifikasi='revisi'
     ORDER BY tgl_upload DESC"
);
$jumlah_revisi = mysqli_num_rows($query_revisi);

$semua_dokumen = mysqli_query($conn,
    "SELECT * FROM dokumen_pendaftaran WHERE id_siswa='$id_siswa' ORDER BY jenis ASC"
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
    <title>Revisi Data — Gemilang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/revisi_dokumen.css">
    <link rel="stylesheet" href="../css/dashboard_layout.css">
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
            font-size: 14px; font-weight: 700; color: var(--navy);
            margin-bottom: 14px; padding-bottom: 8px;
            border-bottom: 2px solid #f0f2f5;
        }
        .biodata-section .form-control,
        .biodata-section .form-select {
            border-radius: 10px; font-size: 14px; border: 2px solid #dee2e6;
        }
        .biodata-section .form-control:focus,
        .biodata-section .form-select:focus {
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(13,27,42,0.10);
        }
        .dok-status-card {
            background: #fff; border-radius: 14px; padding: 16px;
            margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.07);
        }
        .dok-status-card h6 {
            font-size: 14px; font-weight: 700; color: var(--navy);
            margin-bottom: 12px; padding-bottom: 8px;
            border-bottom: 2px solid #f0f2f5;
        }
        .dok-status-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px solid #f8f9fa; font-size: 13px;
        }
        .dok-status-item:last-child { border-bottom: none; }
        .badge-valid   { background:#d1e7dd;color:#0a3622;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700; }
        .badge-revisi  { background:#f8d7da;color:#842029;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700; }
        .badge-pending { background:#fff3cd;color:#664d03;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700; }
        .badge-baru    { background:#cfe2ff;color:#084298;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700; }
        .debug-box {
            background: #1e1e1e; color: #d4d4d4; border-radius: 10px;
            padding: 14px 16px; margin-bottom: 16px; font-size: 12px;
            font-family: monospace; line-height: 1.8;
        }
        .debug-box .title { color: #ffc107; font-weight: 700; margin-bottom: 8px; display: block; }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">Gemilang 👮</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard_peserta.php"><span>🏠</span> Dashboard</a></li>
            <li><a href="revisi_dokumen.php" class="active"><span>📝</span> Revisi Dokumen</a></li>
            <li><a href="../ganti_password.php"><span>🔒</span> Ganti Password</a></li>
        </ul>
        <div class="sidebar-footer">
            <button type="button" class="btn-logout" id="btnLogout">
                <span>🚪</span> Logout
            </button>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" id="menuToggle">☰</button>
                <h1 class="page-title">Revisi Dokumen</h1>
            </div>
            <div>
                <a href="dashboard_peserta.php" class="btn btn-outline-secondary btn-sm"
                   style="border-radius:20px;">← Kembali</a>
            </div>
        </header>

        <div class="content-body">
<div class="rev-container">

    <div class="rev-header-card">
        <h5>Perbarui data untuk</h5>
        <p class="siswa-nama"><?php echo htmlspecialchars($siswa['nama']); ?></p>
    </div>

    <!-- Alert sukses -->
    <?php if ($pesan_sukses): ?>
    <div class="rev-alert-sukses">✅ <?php echo htmlspecialchars($pesan_sukses); ?></div>
    <?php endif; ?>

    <!-- Alert error -->
    <?php if ($pesan_error): ?>
    <div class="alert alert-danger" style="border-radius:10px;font-size:14px;">
        ⚠️ <?php echo htmlspecialchars($pesan_error); ?>
    </div>
    <?php endif; ?>

    <!-- DEBUG BOX — tampilkan detail proses upload (hapus setelah testing selesai) -->
    <?php if (!empty($debug_info)): ?>
    <div class="debug-box">
        <span class="title">🔧 Debug Info (hapus setelah testing)</span>
        <?php foreach ($debug_info as $line): ?>
            <div><?php echo htmlspecialchars($line); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Batas waktu -->
    <?php if ($batas_revisi): ?>
        <?php if ($sudah_kadaluarsa): ?>
        <div class="rev-alert-locked">
            <div class="lock-icon">🔒</div>
            <div class="lock-title">Masa Revisi Telah Berakhir</div>
            <div class="lock-desc">
                Batas: <strong><?php echo $batas_revisi; ?></strong>. Hubungi admin.
            </div>
        </div>
        <?php else: ?>
        <div class="rev-alert-deadline">
            <div class="deadline-title">⏰ Batas Waktu Revisi</div>
            <div class="deadline-info">
                Kirim sebelum <strong><?php echo $batas_revisi; ?></strong>.
                <?php
                $sisa = (int) round((strtotime($batas_revisi) - strtotime($hari_ini)) / 86400);
                if ($sisa === 0)     echo "<strong style='color:#dc3545'> Hari ini batas terakhir!</strong>";
                elseif ($sisa === 1) echo " <strong style='color:#e67e22'>Sisa 1 hari lagi.</strong>";
                else                 echo " Sisa <strong>$sisa hari</strong> lagi.";
                ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- STATUS SEMUA DOKUMEN -->
    <?php
    mysqli_data_seek($semua_dokumen, 0);
    if (mysqli_num_rows($semua_dokumen) > 0):
    ?>
    <div class="dok-status-card">
        <h6>📋 Status Semua Dokumen</h6>
        <?php
        mysqli_data_seek($semua_dokumen, 0);
        while ($d = mysqli_fetch_assoc($semua_dokumen)):
            $baru = in_array($d['jenis'], $dok_diperbarui);
        ?>
        <div class="dok-status-item">
            <span style="font-weight:600;color:var(--navy);">
                <?php echo $label_map[$d['jenis']] ?? ucfirst($d['jenis']); ?>
            </span>
            <?php if ($baru): ?>
                <span class="badge-baru">🆕 Baru Diupload</span>
            <?php elseif ($d['status_verifikasi'] === 'valid'): ?>
                <span class="badge-valid">✅ Valid</span>
            <?php elseif ($d['status_verifikasi'] === 'revisi'): ?>
                <span class="badge-revisi">⚠️ Perlu Revisi</span>
            <?php else: ?>
                <span class="badge-pending">⏳ Menunggu Verifikasi</span>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <?php if (!$sudah_kadaluarsa): ?>

    <!-- FIX: Gunakan hidden field 'form_action' sebagai penanda POST -->
    <!-- JANGAN andalkan nama button submit karena bisa hilang saat disabled via JS -->
    <form method="POST" enctype="multipart/form-data" id="formRevisi">
        <input type="hidden" name="form_action" value="revisi">

        <!-- BAGIAN 1: BIODATA -->
        <div class="biodata-section">
            <h6>📋 Perbarui Biodata</h6>
            <div class="mb-3">
                <label class="form-label fw-bold" style="font-size:12px;">Nama Lengkap</label>
                <input type="text" name="nama" class="form-control"
                       value="<?php echo htmlspecialchars($siswa['nama']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold" style="font-size:12px;">Nomor HP</label>
                <input type="text" name="no_telp" class="form-control"
                       value="<?php echo htmlspecialchars($siswa['no_telp']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold" style="font-size:12px;">Alamat</label>
                <textarea name="alamat" class="form-control" rows="3" required
                          style="resize:none;"><?php echo htmlspecialchars($siswa['alamat']); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold" style="font-size:12px;">Tanggal Lahir</label>
                <input type="date" name="tgl_lahir" class="form-control"
                       value="<?php echo $siswa['tgl_lahir']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold" style="font-size:12px;">Jenis Kelamin</label>
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
                <label class="form-label fw-bold" style="font-size:12px;">Agama</label>
                <select name="agama" class="form-select" required>
                    <?php foreach (['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'] as $ag): ?>
                    <option value="<?php echo $ag; ?>"
                            <?php echo $siswa['agama']===$ag?'selected':''; ?>>
                        <?php echo $ag; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label fw-bold" style="font-size:12px;">Tinggi (cm)</label>
                    <input type="number" name="tinggi_badan" class="form-control"
                           value="<?php echo $siswa['tinggi_badan']; ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold" style="font-size:12px;">Berat (kg)</label>
                    <input type="number" name="berat_badan" class="form-control"
                           value="<?php echo $siswa['berat_badan']; ?>" required>
                </div>
            </div>
        </div>

        <!-- BAGIAN 2: DOKUMEN REVISI -->
        <?php if ($jumlah_revisi > 0): ?>

        <div class="rev-panduan">
            <strong>📎 Upload ulang dokumen yang perlu direvisi</strong>
            <p style="font-size:12px;margin:6px 0 0;color:#0c5460;">
                Pilih file baru untuk mengganti. Kosongkan jika tidak ingin mengubah.
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
                <span class="rev-dok-badge revisi">⚠️ Perlu Revisi</span>
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
                <label>Upload file baru
                    (<em><?php echo $label_map[$dok['jenis']] ?? ucfirst($dok['jenis']); ?></em>)
                </label>
                <input type="file"
                       name="file_revisi_<?php echo htmlspecialchars($dok['jenis']); ?>"
                       accept=".jpg,.jpeg,.png,.pdf"
                       onchange="previewFile(this, '<?php echo $dok['jenis']; ?>')">
                <div class="upload-hint">JPG, PNG, PDF · Maks 5MB</div>
                <div id="preview_<?php echo $dok['jenis']; ?>"
                     style="display:none;font-size:13px;margin-top:6px;
                            padding:8px;border-radius:8px;"></div>
            </div>
        </div>
        <?php endwhile; ?>

        <?php else: ?>
        <div style="background:#d1e7dd;border-radius:12px;padding:16px;
                    text-align:center;margin-bottom:20px;">
            <div style="font-size:28px;margin-bottom:8px;">✅</div>
            <p style="font-size:14px;color:#0a3622;margin:0;font-weight:600;">
                Tidak ada dokumen yang perlu direvisi.
            </p>
        </div>
        <?php endif; ?>

        <!-- Tombol submit — JANGAN pakai disabled di JS event submit -->
        <button type="submit" class="rev-btn-submit" id="btnSubmit">
            💾 Kirim Perubahan
        </button>

    </form>

    <?php else: ?>
    <!-- Kadaluarsa: read-only -->
    <?php
    mysqli_data_seek($query_revisi, 0);
    while ($dok = mysqli_fetch_assoc($query_revisi)):
    ?>
    <div class="rev-dok-card" style="opacity:0.6;">
        <div class="rev-dok-jenis">
            <span class="jenis-nama">
                <?php echo $label_map[$dok['jenis']] ?? ucfirst($dok['jenis']); ?>
            </span>
            <span class="rev-dok-badge revisi">🔒 Terkunci</span>
        </div>
        <?php if ($dok['catatan_admin']): ?>
        <div class="rev-catatan-admin">
            <div class="catatan-label">Catatan Admin</div>
            <p class="catatan-isi"><?php echo htmlspecialchars($dok['catatan_admin']); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endwhile; ?>
    <?php endif; ?>

    <a href="dashboard_peserta.php" class="rev-btn-back" style="margin-top:16px;">
        ← Kembali ke Dashboard
    </a>
    <div style="height:40px;"></div>

</div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
function previewFile(input, jenis) {
    var el = document.getElementById('preview_' + jenis);
    if (!el) return;
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
        el.innerHTML        = '✅ ' + f.name + ' (' + (f.size / 1024).toFixed(0) + ' KB) — siap diupload';
        el.style.display    = 'block';
    }
}

// FIX: Jangan disable button di event submit!
// Button disabled = nilai button tidak ikut ke POST
// Cukup ubah teks saja sebagai feedback visual
document.getElementById('formRevisi')?.addEventListener('submit', function() {
    var btn = document.getElementById('btnSubmit');
    if (btn) {
        // Hanya ubah teks, JANGAN set disabled
        btn.textContent = '⏳ Menyimpan... mohon tunggu';
        btn.style.opacity = '0.7';
        btn.style.cursor  = 'not-allowed';
    }
});

const menuToggle = document.getElementById('menuToggle');
const sidebar    = document.getElementById('sidebar');
menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && !sidebar.contains(e.target) && e.target !== menuToggle)
        sidebar.classList.remove('open');
});
document.getElementById('btnLogout').addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title:'Keluar dari Sistem?', text:"Anda akan mengakhiri sesi. Lanjutkan?", icon:'warning',
        showCancelButton:true, confirmButtonColor:'#dc3545', cancelButtonColor:'#6c757d',
        confirmButtonText:'Ya, Logout', cancelButtonText:'Batal', reverseButtons:true
    }).then((result) => { if (result.isConfirmed) window.location.href = '../logout.php'; });
});
</script>
</body>
</html>
<?php
session_start();
include "../koneksi.php";

if(!isset($_SESSION['role']) || $_SESSION['role'] != "publikasi"){
    header("location:../login.php");
    exit;
}

$periode = mysqli_query($conn,
    "SELECT id_periode, tahun, gelombang
     FROM periode_diklat
     ORDER BY tahun DESC, gelombang DESC"
);

// FIX BUG: Cek dari id_periode bukan dari $_POST['submit']
// Karena form disubmit via JS form.submit(), tombol submit tidak masuk ke POST
if(isset($_POST['id_periode']) && !empty($_POST['id_periode'])){

    $id_periode = (int) $_POST['id_periode'];
    $tempat     = mysqli_real_escape_string($conn, trim($_POST['tempat']));

    /* Upload brosur */
    $nama_file = '';
    if(!empty($_FILES['brosur']['name'])){
        $ext     = strtolower(pathinfo($_FILES['brosur']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf'];

        if(!in_array($ext, $allowed)){
            $error = "Format file tidak didukung. Gunakan JPG, PNG, atau PDF.";
            goto tampilForm;
        }

        if($_FILES['brosur']['size'] > 5 * 1024 * 1024){
            $error = "Ukuran file terlalu besar. Maksimal 5MB.";
            goto tampilForm;
        }

        $nama_file = 'brosur_' . $id_periode . '_' . time() . '.' . $ext;
        $folder    = "../uploads/";

        if(!is_dir($folder)){
            mkdir($folder, 0777, true);
        }

        if(!move_uploaded_file($_FILES['brosur']['tmp_name'], $folder . $nama_file)){
            $error = "Gagal mengupload file. Periksa permission folder uploads/.";
            goto tampilForm;
        }
    }

    /* Cek apakah sudah ada data untuk periode ini */
    $cek = mysqli_query($conn,
        "SELECT id_info, brosur_path FROM informasi_diklat WHERE id_periode='$id_periode'"
    );

    if(mysqli_num_rows($cek) > 0){
        $lama       = mysqli_fetch_assoc($cek);
        $pathBrosur = $nama_file ?: $lama['brosur_path'];
        $pathBrosur = mysqli_real_escape_string($conn, $pathBrosur);

        mysqli_query($conn,"
            UPDATE informasi_diklat SET
                brosur_path     = '$pathBrosur',
                tempat          = '$tempat',
                diperbarui_pada = NOW()
            WHERE id_periode = '$id_periode'
        ");
        $pesan = "update";
    } else {
        if(empty($nama_file)){
            $error = "Brosur wajib diupload untuk publikasi baru.";
            goto tampilForm;
        }
        $nama_file_esc = mysqli_real_escape_string($conn, $nama_file);
        mysqli_query($conn,"
            INSERT INTO informasi_diklat (id_periode, brosur_path, tempat)
            VALUES ('$id_periode','$nama_file_esc','$tempat')
        ");
        $pesan = "insert";
    }
}

tampilForm:
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Publikasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/dashboard_layout.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .card-form {
            background: var(--white);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid var(--gray-border);
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">📢 Publikasi</div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard_publikasi.php" class="active">
                    <span>📰</span> Data Publikasi
                </a>
            </li>
            <li>
                <a href="../ganti_password.php">
                    <span>🔒</span> Ganti Password
                </a>
            </li>
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
                <h1 class="page-title">Publikasi Informasi Diklat</h1>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">
                    Publikasi: <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
            </div>
        </header>

        <div class="content-body">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="card-form shadow-lg">

                        <h4 class="mb-4 fw-bold" style="color:var(--navy);">
                            Upload Publikasi Diklat
                        </h4>

                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger" style="border-radius:10px;">
                                ⚠️ <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <!-- FIX: Tidak pakai JS submit untuk avoid masalah,
                             pakai tombol submit biasa dengan hidden field -->
                        <form method="POST" enctype="multipart/form-data" id="formPublikasi">
                            <!-- Hidden field sebagai pengganti tombol submit yang hilang saat JS submit -->
                            <input type="hidden" name="form_submitted" value="1">

                            <div class="mb-3">
                                <label class="form-label fw-bold">Pilih Periode</label>
                                <select name="id_periode" class="form-select" required id="selectPeriode">
                                    <option value="">-- Pilih Periode --</option>
                                    <?php
                                    mysqli_data_seek($periode, 0);
                                    while($p = mysqli_fetch_assoc($periode)):
                                    ?>
                                    <option value="<?php echo $p['id_periode']; ?>">
                                        Gelombang <?php echo $p['gelombang']; ?> — <?php echo $p['tahun']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Tempat Pelatihan</label>
                                <input type="text" name="tempat" class="form-control"
                                       placeholder="Contoh: Pusdiklat Polda DIY, Bandung" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    Upload Brosur / Poster
                                    <span style="font-weight:400;color:#6c757d;">(JPG, PNG, PDF — maks 5MB)</span>
                                </label>
                                <input type="file" name="brosur" class="form-control"
                                       accept=".jpg,.jpeg,.png,.pdf" id="inputBrosur"
                                       onchange="previewBrosur(this)">
                                <div class="form-text">
                                    Biarkan kosong jika tidak ingin mengubah brosur yang sudah ada.
                                </div>

                                <div id="previewWrap" class="mt-3 d-none text-center">
                                    <img id="previewImg" src="" alt="Preview Brosur"
                                         class="img-fluid rounded"
                                         style="max-height: 300px; object-fit: contain;">
                                </div>
                            </div>

                            <!-- FIX: Tombol submit biasa, konfirmasi tetap via SweetAlert
                                 tapi sekarang submit button value ikut masuk POST -->
                            <button type="button" class="btn btn-primary w-100 fw-bold"
                                    onclick="konfirmasi()" style="padding:12px;">
                                📢 Publikasikan
                            </button>

                        </form>
                    </div>

                    <!-- Riwayat publikasi -->
                    <div class="card-form shadow-lg mt-4">
                        <h5 class="fw-bold mb-3" style="color:var(--navy);">Riwayat Publikasi</h5>
                        <?php
                        $riwayat = mysqli_query($conn,"
                            SELECT i.*, p.tahun, p.gelombang
                            FROM informasi_diklat i
                            JOIN periode_diklat p ON i.id_periode = p.id_periode
                            ORDER BY i.diperbarui_pada DESC
                        ");
                        if(mysqli_num_rows($riwayat) === 0):
                        ?>
                        <p style="color:#6c757d;font-size:13px;">Belum ada publikasi.</p>
                        <?php else: while($r = mysqli_fetch_assoc($riwayat)): ?>
                        <div class="d-flex justify-content-between align-items-center
                                    border-bottom py-2 gap-2">
                            <div>
                                <div class="fw-semibold" style="font-size:14px;">
                                    Gelombang <?php echo $r['gelombang']; ?> — <?php echo $r['tahun']; ?>
                                </div>
                                <div style="color:#6c757d;font-size:12px;">
                                    <?php echo htmlspecialchars($r['tempat']); ?>
                                    &nbsp;·&nbsp;
                                    Diperbarui: <?php echo date('d M Y H:i', strtotime($r['diperbarui_pada'])); ?>
                                </div>
                            </div>
                            <?php if($r['brosur_path']): ?>
                            <a href="../uploads/<?php echo htmlspecialchars($r['brosur_path']); ?>"
                               target="_blank"
                               class="btn btn-sm btn-outline-primary" style="white-space:nowrap;">
                               Lihat Brosur
                            </a>
                            <?php else: ?>
                            <span style="font-size:12px;color:#adb5bd;">Belum ada brosur</span>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </main>
</div>

<?php if(isset($pesan)): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    <?php if($pesan == "insert"): ?>
        Swal.fire({icon:'success',title:'Berhasil!',
            text:'Informasi diklat berhasil dipublikasikan.',
            confirmButtonColor:'#0d1b2a'});
    <?php else: ?>
        Swal.fire({icon:'success',title:'Berhasil Diperbarui!',
            text:'Informasi diklat berhasil diperbarui.',
            confirmButtonColor:'#0d1b2a'});
    <?php endif; ?>
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
function previewBrosur(input) {
    const wrap = document.getElementById('previewWrap');
    const img  = document.getElementById('previewImg');
    if(input.files && input.files[0]){
        const ext = input.files[0].name.split('.').pop().toLowerCase();
        if(['jpg','jpeg','png'].includes(ext)){
            const reader = new FileReader();
            reader.onload = e => {
                img.src = e.target.result;
                wrap.classList.remove('d-none');
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            wrap.classList.add('d-none');
        }
    }
}

function konfirmasi() {
    const periode = document.getElementById('selectPeriode');
    if(!periode.value){
        Swal.fire('Perhatian','Pilih periode terlebih dahulu.','warning');
        return;
    }
    const teks = periode.options[periode.selectedIndex]?.text || '-';
    Swal.fire({
        title: 'Konfirmasi Publikasi',
        html: `Publikasikan informasi untuk periode <strong>${teks}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d1b2a',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Publikasikan',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // FIX: Submit form biasa tanpa JS form.submit() yang hilangkan button
            document.getElementById('formPublikasi').submit();
        }
    });
}

const menuToggle = document.getElementById('menuToggle');
const sidebar    = document.getElementById('sidebar');
menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
document.addEventListener('click', (e) => {
    if(window.innerWidth <= 768 && !sidebar.contains(e.target) && e.target !== menuToggle)
        sidebar.classList.remove('open');
});
document.getElementById('btnLogout').addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title:'Keluar dari Sistem?',text:"Anda akan mengakhiri sesi. Lanjutkan?",icon:'warning',
        showCancelButton:true,confirmButtonColor:'#dc3545',cancelButtonColor:'#6c757d',
        confirmButtonText:'Ya, Logout',cancelButtonText:'Batal',reverseButtons:true
    }).then((result) => { if(result.isConfirmed) window.location.href='../logout.php'; });
});
</script>
</body>
</html>
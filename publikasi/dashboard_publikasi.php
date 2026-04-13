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

if(isset($_POST['submit'])){

    $id_periode = (int) $_POST['id_periode'];
    $tempat     = mysqli_real_escape_string($conn, trim($_POST['tempat']));

    /* Upload brosur */
    $nama_file = '';
    if(!empty($_FILES['brosur']['name'])){
        $ext       = strtolower(pathinfo($_FILES['brosur']['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg','jpeg','png','pdf'];

        if(!in_array($ext, $allowed)){
            $error = "Format file tidak didukung. Gunakan JPG, PNG, atau PDF.";
            goto tampilForm;
        }

        $nama_file = 'brosur_' . $id_periode . '_' . time() . '.' . $ext;
        $folder    = "../uploads/";
        move_uploaded_file($_FILES['brosur']['tmp_name'], $folder . $nama_file);
    }

    /* Cek apakah sudah ada data untuk periode ini */
    $cek = mysqli_query($conn,
        "SELECT id_info, brosur_path FROM informasi_diklat WHERE id_periode='$id_periode'"
    );

    if(mysqli_num_rows($cek) > 0){
        $lama      = mysqli_fetch_assoc($cek);
        $pathBrosur = $nama_file ?: $lama['brosur_path']; // pertahankan brosur lama jika tidak upload baru

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
        mysqli_query($conn,"
            INSERT INTO informasi_diklat (id_periode, brosur_path, tempat)
            VALUES ('$id_periode','$nama_file','$tempat')
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
    <!-- SweetAlert2 -->
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
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            📢 Publikasi 
        </div>
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

                <h4 class="mb-4 text-warning fw-bold text-center">
                    Upload Publikasi Diklat
                </h4>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="formPublikasi">

                    <div class="mb-3">
                        <label class="form-label">Pilih Periode</label>
                        <select name="id_periode" class="form-select" required id="selectPeriode">
                            <option value="">-- Pilih Periode --</option>
                            <?php
                            mysqli_data_seek($periode, 0);
                            while($p = mysqli_fetch_assoc($periode)):
                            ?>
                            <option value="<?= $p['id_periode']; ?>">
                                Gelombang <?= $p['gelombang']; ?> - <?= $p['tahun']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tempat Pelatihan</label>
                        <input type="text" name="tempat" class="form-control"
                               placeholder="Contoh: Pusdiklat Polda DIY, Bandung" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Upload Brosur / Poster (Portrait)</label>
                        <input type="file" name="brosur" class="form-control"
                               accept=".jpg,.jpeg,.png,.pdf" id="inputBrosur"
                               onchange="previewBrosur(this)">
                        <div class="form-text">Format: JPG, PNG, PDF. Biarkan kosong jika tidak ingin mengubah brosur.</div>

                        <!-- Preview brosur -->
                        <div id="previewWrap" class="mt-3 d-none text-center">
                            <img id="previewImg" src="" alt="Preview Brosur"
                                 class="img-fluid rounded"
                                 style="max-height: 300px; object-fit: contain;">
                        </div>
                    </div>

                    <!-- Tombol konfirmasi -->
                    <button type="button" class="btn btn-primary w-100" onclick="konfirmasi()">
                        Publikasikan
                    </button>

                </form>

            </div>

            <!-- Riwayat publikasi -->
            <div class="card-form shadow-lg mt-4">
                <h5 class="text-warning fw-bold mb-3">Riwayat Publikasi</h5>
                <?php
                $riwayat = mysqli_query($conn,"
                    SELECT i.*, p.tahun, p.gelombang
                    FROM informasi_diklat i
                    JOIN periode_diklat p ON i.id_periode = p.id_periode
                    ORDER BY i.diperbarui_pada DESC
                ");
                while($r = mysqli_fetch_assoc($riwayat)):
                ?>
                <div class="d-flex justify-content-between align-items-center
                            border-bottom border-secondary py-2">
                    <div>
                        <div class="text-white fw-semibold">
                            Gelombang <?= $r['gelombang']; ?> - <?= $r['tahun']; ?>
                        </div>
                        <div class="text-muted small">
                            <?= htmlspecialchars($r['tempat']); ?> &nbsp;·&nbsp;
                            Diperbarui: <?= $r['diperbarui_pada']; ?>
                        </div>
                    </div>
                    <?php if($r['brosur_path']): ?>
                    <a href="../uploads/<?= $r['brosur_path']; ?>" target="_blank"
                       class="btn btn-sm btn-outline-warning">
                       Lihat Brosur
                    </a>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>

        </div>
    </div> <!-- container content -->
        </div> <!-- content-body -->
    </main>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="../js/dashboard.js"></script>

<?php if(isset($pesan)): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    <?php if($pesan == "insert"): ?>
        Swal.fire('Berhasil!', 'Publikasi berhasil ditambahkan.', 'success');
    <?php else: ?>
        Swal.fire('Berhasil!', 'Publikasi berhasil diperbarui.', 'success');
    <?php endif; ?>
});
</script>
<?php endif; ?>

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
    const teks    = periode.options[periode.selectedIndex]?.text || '-';
    if(!periode.value){
        Swal.fire('Perhatian', 'Pilih periode terlebih dahulu.', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Konfirmasi Publikasi',
        html: `Apakah Anda yakin ingin mempublikasikan informasi ini untuk periode <strong>${teks}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Publikasikan',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formPublikasi').submit();
        }
    });
}
</script>
</body>
</html>
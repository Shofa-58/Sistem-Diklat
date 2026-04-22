<?php
/**
 * auto_update_status.php — versi diperbaiki
 *
 * Logika auto-update status siswa:
 *
 * SEBELUMNYA: trigger dari batas_verifikasi (deadline admin verifikasi)
 *             → membingungkan, batas_verifikasi = batas admin menerima dokumen
 *
 * SEKARANG: trigger dari tanggal_mulai periode diklat
 *           → lebih logis: ketika diklat sudah dimulai,
 *             siswa yang sudah terverifikasi otomatis menjadi 'peserta'
 *
 * Alur:
 * 1. Siswa daftar          → status 'calon'
 * 2. Dokumen valid semua   → status 'terverifikasi'  (dilakukan admin manual)
 * 3. tanggal_mulai tiba    → status 'peserta'        (AUTO oleh fungsi ini)
 * 4. Admin input nilai     → status 'lulus'/'tidak_lulus' (dilakukan admin manual)
 *
 * Cara pakai: include "auto_update_status.php"; di halaman yang relevan.
 */

function autoUpdateStatusPeserta($conn) {
    $today = date('Y-m-d');

    /* ============================================================
       STEP 1:
       Siswa 'terverifikasi' yang sudah masuk peserta_periode
       dan tanggal_mulai periodenya sudah tiba → update ke 'peserta'
       ============================================================ */
    $result = mysqli_query($conn,
        "SELECT DISTINCT pp.id_peserta
         FROM peserta_periode pp
         JOIN periode_diklat pd ON pp.id_periode = pd.id_periode
         JOIN siswa s ON pp.id_peserta = s.id_peserta
         WHERE s.status = 'terverifikasi'
         AND pd.tanggal_mulai <= '$today'"
    );

    while ($row = mysqli_fetch_assoc($result)) {
        $id = (int) $row['id_peserta'];
        mysqli_query($conn,
            "UPDATE siswa SET status='peserta'
             WHERE id_peserta='$id' AND status='terverifikasi'"
        );
    }

    /* ============================================================
       STEP 2:
       Siswa 'terverifikasi' yang BELUM di peserta_periode
       tapi periodenya sudah mulai → masukkan ke periode aktif
       dan update ke 'peserta'

       Ambil periode yang sedang berjalan atau sudah mulai
       (bukan yang masih pendaftaran dan tanggal_mulai belum tiba)
       ============================================================ */
    $periode_aktif = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_periode, tanggal_mulai FROM periode_diklat
         WHERE tanggal_mulai <= '$today'
         AND status IN ('berjalan', 'pendaftaran')
         ORDER BY tahun DESC, gelombang DESC
         LIMIT 1"
    ));

    if ($periode_aktif) {
        $id_p = (int) $periode_aktif['id_periode'];

        $result2 = mysqli_query($conn,
            "SELECT id_peserta FROM siswa
             WHERE status = 'terverifikasi'
             AND id_peserta NOT IN (
                 SELECT id_peserta FROM peserta_periode WHERE id_periode='$id_p'
             )"
        );

        while ($row = mysqli_fetch_assoc($result2)) {
            $id_s = (int) $row['id_peserta'];

            /* Masukkan ke peserta_periode */
            mysqli_query($conn,
                "INSERT IGNORE INTO peserta_periode (tanggal_terima, id_peserta, id_periode)
                 VALUES ('$today', '$id_s', '$id_p')"
            );

            /* Update status ke peserta */
            mysqli_query($conn,
                "UPDATE siswa SET status='peserta'
                 WHERE id_peserta='$id_s' AND status='terverifikasi'"
            );
        }
    }

    /* ============================================================
       STEP 3 (BONUS):
       Update status periode dari 'pendaftaran' ke 'berjalan'
       otomatis ketika tanggal_mulai sudah tiba
       — agar tampilan di dashboard konsisten
       ============================================================ */
    mysqli_query($conn,
        "UPDATE periode_diklat
         SET status = 'berjalan'
         WHERE status = 'pendaftaran'
         AND tanggal_mulai <= '$today'"
    );

    /* ============================================================
       STEP 4 (BONUS):
       Update status periode dari 'berjalan' ke 'selesai'
       otomatis ketika tanggal_selesai sudah terlewati
       ============================================================ */
    mysqli_query($conn,
        "UPDATE periode_diklat
         SET status = 'selesai'
         WHERE status = 'berjalan'
         AND tanggal_selesai < '$today'"
    );
    /* ============================================================
       STEP 5:
       Hapus otomatis siswa yang melewati tenggat revisi (batas_revisi)
       dan belum dinyatakan sebagai 'peserta'.
       ============================================================ */
    $expired_siswa = mysqli_query($conn,
        "SELECT id_peserta, id_akun FROM siswa 
         WHERE batas_revisi IS NOT NULL 
         AND batas_revisi < '$today'
         AND status NOT IN ('peserta', 'lulus', 'tidak_lulus')"
    );

    while ($exp = mysqli_fetch_assoc($expired_siswa)) {
        $id_s = (int) $exp['id_peserta'];
        $id_a = (int) $exp['id_akun'];

        // 1. Hapus dokumen & file fisik
        $q_dok = mysqli_query($conn, "SELECT file_path FROM dokumen_pendaftaran WHERE id_siswa='$id_s'");
        while ($dok = mysqli_fetch_assoc($q_dok)) {
            $path = $dok['file_path'];
            if ($path && file_exists($path)) @unlink($path);
            // Jika path tersimpan dengan ../ kita perlu bersihkan juga
            $clean_path = preg_replace('/^(\.\.\/)+/', '', $path);
            if (file_exists($clean_path)) @unlink($clean_path);
        }
        // Hapus folder siswa
        $folder = "uploads/" . $id_s;
        if (is_dir($folder)) {
            // Hapus isi folder jika ada sisa
            $files = glob($folder . '/*');
            foreach ($files as $file) { if (is_file($file)) @unlink($file); }
            @rmdir($folder);
        }

        // 2. Hapus dari tabel relasi
        mysqli_query($conn, "DELETE FROM dokumen_pendaftaran WHERE id_siswa='$id_s'");
        mysqli_query($conn, "DELETE FROM peserta_periode WHERE id_peserta='$id_s'");
        mysqli_query($conn, "DELETE FROM evaluasi WHERE id_siswa='$id_s'");
        mysqli_query($conn, "DELETE FROM notifikasi WHERE id_siswa='$id_s'");

        // 3. Hapus data utama
        mysqli_query($conn, "DELETE FROM siswa WHERE id_peserta='$id_s'");
        if ($id_a) mysqli_query($conn, "DELETE FROM akun WHERE id_akun='$id_a'");
        
        error_log("Auto-Delete: Siswa ID $id_s dihapus karena melewati batas revisi.");
    }
}


/* Jalankan langsung saat file di-include */
if (isset($conn)) {
    autoUpdateStatusPeserta($conn);
}
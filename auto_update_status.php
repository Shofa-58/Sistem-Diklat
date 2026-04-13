<?php
/**
 * auto_update_status.php
 * Dijalankan otomatis saat halaman admin/siswa dimuat.
 * Cek siswa berstatus 'terverifikasi' yang sudah melewati batas_verifikasi
 * pada periode_diklat → update ke 'peserta'.
 *
 * Cara pakai: include "auto_update_status.php"; di halaman yang relevan.
 */

function autoUpdateStatusPeserta($conn) {
    $today = date('Y-m-d');

    /*
     * Logic:
     * - Ambil semua periode yang batas_verifikasinya sudah lewat
     * - Siswa yang terverifikasi dan sudah masuk peserta_periode → update ke 'peserta'
     * - Siswa yang status='terverifikasi' TAPI belum di peserta_periode:
     *   → tambahkan ke peserta_periode periode yang sedang 'pendaftaran' / 'berjalan'
     *   → lalu update status ke 'peserta'
     */

    /* 1. Update siswa 'terverifikasi' yang periode-nya sudah melewati batas_verifikasi */
    $result = mysqli_query($conn,
        "SELECT DISTINCT pp.id_peserta
         FROM peserta_periode pp
         JOIN periode_diklat pd ON pp.id_periode = pd.id_periode
         JOIN siswa s ON pp.id_peserta = s.id_peserta
         WHERE s.status = 'terverifikasi'
         AND pd.batas_verifikasi IS NOT NULL
         AND pd.batas_verifikasi < '$today'"
    );

    while ($row = mysqli_fetch_assoc($result)) {
        $id = (int) $row['id_peserta'];
        mysqli_query($conn,
            "UPDATE siswa SET status='peserta' WHERE id_peserta='$id' AND status='terverifikasi'"
        );
    }

    /* 2. Siswa 'terverifikasi' yang BELUM di peserta_periode:
          masukkan ke periode aktif dan update status */
    $periode_aktif = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_periode, batas_verifikasi FROM periode_diklat
         WHERE status IN ('pendaftaran','berjalan')
         AND batas_verifikasi IS NOT NULL
         AND batas_verifikasi < '$today'
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

            /* Tambahkan ke peserta_periode */
            mysqli_query($conn,
                "INSERT IGNORE INTO peserta_periode (tanggal_terima, id_peserta, id_periode)
                 VALUES ('$today', '$id_s', '$id_p')"
            );

            /* Update status */
            mysqli_query($conn,
                "UPDATE siswa SET status='peserta'
                 WHERE id_peserta='$id_s' AND status='terverifikasi'"
            );
        }
    }
}

/* Jalankan langsung saat file di-include */
if (isset($conn)) {
    autoUpdateStatusPeserta($conn);
}
<?php
/**
 * helpers.php
 * Berisi fungsi-fungsi pendukung sistem:
 * - generatePassword()
 * - generateUsername()
 * - kirimEmailAkun()
 *
 * Cara pakai: include "helpers.php"; di file yang butuh fungsi ini.
 * Pastikan PHPMailer sudah diinstall via Composer:
 *   composer require phpmailer/phpmailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

/* ============================================================
   KONFIGURASI EMAIL - Ganti sesuai akun Gmail sistem
   Aktifkan "App Password" di akun Google (bukan password biasa)
   https://myaccount.google.com/apppasswords
   ============================================================ */
define('MAIL_FROM',     'shofaazmy27@gmail.com'); // email sistem
define('MAIL_NAME',     'Sistem Diklat Satpam Gemilang');
define('MAIL_PASSWORD', 'jypn ebfh dfne fzlb');             // Gmail App Password


/* ============================================================
   GENERATE PASSWORD
   Format: 9 karakter, kombinasi huruf besar, kecil, dan angka
   ============================================================ */
function generatePassword(int $panjang = 9): string {
    $hurufBesar = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // hapus I,O agar tidak ambigu
    $hurufKecil = 'abcdefghjkmnpqrstuvwxyz';  // hapus i,l,o agar tidak ambigu
    $angka      = '23456789';                  // hapus 0,1 agar tidak ambigu

    // Pastikan minimal ada 1 dari setiap jenis karakter
    $password  = $hurufBesar[random_int(0, strlen($hurufBesar) - 1)];
    $password .= $hurufKecil[random_int(0, strlen($hurufKecil) - 1)];
    $password .= $angka[random_int(0, strlen($angka) - 1)];

    // Isi sisa karakter secara acak dari gabungan semua
    $semua = $hurufBesar . $hurufKecil . $angka;
    for ($i = 3; $i < $panjang; $i++) {
        $password .= $semua[random_int(0, strlen($semua) - 1)];
    }

    // Acak urutan agar posisi tidak terprediksi
    return str_shuffle($password);
}


/* ============================================================
   GENERATE USERNAME
   Format: siswa{TAHUN}P{PERIODE}{URUTAN 3 digit}
   Contoh: siswa2026P1001, siswa2026P2013

   Periode dihitung dari bulan pendaftaran:
     Januari - Maret   → P1
     April   - Juni    → P2
     Juli    - September→ P3
     Oktober - Desember → P4
   ============================================================ */
function generateUsername($conn): string {
    $tahun  = date('Y');
    $bulan  = (int) date('n');

    // Tentukan periode dari bulan
    $periode = (int) ceil($bulan / 3); // 1-4

    // Prefix username
    $prefix = "siswa{$tahun}P{$periode}";

    // Hitung urutan: cari username terakhir dengan prefix yang sama
    $result = mysqli_query($conn,
        "SELECT username FROM akun
         WHERE username LIKE '{$prefix}%'
         ORDER BY id_akun DESC
         LIMIT 1"
    );

    $urutan = 1;
    if ($row = mysqli_fetch_assoc($result)) {
        // Ambil 3 digit terakhir sebagai urutan
        $urutan = (int) substr($row['username'], -3) + 1;
    }

    // Format urutan 3 digit: 001, 002, dst.
    return $prefix . str_pad($urutan, 3, '0', STR_PAD_LEFT);
}


/* ============================================================
   KIRIM EMAIL AKUN KE PESERTA
   Parameter:
     $emailTujuan  → email peserta
     $namaPeserta  → nama lengkap peserta
     $username     → username yang di-generate
     $password     → password plain yang di-generate
   ============================================================ */
function kirimEmailAkun(
    string $emailTujuan,
    string $namaPeserta,
    string $username,
    string $password
): bool {

    $mail = new PHPMailer(true);

    try {
        // Server SMTP Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_FROM;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Pengirim & penerima
        $mail->setFrom(MAIL_FROM, MAIL_NAME);
        $mail->addAddress($emailTujuan, $namaPeserta);
        $mail->CharSet = 'UTF-8';

        // Konten email
        $mail->isHTML(true);
        $mail->Subject = 'Informasi Akun - Sistem Diklat Satpam Gemilang';
        $mail->Body    = emailTemplate($namaPeserta, $username, $password);
        $mail->AltBody = "Halo $namaPeserta,\n\nAkun Anda telah dibuat.\nUsername: $username\nPassword: $password\n\nSilakan login dan ganti password Anda.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Gagal kirim email ke $emailTujuan: " . $mail->ErrorInfo);
        return false;
    }
}


/* ============================================================
   TEMPLATE HTML EMAIL
   ============================================================ */
function emailTemplate(string $nama, string $username, string $password): string {
    return "
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
            .container { max-width: 520px; margin: 40px auto; background: #fff;
                         border-radius: 12px; overflow: hidden;
                         box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: #0d1b2a; padding: 30px; text-align: center; }
            .header h2 { color: #ffc107; margin: 0; font-size: 20px; }
            .body { padding: 30px; color: #333; }
            .body p { line-height: 1.7; margin: 0 0 12px; }
            .credential-box { background: #f0f4ff; border-left: 4px solid #0d1b2a;
                              border-radius: 8px; padding: 16px 20px; margin: 20px 0; }
            .credential-box p { margin: 6px 0; font-size: 15px; }
            .credential-box span { font-weight: bold; color: #0d1b2a;
                                   font-size: 17px; letter-spacing: 1px; }
            .warning { background: #fff8e1; border-left: 4px solid #ffc107;
                       border-radius: 8px; padding: 14px 18px;
                       font-size: 13px; color: #7a6000; }
            .footer { background: #f4f4f4; text-align: center;
                      padding: 16px; font-size: 12px; color: #999; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🛡️ Sistem Diklat Satpam Gemilang</h2>
            </div>
            <div class='body'>
                <p>Halo, <strong>$nama</strong>!</p>
                <p>Pendaftaran Anda telah diterima. Berikut informasi akun untuk login ke sistem:</p>

                <div class='credential-box'>
                    <p>Username &nbsp;: <span>$username</span></p>
                    <p>Password &nbsp;&nbsp;: <span>$password</span></p>
                </div>

                <div class='warning'>
                    ⚠️ <strong>Penting:</strong> Segera ganti password Anda setelah login pertama
                    melalui menu <em>Ganti Password</em> di dashboard.
                    Jangan bagikan password kepada siapapun.
                </div>

                <p style='margin-top:20px;'>
                    Silakan login di:<br>
                    <a href='http://localhost/sistem-satpam/login.php'
                       style='color:#0d6efd;'>
                        http://localhost/sistem-satpam/login.php
                    </a>
                </p>
            </div>
            <div class='footer'>
                Email ini dikirim otomatis oleh sistem. Jangan membalas email ini.
            </div>
        </div>
    </body>
    </html>
    ";
}
/* ============================================================
   FIX PATH
   Fungsi untuk memastikan path file (uploads) benar baik diakses
   dari root maupun dari subfolder (admin/, siswa/, dll).
   ============================================================ */
function fix_path($path) {
    if (!$path) return '';
    // Bersihkan path dari ../ yang ada di awal (jika ada)
    $clean_path = preg_replace('/^(\.\.\/)+/', '', $path);
    
    // Cek apakah script yang memanggil ada di subfolder
    // Asumsi: subfolder selalu 1 tingkat di bawah root (ex: admin/xxx.php)
    // Cara cek: lihat apakah ../koneksi.php ada
    if (file_exists('../koneksi.php')) {
        return '../' . $clean_path;
    }
    return $clean_path;
}
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "sistem_diklat_satpam";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    // jangan tampilkan credential di produksi — ini untuk debugging lokal
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set utf8
mysqli_set_charset($conn, "utf8mb4");
?>
<!-- all set -->
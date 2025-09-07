<?php
// Pengaturan koneksi database
$servername = "localhost"; // Nama server database (dalam kasus ini, localhost)
$username = "root";       // Nama pengguna database (default untuk XAMPP/MAMP)
$password = "";           // Kata sandi database (default kosong untuk XAMPP/MAMP)
$dbname = "xvriez";   // Nama database yang telah Anda buat

// Membuat objek koneksi baru
$conn = new mysqli($servername, $username, $password, $dbname);

// Memeriksa apakah koneksi berhasil
if ($conn->connect_error) {
    // Jika koneksi gagal, hentikan eksekusi skrip dan tampilkan pesan error
    die("Koneksi ke database gagal: " . $conn->connect_error);
}
?>
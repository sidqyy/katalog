<?php
// Konfigurasi Database
$host = "localhost";
$user = "root";
$pass = ""; 
$db   = "katalog_db";

mysqli_report(MYSQLI_REPORT_OFF); // Matikan exception otomatis PHP 8+ agar tidak error 500
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    $is_api = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    if ($is_api) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
        die(json_encode(["status" => "error", "message" => "Koneksi database gagal."]));
    } else {
        die("<h1>Gangguan Sistem</h1><p>Sistem gagal terhubung ke database. Silakan coba beberapa saat lagi.</p>");
    }
}

$conn->set_charset("utf8mb4");
?>

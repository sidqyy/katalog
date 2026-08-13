<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Konfigurasi Database
$host = "localhost";
$user = "root";
$pass = ""; // Sesuaikan dengan password root Laragon Anda (biasanya kosong)
$db   = "katalog_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die(json_encode(["error" => "Koneksi gagal: " . $conn->connect_error]));
}

// Menangkap parameter GET
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? $conn->real_escape_string($_GET['kategori']) : '';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;

// Parameter Paginasi
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && (int)$_GET['limit'] > 0 ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Menyusun Query
$query = "SELECT id, nama_produk, harga, deskripsi, link_gambar FROM products WHERE 1=1";

if (!empty($search)) {
    $query .= " AND nama_produk LIKE '%$search%'";
}
if (!empty($kategori)) {
    $query .= " AND kategori = '$kategori'";
}
if ($min_price > 0) {
    $query .= " AND harga >= $min_price";
}
if ($max_price > 0) {
    $query .= " AND harga <= $max_price";
}

$query .= " ORDER BY id DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($query);

$products = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = [
            "id" => $row["id"],
            "nama" => $row["nama_produk"],
            "harga" => (float)$row["harga"],
            "deskripsi" => $row["deskripsi"],
            "link_gambar" => $row["link_gambar"]
        ];
    }
}

echo json_encode(["status" => "success", "data" => $products]);
$conn->close();
?>

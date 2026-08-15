<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config.php';

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
$query = "SELECT id, nama_produk, harga, deskripsi, link_gambar FROM products WHERE status = 1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND nama_produk LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if (!empty($kategori)) {
    $query .= " AND kategori = ?";
    $params[] = $kategori;
    $types .= "s";
}
if ($min_price > 0) {
    $query .= " AND harga >= ?";
    $params[] = $min_price;
    $types .= "d";
}
if ($max_price > 0) {
    $query .= " AND harga <= ?";
    $params[] = $max_price;
    $types .= "d";
}

$query .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$products = [];
if ($stmt = $conn->prepare($query)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $link_gambar = $row["link_gambar"];
            
            // AUTO-HEALING: Jika DB hasil export dari localhost, otomatis ganti URL ke domain live (cPanel)
            if (strpos($link_gambar, 'localhost') !== false || strpos($link_gambar, '127.0.0.1') !== false) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $current_host = $protocol . $_SERVER['HTTP_HOST'];
                $link_gambar = preg_replace('#^https?://(localhost|127\.0\.0\.1)(:\d+)?#', $current_host, $link_gambar);
            }

            $products[] = [
                "id" => $row["id"],
                "nama" => $row["nama_produk"],
                "harga" => (float)$row["harga"],
                "deskripsi" => $row["deskripsi"],
                "link_gambar" => $link_gambar
            ];
        }
    }
    $stmt->close();
}

echo json_encode(["status" => "success", "data" => $products]);
$conn->close();
?>

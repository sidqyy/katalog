<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config.php';

$query = "SELECT nama_kategori FROM categories ORDER BY id ASC";
$result = $conn->query($query);

$categories = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row["nama_kategori"];
    }
}

echo json_encode(["status" => "success", "data" => $categories]);
$conn->close();
?>

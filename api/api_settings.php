<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../config.php';

$stmt = $conn->prepare("SELECT wa1_name, wa1_number, wa2_name, wa2_number FROM settings WHERE id = 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(["status" => "success", "data" => $row]);
} else {
    echo json_encode(["status" => "error", "message" => "Settings not found"]);
}
$stmt->close();
$conn->close();
?>

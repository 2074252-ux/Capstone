<?php
require 'db.php';
header('Content-Type: application/json');

$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';

if ($barcode === '') {
    echo json_encode(['exists' => false]);
    exit;
}

$stmt = $conn->prepare("SELECT name, category_id, description FROM items WHERE barcode = ? LIMIT 1");
$stmt->bind_param("s", $barcode);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'exists' => true,
        'name' => $row['name'],
        'category_id' => $row['category_id'],
        'description' => $row['description']
    ]);
} else {
    echo json_encode(['exists' => false]);
}
$stmt->close();

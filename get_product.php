<?php
require 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['barcode'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No barcode provided'
    ]);
    exit;
}

$barcode = $_GET['barcode'];

// First check items table
$stmt = $conn->prepare("
    SELECT id, barcode, name, quantity, wholesale_price, retail_price, expiration_date 
    FROM items 
    WHERE barcode = ?
");

$stmt->bind_param("s", $barcode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'name' => $product['name'],
        'price' => $product['retail_price'],
        'wholesale_price' => $product['wholesale_price'],
        'retail_price' => $product['retail_price'],
        'quantity' => $product['quantity'],
        'expiration_date' => $product['expiration_date']
    ]);
    exit;
}

// If not found in items, check custom_barcodes
$stmt = $conn->prepare("
    SELECT id, barcode, name, price as retail_price
    FROM custom_barcodes 
    WHERE barcode = ?
");

$stmt->bind_param("s", $barcode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'name' => $product['name'],
        'price' => $product['retail_price'],
        'wholesale_price' => 0, // Default for custom barcodes
        'retail_price' => $product['retail_price'],
        'quantity' => 0
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Product not found'
]);
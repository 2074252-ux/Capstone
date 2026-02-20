
<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, barcode, name, quantity 
    FROM items 
    WHERE name LIKE ? 
    UNION 
    SELECT id, barcode, name, 0 as quantity 
    FROM custom_barcodes 
    WHERE name LIKE ? 
    LIMIT 10
");

$searchTerm = "%$query%";
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

header('Content-Type: application/json');
echo json_encode($items);
?>
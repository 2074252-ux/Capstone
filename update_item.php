<?php
require 'db.php';
$id = $_POST['id'];
$barcode = $_POST['barcode'];
$name = $_POST['name'];
$quantity = $_POST['quantity'];
$price = $_POST['price'];

$stmt = $conn->prepare("UPDATE items SET barcode=?, name=?, quantity=?, price=? WHERE id=?");
$stmt->bind_param("ssidi", $barcode, $name, $quantity, $price, $id);
$stmt->execute();
$stmt->close();
header("Location: items.php");
exit();

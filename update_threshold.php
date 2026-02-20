<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = intval($_POST['item_id']);
    $threshold = intval($_POST['threshold']);
    
    if ($threshold < 1) {
        $_SESSION['status_message'] = "❌ Threshold must be at least 1";
        $_SESSION['status_type'] = 'error';
        header('Location: items.php');
        exit;
    }
    
    $stmt = $conn->prepare("
        UPDATE items 
        SET low_stock_threshold = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $threshold, $item_id);
    
    if ($stmt->execute()) {
        $_SESSION['status_message'] = "✅ Low stock threshold updated successfully";
        $_SESSION['status_type'] = 'success';
    } else {
        $_SESSION['status_message'] = "❌ Error updating threshold: " . $stmt->error;
        $_SESSION['status_type'] = 'error';
    }
    
    header('Location: items.php');
    exit;
}
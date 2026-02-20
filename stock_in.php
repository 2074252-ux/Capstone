<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// AJAX lookup endpoint
if (isset($_GET['action']) && $_GET['action'] === 'lookup') {
    $barcode = $_GET['barcode'] ?? '';
    header('Content-Type: application/json; charset=utf-8');
    
    if (!$barcode) {
        echo json_encode(['found' => false]);
        exit;
    }

    // First check items table
    $stmt = $conn->prepare("
        SELECT id, barcode, name, expiration_date, quantity, 
               wholesale_price, retail_price, markup_percentage 
        FROM items 
        WHERE barcode = ?
        ORDER BY expiration_date IS NULL, expiration_date DESC
    ");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    // If not found in items, check custom_barcodes
    if (count($items) === 0) {
        $stmt = $conn->prepare("
            SELECT id, barcode, name, price as retail_price, wholesale_price, 0 as quantity,
                   NULL as expiration_date, 'custom' as source
            FROM custom_barcodes 
            WHERE barcode = ?
        ");
        $stmt->bind_param("s", $barcode);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    echo json_encode([
        'found' => count($items) > 0,
        'items' => $items,
        'source' => count($items) > 0 ? ($items[0]['source'] ?? 'items') : null
    ]);
    exit;
}

// Handle stock in POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = $_POST['barcode'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 0);
    $expiration_date = $_POST['expiration_date'] ? $_POST['expiration_date'] : null;
    $wholesale_price = isset($_POST['wholesale_price']) ? floatval($_POST['wholesale_price']) : 0;
    $markup_percentage = isset($_POST['markup_percentage']) ? floatval($_POST['markup_percentage']) : 10.00;
    
    // Calculate retail price based on wholesale price and markup
    $retail_price = $wholesale_price + ($wholesale_price * ($markup_percentage / 100));

    // Check if barcode exists in custom_barcodes
    $customQuery = $conn->prepare("SELECT * FROM custom_barcodes WHERE barcode = ?");
    $customQuery->bind_param("s", $barcode);
    $customQuery->execute();
    $customResult = $customQuery->get_result();

    if ($barcode && $quantity > 0) {
        $item_id = null;
        
        // Get item_id if exists
        $getItemId = $conn->prepare("SELECT id FROM items WHERE barcode = ? LIMIT 1");
        $getItemId->bind_param("s", $barcode);
        $getItemId->execute();
        $itemResult = $getItemId->get_result();
        
        if ($itemResult->num_rows > 0) {
            $item_id = $itemResult->fetch_assoc()['id'];
        }

        // Start transaction
        $conn->begin_transaction();
        try {
            if ($customResult->num_rows > 0) {
                // For custom items
                $stmt = $conn->prepare("
                    INSERT INTO items (
                        barcode, name, quantity, wholesale_price, 
                        markup_percentage, retail_price, expiration_date
                    )
                    SELECT 
                        barcode, name, ?, ?, 
                        ?, ?, ?
                    FROM custom_barcodes WHERE barcode = ?
                ");
                $stmt->bind_param(
                    "idddss", 
                    $quantity, $wholesale_price, 
                    $markup_percentage, $retail_price, 
                    $expiration_date, $barcode
                );
            } else {
                // For existing items
                $stmt = $conn->prepare("
                    UPDATE items 
                    SET quantity = quantity + ?,
                        expiration_date = COALESCE(?, expiration_date),
                        wholesale_price = ?,
                        markup_percentage = ?,
                        retail_price = ?
                    WHERE barcode = ?
                ");
                $stmt->bind_param(
                    "isddds", 
                    $quantity, $expiration_date, 
                    $wholesale_price, $markup_percentage, 
                    $retail_price, $barcode
                );
            }
            
            if ($stmt->execute()) {
                // Record the stock transaction with user_id
                $stockTransQuery = $conn->prepare("
                    INSERT INTO stock_transactions (item_id, type, quantity, user_id, date) 
                    VALUES (?, 'in', ?, ?, NOW())
                ");
                $userId = $_SESSION['user_id']; // Make sure you store user_id in session on login
                $stockTransQuery->bind_param("iii", $item_id, $quantity, $userId);
                $stockTransQuery->execute();

                $conn->commit();
                $_SESSION['status_message'] = "✅ Stock added successfully!";
                $_SESSION['status_type'] = 'success';
                header("Location: items.php");
                exit();
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['status_message'] = "❌ Error updating stock: " . $e->getMessage();
            $_SESSION['status_type'] = 'error';
            header("Location: items.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock In - Che-Che MiniMart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen flex font-sans text-slate-800">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 p-8 ml-[256px]">
        <header class="pb-6 mb-8 border-b-2 border-slate-200">
            <h1 class="text-3xl font-bold text-slate-900">📦 Stock In</h1>
        </header>

        <div class="max-w-xl mx-auto rounded-xl shadow-lg border border-slate-200 bg-white p-8">
            <?php if ($message): ?>
                <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6" id="stockInForm">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Barcode</label>
                    <div class="flex items-stretch space-x-2">
                        <input type="text" id="barcode" name="barcode" class="flex-1 p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-green-500" required>
                        <button type="button" id="start-scan" class="p-3 bg-green-600 text-white rounded-md hover:bg-green-700"><i class="bi bi-upc-scan"></i></button>
                        <button type="button" id="stop-scan" class="p-3 bg-red-600 text-white rounded-md" style="display:none;"><i class="bi bi-stop-fill"></i></button>
                    </div>
                    <div id="scanner" class="relative w-full aspect-video border-2 border-dashed border-green-400 rounded-lg bg-black overflow-hidden mt-2" style="display:none;"></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                    <input type="text" id="name" name="name" class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-green-500" placeholder="Item name will auto-fill when barcode is entered">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Expiration Date</label>
                    <input type="date" id="expiration_date" name="expiration_date" class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-green-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="1" class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-green-500" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Supplier Price</label>
                        <input type="number" step="0.01" id="wholesale_price" name="wholesale_price" class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price</label>
                        <input type="number" step="0.01" id="retail_price" name="retail_price" class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-green-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Markup Percentage</label>
                    <input type="number" step="0.01" id="markup_percentage" name="markup_percentage" value="10.00" class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-green-500">
                </div>

                <button type="submit" class="w-full py-3 px-4 font-bold text-white bg-green-600 rounded-md hover:bg-green-700">
                    ✅ Add Stock
                </button>
            </form>
        </div>
    </div>

    <script>
    let scannerRunning = false;
    const barcodeInput = document.getElementById('barcode');
    const nameInput = document.getElementById('name');
    const expirationInput = document.getElementById('expiration_date');
    const wholesaleInput = document.getElementById('wholesale_price');
    const retailInput = document.getElementById('retail_price');

    async function lookupBarcode(code) {
        if (!code) return;
        try {
            const res = await fetch(`stock_in.php?action=lookup&barcode=${encodeURIComponent(code)}`);
            const json = await res.json();
            if (json.found && json.item) {
                nameInput.value = json.item.name ?? '';
                expirationInput.value = json.item.expiration_date ?? '';
                wholesaleInput.value = json.item.wholesale_price ?? '';
                retailInput.value = json.item.retail_price ?? '';
            }
        } catch (e) {
            console.error('Lookup error', e);
        }
    }

    barcodeInput.addEventListener('change', (e) => lookupBarcode(e.target.value));
    barcodeInput.addEventListener('blur', (e) => lookupBarcode(e.target.value));

    document.getElementById('start-scan').addEventListener('click', function() {
        document.getElementById('scanner').style.display = 'block';
        document.getElementById('start-scan').style.display = 'none';
        document.getElementById('stop-scan').style.display = 'inline-flex';
        Quagga.init({
            inputStream: { type: "LiveStream", target: document.querySelector('#scanner'), constraints: { facingMode: "environment" }},
            decoder: { readers: ["ean_reader", "code_128_reader"] }
        }, function(err) {
            if (err) return console.error(err);
            Quagga.start(); scannerRunning = true;
        });
        Quagga.onDetected(function(data) {
            const code = data.codeResult.code;
            document.getElementById('barcode').value = code;
            lookupBarcode(code);
            stopScanner();
        });
    });
    document.getElementById('stop-scan').addEventListener('click', stopScanner);
    function stopScanner() {
        if (scannerRunning) { Quagga.stop(); scannerRunning = false; }
        document.getElementById('scanner').style.display = 'none';
        document.getElementById('start-scan').style.display = 'inline-flex';
        document.getElementById('stop-scan').style.display = 'none';
    }
    </script>
</body>
</html>

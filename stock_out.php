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
        SELECT id, barcode, name, expiration_date, quantity, retail_price 
        FROM items 
        WHERE barcode = ?
        ORDER BY expiration_date IS NULL, expiration_date ASC
    ");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $row['source'] = 'items';
        $items[] = $row;
    }
    
    // If not found in items, check custom_barcodes
    if (count($items) === 0) {
        $stmt = $conn->prepare("
            SELECT id, barcode, name, price as retail_price, 
                   NULL as expiration_date, 0 as quantity,
                   'custom' as source
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
        'items' => $items
    ]);
    exit;
}

// Handle stock out POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = $_POST['barcode'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 0);
    $reason = $_POST['stock_out_reason'] ?? '';
    $remarks = $_POST['damage_remarks'] ?? '';

    if ($barcode && $quantity > 0) {
        $conn->begin_transaction();

        try {
            // Get current item details
            $stmt = $conn->prepare("SELECT * FROM items WHERE barcode = ?");
            $stmt->bind_param("s", $barcode);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if ($item && $item['quantity'] >= $quantity) {
                // Update items table - reset expiration if expired reason
                if ($reason === 'expired') {
                    $stmt = $conn->prepare("
                        UPDATE items 
                        SET quantity = quantity - ?, 
                            expiration_date = NULL 
                        WHERE barcode = ?
                    ");
                } else {
                    $stmt = $conn->prepare("
                        UPDATE items 
                        SET quantity = quantity - ? 
                        WHERE barcode = ?
                    ");
                }
                $stmt->bind_param("is", $quantity, $barcode);
                $stmt->execute();

                // Record in appropriate table based on reason
                if ($reason === 'expired') {
                    $stmt = $conn->prepare("
                        INSERT INTO expired_items (
                            item_id, barcode, name, category_id, retail_price,
                            current_stock, expired_quantity, expiration_date, 
                            stock_remained, date_reported
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stock_remained = $item['quantity'] - $quantity;
                    $stmt->bind_param(
                        "issidisss",
                        $item['id'], $barcode, $item['name'], $item['category_id'],
                        $item['retail_price'], $item['quantity'], $quantity,
                        $item['expiration_date'], $stock_remained
                    );
                    $stmt->execute();
                } elseif ($reason === 'damage') {
                    $stmt = $conn->prepare("
                        INSERT INTO damage_items (
                            item_id, barcode, name, category_id, retail_price,
                            current_stock, damage_quantity, remarks, stock_remained,
                            user_id, date_reported
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stock_remained = $item['quantity'] - $quantity;
                    $stmt->bind_param(
                        "issiiiisii", 
                        $item['id'], $barcode, $item['name'], $item['category_id'],
                        $item['retail_price'], $item['quantity'], $quantity,
                        $remarks, $stock_remained,
                        $_SESSION['user_id']
                    );
                    $stmt->execute();
                }

                $conn->commit();
                $_SESSION['status_message'] = "✅ Stock removed successfully!";
                $_SESSION['status_type'] = 'success';
            } else {
                throw new Exception("Not enough stock available");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['status_message'] = "❌ Error: " . $e->getMessage();
            $_SESSION['status_type'] = 'error';
        }
    }
    
    header("Location: items.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Out - Che-Che MiniMart</title>
    <!-- Tailwind CSS CDN for a modern design -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <style>
        /* Custom scrollbar for a clean look */
        .scrollable::-webkit-scrollbar {
            width: 8px;
        }
        .scrollable::-webkit-scrollbar-track {
            background: #f1f5f9; /* slate-100 */
        }
        .scrollable::-webkit-scrollbar-thumb {
            background-color: #cbd5e1; /* slate-300 */
            border-radius: 4px;
        }
        .scrollable::-webkit-scrollbar-thumb:hover {
            background-color: #94a3b8; /* slate-400 */
        }
        #scanner video {
            object-fit: cover;
            width: 100% !important;
            height: 100% !important;
            border-radius: 8px;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen font-sans antialiased text-slate-800 flex">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 p-8 ml-[256px] transition-all duration-300">
        <!-- Header -->
        <header class="pb-6 mb-8 border-b-2 border-slate-200">
            <h1 class="text-3xl font-bold text-slate-900">📦 Stock Out</h1>
        </header>

        <div class="max-w-xl mx-auto rounded-xl shadow-lg border border-slate-200 bg-white p-8">
            <?php if ($message): ?>
                <div class="p-4 mb-4 text-sm text-sky-800 rounded-lg bg-sky-50" role="alert">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="barcode" class="block text-sm font-medium text-slate-700 mb-1">Barcode</label>
                    <div class="flex items-stretch space-x-2">
                        <input type="text" id="barcode" name="barcode" class="flex-1 p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors" placeholder="Scan or enter barcode" required>
                        <button type="button" class="flex items-center justify-center p-3 text-sm font-semibold text-white bg-red-600 rounded-md shadow hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors" id="start-scan">
                            <i class="bi bi-upc-scan text-lg"></i>
                        </button>
                        <button type="button" class="flex items-center justify-center p-3 text-sm font-semibold text-white bg-red-600 rounded-md shadow hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors" id="stop-scan" style="display:none;">
                            <i class="bi bi-stop-fill text-lg"></i>
                        </button>
                    </div>
                </div>
                
                <div id="scanner" class="relative w-full aspect-video border-2 border-dashed border-red-400 rounded-lg bg-black overflow-hidden" style="display:none;"></div>

                <div>
                    <label for="quantity" class="block text-sm font-medium text-slate-700 mb-1">Quantity</label>
                    <input type="number" id="quantity" name="quantity" class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors" min="1" required>
                </div>

                <div>
                    <label for="stock_out_reason" class="block text-sm font-medium text-slate-700 mb-1">Reason for Stock Out</label>
                    <select id="stock_out_reason" name="stock_out_reason" class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors" required>
                        <option value="">Select a reason</option>
                        <option value="damage">Damage</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>

                <div id="damage-remarks-field" style="display:none;">
                    <label for="damage_remarks" class="block text-sm font-medium text-slate-700 mb-1">Damage Remarks</label>
                    <input type="text" id="damage_remarks" name="damage_remarks" class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors" placeholder="Enter remarks for damage" maxlength="255">
                </div>

                <button type="submit" class="w-full py-3 px-4 font-bold text-white bg-red-600 rounded-md hover:bg-red-700 flex items-center justify-center space-x-2">
                    <i class="bi bi-dash-circle-fill text-xl"></i>
                    <span>Remove Stock</span>
                </button>
            </form>
        </div>
    </div>

    <script>
    let scannerRunning = false;
    document.getElementById('start-scan').addEventListener('click', function() {
        document.getElementById('scanner').style.display = 'block';
        document.getElementById('start-scan').style.display = 'none';
        document.getElementById('stop-scan').style.display = 'inline-flex';
        Quagga.init({
            inputStream: {
                type : "LiveStream",
                target: document.querySelector('#scanner'),
                constraints: {
                    width: 640,
                    height: 480,
                    facingMode: "environment"
                }
            },
            decoder: {
                readers : ["ean_reader", "code_128_reader"]
            }
        }, function(err) {
            if (err) {
                console.error(err);
                document.getElementById('scanner').style.display = 'none';
                document.getElementById('start-scan').style.display = 'inline-flex';
                document.getElementById('stop-scan').style.display = 'none';
                return;
            }
            Quagga.start();
            scannerRunning = true;
        });

        Quagga.onDetected(function(data) {
            document.getElementById('barcode').value = data.codeResult.code;
            stopScanner();
        });
    });

    document.getElementById('stop-scan').addEventListener('click', stopScanner);

    function stopScanner() {
        if (scannerRunning) {
            Quagga.stop();
            scannerRunning = false;
        }
        document.getElementById('scanner').style.display = 'none';
        document.getElementById('start-scan').style.display = 'inline-flex';
        document.getElementById('stop-scan').style.display = 'none';
    }

    document.getElementById('stock_out_reason').addEventListener('change', function() {
        const value = this.value;
        document.getElementById('damage-remarks-field').style.display = (value === 'damage') ? 'block' : 'none';
    });
    </script>
</body>
</html>

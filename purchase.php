<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$message = "";

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    $items = json_decode($_POST['cart_data'], true);
    $transaction_id = uniqid("TXN");
    $total_price = 0;

    $stock_check_passed = true;
    $stock_errors = [];

    // --- CRITICAL: Stock Availability Check ---
    if (!empty($items)) {
        foreach ($items as $item) {
            $barcode = $item['barcode'];
            $requested_quantity = $item['quantity'];

            // 1. Get current stock for the item
            $check_stock = $conn->prepare("SELECT name, quantity FROM items WHERE barcode = ?");
            $check_stock->bind_param("s", $barcode);
            $check_stock->execute();
            $result = $check_stock->get_result();

            if ($result->num_rows === 0) {
                $stock_errors[] = "Product with barcode <strong>{$barcode}</strong> not found.";
                $stock_check_passed = false;
                break;
            }

            $product = $result->fetch_assoc();
            $available_quantity = $product['quantity'];
            $product_name = $product['name'];

            // 2. Compare requested quantity vs available stock
            if ($requested_quantity > $available_quantity) {
                $stock_errors[] = "Insufficient stock for <strong>{$product_name}</strong> (Barcode: {$barcode}). Requested: {$requested_quantity}, Available: {$available_quantity}.";
                $stock_check_passed = false;
            }
        }
    } else {
        $message = "❌ Cannot complete purchase: Cart is empty.";
        $stock_check_passed = false;
    }
    // --- END Stock Availability Check ---


    
    if ($stock_check_passed) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            foreach ($items as $it) {
                $barcode = $it['barcode'];
                $name = $it['name'];
                $quantity = intval($it['quantity']);
                $price = floatval($it['price']);
                $subtotal = $price * $quantity;
                
                // Get wholesale price from items table
                $stmt = $conn->prepare("
                    SELECT wholesale_price, retail_price 
                    FROM items 
                    WHERE barcode = ?
                    UNION
                    SELECT wholesale_price, retail_price 
                    FROM custom_barcodes 
                    WHERE barcode = ?
                ");
                $stmt->bind_param("ss", $barcode, $barcode);
                $stmt->execute();
                $result = $stmt->get_result();
                $priceData = $result->fetch_assoc();
                
                $wholesale_price = $priceData ? floatval($priceData['wholesale_price']) : 0;
                $retail_price = $priceData ? floatval($priceData['retail_price']) : $price;

                // Insert sale record
                $insert = $conn->prepare("
                    INSERT INTO sales (
                        transaction_id,
                        barcode,
                        name,
                        wholesale_price,
                        retail_price,
                        quantity,
                        total_amount,
                        sale_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $insert->bind_param(
                    "sssdddd",
                    $transaction_id,
                    $barcode,
                    $name,
                    $wholesale_price,
                    $retail_price,
                    $quantity,
                    $subtotal
                );
                
                if (!$insert->execute()) {
                    throw new Exception("Failed to record sale: " . $insert->error);
                }

                // Update inventory
                $update = $conn->prepare("
                    UPDATE items 
                    SET quantity = quantity - ? 
                    WHERE barcode = ?
                ");
                $update->bind_param("is", $quantity, $barcode);
                $update->execute();
            }

            $conn->commit();
            $message = "✅ Sale recorded successfully! Total: ₱" . number_format($total_price, 2);

        } catch (Exception $e) {
            $conn->rollback();
            $message = "❌ Error: " . $e->getMessage();
        }
    } else {
        // Sale failed due to stock issues
        if (!empty($stock_errors)) {
            // Combine errors and use <strong> for product names
            $error_details = implode("<br>", $stock_errors);
            $message = "❌ <strong>Checkout failed due to insufficient stock:</strong><br>" . $error_details;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Record Sale - Che-Che MiniMart</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>

    <style>
        body { font-family: 'Inter', system-ui, -apple-system, "Segoe UI", Roboto, Arial; }
        .card { background: white; border-radius: 12px; box-shadow: 0 8px 24px rgba(15,23,42,0.06); border: 1px solid rgba(15,23,42,0.04); }
        .btn-primary { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .alert { border-radius: 10px; padding: 12px; display:flex; gap:12px; align-items:center; }
        .table-head { background: linear-gradient(90deg, rgba(245,158,11,0.12), rgba(245,158,11,0.06)); }
        @media (max-width: 768px) {
            .desktop-grid { grid-template-columns: 1fr; }
            .scanner-area { order: 2; }
        }
        /* small scrollbar */
        .scrollable::-webkit-scrollbar { height:8px; width:8px; }
        .scrollable::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.12); border-radius:6px; }

            </style>
</head>
<body class="bg-slate-50 min-h-screen antialiased text-slate-800 flex">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 p-6 lg:p-10 ml-[256px]">
        <header class="mb-6">
            <h1 class="text-2xl lg:text-3xl font-extrabold">🛒 Record Sale</h1>
            <p class="text-sm text-slate-500 mt-1">For handheld scanner and camera scan. Add items then Record Sale.</p>
        </header>

        <div class="card p-6 mb-6">
            <!-- Message area (server-side) -->
            <?php if ($message): ?>
                <?php
                    // Keep HTML in server message for detailed errors intentionally (already used in your code)
                    $is_error = strpos($message, '❌') !== false;
                    $bg = $is_error ? 'bg-red-50 border-red-100 text-red-800' : 'bg-emerald-50 border-emerald-100 text-emerald-800';
                ?>
                <div class="alert <?= $bg ?> border mb-4">
                    <div class="text-2xl"><?= $is_error ? '❌' : '✅' ?></div>
                    <div class="flex-1 text-sm"><?= $message ?></div>
                    <button id="closeServerAlert" class="text-slate-500 hover:text-slate-700">✕</button>
                </div>
            <?php endif; ?>

            <form id="addItemForm" onsubmit="event.preventDefault(); processItem();">
                <div class="grid grid-cols-12 gap-4 desktop-grid items-end">
                    <div class="col-span-12 md:col-span-6">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Barcode</label>
                        <div class="flex gap-2">
                            <input type="text" id="barcode" class="flex-1 p-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-amber-400" placeholder="Scan barcode or type manually">
                            <button id="start-scan" type="button" class="px-3 py-2 rounded-lg btn-primary hover:opacity-95">Scan</button>
                            <button id="stop-scan" type="button" class="px-3 py-2 rounded-lg btn-danger" style="display:none;">Stop</button>
                        </div>
                        <p class="text-xs text-slate-400 mt-2">Handheld scanner: simply scan — no extra clicks needed.</p>
                    </div>

                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Product</label>
                        <input type="text" id="product_name" class="w-full p-3 rounded-lg border border-slate-300" readonly>
                    </div>

                    <div class="col-span-3 md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price</label>
                        <input type="number" step="0.01" id="price" class="w-full p-3 rounded-lg border border-slate-300" readonly>
                    </div>

                    <div class="col-span-3 md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Qty</label>
                        <input type="number" id="quantity" value="1" min="1" class="w-full p-3 rounded-lg border border-slate-300">
                    </div>

                    <div class="col-span-12 md:col-span-12 lg:col-span-12 text-right">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700">Proceed</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="table-head text-slate-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Barcode</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider">Selling Price</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider">Qty</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider">Subtotal</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cart-table-body" class="bg-white divide-y divide-slate-100 scrollable max-h-[48vh] overflow-y-auto"></tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="4" class="px-6 py-3 text-right font-semibold">TOTAL</td>
                            <td id="total" class="px-6 py-3 text-right font-bold text-emerald-600">₱0.00</td>
                            <td class="px-6 py-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-4">
                <form id="checkout-form" method="POST" class="w-full md:w-auto">
                    <input type="hidden" name="cart_data" id="cart_data">
                    <button type="submit" name="checkout" class="px-6 py-3 rounded-lg bg-amber-500 text-white font-semibold hover:bg-amber-600 w-full md:w-auto">✅ Record Sale</button>
                </form>
             
            </div>
        </div>
    </div>

    <div id="interactive" class="viewport hidden fixed inset-0 bg-black/90 z-50">
        <div class="absolute inset-0 flex flex-col items-center justify-center p-4">
            <div class="relative w-full max-w-lg aspect-video rounded-lg overflow-hidden">
                <video class="w-full h-full object-cover"></video>
                <div class="absolute inset-0 pointer-events-none border-2 border-amber-500/50">
                    <div class="absolute top-0 left-1/2 w-1/2 h-1 bg-amber-500/50"></div>
                    <div class="absolute bottom-0 left-1/2 w-1/2 h-1 bg-amber-500/50"></div>
                    <div class="absolute left-0 top-1/2 h-1/2 w-1 bg-amber-500/50"></div>
                    <div class="absolute right-0 top-1/2 h-1/2 w-1 bg-amber-500/50"></div>
                </div>
            </div>
            <button type="button" id="close-scanner" class="mt-4 px-6 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg">
                Close Camera
            </button>
        </div>
    </div>










  
    <input type="text" id="global-scanner-capture" class="sr-only" autocomplete="off" />

    <script src="scripts/purchase.js"></script>
</body>
</html>

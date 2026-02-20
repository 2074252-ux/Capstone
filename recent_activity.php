<?php

require 'db.php';

require 'auth.php';
check_login();

// If employee and no temporary access, show restricted page
if (is_page_restricted() && !has_temporary_access()) {
    require 'restricted.php';
    exit;
}

/**
 * Checks if a column exists in a given table.
 * @param mysqli $conn Database connection.
 * @param string $table Table name.
 * @param string $column Column name.
 * @return bool True if column exists, false otherwise.
 */
function column_exists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $db = $conn->real_escape_string($conn->query("SELECT DATABASE()")->fetch_row()[0] ?? '');
    $q = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'");
    if ($q) return (int)$q->fetch_assoc()['cnt'] > 0;
    return false;
}

/**
 * Checks if a table exists.
 * @param mysqli $conn Database connection.
 * @param string $name Table name.
 * @return bool True if table exists, false otherwise.
 */
function table_exists($conn, $name) {
    $name = $conn->real_escape_string($name);
    $res = $conn->query("SHOW TABLES LIKE '$name'");
    return ($res && $res->num_rows > 0);
}

// --- Stock In Query (Already has 'Processed By' logic) ---
$stockInResult = $conn->query("
    SELECT 
        i.name AS item_name, 
        st.quantity,
        st.date AS activity_date,
        i.barcode,
        c.name AS category_name,
        i.expiration_date,
        u.username AS processed_by,
        u.role AS user_role
    FROM stock_transactions st
    JOIN items i ON st.item_id = i.id
    LEFT JOIN categories c ON i.category_id = c.id
    -- Assuming stock_transactions uses 'user_id' for the processor
    LEFT JOIN users u ON st.user_id = u.id 
    WHERE st.type = 'in'
    ORDER BY st.date DESC, st.id DESC
");


$stockIn = $stockInResult ? $stockInResult->fetch_all(MYSQLI_ASSOC) : [];

// --- Stock Out Processed By Logic ---
// 1. Determine the user ID column for damage_items (di)
$damage_user_col = column_exists($conn, 'damage_items', 'user_id') ? 'user_id' : 
                   (column_exists($conn, 'damage_items', 'entered_by') ? 'entered_by' : null);
$damage_select = $damage_user_col ? "ud.username AS actor_name, ud.role AS actor_role" : "NULL AS actor_name, NULL AS actor_role";
// Use alias 'ud' for users join in damaged items subquery
$damage_join = $damage_user_col ? "LEFT JOIN users ud ON di.{$damage_user_col} = ud.id" : "";

// 2. Determine the user ID column for expired_items (ei)
$expired_user_col = column_exists($conn, 'expired_items', 'user_id') ? 'user_id' : 
                    (column_exists($conn, 'expired_items', 'entered_by') ? 'entered_by' : null);
$expired_select = $expired_user_col ? "ue.username AS actor_name, ue.role AS actor_role" : "NULL AS actor_name, NULL AS actor_role";
// Use alias 'ue' for users join in expired items subquery
$expired_join = $expired_user_col ? "LEFT JOIN users ue ON ei.{$expired_user_col} = ue.id" : "";

// 3. Determine the user ID column for sales (s) when used as stock-out reason
$sales_stockout_user_col = column_exists($conn, 'sales', 'user_id') ? 'user_id' : 
                           (column_exists($conn, 'sales', 'entered_by') ? 'entered_by' : null);
$sales_stockout_select = $sales_stockout_user_col ? "us.username AS actor_name, us.role AS actor_role" : "NULL AS actor_name, NULL AS actor_role";
// Use alias 'us' for users join in sales subquery
$sales_stockout_join = $sales_stockout_user_col ? "LEFT JOIN users us ON s.{$sales_stockout_user_col} = us.id" : "";


// Stock Out - Fetching items removed from stock
$stockOutResult = $conn->query("
    SELECT 
        item_name,
        quantity,
        activity_date,
        reason,
        damage_remarks,
        expired_date,
        barcode,
        category_name,
        actor_name,
        actor_role
    FROM (
        -- Get damaged items (Stock Out reason 1)
        SELECT 
            i.name AS item_name,
            di.damage_quantity AS quantity,
            di.date_reported AS activity_date,
            'Damaged' AS reason,
            di.remarks AS damage_remarks,
            NULL AS expired_date,
            i.barcode,
            c.name AS category_name,
            {$damage_select} -- Now selects actor name/role
        FROM damage_items di
        JOIN items i ON di.item_id = i.id
        LEFT JOIN categories c ON i.category_id = c.id
        {$damage_join} -- New join for the processor (ud)
        
        UNION ALL
        
        -- Get expired items (Stock Out reason 2)
        SELECT 
            i.name AS item_name,
            ei.expired_quantity AS quantity,
            ei.date_reported AS activity_date,
            'Expired' AS reason,
            NULL AS damage_remarks,
            ei.expiration_date AS expired_date,
            i.barcode,
            c.name AS category_name,
            {$expired_select} -- Now selects actor name/role
        FROM expired_items ei
        JOIN items i ON ei.item_id = i.id
        LEFT JOIN categories c ON i.category_id = c.id
        {$expired_join} -- New join for the processor (ue)
        
        UNION ALL
        
        -- Get sold items from sales (Stock Out reason 3)
        SELECT 
            s.name AS item_name,
            s.quantity,
            s.sale_date AS activity_date,
            'Sold' AS reason,
            NULL AS damage_remarks,
            NULL AS expired_date,
            s.barcode,
            NULL AS category_name,
            {$sales_stockout_select} -- Now selects actor name/role
        FROM sales s
        {$sales_stockout_join} -- New join for the processor (us)
    ) AS combined_stock_out
    ORDER BY activity_date DESC
");

$stockOut = $stockOutResult ? $stockOutResult->fetch_all(MYSQLI_ASSOC) : [];

// --- Sales: attempt to include actor info for each sale row if present in schema ---
// Reusing the general sales actor logic for the main sales query
$sales_actor_select = "NULL AS actor_name, NULL AS actor_role";
$sales_actor_join = "";
if (table_exists($conn, 'users')) {
    if (column_exists($conn, 'sales', 'user_id')) {
        $sales_actor_select = "u.username AS actor_name, u.role AS actor_role";
        $sales_actor_join = "LEFT JOIN users u ON s.user_id = u.id";
    } elseif (column_exists($conn, 'sales', 'entered_by')) {
        $sales_actor_select = "u.username AS actor_name, u.role AS actor_role";
        $sales_actor_join = "LEFT JOIN users u ON s.entered_by = u.id";
    }
}

$sales = $conn->query("
    SELECT 
        s.transaction_id,
        s.barcode,
        s.name AS item_name,
        s.quantity,
        s.wholesale_price,
        s.retail_price,
        s.total_amount,
        s.sale_date,
        {$sales_actor_select}
    FROM sales s
    {$sales_actor_join}
    ORDER BY s.sale_date DESC, s.transaction_id DESC
    LIMIT 100
");

$salesGrouped = [];
if ($sales) {
    while ($row = $sales->fetch_assoc()) {
        $txnId = $row['transaction_id'] ?? 'NO_TXN';
        if (!isset($salesGrouped[$txnId])) {
            $salesGrouped[$txnId] = [
                'date' => $row['sale_date'],
                'items' => [],
                'total' => 0,
                // These now correctly capture the actor info from the query
                'actor_name' => $row['actor_name'] ?? null, 
                'actor_role' => $row['actor_role'] ?? null,
            ];
        }

        $lineTotal = floatval($row['total_amount']);
        $salesGrouped[$txnId]['items'][] = [
            'name' => $row['item_name'],
            'barcode' => $row['barcode'],
            'quantity' => $row['quantity'],
            'retail_price' => $row['retail_price'],
            'wholesale_price' => $row['wholesale_price'],
            'line_total' => $lineTotal
        ];
        $salesGrouped[$txnId]['total'] += $lineTotal;
    }
}

// Re-seek for tables that used fetch_all earlier is not required here because we used arrays
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Recent Activity - Che-Che MiniMart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-slate-50 min-h-screen font-sans antialiased text-slate-800 flex">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 p-8 md:ml-[256px]">
        <header class="pb-6 mb-8 border-b-2 border-slate-200">
            <h1 class="text-3xl font-bold text-slate-900">📋 Recent Activity Logs</h1>
        </header>

        <div class="flex space-x-2 mb-6 border-b border-slate-200 p-1 rounded-t-lg bg-slate-100">
            <button class="tab-button flex items-center gap-2 p-3 text-sm font-semibold rounded-lg transition-colors duration-200 bg-sky-600 text-white" 
                    data-target="stockInContent">
                <i class="bi bi-box-arrow-in-down"></i> Stock In
            </button>
            <button class="tab-button flex items-center gap-2 p-3 text-sm font-semibold rounded-lg transition-colors duration-200 bg-white text-slate-700 hover:bg-slate-100" 
                    data-target="stockOutContent">
                <i class="bi bi-box-arrow-up"></i> Stock Out
            </button>
            <button class="tab-button flex items-center gap-2 p-3 text-sm font-semibold rounded-lg transition-colors duration-200 bg-white text-slate-700 hover:bg-slate-100" 
                    data-target="salesContent">
                <i class="bi bi-cash-coin"></i> Sales
            </button>
        </div>

        <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6">
            <div id="stockInContent" class="tab-content" style="max-height: calc(100vh - 250px); overflow-y: auto;">
                <h2 class="text-xl font-bold mb-4 text-green-600">Recent Stock In Entries</h2>
                
                <!-- Search and Filter Controls -->
                <div class="mb-4 flex flex-wrap gap-3">
                    <div class="flex-1">
                        <input type="text" 
                               id="stockInSearch" 
                               placeholder="Search items..."
                               class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <div class="w-48">
                        <input type="date" 
                               id="stockInDateFilter"
                               class="w-full p-2 border border-slate-300 rounded-lg">
                    </div>
                    <button onclick="resetStockInFilters()"
                            class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">
                        Reset
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 border-collapse">
                        <thead class="sticky top-0 bg-white text-slate-800 font-medium border-b border-slate-300">
                            <tr>
                                <th class="p-4">Item</th>
                                <th class="p-4">Category</th>
                                <th class="p-4">Barcode</th>
                                <th class="p-4">Quantity</th>
                                <th class="p-4">Expiration Date</th>
                                <th class="p-4">Date added</th>
                                <th class="p-4">Processed By</th>
                            </tr>
                        </thead>
                        <tbody id="stockInTableBody">
                            <?php if (!empty($stockIn)): ?>
                                <?php foreach ($stockIn as $row): ?>
                                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                        <td class="p-4 font-medium text-slate-800"><?= htmlspecialchars($row['item_name']) ?></td>
                                        <td class="p-4"><?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></td>
                                        <td class="p-4 font-mono text-xs"><?= htmlspecialchars($row['barcode']) ?></td>
                                        <td class="p-4 text-green-600 font-bold">+<?= htmlspecialchars($row['quantity']) ?></td>
                                        <td class="p-4"><?= $row['expiration_date'] ? date('Y-m-d', strtotime($row['expiration_date'])) : 'N/A' ?></td>
                                        <td class="p-4 text-slate-900"><?= date('Y-m-d H:i', strtotime($row['activity_date'])) ?></td>
                                        <td class="p-4">
                                            <?php if ($row['processed_by']): ?>
                                                <span class="px-2 py-1 text-xs rounded-full <?= $row['user_role'] === 'owner' ? 'bg-violet-100 text-violet-800' : 'bg-blue-100 text-blue-800' ?>">
                                                    <?= ucfirst(htmlspecialchars($row['user_role'])) ?>: <?= htmlspecialchars($row['processed_by']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>


<div id="stockOutContent" class="tab-content hidden" style="max-height: calc(100vh - 250px); overflow-y: auto;">
    <h2 class="text-xl font-bold mb-4 text-red-600">Recent Stock Out Entries</h2>
    
    <!-- Add search and filter similar to stock in -->
    <div class="mb-4 flex flex-wrap gap-3">
        <div class="flex-1">
            <input type="text" 
                   id="stockOutSearch" 
                   placeholder="Search items..."
                   class="w-full p-2 border border-slate-300 rounded-lg">
        </div>
        <select id="stockOutReasonFilter" 
                class="p-2 border border-slate-300 rounded-lg">
            <option value="all">All Reasons</option>
            <option value="Damaged">Damaged</option>
            <option value="Expired">Expired</option>
            <option value="Sold">Sold</option>
            <option value="Regular">Regular Stock Out</option>
        </select>
        <div class="w-48">
            <input type="date" 
                   id="stockOutDateFilter"
                   class="w-full p-2 border border-slate-300 rounded-lg">
        </div>
        <button onclick="resetStockOutFilters()"
                class="px-4 py-2 bg-slate-100 rounded-lg hover:bg-slate-200">
            Reset
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600 border-collapse">
            <thead class="sticky top-0 bg-white text-slate-800 font-medium border-b border-slate-300">
                <tr>
                    <th class="p-4">Item</th>
                    <th class="p-4">Category</th>
                    <th class="p-4">Quantity</th>
                    <th class="p-4">Reason</th>
                    <th class="p-4">Details</th>
                    <th class="p-4">Date remove</th>
                    <th class="p-4">Processed By</th> <!-- Added Processed By column -->
                </tr>
            </thead>
            <tbody id="stockOutTableBody">
                <?php if (!empty($stockOut)): ?>
                    <?php foreach ($stockOut as $row): 
                        $statusColor = match($row['reason']) {
                            'Damaged' => 'text-red-600',
                            'Expired' => 'text-amber-600',
                            'Sold' => 'text-emerald-600',
                            default => 'text-slate-600'
                        };
                    ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                            <td class="p-4 font-medium text-slate-800"><?= htmlspecialchars($row['item_name']) ?></td>
                            <td class="p-4"><?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></td>
                            <td class="p-4 text-red-600 font-bold">-<?= htmlspecialchars($row['quantity']) ?></td>
                            <td class="p-4 <?= $statusColor ?> font-medium"><?= htmlspecialchars($row['reason']) ?></td>
                            <td class="p-4 text-sm">
                                <?php if ($row['reason'] === 'Damaged' && $row['damage_remarks']): ?>
                                    <?= htmlspecialchars($row['damage_remarks']) ?>
                                <?php elseif ($row['reason'] === 'Expired' && $row['expired_date']): ?>
                                    Expired on: <?= htmlspecialchars($row['expired_date']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-slate-900"><?= date('Y-m-d H:i', strtotime($row['activity_date'])) ?></td>
                            <td class="p-4">
                                <?php if ($row['actor_name']): // Display Processed By Badge ?>
                                    <span class="px-2 py-1 text-xs rounded-full <?= $row['actor_role'] === 'owner' ? 'bg-violet-100 text-violet-800' : 'bg-blue-100 text-blue-800' ?>">
                                        <?= ucfirst(htmlspecialchars($row['actor_role'])) ?>: <?= htmlspecialchars($row['actor_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center p-4 text-slate-500">No stock out activity recorded.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

            <div id="salesContent" class="tab-content hidden" style="max-height: calc(100vh - 250px); overflow-y: auto;">
                <h2 class="text-xl font-bold mb-4 text-amber-600">Recent Sales Transactions</h2>
                <?php if (!empty($salesGrouped)): ?>
                    <?php foreach ($salesGrouped as $txnId => $txn): ?>
                        <div class="bg-slate-50 rounded-lg border border-slate-200 p-4 mb-4 last:mb-0">
                            <div class="flex justify-between items-center border-b border-slate-200 pb-2 mb-2">
                                <strong class="text-slate-900">🧾 Transaction #<?= htmlspecialchars($txnId) ?></strong>
                                
                                <!-- Updated Sales Processed By Display -->
                                <span class="text-xs text-slate-500 flex items-center gap-2">
                                    <?= date('Y-m-d H:i:s', strtotime($txn['date'])) ?>
                                    <?php if (!empty($txn['actor_name'])): ?>
                                        <span class="px-2 py-1 text-xs rounded-full <?= $txn['actor_role'] === 'owner' ? 'bg-violet-100 text-violet-800' : 'bg-blue-100 text-blue-800' ?>">
                                            <?= ucfirst(htmlspecialchars($txn['actor_role'])) ?>: <?= htmlspecialchars($txn['actor_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <!-- End Updated Sales Processed By Display -->
                            </div>
                            <table class="w-full text-left text-xs text-slate-600 border-collapse">
                                <thead>
                                    <tr class="text-slate-500 uppercase font-medium">
                                        <th class="py-2">Item</th>
                                        <th class="py-2">Barcode</th>
                                        <th class="py-2 text-center">Qty</th>
                                        <th class="py-2 text-right">Price</th>
                                        <th class="py-2 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($txn['items'] as $item): ?>
                                        <tr class="border-t border-slate-100">
                                            <td class="py-2"><?= htmlspecialchars($item['name']) ?></td>
                                            <td class="py-2 font-mono text-xs"><?= htmlspecialchars($item['barcode']) ?></td>
                                            <td class="py-2 text-center"><?= htmlspecialchars($item['quantity']) ?></td>
                                            <td class="py-2 text-right">₱<?= number_format($item['retail_price'], 2) ?></td>
                                            <td class="py-2 text-right">₱<?= number_format($item['line_total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-slate-200 font-bold text-slate-800">
                                        <td colspan="4" class="py-2 text-right">Total:</td>
                                        <td class="py-2 text-right">₱<?= number_format($txn['total'], 2) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-slate-500 text-center py-4">No sales recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="scripts/recent_activity.js"></script>
</body>
</html>

<?php

require 'db.php';


require 'auth.php';
check_login();

// If employee and no temporary access, show restricted page
if (is_page_restricted() && !has_temporary_access()) {
    require 'restricted.php';
    exit;
}


// --- Dashboard Detailed Stats Queries ---
// General stats
$totalItems = $conn->query("SELECT COUNT(*) AS total FROM items")->fetch_assoc()['total'];
$totalStockInToday = $conn->query("SELECT SUM(quantity) AS total FROM stock_transactions WHERE type='in' AND DATE(date) = CURDATE()")->fetch_assoc()['total'] ?? 0;




$stockOutQuery = "
    SELECT COALESCE(SUM(quantity), 0) AS total_out
    FROM (
        -- Stock Transactions (e.g., manual 'out' transactions)
        SELECT quantity FROM stock_transactions WHERE type='out' AND DATE(date) = CURDATE()
        UNION ALL
        -- Sales Transactions
        SELECT quantity FROM sales WHERE DATE(sale_date) = CURDATE()
        UNION ALL
        -- Damage Items
        SELECT damage_quantity as quantity FROM damage_items WHERE DATE(date_reported) = CURDATE()
        UNION ALL
        -- Expired Items
        SELECT expired_quantity as quantity FROM expired_items WHERE DATE(date_reported) = CURDATE()
    ) AS all_stock_out_today
";
$totalStockOutToday = $conn->query($stockOutQuery)->fetch_assoc()['total_out'] ?? 0;



// Today's sales
$totalSales = $conn->query("SELECT SUM(total_amount) AS total FROM sales WHERE DATE(sale_date) = CURDATE()")->fetch_assoc()['total'] ?? 0;
$totalSalesCount = $conn->query("SELECT COUNT(*) AS count FROM sales WHERE DATE(sale_date) = CURDATE()")->fetch_assoc()['count'] ?? 0;


$lowStockRes = $conn->query("
    SELECT i.*, c.name AS category_name 
    FROM items i 
    LEFT JOIN categories c ON i.category_id = c.id 
    WHERE i.quantity <= i.low_stock_threshold 
    ORDER BY 
        (i.quantity * 1.0 / i.low_stock_threshold) ASC, 
        i.quantity ASC
");
$lowStockItems = [];
while ($r = $lowStockRes->fetch_assoc()) {
    $lowStockItems[] = $r;
}
$lowStockCount = count($lowStockItems);




// Today's Top Selling Products (by quantity)
$todayTopProducts = $conn->query("
    SELECT name, SUM(quantity) as items_sold_today, SUM(total_amount) as revenue_today
    FROM sales
    WHERE DATE(sale_date) = CURDATE()
    GROUP BY name
    ORDER BY items_sold_today DESC
    LIMIT 5
");


$recentSales = $conn->query("
    SELECT s.transaction_id, s.barcode,
           COALESCE(NULLIF(s.name,'0'), i.name, cb.name) AS product_name,
           s.total_amount, s.sale_date
    FROM sales s
    LEFT JOIN items i ON s.barcode = i.barcode
    LEFT JOIN custom_barcodes cb ON s.barcode = cb.barcode
    WHERE DATE(s.sale_date) = CURDATE()
    ORDER BY s.sale_date DESC
    LIMIT 5
");


$unsold = $conn->query("
    SELECT i.name, i.quantity
    FROM items i
    WHERE i.id NOT IN (
        SELECT DISTINCT id
        FROM sales
        WHERE sale_date >= NOW() - INTERVAL 2 DAY
    )
    ORDER BY i.quantity DESC
    LIMIT 3
");



$latestProduct = $conn->query("
    SELECT name, retail_price, quantity, barcode
    FROM items
    ORDER BY id DESC
    LIMIT 1
");


function formatTime($datetime) {
    return date('h:i A', strtotime($datetime));
}


$expiringSoonDays = 30;
$today = new DateTime();


$expiringItemsQuery = "
    SELECT i.*, c.name AS category_name,
    DATEDIFF(i.expiration_date, CURDATE()) as days_until_expiry
    FROM items i 
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.expiration_date IS NOT NULL 
    AND (
        (i.expiration_date <= CURDATE()) OR 
        (DATEDIFF(i.expiration_date, CURDATE()) <= $expiringSoonDays)
    )
    ORDER BY i.expiration_date ASC";

$expiringItems = $conn->query($expiringItemsQuery)->fetch_all(MYSQLI_ASSOC);
$expiringCount = count($expiringItems);

?>
<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard - Che-Che MiniMart</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
<script src="scripts/dashboard.js"></script>
    <script>
 
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f5f7ff',
                            100: '#eef2ff',
                            200: '#e0e7ff',
                            300: '#c7d2fe',
                            400: '#a78bfa',
                            500: '#7c3aed',
                            600: '#6d28d9',
                            700: '#5b21b6'
                        },
                        muted: {
                            50: '#f9fafb',
                            100: '#f3f4f6',
                            200: '#e5e7eb',
                            300: '#d1d5db',
                            400: '#9ca3af'
                        }
                    },
                    boxShadow: {
                        'card': '0 6px 20px rgba(16,24,40,0.06)',
                        'card-strong': '0 10px 30px rgba(16,24,40,0.08)'
                    }
                }
            }
        }
    </script>

    <style>
        html, body { font-family: 'Inter', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; }
        /* custom scrollbar */
        .scrollable::-webkit-scrollbar { height: 8px; width: 8px; }
        .scrollable::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.15); border-radius: 6px; }
        .card-header { background: linear-gradient(90deg, rgba(124,58,237,0.06), rgba(124,58,237,0.02)); }
        /* keep table headers visible and neat */
        thead.sticky { position: sticky; top: 0; z-index: 10; backdrop-filter: blur(4px); }
        /* compact responsive table */
        .table-fixed-layout th, .table-fixed-layout td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Notification animations */
        .notify-glow { box-shadow: 0 6px 24px rgba(252, 211, 77, 0.22); }
        .exclamation-badge {
            animation: pulse 1.6s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.15); opacity: .8; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* header background */
        .header-bg {
            background: linear-gradient(90deg, rgba(124,58,237,0.06), rgba(19, 19, 19, 0.7));
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 6px 18px rgba(15,23,42,0.04);
            backdrop-filter: blur(6px) saturate(110%);
        }

      
.hover-card {
    display: none;
    position: absolute;
    top: 100%;
    margin-top: 0.5rem;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}


.notification-hover {
    z-index: 1000; 
}

.stats-hover {
    z-index: 100; 
}

.stats-card {
    position: relative;
}

.stats-card:hover .hover-card {
    display: block;
}


.header-notif-container {
    position: relative;
    z-index: 1000;
}


#notifPanel {
    display: none;
}
    </style>
</head>
<body class="bg-white text-slate-800 min-h-screen">

    <?php include 'sidebar.php'; ?>

    <main class="transition-all duration-300 lg:ml-[256px] p-6 lg:p-10">
      <header class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8 header-bg">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">
            Welcome back, 
            <span class="font-medium text-slate-700">
                <?= htmlspecialchars($_SESSION['username']) ?>
            </span>
        </p>
    </div>

    <div class="flex items-center gap-3">
        <a href="sales_report.php"
           class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white text-sm font-medium rounded-lg shadow-card hover:shadow-card-strong transition">
            Reports
        </a>
    </div>
</header>

       <section class="mb-6">
    <div class="stats-card inline-block">
        <button id="notifBtn" 
                class="relative inline-flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span class="text-xs text-slate-500">Notifications</span>

            <?php if ($lowStockCount > 0): ?>
                <span class="absolute -top-2 -right-2 flex items-center justify-center h-6 w-6 bg-rose-600 text-white text-xs font-semibold rounded-full notify-glow">
                    <?= $lowStockCount ?>
                    <span class="absolute -top-3 -right-3 bg-amber-400 text-xxs text-amber-900 font-bold rounded-full h-5 w-5 flex items-center justify-center exclamation-badge">!</span>
                </span>
            <?php endif; ?>
        </button>

        <!-- Low Stock Hover Card -->
        <div class="hover-card" style="width: 320px; left: 0; transform: none;">
            <div class="p-4 border-b border-slate-100">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-800">Notifications</h4>
                    <div class="text-xs">
                        <span class="text-rose-600"><?= $lowStockCount ?> low stock</span>
                        <span class="mx-1">·</span>
                        <span class="text-amber-600"><?= $expiringCount ?> expiring</span>
                    </div>
                </div>
            </div>
            <div class="max-h-[400px] overflow-y-auto">
                <?php if ($lowStockCount > 0 || $expiringCount > 0): ?>
                    <!-- Low Stock Section -->
                    <?php if ($lowStockCount > 0): ?>
                        <div class="p-2 bg-rose-50">
                            <h5 class="text-xs font-semibold text-rose-800 px-2">Low Stock Items</h5>
                        </div>
                        <div class="mb-4">
                            <?php foreach ($lowStockItems as $item): ?>
                                <div class="px-4 py-2 hover:bg-slate-50 flex justify-between items-center">
                                    <span class="text-sm text-slate-800"><?= htmlspecialchars($item['name']) ?></span>
                                    <span class="text-xs font-medium text-rose-600">
                                        <?= $item['quantity'] ?> left (min: <?= $item['low_stock_threshold'] ?>)
                                         </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Expiring Items Section -->
                    <?php if ($expiringCount > 0): ?>
                        <div class="p-2 bg-amber-50">
                            <h5 class="text-xs font-semibold text-amber-800 px-2">Expiring/Expired Items</h5>
                        </div>
                        <div>
                            <?php foreach ($expiringItems as $item): 
                                $daysLeft = $item['days_until_expiry'];
                                $statusClass = $daysLeft <= 0 ? 'text-red-600' : 'text-amber-600';
                                $status = $daysLeft <= 0 ? 'Expired' : "$daysLeft days left";
                            ?>
                                <div class="px-4 py-2 hover:bg-slate-50 flex justify-between items-center">
                                    <span class="text-sm text-slate-800"><?= htmlspecialchars($item['name']) ?></span>
                                    <span class="text-xs font-medium <?= $statusClass ?>">
                                        <?= $status ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-sm text-emerald-600">No alerts at this time.</div>
                <?php endif; ?>
            </div>
            <div class="p-3 border-t border-slate-100 text-center">
                <a href="items.php" class="text-xs text-violet-600 hover:underline">View inventory</a>
            </div>
        </div>
    </div>
</section>
<h2 class="text-lg font-semibold text-slate-800 mb-4">Overview</h2>
<!-- Then your existing stats grid section starts here -->
<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 shadow-card border border-slate-100">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500">Total Items</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900"><?= htmlspecialchars($totalItems) ?></p>
            </div>
            <div class="bg-brand-50 text-brand-600 rounded-full p-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a1 1 0 001 1h16a1 1 0 001-1V7M3 7l9-4 9 4M12 3v18" />
                </svg>
            </div>
        </div>
    </div>



  <div class="stats-card relative bg-white rounded-2xl p-5 shadow-card border border-slate-100">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500">Stock In Today</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900"><?= htmlspecialchars($totalStockInToday) ?></p>
            </div>
            <div class="bg-emerald-50 text-emerald-600 rounded-full p-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20V4m0 0l4 4m-4-4l-4 4" />
                </svg>
            </div>
        </div>
        <div class="hover-card stats-hover">
    <div class="p-4 border-b border-slate-100">
        <h4 class="font-medium text-sm">Today's Stock In Items</h4>
    </div>
    <div class="p-2">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-2 py-1 text-left text-xs text-slate-500 w-1/2">Product Name</th>
                    <th class="px-2 py-1 text-right text-xs text-slate-500 w-1/4">Quantity</th>
                    <th class="px-2 py-1 text-right text-xs text-slate-500 w-1/4">Time</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $todayStockIn = $conn->query("
                    SELECT i.name, st.quantity, st.date 
                    FROM stock_transactions st 
                    JOIN items i ON st.item_id = i.id 
                    WHERE st.type='in' AND DATE(st.date) = CURDATE()
                    ORDER BY st.date DESC LIMIT 4
                ");
                if ($todayStockIn->num_rows > 0):
                    while ($item = $todayStockIn->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-2 py-2 text-slate-600"><span class="hover-item-name"><?= htmlspecialchars($item['name']) ?></span></td>
                            <td class="px-2 py-2 text-right font-medium text-emerald-600">+<?= htmlspecialchars($item['quantity']) ?></td>
                            <td class="px-2 py-2 text-right text-slate-500 text-xs"><?= date('h:i A', strtotime($item['date'])) ?></td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr><td colspan="3" class="p-4 text-center text-slate-500">No items added today</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-slate-100 text-center">
        <a href="recent_activity.php" class="text-xs text-emerald-600 hover:underline">View All Stock In Records</a>
    </div>
</div>
    </div>



   <div class="stats-card relative bg-white rounded-2xl p-5 shadow-card border border-slate-100">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500">Stock Out Today</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900"><?= htmlspecialchars($totalStockOutToday) ?></p>
            </div>
            <div class="bg-rose-50 text-rose-600 rounded-full p-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m0 0l-4-4m4 4l4-4" />
                </svg>
            </div>
        </div>

        <div class="hover-card stats-hover">
    <div class="p-4 border-b border-slate-100">
        <h4 class="font-medium text-sm">Today's Stock Out Items</h4>
    </div>
    <div class="p-2">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-2 py-1 text-left text-xs text-slate-500 w-1/3">Product Name</th>
                    <th class="px-2 py-1 text-right text-xs text-slate-500 w-1/6">Qty</th>
                    <th class="px-2 py-1 text-center text-xs text-slate-500 w-1/4">Reason</th>
                    <th class="px-2 py-1 text-right text-xs text-slate-500 w-1/4">Time</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $todayStockOut = $conn->query("
                    SELECT 
                        item_name,
                        quantity,
                        reason,
                        activity_date
                    FROM (
                        SELECT 
                            i.name as item_name,
                            di.damage_quantity as quantity,
                            'Damaged' as reason,
                            di.date_reported as activity_date
                        FROM damage_items di
                        JOIN items i ON di.item_id = i.id
                        WHERE DATE(di.date_reported) = CURDATE()
                        
                        UNION ALL
                        
                        SELECT 
                            i.name as item_name,
                            ei.expired_quantity as quantity,
                            'Expired' as reason,
                            ei.date_reported as activity_date
                        FROM expired_items ei
                        JOIN items i ON ei.item_id = i.id
                        WHERE DATE(ei.date_reported) = CURDATE()
                        
                        UNION ALL
                        
                        SELECT 
                            name as item_name,
                            quantity,
                            'Sold' as reason,
                            sale_date as activity_date
                        FROM sales
                        WHERE DATE(sale_date) = CURDATE()
                    ) AS combined_stock_out
                    ORDER BY activity_date DESC
                    LIMIT 4
                ");
                
                if ($todayStockOut->num_rows > 0):
                    while ($item = $todayStockOut->fetch_assoc()): 
                        $reasonColor = match($item['reason']) {
                            'Damaged' => 'text-red-600',
                            'Expired' => 'text-amber-600',
                            'Sold' => 'text-emerald-600',
                            default => 'text-slate-600'
                        };
                    ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-2 py-2 text-slate-600"><span class="hover-item-name max-w-[100px]"><?= htmlspecialchars($item['item_name']) ?></span></td>
                            <td class="px-2 py-2 text-right font-medium text-rose-600">-<?= htmlspecialchars($item['quantity']) ?></td>
                            <td class="px-2 py-2 text-center">
                                <span class="text-xs <?= $reasonColor ?> px-2 py-1 rounded-full bg-slate-100 whitespace-nowrap">
                                    <?= htmlspecialchars($item['reason']) ?>
                                </span>
                            </td>
                            <td class="px-2 py-2 text-right text-slate-500 text-xs"><?= date('h:i A', strtotime($item['activity_date'])) ?></td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr><td colspan="4" class="p-4 text-center text-slate-500">No items removed today</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-slate-100 text-center">
        <a href="recent_activity.php" class="text-xs text-rose-600 hover:underline">View All Stock Out Records</a>
    </div>
</div>
    </div>




   <div class="stats-card relative bg-white rounded-2xl p-5 shadow-card border border-slate-100">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500">Sales Today</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900">₱<?= number_format($totalSales, 2) ?></p>
                <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($totalSalesCount) ?> transactions</p>
            </div>
            <div class="bg-amber-50 text-amber-600 rounded-full p-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        <div class="hover-card stats-hover">
    <div class="p-4 border-b border-slate-100">
        <h4 class="font-medium text-sm">Today's Recent Sales</h4>
    </div>
    <div class="p-2">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-2 py-1 text-left text-xs text-slate-500 w-1/3">Product Name</th>
                    <th class="px-2 py-1 text-right text-xs text-slate-500 w-1/6">Qty</th>
                    <th class="px-2 py-1 text-right text-xs text-slate-500 w-1/4">Amount</th>
                    <th class="px-2 py-1 text-right text-xs text-slate-500 w-1/4">Time</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $todaySales = $conn->query("
                    SELECT name, quantity, total_amount, sale_date 
                    FROM sales 
                    WHERE DATE(sale_date) = CURDATE()
                    ORDER BY sale_date DESC 
                    LIMIT 4
                ");
                if ($todaySales->num_rows > 0):
                    while ($sale = $todaySales->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-2 py-2 text-slate-600"><span class="hover-item-name max-w-[100px]"><?= htmlspecialchars($sale['name']) ?></span></td>
                            <td class="px-2 py-2 text-right text-slate-600"><?= htmlspecialchars($sale['quantity']) ?></td>
                            <td class="px-2 py-2 text-right font-medium text-slate-900">₱<?= number_format($sale['total_amount'], 2) ?></td>
                            <td class="px-2 py-2 text-right text-slate-500 text-xs"><?= date('h:i A', strtotime($sale['sale_date'])) ?></td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr><td colspan="4" class="p-4 text-center text-slate-500">No sales recorded today</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-slate-100 text-center">
        <a href="recent_activity.php" class="text-xs text-amber-600 hover:underline">View All Sales Records</a>
    </div>
</div>
    </div>
</section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="space-y-6 lg:col-span-1">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-card overflow-hidden">
                    <div class="px-6 py-4 card-header border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Latest Added Product</h3>
                    </div>
                    <div class="p-4">
                        <?php if ($latestProduct->num_rows > 0): 
                            $prod = $latestProduct->fetch_assoc(); ?>
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($prod['name']) ?></div>
                                    <div class="text-xs text-slate-500 mt-1">Barcode: <span class="font-medium text-slate-600"><?= htmlspecialchars($prod['barcode']) ?></span></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-slate-900">₱<?= number_format($prod['retail_price'], 2) ?></div>
                                    <div class="text-xs text-slate-500 mt-1">Stock: <span class="font-medium text-slate-700"><?= htmlspecialchars($prod['quantity']) ?></span></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-slate-500">No products found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="lowstock" class="bg-white rounded-2xl border border-slate-100 shadow-card overflow-hidden">
                    <div class="px-6 py-4 card-header border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Items for Reorder</h3>
                    </div>
                    <div class="p-4">
                        <?php if ($lowStockCount > 0): ?>
                            <table class="w-full table-fixed-layout text-sm">
                                <thead class="sticky top-0 bg-white">
                                    <tr>
                                        <th class="text-left px-4 py-3 text-xs text-slate-500">Item</th>
                                        <th class="text-right px-4 py-3 text-xs text-slate-500">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockItems as $item): ?>
                                    <tr class="border-t border-slate-100 hover:bg-slate-50">
                                        <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($item['name']) ?></td>
                                        <td class="px-4 py-3 text-right font-semibold text-rose-600"><?= htmlspecialchars($item['quantity']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="p-4 text-center text-sm text-emerald-600">All items are sufficiently stocked.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-card overflow-hidden">
                    <div class="px-6 py-4 card-header border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Today's Top Selling Products</h3>
                    </div>
                    <div class="p-4">
                        <div class="scrollable max-h-[260px] overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-white">
                                    <tr>
                                        <th class="text-left px-4 py-3 text-xs text-slate-500">Product</th>
                                        <th class="text-right px-4 py-3 text-xs text-slate-500">Qty Sold</th>
                                        <th class="text-right px-4 py-3 text-xs text-slate-500">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($todayTopProducts->num_rows > 0): ?>
                                        <?php while($prod = $todayTopProducts->fetch_assoc()): ?>
                                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                                            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($prod['name']) ?></td>
                                            <td class="px-4 py-3 text-right"><?= htmlspecialchars($prod['items_sold_today']) ?></td>
                                            <td class="px-4 py-3 text-right font-semibold text-slate-900">₱<?= number_format($prod['revenue_today'], 2) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="p-4 text-center text-slate-500">No sales recorded today.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                    <div class="bg-white rounded-2xl border border-slate-100 shadow-card overflow-hidden">
                    <div class="px-6 py-4 card-header border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-800">Recent Individual Sales (Today)</h3>
                    </div>
                    <div class="p-4">
                        <div class="scrollable max-h-[260px] overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-white">
                                    <tr>
                                        <th class="text-left px-4 py-3 text-xs text-slate-500">Transaction ID</th>
                                        <th class="text-left px-4 py-3 text-xs text-slate-500">Product</th>
                                        <th class="text-right px-4 py-3 text-xs text-slate-500">Total</th>
                                        <th class="text-right px-4 py-3 text-xs text-slate-500">Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recentSales->num_rows > 0): ?>
                                        <?php while($sale = $recentSales->fetch_assoc()): ?>
                                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                                            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($sale['transaction_id'] ?? '#'. $sale['barcode']) ?></td>
                                            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($sale['product_name'] ?: ($sale['barcode'] ?: 'Unknown')) ?></td>
                                            <td class="px-4 py-3 text-right font-semibold text-slate-900">₱<?= number_format($sale['total_amount'], 2) ?></td>
                                            <td class="px-4 py-3 text-right text-slate-500"><?= formatTime($sale['sale_date']) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="p-4 text-center text-slate-500">No sales recorded today.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </main>
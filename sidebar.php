<?php
// Function to check if the current page is active
function isActive($pageName) {
    return basename($_SERVER['PHP_SELF']) == $pageName ? 'bg-sky-600 text-white' : 'text-slate-200 hover:bg-slate-800';
}

// --- DB & low-stock check ---
require 'db.php';

$lowStockThreshold = 5;
$lowStockCount = 0;

if (isset($conn)) {
    $sql = "SELECT COUNT(*) AS low_count FROM items WHERE quantity <= ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $lowStockThreshold);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $lowStockCount = (int)$row['low_count'];
        }
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) AS low_count FROM items WHERE quantity <= $lowStockThreshold");
        if ($result && $row = $result->fetch_assoc()) {
            $lowStockCount = (int)$row['low_count'];
        }
    }
}
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.tailwindcss.com"></script>

<style>
@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.8); }
    50% { box-shadow: 0 0 10px 4px rgba(239, 68, 68, 0.45); }
}
.pulse-glow {
    animation: pulse-glow 1.5s infinite;
}
.bi {
    font-size: 1.25rem;
}
</style>

<!-- Mobile Toggle Button -->
<div class="md:hidden fixed top-4 left-4 z-50">
    <button id="sidebarToggle" class="p-2 bg-slate-900 text-white rounded-lg shadow-lg focus:outline-none">
        <i class="bi bi-list text-2xl"></i>
    </button>
</div>

<!-- Overlay for mobile (background dim when sidebar open) -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40 md:hidden"></div>

<!-- Sidebar -->
<aside id="sidebar" class="fixed top-0 left-0 w-64 h-full bg-slate-900 text-slate-100 p-6 flex flex-col justify-between shadow-xl transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-[60]">

    <!-- Sidebar Header -->
    <div>
        <div class="flex items-center mb-8 pb-4 border-b border-slate-700">
            <i class="bi bi-shop text-amber-400 text-3xl"></i>
            <h3 class="text-2xl font-bold ml-2 text-slate-50">Che-Che MiniMart</h3>
        </div>

        <!-- Navigation Links -->
        <nav>
            <ul class="space-y-3">
                <li>
                    <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-xl <?= isActive('dashboard.php') ?>">
                        <i class="bi bi-speedometer2 text-xl text-sky-400"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="sales_report.php" class="flex items-center space-x-3 p-3 rounded-xl <?= isActive('sales_report.php') ?>">
                        <i class="bi bi-bar-chart-line-fill text-xl text-violet-600"></i>
                        <span>Sales Report</span>
                    </a>
                </li>
                <li>
                    <a href="items.php" class="relative flex items-center space-x-3 p-3 rounded-xl <?= isActive('items.php') ?>">
                        <i class="bi bi-list-ul text-xl text-indigo-400"></i>
                        <span>Inventory</span>
                        <?php if ($lowStockCount > 0): ?>
                            <span class="absolute right-3 top-3 inline-flex h-3 w-3 rounded-full bg-red-500 pulse-glow" title="<?= $lowStockCount ?> low stock item(s)"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="recent_activity.php" class="flex items-center space-x-3 p-3 rounded-xl <?= isActive('recent_activity.php') ?>">
                        <i class="bi bi-clock-history text-xl text-sky-400"></i>
                        <span>Recent Activity</span>
                    </a>
                </li>
                
                <li>
                    <a href="purchase.php" class="flex items-center space-x-3 p-3 rounded-xl <?= isActive('purchase.php') ?>">
                        <i class="bi bi-cart-check text-xl text-amber-400"></i>
                        <span>Product Sales</span>
                    </a>
                </li>
                <li>
                    <a href="add_item.php" class="flex items-center space-x-3 p-3 rounded-xl <?= isActive('add_item.php') ?>">
                        <i class="bi bi-plus-circle text-xl text-slate-400"></i>
                        <span>Add New Item</span>
                    </a>
                </li>
                <li>
                    <a href="generate_barcode.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-amber-100 hover:text-amber-700 <?= isActive('generate_barcode.php') ?>">
                        <i class="bi bi-upc-scan text-xl text-slate-400"></i>
                        <span>Custom Barcodes</span>
                    </a>
                </li>
                <li>
                    <a href="item_report.php" class="flex items-center space-x-3 p-3 rounded-xl <?= isActive('item_report.php') ?>">
                        <i class="bi bi-graph-up text-xl text-green-400"></i>
                        <span>Item / Product Report</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Logout -->
    <div class="p-3">
        <a href="logout.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-red-600 text-red-400 hover:text-white">
            <i class="bi bi-box-arrow-right text-xl"></i>
            <span>Log Out</span>
        </a>
    </div>
</aside>

<!-- Sidebar Toggle Script -->
<script>
document.getElementById('sidebarToggle').addEventListener('click', function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    const isOpen = !sidebar.classList.contains('-translate-x-full');
    if (isOpen) {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    } else {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    }
});

// Hide sidebar when overlay is clicked
document.getElementById('sidebarOverlay').addEventListener('click', function () {
    document.getElementById('sidebar').classList.add('-translate-x-full');
    this.classList.add('hidden');
});
</script>

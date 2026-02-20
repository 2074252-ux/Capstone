<?php
session_start();
require 'db.php';
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$lowStockThreshold = 5;
$expiringSoonDays = 30;
$today = new DateTime();

// changed ordering: group by barcode then expiration so duplicate barcodes appear together
$query = "
    SELECT i.*, c.name AS category_name, c.id AS category_id,
           i.date_added,
           (SELECT MAX(st.date) 
            FROM stock_transactions st 
            WHERE st.item_id = i.id AND st.type = 'in') as last_stock_in
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    ORDER BY i.date_added DESC
";
$result = $conn->query($query);

$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = [];
while ($cat = $categories_result->fetch_assoc()) {
    $categories[] = $cat;
}

$status_message = $_SESSION['status_message'] ?? null;
$status_type = $_SESSION['status_type'] ?? 'success';
unset($_SESSION['status_message']);
unset($_SESSION['status_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Items - Che-Che MiniMart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .scrollable::-webkit-scrollbar { width: 8px; height: 8px; }
        .scrollable::-webkit-scrollbar-track { background: #f1f5f9; }
        .scrollable::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 4px; }
        .scrollable::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }

        /* header background */
        .page-header {
            background: linear-gradient(90deg, rgba(124,58,237,0.06), rgba(255,255,255,0.85));
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 30px rgba(15,23,42,0.04);
            backdrop-filter: blur(6px) saturate(110%);
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen font-sans antialiased text-slate-800 flex">
    <?php include 'sidebar.php'; ?>

    <div id="app" data-lowstock="<?= (int)$lowStockThreshold ?>" class="flex-1 p-8 ml-[256px] transition-all duration-300">
        <!-- Header -->
        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div class="page-header flex-1">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">📦 Items / Products</h1>
                        <p class="mt-1 text-sm text-slate-500">Manage inventory, stock movements and expirations</p>
                    </div>
                    <div class="hidden sm:flex items-center gap-3">
                        <a href="add_item.php" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 text-white rounded-lg shadow hover:bg-amber-600 transition">+ Add Item</a>
                       
                    </div>
                </div>
            </div>
        </header>

        <!-- Status Message -->
        <?php if ($status_message): ?>
            <?php
            $bg_class = ($status_type === 'success') ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
            $icon = ($status_type === 'success') ? '✅' : '❌';
            ?>
            <div class="<?= $bg_class ?> border px-4 py-3 rounded-xl mb-6 shadow-md" role="alert">
                <p class="font-bold inline-block mr-2"><?= $icon ?></p>
                <span class="block sm:inline"><?= htmlspecialchars($status_message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Search + Filters + Stock actions -->
        <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                <div class="flex-1">
                    <input type="text" id="searchInput" class="w-full p-3 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-sky-500" placeholder="🔍 Search by name or category...">
                </div>

                <div class="flex gap-3 items-center">
                    <select id="stockFilterDropdown" class="p-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-sky-500">
                        <option value="all">All Stock</option>
                        <option value="low_stock">Low Stock</option>
                        <option value="in_stock">In Stock</option>
                    </select>

                    <select id="categoryFilterDropdown" class="p-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-sky-500">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="expirationFilterDropdown" class="p-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-sky-500">
                        <option value="all">All Expirations</option>
                        <option value="expired">Expired</option>
                        <option value="expiring_soon">Expiring Soon (<?= $expiringSoonDays ?> days)</option>
                        <option value="valid">Valid (No Expiry)</option>
                    </select>

                    <select id="sortDropdown" class="p-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-sky-500">
                        <option value="">Sort By...</option>
                        <option value="latest_added">Latest Added Items</option>
                        <option value="latest_stock">Latest Stock In</option>
                        <option value="name_asc">Name (A-Z)</option>
                        <option value="name_desc">Name (Z-A)</option>
                        <option value="quantity_asc">Quantity (Low to High)</option>
                        <option value="quantity_desc">Quantity (High to Low)</option>
                        <option value="price_asc">Price (Low to High)</option>
                        <option value="price_desc">Price (High to Low)</option>
                        <option value="expires_asc">Expires (Soonest First)</option>
                        <option value="expires_desc">Expires (Latest First)</option>
                    </select>

                    <!-- Stock In/Out compact buttons -->
                    <div class="flex items-center gap-2">
                        <button id="openStockIn" class="px-3 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition">Stock In</button>
                        <button id="openStockOut" class="px-3 py-2 bg-rose-600 text-white rounded-lg text-sm hover:bg-rose-700 transition">Stock Out</button>
                    </div>
                </div>
            </div>

            <!-- Item View Toggle Buttons -->
            <div class="flex gap-3 items-center mb-4">
                <button type="button" class="table-view-btn active px-4 py-2 bg-amber-500 text-white rounded-lg" data-table="items">
                    Regular Items
                </button>
                <button type="button" class="table-view-btn px-4 py-2 bg-slate-100 text-slate-700 rounded-lg" data-table="damage">
                    Damaged Items
                </button>
                <button type="button" class="table-view-btn px-4 py-2 bg-slate-100 text-slate-700 rounded-lg" data-table="expired">
                    Expired Items
                </button>
            </div>
        </div>

        <!-- Items Table -->
        <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-0 overflow-hidden">
            <div class="scrollable max-h-[calc(100vh-320px)] overflow-y-auto">
                <table class="w-full text-left text-sm text-slate-900 border-collapse" id="itemsTable">
                    <thead class="sticky top-0 bg-slate-100 text-slate-900 font-medium border-b border-slate-300">
                    <tr>
                        <th class="p-4">Barcode</th>
                        <th class="p-4">Name</th>
                        <th class="p-4">Category</th>
                        <th class="p-4">Quantity</th>
                        <th class="p-4">Supplier Price</th>
                        <th class="p-4">Selling Price</th>
                        <th class="p-4">Expires In</th>
                        <th class="p-4">Date Added</th>
                    </tr>
                </thead>
                    <tbody id="itemsTbody">
                        <?php while($row = $result->fetch_assoc()):
                            $expDate = $row['expiration_date'] ? new DateTime($row['expiration_date']) : null;
                            $daysLeft = $expDate ? (int)$today->diff($expDate)->format('%r%a') : null;

                            $expClass = 'text-slate-600';
                            $expirationStatus = 'valid';
                            if ($expDate && $daysLeft < 0) {
                                $expClass = 'font-semibold text-white bg-red-500 px-2 py-0.5 rounded-full';
                                $expirationStatus = 'expired';
                            } elseif ($expDate && $daysLeft <= $expiringSoonDays) {
                                $expClass = 'font-semibold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full';
                                $expirationStatus = 'expiring_soon';
                            }

                            // prepare data attributes
                            $tr_name = htmlspecialchars($row['name'], ENT_QUOTES);
                            $tr_category = htmlspecialchars($row['category_name'], ENT_QUOTES);
                            $tr_quantity = (int)$row['quantity'];
                            $tr_wholesale = (float)$row['wholesale_price'];
                            $tr_retail = (float)$row['retail_price'];
                            $tr_exp_status = $expirationStatus;
                        ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors"
                            data-quantity="<?= $tr_quantity ?>"
                            data-category="<?= $tr_category ?>"
                            data-expiration-status="<?= $tr_exp_status ?>"
                            data-name="<?= $tr_name ?>"
                            data-wholesale="<?= $tr_wholesale ?>"
                            data-retail="<?= $tr_retail ?>"
                            data-barcode="<?= htmlspecialchars($row['barcode']) ?>"
                            data-id="<?= htmlspecialchars($row['id']) ?>"
                            data-date-added="<?= htmlspecialchars($row['date_added']) ?>"
                            data-last-stock-in="<?= htmlspecialchars($row['last_stock_in']) ?>">
                            <td class="p-4 text-slate-900"><?= htmlspecialchars($row['barcode']) ?></td>
                            <td class="p-4 font-medium text-slate-900"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="p-4 text-slate-900"><?= htmlspecialchars($row['category_name']) ?></td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <?php if ($row['quantity'] <= $row['low_stock_threshold']): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                            Low (<?= $row['quantity'] ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            <?= $row['quantity'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <button onclick="editThreshold(this)" 
                                            class="text-xs text-slate-900 hover:text-violet-600 transition-colors"
                                            data-id="<?= $row['id'] ?>"
                                            data-name="<?= htmlspecialchars($row['name']) ?>"
                                            data-threshold="<?= $row['low_stock_threshold'] ?>">
                                        (Alert at <?= $row['low_stock_threshold'] ?>)
                                    </button>
                                </div>
                            </td>
                            <td class="p-4 text-slate-900">₱<?= number_format($row['wholesale_price'] ?? 0, 2) ?></td>
                            <td class="p-4 text-slate-900">₱<?= number_format($row['retail_price'] ?? 0, 2) ?></td>
                            <td class="p-4 text-slate-900">
                                <?php if ($expDate && ($daysLeft < 0 || $daysLeft <= $expiringSoonDays)): ?>
                                    <button onclick="openStockOutForExpired(this)" 
                                            class="<?= $expClass ?> px-2 py-1 rounded cursor-pointer hover:opacity-75 transition-opacity"
                                            data-item-id="<?= htmlspecialchars($row['id']) ?>"
                                            data-barcode="<?= htmlspecialchars($row['barcode']) ?>"
                                            data-name="<?= htmlspecialchars($row['name']) ?>"
                                            data-expiry="<?= $expDate ? $expDate->format('Y-m-d') : '' ?>"
                                            data-quantity="<?= htmlspecialchars($row['quantity']) ?>">
                                        <?= $expDate ? $expDate->format('Y-m-d') : '—' ?>
                                    </button>
                                <?php else: ?>
                                    <span class="<?= $expClass ?>"><?= $expDate ? $expDate->format('Y-m-d') : '—' ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-slate-900">
                                <?= date('M d, Y', strtotime($row['date_added'])) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- include stock modals (moved to modals folder) -->
    <?php include __DIR__ . '/modals/stock_in_modal.php'; ?>
    <?php include __DIR__ . '/modals/stock_out_modal.php'; ?>
    <?php include __DIR__ . '/modals/threshold_modal.php'; ?>

    

    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
        <form action="update_item.php" method="POST">
          <div class="flex justify-between items-center p-5 border-b border-gray-200">
            <h5 class="text-xl font-semibold text-slate-800">Edit Item</h5>
            <button type="button" class="closeModal text-gray-400 hover:text-gray-600">&times;</button>
          </div>
          <div class="p-6 space-y-4">
            <input type="hidden" name="id" id="edit_id">
            <label class="block text-sm font-medium text-gray-700">Barcode:</label>
            <input type="text" name="barcode" id="edit_barcode" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">
            
            <label class="block text-sm font-medium text-gray-700 mt-3">Name:</label>
            <input type="text" name="name" id="edit_name" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">
            
            <label class="block text-sm font-medium text-gray-700 mt-3">Category:</label>
            <select name="category_id" id="edit_category_id" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>

            <label class="block text-sm font-medium text-gray-700 mt-3">Quantity:</label>
            <input type="number" name="quantity" id="edit_quantity" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">

            <div class="grid grid-cols-2 gap-3 mt-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">Wholesale Price:</label>
                <input type="number" step="0.01" name="wholesale_price" id="edit_wholesale_price" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Retail Price:</label>
                <input type="number" step="0.01" name="retail_price" id="edit_retail_price" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">
              </div>
            </div>

            <label class="block text-sm font-medium text-gray-700 mt-3">Expiration Date:</label>
            <input type="date" name="expiration_date" id="edit_expiration_date" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">
          </div>
          <div class="flex justify-end p-5 border-t border-gray-200">
            <button type="submit" class="px-4 py-2 rounded-full bg-sky-500 text-white hover:bg-sky-600 mr-2">Save</button>
            <button type="button" class="closeModal px-4 py-2 rounded-full bg-gray-200 text-gray-700 hover:bg-gray-300">Cancel</button>
          </div>
        </form>
      </div>
    </div>


    <!-- load the moved scripts -->
    <script src="scripts/items.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', () => {
    const viewBtns = document.querySelectorAll('.table-view-btn');
    const itemsTable = document.querySelector('#itemsTable');
    
    viewBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            viewBtns.forEach(b => {
                b.classList.remove('active', 'bg-amber-500', 'text-white');
                b.classList.add('bg-slate-100', 'text-slate-700');
            });
            
            btn.classList.add('active', 'bg-amber-500', 'text-white');
            btn.classList.remove('bg-slate-100', 'text-slate-700');
            
            // Fetch and display appropriate table
            fetch(`get_items_table.php?type=${btn.dataset.table}`)
                .then(res => res.text())
                .then(html => {
                    itemsTable.innerHTML = html;
                });
        });
    });
});
</script>
</body>
</html>
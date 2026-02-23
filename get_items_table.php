<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    exit('Unauthorized');
}

$type = $_GET['type'] ?? 'items';

// Prepare the table header based on type
switch($type) {
    case 'items':
        echo '<thead class="bg-slate-100">
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
              <tbody>';

        $query = "SELECT i.*, c.name as category_name, (SELECT MAX(st.date) FROM stock_transactions st WHERE st.item_id = i.id AND st.type = 'in') as last_stock_in FROM items i LEFT JOIN categories c ON i.category_id = c.id ORDER BY i.date_added DESC";
        $result = $conn->query($query);
        $today = new DateTime();

        while($row = $result->fetch_assoc()) {
            $expDate = $row['expiration_date'] ? new DateTime($row['expiration_date']) : null;
            $daysLeft = $expDate ? (int)$today->diff($expDate)->format('%r%a') : null;

            $expClass = 'text-slate-600';
            $expirationStatus = 'valid';
            if ($expDate && $daysLeft < 0) {
                $expClass = 'font-semibold text-white bg-red-500 px-2 py-0.5 rounded-full';
                $expirationStatus = 'expired';
            } elseif ($expDate && $daysLeft <= 30) {
                $expClass = 'font-semibold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full';
                $expirationStatus = 'expiring_soon';
            }

            $tr_name = htmlspecialchars($row['name'], ENT_QUOTES);
            $tr_category = htmlspecialchars($row['category_name'], ENT_QUOTES);
            $tr_quantity = (int)$row['quantity'];
            $tr_wholesale = number_format($row['wholesale_price'] ?? 0, 2);
            $tr_retail = number_format($row['retail_price'] ?? 0, 2);
            $tr_exp_display = $expDate ? $expDate->format('Y-m-d') : '—';
            $dateAdded = $row['date_added'] ? date('M d, Y', strtotime($row['date_added'])) : '';

            echo "<tr class='border-b border-slate-100 hover:bg-slate-50 transition-colors' ";
            echo "data-quantity=\"{$tr_quantity}\" ";
            echo "data-category=\"{$tr_category}\" ";
            echo "data-expiration-status=\"{$expirationStatus}\" ";
            echo "data-name=\"{$tr_name}\" ";
            echo "data-wholesale=\"{$row['wholesale_price']}\" ";
            echo "data-retail=\"{$row['retail_price']}\" ";
            echo "data-barcode=\"".htmlspecialchars($row['barcode'])."\" ";
            echo "data-id=\"".htmlspecialchars($row['id'])."\" ";
            echo "data-date-added=\"".htmlspecialchars($row['date_added'])."\" ";
            echo "data-last-stock-in=\"".htmlspecialchars($row['last_stock_in'])."\">";

            echo "<td class='p-4 text-slate-900'>".htmlspecialchars($row['barcode'])."</td>";
            echo "<td class='p-4 font-medium text-slate-900'>".htmlspecialchars($row['name'])."</td>";
            echo "<td class='p-4 text-slate-900'>".htmlspecialchars($row['category_name'])."</td>";

            // Quantity cell with low stock badge and edit threshold button
            echo "<td class='p-4'><div class='flex items-center gap-2'>";
            if ($row['quantity'] <= $row['low_stock_threshold']) {
                echo "<span class='px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800'>Low (".((int)$row['quantity']).")</span>";
            } else {
                echo "<span class='px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800'>".((int)$row['quantity'])."</span>";
            }
            echo "<button onclick=\"editThreshold(this)\" class='text-xs text-slate-900 hover:text-violet-600 transition-colors' data-id='".htmlspecialchars($row['id'])."' data-name='".htmlspecialchars($row['name'])."' data-threshold='".htmlspecialchars($row['low_stock_threshold'])."'>(Alert at ".htmlspecialchars($row['low_stock_threshold']).")</button>";
            echo "</div></td>";

            echo "<td class='p-4 text-slate-900'>₱{$tr_wholesale}</td>";
            echo "<td class='p-4 text-slate-900'>₱{$tr_retail}</td>";

            echo "<td class='p-4 text-slate-900'>";
            if ($expDate && ($daysLeft < 0 || $daysLeft <= 30)) {
                echo "<button onclick=\"openStockOutForExpired(this)\" class='".$expClass." px-2 py-1 rounded cursor-pointer hover:opacity-75 transition-opacity' data-item-id='".htmlspecialchars($row['id'])."' data-barcode='".htmlspecialchars($row['barcode'])."' data-name='".htmlspecialchars($row['name'])."' data-expiry='".($expDate ? $expDate->format('Y-m-d') : '')."' data-quantity='".htmlspecialchars($row['quantity'])."'>".($expDate ? $expDate->format('Y-m-d') : '—')."</button>";
            } else {
                echo "<span class='text-slate-500'>" . $tr_exp_display . "</span>";
                
            }
            echo "</td>";

            echo "<td class='p-4 text-slate-900'>{$dateAdded}</td>";

            echo "</tr>";
        }
        break;
    case 'damage':
        echo '<thead class="bg-slate-100">
                <tr>
                    <th class="p-4">Date</th>
                    <th class="p-4">Item Name</th>
                    <th class="p-4">Category</th>
                    <th class="p-4">Damaged Qty</th>
                    <th class="p-4">Stock Remained</th>
                    <th class="p-4">Remarks</th>
                </tr>
              </thead>
              <tbody>';

        $query = "SELECT d.*, c.name as category_name 
                 FROM damage_items d 
                 LEFT JOIN categories c ON d.category_id = c.id 
                 ORDER BY d.date_reported DESC";
        $result = $conn->query($query);
        
        while($row = $result->fetch_assoc()) {
            echo "<tr class='border-b hover:bg-slate-50'>
                    <td class='p-4'>{$row['date_reported']}</td>
                    <td class='p-4'>{$row['name']}</td>
                    <td class='p-4'>{$row['category_name']}</td>
                    <td class='p-4'>{$row['damage_quantity']}</td>
                    <td class='p-4'>{$row['stock_remained']}</td>
                    <td class='p-4'>{$row['remarks']}</td>
                  </tr>";
        }
        break;

    case 'expired':
        echo '<thead class="bg-slate-100">
                <tr>
                    <th class="p-4">Date</th>
                    <th class="p-4">Item Name</th>
                    <th class="p-4">Category</th>
                    <th class="p-4">Expired Qty</th>
                    <th class="p-4">Stock Remained</th>
                    <th class="p-4">Expiration Date</th>
                </tr>
              </thead>
              <tbody>';

        $query = "SELECT e.*, c.name as category_name 
                 FROM expired_items e 
                 LEFT JOIN categories c ON e.category_id = c.id 
                 ORDER BY e.date_reported DESC";
        $result = $conn->query($query);
        
        while($row = $result->fetch_assoc()) {
            echo "<tr class='border-b hover:bg-slate-50'>
                    <td class='p-4'>{$row['date_reported']}</td>
                    <td class='p-4'>{$row['name']}</td>
                    <td class='p-4'>{$row['category_name']}</td>
                    <td class='p-4'>{$row['expired_quantity']}</td>
                    <td class='p-4'>{$row['stock_remained']}</td>
                    <td class='p-4'>{$row['expiration_date']}</td>
                  </tr>";
        }
        break;
}

echo '</tbody>';
?>
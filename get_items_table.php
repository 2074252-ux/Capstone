<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    exit('Unauthorized');
}

$type = $_GET['type'] ?? 'items';

// Prepare the table header based on type
switch($type) {
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
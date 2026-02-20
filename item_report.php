<?php
require 'db.php';
require_once __DIR__ . '/vendor/autoload.php'; // ✅ Composer autoload for TCPDF

function g($k, $d = null) { return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

$products = [];
if (isset($conn)) {
    $res = $conn->query("SELECT id, barcode, name FROM items ORDER BY name");
    if ($res) while ($r = $res->fetch_assoc()) $products[] = $r;
}

$barcode = g('barcode', '');
$start = g('start', '');
$end = g('end', '');
$group_by = g('group_by', 'day');
$export = g('export', '');
$errors = [];
$rows = [];
$totals = ['stock_in'=>0,'stock_out'=>0,'sold'=>0];

$product_id = null;
$product_info = null;

// 🔹 Fetch product details
if ($barcode && isset($conn)) {
    $stmt = $conn->prepare("SELECT id, name, barcode, expiration_date FROM items WHERE barcode = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $barcode);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) {
            $product_id = (int)$r['id'];
            $product_info = $r;
        }
        $stmt->close();
    }
}

$start_dt = $start ?: null;
$end_dt = $end ?: null;

$group_formats = [
    'hour' => "%Y-%m-%d %H:00:00",
    'day'  => "%Y-%m-%d",
    'month'=> "%Y-%m",
    'year' => "%Y"
];
$fmt = isset($group_formats[$group_by]) ? $group_formats[$group_by] : $group_formats['day'];

function table_exists($conn, $name) {
    $name = $conn->real_escape_string($name);
    $res = $conn->query("SHOW TABLES LIKE '$name'");
    return ($res && $res->num_rows > 0);
}

$sold_rows = [];
if ($product_id && isset($conn)) {
    // sold (sales table with barcode join to items)
    if (table_exists($conn, 'sales') && table_exists($conn, 'items')) {
        $sql = "SELECT DATE_FORMAT(s.sale_date, '$fmt') AS period, SUM(s.quantity) AS sold
                FROM sales s
                JOIN items i ON s.barcode = i.barcode
                WHERE i.id = ?
                " . ($start_dt ? " AND s.sale_date >= ? " : "") . ($end_dt ? " AND s.sale_date <= ? " : "") . "
                GROUP BY period
                ORDER BY period ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($start_dt && $end_dt) $stmt->bind_param("iss", $product_id, $start_dt, $end_dt);
            elseif ($start_dt) $stmt->bind_param("is", $product_id, $start_dt);
            elseif ($end_dt) $stmt->bind_param("is", $product_id, $end_dt);
            else $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) { $sold_rows[$r['period']] = (int)$r['sold']; }
            $stmt->close();
        }
    } elseif (table_exists($conn, 'sales_items')) {
        // fallback if sales_items exists
        $sql = "SELECT DATE_FORMAT(created_at, '$fmt') AS period, SUM(quantity) AS sold
                FROM sales_items
                WHERE item_id = ?
                " . ($start_dt ? " AND created_at >= ? " : "") . ($end_dt ? " AND created_at <= ? " : "") . "
                GROUP BY period ORDER BY period ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($start_dt && $end_dt) $stmt->bind_param("iss", $product_id, $start_dt, $end_dt);
            elseif ($start_dt) $stmt->bind_param("is", $product_id, $start_dt);
            elseif ($end_dt) $stmt->bind_param("is", $product_id, $end_dt);
            else $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) { $sold_rows[$r['period']] = (int)$r['sold']; }
            $stmt->close();
        }
    } else {
        $errors[] = "No recognized sales table found. Adjust queries to match your schema.";
    }

    // stock transactions (stock_transactions preferred)
    $stock_rows = [];
    if (table_exists($conn, 'stock_transactions')) {
        $sql = "SELECT DATE_FORMAT(st.date, '$fmt') AS period,
                       SUM(CASE WHEN st.type='in' THEN st.quantity ELSE 0 END) AS stock_in,
                       SUM(CASE WHEN st.type='out' THEN st.quantity ELSE 0 END) AS stock_out
                FROM stock_transactions st
                WHERE st.item_id = ?
                " . ($start_dt ? " AND st.date >= ? " : "") . ($end_dt ? " AND st.date <= ? " : "") . "
                GROUP BY period ORDER BY period ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($start_dt && $end_dt) $stmt->bind_param("iss", $product_id, $start_dt, $end_dt);
            elseif ($start_dt) $stmt->bind_param("is", $product_id, $start_dt);
            elseif ($end_dt) $stmt->bind_param("is", $product_id, $end_dt);
            else $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $stock_rows[$r['period']] = ['in' => (int)$r['stock_in'], 'out' => (int)$r['stock_out']];
            }
            $stmt->close();
        }
    }

    // Merge results
    $periods = array_unique(array_merge(array_keys($sold_rows), array_keys($stock_rows)));
    sort($periods);
    foreach ($periods as $p) {
        $si = isset($stock_rows[$p]) ? $stock_rows[$p]['in'] : 0;
        $so = isset($stock_rows[$p]) ? $stock_rows[$p]['out'] : 0;
        $sd = isset($sold_rows[$p]) ? $sold_rows[$p] : 0;
        $rows[] = ['period'=>$p, 'stock_in'=>$si, 'stock_out'=>$so, 'sold'=>$sd, 'net'=>($si - $so - $sd)];
        $totals['stock_in'] += $si;
        $totals['stock_out'] += $so;
        $totals['sold'] += $sd;
    }
}

// ✅ Export PDF using TCPDF
if ($export === 'pdf' && !empty($rows)) {
    $pdf = new TCPDF();
    $pdf->SetCreator('Inventory System');
    $pdf->SetAuthor('Inventory Report');
    $pdf->SetTitle('Item Report');
    $pdf->SetMargins(15, 20, 15);
    $pdf->AddPage();

    $html = '<h2 style="text-align:center;">Item / Product Report</h2>';

    // Product info
    if ($product_info) {
        $html .= '<table border="0" cellpadding="4" cellspacing="0" style="font-size:12px;">
                    <tr><td><b>Product Name:</b> '.htmlspecialchars($product_info['name']).'</td></tr>
                    <tr><td><b>Barcode:</b> '.htmlspecialchars($product_info['barcode']).'</td></tr>
                    <tr><td><b>Expiration Date:</b> '.($product_info['expiration_date'] ? htmlspecialchars($product_info['expiration_date']) : 'N/A').'</td></tr>
                  </table><br>';
    }

    // Table header
    $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%" style="font-size:11px;">
                <thead>
                    <tr style="background-color:#f0f0f0;">
                        <th><b>Period</b></th>
                        <th><b>Stock In</b></th>
                        <th><b>Stock Out</b></th>
                        <th><b>Sold</b></th>
                        <th><b>Available Product on Hand</b></th>
                    </tr>
                </thead><tbody>';

    // Rows
    foreach ($rows as $r) {
        $html .= '<tr>
                    <td>'.htmlspecialchars($r['period']).'</td>
                    <td align="right">'.(int)$r['stock_in'].'</td>
                    <td align="right">'.(int)$r['stock_out'].'</td>
                    <td align="right">'.(int)$r['sold'].'</td>
                    <td align="right">'.(int)$r['net'].'</td>
                  </tr>';
    }

    // Totals
    $html .= '<tr style="background-color:#f9f9f9;font-weight:bold;">
                <td>Totals</td>
                <td align="right">'.(int)$totals['stock_in'].'</td>
                <td align="right">'.(int)$totals['stock_out'].'</td>
                <td align="right">'.(int)$totals['sold'].'</td>
                <td align="right">'.(int)($totals['stock_in'] - $totals['stock_out'] - $totals['sold']).'</td>
              </tr>';

    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('item_report.pdf', 'I');
    exit;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Item / Product Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen font-sans antialiased text-slate-800 flex">
<?php include 'sidebar.php'; ?>
<main class="md:ml-72 flex-1 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-2xl font-bold mb-4 text-slate-900">Item / Product Report</h1>

        <!-- Filter Card -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Barcode (select)</label>
                    <input list="prodlist" name="barcode" value="<?= htmlentities($barcode) ?>" class="w-full p-2 rounded border bg-white text-slate-900" placeholder="Select barcode or type">
                    <datalist id="prodlist">
                        <?php foreach ($products as $p): ?>
                            <option value="<?= htmlspecialchars($p['barcode']) ?>"><?= htmlentities($p['name']) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                    <p class="text-xs text-slate-400 mt-1">Choose barcode; the report will resolve the product automatically.</p>
                </div>

                <div>
                    <label class="block text-sm text-slate-600 mb-1">Start (date/time)</label>
                    <input type="datetime-local" name="start" value="<?= htmlentities(str_replace(' ', 'T', $start)) ?>" class="w-full p-2 rounded border bg-white text-slate-900">
                </div>

                <div>
                    <label class="block text-sm text-slate-600 mb-1">End (date/time)</label>
                    <input type="datetime-local" name="end" value="<?= htmlentities(str_replace(' ', 'T', $end)) ?>" class="w-full p-2 rounded border bg-white text-slate-900">
                </div>

                <div>
                    <label class="block text-sm text-slate-600 mb-1">Group by</label>
                    <select name="group_by" class="w-full p-2 rounded border bg-white text-slate-900">
                        <option value="hour" <?= $group_by==='hour' ? 'selected' : '' ?>>Hour</option>
                        <option value="day" <?= $group_by==='day' ? 'selected' : '' ?>>Day</option>
                        <option value="month" <?= $group_by==='month' ? 'selected' : '' ?>>Month</option>
                        <option value="year" <?= $group_by==='year' ? 'selected' : '' ?>>Year</option>
                    </select>
                </div>

                <div class="md:col-span-4 flex gap-2 mt-2">
                    <button type="submit" class="px-4 py-2 bg-sky-600 text-white rounded">Apply</button>
                    <button type="submit" name="export" value="pdf" class="px-4 py-2 bg-red-600 text-white rounded">Export PDF</button>
                    <a href="item_report.php" class="px-4 py-2 bg-slate-100 text-slate-800 rounded border">Reset</a>
                </div>
            </form>
        </div>

        <?php if ($errors): ?>
            <div class="bg-rose-50 text-rose-700 p-3 rounded mb-4 border border-rose-100">
                <?php foreach ($errors as $e) echo "<div class='text-sm'>".htmlentities($e)."</div>"; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="font-semibold mb-4 text-slate-800">Results</h2>

            <?php if (!$barcode || !$product_id): ?>
                <div class="text-slate-500">Please select a barcode and click Apply to see the report.</div>
            <?php else: ?>
                <!-- Product Info -->
                <div class="mb-4 p-4 rounded-lg bg-slate-50 border border-slate-200">
                    <div class="grid md:grid-cols-3 gap-2 text-sm">
                        <div><span class="font-semibold">Product Name:</span> <?= htmlentities($product_info['name']) ?></div>
                        <div><span class="font-semibold">Barcode:</span> <?= htmlentities($product_info['barcode']) ?></div>
                        <div><span class="font-semibold">Expiration Date:</span> <?= $product_info['expiration_date'] ? htmlentities($product_info['expiration_date']) : 'N/A' ?></div>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-slate-600">
                            <tr>
                                <th class="p-2">Period</th>
                                <th class="p-2">Stock In</th>
                                <th class="p-2">Stock Out</th>
                                <th class="p-2">Sold</th>
                                <th class="p-2">Available product on hand</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr><td class="p-4 text-slate-400" colspan="5">No data for selected range.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr class="border-t">
                                        <td class="p-2 text-slate-800"><?= htmlentities($r['period']) ?></td>
                                        <td class="p-2"><?= (int)$r['stock_in'] ?></td>
                                        <td class="p-2"><?= (int)$r['stock_out'] ?></td>
                                        <td class="p-2"><?= (int)$r['sold'] ?></td>
                                        <td class="p-2"><?= (int)$r['net'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="border-t font-semibold bg-slate-50">
                                    <td class="p-2">Totals</td>
                                    <td class="p-2"><?= (int)$totals['stock_in'] ?></td>
                                    <td class="p-2"><?= (int)$totals['stock_out'] ?></td>
                                    <td class="p-2"><?= (int)$totals['sold'] ?></td>
                                    <td class="p-2"><?= (int)($totals['stock_in'] - $totals['stock_out'] - $totals['sold']) ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>

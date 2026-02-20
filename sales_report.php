<?php

require 'db.php';
require 'auth.php';
check_login();

// If employee and no temporary access, show restricted page
if (is_page_restricted() && !has_temporary_access()) {
    require 'restricted.php';
    exit;
}



if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get the requested report type from GET parameter, default to 'daily'
$reportType = $_GET['report'] ?? 'daily';
// UPDATED: Removed 'Inventory Breakdown' as the report now focuses on sales
$reportTitle = match ($reportType) {
    'daily' => 'Daily Sales Report',
    'monthly' => 'Monthly Sales Report',
    'yearly' => 'Yearly Sales Report',
    default => 'Detailed Sales Reports'
};

// --- Report Generation Logic ---

// Base condition for SQL WHERE clause
$whereClause = match ($reportType) {
    'daily' => 'DATE(sale_date) = CURDATE()',
    'monthly' => 'MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())',
    'yearly' => 'YEAR(sale_date) = YEAR(CURDATE())',
    default => '1=1' // All time
};

// 1. Total Income Calculation for the Period
$totalIncomeResult = $conn->query("
    SELECT SUM(total_amount) AS total_income
    FROM sales 
    WHERE {$whereClause}
");
$totalIncome = $totalIncomeResult->fetch_assoc()['total_income'] ?? 0;

// 2. Sales Aggregation (by product name - based on the existing sales table schema)
$salesDataResult = $conn->query("
    SELECT name, SUM(quantity) as total_sold, SUM(total_amount) as total_revenue
    FROM sales
    WHERE {$whereClause}
    GROUP BY name
");

// 3. Stock In/Out Aggregation (REMOVED - focusing only on Sales data)
// $stockInDataResult and $stockOutQuery, $stockOutBreakdownQuery are removed.

// 4. Merge data into a single summary array (Simplified)
$productSummary = [];
$overallTotalSold = 0; // Initialize overall total sold

// Merge sales data and calculate overall sold
while ($row = $salesDataResult->fetch_assoc()) {
    $name = $row['name'];
    $total_sold = (int)$row['total_sold'];
    $total_revenue = (float)$row['total_revenue'];
    
    $productSummary[$name] = [
        'name' => $name,
        'total_sold' => $total_sold,
        'total_revenue' => $total_revenue,
    ];
    
    $overallTotalSold += $total_sold; // Calculate the new overall total
}

// Convert to indexed array for easier HTML iteration
$finalProductSummary = array_values($productSummary);
// Sort by revenue descending
usort($finalProductSummary, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

// 5. Data Aggregation for Chart (Income Trend) - Kept as is
$chartDataQuery = '';
$labelFormat = '';

switch ($reportType) {
    case 'daily':
        // Hourly sales breakdown for today
        $chartDataQuery = "SELECT HOUR(sale_date) AS time_unit, SUM(total_amount) AS income FROM sales WHERE DATE(sale_date) = CURDATE() GROUP BY time_unit ORDER BY time_unit";
        break;
    case 'monthly':
        // Daily sales breakdown for this month
        $chartDataQuery = "SELECT DAY(sale_date) AS time_unit, SUM(total_amount) AS income FROM sales WHERE MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE()) GROUP BY time_unit ORDER BY time_unit";
        break;
    case 'yearly':
        // Monthly sales breakdown for this year
        $chartDataQuery = "SELECT MONTH(sale_date) AS time_unit, SUM(total_amount) AS income FROM sales WHERE YEAR(sale_date) = YEAR(CURDATE()) GROUP BY time_unit ORDER BY time_unit";
        break;
}

$chartData = [];
if ($chartDataQuery) {
    $result = $conn->query($chartDataQuery);
    while ($row = $result->fetch_assoc()) {
        $chartData[] = $row;
    }
}

// Prepare labels and data for Chart.js
$chartLabels = [];
$chartIncomes = [];

foreach ($chartData as $data) {
    if ($reportType === 'yearly') {
        // Convert month number to month name
        $monthName = date('F', mktime(0, 0, 0, $data['time_unit'], 10));
        $chartLabels[] = $monthName;
    } else if ($reportType === 'daily') {
        $chartLabels[] = $data['time_unit'] . ':00'; // Hour
    } else {
        $chartLabels[] = 'Day ' . $data['time_unit'];
    }
    $chartIncomes[] = (float)$data['income'];
}
$chartLabelsJson = json_encode($chartLabels);
$chartIncomesJson = json_encode($chartIncomes);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Che-Che MiniMart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        /* Styles for the active tab (selected view) */
        .active-tab {
            /* Keep it distinct and highlighted */
            @apply border-b-4 border-violet-500 text-violet-700 font-bold bg-violet-50;
        }
        /* ENHANCED: Styles for the clickable tabs */
        .tab {
            @apply px-4 py-2 text-sm font-medium text-slate-700 transition-all duration-200 
                   rounded-t-lg cursor-pointer 
                   border border-b-0 border-slate-200 
                   bg-white hover:bg-violet-50 hover:text-violet-600 shadow-sm;
        }
        /* Ensure the border-bottom is visually distinct from the active tab */
        .tab-container {
            @apply flex border-b-2 border-slate-200 mb-8;
        }
        .scrollable-lg { max-height: 50vh; overflow-y: auto; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen font-sans antialiased text-slate-800 flex">
    <?php include 'sidebar.php'; ?>
    
    <div class="flex-1 p-4 sm:p-8 lg:ml-[256px] transition-all duration-300">
        
        <h2 class="text-3xl font-bold text-slate-900 mb-6"><?= htmlspecialchars($reportTitle) ?></h2>

       <div id="chartDataWrapper" data-labels='<?= $chartLabelsJson ?>' data-incomes='<?= $chartIncomesJson ?>' data-type='<?= $reportType ?>' data-access-status='<?= $accessStatus ?>' data-expiration-time='<?= $expirationTime ?>'></div>



     <div class="flex flex-wrap gap-3 mb-8">
    <a href="?report=daily" 
        class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold rounded-lg shadow-sm border 
        transition-all duration-200 
        <?= $reportType === 'daily' 
            ? 'bg-violet-600 text-white border-violet-700 ring-2 ring-violet-300 shadow-md scale-105' 
            : 'bg-white text-slate-700 border-slate-300 hover:bg-violet-50 hover:text-violet-600' ?>">
        📅 Daily Report
    </a>

    <a href="?report=monthly" 
        class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold rounded-lg shadow-sm border 
        transition-all duration-200 
        <?= $reportType === 'monthly' 
            ? 'bg-violet-600 text-white border-violet-700 ring-2 ring-violet-300 shadow-md scale-105' 
            : 'bg-white text-slate-700 border-slate-300 hover:bg-violet-50 hover:text-violet-600' ?>">
        📊 Monthly Report
    </a>

    <a href="?report=yearly" 
        class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold rounded-lg shadow-sm border 
        transition-all duration-200 
        <?= $reportType === 'yearly' 
            ? 'bg-violet-600 text-white border-violet-700 ring-2 ring-violet-300 shadow-md scale-105' 
            : 'bg-white text-slate-700 border-slate-300 hover:bg-violet-50 hover:text-violet-600' ?>">
        📆 Yearly Report
    </a>
</div>

        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-violet-600 text-white rounded-xl shadow-lg p-6 md:col-span-1">
                <p class="text-sm font-semibold mb-2 opacity-90">Total Income (<?= ucfirst($reportType) ?>)</p>
                <h3 class="text-4xl font-extrabold">₱<?= number_format($totalIncome, 2) ?></h3>
                <p class="text-xs mt-2 opacity-70">Revenue generated in the selected period.</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 md:col-span-2 flex items-center">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full">
                    <div class="col-span-1">
                        <p class="text-sm font-semibold text-emerald-600 mb-2">Overall Total Sold Items</p>
                        <h3 class="text-4xl font-extrabold text-slate-900"><?= number_format($overallTotalSold) ?> items</h3>
                    </div>
                 
                </div>
            </div>
            
        </div>

        <div class="bg-white rounded-xl shadow-lg border border-violet-200 p-6 mb-8">
            <h3 class="text-xl font-semibold text-violet-700 mb-4 flex items-center gap-2">
                <span class="text-2xl">📈</span> Historical Income Trend (<?= ucfirst($reportType) ?> Breakdown)
            </h3>
            <div class="h-80 w-full">
                <canvas id="incomeChart"></canvas>
            </div>
        </div>

      <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6">
            <h3 class="text-xl font-semibold text-slate-700 mb-4 flex items-center gap-2">
                <span class="text-2xl">🏆</span> Item Performance
            </h3>
            <div class="scrollable-lg">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="sticky top-0 bg-slate-100 text-slate-700 font-medium border-b border-slate-300">
                        <tr>
                            <th class="p-3">Product Name</th>
                            <th class="p-3 text-center">Total Sold</th>
                            <th class="p-3 text-right">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($finalProductSummary)): ?>
                            <?php foreach ($finalProductSummary as $item): ?>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="p-3 font-medium text-slate-800">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-lg font-medium text-sm">
                                            <?= number_format($item['total_sold']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-right font-semibold text-violet-600">
                                        ₱<?= number_format($item['total_revenue'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="p-6 text-center text-slate-500">
                                    No product performance data found for the selected period.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

   <script src="scripts/sales_report.js"></script>
</body>
</html>
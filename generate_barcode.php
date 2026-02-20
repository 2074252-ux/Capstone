<?php
session_start();
require 'db.php';
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$message = "";

// Handle form submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $unit = trim($_POST["unit"]);
    $barcode = trim($_POST["barcode"]);

    if (empty($barcode)) {
        $barcode = "CB" . str_pad(rand(0, 9999999999), 10, "0", STR_PAD_LEFT);
    }

    // Check duplicate
    $check = $conn->prepare("SELECT id FROM custom_barcodes WHERE barcode = ?");
    $check->bind_param("s", $barcode);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = "❌ Barcode already exists!";
    } else {
        $stmt = $conn->prepare("INSERT INTO custom_barcodes (name, description, unit, barcode) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $description, $unit, $barcode);
        if ($stmt->execute()) {
            $message = "✅ Custom item created successfully!";
        } else {
            $message = "❌ Error adding custom barcode.";
        }
    }
}

$recent = $conn->query("SELECT * FROM custom_barcodes ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Barcode - Che-Che MiniMart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-gray-50 min-h-screen font-sans text-slate-800 flex">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 p-8 ml-[256px]">
        <header class="pb-6 mb-8 border-b-2 border-slate-200 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-slate-900">🏷️ Custom Barcode Generator</h1>
            <button id="openModal" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2 px-4 rounded-md shadow">
                <i class="bi bi-plus-circle"></i> New Barcode
            </button>
        </header>

        <?php if ($message): ?>
        <div class="p-4 mb-6 text-sm <?= strpos($message, '✅') !== false ? 'text-green-800 bg-green-50' : 'text-amber-800 bg-amber-50' ?> rounded-lg">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

      <!-- 🔎 Enhanced Search & Unit Filter -->
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <!-- Search by name/barcode -->
    <div class="relative flex-1 min-w-[250px]">
        <input 
            type="text" 
            id="searchInput" 
            placeholder="Search barcode or name..." 
            class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-amber-500"
        >
        <i class="bi bi-search absolute top-3 right-4 text-gray-400"></i>
        
    </div>

    <!-- Unit filter -->
    <div class="flex items-center gap-2">
        <label for="unitFilter" class="text-sm font-medium text-gray-700">Unit:</label>
        <select 
            id="unitFilter" 
            class="p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-amber-500"
        >
            <option value="">All Units</option>
            <option value="KG">KG</option>
            <option value="G">G</option>
            <option value="1/2">½</option>
            <option value="1/4">¼</option>
            <option value="3/4">¾</option>
            <option value="PCS">PCS</option>
            <option value="PACK">PACK</option>
            <option value="SACHET">SACHET</option>
            <option value="DOZEN">DOZEN</option>
            <option value="BOTTLE">BOTTLE</option>
            <option value="CAN">CAN</option>
        </select>
    </div>
</div>


        <!-- Table -->
        <div class="bg-white shadow-md border border-gray-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-amber-100">
                    <tr class="text-left">
                        <th class="p-3 border">Barcode Image</th>
                        <th class="p-3 border">Barcode Number</th>
                        <th class="p-3 border">Name</th>
                        <th class="p-3 border">Unit</th>
                        <th class="p-3 border text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="barcodeTable">
                    <?php while ($row = $recent->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="border p-2 font-mono text-center">
                            <svg id="barcode<?= $row['id'] ?>" class="mx-auto h-12"></svg>
                            <script>
                                JsBarcode("#barcode<?= $row['id'] ?>", "<?= $row['barcode'] ?>", {
                                    format: "CODE128",
                                    displayValue: false,
                                    height: 50
                                });
                            </script>
                        </td>
                        <td class="border p-3 font-mono"><?= htmlspecialchars($row['barcode']) ?></td>
                        <td class="border p-3"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="border p-3"><?= htmlspecialchars($row['unit']) ?></td>
                        <td class="border p-3 text-center">
                            <button onclick="downloadBarcode('<?= $row['barcode'] ?>', '<?= $row['id'] ?>')" 
                                    class="text-amber-600 hover:text-amber-800 font-semibold">
                                <i class="bi bi-download"></i> Print
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div id="barcodeModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white w-full max-w-lg rounded-xl shadow-lg p-6 relative">
            <button id="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
            <h2 class="text-2xl font-bold mb-4 text-amber-600">Create New Barcode</h2>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium mb-1">Item Name</label>
                    <input type="text" name="name" required class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-amber-500">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea name="description" class="w-full p-3 border border-gray-300 rounded-md"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Unit</label>
                    <input type="text" name="unit" placeholder="e.g., 1kg, 1/4kg, etc." required class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-amber-500">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Barcode (Optional)</label>
                    <input type="text" name="barcode" class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-amber-500" placeholder="Leave blank to auto-generate">
                </div>

                <button type="submit" class="w-full py-3 px-4 text-white bg-amber-500 font-bold rounded-md hover:bg-amber-600">
                    <i class="bi bi-check2-circle"></i> Generate Barcode
                </button>
            </form>
        </div>
    </div>

    <script src="scripts/generate_barcode.js"></script>
</body>
</html>

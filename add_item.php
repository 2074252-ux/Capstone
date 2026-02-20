<?php
require 'db.php';


require 'auth.php';
check_login();

// If employee and no temporary access, show restricted page
if (is_page_restricted() && !has_temporary_access()) {
    require 'restricted.php';
    exit;
}


$message = "";
$showDuplicateModal = false;
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize inputs
    $barcode = trim($_POST['barcode']);
    $name = trim($_POST['name']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $description = trim($_POST['description']);
    $wholesale_price = 0.00;
    $retail_price = 0.00;

    // Store form data to refill fields if needed
    $formData = compact('barcode', 'name', 'category_id', 'description');

    // Check for duplicates unless user already confirmed
    if (!isset($_POST['confirm_duplicate'])) {
        $check = $conn->prepare("SELECT COUNT(*) AS total FROM items WHERE barcode = ?");
        $check->bind_param("s", $barcode);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        $check->close();

        if ($result['total'] > 0) {
            $showDuplicateModal = true; // trigger modal
        }
    }

    // If not duplicate or confirmed, proceed to insert
    if (!$showDuplicateModal) {
        if ($category_id === null) {
            $stmt = $conn->prepare("
                INSERT INTO items (
                    barcode, name, category_id, description, 
                    wholesale_price, retail_price, low_stock_threshold
                ) VALUES (?, ?, NULL, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssddi", 
                $barcode, $name, $description, 
                $wholesale_price, $retail_price, 
                $_POST['low_stock_threshold']
            );
        } else {
            $stmt = $conn->prepare("
                INSERT INTO items (
                    barcode, name, category_id, description, 
                    wholesale_price, retail_price, low_stock_threshold
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssisddi", 
                $barcode, $name, $category_id, $description, 
                $wholesale_price, $retail_price, 
                $_POST['low_stock_threshold']
            );
        }

        if ($stmt->execute()) {
            $message = "✅ Item added successfully!";
            $formData = []; // clear form after success
        } else {
            $message = "❌ Error: " . $stmt->error;
        }

        $stmt->close();
    }
}

// Fetch categories for dropdown
$categories_result = $conn->query("SELECT id, name FROM categories");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Item</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
  
  </head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<?php include 'sidebar.php'; ?>

  <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-lg">
    <h1 class="text-2xl font-bold mb-6 text-gray-800 text-center">Add New Item</h1>

    <?php if (!empty($message)): ?>
      <div class="mb-4 p-3 rounded-lg 
        <?= str_contains($message, '✅') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <form id="addItemForm" action="add_item.php" method="POST" class="space-y-4">

      <div class="relative">
        <label for="barcode" class="block text-sm font-medium text-gray-700">Barcode</label>
        <div class="flex space-x-2">
          <input type="text" id="barcode" name="barcode" required
            value="<?= htmlspecialchars($formData['barcode'] ?? '') ?>"
            class="flex-grow border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <button type="button" id="scanButton" class="mt-1 p-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition" aria-expanded="false" title="Scan Barcode with Camera">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0113 3.414L16.586 7A2 2 0 0118 8.414v5.172a2 2 0 01-2 2h-5.172a2 2 0 01-1.414.586L4 16a2 2 0 01-2-2V6a2 2 0 012-2zm2 10a1 1 0 001 1h6.586l1.707-1.707a1 1 0 00.293-.707V6a1 1 0 00-1-1h-4.586a1 1 0 00-.707.293L4 7.414V14a1 1 0 001 1zM7 8a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
        
        <div id="reader" style="width: 100%; display: none; margin-top: 1rem;"></div>
        
        <style>
          /* User requested the video feed not to be mirrored. This CSS ensures the video element is not flipped by the browser/library defaults. */
          #reader video {
            transform: scaleX(1) !important;
          }
        </style>
      </div>

      <div>
        <label for="name" class="block text-sm font-medium text-gray-700">Item Name</label>
        <input type="text" id="name" name="name" required
          value="<?= htmlspecialchars($formData['name'] ?? '') ?>"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>

      <div>
        <label for="category_id" class="block text-sm font-medium text-gray-700">Category (Optional)</label>
        <select id="category_id" name="category_id"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- No Category --</option>
          <?php 
          // Re-query categories for display after while loop
          $categories_result2 = $conn->query("SELECT id, name FROM categories");
          while ($row = $categories_result2->fetch_assoc()): ?>
            <option value="<?= $row['id'] ?>" 
              <?= (isset($formData['category_id']) && $formData['category_id'] == $row['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($row['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
        <textarea id="description" name="description" rows="3"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
      </div>

      <div>
        <label for="low_stock_threshold" class="block text-sm font-medium text-gray-700">
          Low Stock Alert Threshold
          <span class="text-xs text-slate-500">(Notify when stock reaches this level)</span>
        </label>
        <input type="number" id="low_stock_threshold" name="low_stock_threshold" min="1"
          value="5"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>

      <div class="pt-4">
        <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-2 rounded-lg hover:bg-blue-700 transition">
          Add Item
        </button>
      </div>
    </form>
  </div>

  <?php if ($showDuplicateModal): ?>
  <div class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl shadow-2xl max-w-md w-full">
      <h2 class="text-xl font-semibold text-gray-800 mb-3">Duplicate Barcode Found</h2>
      <p class="text-gray-600 mb-4">
        The barcode <strong><?= htmlspecialchars($barcode) ?></strong> already exists in the database.<br>
        Would you still like to proceed adding this item?
      </p>
      <div class="flex justify-end space-x-3">
        <button onclick="window.history.back()" 
                class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Cancel</button>

        <form action="add_item.php" method="POST">
          <?php foreach ($formData as $key => $value): ?>
            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
          <?php endforeach; ?>
          <input type="hidden" name="confirm_duplicate" value="1">
          <button type="submit" 
                  class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Proceed Anyway</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script>
    const barcodeInput = document.getElementById('barcode');
    const scanButton = document.getElementById('scanButton');
    const readerDiv = document.getElementById('reader');
    let html5QrCode = null;
    let isScanning = false;

    // Existing AJAX Auto-fill Logic
    function fetchItemDetails(barcode) {
        if (!barcode) return;

        fetch('get_item_by_barcode.php?barcode=' + encodeURIComponent(barcode))
            .then(res => res.json())
            .then(data => {
                if (data.exists) {
                    document.getElementById('name').value = data.name;
                    document.getElementById('category_id').value = data.category_id;
                    document.getElementById('description').value = data.description || "";

                    // Make fields read-only to prevent edits if item exists
                    document.getElementById('name').readOnly = true;
                    document.getElementById('category_id').disabled = false;
                    document.getElementById('description').readOnly = true;
                } else {
                    // Make editable if new barcode
                    document.getElementById('name').readOnly = false;
                    document.getElementById('category_id').disabled = false;
                    document.getElementById('description').readOnly = false;
                    // Clear fields only if they are not already filled from the PHP form data
                    if (!"<?= $formData['name'] ?? '' ?>") document.getElementById('name').value = "";
                    if (!"<?= $formData['category_id'] ?? '' ?>") document.getElementById('category_id').value = "";
                    if (!"<?= $formData['description'] ?? '' ?>") document.getElementById('description').value = "";
                }
            })
            .catch(error => console.error('Error fetching item details:', error));
    }

    barcodeInput.addEventListener('change', function () {
        fetchItemDetails(this.value.trim());
    });

    // Camera Scan Logic
    function onScanSuccess(decodedText, decodedResult) {
        // Stop the scanner immediately upon successful scan
        if (isScanning) {
            stopScan();
            
            // Populate the barcode field
            barcodeInput.value = decodedText;
            
            // Trigger the existing AJAX auto-fill logic
            fetchItemDetails(decodedText);
        }
    }

    function onScanError(errorMessage) {
        // Optional: console.log for debugging or a subtle notification to the user
    }

    function startScan() {
        if (typeof Html5Qrcode === 'undefined') {
            alert('Error: html5-qrcode library not loaded. Please ensure the script is included in your <head> as instructed.');
            return;
        }
        
        // Show the reader area
        readerDiv.style.display = 'block';
        scanButton.textContent = 'Stop Scan';
        scanButton.setAttribute('aria-expanded', 'true');
        isScanning = true;

        if (!html5QrCode) {
            // Create the Html5Qrcode object
            html5QrCode = new Html5Qrcode("reader");
        }
        
        // Configuration for the scanner
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 150 },
            // Use front camera if on mobile, rear camera otherwise.
            // On desktop, it will just use the default camera.
            // This is crucial for avoiding the mirrored issue on the front camera.
            facingMode: "environment" 
        };

        // Start scanning with camera
        html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanError
        ).catch(err => {
            readerDiv.style.display = 'none';
            stopScan();
            alert(`Error starting camera: ${err}. Please ensure you grant camera permissions.`);
        });
    }

    async function stopScan() {
        if (html5QrCode && isScanning) {
            try {
                await html5QrCode.stop();
            } catch (err) {
                // Ignore if the camera is already off
            }
        }
        readerDiv.style.display = 'none';
        scanButton.textContent = 'Scan Barcode';
        scanButton.setAttribute('aria-expanded', 'false');
        isScanning = false;
    }

    scanButton.addEventListener('click', function() {
        if (isScanning) {
            stopScan();
        } else {
            startScan();
        }
    });

    // Ensure scanner is stopped if the page is navigated away from or closed
    window.addEventListener('beforeunload', stopScan);

    // Add this inside your existing script tags in add_item.php
document.getElementById('addItemForm').addEventListener('submit', function(e) {
    const thresholdInput = document.getElementById('low_stock_threshold');
    const threshold = parseInt(thresholdInput.value);
    
    if (isNaN(threshold) || threshold < 1) {
        e.preventDefault();
        alert('Please enter a valid low stock threshold (minimum 1)');
        thresholdInput.focus();
        return false;
    }
});
  </script>
</body>
</html>
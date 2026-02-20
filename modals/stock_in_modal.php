
<div id="stockInModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center p-4 z-50">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl">
    <!-- Update the form tag -->
    <form action="stock_in.php" method="POST" class="flex flex-col h-full">
      <div class="flex justify-between items-center p-6 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">Stock In</h3>
        <button type="button" class="closeStockIn text-gray-400 hover:text-gray-600">&times;</button>
      </div>

      <div class="p-6 space-y-4 flex-1">
        <!-- Product Search -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Search Product</label>
          <div class="relative">
            <input type="text" id="productSearch" 
                   class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-emerald-500"
                   placeholder="Type product name...">
            <div id="searchResults" class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-md shadow-lg hidden max-h-60 overflow-y-auto z-10">
              <!-- Search results will be populated here -->
            </div>
          </div>
        </div>

        <!-- Barcode Scanner (existing) -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Scan Barcode</label>
          <div class="flex gap-2">
            <input id="barcode-in" name="barcode" type="text" required 
                   class="flex-1 p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-emerald-500">
            <button id="start-scan-in" type="button" class="px-3 py-2 bg-emerald-600 text-white rounded-md">
              <i class="bi bi-upc-scan"></i>
            </button>
            <button id="stop-scan-in" type="button" class="px-3 py-2 bg-red-600 text-white rounded-md" style="display:none;">
              <i class="bi bi-stop-fill"></i>
            </button>
          </div>
          <div id="scanner-in" class="mt-2 hidden aspect-video rounded-md overflow-hidden bg-black"></div>
        </div>

        <!-- Item Selection (shows when multiple items found) -->
        <div id="itemSelect" class="hidden">
          <label class="block text-sm font-medium text-slate-700 mb-1">Select Item Variant</label>
          <div class="bg-slate-50 rounded-lg p-4 max-h-48 overflow-y-auto space-y-2">
            <!-- Items will be inserted here by JavaScript -->
          </div>
        </div>

        <input type="hidden" id="selected-item-id" name="item_id">
        
        <!-- Item Details -->
        <div id="itemDetails" class="hidden space-y-4">
          <div class="bg-slate-50 rounded-lg p-4">
            <div class="text-sm text-slate-500">Selected Item:</div>
            <div id="selectedItemName" class="font-medium text-slate-800"></div>
            <div id="selectedItemExp" class="text-sm text-slate-600"></div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Quantity</label>
              <input type="number" name="quantity" min="1" required 
                     class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-emerald-500">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">New Expiration Date</label>
              <input type="date" name="expiration_date" 
                     class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Supplier Price</label>
              <input type="number" step="0.01" id="wholesale_price" name="wholesale_price" 
                     class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-emerald-500"
                     onchange="calculateSellingPrice()">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Markup Percentage (%)</label>
              <input type="number" step="0.01" id="markup_percentage" name="markup_percentage" 
                     class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-emerald-500"
                     value="10" min="0" max="100"
                     onchange="calculateSellingPrice()">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Selling Price (Calculated)</label>
              <input type="number" step="0.01" id="retail_price" name="retail_price" 
                     class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-emerald-500 bg-slate-50"
                     readonly>
              <p class="text-xs text-slate-500 mt-1">Auto-calculated based on markup</p>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 p-6 border-t border-slate-200">
        <button type="button" class="closeStockIn px-4 py-2 bg-slate-100 rounded-md">Cancel</button>
        <button type="submit" id="submitStockIn" class="px-4 py-2 bg-emerald-600 text-white rounded-md">Add Stock</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const productSearch = document.getElementById('productSearch');
  const searchResults = document.getElementById('searchResults');
  const barcodeInput = document.getElementById('barcode-in');
  const itemSelect = document.getElementById('itemSelect');
  const itemDetails = document.getElementById('itemDetails');
  const selectedItemId = document.getElementById('selected-item-id');
  const selectedItemName = document.getElementById('selectedItemName');
  const selectedItemExp = document.getElementById('selectedItemExp');

  // Update the calculation function
function calculateSellingPrice() {
    const wholesalePrice = parseFloat(document.getElementById('wholesale_price').value) || 0;
    const markupPercentage = parseFloat(document.getElementById('markup_percentage').value) || 0;
    const retailPriceInput = document.getElementById('retail_price');
    
    if (wholesalePrice > 0) {
        const markup = (wholesalePrice * markupPercentage) / 100;
        const sellingPrice = wholesalePrice + markup;
        retailPriceInput.value = sellingPrice.toFixed(2);
    } else {
        retailPriceInput.value = '';
    }
}

// Add event listeners for real-time calculation
document.getElementById('wholesale_price').addEventListener('input', calculateSellingPrice);
document.getElementById('markup_percentage').addEventListener('input', calculateSellingPrice);

  async function lookupBarcode(code) {
    if (!code) return;
    try {
        const res = await fetch(`stock_in.php?action=lookup&barcode=${encodeURIComponent(code)}`);
        const json = await res.json();
        if (json.found && json.items && json.items[0]) {
            const item = json.items[0];
            document.getElementById('wholesale_price').value = item.wholesale_price ?? '';
            document.getElementById('markup_percentage').value = item.markup_percentage ?? 10;
            calculateSellingPrice(); // Calculate selling price automatically
        }
    } catch (e) {
        console.error('Lookup error', e);
    }
  }

  async function lookupBarcode(code) {
    try {
        const res = await fetch(`stock_in.php?action=lookup&barcode=${encodeURIComponent(code)}`);
        const data = await res.json();
        
        if (data.found && data.items) {
            if (data.items.length > 1) {
                // Multiple items found - show selector
                itemSelect.classList.remove('hidden');
                itemDetails.classList.add('hidden');
                
                const container = itemSelect.querySelector('div');
                container.innerHTML = '';
                
                data.items.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'p-3 bg-white rounded border border-slate-200 cursor-pointer hover:bg-slate-50';
                    div.innerHTML = `
                        <div class="font-medium">${item.name}</div>
                        <div class="text-sm text-slate-500">
                            ${item.source === 'custom' ? 'Custom Item' : `Current Qty: ${item.quantity}`}
                            ${item.expiration_date ? ` · Expires: ${item.expiration_date}` : ''}
                        </div>
                        ${item.wholesale_price ? `<div class="text-xs text-slate-400">Wholesale: ₱${item.wholesale_price}</div>` : ''}
                        ${item.retail_price ? `<div class="text-xs text-slate-400">Retail: ₱${item.retail_price}</div>` : ''}
                    `;
                    
                    div.onclick = () => selectItem(item);
                    container.appendChild(div);
                });
            } else if (data.items.length === 1) {
                // Single item - select it directly
                selectItem(data.items[0]);
            }
        } else {
            // No items found
            itemSelect.classList.add('hidden');
            itemDetails.classList.add('hidden');
            alert('No items found with this barcode');
        }
    } catch (e) {
        console.error('Error looking up barcode:', e);
        alert('Error looking up barcode');
    }
  }

  function selectItem(item) {
    itemSelect.classList.add('hidden');
    itemDetails.classList.remove('hidden');
    
    selectedItemId.value = item.id;
    selectedItemName.textContent = item.name;
    
    if (item.source === 'custom') {
        selectedItemExp.textContent = 'Custom Item';
    } else {
        selectedItemExp.textContent = item.expiration_date ? 
            `Current Expiration: ${item.expiration_date}` : 
            'No expiration set';
    }
    
    // Pre-fill prices if available
    if (item.wholesale_price) {
        document.querySelector('[name="wholesale_price"]').value = item.wholesale_price;
    }
    if (item.retail_price) {
        document.querySelector('[name="retail_price"]').value = item.retail_price;
    }
  }

  // Lookup when barcode entered
  let lookupTimeout;
  barcodeInput.addEventListener('input', (e) => {
    clearTimeout(lookupTimeout);
    lookupTimeout = setTimeout(() => lookupBarcode(e.target.value), 300);
  });

  let searchTimeout;
  
  productSearch.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();
    
    if (query.length < 2) {
      searchResults.classList.add('hidden');
      return;
    }
    
    searchTimeout = setTimeout(() => {
      fetch(`search_products.php?q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
          searchResults.innerHTML = '';
          
          if (data.length > 0) {
            data.forEach(item => {
              const div = document.createElement('div');
              div.className = 'p-2 hover:bg-slate-50 cursor-pointer border-b last:border-b-0';
              div.innerHTML = `
                <div class="font-medium">${item.name}</div>
                <div class="text-sm text-slate-500">
                  Barcode: ${item.barcode} · Stock: ${item.quantity}
                </div>
              `;
              div.onclick = () => {
                barcodeInput.value = item.barcode;
                productSearch.value = item.name;
                searchResults.classList.add('hidden');
                // Trigger barcode lookup
                lookupBarcode(item.barcode);
              };
              searchResults.appendChild(div);
            });
            searchResults.classList.remove('hidden');
          } else {
            searchResults.classList.add('hidden');
          }
        });
    }, 300);
  });
  
  // Hide results when clicking outside
  document.addEventListener('click', (e) => {
    if (!productSearch.contains(e.target)) {
      searchResults.classList.add('hidden');
    }
  });

  // Add form submission handler
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Submit the form using fetch
        fetch('stock_in.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => response.text())
        .then(() => {
            // Close the modal
            document.getElementById('stockInModal').classList.add('hidden');
            // Redirect to items.php
            window.location.href = 'items.php';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request.');
        });
    });
});
</script>

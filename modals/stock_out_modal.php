<div id="stockOutModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center p-4 z-50">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl">
    <form id="stockOutForm" action="stock_out.php" method="POST" class="flex flex-col h-full">
      <div class="flex justify-between items-center p-6 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">Stock Out</h3>
        <button type="button" class="closeStockOut text-gray-400 hover:text-gray-600">&times;</button>
      </div>

      <div class="p-6 space-y-4 flex-1">
        <!-- Update barcode section text colors -->
        <div>
          <label class="block text-sm font-medium text-slate-900 mb-1">Scan Barcode</label>
          <div class="flex gap-2">
            <input id="barcode-out" name="barcode" type="text" required 
                   class="flex-1 p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-rose-500 text-slate-900">
            <button id="start-scan-out" type="button" class="px-3 py-2 bg-rose-600 text-white rounded-md">
              <i class="bi bi-upc-scan"></i>
            </button>
            <button id="stop-scan-out" type="button" class="px-3 py-2 bg-red-600 text-white rounded-md" style="display:none;">
              <i class="bi bi-stop-fill"></i>
            </button>
          </div>
        </div>

        <!-- Item Selection (shows when multiple items found) -->
        <div id="itemSelectOut" class="hidden">
          <label class="block text-sm font-medium text-slate-900 mb-1">Select Item Variant</label>
          <div class="bg-slate-50 rounded-lg p-4 max-h-48 overflow-y-auto space-y-2 text-slate-900">
            <!-- Items will be inserted here by JavaScript -->
          </div>
        </div>

        <input type="hidden" id="selected-item-id-out" name="item_id">
        
        <!-- Item Details -->
        <div id="itemDetailsOut" class="hidden space-y-4">
          <div class="bg-slate-50 rounded-lg p-4">
            <div class="text-sm text-slate-900">Selected Item:</div>
            <div id="selectedItemNameOut" class="font-medium text-slate-900"></div>
            <div id="selectedItemExpOut" class="text-sm text-slate-900"></div>
            <div id="selectedItemQtyOut" class="text-sm text-slate-900"></div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-900 mb-1">Quantity to Remove</label>
            <input type="number" name="quantity" min="1" required 
                   class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-rose-500 text-slate-900">
          </div>
        </div>

        <!-- Update reason selection text colors -->
        <div>
          <label class="block text-sm font-medium text-slate-900 mb-1">Reason for Stock Out*</label>
          <select name="stock_out_reason" id="stockOutReason" required 
                  class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-rose-500 text-slate-900">
            <option value="">Select Reason</option>
            <option value="damage">Damaged Items</option>
            <option value="expired">Expired Items</option>
          </select>
        </div>

        <!-- Update damage fields text colors -->
        <div id="damageFields" class="hidden space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-900 mb-1">Damage Description</label>
            <textarea name="damage_remarks" rows="2" 
                      class="w-full p-3 border border-slate-300 rounded-md focus:ring-2 focus:ring-rose-500 text-slate-900"
                      placeholder="Describe the damage..."></textarea>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 p-6 border-t border-slate-200">
        <button type="button" class="closeStockOut px-4 py-2 bg-slate-100 rounded-md text-slate-900">Cancel</button>
        <button type="submit" id="stockOutSubmit" class="px-4 py-2 bg-rose-600 text-white rounded-md" disabled>
          Remove Stock
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Add confirmation modal -->
<div id="confirmStockOutModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center p-4 z-[60]">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
    <h4 class="text-lg font-semibold text-slate-900 mb-4">Confirm Stock Out</h4>
    <p class="text-slate-900 mb-4">Are you sure you want to remove this item from stock?</p>
    <div id="confirmStockOutDetails" class="bg-slate-50 rounded-lg p-4 mb-6">
      <p id="confirmItemName" class="font-medium text-slate-900"></p>
      <p id="confirmQuantity" class="text-slate-900"></p>
      <p id="confirmReason" class="text-slate-900"></p>
    </div>
    <div class="flex justify-end gap-3">
      <button type="button" id="cancelStockOut" class="px-4 py-2 bg-slate-100 text-slate-900 rounded-md hover:bg-slate-200">
        Cancel
      </button>
      <button type="button" id="confirmStockOutBtn" class="px-4 py-2 bg-rose-600 text-white rounded-md hover:bg-rose-700">
        Proceed
      </button>
    </div>
  </div>
</div>

<!-- Update JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const barcodeInput = document.getElementById('barcode-out');
    const itemSelect = document.getElementById('itemSelectOut');
    const itemDetails = document.getElementById('itemDetailsOut');
    const selectedItemId = document.getElementById('selected-item-id-out');
    const selectedItemName = document.getElementById('selectedItemNameOut');
    const selectedItemExp = document.getElementById('selectedItemExpOut');
    const selectedItemQty = document.getElementById('selectedItemQtyOut');
    const submitBtn = document.getElementById('stockOutSubmit');
    const reasonSelect = document.getElementById('stockOutReason');
    const damageFields = document.getElementById('damageFields');

    async function lookupBarcode(code) {
        if (!code) return;
        
        try {
            const res = await fetch(`stock_out.php?action=lookup&barcode=${encodeURIComponent(code)}`);
            const data = await res.json();
            
            if (data.found && data.items && data.items.length > 0) {
                if (data.items.length > 1) {
                    // Multiple items found - show selector
                    itemSelect.classList.remove('hidden');
                    itemDetails.classList.add('hidden');
                    submitBtn.disabled = true;
                    
                    const container = itemSelect.querySelector('.bg-slate-50');
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
                        `;
                        
                        div.onclick = () => selectItem(item);
                        container.appendChild(div);
                    });
                } else {
                    // Single item - select it directly
                    selectItem(data.items[0]);
                }
            } else {
                // No items found
                itemSelect.classList.add('hidden');
                itemDetails.classList.add('hidden');
                submitBtn.disabled = true;
                alert('No items found with this barcode');
            }
        } catch (e) {
            console.error('Error:', e);
            alert('Error looking up barcode');
        }
    }

    function selectItem(item) {
        itemSelect.classList.add('hidden');
        itemDetails.classList.remove('hidden');
        submitBtn.disabled = false;
        
        selectedItemId.value = item.id;
        selectedItemName.textContent = item.name;
        selectedItemExp.textContent = item.expiration_date ? 
            `Expires: ${item.expiration_date}` : 
            'No expiration set';
        selectedItemQty.textContent = `Current Stock: ${item.quantity}`;

        // Set max quantity that can be removed
        const qtyInput = document.querySelector('[name="quantity"]');
        qtyInput.max = item.quantity || 0;
        qtyInput.value = ''; // Clear previous value
    }

    // Lookup when barcode entered
    let lookupTimeout;
    barcodeInput.addEventListener('input', (e) => {
        clearTimeout(lookupTimeout);
        lookupTimeout = setTimeout(() => lookupBarcode(e.target.value), 300);
    });

    // Handle reason selection
    reasonSelect.addEventListener('change', (e) => {
        if (e.target.value === 'damage') {
            damageFields.classList.remove('hidden');
        } else {
            damageFields.classList.add('hidden');
        }
    });

    // Add form submission handling
    const stockOutForm = document.getElementById('stockOutForm');
    const confirmModal = document.getElementById('confirmStockOutModal');
    const confirmItemName = document.getElementById('confirmItemName');
    const confirmQuantity = document.getElementById('confirmQuantity');
    const confirmReason = document.getElementById('confirmReason');
    const confirmBtn = document.getElementById('confirmStockOutBtn');
    const cancelBtn = document.getElementById('cancelStockOut');

    stockOutForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const itemName = document.getElementById('selectedItemNameOut').textContent;
        const quantity = stockOutForm.querySelector('[name="quantity"]').value;
        const reason = stockOutForm.querySelector('[name="stock_out_reason"]').value;
        
        // Show confirmation details
        confirmItemName.textContent = itemName;
        confirmQuantity.textContent = `Quantity to remove: ${quantity}`;
        confirmReason.textContent = `Reason: ${reason.charAt(0).toUpperCase() + reason.slice(1)}`;
        
        // Show confirmation modal
        confirmModal.classList.remove('hidden');
    });

    // Handle confirmation
    confirmBtn.addEventListener('click', () => {
        confirmModal.classList.add('hidden');
        stockOutForm.submit(); // Actually submit the form
    });

    // Handle cancellation
    cancelBtn.addEventListener('click', () => {
        confirmModal.classList.add('hidden');
    });

    // Add close button handlers
    const closeButtons = document.querySelectorAll('.closeStockOut');
    const stockOutModal = document.getElementById('stockOutModal');

    function closeStockOutModal() {
        stockOutModal.classList.add('hidden');
        confirmModal.classList.add('hidden');
        // Reset form
        stockOutForm.reset();
        itemSelect.classList.add('hidden');
        itemDetails.classList.add('hidden');
        submitBtn.disabled = true;
    }

    // Handle close button clicks
    closeButtons.forEach(button => {
        button.addEventListener('click', closeStockOutModal);
    });

    // Handle outside click
    stockOutModal.addEventListener('click', (e) => {
        if (e.target === stockOutModal) {
            closeStockOutModal();
        }
    });

    // Handle Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeStockOutModal();
        }
    });

    // Replace the openStockOutForExpired function
    window.openStockOutForExpired = async function(button) {
        const barcode = button.dataset.barcode;
        const itemName = button.dataset.name;
        const expiryDate = button.dataset.expiry;
        const currentStock = button.dataset.quantity;
        const itemId = button.dataset.itemId;

        // Show the modal
        stockOutModal.classList.remove('hidden');

        // First lookup the barcode
        try {
            const res = await fetch(`stock_out.php?action=lookup&barcode=${encodeURIComponent(barcode)}`);
            const data = await res.json();
            
            if (data.found && data.items && data.items.length > 0) {
                // Select the first item
                const item = data.items[0];
                
                // Pre-fill the form
                barcodeInput.value = barcode;
                selectedItemId.value = itemId;
                selectedItemName.textContent = itemName;
                selectedItemExp.textContent = `Expires: ${expiryDate}`;
                selectedItemQty.textContent = `Current Stock: ${currentStock}`;

                // Show item details section
                itemDetails.classList.remove('hidden');
                itemSelect.classList.add('hidden');

                // Set reason to expired
                reasonSelect.value = 'expired';
                damageFields.classList.add('hidden');

                // Enable submit button
                submitBtn.disabled = false;
            }
        } catch (e) {
            console.error('Error looking up barcode:', e);
            alert('Error loading item details');
        }
    }

    // Update the lookupBarcode function to return the data
    async function lookupBarcode(code) {
        if (!code) return;
        
        try {
            const res = await fetch(`stock_out.php?action=lookup&barcode=${encodeURIComponent(code)}`);
            const data = await res.json();
            
            if (data.found && data.items && data.items.length > 0) {
                if (data.items.length > 1) {
                    // Multiple items found - show selector
                    itemSelect.classList.remove('hidden');
                    itemDetails.classList.add('hidden');
                    submitBtn.disabled = true;
                    
                    const container = itemSelect.querySelector('.bg-slate-50');
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
                        `;
                        
                        div.onclick = () => selectItem(item);
                        container.appendChild(div);
                    });
                } else {
                    // Single item - select it directly
                    selectItem(data.items[0]);
                }
                return data;
            } else {
                // No items found
                itemSelect.classList.add('hidden');
                itemDetails.classList.add('hidden');
                submitBtn.disabled = true;
                alert('No items found with this barcode');
            }
        } catch (e) {
            console.error('Error:', e);
            alert('Error looking up barcode');
        }
        return null;
    }
});
</script>
document.addEventListener("DOMContentLoaded", () => {
  // Elements
  const modalEdit = document.getElementById("editModal");
  const editButtons = document.querySelectorAll(".editBtn");
  const closeModalButtons = document.querySelectorAll(".closeModal");

  // Stock modals/buttons
  const stockInModal = document.getElementById("stockInModal");
  const stockOutModal = document.getElementById("stockOutModal");
  const openStockIn = document.getElementById("openStockIn");
  const openStockOut = document.getElementById("openStockOut");
  const closeStockInBtns = document.querySelectorAll(".closeStockIn");
  const closeStockOutBtns = document.querySelectorAll(".closeStock");

  // Scanners (will lazy-load Quagga when used)
  const scannerIn = document.getElementById('scanner-in');
  const scannerOut = document.getElementById('scanner-out');
  const startScanIn = document.getElementById('start-scan-in');
  const stopScanIn = document.getElementById('stop-scan-in');
  const startScanOut = document.getElementById('start-scan-out');
  const stopScanOut = document.getElementById('stop-scan-out');
  const barcodeIn = document.getElementById('barcode-in');
  const barcodeOut = document.getElementById('barcode-out');

  // Edit modal open
  editButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      document.getElementById("edit_id").value = btn.dataset.id;
      document.getElementById("edit_barcode").value = btn.dataset.barcode;
      document.getElementById("edit_name").value = btn.dataset.name;
      document.getElementById("edit_category_id").value = btn.dataset.categoryId;
      document.getElementById("edit_quantity").value = btn.dataset.quantity;
      document.getElementById("edit_price").value = btn.dataset.price;
      document.getElementById("edit_expiration_date").value = btn.dataset.expirationDate;
      modalEdit.classList.remove("hidden");
    });
  });

  closeModalButtons.forEach(btn => btn.addEventListener("click", () => modalEdit.classList.add("hidden")));
  modalEdit.addEventListener("click", e => { if (e.target === modalEdit) modalEdit.classList.add("hidden"); });

  // Stock modal open/close
  openStockIn.addEventListener('click', () => stockInModal.classList.remove('hidden'));
  openStockOut.addEventListener('click', () => stockOutModal.classList.remove('hidden'));
  closeStockInBtns.forEach(b => b.addEventListener('click', () => stopScanner('in') || stockInModal.classList.add('hidden')));
  closeStockOutBtns.forEach(b => b.addEventListener('click', () => stopScanner('out') || stockOutModal.classList.add('hidden')));
  stockInModal.addEventListener('click', e => { if (e.target === stockInModal) { stopScanner('in'); stockInModal.classList.add('hidden'); } });
  stockOutModal.addEventListener('click', e => { if (e.target === stockOutModal) { stopScanner('out'); stockOutModal.classList.add('hidden'); } });

  // ---- Quagga loader (lazy) ----
  let Quagga = null;
  function ensureQuagga(callback) {
    if (Quagga) return callback();
    const s = document.createElement('script');
    s.src = "https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js";
    s.onload = () => { Quagga = window.Quagga; callback(); };
    s.onerror = () => console.error('Failed to load Quagga');
    document.head.appendChild(s);
  }

  // Scanner control per modal
  const running = { in: false, out: false };
  function startScanner(kind) {
    ensureQuagga(() => {
      const target = (kind === 'in') ? scannerIn : scannerOut;
      const constraints = { facingMode: "environment" };
      target.classList.remove('hidden');
      running[kind] = true;
      (kind === 'in') ? startScanIn.style.display = 'none' : startScanOut.style.display = 'none';
      (kind === 'in') ? stopScanIn.style.display = 'inline-flex' : stopScanOut.style.display = 'inline-flex';

      Quagga.init({
        inputStream: {
          type: "LiveStream",
          target: target,
          constraints: constraints
        },
        decoder: { readers: ["ean_reader", "code_128_reader"] }
      }, function(err) {
        if (err) {
          console.error(err);
          stopScanner(kind);
          return;
        }
        Quagga.start();
      });

      Quagga.onDetected(function(data) {
        const code = data && data.codeResult && data.codeResult.code;
        if (code) {
          if (kind === 'in') barcodeIn.value = code; else barcodeOut.value = code;
          stopScanner(kind);
        }
      });
    });
  }

  function stopScanner(kind) {
    if (!Quagga) {
      // just reset UI
      if (kind === 'in') {
        scannerIn.classList.add('hidden');
        startScanIn.style.display = 'inline-flex';
        stopScanIn.style.display = 'none';
      } else {
        scannerOut.classList.add('hidden');
        startScanOut.style.display = 'inline-flex';
        stopScanOut.style.display = 'none';
      }
      running[kind] = false;
      return;
    }
    try {
      Quagga.stop();
    } catch(e) { /* ignore */ }
    if (kind === 'in') {
      scannerIn.classList.add('hidden');
      startScanIn.style.display = 'inline-flex';
      stopScanIn.style.display = 'none';
    } else {
      scannerOut.classList.add('hidden');
      startScanOut.style.display = 'inline-flex';
      stopScanOut.style.display = 'none';
    }
    running[kind] = false;
  }

  // attach scanner buttons
  startScanIn && startScanIn.addEventListener('click', () => startScanner('in'));
  stopScanIn && stopScanIn.addEventListener('click', () => stopScanner('in'));
  startScanOut && startScanOut.addEventListener('click', () => startScanner('out'));
  stopScanOut && stopScanOut.addEventListener('click', () => stopScanner('out'));

  // ---- Filtering + Sorting logic (moved from inline) ----
  const searchInput = document.getElementById('searchInput');
  const stockFilter = document.getElementById('stockFilterDropdown');
  const categoryFilter = document.getElementById('categoryFilterDropdown');
  const expirationFilter = document.getElementById('expirationFilterDropdown');
  const sortDropdown = document.getElementById('sortDropdown');
  const tbody = document.getElementById('itemsTbody');

  const originalRows = Array.from(tbody.querySelectorAll('tr'));
  const lowStockThreshold = parseInt(document.getElementById('app').dataset.lowstock || '5', 10);

  function applyFiltersAndSort() {
    const q = searchInput.value.trim().toLowerCase();
    const stockVal = stockFilter.value;
    const categoryVal = categoryFilter.value;
    const expVal = expirationFilter.value;
    const sortVal = sortDropdown.value;

    let filtered = originalRows.filter(row => {
      const name = (row.dataset.name || '').toLowerCase();
      const category = (row.dataset.category || '').toLowerCase();
      const quantity = parseInt(row.dataset.quantity || '0', 10);
      const expirationStatus = (row.dataset.expirationStatus || row.getAttribute('data-expiration-status') || '').toLowerCase();

      if (q) {
        if (!name.includes(q) && !category.includes(q)) return false;
      }
      if (stockVal === 'low_stock' && !(quantity <= lowStockThreshold)) return false;
      if (stockVal === 'in_stock' && !(quantity > lowStockThreshold)) return false;
      if (categoryVal !== 'all' && categoryVal.toLowerCase() !== category.toLowerCase()) return false;
      if (expVal === 'expired' && expirationStatus !== 'expired') return false;
      if (expVal === 'expiring_soon' && expirationStatus !== 'expiring_soon') return false;
      if (expVal === 'valid' && expirationStatus !== 'valid') return false;
      return true;
    });

    if (sortVal) {
      filtered.sort((a, b) => {
        if (sortVal.includes('name')) {
          const an = (a.dataset.name || '').toLowerCase();
          const bn = (b.dataset.name || '').toLowerCase();
          if (an < bn) return sortVal === 'name_asc' ? -1 : 1;
          if (an > bn) return sortVal === 'name_asc' ? 1 : -1;
          return 0;
        }
        if (sortVal.includes('quantity')) {
          const aq = parseInt(a.dataset.quantity || '0', 10);
          const bq = parseInt(b.dataset.quantity || '0', 10);
          return sortVal === 'quantity_asc' ? aq - bq : bq - aq;
        }
        if (sortVal.includes('price')) {
          const ap = parseFloat(a.dataset.price || '0');
          const bp = parseFloat(b.dataset.price || '0');
          return sortVal === 'price_asc' ? ap - bp : bp - ap;
        }
        if (sortVal.includes('expires')) {
          const aDateText = a.querySelector('td:nth-child(6)')?.textContent.trim() || '';
          const bDateText = b.querySelector('td:nth-child(6)')?.textContent.trim() || '';
          const aTs = Date.parse(aDateText) || (sortVal === 'expires_asc' ? Infinity : -Infinity);
          const bTs = Date.parse(bDateText) || (sortVal === 'expires_asc' ? Infinity : -Infinity);
          return sortVal === 'expires_asc' ? aTs - bTs : bTs - aTs;
        }
        return 0;
      });
    }

    tbody.innerHTML = '';
    filtered.forEach(r => tbody.appendChild(r));
  }

  [searchInput].forEach(el => el.addEventListener('input', applyFiltersAndSort));
  [stockFilter, categoryFilter, expirationFilter, sortDropdown].forEach(el => el.addEventListener('change', applyFiltersAndSort));
  applyFiltersAndSort();

  // Close dropdowns / modals on Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      modalEdit.classList.add('hidden');
      stockInModal.classList.add('hidden');
      stockOutModal.classList.add('hidden');
      stopScanner('in'); stopScanner('out');
    }
  });

  // Store original table HTML for regular items
  let originalTableHtml = '';

  // Store the original regular items table HTML
  originalTableHtml = itemsTable.innerHTML;

  // Update the view buttons click handlers
  const viewBtns = document.querySelectorAll('.table-view-btn');
  
  viewBtns.forEach(btn => {
      btn.addEventListener('click', () => {
          // Update button styles
          viewBtns.forEach(b => {
              b.classList.remove('active', 'bg-amber-500', 'text-white');
              b.classList.add('bg-slate-100', 'text-slate-700');
          });
          
          btn.classList.add('active', 'bg-amber-500', 'text-white');
          btn.classList.remove('bg-slate-100', 'text-slate-700');
          
          if (btn.dataset.table === 'items') {
              // For regular items, use the stored original HTML
              itemsTable.innerHTML = originalTableHtml;
              // Reapply any filters and sorting
              if (typeof applyFiltersAndSort === 'function') {
                  applyFiltersAndSort();
              }
          } else {
              // Fetch damaged or expired items
              fetch(`get_items_table.php?type=${btn.dataset.table}`)
                  .then(res => res.text())
                  .then(html => {
                      itemsTable.innerHTML = html;
                  })
                  .catch(err => {
                      console.error('Error fetching table:', err);
                      itemsTable.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-red-600">Error loading data</td></tr>';
                  });
          }
      });
  });

  // ...existing code...

function sortItems() {
    const sortValue = document.getElementById('sortDropdown').value;
    const rows = Array.from(document.getElementById('itemsTbody').getElementsByTagName('tr'));

    rows.sort((a, b) => {
        switch(sortValue) {
            case 'latest_added':
                const dateAddedA = new Date(a.dataset.dateAdded || 0);
                const dateAddedB = new Date(b.dataset.dateAdded || 0);
                return dateAddedB - dateAddedA;

            case 'latest_stock':
                const lastStockInA = new Date(a.dataset.lastStockIn || 0);
                const lastStockInB = new Date(b.dataset.lastStockIn || 0);
                return lastStockInB - lastStockInA;

            case 'name_asc':
                return a.dataset.name.localeCompare(b.dataset.name);

            case 'name_desc':
                return b.dataset.name.localeCompare(a.dataset.name);

            case 'quantity_asc':
                return parseInt(a.dataset.quantity) - parseInt(b.dataset.quantity);

            case 'quantity_desc':
                return parseInt(b.dataset.quantity) - parseInt(a.dataset.quantity);

            case 'price_asc':
                return parseFloat(a.dataset.retail) - parseFloat(b.dataset.retail);

            case 'price_desc':
                return parseFloat(b.dataset.retail) - parseFloat(a.dataset.retail);

            default:
                return 0;
        }
    });

    const tbody = document.getElementById('itemsTbody');
    tbody.innerHTML = '';
    rows.forEach(row => tbody.appendChild(row));
}

// Add event listener for sort dropdown
document.getElementById('sortDropdown').addEventListener('change', function() {
    sortItems();
});

// Make sure all filters work together
function applyFilters() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const stockFilter = document.getElementById('stockFilterDropdown').value;
    const categoryFilter = document.getElementById('categoryFilterDropdown').value;
    const expirationFilter = document.getElementById('expirationFilterDropdown').value;
    const lowStockThreshold = parseInt(document.getElementById('app').dataset.lowstock);

    const rows = Array.from(document.getElementById('itemsTbody').getElementsByTagName('tr'));

    rows.forEach(row => {
        const name = row.dataset.name.toLowerCase();
        const category = row.dataset.category.toLowerCase();
        const quantity = parseInt(row.dataset.quantity);
        const expirationStatus = row.dataset.expirationStatus;

        const matchesSearch = name.includes(searchTerm) || category.includes(searchTerm);
        
        const matchesStock = stockFilter === 'all' || 
            (stockFilter === 'low_stock' && quantity <= lowStockThreshold) ||
            (stockFilter === 'in_stock' && quantity > lowStockThreshold);

        const matchesCategory = categoryFilter === 'all' || category === categoryFilter.toLowerCase();
        
        const matchesExpiration = expirationFilter === 'all' || expirationStatus === expirationFilter;

        row.style.display = matchesSearch && matchesStock && matchesCategory && matchesExpiration ? '' : 'none';
    });

    // Apply current sort after filtering
    sortItems();
}

// Add event listeners for all filters
document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('stockFilterDropdown').addEventListener('change', applyFilters);
document.getElementById('categoryFilterDropdown').addEventListener('change', applyFilters);
document.getElementById('expirationFilterDropdown').addEventListener('change', applyFilters);

// Initial sort and filter application
document.addEventListener('DOMContentLoaded', () => {
    applyFilters();
});


// Add this function to handle expired item clicks
function openStockOutForExpired(button) {
    const stockOutModal = document.getElementById('stockOutModal');
    const barcodeInput = document.getElementById('barcode-out');
    const itemIdInput = document.getElementById('selected-item-id-out');
    const reasonSelect = document.getElementById('stockOutReason');
    const selectedItemName = document.getElementById('selectedItemNameOut');
    const selectedItemExp = document.getElementById('selectedItemExpOut');
    const selectedItemQty = document.getElementById('selectedItemQtyOut');
    
    // Set the values
    barcodeInput.value = button.dataset.barcode;
    itemIdInput.value = button.dataset.itemId;
    selectedItemName.textContent = button.dataset.name;
    selectedItemExp.textContent = `Expires: ${button.dataset.expiry}`;
    selectedItemQty.textContent = `Current Stock: ${button.dataset.quantity}`;
    
    // Set reason based on expiration status
    if (button.classList.contains('bg-red-500')) {
        reasonSelect.value = 'expired';
    } else if (button.classList.contains('bg-amber-100')) {
        reasonSelect.value = 'expired';
    }
    
    // Show modal
    stockOutModal.classList.remove('hidden');
    
    // Trigger reason change event to show/hide appropriate fields
    reasonSelect.dispatchEvent(new Event('change'));
}

// Make sure this function is available globally
window.openStockOutForExpired = openStockOutForExpired;



});

// Add these functions to your existing items.js file
function editThreshold(button) {
    const modal = document.getElementById('thresholdModal');
    const nameEl = document.getElementById('thresholdItemName');
    const idInput = document.getElementById('thresholdItemId');
    const thresholdInput = document.getElementById('thresholdValue');
    
    // Set modal values
    nameEl.textContent = button.dataset.name;
    idInput.value = button.dataset.id;
    thresholdInput.value = button.dataset.threshold;
    
    // Show modal
    modal.classList.remove('hidden');
}

function closeThresholdModal() {
    document.getElementById('thresholdModal').classList.add('hidden');
}

// Add to your existing DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', () => {
    // ... existing code ...

    // Threshold modal form submission
    const thresholdForm = document.getElementById('thresholdForm');
    if (thresholdForm) {
        thresholdForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(thresholdForm);
            try {
                const response = await fetch('update_threshold.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    // Close modal
                    closeThresholdModal();
                    
                    // Show success message
                    alert('Threshold updated successfully');
                    
                    // Reload page to show updated values
                    window.location.reload();
                } else {
                    throw new Error('Failed to update threshold');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to update threshold. Please try again.');
            }
        });
    }
});

    // Close modal on outside click
    const thresholdModal = document.getElementById('thresholdModal');
    if (thresholdModal) {
        thresholdModal.addEventListener('click', (e) => {
            if (e.target === thresholdModal) {
                closeThresholdModal();
            }
        });
    }

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeThresholdModal();
        }
    });

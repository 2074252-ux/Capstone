document.addEventListener('DOMContentLoaded', () => {
  const customSelect = document.getElementById('customSelect');
  const barcodeInput = document.getElementById('add_barcode');
  const nameInput = document.getElementById('add_name');
  const startScanBtn = document.getElementById('start-scan');
  const stopScanBtn = document.getElementById('stop-scan');
  const scannerEl = document.getElementById('scanner');
  const addForm = document.getElementById('addItemForm');
  const dupModal = document.getElementById('dupModal');
  const dupList = document.getElementById('dupList');
  const dupCancel = document.getElementById('dupCancel');
  const dupProceed = document.getElementById('dupProceed');
  const confirmInput = document.getElementById('confirm_duplicate');

  // Custom select auto-fill
  customSelect && customSelect.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const code = opt.value || '';
    const nm = opt.getAttribute('data-name') || '';
    if (code) {
      barcodeInput.value = code;
      if (nm) nameInput.value = nm;
      // trigger auto-lookup to show existing entries if any
      fetchExistingAndMaybePrompt(code);
    }
  });

  // ---- Handheld scanner: keyboard buffer detector ----
  (function setupScannerBuffer() {
    let buffer = '';
    let lastTime = Date.now();
    const maxInterval = 60; // ms between keystrokes to consider as scanner
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        if (buffer.length > 0) {
          handleScannedBarcode(buffer);
          buffer = '';
        }
        return;
      }
      if (e.key.length !== 1) return;
      const now = Date.now();
      if (now - lastTime > maxInterval) buffer = '';
      lastTime = now;
      buffer += e.key;
      if (buffer.length > 128) buffer = buffer.slice(-128);
    }, true);
  })();

  function handleScannedBarcode(code) {
    if (!code) return;
    barcodeInput.value = code;
    lookupBarcodeForName(code);
    flashFeedback();
    fetchExistingAndMaybePrompt(code);
  }

  function flashFeedback() {
    const el = scannerEl;
    if (!el) return;
    el.classList.remove('hidden');
    el.style.boxShadow = '0 0 0 3px rgba(250,204,21,0.25)';
    setTimeout(() => {
      el.classList.add('hidden');
      el.style.boxShadow = '';
    }, 600);
  }

  // ---- Camera scanner (Quagga) controls ----
  let quaggaRunning = false;
  function startQuagga() {
    if (!window.Quagga || quaggaRunning) return;
    scannerEl.classList.remove('hidden');
    startScanBtn.style.display = 'none';
    stopScanBtn.style.display = 'inline-flex';
    try {
      Quagga.init({
        inputStream: { type: "LiveStream", target: scannerEl, constraints: { facingMode: "environment" } },
        decoder: { readers: ["ean_reader", "code_128_reader"] }
      }, function (err) {
        if (err) { console.error('Quagga init error', err); stopQuagga(); return; }
        Quagga.start(); quaggaRunning = true;
      });
      Quagga.onDetected((data) => {
        const code = data && data.codeResult && data.codeResult.code;
        if (code) { handleScannedBarcode(code); stopQuagga(); }
      });
    } catch (e) { console.error(e); stopQuagga(); }
  }

  function stopQuagga() {
    if (window.Quagga && quaggaRunning) {
      try { Quagga.stop(); } catch (e) {}
    }
    quaggaRunning = false;
    scannerEl.classList.add('hidden');
    startScanBtn.style.display = 'inline-flex';
    stopScanBtn.style.display = 'none';
  }

  startScanBtn && startScanBtn.addEventListener('click', () => startQuagga());
  stopScanBtn && stopScanBtn.addEventListener('click', () => stopQuagga());

  // --- server lookup to auto-fill name ---
  async function lookupBarcodeForName(code) {
    if (!code) return;
    try {
      const res = await fetch(`add_item.php?action=find&barcode=${encodeURIComponent(code)}`);
      const json = await res.json();
      if (json.found && json.items && json.items.length > 0) {
        nameInput.value = json.items[0].name || nameInput.value;
      }
    } catch (e) { console.error(e); }
  }

  // --- check existing items and, if found, prompt user before submit ---
  async function fetchExistingAndMaybePrompt(code) {
    if (!code) return false;
    try {
        const res = await fetch(`add_item.php?action=find&barcode=${encodeURIComponent(code)}`);
        const json = await res.json();
        
        if (json.found && json.items && json.items.length > 0) {
            // If it's from custom_barcodes, don't show duplicate warning
            if (json.source === 'custom') {
                nameInput.value = json.items[0].name || '';
                return false; // Allow direct submission
            }

            // Update modal content
            document.getElementById('dupBarcode').textContent = code;
            
            // Show existing items in modal
            dupList.innerHTML = '';
            json.items.forEach(i => {
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2 p-2 bg-white/50 rounded border border-amber-200';
                
                // Item details
                const details = document.createElement('div');
                details.className = 'flex-1';
                
                const name = document.createElement('div');
                name.className = 'font-medium text-amber-900';
                name.textContent = i.name;
                
                const info = document.createElement('div');
                info.className = 'text-xs text-amber-800';
                info.textContent = `Quantity: ${i.quantity || 0}`;
                if (i.expiration_date) {
                    info.textContent += ` · Expires: ${i.expiration_date}`;
                }
                
                details.appendChild(name);
                details.appendChild(info);
                div.appendChild(details);
                
                // Price info if available
                if (i.wholesale_price || i.retail_price) {
                    const prices = document.createElement('div');
                    prices.className = 'text-xs text-amber-800 text-right';
                    if (i.wholesale_price) {
                        prices.innerHTML += `W: ₱${Number(i.wholesale_price).toFixed(2)}<br>`;
                    }
                    if (i.retail_price) {
                        prices.innerHTML += `R: ₱${Number(i.retail_price).toFixed(2)}`;
                    }
                    div.appendChild(prices);
                }
                
                dupList.appendChild(div);
            });

            // Auto-fill name from first matching item
            if (json.items[0] && json.items[0].name) {
                nameInput.value = json.items[0].name;
            }

            // Show modal with animation
            dupModal.classList.remove('hidden');
            
            // Return promise that resolves when user makes a choice
            return new Promise((resolve) => {
                dupCancel.onclick = () => {
                    dupModal.classList.add('hidden');
                    confirmInput.value = "0";
                    resolve(false);
                };
                
                dupProceed.onclick = () => {
                    dupModal.classList.add('hidden');
                    confirmInput.value = "1";
                    resolve(true);
                };

                // Close on backdrop click
                dupModal.querySelector('.backdrop-blur-sm').onclick = () => {
                    dupModal.classList.add('hidden');
                    confirmInput.value = "0";
                    resolve(false);
                };
            });
        }
        return false;
    } catch (e) {
        console.error(e);
        return false;
    }
  }

  // intercept submit to check duplicates
  addForm && addForm.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    
    const code = barcodeInput.value.trim();
    const name = nameInput.value.trim();
    
    // Basic validation
    if (!code || !name) {
        alert('Please fill in at least the barcode and name fields');
        return;
    }
    
    // If already confirmed or no barcode, submit directly
    if (confirmInput.value === "1" || !code) {
        addForm.submit();
        return;
    }
    
    // Check for duplicates
    try {
        const response = await fetch(`add_item.php?action=find&barcode=${encodeURIComponent(code)}`);
        const data = await response.json();
        
        if (data.found && data.items && data.items.length > 0) {
            // Show duplicate warning modal
            document.getElementById('dupBarcode').textContent = code;
            dupList.innerHTML = '';
            
            data.items.forEach(item => {
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2 p-2 bg-white/50 rounded border border-amber-200';
                div.innerHTML = `
                    <div class="flex-1">
                        <div class="font-medium text-amber-900">${item.name}</div>
                        <div class="text-xs text-amber-800">
                            Quantity: ${item.quantity || 0}
                            ${item.expiration_date ? ` · Expires: ${item.expiration_date}` : ''}
                        </div>
                    </div>
                `;
                dupList.appendChild(div);
            });
            
            dupModal.classList.remove('hidden');
            
            // Wait for user decision
            const shouldProceed = await new Promise((resolve) => {
                dupCancel.onclick = () => {
                    dupModal.classList.add('hidden');
                    confirmInput.value = "0";
                    resolve(false);
                };
                
                dupProceed.onclick = () => {
                    dupModal.classList.add('hidden');
                    confirmInput.value = "1";
                    resolve(true);
                };
            });
            
            if (shouldProceed) {
                addForm.submit();
            }
        } else {
            // No duplicates found, submit directly
            addForm.submit();
        }
    } catch (error) {
        console.error('Error checking duplicates:', error);
        // On error, allow submission
        addForm.submit();
    }
  });

  window._addItem = { handleScannedBarcode, startQuagga, stopQuagga };
});

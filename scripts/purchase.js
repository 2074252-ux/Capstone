document.addEventListener("DOMContentLoaded", function() {
    // Get DOM elements
    const barcodeInput = document.getElementById('barcode');
    const productNameInput = document.getElementById('product_name');
    const priceInput = document.getElementById('price');
    const quantityInput = document.getElementById('quantity');
    const addItemForm = document.getElementById('addItemForm');
    const cartTableBody = document.getElementById('cart-table-body');
    const totalElement = document.getElementById('total');
    const cartDataInput = document.getElementById('cart_data');
    
    // Scanner elements
    const startScanBtn = document.getElementById('start-scan');
    const stopScanBtn = document.getElementById('stop-scan');
    const closeScannerBtn = document.getElementById('close-scanner');
    const scannerUI = document.getElementById('interactive');

    let isScanning = false;

    // Camera scanning setup
    function initScanner() {
        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector("#interactive video"),
                constraints: {
                    facingMode: "environment",
                    width: 640,
                    height: 480
                },
                area: {
                    top: "25%",
                    right: "25%",
                    left: "25%",
                    bottom: "25%",
                }
            },
            locate: true,
            numOfWorkers: navigator.hardwareConcurrency || 4,
            decoder: {
                readers: [
                    "ean_reader",
                    "ean_8_reader",
                    "code_128_reader",
                    "code_39_reader",
                    "upc_reader"
                ],
                debug: {
                    showCanvas: true,
                    showPatches: true,
                    showFoundPatches: true,
                    showSkeleton: true,
                    showLabels: true,
                    showPatchLabels: true,
                    showRemainingPatchLabels: true,
                    boxFromPatches: {
                        showTransformed: true,
                        showTransformedBox: true,
                        showBB: true
                    }
                }
            }
        }, function(err) {
            if (err) {
                console.error("Scanner initialization error:", err);
                alert("Error accessing camera. Please check permissions.");
                return;
            }
            console.log("Scanner initialized successfully");
            Quagga.start();
            isScanning = true;
            scannerUI.classList.remove('hidden');
            stopScanBtn.style.display = 'inline-block';
            startScanBtn.style.display = 'none';
        });

        // Handle successful scans
        Quagga.onDetected(function(result) {
            if (result.codeResult.code) {
                const code = result.codeResult.code;
                console.log("Barcode detected:", code);
                barcodeInput.value = code;
                stopScanner();
                fetchProduct(code);
            }
        });

        // Handle processing errors
        Quagga.onProcessed(function(result) {
            const drawingCtx = Quagga.canvas.ctx.overlay;
            const drawingCanvas = Quagga.canvas.dom.overlay;

            if (result) {
                if (result.boxes) {
                    drawingCtx.clearRect(0, 0, parseInt(drawingCanvas.getAttribute("width")), parseInt(drawingCanvas.getAttribute("height")));
                    result.boxes.filter(function(box) {
                        return box !== result.box;
                    }).forEach(function(box) {
                        Quagga.ImageDebug.drawPath(box, { x: 0, y: 1 }, drawingCtx, { color: "green", lineWidth: 2 });
                    });
                }

                if (result.box) {
                    Quagga.ImageDebug.drawPath(result.box, { x: 0, y: 1 }, drawingCtx, { color: "#00F", lineWidth: 2 });
                }

                if (result.codeResult && result.codeResult.code) {
                    Quagga.ImageDebug.drawPath(result.line, { x: 'x', y: 'y' }, drawingCtx, { color: 'red', lineWidth: 3 });
                }
            }
        });
    }

    // Start scanner button click handler
    startScanBtn.addEventListener('click', function() {
        console.log("Starting scanner...");
        initScanner();
    });

    // Stop scanner function
    function stopScanner() {
        if (isScanning) {
            console.log("Stopping scanner...");
            Quagga.stop();
            isScanning = false;
            scannerUI.classList.add('hidden');
            stopScanBtn.style.display = 'none';
            startScanBtn.style.display = 'inline-block';
        }
    }

    // Stop scanner button click handlers
    stopScanBtn.addEventListener('click', stopScanner);
    closeScannerBtn.addEventListener('click', stopScanner);

    // Handle page visibility change
    document.addEventListener('visibilitychange', function() {
        if (document.hidden && isScanning) {
            stopScanner();
        }
    });

    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (isScanning) {
            Quagga.stop();
        }
    });
    
    let cart = [];

    // Listen for barcode input
    barcodeInput.addEventListener('input', function() {
        const barcode = this.value.trim();
        if (barcode.length >= 8) { // Minimum barcode length
            fetchProduct(barcode);
        }
    });

    // Fetch product details when barcode is entered
    function fetchProduct(barcode) {
        if (!barcode) return;
        
        console.log('Fetching product:', barcode); // Debug log
        
        fetch(`get_product.php?barcode=${encodeURIComponent(barcode.trim())}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Product data:', data); // Debug log
                
                if (data.success) {
                    // Auto-fill the form fields
                    productNameInput.value = data.name;
                    priceInput.value = data.retail_price || data.price || 0;
                    // Store wholesale and retail prices as data attributes
                    priceInput.dataset.wholesalePrice = data.wholesale_price || 0;
                    priceInput.dataset.retailPrice = data.retail_price || data.price || 0;
                    quantityInput.value = "1";
                    quantityInput.focus();
                    quantityInput.select();
                } else {
                    showToast(data.message || "Product not found", "error");
                    clearForm();
                }
            })
            .catch(error => {
                console.error('Fetch error:', error); // Debug log
                showToast("Error fetching product. Please try again.", "error");
                clearForm();
            });
    }

    // Handle form submission (Proceed button)
    addItemForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const barcode = barcodeInput.value.trim();
        const name = productNameInput.value.trim();
        const price = parseFloat(priceInput.value) || 0;
        const quantity = parseInt(quantityInput.value) || 0;
        // Add these values as data attributes to the form or hidden inputs
        const wholesalePrice = parseFloat(priceInput.dataset.wholesalePrice) || 0;
        const retailPrice = parseFloat(priceInput.dataset.retailPrice) || 0;

        // Validation
        if (!barcode || !name || price <= 0 || quantity <= 0) {
            showToast("Please fill all fields correctly", "error");
            return;
        }

        // Add to cart with wholesale and retail prices
        const item = {
            barcode: barcode,
            name: name,
            price: price,
            quantity: quantity,
            subtotal: price * quantity,
            wholesale_price: wholesalePrice,
            retail_price: retailPrice
        };

        // Check if item already exists in cart
        const existingIndex = cart.findIndex(i => i.barcode === barcode);
        if (existingIndex !== -1) {
            cart[existingIndex].quantity += quantity;
            cart[existingIndex].subtotal = cart[existingIndex].price * cart[existingIndex].quantity;
        } else {
            cart.push(item);
        }

        // Update display
        renderCart();
        clearForm();
        barcodeInput.focus();
    });

    // Render cart items
    function renderCart() {
        cartTableBody.innerHTML = '';
        let total = 0;

        cart.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-6 py-4">${escapeHtml(item.barcode)}</td>
                <td class="px-6 py-4">${escapeHtml(item.name)}</td>
                <td class="px-6 py-4 text-right">₱${item.price.toFixed(2)}</td>
                <td class="px-6 py-4 text-right">${item.quantity}</td>
                <td class="px-6 py-4 text-right">₱${item.subtotal.toFixed(2)}</td>
                <td class="px-6 py-4 text-center">
                    <button type="button" onclick="removeItem(${index})" 
                            class="text-red-500 hover:text-red-700">
                        Remove
                    </button>
                </td>
            `;
            cartTableBody.appendChild(tr);
            total += item.subtotal;
        });

        // Update total and cart data
        totalElement.textContent = `₱${total.toFixed(2)}`;
        cartDataInput.value = JSON.stringify(cart);
    }

    // Clear form fields
    function clearForm() {
        barcodeInput.value = '';
        productNameInput.value = '';
        priceInput.value = '';
        priceInput.dataset.wholesalePrice = '';
        priceInput.dataset.retailPrice = '';
        quantityInput.value = '1';
    }

    // Remove item from cart
    window.removeItem = function(index) {
        cart.splice(index, 1);
        renderCart();
    }

    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Toast notification
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg ${
            type === 'error' ? 'bg-red-500' : 'bg-emerald-500'
        } text-white`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
});

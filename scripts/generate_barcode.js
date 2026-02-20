// scripts/generate_barcode.js

document.addEventListener('DOMContentLoaded', () => {
    // --- 1. Modal Toggle Logic ---
    const modal = document.getElementById('barcodeModal');
    const openModalBtn = document.getElementById('openModal');
    const closeModalBtn = document.getElementById('closeModal');

    if (modal && openModalBtn && closeModalBtn) {
        openModalBtn.addEventListener('click', () => modal.classList.remove('hidden'));
        closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));

        // Close on outside click
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    }


    // --- 2. Combined Search + Unit Filter ---
    const searchInput = document.getElementById('searchInput');
    const unitFilter = document.getElementById('unitFilter');
    // NOTE: This assumes the rows are direct children of the table body/tbody
    const rows = document.querySelectorAll('#barcodeTable tbody tr'); 

    if (searchInput && unitFilter && rows.length > 0) {
        function filterTable() {
            const searchValue = searchInput.value.toLowerCase();
            const unitValue = unitFilter.value.toLowerCase();

            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                // Assumes the unit is in the third column (td:nth-child(3))
                const unitText = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || ""; 

                const matchesSearch = rowText.includes(searchValue);
                // Matches if no unit is selected ("") OR the unit text includes the selected unit value
                const matchesUnit = unitValue === "" || unitText.includes(unitValue); 

                row.style.display = matchesSearch && matchesUnit ? "" : "none";
            });
        }

        searchInput.addEventListener('input', filterTable);
        unitFilter.addEventListener('change', filterTable);
    }
});


// --- 3. Download Barcode Function (Needs to be global/attached to window for inline HTML calls) ---
window.downloadBarcode = function(code, id) {
    const svg = document.querySelector(`#barcode${id}`);
    if (!svg) {
        console.error(`SVG element with ID barcode${id} not found.`);
        return;
    }

    // Convert SVG to PNG using Canvas
    const serializer = new XMLSerializer();
    const svgData = serializer.serializeToString(svg);

    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");
    const img = new Image();
    const svgBlob = new Blob([svgData], { type: "image/svg+xml;charset=utf-8" });
    const url = URL.createObjectURL(svgBlob);

    img.onload = function() {
        // Set a high resolution (e.g., 3x scale) for a better quality PNG
        const scaleFactor = 3; 
        canvas.width = img.width * scaleFactor;
        canvas.height = img.height * scaleFactor;
        
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        
        const pngUrl = canvas.toDataURL("image/png");
        const downloadLink = document.createElement("a");
        
        downloadLink.href = pngUrl;
        downloadLink.download = code + ".png";
        downloadLink.click();
        
        URL.revokeObjectURL(url); // Clean up the object URL
    };
    img.src = url;
}
document.addEventListener('DOMContentLoaded', () => {
    // Select all tab buttons and content sections
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    // Function to handle the tab switching logic
    function switchTab(targetTabId) {
        // 1. Hide all content sections and deactivate all buttons
        tabContents.forEach(content => {
            content.classList.add('hidden');
        });

        tabButtons.forEach(button => {
            button.classList.remove('bg-sky-600', 'text-white');
            button.classList.add('bg-white', 'text-slate-700', 'hover:bg-slate-100');
        });

        // 2. Show the selected content section
        const targetContent = document.getElementById(targetTabId);
        if (targetContent) {
            targetContent.classList.remove('hidden');
        }

        // 3. Activate the corresponding button
        const targetButton = document.querySelector(`.tab-button[data-target="${targetTabId}"]`);
        if (targetButton) {
            targetButton.classList.remove('bg-white', 'text-slate-700', 'hover:bg-slate-100');
            targetButton.classList.add('bg-sky-600', 'text-white');
        }
    }

    // Attach click listeners to all buttons
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetTabId = button.getAttribute('data-target');
            switchTab(targetTabId);
        });
    });

    // Set the initial tab (Stock In) to be active on page load
    switchTab('stockInContent');

    // Search and filter functionality for Stock In
    function setupStockInFilters() {
        const searchInput = document.getElementById('stockInSearch');
        const dateFilter = document.getElementById('stockInDateFilter');
        const tbody = document.getElementById('stockInTableBody');
        
        if (!tbody) return;
        
        const rows = Array.from(tbody.getElementsByTagName('tr'));
        
        function filterRows() {
            const searchTerm = searchInput?.value?.toLowerCase() || '';
            const filterDate = dateFilter?.value || '';

            rows.forEach(row => {
                const cells = Array.from(row.getElementsByTagName('td'));
                if (cells.length === 0) return;

                const itemName = cells[0].textContent.toLowerCase();
                const category = cells[1].textContent.toLowerCase();
                const barcode = cells[2].textContent.toLowerCase();
                const rowDate = cells[4].textContent.split(' ')[0]; // Get just the date part

                const matchesSearch = searchTerm === '' || 
                    itemName.includes(searchTerm) || 
                    category.includes(searchTerm) || 
                    barcode.includes(searchTerm);

                const matchesDate = filterDate === '' || rowDate === filterDate;

                row.style.display = (matchesSearch && matchesDate) ? '' : 'none';
            });
        }

        // Add event listeners
        searchInput?.addEventListener('input', filterRows);
        dateFilter?.addEventListener('change', filterRows);

        // Reset function
        window.resetStockInFilters = function() {
            if (searchInput) searchInput.value = '';
            if (dateFilter) dateFilter.value = '';
            rows.forEach(row => row.style.display = '');
        };
    }

    // Search and filter functionality for Stock Out
    function setupStockOutFilters() {
        const searchInput = document.getElementById('stockOutSearch');
        const dateFilter = document.getElementById('stockOutDateFilter');
        const reasonFilter = document.getElementById('stockOutReasonFilter');
        const tbody = document.getElementById('stockOutTableBody');
        
        if (!tbody) return;
        
        const rows = Array.from(tbody.getElementsByTagName('tr'));
        
        function filterRows() {
            const searchTerm = searchInput?.value?.toLowerCase() || '';
            const filterDate = dateFilter?.value || '';
            const filterReason = reasonFilter?.value || 'all';

            rows.forEach(row => {
                const cells = Array.from(row.getElementsByTagName('td'));
                if (cells.length === 0) return;

                const itemName = cells[0].textContent.toLowerCase();
                const category = cells[1].textContent.toLowerCase();
                const reason = cells[3].textContent.trim();
                const rowDate = cells[5].textContent.split(' ')[0];

                const matchesSearch = searchTerm === '' || 
                    itemName.includes(searchTerm) || 
                    category.includes(searchTerm);

                const matchesDate = filterDate === '' || rowDate === filterDate;
                const matchesReason = filterReason === 'all' || reason === filterReason;

                row.style.display = (matchesSearch && matchesDate && matchesReason) ? '' : 'none';
            });
        }

        // Add event listeners
        searchInput?.addEventListener('input', filterRows);
        dateFilter?.addEventListener('change', filterRows);
        reasonFilter?.addEventListener('change', filterRows);

        // Reset function
        window.resetStockOutFilters = function() {
            if (searchInput) searchInput.value = '';
            if (dateFilter) dateFilter.value = '';
            if (reasonFilter) reasonFilter.value = 'all';
            rows.forEach(row => row.style.display = '');
        };
    }

    // Initialize both filters
    setupStockInFilters();
    setupStockOutFilters();

    // Set up tab switching to ensure filters work after switching tabs
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetTabId = button.getAttribute('data-target');
            switchTab(targetTabId);
            
            // Re-initialize filters for the active tab
            if (targetTabId === 'stockInContent') {
                setupStockInFilters();
            } else if (targetTabId === 'stockOutContent') {
                setupStockOutFilters();
            }
        });
    });
});
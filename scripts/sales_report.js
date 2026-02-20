// scripts/sales_report.js

document.addEventListener('DOMContentLoaded', function() {
    const dataWrapper = document.getElementById('chartDataWrapper');
    const canvasElement = document.getElementById('incomeChart');
    const container = canvasElement ? canvasElement.parentElement : null;

    if (!dataWrapper || !canvasElement || typeof Chart === 'undefined') {
        console.error("Chart elements or Chart.js library not found.");
        return;
    }

    // --- 1. Get Data from HTML Attributes ---
    // Note: JSON.parse() is used because the data is stored as a JSON string
    const labels = JSON.parse(dataWrapper.dataset.labels || '[]');
    const data = JSON.parse(dataWrapper.dataset.incomes || '[]');
    const chartType = dataWrapper.dataset.type || '';


    if (labels.length > 0) {
        // --- 2. Determine X-axis Title ---
        let xAxisTitle = '';
        if (chartType === 'daily') xAxisTitle = 'Hour of Day';
        else if (chartType === 'monthly') xAxisTitle = 'Day of Month';
        else if (chartType === 'yearly') xAxisTitle = 'Month';

        // --- 3. Initialize Chart.js ---
        new Chart(
            canvasElement,
            {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Revenue (₱)',
                            data: data,
                            backgroundColor: 'rgba(139, 92, 246, 0.7)', // violet-500
                            borderColor: 'rgb(139, 92, 246)',
                            borderWidth: 1,
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Income (₱)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: xAxisTitle
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        // Format the income data with currency and commas
                                        label += '₱' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            }
        );
    } else if (container) {
        // --- 4. Handle No Data ---
        container.innerHTML = '<p class="text-center text-slate-500 p-10">No sales data recorded for this period to generate a trend chart.</p>';
    }

    
    // ---  Temporary Access Expiration Logic ---
    const accessStatus = dataWrapper.dataset.accessStatus; // 'granted' or 'restricted'
    const expirationTime = parseInt(dataWrapper.dataset.expirationTime, 10) || 0; // Timestamp in seconds

    if (accessStatus === 'granted' && expirationTime > 0) {
        const currentTime = Math.floor(Date.now() / 1000); // Current time in seconds
        const timeRemaining = expirationTime - currentTime;

        if (timeRemaining > 0) {
            console.log(`Access granted. Reloading page in ${timeRemaining} seconds.`);
            
            // Set a timer to reload the page right after the access expires
            setTimeout(function() {
                // This reload forces PHP to re-run the restriction check
                window.location.reload();
            }, timeRemaining * 1000); // Convert seconds to milliseconds
        }
    }
});
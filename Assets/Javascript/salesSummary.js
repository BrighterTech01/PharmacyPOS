// sales-summary.js - Complete external JavaScript file

/**
 * Initialize all charts with data from PHP
 */
function initializeCharts() {
    // Get data from data attributes
    const salesDataElement = document.getElementById('salesData');
    if (!salesDataElement) {
        console.error('Sales data element not found');
        return;
    }

    try {
        const dailySalesData = JSON.parse(salesDataElement.dataset.dailySales || '[]');
        const topProductsData = JSON.parse(salesDataElement.dataset.topProducts || '[]');

        initializeDailySalesChart(dailySalesData);
        initializeTopProductsChart(topProductsData);
    } catch (error) {
        console.error('Error parsing sales data:', error);
    }
}

/**
 * Initialize Daily Sales Chart
 */
function initializeDailySalesChart(dailySalesData) {
    const dailyCtx = document.getElementById('dailySalesChart');
    if (!dailyCtx) return;

    const container = dailyCtx.parentElement;
    const existingPlaceholder = container.querySelector('.chart-empty-state');
    if (existingPlaceholder) {
        existingPlaceholder.remove();
    }
    dailyCtx.style.display = 'block';

    if (dailySalesData.length === 0) {
        dailyCtx.style.display = 'none';
        const placeholder = document.createElement('div');
        placeholder.className = 'chart-empty-state flex items-center justify-center h-full text-gray-500 text-sm';
        placeholder.textContent = 'No data available for the selected period';
        container.appendChild(placeholder);
        return;
    }

    const dailyLabels = dailySalesData.map(item => {
        const date = new Date(item.sale_date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    
    const dailyRevenue = dailySalesData.map(item => parseFloat(item.daily_revenue || 0));
    const dailyTransactions = dailySalesData.map(item => parseInt(item.daily_transactions || 0));

    try {
        new Chart(dailyCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: dailyRevenue,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                }, {
                    label: 'Transactions',
                    data: dailyTransactions,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.yAxisID === 'y') {
                                    label += '₱' + context.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                } else {
                                    label += context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₱)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString('en-PH');
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Transactions'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error initializing daily sales chart:', error);
        dailyCtx.innerHTML = '<div class="flex items-center justify-center h-full text-red-500">Error loading chart</div>';
    }
}

/**
 * Initialize Top Products Chart
 */
function initializeTopProductsChart(topProductsData) {
    const topProductsCtx = document.getElementById('topProductsChart');
    if (!topProductsCtx) return;

    const container = topProductsCtx.parentElement;
    const existingPlaceholder = container.querySelector('.chart-empty-state');
    if (existingPlaceholder) {
        existingPlaceholder.remove();
    }
    topProductsCtx.style.display = 'block';

    if (topProductsData.length === 0) {
        topProductsCtx.style.display = 'none';
        const placeholder = document.createElement('div');
        placeholder.className = 'chart-empty-state flex items-center justify-center h-full text-gray-500 text-sm';
        placeholder.textContent = 'No data available for the selected period';
        container.appendChild(placeholder);
        return;
    }

    const productLabels = topProductsData.map(item => item.brand_name);
    const productQuantities = topProductsData.map(item => parseInt(item.total_quantity || 0));

    try {
        new Chart(topProductsCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: productLabels,
                datasets: [{
                    label: 'Quantity Sold',
                    data: productQuantities,
                    backgroundColor: [
                        '#10B981', '#3B82F6', '#8B5CF6', '#F59E0B', '#EF4444',
                        '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1'
                    ],
                    borderRadius: 6,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Quantity: ${context.parsed.x.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    },
                    y: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 0
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error initializing top products chart:', error);
        topProductsCtx.innerHTML = '<div class="flex items-center justify-center h-full text-red-500">Error loading chart</div>';
    }
}

/**
 * Update date range and reload page with new dates
 */
function updateDateRange() {
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    if (!startDate || !endDate) {
        console.error('Date inputs not found');
        return;
    }

    const startValue = startDate.value;
    const endValue = endDate.value;
    
    if (startValue && endValue) {
        // Validate that end date is not before start date
        if (new Date(endValue) < new Date(startValue)) {
            alert('End date cannot be before start date');
            return;
        }
        
        window.location.href = `?start_date=${startValue}&end_date=${endValue}`;
    } else {
        alert('Please select both start and end dates');
    }
}

/**
 * Add event listeners for Enter key on date inputs
 */
function initializeEventListeners() {
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    if (startDate && endDate) {
        // Add Enter key support
        [startDate, endDate].forEach(input => {
            input.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    updateDateRange();
                }
            });
        });
    }
}

/**
 * Initialize everything when DOM is loaded
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeEventListeners();
});

// Make functions available globally
window.updateDateRange = updateDateRange;
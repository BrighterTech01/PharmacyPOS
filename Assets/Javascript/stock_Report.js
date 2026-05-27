// stock-reports.js

// Initialize charts and functionality
function initializeCharts() {
    initializeCategoryStockChart();
    initializeStockStatusChart();
}

// Category Stock Chart
function initializeCategoryStockChart() {
    const categoryStockData = window.categoryStockData || [];
    const categoryLabels = categoryStockData.map(item => item.category_name);
    const categoryValues = categoryStockData.map(item => parseFloat(item.total_value || 0));

    if (categoryStockData.length > 0) {
        const categoryCtx = document.getElementById('categoryStockChart');
        if (categoryCtx) {
            new Chart(categoryCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryValues,
                        backgroundColor: [
                            '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                            '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6366F1'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
        }
    }
}

// Stock Status Chart
function initializeStockStatusChart() {
    const statusData = {
        labels: ['In Stock', 'Low Stock', 'Out of Stock'],
        datasets: [{
            data: [
                window.inStockCount || 0,
                window.lowStockCount || 0,
                window.outOfStockCount || 0
            ],
            backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    };

    const statusCtx = document.getElementById('stockStatusChart');
    if (statusCtx) {
        new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Update stock report function
function updateStockReport() {
    const reportType = document.getElementById('reportType').value;
    const category = document.getElementById('categoryFilter').value;
    const stockStatus = document.getElementById('stockStatus').value;
    
    const params = new URLSearchParams({
        report_type: reportType,
        category: category,
        stock_status: stockStatus
    });
    
    window.location.href = `?${params.toString()}`;
}

// Export stock report function
function exportStockReport() {
    const reportType = document.getElementById('reportType').value;
    const category = document.getElementById('categoryFilter').value;
    const stockStatus = document.getElementById('stockStatus').value;
    
    // Create CSV content
    let csvContent = "Product,Category,Manufacturer,Stock,Unit Price,Total Value,Status\n";
    
    const table = document.getElementById('stockTableBody') || document.getElementById('lowStockTableBody');
    if (table) {
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            if (row.style.display !== 'none' && row.cells.length > 1) {
                const cells = row.cells;
                const rowData = [
                    cells[0]?.textContent.trim() || '',
                    cells[1]?.textContent.trim() || '',
                    cells[2]?.textContent.trim() || '',
                    cells[3]?.textContent.trim() || '',
                    cells[4]?.textContent.trim() || '',
                    cells[5]?.textContent.trim() || '',
                    cells[6]?.textContent.trim() || ''
                ];
                csvContent += rowData.join(',') + '\n';
            }
        }
    }
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `stock_report_${reportType}_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Search functionality
function initializeSearch() {
    const searchStock = document.getElementById('searchStock');
    const searchLowStock = document.getElementById('searchLowStock');
    
    if (searchStock) {
        searchStock.addEventListener('keyup', function() {
            filterTable('stockTableBody', this.value);
        });
    }
    
    if (searchLowStock) {
        searchLowStock.addEventListener('keyup', function() {
            filterTable('lowStockTableBody', this.value);
        });
    }
}

function filterTable(tableId, searchInput) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.getElementsByTagName('tr');
    const searchTerm = searchInput.toLowerCase();
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const productName = row.cells[0]?.textContent.toLowerCase() || '';
        const category = row.cells[1]?.textContent.toLowerCase() || '';
        
        if (productName.includes(searchTerm) || category.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeSearch();
    
    // Make functions globally available for onclick handlers
    window.updateStockReport = updateStockReport;
    window.exportStockReport = exportStockReport;
    window.filterTable = filterTable;
});

// Error handling for missing elements
window.addEventListener('error', function(e) {
    console.error('Error in stock-reports.js:', e.error);
});
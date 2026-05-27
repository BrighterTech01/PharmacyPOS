// Stock Card JavaScript

// Stock card state variables
let currentProductId = null;
let currentStockCardData = null; // Store current stock card data for printing
let allMovements = []; // Store all movements for filtering

// View Stock Card Function
async function viewStockCard(productId) {
    const modal = document.getElementById('stockCardModal');
    const tableBody = document.getElementById('stockCardTableBody');
    const productName = document.getElementById('modalProductName');
    const productDetails = document.getElementById('modalProductDetails');
    const productSummary = document.getElementById('productSummary');

    // Show modal with loading state
    modal.classList.remove('hidden');
    
    // Store current product ID for refresh functionality
    currentProductId = productId;
    
    // Hide real-time monitoring banner (no longer needed)
    const banner = document.getElementById('realtimeStockBanner');
    if (banner) {
        banner.classList.add('hidden');
    }
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="16" class="px-4 py-12 text-center">
                <div class="flex flex-col items-center justify-center text-gray-400">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-gray-400 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <p class="font-semibold text-lg text-gray-600 mb-1">Loading stock card...</p>
                </div>
            </td>
        </tr>
    `;

    try {
        const response = await fetch(`../../Controllers/getControllers/getStockCardData.php?product_id=${productId}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load stock card data');
        }

        const { product, movements, summary, current_stock, current_stock_packages, current_stock_units } = data;
        
        // Store all movements for filtering
        allMovements = movements;
        
        // Store data for printing
        currentStockCardData = { product, movements, summary, current_stock };

        // Update product name and details
        productName.textContent = product.brand_name || 'N/A';
        const details = [
            product.generic_name || '',
            product.strength || '',
            product.unit_name || ''
        ].filter(Boolean).join(' / ');
        productDetails.textContent = details || 'Product Details';

        // Determine stock status
        const stockQty = current_stock || summary.ending_balance_qty || 0;
        const stockPackages = current_stock_packages || 0;
        const stockUnits = current_stock_units || current_stock || 0;
        const stockTotal = stockQty; // Total is the same as units (package * unit + direct units)
        
        // Determine color scheme based on stock level
        let stockBgClass, stockBorderClass, stockTextClass, stockIconBgClass, stockButtonClass;
        if (stockQty === 0) {
            stockBgClass = 'from-red-50 to-red-100';
            stockBorderClass = 'border-red-200';
            stockTextClass = 'text-red-600';
            stockIconBgClass = 'bg-red-200';
            stockButtonClass = 'bg-red-200 text-red-700 hover:bg-red-300';
        } else if (stockQty <= 10) {
            stockBgClass = 'from-orange-50 to-orange-100';
            stockBorderClass = 'border-orange-200';
            stockTextClass = 'text-orange-600';
            stockIconBgClass = 'bg-orange-200';
            stockButtonClass = 'bg-orange-200 text-orange-700 hover:bg-orange-300';
        } else {
            stockBgClass = 'from-green-50 to-green-100';
            stockBorderClass = 'border-green-200';
            stockTextClass = 'text-green-600';
            stockIconBgClass = 'bg-green-200';
            stockButtonClass = 'bg-green-200 text-green-700 hover:bg-green-300';
        }
        
        const stockTextBoldClass = stockQty === 0 ? 'text-red-900' : (stockQty <= 10 ? 'text-orange-900' : 'text-green-900');
        const stockIconClass = stockQty === 0 ? 'text-red-600' : (stockQty <= 10 ? 'text-orange-600' : 'text-green-600');

        // Update summary cards - perfectly aligned grid layout with proper structure
        productSummary.innerHTML = `
            <!-- Row 1: Packages - Out, In, Current Stock -->
            <div class="w-full bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 border-2 border-red-200 shadow-md hover:shadow-lg transition-all h-[160px] flex flex-col items-center justify-center">
                <div class="text-center w-full">
                    <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">Out (Pkg)</p>
                    <p class="text-3xl font-extrabold text-red-900 mb-1">${formatNumber(summary.total_out_packages || 0)}</p>
                    <p class="text-xs font-medium text-red-700">pkg</p>
                </div>
            </div>
            <div class="w-full bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border-2 border-blue-200 shadow-md hover:shadow-lg transition-all h-[160px] flex flex-col items-center justify-center">
                <div class="text-center w-full">
                    <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">In (Pkg)</p>
                    <p class="text-3xl font-extrabold text-blue-900 mb-1">${formatNumber(summary.total_in_packages || 0)}</p>
                    <p class="text-xs font-medium text-blue-700">pkg</p>
                </div>
            </div>
            <div class="w-full bg-gradient-to-br ${stockBgClass} rounded-xl p-5 border-2 ${stockBorderClass} shadow-md hover:shadow-lg transition-all h-[160px] flex flex-col items-center justify-center">
                <div class="text-center w-full">
                    <p class="text-xs font-bold ${stockTextClass} uppercase tracking-wider mb-2">Current Stock (Pkg)</p>
                    <p class="text-3xl font-extrabold ${stockTextBoldClass} mb-1">${formatNumber(stockPackages)}</p>
                    <p class="text-xs font-medium ${stockTextClass}">pkg</p>
                </div>
            </div>
            
            <!-- Row 2: Units - Out, In, Current Stock -->
            <div class="w-full bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 border-2 border-red-200 shadow-md hover:shadow-lg transition-all h-[160px] flex flex-col items-center justify-center">
                <div class="text-center w-full">
                    <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">Out (Unit)</p>
                    <p class="text-3xl font-extrabold text-red-900 mb-1">${formatNumber(summary.total_out_total_units || 0)}</p>
                    <p class="text-xs font-medium text-red-700">${escapeHtml(product.unit_name || 'units')}</p>
                </div>
            </div>
            <div class="w-full bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border-2 border-blue-200 shadow-md hover:shadow-lg transition-all h-[160px] flex flex-col items-center justify-center">
                <div class="text-center w-full">
                    <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">In (Unit)</p>
                    <p class="text-3xl font-extrabold text-blue-900 mb-1">${formatNumber(summary.total_in_total_units || 0)}</p>
                    <p class="text-xs font-medium text-blue-700">${escapeHtml(product.unit_name || 'units')}</p>
                </div>
            </div>
            <div class="w-full bg-gradient-to-br ${stockBgClass} rounded-xl p-5 border-2 ${stockBorderClass} shadow-md hover:shadow-lg transition-all h-[160px] flex flex-col items-center justify-center">
                <div class="text-center w-full">
                    <p class="text-xs font-bold ${stockTextClass} uppercase tracking-wider mb-2">Current Stock (Unit)</p>
                    <p class="text-3xl font-extrabold ${stockTextBoldClass} mb-1">${formatNumber(stockUnits)}</p>
                    <p class="text-xs font-medium ${stockTextClass}">${escapeHtml(product.unit_name || 'units')}</p>
                </div>
            </div>
            
            <!-- Row 3: Total - Out, In, Current Stock -->
            <div class="w-full bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 border-2 border-red-200 shadow-md hover:shadow-lg transition-all h-[160px] flex flex-col items-center justify-center">
                <div class="text-center w-full">
                    <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-2">Out (Total)</p>
                    <p class="text-3xl font-extrabold text-red-900 mb-1">${formatNumber(summary.total_out_total_units || 0)}</p>
                    <p class="text-xs font-medium text-red-700">total units</p>
                </div>
            </div>
            <div class="w-full bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border-2 border-blue-200 shadow-md hover:shadow-lg transition-all h-[160px] flex flex-col items-center justify-center">
                <div class="text-center w-full">
                    <p class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">In (Total)</p>
                    <p class="text-3xl font-extrabold text-blue-900 mb-1">${formatNumber(summary.total_in_total_units || 0)}</p>
                    <p class="text-xs font-medium text-blue-700">total units</p>
                </div>
            </div>
            <div class="w-full bg-gradient-to-br ${stockBgClass} rounded-xl p-5 border-2 ${stockBorderClass} shadow-md hover:shadow-lg transition-all h-[160px] flex flex-col items-center justify-center">
                <div class="text-center w-full">
                    <p class="text-xs font-bold ${stockTextClass} uppercase tracking-wider mb-2">Current Stock (Total)</p>
                    <p class="text-3xl font-extrabold ${stockTextBoldClass} mb-1">${formatNumber(stockTotal)}</p>
                    <p class="text-xs font-medium ${stockTextClass}">package × unit</p>
                </div>
            </div>
            
            <!-- Row 4: Inventory Value - Full width card -->
            <div class="col-span-1 md:col-span-3 mt-5">
                <div class="bg-gradient-to-br from-purple-50 via-purple-100 to-purple-50 rounded-xl p-5 border-2 border-purple-300 shadow-lg hover:shadow-xl transition-all">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-xs font-bold text-purple-600 uppercase tracking-wider mb-2">Inventory Value</p>
                            <p class="text-3xl font-extrabold text-purple-900 mb-1">${formatCurrency(summary.ending_balance_total)}</p>
                            <p class="text-xs font-medium text-purple-700">Total inventory worth</p>
                        </div>
                        <div class="p-3 bg-purple-200 rounded-xl shadow-inner">
                            <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Apply date filter
        const filteredMovements = filterMovementsByDate(movements);
        // Limit to 10 rows if no date filter is applied, otherwise show all filtered results
        const hasDateFilter = document.getElementById('stockCardDateFrom')?.value || document.getElementById('stockCardDateTo')?.value;
        const displayMovements = hasDateFilter ? filteredMovements : filteredMovements.slice(0, 10);
        
        // Add scrollbar if 10 or more rows
        const tableContainer = document.getElementById('stockCardTableContainer');
        if (tableContainer) {
            if (displayMovements.length >= 10) {
                tableContainer.style.maxHeight = '500px';
                tableContainer.style.overflowY = 'auto';
                tableContainer.classList.add('pr-2');
            } else {
                tableContainer.style.maxHeight = 'none';
                tableContainer.style.overflowY = 'visible';
                tableContainer.classList.remove('pr-2');
            }
        }
        
        // Populate stock card table
        if (displayMovements.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="16" class="px-4 py-12 text-center">
                        <div class="flex flex-col items-center justify-center text-gray-400">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <p class="font-semibold text-lg text-gray-600 mb-1">No Stock Movements</p>
                            <p class="text-sm">No stock movements found for this product${filteredMovements.length > 0 ? ' in the selected date range' : ''}.</p>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            // Calculate totals
            let totalInPkg = 0;
            let totalInTotalUnits = 0;
            let totalInTotal = 0;
            let totalOutPkg = 0;
            let totalOutTotalUnits = 0;
            let totalOutTotal = 0;
            
            displayMovements.forEach(movement => {
                totalInPkg += parseFloat(movement.in_packages || 0);
                totalInTotalUnits += parseFloat(movement.in_total_units || 0);
                totalInTotal += parseFloat(movement.in_total || 0);
                totalOutPkg += parseFloat(movement.out_packages || 0);
                totalOutTotalUnits += parseFloat(movement.out_total_units || 0);
                totalOutTotal += parseFloat(movement.out_total || 0);
            });
            
            // Get final balance from last movement
            const lastMovement = displayMovements[displayMovements.length - 1];
            const finalBalancePkg = lastMovement ? (lastMovement.balance_packages || 0) : 0;
            const finalBalanceUnit = lastMovement ? (lastMovement.balance_qty || 0) : 0;
            const finalBalanceTotal = lastMovement ? (lastMovement.balance_total || 0) : 0;
            
            tableBody.innerHTML = displayMovements.map(movement => {
                let date = 'N/A';
                try {
                    const dateObj = new Date(movement.date);
                    if (!isNaN(dateObj.getTime())) {
                        date = dateObj.toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: '2-digit', 
                            day: '2-digit' 
                        });
                    }
                } catch (e) {
                    date = movement.date || 'N/A';
                }
                
                const hasIn = (movement.in_packages > 0 || movement.in_total_units > 0);
                const hasOut = (movement.out_packages > 0 || movement.out_total_units > 0);
                
                return `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 border-b border-gray-100">${date}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-100">${escapeHtml(movement.particulars)}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-100">${movement.username ? escapeHtml(movement.username) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-emerald-700 border-b border-gray-100">${hasIn ? formatNumber(movement.in_packages || 0) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-emerald-700 border-b border-gray-100">${hasIn ? formatNumber(movement.in_units_per_package || 0) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-emerald-600 border-b border-gray-100">${hasIn ? formatNumber(movement.in_total_units || 0) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700 border-b border-gray-100">${hasIn ? formatCurrency(movement.in_cost_per_unit) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-emerald-700 border-b border-gray-100">${hasIn ? formatCurrency(movement.in_total) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-red-700 border-b border-gray-100">${hasOut ? formatNumber(movement.out_packages || 0) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-red-700 border-b border-gray-100">${hasOut ? formatNumber(movement.out_units_per_package || 0) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-red-600 border-b border-gray-100">${hasOut ? formatNumber(movement.out_total_units || 0) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right text-gray-700 border-b border-gray-100">${hasOut ? formatCurrency(movement.out_cost_per_unit) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-red-700 border-b border-gray-100">${hasOut ? formatCurrency(movement.out_total) : '–'}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-purple-900 border-b border-gray-100">${formatNumber(movement.balance_packages || 0)}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-purple-900 border-b border-gray-100">${formatNumber(movement.balance_qty || 0)}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-purple-900 border-b border-gray-100">${formatCurrency(movement.balance_total || 0)}</td>
                    </tr>
                `;
            }).join('');
            
            // Add totals row
            const totalsRow = `
                <tr class="bg-gradient-to-r from-gray-50 to-gray-100 font-bold">
                    <td colspan="3" class="px-4 py-3 text-sm text-gray-900 border-t-2 border-gray-300">TOTAL</td>
                    <td class="px-4 py-3 text-sm text-right text-emerald-700 border-t-2 border-gray-300">${formatNumber(totalInPkg)}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700 border-t-2 border-gray-300">–</td>
                    <td class="px-4 py-3 text-sm text-right text-emerald-600 border-t-2 border-gray-300">${formatNumber(totalInTotalUnits)}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700 border-t-2 border-gray-300">–</td>
                    <td class="px-4 py-3 text-sm text-right text-emerald-700 border-t-2 border-gray-300">${formatCurrency(totalInTotal)}</td>
                    <td class="px-4 py-3 text-sm text-right text-red-700 border-t-2 border-gray-300">${formatNumber(totalOutPkg)}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700 border-t-2 border-gray-300">–</td>
                    <td class="px-4 py-3 text-sm text-right text-red-600 border-t-2 border-gray-300">${formatNumber(totalOutTotalUnits)}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700 border-t-2 border-gray-300">–</td>
                    <td class="px-4 py-3 text-sm text-right text-red-700 border-t-2 border-gray-300">${formatCurrency(totalOutTotal)}</td>
                    <td class="px-4 py-3 text-sm text-right text-purple-900 border-t-2 border-gray-300">${formatNumber(finalBalancePkg)}</td>
                    <td class="px-4 py-3 text-sm text-right text-purple-900 border-t-2 border-gray-300">${formatNumber(finalBalanceUnit)}</td>
                    <td class="px-4 py-3 text-sm text-right text-purple-900 border-t-2 border-gray-300">${formatCurrency(finalBalanceTotal)}</td>
                </tr>
            `;
            tableBody.innerHTML += totalsRow;
        }
        
    } catch (error) {
        console.error('Error loading stock card:', error);
        tableBody.innerHTML = `
            <tr>
                <td colspan="16" class="px-4 py-12 text-center">
                    <div class="flex flex-col items-center justify-center text-red-400">
                        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p class="font-semibold text-lg text-red-600 mb-1">Error Loading Stock Card</p>
                        <p class="text-sm">${escapeHtml(error.message)}</p>
                    </div>
                </td>
            </tr>
        `;
    }
}

// Close Stock Card Modal
function closeStockCardModal() {
    const modal = document.getElementById('stockCardModal');
    modal.classList.add('hidden');
    
    // Hide real-time monitoring banner
    const banner = document.getElementById('realtimeStockBanner');
    if (banner) {
        banner.classList.add('hidden');
    }
    
    // Clear current product ID
    currentProductId = null;
}

// Filter Products Function
function filterProducts() {
    const searchInput = document.getElementById('searchInput')?.value.trim().toLowerCase();
    const tableBody = document.getElementById('productsTableBody');
    const rows = tableBody?.getElementsByTagName('tr');

    if (!rows) return;

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent || row.innerText;
        
        if (text.toLowerCase().includes(searchInput)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

// Format Number
function formatNumber(number) {
    const num = parseFloat(number);
    if (isNaN(num)) return '0';
    return num.toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

// Format Currency
function formatCurrency(amount) {
    const num = parseFloat(amount);
    if (isNaN(num)) return '₱0.00';
    return '₱' + num.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Close modal on outside click
document.addEventListener('click', function(event) {
    const modal = document.getElementById('stockCardModal');
    if (event.target === modal) {
        closeStockCardModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeStockCardModal();
    }
});

// Print Stock Card Function
function printStockCard() {
    if (!currentStockCardData) {
        alert('No stock card data available to print.');
        return;
    }
    
    // Get filtered movements based on date range
    const filteredMovements = filterMovementsByDate(allMovements);
    const printMovements = filteredMovements.slice(0, 10); // Limit to 10 for print (for readability)
    
    const { product, summary, current_stock } = currentStockCardData;
    
    // Format product details
    const productName = product.brand_name || 'N/A';
    const productDetails = [
        product.generic_name || '',
        product.strength || '',
        product.unit_name || ''
    ].filter(Boolean).join(' / ') || 'Product Details';
    
    // Format movements for print
    let movementsHTML = '';
    if (printMovements.length === 0) {
        movementsHTML = `
            <tr>
                <td colspan="16" class="text-center" style="padding: 20px; color: #64748b;">
                    No stock movements found for this product.
                </td>
            </tr>
        `;
    } else {
        printMovements.forEach(movement => {
            let date = 'N/A';
            try {
                const dateObj = new Date(movement.date);
                if (!isNaN(dateObj.getTime())) {
                    date = dateObj.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: '2-digit', 
                        day: '2-digit' 
                    });
                }
            } catch (e) {
                date = movement.date || 'N/A';
            }
            
            const hasIn = (movement.in_packages > 0 || movement.in_total_units > 0);
            const hasOut = (movement.out_packages > 0 || movement.out_total_units > 0);
            
            movementsHTML += `
                <tr>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px;">${date}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px;">${escapeHtml(movement.particulars)}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px;">${movement.username ? escapeHtml(movement.username) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right;">${hasIn ? formatNumber(movement.in_packages || 0) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right;">${hasIn ? formatNumber(movement.in_units_per_package || 0) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right; font-weight: bold;">${hasIn ? formatNumber(movement.in_total_units || 0) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right;">${hasIn ? formatCurrency(movement.in_cost_per_unit) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right;">${hasIn ? formatCurrency(movement.in_total) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right;">${hasOut ? formatNumber(movement.out_packages || 0) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right;">${hasOut ? formatNumber(movement.out_units_per_package || 0) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right; font-weight: bold;">${hasOut ? formatNumber(movement.out_total_units || 0) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right;">${hasOut ? formatCurrency(movement.out_cost_per_unit) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right;">${hasOut ? formatCurrency(movement.out_total) : '–'}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right; font-weight: bold;">${formatNumber(movement.balance_packages || 0)}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right; font-weight: bold;">${formatNumber(movement.balance_qty || 0)}</td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 10px; text-align: right; font-weight: bold;">${formatCurrency(movement.balance_total || 0)}</td>
                </tr>
            `;
        });
    }
    
    // Determine stock status color
    const stockQty = current_stock || summary.ending_balance_qty || 0;
    let stockStatusColor = '#059669'; // green
    let stockStatusText = 'In Stock';
    if (stockQty === 0) {
        stockStatusColor = '#dc2626'; // red
        stockStatusText = 'Out of Stock';
    } else if (stockQty <= 10) {
        stockStatusColor = '#ea580c'; // orange
        stockStatusText = 'Low Stock';
    }
    
    // Create print window
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Stock Card - ${escapeHtml(productName)}</title>
            <meta charset="UTF-8">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    font-size: 11px;
                    margin: 0;
                    padding: 20px;
                    color: #000;
                    line-height: 1.4;
                    background: #fff;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 25px;
                    border-bottom: 3px solid #9333ea;
                    padding-bottom: 15px;
                }
                .print-header h1 {
                    font-size: 28px;
                    margin: 0 0 10px 0;
                    color: #1e293b;
                    font-weight: 700;
                }
                .print-header .product-info {
                    margin-top: 10px;
                }
                .print-header .product-name {
                    font-size: 18px;
                    font-weight: 600;
                    color: #1e293b;
                    margin-bottom: 5px;
                }
                .print-header .product-details {
                    font-size: 12px;
                    color: #64748b;
                }
                .print-summary {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 15px;
                    margin: 20px 0 25px 0;
                }
                .summary-box {
                    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    padding: 15px;
                    text-align: center;
                }
                .print-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .print-table thead {
                    background: linear-gradient(135deg, #7e22ce 0%, #9333ea 100%);
                }
                .print-table th {
                    color: #fff;
                    font-weight: 700;
                    padding: 10px 8px;
                    text-align: left;
                    border: 1px solid #6b21a8;
                    font-size: 9px;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                .print-table th.text-right {
                    text-align: right;
                }
                .print-table tbody td {
                    padding: 8px;
                    border: 1px solid #e2e8f0;
                    font-size: 10px;
                    color: #334155;
                }
                .print-table tbody tr {
                    border-bottom: 1px solid #e2e8f0;
                }
                .print-table tbody tr:nth-child(even) {
                    background: #f8fafc;
                }
                .text-right {
                    text-align: right;
                }
                .text-bold {
                    font-weight: 700;
                }
                .print-footer {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 2px solid #cbd5e1;
                    text-align: center;
                    font-size: 10px;
                    color: #64748b;
                }
                @media print {
                    body {
                        margin: 0;
                        padding: 15px;
                    }
                    .print-table {
                        page-break-inside: auto;
                    }
                    .print-table thead {
                        display: table-header-group;
                    }
                    @page {
                        margin: 1cm;
                        size: A4 landscape;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>📋 Stock Card Report</h1>
                <div class="product-info">
                    <div class="product-name">${escapeHtml(productName)}</div>
                    <div class="product-details">${escapeHtml(productDetails)}</div>
                    ${document.getElementById('stockCardDateFrom')?.value || document.getElementById('stockCardDateTo')?.value ? `
                        <div style="margin-top: 8px; font-size: 11px; color: #64748b;">
                            <strong>Date Range:</strong> 
                            ${document.getElementById('stockCardDateFrom')?.value || 'All'} 
                            to 
                            ${document.getElementById('stockCardDateTo')?.value || 'All'}
                        </div>
                    ` : ''}
                    ${filteredMovements.length > 10 ? `
                        <div style="margin-top: 5px; font-size: 10px; color: #ea580c;">
                            Showing first 10 of ${filteredMovements.length} movements
                        </div>
                    ` : ''}
                </div>
            </div>
            
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">Date</th>
                        <th style="width: 12%;">Particulars</th>
                        <th style="width: 10%;">Username</th>
                        <th style="width: 5%;" class="text-right">In (Pkg)</th>
                        <th style="width: 5%;" class="text-right">In (Unit)</th>
                        <th style="width: 6%;" class="text-right">In Total (Pkg×Unit)</th>
                        <th style="width: 6%;" class="text-right">In (Cost/Unit)</th>
                        <th style="width: 7%;" class="text-right">In (Total)</th>
                        <th style="width: 5%;" class="text-right">Out (Pkg)</th>
                        <th style="width: 5%;" class="text-right">Out (Unit)</th>
                        <th style="width: 6%;" class="text-right">Out Total (Pkg×Unit)</th>
                        <th style="width: 6%;" class="text-right">Out (Cost/Unit)</th>
                        <th style="width: 7%;" class="text-right">Out (Total)</th>
                        <th style="width: 6%;" class="text-right">Balance (Pkg)</th>
                        <th style="width: 6%;" class="text-right">Balance (Unit)</th>
                        <th style="width: 8%;" class="text-right">Balance (Total)</th>
                    </tr>
                </thead>
                <tbody>
                    ${movementsHTML}
                </tbody>
            </table>
            
            <div class="print-footer">
                <p><strong>Generated by OCT Pharmacy</strong> | ${new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    
    // Wait for content to load, then print
    setTimeout(() => {
        printWindow.print();
    }, 250);
}

// Filter movements by date range
function filterMovementsByDate(movements) {
    const dateFrom = document.getElementById('stockCardDateFrom')?.value;
    const dateTo = document.getElementById('stockCardDateTo')?.value;
    
    if (!dateFrom && !dateTo) {
        // No filter - return all movements sorted by date (newest first)
        return movements.slice().sort((a, b) => {
            const dateA = new Date(a.date);
            const dateB = new Date(b.date);
            return dateB - dateA; // Newest first
        });
    }
    
    const fromDate = dateFrom ? new Date(dateFrom) : null;
    const toDate = dateTo ? new Date(dateTo) : null;
    
    // Set time to end of day for toDate
    if (toDate) {
        toDate.setHours(23, 59, 59, 999);
    }
    
    return movements.filter(movement => {
        const movementDate = new Date(movement.date);
        
        if (fromDate && toDate) {
            return movementDate >= fromDate && movementDate <= toDate;
        } else if (fromDate) {
            return movementDate >= fromDate;
        } else if (toDate) {
            return movementDate <= toDate;
        }
        
        return true;
    }).sort((a, b) => {
        const dateA = new Date(a.date);
        const dateB = new Date(b.date);
        return dateB - dateA; // Newest first
    });
}

// Filter stock card movements based on date range
function filterStockCardMovements() {
    if (!currentProductId || allMovements.length === 0) return;
    
    const tableBody = document.getElementById('stockCardTableBody');
    const tableContainer = document.getElementById('stockCardTableContainer');
    if (!tableBody) return;
    
    // Check if date inputs match quick filter ranges (to maintain active state)
    const dateFrom = document.getElementById('stockCardDateFrom')?.value;
    const dateTo = document.getElementById('stockCardDateTo')?.value;
    
    // Reset quick filter buttons if dates don't match predefined ranges
    if (dateFrom || dateTo) {
        const today = new Date();
        const fromDate = dateFrom ? new Date(dateFrom) : null;
        const toDate = dateTo ? new Date(dateTo) : null;
        
        // Check if dates match quick filter ranges
        let matchesQuickFilter = false;
        
        if (fromDate && toDate) {
            // Check week
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - today.getDay());
            weekStart.setHours(0, 0, 0, 0);
            if (fromDate.getTime() === weekStart.getTime() && toDate.toDateString() === today.toDateString()) {
                matchesQuickFilter = true;
            }
            
            // Check month
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            monthStart.setHours(0, 0, 0, 0);
            if (!matchesQuickFilter && fromDate.getTime() === monthStart.getTime() && toDate.toDateString() === today.toDateString()) {
                matchesQuickFilter = true;
            }
            
            // Check year
            const yearStart = new Date(today.getFullYear(), 0, 1);
            yearStart.setHours(0, 0, 0, 0);
            if (!matchesQuickFilter && fromDate.getTime() === yearStart.getTime() && toDate.toDateString() === today.toDateString()) {
                matchesQuickFilter = true;
            }
        }
        
        // If dates don't match quick filters, reset button states
        if (!matchesQuickFilter) {
            document.querySelectorAll('[id^="quickFilter"]').forEach(btn => {
                btn.classList.remove('bg-purple-600', 'text-white', 'border-purple-600');
                btn.classList.add('bg-white', 'border-gray-300', 'text-gray-700');
            });
        }
    }
    
    const filteredMovements = filterMovementsByDate(allMovements);
    // Limit to 10 rows if no date filter is applied, otherwise show all filtered results
    const hasDateFilter = dateFrom || dateTo;
    const displayMovements = hasDateFilter ? filteredMovements : filteredMovements.slice(0, 10);
    
    // Add scrollbar if 10 or more rows
    if (tableContainer) {
        if (displayMovements.length >= 10) {
            tableContainer.style.maxHeight = '500px';
            tableContainer.style.overflowY = 'auto';
            tableContainer.classList.add('pr-2');
        } else {
            tableContainer.style.maxHeight = 'none';
            tableContainer.style.overflowY = 'visible';
            tableContainer.classList.remove('pr-2');
        }
    }
    
    if (displayMovements.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="16" class="px-4 py-12 text-center">
                    <div class="flex flex-col items-center justify-center text-gray-400">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <p class="font-semibold text-lg text-gray-600 mb-1">No Stock Movements</p>
                        <p class="text-sm">No stock movements found in the selected date range.</p>
                    </div>
                </td>
            </tr>
        `;
    } else {
        tableBody.innerHTML = displayMovements.map(movement => {
            let date = 'N/A';
            try {
                const dateObj = new Date(movement.date);
                if (!isNaN(dateObj.getTime())) {
                    date = dateObj.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: '2-digit', 
                        day: '2-digit' 
                    });
                }
            } catch (e) {
                date = movement.date || 'N/A';
            }
            
            const hasIn = (movement.in_packages > 0 || movement.in_total_units > 0);
            const hasOut = (movement.out_packages > 0 || movement.out_total_units > 0);
            
            return `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 border-b border-gray-100">${date}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-100">${escapeHtml(movement.particulars)}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 border-b border-gray-100">${movement.username ? escapeHtml(movement.username) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-emerald-700 border-b border-gray-100">${hasIn ? formatNumber(movement.in_packages || 0) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-emerald-700 border-b border-gray-100">${hasIn ? formatNumber(movement.in_units_per_package || 0) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-emerald-600 border-b border-gray-100">${hasIn ? formatNumber(movement.in_total_units || 0) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700 border-b border-gray-100">${hasIn ? formatCurrency(movement.in_cost_per_unit) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-emerald-700 border-b border-gray-100">${hasIn ? formatCurrency(movement.in_total) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-red-700 border-b border-gray-100">${hasOut ? formatNumber(movement.out_packages || 0) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-red-700 border-b border-gray-100">${hasOut ? formatNumber(movement.out_units_per_package || 0) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-red-600 border-b border-gray-100">${hasOut ? formatNumber(movement.out_total_units || 0) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700 border-b border-gray-100">${hasOut ? formatCurrency(movement.out_cost_per_unit) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-red-700 border-b border-gray-100">${hasOut ? formatCurrency(movement.out_total) : '–'}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-purple-900 border-b border-gray-100">${formatNumber(movement.balance_packages || 0)}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-purple-900 border-b border-gray-100">${formatNumber(movement.balance_qty || 0)}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-purple-900 border-b border-gray-100">${formatCurrency(movement.balance_total || 0)}</td>
                </tr>
            `;
        }).join('');
        
        // Add totals row
        const totalsRow = `
            <tr class="bg-gradient-to-r from-gray-50 to-gray-100 font-bold">
                <td colspan="3" class="px-4 py-3 text-sm text-gray-900 border-t-2 border-gray-300">TOTAL</td>
                <td class="px-4 py-3 text-sm text-right text-emerald-700 border-t-2 border-gray-300">${formatNumber(totalInPkg)}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-700 border-t-2 border-gray-300">–</td>
                <td class="px-4 py-3 text-sm text-right text-emerald-600 border-t-2 border-gray-300">${formatNumber(totalInTotalUnits)}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-700 border-t-2 border-gray-300">–</td>
                <td class="px-4 py-3 text-sm text-right text-emerald-700 border-t-2 border-gray-300">${formatCurrency(totalInTotal)}</td>
                <td class="px-4 py-3 text-sm text-right text-red-700 border-t-2 border-gray-300">${formatNumber(totalOutPkg)}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-700 border-t-2 border-gray-300">–</td>
                <td class="px-4 py-3 text-sm text-right text-red-600 border-t-2 border-gray-300">${formatNumber(totalOutTotalUnits)}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-700 border-t-2 border-gray-300">–</td>
                <td class="px-4 py-3 text-sm text-right text-red-700 border-t-2 border-gray-300">${formatCurrency(totalOutTotal)}</td>
                <td class="px-4 py-3 text-sm text-right text-purple-900 border-t-2 border-gray-300">${formatNumber(finalBalancePkg)}</td>
                <td class="px-4 py-3 text-sm text-right text-purple-900 border-t-2 border-gray-300">${formatNumber(finalBalanceUnit)}</td>
                <td class="px-4 py-3 text-sm text-right text-purple-900 border-t-2 border-gray-300">${formatCurrency(finalBalanceTotal)}</td>
            </tr>
        `;
        tableBody.innerHTML += totalsRow;
    }
}

// Clear date filter
function clearStockCardDateFilter() {
    const dateFrom = document.getElementById('stockCardDateFrom');
    const dateTo = document.getElementById('stockCardDateTo');
    
    if (dateFrom) dateFrom.value = '';
    if (dateTo) dateTo.value = '';
    
    // Remove active state from quick filter buttons
    document.querySelectorAll('[id^="quickFilter"]').forEach(btn => {
        btn.classList.remove('bg-purple-600', 'text-white', 'border-purple-600');
        btn.classList.add('bg-white', 'border-gray-300', 'text-gray-700');
    });
    
    filterStockCardMovements();
}

// Set quick filter (Week, Month, Year)
function setQuickFilter(type) {
    const dateFrom = document.getElementById('stockCardDateFrom');
    const dateTo = document.getElementById('stockCardDateTo');
    
    if (!dateFrom || !dateTo) return;
    
    const today = new Date();
    let startDate = new Date();
    
    // Remove active state from all quick filter buttons
    document.querySelectorAll('[id^="quickFilter"]').forEach(btn => {
        btn.classList.remove('bg-purple-600', 'text-white', 'border-purple-600');
        btn.classList.add('bg-white', 'border-gray-300', 'text-gray-700');
    });
    
    // Set date range based on filter type
    if (type === 'week') {
        // Start of this week (Sunday)
        const day = today.getDay();
        startDate.setDate(today.getDate() - day);
        startDate.setHours(0, 0, 0, 0);
        dateFrom.value = startDate.toISOString().split('T')[0];
        dateTo.value = today.toISOString().split('T')[0];
        
        // Set active state
        document.getElementById('quickFilterWeek')?.classList.remove('bg-white', 'border-gray-300', 'text-gray-700');
        document.getElementById('quickFilterWeek')?.classList.add('bg-purple-600', 'text-white', 'border-purple-600');
    } else if (type === 'month') {
        // Start of this month
        startDate = new Date(today.getFullYear(), today.getMonth(), 1);
        startDate.setHours(0, 0, 0, 0);
        dateFrom.value = startDate.toISOString().split('T')[0];
        dateTo.value = today.toISOString().split('T')[0];
        
        // Set active state
        document.getElementById('quickFilterMonth')?.classList.remove('bg-white', 'border-gray-300', 'text-gray-700');
        document.getElementById('quickFilterMonth')?.classList.add('bg-purple-600', 'text-white', 'border-purple-600');
    } else if (type === 'year') {
        // Start of this year
        startDate = new Date(today.getFullYear(), 0, 1);
        startDate.setHours(0, 0, 0, 0);
        dateFrom.value = startDate.toISOString().split('T')[0];
        dateTo.value = today.toISOString().split('T')[0];
        
        // Set active state
        document.getElementById('quickFilterYear')?.classList.remove('bg-white', 'border-gray-300', 'text-gray-700');
        document.getElementById('quickFilterYear')?.classList.add('bg-purple-600', 'text-white', 'border-purple-600');
    }
    
    filterStockCardMovements();
}

// Refresh stock card data on demand
async function refreshStockCard() {
    if (!currentProductId) return;
    
    const modal = document.getElementById('stockCardModal');
    if (modal.classList.contains('hidden')) return;
    
    try {
        const response = await fetch(`../../Controllers/getControllers/getStockCardData.php?product_id=${currentProductId}`);
        const data = await response.json();
        
        if (data.success) {
            // Update current stock display
            const stockDisplay = document.getElementById('currentStockDisplay');
            if (stockDisplay && data.current_stock !== undefined) {
                const oldStock = parseInt(stockDisplay.textContent.replace(/,/g, '')) || 0;
                const newStock = data.current_stock || 0;
                
                stockDisplay.textContent = formatNumber(newStock);
                
                // Update stock card color based on new stock
                if (newStock !== oldStock) {
                    stockDisplay.classList.add('animate-pulse');
                    setTimeout(() => {
                        stockDisplay.classList.remove('animate-pulse');
                    }, 1000);
                    
                    // Reload entire stock card to show updated movements
                    viewStockCard(currentProductId);
                }
            }
        }
    } catch (error) {
        console.error('Error refreshing stock card:', error);
    }
}


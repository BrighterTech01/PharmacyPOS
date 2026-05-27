const API_URL = '../../Controllers/getControllers/getSaleDetails.php';
let currentSaleId = null;

// Filter Sales Function
function filterSales() {
  const searchInput = document.getElementById('searchInput').value.toLowerCase();
  const cashierFilter = document.getElementById('cashierFilter').value;
  const table = document.getElementById('salesTableBody');
  const rows = table.getElementsByTagName('tr');
  
  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    
    // Skip detail rows (expanded rows)
    if (row.id && row.id.startsWith('details-')) {
      // Hide detail row if parent row is hidden
      const parentRow = row.previousElementSibling;
      if (parentRow && parentRow.style.display === 'none') {
        row.style.display = 'none';
      }
      continue;
    }
    
    // Skip if not a data row
    if (!row.dataset.saleId) continue;
    
    // Columns: 0=Expand, 1=Time, 2=Cashier, 3=Products
    const time = row.cells[1]?.textContent.toLowerCase() || '';
    const cashier = row.cells[2]?.textContent.toLowerCase() || '';
    const products = row.cells[3]?.textContent.toLowerCase() || '';
    const saleId = row.dataset.saleId || '';
    
    let showRow = true;
    
    // Search filter - search in sale ID, time, cashier, and products
    if (searchInput) {
      const searchText = saleId + ' ' + time + ' ' + cashier + ' ' + products;
      if (!searchText.includes(searchInput)) {
        showRow = false;
      }
    }
    
    // Cashier filter
    if (cashierFilter && showRow) {
      if (!cashier.includes(cashierFilter.toLowerCase())) {
        showRow = false;
      }
    }
    
    row.style.display = showRow ? '' : 'none';
    
    // Also hide the detail row if parent is hidden
    const detailRow = document.getElementById(`details-${saleId}`);
    if (detailRow) {
      detailRow.style.display = showRow ? (detailRow.classList.contains('hidden') ? 'none' : '') : 'none';
    }
  }
}


// Update date range function with debounce
let dateUpdateTimeout;
function updateDateRange() {
    clearTimeout(dateUpdateTimeout);
    
    dateUpdateTimeout = setTimeout(() => {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (startDate && endDate) {
            // Validate that end date is not before start date
            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date');
                return;
            }
        window.location.href = `?start_date=${startDate}&end_date=${endDate}`;
    }
    }, 300); // Wait 300ms after last change before updating
}

// Export transactions function
function exportTransactions() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    // Create CSV content
    let csvContent = "Transaction ID,Cashier,Products,Items,Total Amount,Date\n";
    
    const table = document.getElementById('salesTableBody');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        if (row.style.display !== 'none' && row.cells.length > 0) {
            const cells = row.cells;
            // Columns: 0=Transaction, 1=Cashier, 2=Products, 3=Items, 4=Amount, 5=Date
            const rowData = [
                cells[0]?.textContent.trim() || '',
                cells[1]?.textContent.trim() || '',
                cells[2]?.textContent.trim() || '',
                cells[3]?.textContent.trim() || '',
                cells[4]?.textContent.trim() || '',
                cells[5]?.textContent.trim() || ''
            ];
            csvContent += rowData.join(',') + '\n';
        }
    }
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `transactions_${startDate}_to_${endDate}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Print receipt function
function printReceipt(saleId) {
    window.open(`../Controllers/printReceipt.php?id=${saleId}`, '_blank');
}

// Toggle Sale Details (Expandable Row)
async function toggleSaleDetails(saleId, button) {
  const detailsRow = document.getElementById(`details-${saleId}`);
  const itemsContent = document.getElementById(`items-content-${saleId}`);
  const icon = button.querySelector('svg');
  
  if (!detailsRow) return;
  
  // Toggle visibility
  const isExpanded = !detailsRow.classList.contains('hidden');
  
  if (isExpanded) {
    // Collapse
    detailsRow.classList.add('hidden');
    icon.classList.remove('rotate-180');
  } else {
    // Expand
    detailsRow.classList.remove('hidden');
    icon.classList.add('rotate-180');
    
    // Check if content is already loaded
    if (itemsContent.dataset.loaded !== 'true') {
      await loadSaleItems(saleId, itemsContent);
    }
  }
}

// Load Sale Items
async function loadSaleItems(saleId, container) {
  try {
    const response = await fetch(`${API_URL}?sale_id=${saleId}`);
    const result = await response.json();
    
    if (response.ok && result.success) {
      const sale = result.sale;
      const items = result.items || [];
      
      let itemsHTML = '';
      
      if (items.length === 0) {
        itemsHTML = `
          <div class="text-center py-8">
            <p class="text-sm text-gray-500">No items found for this transaction</p>
          </div>
        `;
      } else {
        itemsHTML = `
          <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse">
              <thead class="bg-gray-50 border-b-2 border-gray-300">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Description</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Batch</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase">Quantity</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Price</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Subtotal</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase">Discount</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Total</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
        `;
        
        items.forEach((item, index) => {
          const productName = item.brand_name || item.generic_name || 'Unknown Product';
          const productInfo = item.generic_name && item.generic_name !== item.brand_name ? item.generic_name : '';
          const strengthInfo = item.strength || '';
          
          const qtyDisplay = [];
          if (item.quantity_packages > 0) qtyDisplay.push(`${item.quantity_packages} pkg`);
          if (item.quantity_units > 0) qtyDisplay.push(`${item.quantity_units} ${item.sale_unit || 'unit'}`);
          const qtyText = qtyDisplay.length > 0 ? qtyDisplay.join(' + ') : '0';
          
          const itemDiscount = parseFloat(item.item_discount_amount || 0);
          const itemTotal = parseFloat(item.subtotal || 0) - itemDiscount;
          
          itemsHTML += `
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-900 text-sm">${productName}</div>
                    ${productInfo ? `<div class="text-xs text-gray-500 mt-0.5">${productInfo}</div>` : ''}
                    ${strengthInfo ? `<div class="text-xs text-gray-400 mt-0.5">${strengthInfo}</div>` : ''}
                  </td>
                  <td class="px-4 py-3">
                    <div class="text-xs font-medium text-gray-700">${item.batch_no || 'N/A'}</div>
                    ${item.expiry_date ? `<div class="text-xs text-gray-500 mt-0.5">Exp: ${new Date(item.expiry_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>` : ''}
                  </td>
                  <td class="px-4 py-3 text-center text-xs text-gray-700">${qtyText}</td>
                  <td class="px-4 py-3 text-right text-xs text-gray-900">₱${parseFloat(item.unit_price || 0).toFixed(2)}</td>
                  <td class="px-4 py-3 text-right text-xs text-gray-900">₱${parseFloat(item.subtotal || 0).toFixed(2)}</td>
                  <td class="px-4 py-3 text-center text-xs">
                    ${itemDiscount > 0 ? `<span class="text-red-600 font-medium">-₱${itemDiscount.toFixed(2)}</span>` : '<span class="text-gray-400">₱0.00</span>'}
                  </td>
                  <td class="px-4 py-3 text-right text-xs font-semibold text-gray-900">₱${itemTotal.toFixed(2)}</td>
                </tr>
          `;
        });
        
        itemsHTML += `
              </tbody>
              <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                <tr>
                  <td colspan="4" class="px-4 py-3 text-right text-xs font-bold text-gray-900">Subtotal:</td>
                  <td class="px-4 py-3 text-right text-xs font-bold text-gray-900">₱${parseFloat(sale.total_subtotal || 0).toFixed(2)}</td>
                  <td class="px-4 py-3 text-center text-xs font-bold text-red-600">-₱${parseFloat(sale.discount_amount || 0).toFixed(2)}</td>
                  <td class="px-4 py-3 text-right text-xs font-bold text-gray-900">₱${parseFloat(sale.total_amount || 0).toFixed(2)}</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
           
            </div>
            <div class="text-xs text-gray-700">
              <span class="font-semibold">Paid with ${sale.payment_method || 'cash'} tender</span>
              <span class="ml-2 font-bold text-lg text-gray-900">₱${parseFloat(sale.total_amount || 0).toFixed(2)}</span>
            </div>
          </div>
        `;
      }
      
      container.innerHTML = itemsHTML;
      container.dataset.loaded = 'true';
    } else {
      container.innerHTML = `
        <div class="text-center py-8">
          <p class="text-sm text-red-600">Failed to load items: ${result.error || 'Unknown error'}</p>
        </div>
      `;
    }
  } catch (error) {
    console.error('Error loading sale items:', error);
    container.innerHTML = `
      <div class="text-center py-8">
        <p class="text-sm text-red-600">Error loading items. Please try again.</p>
      </div>
    `;
  }
}

// View Sale Details (Legacy function - kept for backward compatibility)
async function viewSaleDetails(saleId) {
  // This function is now replaced by toggleSaleDetails
  // But we'll keep it for any other references
  const button = document.querySelector(`button[onclick*="${saleId}"]`);
  if (button) {
    toggleSaleDetails(saleId, button);
  }
}

function printSaleReceipt() {
  if (currentSaleId) {
    window.open(`../Controllers/printReceipt.php?id=${currentSaleId}`, '_blank');
  }
}

function closeSaleDetailsModal() {
  document.getElementById('saleDetailsModal').classList.add('hidden');
  document.getElementById('saleDetailsModal').classList.remove('flex');
  document.body.style.overflow = 'auto';
  currentSaleId = null;
  document.getElementById('printReceiptBtn').classList.add('hidden');
}

// Print all visible transactions
async function printFullReport() {
    const table = document.getElementById('salesTableBody');
    const visibleRows = Array.from(table.querySelectorAll('tr')).filter(row => {
        // Skip detail rows and only get main transaction rows
        return row.style.display !== 'none' && 
               !row.id?.startsWith('details-') && 
               row.dataset.saleId;
    });
    
    if (visibleRows.length === 0) {
        alert('No transactions to print.');
        return;
    }
    
    // Show loading message
    const loadingMsg = document.createElement('div');
    loadingMsg.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px;border:2px solid #2563eb;border-radius:8px;z-index:10000;';
    loadingMsg.innerHTML = '<p>Loading transaction details for print...</p><p style="font-size:12px;color:#666;">Please wait...</p>';
    document.body.appendChild(loadingMsg);
    
    // Calculate totals for all visible rows
    let totalTransactions = 0;
    let totalRevenue = 0;
    let totalItems = 0;
    
    // Collect all sale IDs and fetch their details
    const saleIds = [];
    const saleDataMap = new Map();
    
    visibleRows.forEach(row => {
        if (row.dataset.saleId) {
            totalTransactions++;
            const saleId = row.dataset.saleId;
            saleIds.push(saleId);
            
            // Get amount from data attribute
            const amount = parseFloat(row.getAttribute('data-amount') || '0');
            totalRevenue += amount;
            
            // Get items from data attribute
            const items = parseInt(row.getAttribute('data-items') || '0');
            totalItems += items;
        }
    });
    
    const averageAmount = totalTransactions > 0 ? totalRevenue / totalTransactions : 0;
    
    // Fetch all sale details
    try {
        const fetchPromises = saleIds.map(async (saleId) => {
            try {
                const response = await fetch(`${API_URL}?sale_id=${saleId}`);
                const result = await response.json();
                if (response.ok && result.success) {
                    saleDataMap.set(saleId, result);
                }
            } catch (error) {
                console.error(`Error fetching sale ${saleId}:`, error);
            }
        });
        
        await Promise.all(fetchPromises);
    } catch (error) {
        console.error('Error fetching sale details:', error);
    }
    
    // Remove loading message
    document.body.removeChild(loadingMsg);
    
    // Get filter information
    const startDate = document.getElementById('startDate')?.value || 'All Time';
    const endDate = document.getElementById('endDate')?.value || 'All Time';
    const cashierFilter = document.getElementById('cashierFilter')?.value || 'All Cashiers';
    const searchInput = document.getElementById('searchInput')?.value || '';
    
    // Create print window
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Sales Transactions Report</title>
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
                    line-height: 1.5;
                    background: #fff;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 3px solid #2563eb;
                    padding-bottom: 15px;
                }
                .print-header h1 {
                    font-size: 28px;
                    margin: 0 0 8px 0;
                    color: #1e293b;
                    font-weight: 700;
                }
                .print-header p {
                    margin: 3px 0;
                    color: #64748b;
                    font-size: 11px;
                }
                .print-summary-cards {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 15px;
                    margin: 20px 0;
                    margin-bottom: 25px;
                }
                .summary-card {
                    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    padding: 15px;
                    text-align: center;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .summary-card .label {
                    font-size: 10px;
                    color: #475569;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                }
                .summary-card .value {
                    font-size: 20px;
                    font-weight: 700;
                    color: #1e293b;
                }
                .print-info {
                    margin-bottom: 20px;
                    padding: 12px 15px;
                    background: #f1f5f9;
                    border-left: 4px solid #2563eb;
                    border-radius: 4px;
                }
                .print-info p {
                    margin: 4px 0;
                    font-size: 11px;
                    color: #334155;
                }
                .print-info strong {
                    color: #1e293b;
                    font-weight: 600;
                }
                .print-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .print-table thead {
                    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
                }
                .print-table th {
                    color: #fff;
                    font-weight: 700;
                    padding: 12px 8px;
                    text-align: left;
                    border: 1px solid #1e3a8a;
                    font-size: 10px;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                .print-table tbody td {
                    padding: 10px 8px;
                    border: 1px solid #e2e8f0;
                    font-size: 10px;
                    color: #334155;
                    word-wrap: break-word;
                    max-width: 200px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .print-table tfoot {
                    background: #f1f5f9;
                }
                .print-table tfoot td {
                    font-weight: 700;
                    border-top: 2px solid #2563eb;
                }
                .print-table tbody tr {
                    border-bottom: 1px solid #e2e8f0;
                }
                .print-table tbody tr:nth-child(even) {
                    background: #f8fafc;
                }
                .print-table tbody tr:hover {
                    background: #f1f5f9;
                }
                .item-details-row {
                    background: #f8fafc !important;
                }
                .item-details-row td {
                    padding: 8px 8px 8px 40px !important;
                    border-top: none !important;
                    border-bottom: 1px solid #e2e8f0;
                }
                .item-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 0;
                    font-size: 9px;
                }
                .item-table thead {
                    background: #e2e8f0;
                }
                .item-table th {
                    padding: 6px 4px;
                    text-align: left;
                    font-size: 8px;
                    font-weight: 600;
                    color: #334155;
                    border: 1px solid #cbd5e1;
                }
                .item-table td {
                    padding: 6px 4px;
                    border: 1px solid #e2e8f0;
                    font-size: 9px;
                    color: #475569;
                }
                .item-table tbody tr {
                    background: #fff;
                }
                .item-table tbody tr:nth-child(even) {
                    background: #f8fafc;
                }
                @page {
                    margin: 1cm;
                    size: A4 landscape;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
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
                        padding: 10px;
                        font-size: 9px;
                    }
                    .print-header {
                        margin-bottom: 15px;
                        padding-bottom: 10px;
                    }
                    .print-header h1 {
                        font-size: 24px;
                    }
                    .print-summary-cards {
                        page-break-inside: avoid;
                        margin: 15px 0;
                        gap: 10px;
                    }
                    .summary-card {
                        padding: 10px;
                    }
                    .summary-card .value {
                        font-size: 18px;
                    }
                    .print-table {
                        page-break-inside: auto;
                        font-size: 9px;
                    }
                    .print-table thead {
                        display: table-header-group;
                    }
                    .print-table th,
                    .print-table td {
                        padding: 8px 6px;
                        font-size: 9px;
                    }
                    .item-table {
                        font-size: 8px;
                    }
                    .item-table th,
                    .item-table td {
                        padding: 4px 3px;
                        font-size: 8px;
                    }
                    .item-details-row td {
                        padding: 4px !important;
                    }
                    .print-info {
                        margin-bottom: 15px;
                        padding: 10px;
                    }
                    .print-footer {
                        margin-top: 20px;
                        padding-top: 10px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>Sales Transactions Report</h1>
                <p>OCT Pharmacy Management System</p>
                <p>Generated on ${new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
            </div>
            
            <div class="print-summary-cards">
                <div class="summary-card">
                    <div class="label">Total Transactions</div>
                    <div class="value">${totalTransactions}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Total Revenue</div>
                    <div class="value">₱${totalRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Total Items</div>
                    <div class="value">${totalItems.toLocaleString()}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Average Amount</div>
                    <div class="value">₱${averageAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                </div>
            </div>
            
            <div class="print-info">
                <p><strong>Report Period:</strong> ${startDate !== 'All Time' ? new Date(startDate).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'All Time'} ${endDate !== 'All Time' && startDate !== 'All Time' ? ' to ' + new Date(endDate).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : ''}</p>
                ${cashierFilter !== 'All Cashiers' ? `<p><strong>Cashier Filter:</strong> ${cashierFilter}</p>` : ''}
                ${searchInput ? `<p><strong>Search Term:</strong> ${searchInput}</p>` : ''}
            </div>
            
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">Transaction ID</th>
                        <th style="width: 12%;">Time</th>
                        <th style="width: 12%;">Cashier</th>
                        <th style="width: 25%;">Products</th>
                        <th style="width: 8%;" class="text-center">Items</th>
                        <th style="width: 12%;" class="text-right">Amount</th>
                        <th style="width: 10%;" class="text-center">Discount</th>
                        <th style="width: 11%;" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
    `);
    
    // Add all visible rows to print with item details
    for (const row of visibleRows) {
        // Skip if not a data row
        if (!row.dataset.saleId || row.cells.length < 8) {
            continue;
        }
        
        // Columns: 0=Expand, 1=Time, 2=Cashier, 3=Products, 4=Items, 5=Amount, 6=Discount, 7=Total
        const saleId = row.dataset.saleId || '';
        const time = row.cells[1]?.textContent.trim() || '';
        const cashier = row.cells[2]?.textContent.trim() || '';
        const products = row.cells[3]?.textContent.trim() || '';
        const items = row.cells[4]?.textContent.trim() || '';
        const amount = row.cells[5]?.textContent.trim() || '';
        const discount = row.cells[6]?.textContent.trim() || '₱0.00';
        const total = row.cells[7]?.textContent.trim() || '';
        
        // Get sale details if available
        const saleData = saleDataMap.get(saleId);
        const saleItems = saleData?.items || [];
        
        // Main transaction row
        printWindow.document.write(`
                <tr>
                    <td style="padding: 10px 8px; font-weight: 600;">#${saleId}</td>
                    <td style="padding: 10px 8px;">${time}</td>
                    <td style="padding: 10px 8px;">${cashier}</td>
                    <td style="padding: 10px 8px;">${products}</td>
                    <td style="padding: 10px 8px; text-align: center;">${items}</td>
                    <td style="padding: 10px 8px; text-align: right;">${amount}</td>
                    <td style="padding: 10px 8px; text-align: center;">${discount}</td>
                    <td style="padding: 10px 8px; text-align: right; font-weight: 600;">${total}</td>
                </tr>
            `);
        
        // Item details row
        if (saleItems.length > 0) {
            printWindow.document.write(`
                <tr class="item-details-row">
                    <td colspan="8" style="padding: 0;">
                        <table class="item-table">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">Description</th>
                                    <th style="width: 12%;">Batch</th>
                                    <th style="width: 10%; text-align: center;">Quantity</th>
                                    <th style="width: 12%; text-align: right;">Price</th>
                                    <th style="width: 12%; text-align: right;">Subtotal</th>
                                    <th style="width: 10%; text-align: center;">Discount</th>
                                    <th style="width: 14%; text-align: right;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
            `);
            
            saleItems.forEach((item) => {
                const productName = item.brand_name || item.generic_name || 'Unknown Product';
                const productInfo = item.generic_name && item.generic_name !== item.brand_name ? item.generic_name : '';
                const strengthInfo = item.strength || '';
                
                const qtyDisplay = [];
                if (item.quantity_packages > 0) qtyDisplay.push(`${item.quantity_packages} pkg`);
                if (item.quantity_units > 0) qtyDisplay.push(`${item.quantity_units} ${item.sale_unit || 'unit'}`);
                const qtyText = qtyDisplay.length > 0 ? qtyDisplay.join(' + ') : '0';
                
                const itemDiscount = parseFloat(item.item_discount_amount || 0);
                const itemTotal = parseFloat(item.subtotal || 0) - itemDiscount;
                
                printWindow.document.write(`
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #1e293b;">${productName}</div>
                                        ${productInfo ? `<div style="font-size: 8px; color: #64748b;">${productInfo}</div>` : ''}
                                        ${strengthInfo ? `<div style="font-size: 8px; color: #94a3b8;">${strengthInfo}</div>` : ''}
                                    </td>
                                    <td>
                                        <div style="font-size: 9px;">${item.batch_no || 'N/A'}</div>
                                        ${item.expiry_date ? `<div style="font-size: 8px; color: #64748b;">Exp: ${new Date(item.expiry_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>` : ''}
                                    </td>
                                    <td style="text-align: center;">${qtyText}</td>
                                    <td style="text-align: right;">₱${parseFloat(item.unit_price || 0).toFixed(2)}</td>
                                    <td style="text-align: right;">₱${parseFloat(item.subtotal || 0).toFixed(2)}</td>
                                    <td style="text-align: center; color: ${itemDiscount > 0 ? '#dc2626' : '#94a3b8'};">
                                        ${itemDiscount > 0 ? `-₱${itemDiscount.toFixed(2)}` : '₱0.00'}
                                    </td>
                                    <td style="text-align: right; font-weight: 600;">₱${itemTotal.toFixed(2)}</td>
                                </tr>
                `);
            });
            
            // Item totals row
            if (saleData?.sale) {
                const sale = saleData.sale;
                printWindow.document.write(`
                                <tr style="background: #e2e8f0; font-weight: 600; border-top: 2px solid #cbd5e1;">
                                    <td colspan="3" style="text-align: right; padding: 8px 4px;">Subtotal:</td>
                                    <td style="text-align: right; padding: 8px 4px;">₱${parseFloat(sale.total_subtotal || 0).toFixed(2)}</td>
                                    <td style="text-align: center; padding: 8px 4px; color: #dc2626;">-₱${parseFloat(sale.discount_amount || 0).toFixed(2)}</td>
                                    <td colspan="2" style="text-align: right; padding: 8px 4px; color: #2563eb; font-size: 10px;">₱${parseFloat(sale.total_amount || 0).toFixed(2)}</td>
                                </tr>
                `);
            }
            
            printWindow.document.write(`
                            </tbody>
                        </table>
                    </td>
                </tr>
            `);
        }
    }
    
    // Calculate totals for print
    let printTotalRevenue = 0;
    let printTotalItems = 0;
    visibleRows.forEach(row => {
        if (row.dataset.saleId && row.cells.length >= 8) {
            const amount = parseFloat(row.getAttribute('data-amount') || '0');
            const items = parseInt(row.getAttribute('data-items') || '0');
            printTotalRevenue += amount;
            printTotalItems += items;
        }
    });
    
    printWindow.document.write(`
                </tbody>
                <tfoot style="background: #f1f5f9; border-top: 2px solid #2563eb;">
                    <tr>
                        <td colspan="4" style="padding: 12px 8px; font-weight: 700; text-align: right; font-size: 11px;">TOTALS:</td>
                        <td style="padding: 12px 8px; font-weight: 700; text-align: center; font-size: 11px;">${printTotalItems}</td>
                        <td style="padding: 12px 8px; font-weight: 700; text-align: right; font-size: 11px;">₱${printTotalRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td colspan="2" style="padding: 12px 8px; font-weight: 700; text-align: right; font-size: 11px; color: #2563eb;">₱${printTotalRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    </tr>
                </tfoot>
            </table>
            
            <div class="print-footer">
                <p><strong>Generated by OCT Pharmacy Management System</strong> | ${new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
                <p style="margin-top: 5px;">This is a computer-generated report. No signature required.</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Add event listeners for filters and date inputs
document.addEventListener('DOMContentLoaded', function() {
    const cashierFilter = document.getElementById('cashierFilter');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    
    if (cashierFilter) {
        cashierFilter.addEventListener('change', filterSales);
    }
    
    // Auto-update when date inputs change
    if (startDate) {
        startDate.addEventListener('change', updateDateRange);
    }
    
    if (endDate) {
        endDate.addEventListener('change', updateDateRange);
    }
    
    // Close modal when clicking outside
    const modal = document.getElementById('saleDetailsModal');
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeSaleDetailsModal();
            }
        });
    }
});
// Stock Transfer Functions

// Transfer Stock Modal Functions
function openTransferStockModal() {
  const modal = document.getElementById('transferStockModal');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const form = document.getElementById('transferStockForm');
    if (form) form.reset();
    document.getElementById('transferTotalUnits').value = 0;
    document.getElementById('batchInfo').textContent = '';
  }
}

function closeTransferStockModal() {
  const modal = document.getElementById('transferStockModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

// Auto-calculate total units when batch or quantity changes
document.addEventListener('DOMContentLoaded', function() {
  const batchSelect = document.getElementById('transferBatch');
  const quantityInput = document.getElementById('transferQuantityPackages');
  const totalUnitsInput = document.getElementById('transferTotalUnits');
  const batchInfo = document.getElementById('batchInfo');
  
  function calculateTotalUnits() {
    if (!batchSelect || !quantityInput || !totalUnitsInput) return;
    
    const selectedOption = batchSelect.options[batchSelect.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
      totalUnitsInput.value = 0;
      if (batchInfo) batchInfo.textContent = '';
      return;
    }
    
    const unitsPerPackage = parseInt(selectedOption.getAttribute('data-units-per-package') || '0');
    const availablePackages = parseInt(selectedOption.getAttribute('data-available-packages') || '0');
    const quantityPackages = parseInt(quantityInput.value || '0');
    const totalUnits = unitsPerPackage * quantityPackages;
    
    totalUnitsInput.value = totalUnits;
    
    // Update batch info
    if (batchInfo) {
      if (availablePackages > 0) {
        batchInfo.textContent = `Available: ${availablePackages} packages | Units per package: ${unitsPerPackage}`;
        batchInfo.className = 'text-xs text-gray-500 mt-1';
        
        // Warn if exceeding available
        if (quantityPackages > availablePackages) {
          batchInfo.textContent = `⚠️ Warning: Requested ${quantityPackages} packages, but only ${availablePackages} available!`;
          batchInfo.className = 'text-xs text-red-600 mt-1 font-semibold';
        }
      } else {
        batchInfo.textContent = `Units per package: ${unitsPerPackage}`;
        batchInfo.className = 'text-xs text-gray-500 mt-1';
      }
    }
  }
  
  if (batchSelect) {
    batchSelect.addEventListener('change', calculateTotalUnits);
  }
  
  if (quantityInput) {
    quantityInput.addEventListener('input', calculateTotalUnits);
    quantityInput.addEventListener('change', calculateTotalUnits);
  }
});

// Filter transfers table
function filterTransfers() {
  const searchInput = document.getElementById('searchInput')?.value.toLowerCase() || '';
  const table = document.getElementById('transfersTableBody');
  
  if (!table) return;
  
  const rows = table.getElementsByTagName('tr');
  
  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    if (row.cells && row.cells.length > 0) {
      // Check product name, batch, and user
      const productText = (row.cells[1]?.textContent || '').toLowerCase();
      const batchText = (row.cells[2]?.textContent || '').toLowerCase();
      const userText = (row.cells[5]?.textContent || '').toLowerCase();
      
      if (productText.includes(searchInput) || batchText.includes(searchInput) || userText.includes(searchInput)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    }
  }
}

// Apply filters - reload page with URL parameters
function applyFilters() {
  const startDate = document.getElementById('filterStartDate')?.value || '';
  const endDate = document.getElementById('filterEndDate')?.value || '';
  const user = document.getElementById('filterUser')?.value || '';
  const product = document.getElementById('filterProduct')?.value || '';
  
  const params = new URLSearchParams();
  if (startDate) params.set('start_date', startDate);
  if (endDate) params.set('end_date', endDate);
  if (user) params.set('user', user);
  if (product) params.set('product', product);
  
  window.location.href = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
}

// Clear all filters
function clearFilters() {
  window.location.href = window.location.pathname;
}

// Auto-update on date input change with debounce
let dateFilterTimeout;
document.addEventListener('DOMContentLoaded', function() {
  const startDateInput = document.getElementById('filterStartDate');
  const endDateInput = document.getElementById('filterEndDate');
  
  function updateFilters() {
    clearTimeout(dateFilterTimeout);
    dateFilterTimeout = setTimeout(applyFilters, 300);
  }
  
  if (startDateInput) {
    startDateInput.addEventListener('change', updateFilters);
  }
  
  if (endDateInput) {
    endDateInput.addEventListener('change', updateFilters);
  }
});

// View Transfer Details Modal Functions
function openViewTransferModal(transfer) {
  const modal = document.getElementById('viewTransferModal');
  if (!modal) {
    console.error('View transfer modal not found');
    return;
  }
  
  // Format date and time
  const transferDate = transfer.movement_date || '';
  let formattedDate = 'N/A';
  let formattedTime = 'N/A';
  
  if (transferDate && transferDate !== '0000-00-00 00:00:00' && transferDate !== '') {
    try {
      const date = new Date(transferDate);
      formattedDate = date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric'
      });
      formattedTime = date.toLocaleTimeString('en-US', { 
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (e) {
      formattedDate = transferDate;
      formattedTime = transferDate;
    }
  }
  
  // Populate modal with transfer data
  document.getElementById('viewTransferDate').textContent = formattedDate;
  document.getElementById('viewTransferTime').textContent = formattedTime;
  document.getElementById('viewTransferUser').textContent = transfer.username || 'System';
  document.getElementById('viewTransferType').textContent = 'Transfer';
  
  // Product Information
  document.getElementById('viewTransferBrandName').textContent = transfer.brand_name || 'N/A';
  document.getElementById('viewTransferGenericName').textContent = transfer.generic_name || 'N/A';
  document.getElementById('viewTransferBatchNo').textContent = transfer.batch_no || 'N/A';
  
  // Expiry Date
  const expiryDate = transfer.expiry_date || '';
  if (expiryDate && expiryDate !== '0000-00-00' && expiryDate !== '') {
    try {
      const date = new Date(expiryDate);
      document.getElementById('viewTransferExpiry').textContent = date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric'
      });
    } catch (e) {
      document.getElementById('viewTransferExpiry').textContent = expiryDate;
    }
  } else {
    document.getElementById('viewTransferExpiry').textContent = 'N/A';
  }
  
  // Transfer Details - always Storage to Pharmacy
  document.getElementById('viewTransferFrom').textContent = (transfer.from_inventory || 'Storage').charAt(0).toUpperCase() + (transfer.from_inventory || 'Storage').slice(1);
  document.getElementById('viewTransferTo').textContent = (transfer.to_inventory || 'Pharmacy').charAt(0).toUpperCase() + (transfer.to_inventory || 'Pharmacy').slice(1);
  
  // Packages
  const packages = parseInt(transfer.quantity_packages) || 0;
  document.getElementById('viewTransferPackages').textContent = packages > 0 ? packages.toLocaleString() + ' packages' : '0';
  
  // Units
  const units = parseInt(transfer.quantity_units) || 0;
  const unitName = transfer.unit_name || 'units';
  document.getElementById('viewTransferUnits').textContent = units > 0 ? units.toLocaleString() + ' ' + unitName : '0';
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeViewTransferModal() {
  const modal = document.getElementById('viewTransferModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function printTransferDetails() {
  const content = document.getElementById('printableTransferContent').innerHTML;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Transfer Details</title>
      <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .bg-gray-50 { background-color: #f9fafb; padding: 15px; border-radius: 8px; }
        h4 { font-size: 16px; font-weight: 600; margin-bottom: 15px; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }
        p { margin: 0; }
        .text-xs { font-size: 12px; }
        .text-sm { font-size: 14px; }
        .text-gray-500 { color: #6b7280; }
        .text-gray-900 { color: #111827; font-weight: 500; }
      </style>
    </head>
    <body>
      ${content}
    </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.onload = function() {
    printWindow.print();
  };
}

// Print functionality
function toggleAllTransfers(selectAllCheckbox) {
  const checkboxes = document.querySelectorAll('.transfer-checkbox');
  checkboxes.forEach(checkbox => {
    checkbox.checked = selectAllCheckbox.checked;
  });
  updatePrintButton();
}

function updatePrintButton() {
  const checkboxes = document.querySelectorAll('.transfer-checkbox');
  const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
  
  // Update select all checkbox state
  const selectAllCheckbox = document.getElementById('selectAllTransfers');
  if (selectAllCheckbox) {
    selectAllCheckbox.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
  }
}

function printFullReport() {
  // Get all transfer rows from the table (excluding header and empty state rows)
  const tableBody = document.getElementById('transfersTableBody');
  if (!tableBody) {
    alert('No transfer data found.');
    return;
  }
  
  const allRows = Array.from(tableBody.querySelectorAll('tr')).filter(row => {
    // Exclude the "No Transfers Found" row
    return !row.querySelector('td[colspan]');
  });
  
  if (allRows.length === 0) {
    alert('No transfers to print.');
    return;
  }
  
  // Collect all rows
  const selectedRows = [];
  allRows.forEach(row => {
    // Clone the row (no checkbox to remove anymore)
    const clonedRow = row.cloneNode(true);
    selectedRows.push(clonedRow);
  });
  
  // Calculate totals
  let totalTransfers = 0;
  let totalPackages = 0;
  let totalUnits = 0;
  
  selectedRows.forEach(row => {
    totalTransfers++;
    
    // Get quantities from data attributes (more reliable)
    const packages = parseInt(row.getAttribute('data-packages') || '0');
    const units = parseInt(row.getAttribute('data-units') || '0');
    
    totalPackages += packages;
    totalUnits += units;
  });
  
  // Create print window
  const printWindow = window.open('', '_blank');
  
  // Get filter information
  const startDate = document.getElementById('filterStartDate')?.value || '';
  const endDate = document.getElementById('filterEndDate')?.value || '';
  const user = document.getElementById('filterUser')?.value || '';
  const product = document.getElementById('filterProduct')?.value || '';
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Stock Transfer Report - All Items</title>
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
          border-bottom: 3px solid #7c3aed;
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
          grid-template-columns: repeat(3, 1fr);
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
          border-left: 4px solid #7c3aed;
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
          background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 100%);
        }
        .print-table th {
          color: #fff;
          font-weight: 700;
          padding: 12px 8px;
          text-align: left;
          border: 1px solid #5b21b6;
          font-size: 10px;
          text-transform: uppercase;
          letter-spacing: 0.3px;
        }
        .print-table tbody td {
          padding: 10px 8px;
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
        .print-table tfoot {
          background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }
        .print-table tfoot td {
          padding: 12px 8px;
          border: 2px solid #cbd5e1;
          font-weight: 700;
          font-size: 11px;
          color: #1e293b;
        }
        .text-right {
          text-align: right;
        }
        .text-center {
          text-align: center;
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
          .print-summary-cards {
            page-break-inside: avoid;
          }
          .print-table {
            page-break-inside: auto;
          }
          .print-table thead {
            display: table-header-group;
          }
          .print-table tfoot {
            display: table-footer-group;
          }
        }
      </style>
    </head>
    <body>
      <div class="print-header">
        <h1>📦 Stock Transfer Report - All Items</h1>
        ${startDate && endDate ? `<p><strong>Period:</strong> ${startDate} - ${endDate}</p>` : ''}
        ${user ? `<p><strong>User:</strong> ${user}</p>` : ''}
        ${product ? `<p><strong>Product:</strong> ${product}</p>` : ''}
      </div>
      
      <div class="print-summary-cards">
        <div class="summary-card">
          <div class="label">Total Transfers</div>
          <div class="value">${totalTransfers}</div>
        </div>
        <div class="summary-card">
          <div class="label">Total Packages</div>
          <div class="value">${totalPackages.toLocaleString()}</div>
        </div>
        <div class="summary-card">
          <div class="label">Total Units</div>
          <div class="value">${totalUnits.toLocaleString()}</div>
        </div>
      </div>
      
      <div class="print-info">
        <p><strong>Total Items:</strong> ${totalTransfers} transfer${totalTransfers !== 1 ? 's' : ''}</p>
        ${startDate && endDate ? `<p><strong>Period:</strong> ${startDate} - ${endDate}</p>` : ''}
        ${user ? `<p><strong>User:</strong> ${user}</p>` : ''}
        ${product ? `<p><strong>Product:</strong> ${product}</p>` : ''}
      </div>
      
      <table class="print-table">
        <thead>
          <tr>
            <th style="width: 15%;">Date & Time</th>
            <th style="width: 25%;">Product</th>
            <th style="width: 15%;">Batch</th>
            <th style="width: 12%;" class="text-right">Packages</th>
            <th style="width: 15%;" class="text-right">Units</th>
            <th style="width: 18%;">Transferred By</th>
          </tr>
        </thead>
        <tbody>
  `);
  
  selectedRows.forEach(row => {
    // Clean up the row HTML for printing
    const cells = row.querySelectorAll('td');
    let rowHtml = '<tr>';
    
    cells.forEach((cell, index) => {
      // Column mapping: 0=Date/Time, 1=Product, 2=Batch, 3=Packages, 4=Units, 5=Transferred By
      const className = index === 3 || index === 4 ? 'text-right' : '';
      let cellContent = '';
      
      if (index === 0) {
        // Date/Time column - combine date and time
        const dateDiv = cell.querySelector('.text-gray-900');
        const timeDiv = cell.querySelector('.text-gray-500');
        const date = dateDiv ? dateDiv.textContent.trim() : '';
        const time = timeDiv ? timeDiv.textContent.trim() : '';
        cellContent = date + (time ? ' ' + time : '');
      } else if (index === 1) {
        // Product column - combine brand and generic name
        const brandName = cell.querySelector('.font-semibold')?.textContent?.trim() || '';
        const genericName = cell.querySelector('.text-gray-500')?.textContent?.trim() || '';
        cellContent = brandName + (genericName ? ' / ' + genericName : '');
      } else if (index === 2) {
        // Batch column - get text from span
        const batchSpan = cell.querySelector('span');
        cellContent = batchSpan ? batchSpan.textContent.trim() : cell.textContent.trim();
      } else {
        // Other columns - get text content
        cellContent = cell.textContent.trim().replace(/\s+/g, ' ');
      }
      
      rowHtml += `<td class="${className}" style="padding: 10px 8px;">${cellContent}</td>`;
    });
    
    rowHtml += '</tr>';
    printWindow.document.write(rowHtml);
  });
  
  printWindow.document.write(`
        </tbody>
        <tfoot>
          <tr class="text-bold">
            <td colspan="3" class="text-right" style="padding: 12px 8px;">TOTAL:</td>
            <td class="text-right" style="padding: 12px 8px;">${totalPackages.toLocaleString()}</td>
            <td class="text-right" style="padding: 12px 8px;">${totalUnits.toLocaleString()}</td>
            <td style="padding: 12px 8px;"></td>
          </tr>
        </tfoot>
      </table>
      
      <div class="print-footer">
        <p><strong>Generated by Pharmacy Management System</strong> | ${new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
      </div>
    </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.print();
}

// Expose functions to global scope
window.openTransferStockModal = openTransferStockModal;
window.closeTransferStockModal = closeTransferStockModal;
window.filterTransfers = filterTransfers;
window.applyFilters = applyFilters;
window.clearFilters = clearFilters;
window.openViewTransferModal = openViewTransferModal;
window.closeViewTransferModal = closeViewTransferModal;
window.printTransferDetails = printTransferDetails;
window.toggleAllTransfers = toggleAllTransfers;
window.updatePrintButton = updatePrintButton;
window.printFullReport = printFullReport;


// Stock Movement Log - Filter Function Only (Read-Only Viewer)
function filterMovements() {
  const searchInput = document.getElementById('searchInput').value.toLowerCase();
  
  // Filter table rows (desktop view)
  const table = document.getElementById('movementsTableBody');
  if (table) {
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      if (row.classList.contains('movement-row')) {
        const productName = row.getAttribute('data-product') || '';
        const batchNo = row.getAttribute('data-batch') || '';
        const movementType = row.getAttribute('data-type') || '';
        
        if (productName.includes(searchInput) || batchNo.includes(searchInput) || movementType.includes(searchInput)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      }
    }
  }
  
  // Filter mobile cards
  const cards = document.querySelectorAll('.movement-card');
  cards.forEach(card => {
    const productName = card.getAttribute('data-product') || '';
    const batchNo = card.getAttribute('data-batch') || '';
    const movementType = card.getAttribute('data-type') || '';
    
    if (productName.includes(searchInput) || batchNo.includes(searchInput) || movementType.includes(searchInput)) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });
}

// Apply filters - reload page with URL parameters
function applyFilters() {
  const startDate = document.getElementById('filterStartDate')?.value || '';
  const endDate = document.getElementById('filterEndDate')?.value || '';
  const movementType = document.getElementById('filterMovementType')?.value || '';
  const user = document.getElementById('filterUser')?.value || '';
  
  const params = new URLSearchParams();
  if (startDate) params.set('start_date', startDate);
  if (endDate) params.set('end_date', endDate);
  if (movementType) params.set('movement_type', movementType);
  if (user) params.set('user', user);
  
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

// View Movement Details Modal Functions
function openViewMovementModal(movement) {
  const modal = document.getElementById('viewMovementModal');
  if (!modal) {
    console.error('View movement modal not found');
    return;
  }
  
  // Format date and time
  const movementDate = movement.movement_date || '';
  let formattedDate = 'N/A';
  let formattedTime = 'N/A';
  
  if (movementDate && movementDate !== '0000-00-00 00:00:00' && movementDate !== '') {
    try {
      const date = new Date(movementDate);
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
      formattedDate = movementDate;
      formattedTime = movementDate;
    }
  }
  
  // Populate modal with movement data
  document.getElementById('viewMovementDate').textContent = formattedDate;
  document.getElementById('viewMovementTime').textContent = formattedTime;
  
  // Movement Type
  const movementType = movement.movement_type || 'N/A';
  const typeLabels = {
    'delivery': 'Delivery',
    'sale': 'Sale',
    'transfer': 'Transfer',
    'adjustment': 'Adjustment'
  };
  document.getElementById('viewMovementType').textContent = typeLabels[movementType] || movementType;
  
  // User
  document.getElementById('viewMovementUser').textContent = movement.username || 'System';
  
  // Product Information
  document.getElementById('viewMovementBrandName').textContent = movement.brand_name || 'N/A';
  document.getElementById('viewMovementGenericName').textContent = movement.generic_name || 'N/A';
  document.getElementById('viewMovementBatchNo').textContent = movement.batch_no || 'N/A';
  
  // Expiry Date
  const expiryDate = movement.expiry_date || '';
  if (expiryDate && expiryDate !== '0000-00-00' && expiryDate !== '') {
    try {
      const date = new Date(expiryDate);
      document.getElementById('viewMovementExpiry').textContent = date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric'
      });
    } catch (e) {
      document.getElementById('viewMovementExpiry').textContent = expiryDate;
    }
  } else {
    document.getElementById('viewMovementExpiry').textContent = 'N/A';
  }
  
  // Movement Details
  document.getElementById('viewMovementFrom').textContent = (movement.from_inventory || 'N/A').charAt(0).toUpperCase() + (movement.from_inventory || 'N/A').slice(1);
  document.getElementById('viewMovementTo').textContent = (movement.to_inventory || 'N/A').charAt(0).toUpperCase() + (movement.to_inventory || 'N/A').slice(1);
  
  // Packages
  const packages = parseInt(movement.quantity_packages) || 0;
  document.getElementById('viewMovementPackages').textContent = packages > 0 ? packages.toLocaleString() + ' packages' : '0';
  
  // Units
  const units = parseInt(movement.quantity_units) || 0;
  const unitName = movement.unit_name || 'units';
  document.getElementById('viewMovementUnits').textContent = units > 0 ? units.toLocaleString() + ' ' + unitName : '0';
  
  // Reference Information
  document.getElementById('viewMovementRefType').textContent = movement.reference_type ? (movement.reference_type.charAt(0).toUpperCase() + movement.reference_type.slice(1)) : 'N/A';
  document.getElementById('viewMovementRefId').textContent = movement.reference_id ? '#' + movement.reference_id : 'N/A';
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeViewMovementModal() {
  const modal = document.getElementById('viewMovementModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function printMovementDetails() {
  const content = document.getElementById('printableMovementContent').innerHTML;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Movement Details</title>
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
function printFullReport() {
  // Get all visible movement rows (not hidden by filters)
  const tableBody = document.getElementById('movementsTableBody');
  if (!tableBody) {
    alert('No movements table found.');
    return;
  }
  
  const allRows = tableBody.querySelectorAll('tr.movement-row');
  const visibleRows = Array.from(allRows).filter(row => row.style.display !== 'none');
  
  if (visibleRows.length === 0) {
    alert('No movements to print. Please check your filters.');
    return;
  }
  
  // Collect visible rows
  const selectedRows = [];
  visibleRows.forEach(row => {
    selectedRows.push(row.cloneNode(true));
  });
  
  // Calculate totals
  let totalMovements = 0;
  let totalPackages = 0;
  let totalUnits = 0;
  
  selectedRows.forEach(row => {
    totalMovements++;
    
    // Get quantities from data attributes (more reliable)
    const packages = parseInt(row.getAttribute('data-packages') || '0');
    const units = parseInt(row.getAttribute('data-units') || '0');
    
    totalPackages += packages;
    totalUnits += units;
  });
  
  const totalCount = selectedRows.length;
  
  // Create print window
  const printWindow = window.open('', '_blank');
  
  // Get filter information
  const startDate = document.getElementById('filterStartDate')?.value || '';
  const endDate = document.getElementById('filterEndDate')?.value || '';
  const movementType = document.getElementById('filterMovementType')?.value || '';
  const user = document.getElementById('filterUser')?.value || '';
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Stock Movement Report - Selected Items</title>
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
          border-bottom: 3px solid #4f46e5;
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
          border-left: 4px solid #4f46e5;
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
          background: linear-gradient(135deg, #312e81 0%, #4f46e5 100%);
        }
        .print-table th {
          color: #fff;
          font-weight: 700;
          padding: 12px 8px;
          text-align: left;
          border: 1px solid #312e81;
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
        <h1>📋 Stock Movement Report - All Items</h1>
        ${startDate && endDate ? `<p><strong>Period:</strong> ${startDate} - ${endDate}</p>` : ''}
        ${movementType ? `<p><strong>Movement Type:</strong> ${movementType}</p>` : ''}
        ${user ? `<p><strong>User:</strong> ${user}</p>` : ''}
      </div>
      
      <div class="print-summary-cards">
        <div class="summary-card">
          <div class="label">Total Movements</div>
          <div class="value">${totalCount}</div>
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
        <p><strong>Total Movements:</strong> ${totalCount} movement${totalCount !== 1 ? 's' : ''}</p>
        ${startDate && endDate ? `<p><strong>Period:</strong> ${startDate} - ${endDate}</p>` : ''}
        ${movementType ? `<p><strong>Movement Type:</strong> ${movementType}</p>` : ''}
        ${user ? `<p><strong>User:</strong> ${user}</p>` : ''}
      </div>
      
      <table class="print-table">
        <thead>
          <tr>
            <th style="width: 12%;">Date & Time</th>
            <th style="width: 18%;">Product</th>
            <th style="width: 10%;">Batch</th>
            <th style="width: 10%;">Movement Type</th>
            <th style="width: 12%;">From → To</th>
            <th style="width: 12%;" class="text-right">Quantity</th>
            <th style="width: 12%;">Reference</th>
            <th style="width: 14%;">User</th>
          </tr>
        </thead>
        <tbody>
  `);
  
  selectedRows.forEach(row => {
    // Clean up the row HTML for printing
    const cells = row.querySelectorAll('td');
    let rowHtml = '<tr>';
    
    cells.forEach((cell, index) => {
      const text = cell.textContent.trim();
      const className = index === 5 ? 'text-right' : '';
      
      // Clean up product name
      let cellContent = text.replace(/\s+/g, ' ');
      
      if (index === 1 && cell.querySelector('.min-w-0')) {
        // Product column
        const brandName = cell.querySelector('.font-semibold')?.textContent?.trim() || '';
        const genericName = cell.querySelector('.text-gray-500')?.textContent?.trim() || '';
        cellContent = brandName + (genericName ? ' / ' + genericName : '');
      }
      
      if (index === 3 && cell.querySelector('.inline-flex')) {
        // Movement Type column - extract text
        cellContent = cell.querySelector('.inline-flex')?.textContent?.trim() || text;
      }
      
      if (index === 4 && cell.querySelector('.flex.items-center')) {
        // From → To column
        const from = cell.querySelector('span:first-of-type')?.textContent?.trim() || '';
        const to = cell.querySelector('span:last-of-type')?.textContent?.trim() || '';
        cellContent = (from.charAt(0).toUpperCase() + from.slice(1)) + ' → ' + (to.charAt(0).toUpperCase() + to.slice(1));
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
            <td colspan="5" class="text-right" style="padding: 12px 8px;">TOTAL:</td>
            <td class="text-right" style="padding: 12px 8px;">${totalPackages.toLocaleString()} pkg / ${totalUnits.toLocaleString()} units</td>
            <td colspan="2" style="padding: 12px 8px;"></td>
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

// Expose to global scope
window.filterMovements = filterMovements;
window.applyFilters = applyFilters;
window.clearFilters = clearFilters;
window.openViewMovementModal = openViewMovementModal;
window.closeViewMovementModal = closeViewMovementModal;
window.printMovementDetails = printMovementDetails;
window.printFullReport = printFullReport;



let deleteSupplierId = null;

document.addEventListener('DOMContentLoaded', function() {});

// Filter Suppliers Function
function filterSuppliers() {
  const searchInput = document.getElementById('searchInput').value.toLowerCase();
  const deliveryStatusFilter = document.getElementById('deliveryStatusFilter').value;
  const table = document.getElementById('suppliersTableBody');
  const rows = table.getElementsByTagName('tr');
  
  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    // Account for checkbox column at index 0
    const supplierName = (row.cells[1]?.textContent || '').toLowerCase();
    const contactPerson = (row.cells[2]?.textContent || '').toLowerCase();
    const phone = (row.cells[3]?.textContent || '').toLowerCase();
    const email = (row.cells[4]?.textContent || '').toLowerCase();
    const deliveryStatus = row.getAttribute('data-delivery-status') || '';
    
    const matchesSearch = !searchInput || 
      supplierName.includes(searchInput) || 
      contactPerson.includes(searchInput) || 
      phone.includes(searchInput) || 
      email.includes(searchInput);
    
    const matchesDeliveryStatus = !deliveryStatusFilter || deliveryStatus === deliveryStatusFilter;
    
    if (matchesSearch && matchesDeliveryStatus) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  }
}

function setupFormHandlers() {}

// Modal Functions
function openAddSupplierModal() {
  document.getElementById('addSupplierModal').classList.remove('hidden');
  document.getElementById('addSupplierModal').classList.add('flex');
  document.getElementById('addSupplierForm').reset();
}

function closeAddSupplierModal() {
  document.getElementById('addSupplierModal').classList.add('hidden');
  document.getElementById('addSupplierModal').classList.remove('flex');
}

function openEditSupplierModal(supplier) {
  document.getElementById('editSupplierId').value = supplier.supplier_id;
  document.getElementById('editSupplierName').value = supplier.supplier_name;
  document.getElementById('editContactPerson').value = supplier.contact_person || '';
  document.getElementById('editPhone').value = supplier.phone || '';
  document.getElementById('editEmail').value = supplier.email || '';
  document.getElementById('editAddress').value = supplier.address || '';
  
  document.getElementById('editSupplierModal').classList.remove('hidden');
  document.getElementById('editSupplierModal').classList.add('flex');
}

function closeEditSupplierModal() {
  document.getElementById('editSupplierModal').classList.add('hidden');
  document.getElementById('editSupplierModal').classList.remove('flex');
}

function openDeleteSupplierModal(supplierId, supplierName) {
  deleteSupplierId = supplierId;
  document.getElementById('deleteSupplierName').textContent = supplierName;
  var hidden = document.getElementById('deleteSupplierIdHidden');
  if (hidden) hidden.value = supplierId;
  document.getElementById('deleteSupplierModal').classList.remove('hidden');
  document.getElementById('deleteSupplierModal').classList.add('flex');
}

function closeDeleteSupplierModal() {
  deleteSupplierId = null;
  document.getElementById('deleteSupplierModal').classList.add('hidden');
  document.getElementById('deleteSupplierModal').classList.remove('flex');
}

// Deletion handled by standard POST form

// View Supplier Details Modal
function openViewSupplierModal(supplier) {
  const modal = document.getElementById('viewSupplierModal');
  if (!modal) {
    console.error('View supplier modal not found');
    return;
  }
  
  // Populate modal with supplier data
  document.getElementById('viewSupplierId').textContent = '#' + (supplier.supplier_id || 'N/A');
  document.getElementById('viewSupplierName').textContent = supplier.supplier_name || 'N/A';
  document.getElementById('viewContactPerson').textContent = supplier.contact_person || 'N/A';
  document.getElementById('viewPhone').textContent = supplier.phone || 'N/A';
  document.getElementById('viewEmail').textContent = supplier.email || 'N/A';
  document.getElementById('viewAddress').textContent = supplier.address || 'N/A';
  
  // Reset delivery section states
  document.getElementById('deliveriesLoading').classList.remove('hidden');
  document.getElementById('deliveriesEmpty').classList.add('hidden');
  document.getElementById('deliveriesTableContainer').classList.add('hidden');
  document.getElementById('deliveriesTableBody').innerHTML = '';
  
  // Fetch deliveries for this supplier
  fetchSupplierDeliveries(supplier.supplier_id);
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.body.style.overflow = 'hidden';
}

// Fetch deliveries for a supplier
function fetchSupplierDeliveries(supplierId) {
  if (!supplierId) {
    document.getElementById('deliveriesLoading').classList.add('hidden');
    document.getElementById('deliveriesEmpty').classList.remove('hidden');
    return;
  }
  
  fetch(`../../Controllers/getControllers/getSupplierDeliveries.php?supplier_id=${supplierId}`)
    .then(response => response.json())
    .then(data => {
      document.getElementById('deliveriesLoading').classList.add('hidden');
      
      if (data.success && data.deliveries && data.deliveries.length > 0) {
        displayDeliveries(data.deliveries);
        document.getElementById('deliveriesTableContainer').classList.remove('hidden');
        document.getElementById('deliveriesEmpty').classList.add('hidden');
      } else {
        document.getElementById('deliveriesEmpty').classList.remove('hidden');
        document.getElementById('deliveriesTableContainer').classList.add('hidden');
      }
    })
    .catch(error => {
      console.error('Error fetching deliveries:', error);
      document.getElementById('deliveriesLoading').classList.add('hidden');
      document.getElementById('deliveriesEmpty').classList.remove('hidden');
      document.getElementById('deliveriesTableContainer').classList.add('hidden');
    });
}

// Display deliveries in table
function displayDeliveries(deliveries) {
  const tbody = document.getElementById('deliveriesTableBody');
  tbody.innerHTML = '';
  
  deliveries.forEach(delivery => {
    const row = document.createElement('tr');
    row.className = 'hover:bg-gray-50 transition-colors';
    
    // Format date
    const deliveryDate = delivery.delivery_date ? new Date(delivery.delivery_date) : null;
    const formattedDate = deliveryDate ? 
      deliveryDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
    const formattedTime = deliveryDate ? 
      deliveryDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '';
    
    // Product name
    const productName = escapeHtml(delivery.brand_name || 'N/A');
    const genericName = delivery.generic_name ? escapeHtml(delivery.generic_name) : '';
    const strength = delivery.strength ? escapeHtml(delivery.strength) : '';
    const referenceNo = escapeHtml(delivery.reference_no || 'N/A');
    const batchNo = escapeHtml(delivery.batch_no || 'N/A');
    const username = escapeHtml(delivery.username || 'N/A');
    const userInitial = username.charAt(0).toUpperCase();
    
    // Format currency
    const totalCost = parseFloat(delivery.total_cost || 0);
    const formattedCost = '₱' + totalCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    // Format quantities
    const quantityPackages = parseInt(delivery.quantity_packages || 0);
    const quantityUnits = parseInt(delivery.quantity_units || 0);
    
    row.innerHTML = `
      <td class="px-3 py-2 whitespace-nowrap">
        <div class="text-xs font-medium text-gray-900">${formattedDate}</div>
        <div class="text-xs text-gray-500">${formattedTime}</div>
      </td>
      <td class="px-3 py-2 whitespace-nowrap">
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
          ${referenceNo}
        </span>
      </td>
      <td class="px-3 py-2">
        <div class="text-xs font-semibold text-gray-900">${productName}</div>
        ${genericName ? `<div class="text-xs text-gray-500">${genericName}</div>` : ''}
        ${strength ? `<div class="text-xs text-gray-400">${strength}</div>` : ''}
      </td>
      <td class="px-3 py-2 whitespace-nowrap">
        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
          ${batchNo}
        </span>
      </td>
      <td class="px-3 py-2 whitespace-nowrap text-center">
        ${quantityPackages > 0 ? 
          `<span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
            ${quantityPackages.toLocaleString()}
          </span>` : 
          '<span class="text-xs text-gray-400">-</span>'
        }
      </td>
      <td class="px-3 py-2 whitespace-nowrap text-center">
        ${quantityUnits > 0 ? 
          `<span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold bg-green-50 text-green-700 border border-green-200">
            ${quantityUnits.toLocaleString()}
          </span>` : 
          '<span class="text-xs text-gray-400">-</span>'
        }
      </td>
      <td class="px-3 py-2 whitespace-nowrap text-right">
        <span class="text-xs font-semibold text-gray-900">${formattedCost}</span>
      </td>
      <td class="px-3 py-2 whitespace-nowrap">
        <div class="flex items-center">
          <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center mr-2 border border-green-200">
            <span class="text-xs font-semibold text-green-700">
              ${userInitial}
            </span>
          </div>
          <span class="text-xs text-gray-900">${username}</span>
        </div>
      </td>
    `;
    
    tbody.appendChild(row);
  });
}

function closeViewSupplierModal() {
  const modal = document.getElementById('viewSupplierModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
  }
}

// Print Supplier Details Function
function printSupplierDetails() {
  const supplierId = document.getElementById('viewSupplierId')?.textContent || 'N/A';
  const supplierName = document.getElementById('viewSupplierName')?.textContent || 'N/A';
  const contactPerson = document.getElementById('viewContactPerson')?.textContent || 'N/A';
  const phone = document.getElementById('viewPhone')?.textContent || 'N/A';
  const email = document.getElementById('viewEmail')?.textContent || 'N/A';
  const address = document.getElementById('viewAddress')?.textContent || 'N/A';
  
  // Get deliveries table HTML if available
  const deliveriesTableContainer = document.getElementById('deliveriesTableContainer');
  let deliveriesTableHtml = '';
  
  if (deliveriesTableContainer && !deliveriesTableContainer.classList.contains('hidden')) {
    const deliveriesTable = deliveriesTableContainer.querySelector('table');
    const deliveriesTbody = deliveriesTable?.querySelector('tbody');
    
    if (deliveriesTable && deliveriesTbody && deliveriesTbody.children.length > 0) {
      // Build print-friendly table
      let tableRows = '';
      Array.from(deliveriesTbody.children).forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 8) {
          const dateCell = cells[0];
          const dateText = dateCell.querySelector('.font-medium')?.textContent?.trim() || '';
          const timeText = dateCell.querySelector('.text-gray-500')?.textContent?.trim() || '';
          const dateTime = dateText + (timeText ? ' ' + timeText : '');
          
          const referenceNo = cells[1]?.textContent?.trim() || 'N/A';
          const productCell = cells[2];
          const productName = productCell.querySelector('.font-semibold')?.textContent?.trim() || '';
          const genericName = productCell.querySelectorAll('div')[1]?.textContent?.trim() || '';
          const product = productName + (genericName ? ' / ' + genericName : '');
          
          const batchNo = cells[3]?.textContent?.trim() || 'N/A';
          const packages = cells[4]?.textContent?.trim() || '-';
          const units = cells[5]?.textContent?.trim() || '-';
          const totalCost = cells[6]?.textContent?.trim() || '₱0.00';
          const receivedBy = cells[7]?.textContent?.trim() || 'N/A';
          
          tableRows += `
            <tr>
              <td>${escapeHtml(dateTime)}</td>
              <td>${escapeHtml(referenceNo)}</td>
              <td>${escapeHtml(product)}</td>
              <td>${escapeHtml(batchNo)}</td>
              <td class="text-center">${escapeHtml(packages)}</td>
              <td class="text-center">${escapeHtml(units)}</td>
              <td class="text-right">${escapeHtml(totalCost)}</td>
              <td>${escapeHtml(receivedBy)}</td>
            </tr>
          `;
        }
      });
      
      deliveriesTableHtml = `
        <div class="deliveries-section">
          <h3>Delivery History</h3>
          <table class="print-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Reference No</th>
                <th>Product</th>
                <th>Batch No</th>
                <th class="text-center">Packages</th>
                <th class="text-center">Units</th>
                <th class="text-right">Total Cost</th>
                <th>Received By</th>
              </tr>
            </thead>
            <tbody>
              ${tableRows}
            </tbody>
          </table>
        </div>
      `;
    }
  }
  
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('Please allow popups to print this document');
    return;
  }
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Supplier Details - ${escapeHtml(supplierName)}</title>
      <meta charset="UTF-8">
      <style>
        @media print {
          @page {
            margin: 1cm;
          }
        }
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }
        body {
          font-family: 'Segoe UI', Arial, sans-serif;
          padding: 20px;
          font-size: 11px;
          color: #000;
          line-height: 1.5;
        }
        .print-header {
          text-align: center;
          margin-bottom: 20px;
          padding-bottom: 15px;
          border-bottom: 3px solid #2563eb;
        }
        .print-header h1 {
          font-size: 24px;
          margin: 0 0 8px 0;
          color: #1e293b;
          font-weight: 700;
        }
        .print-header p {
          margin: 3px 0;
          color: #64748b;
          font-size: 11px;
        }
        .supplier-info {
          margin-bottom: 25px;
        }
        .supplier-info h3 {
          font-size: 14px;
          font-weight: 700;
          margin-bottom: 15px;
          padding-bottom: 8px;
          border-bottom: 2px solid #e2e8f0;
          color: #1e293b;
        }
        .info-grid {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 12px;
          margin-bottom: 15px;
        }
        .info-item {
          background: #f8fafc;
          padding: 10px;
          border: 1px solid #e2e8f0;
          border-radius: 4px;
        }
        .info-label {
          font-size: 9px;
          color: #64748b;
          text-transform: uppercase;
          letter-spacing: 0.5px;
          margin-bottom: 4px;
          font-weight: 600;
        }
        .info-value {
          font-size: 11px;
          color: #1e293b;
          font-weight: 500;
        }
        .deliveries-section {
          margin-top: 30px;
        }
        .deliveries-section h3 {
          font-size: 14px;
          font-weight: 700;
          margin-bottom: 15px;
          padding-bottom: 8px;
          border-bottom: 2px solid #e2e8f0;
          color: #1e293b;
        }
        .print-table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 15px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .print-table thead {
          background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
        }
        .print-table th {
          color: #fff;
          font-weight: 700;
          padding: 10px 8px;
          text-align: left;
          border: 1px solid #1e3a8a;
          font-size: 10px;
          text-transform: uppercase;
          letter-spacing: 0.3px;
        }
        .print-table th.text-center {
          text-align: center;
        }
        .print-table th.text-right {
          text-align: right;
        }
        .print-table td.text-center {
          text-align: center;
        }
        .print-table td.text-right {
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
        .text-center {
          text-align: center;
        }
        .text-right {
          text-align: right;
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
        }
      </style>
    </head>
    <body>
      <div class="print-header">
        <h1>📋 Supplier Details Report</h1>
      </div>
      
      <div class="supplier-info">
        <h3>Supplier Information</h3>
        <div class="info-grid">
          <div class="info-item">
            <div class="info-label">Supplier ID</div>
            <div class="info-value">${escapeHtml(supplierId)}</div>
          </div>
          <div class="info-item">
            <div class="info-label">Supplier Name</div>
            <div class="info-value">${escapeHtml(supplierName)}</div>
          </div>
          <div class="info-item">
            <div class="info-label">Contact Person</div>
            <div class="info-value">${escapeHtml(contactPerson)}</div>
          </div>
          <div class="info-item">
            <div class="info-label">Phone</div>
            <div class="info-value">${escapeHtml(phone)}</div>
          </div>
          <div class="info-item" style="grid-column: span 2;">
            <div class="info-label">Email</div>
            <div class="info-value">${escapeHtml(email)}</div>
          </div>
          <div class="info-item" style="grid-column: span 2;">
            <div class="info-label">Address</div>
            <div class="info-value">${escapeHtml(address)}</div>
          </div>
        </div>
      </div>
      
      ${deliveriesTableHtml || `
        <div class="deliveries-section">
          <h3>Delivery History</h3>
          <div style="text-align: center; padding: 30px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px;">
            <p style="color: #64748b; font-size: 11px; font-weight: 500;">No delivery records available for this supplier</p>
          </div>
        </div>
      `}
      
      <div class="print-footer">
        <p><strong>Generated by OCT Pharmacy</strong> | ${new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
      </div>
    </body>
    </html>
  `);
  
  printWindow.document.close();
  printWindow.focus();
  
  setTimeout(() => {
    printWindow.print();
    setTimeout(() => {
      if (!printWindow.closed) {
        printWindow.close();
      }
    }, 500);
  }, 250);
}

function escapeHtml(text) {
  if (!text) return '';
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Print functionality
function printFullReport() {
  // Get all visible rows (respecting filters)
  const tableBody = document.getElementById('suppliersTableBody');
  if (!tableBody) {
    alert('No suppliers table found.');
    return;
  }
  
  // Collect all visible rows (not hidden by filters)
  const allRows = tableBody.querySelectorAll('tr');
  const visibleRows = [];
  allRows.forEach(row => {
    // Only include rows that are visible (not hidden by display:none)
    if (row.style.display !== 'none' && row.offsetParent !== null) {
      visibleRows.push(row.cloneNode(true));
    }
  });
  
  if (visibleRows.length === 0) {
    alert('No suppliers to print. Please check your filters.');
    return;
  }
  
  // Calculate totals
  let totalSuppliers = 0;
  let totalDeliveries = 0;
  let suppliersWithDeliveries = 0;
  let suppliersWithoutDeliveries = 0;
  
  selectedRows.forEach(row => {
    totalSuppliers++;
    const deliveryCount = parseInt(row.getAttribute('data-delivery-count') || '0');
    totalDeliveries += deliveryCount;
    if (deliveryCount > 0) {
      suppliersWithDeliveries++;
    } else {
      suppliersWithoutDeliveries++;
    }
  });
  
  // Create print window
  const printWindow = window.open('', '_blank');
  
  // Get filter information
  const searchInput = document.getElementById('searchInput')?.value || '';
  const deliveryStatus = document.getElementById('deliveryStatusFilter')?.value || '';
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Suppliers Report</title>
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
          border-bottom: 3px solid #0284c7;
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
          border-left: 4px solid #0284c7;
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
          background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%);
        }
        .print-table th {
          color: #fff;
          font-weight: 700;
          padding: 12px 8px;
          text-align: left;
          border: 1px solid #0369a1;
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
        }
      </style>
    </head>
    <body>
      <div class="print-header">
        <h1>📋 Suppliers Report</h1>
        ${searchInput ? `<p><strong>Search:</strong> ${escapeHtml(searchInput)}</p>` : ''}
        ${deliveryStatus ? `<p><strong>Status:</strong> ${deliveryStatus === 'with_deliveries' ? 'With Deliveries' : 'Without Deliveries'}</p>` : ''}
      </div>
      
      <div class="print-summary-cards">
        <div class="summary-card">
          <div class="label">Total Suppliers</div>
          <div class="value">${visibleRows.length}</div>
        </div>
        <div class="summary-card">
          <div class="label">Total Deliveries</div>
          <div class="value">${totalDeliveries.toLocaleString()}</div>
        </div>
        <div class="summary-card">
          <div class="label">With Deliveries</div>
          <div class="value">${suppliersWithDeliveries}</div>
        </div>
        <div class="summary-card">
          <div class="label">Without Deliveries</div>
          <div class="value">${suppliersWithoutDeliveries}</div>
        </div>
      </div>
      
      <div class="print-info">
        ${searchInput ? `<p><strong>Search:</strong> ${escapeHtml(searchInput)}</p>` : ''}
        ${deliveryStatus ? `<p><strong>Status:</strong> ${deliveryStatus === 'with_deliveries' ? 'With Deliveries' : 'Without Deliveries'}</p>` : ''}
      </div>
      
      <table class="print-table">
        <thead>
          <tr>
            <th style="width: 30%;">Supplier Details</th>
            <th style="width: 20%;">Contact Person</th>
            <th style="width: 18%;">Phone</th>
            <th style="width: 22%;">Email</th>
            <th style="width: 10%;" class="text-center">Deliveries</th>
          </tr>
        </thead>
        <tbody>
  `);
  
  visibleRows.forEach(row => {
    // Clean up the row HTML for printing
    const cells = row.querySelectorAll('td');
    let rowHtml = '<tr>';
    
    // Only process the first 5 cells (excluding Actions column)
    const cellsToProcess = Array.from(cells).slice(0, 5);
    
    cellsToProcess.forEach((cell, index) => {
      const text = cell.textContent.trim();
      let className = '';
      
      // Determine alignment based on column (Deliveries is index 4 after removing checkbox)
      if (index === 4) { // Deliveries column
        className = 'text-center';
      }
      
      // Clean up cell content
      let cellContent = text.replace(/\s+/g, ' ');
      
      // Special handling for supplier details column (index 0)
      if (index === 0 && cell.querySelector('.font-semibold')) {
        const supplierName = cell.querySelector('.font-semibold')?.textContent?.trim() || '';
        const supplierId = cell.querySelector('.text-gray-500')?.textContent?.trim() || '';
        cellContent = supplierName + (supplierId ? ' (' + supplierId + ')' : '');
      }
      
      // Special handling for contact info column (index 1) - remove "Contact Person" label
      if (index === 1) {
        const contactText = cell.querySelector('.text-gray-900')?.textContent?.trim() || '';
        cellContent = contactText;
      }
      
      // Special handling for phone column (index 2) - remove "Phone" label
      if (index === 2) {
        const phoneText = cell.querySelector('.text-gray-900')?.textContent?.trim() || '';
        cellContent = phoneText;
      }
      
      // Special handling for email column (index 3) - remove "Email" label
      if (index === 3) {
        const emailText = cell.querySelector('.text-gray-900')?.textContent?.trim() || '';
        cellContent = emailText;
      }
      
      rowHtml += `<td class="${className}" style="padding: 10px 8px;">${escapeHtml(cellContent)}</td>`;
    });
    
    rowHtml += '</tr>';
    printWindow.document.write(rowHtml);
  });
  
  printWindow.document.write(`
        </tbody>
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

// Expose functions globally
window.openViewSupplierModal = openViewSupplierModal;
window.closeViewSupplierModal = closeViewSupplierModal;
window.printSupplierDetails = printSupplierDetails;
window.filterSuppliers = filterSuppliers;
window.printFullReport = printFullReport;


// Close modal on outside click
document.addEventListener('click', function(e) {
  const modal = document.getElementById('viewSupplierModal');
  if (e.target === modal) {
    closeViewSupplierModal();
  }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('viewSupplierModal');
    if (modal && !modal.classList.contains('hidden')) {
      closeViewSupplierModal();
    }
  }
});



let deleteBatchId = null;

// No AJAX interception; forms submit natively
document.addEventListener('DOMContentLoaded', function() {});

// Filter Batches Function
function filterBatches() {
  const searchInput = document.getElementById('searchInput').value.toLowerCase();
  const table = document.getElementById('batchesTableBody');
  const rows = table.getElementsByTagName('tr');
  
  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    const productName = row.cells[1]?.textContent.toLowerCase() || '';
    const batchNo = row.cells[2]?.textContent.toLowerCase() || '';
    
    if (productName.includes(searchInput) || batchNo.includes(searchInput)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  }
}

// Expose to global for inline handlers
window.filterBatches = filterBatches;

function setupFormHandlers() {}

// Modal Functions
function openAddBatchModal() {
  document.getElementById('addBatchModal').classList.remove('hidden');
  document.getElementById('addBatchModal').classList.add('flex');
  document.getElementById('addBatchForm').reset();
}

window.openAddBatchModal = openAddBatchModal;

function closeAddBatchModal() {
  document.getElementById('addBatchModal').classList.add('hidden');
  document.getElementById('addBatchModal').classList.remove('flex');
}

window.closeAddBatchModal = closeAddBatchModal;

function openEditBatchModal(batch) {
  document.getElementById('editBatchId').value = batch.batch_id;
  document.getElementById('editProduct').value = batch.product_id;
  document.getElementById('editBatchNo').value = batch.batch_no;
  document.getElementById('editManufacturingDate').value = batch.manufacturing_date;
  document.getElementById('editExpiryDate').value = batch.expiry_date;
  if (document.getElementById('editReceivedDate')) document.getElementById('editReceivedDate').value = batch.received_date || '';
  if (document.getElementById('editQuantityPackages')) document.getElementById('editQuantityPackages').value = batch.quantity_packages || 0;
  if (document.getElementById('editQuantityUnits')) document.getElementById('editQuantityUnits').value = batch.quantity_units || 0;
  
  document.getElementById('editBatchModal').classList.remove('hidden');
  document.getElementById('editBatchModal').classList.add('flex');
}

window.openEditBatchModal = openEditBatchModal;

function closeEditBatchModal() {
  document.getElementById('editBatchModal').classList.add('hidden');
  document.getElementById('editBatchModal').classList.remove('flex');
}

window.closeEditBatchModal = closeEditBatchModal;

function openDeleteBatchModal(batchId, batchNo) {
  deleteBatchId = batchId;
  document.getElementById('deleteBatchName').textContent = batchNo;
  var hidden = document.getElementById('deleteBatchIdHidden');
  if (hidden) hidden.value = batchId;
  document.getElementById('deleteBatchModal').classList.remove('hidden');
  document.getElementById('deleteBatchModal').classList.add('flex');
}

window.openDeleteBatchModal = openDeleteBatchModal;

function closeDeleteBatchModal() {
  deleteBatchId = null;
  document.getElementById('deleteBatchModal').classList.add('hidden');
  document.getElementById('deleteBatchModal').classList.remove('flex');
}

window.closeDeleteBatchModal = closeDeleteBatchModal;

// Deletion handled by standard POST form

// View Batch Details Modal
function openViewBatchModal(batch) {
  const modal = document.getElementById('viewBatchModal');
  if (!modal) {
    console.error('View batch modal not found');
    return;
  }
  
  // Format dates
  function formatDate(dateStr) {
    if (!dateStr || dateStr === '0000-00-00' || dateStr === '0000-00-00 00:00:00') return 'N/A';
    try {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    } catch (e) {
      return dateStr;
    }
  }
  
  function formatDateTime(dateStr) {
    if (!dateStr || dateStr === '0000-00-00 00:00:00') return 'N/A';
    try {
      const date = new Date(dateStr);
      return date.toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return dateStr;
    }
  }
  
  // Check expiry status
  const expiryDate = batch.expiry_date ? new Date(batch.expiry_date) : null;
  const now = new Date();
  const threeMonths = new Date(now.getTime() + (90 * 24 * 60 * 60 * 1000));
  let expiryHtml = 'N/A';
  
  if (expiryDate) {
    const expiryStr = formatDate(batch.expiry_date);
    if (expiryDate < now) {
      expiryHtml = `<span style="color: #dc2626; font-weight: bold;">${expiryStr} (EXPIRED)</span>`;
    } else if (expiryDate <= threeMonths) {
      expiryHtml = `<span style="color: #d97706; font-weight: bold;">${expiryStr} (Expiring Soon)</span>`;
    } else {
      expiryHtml = expiryStr;
    }
  }
  
  // Calculate total units
  const qtyPackages = parseInt(batch.quantity_packages || 0);
  const qtyUnits = parseInt(batch.quantity_units || 0);
  const totalUnits = qtyPackages * qtyUnits;
  
  // Populate modal with batch data
  document.getElementById('viewBatchId').textContent = '#' + (batch.batch_id || 'N/A');
  document.getElementById('viewProduct').textContent = batch.brand_name || 'N/A';
  document.getElementById('viewBatchNo').textContent = batch.batch_no || 'N/A';
  document.getElementById('viewManufacturingDate').textContent = formatDate(batch.manufacturing_date);
  document.getElementById('viewExpiryDate').innerHTML = expiryHtml;
  document.getElementById('viewReceivedDate').textContent = formatDate(batch.received_date);
  document.getElementById('viewQuantityPackages').textContent = number_format(qtyPackages);
  document.getElementById('viewQuantityUnits').textContent = number_format(qtyUnits);
  document.getElementById('viewTotalUnits').textContent = number_format(totalUnits);
  document.getElementById('viewCreatedAt').textContent = formatDateTime(batch.created_at);
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeViewBatchModal() {
  const modal = document.getElementById('viewBatchModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

// Print Batch Details Function
function printBatchDetails() {
  const element = document.getElementById('printableBatchContent');
  if (!element) {
    alert('No content to print');
    return;
  }
  
  const batchId = document.getElementById('viewBatchId')?.textContent || 'N/A';
  const batchNo = document.getElementById('viewBatchNo')?.textContent || 'N/A';
  
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('Please allow popups to print this document');
    return;
  }
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Batch Details - ${escapeHtml(batchNo)}</title>
      <meta charset="UTF-8">
      <style>
        @media print {
          @page {
            margin: 1cm;
          }
        }
        body {
          font-family: Arial, sans-serif;
          padding: 20px;
          font-size: 12px;
          color: #000;
        }
        h2 {
          font-size: 18px;
          margin-bottom: 10px;
          color: #000;
        }
        .header-info {
          margin-bottom: 15px;
          padding-bottom: 10px;
          border-bottom: 2px solid #ddd;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 10px;
        }
        td {
          border: 1px solid #ddd;
          padding: 10px;
          text-align: left;
        }
        td:first-child {
          background-color: #f3f4f6;
          font-weight: bold;
          width: 30%;
        }
        tr:nth-child(even) {
          background-color: #f9fafb;
        }
      </style>
    </head>
    <body>
      <div class="header-info">
        <h2>Batch Details</h2>
        <p>Batch ID: ${escapeHtml(batchId)} | Batch No: ${escapeHtml(batchNo)}</p>
        <p style="font-size: 10px; color: #666;">Generated: ${new Date().toLocaleString()}</p>
      </div>
      ${element.outerHTML}
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

function number_format(num) {
  return new Intl.NumberFormat('en-US').format(num);
}

// Expose functions globally
window.openViewBatchModal = openViewBatchModal;
window.closeViewBatchModal = closeViewBatchModal;
window.printBatchDetails = printBatchDetails;

// Close modal on outside click
document.addEventListener('click', function(e) {
  const modal = document.getElementById('viewBatchModal');
  if (e.target === modal) {
    closeViewBatchModal();
  }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('viewBatchModal');
    if (modal && !modal.classList.contains('hidden')) {
      closeViewBatchModal();
    }
  }
});


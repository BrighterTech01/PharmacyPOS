// Pharmacy Inventory Modal Functions
function openAddPharmacyInventoryModal() {
  const modal = document.getElementById('addPharmacyInventoryModal');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const form = document.getElementById('addPharmacyInventoryForm');
    if (form) form.reset();
  }
}

function closeAddPharmacyInventoryModal() {
  const modal = document.getElementById('addPharmacyInventoryModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function filterPharmacyInventory() {
  const searchInput = document.getElementById('searchInput').value.toLowerCase();
  const productNameFilter = document.getElementById('productNameFilter')?.value || '';
  const categoryFilter = document.getElementById('categoryFilter')?.value || '';
  const stockStatusFilter = document.getElementById('stockStatusFilter')?.value || '';
  const table = document.getElementById('pharmacyInventoryTableBody');
  const rows = table.getElementsByTagName('tr');
  
  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    const productName = (row.getAttribute('data-product') || '').toLowerCase();
    const batchNo = (row.getAttribute('data-batch') || '').toLowerCase();
    const category = (row.getAttribute('data-category') || '').toLowerCase();
    const stockStatus = row.getAttribute('data-stock-status') || '';
    
    let showRow = true;
    
    // Search filter
    if (searchInput && !productName.includes(searchInput) && !batchNo.includes(searchInput)) {
      showRow = false;
    }
    
    // Product name filter
    if (productNameFilter && showRow) {
      if (productName !== productNameFilter.toLowerCase()) {
        showRow = false;
      }
    }
    
    // Category filter
    if (categoryFilter && showRow) {
      if (category !== categoryFilter.toLowerCase()) {
        showRow = false;
      }
    }
    
    // Stock status filter
    if (stockStatusFilter && showRow) {
      if (stockStatus !== stockStatusFilter) {
        showRow = false;
      }
    }
    
    row.style.display = showRow ? '' : 'none';
  }
  
  // Check auto-scroll after filtering
  setTimeout(checkPharmacyAutoScroll, 100);
}

function clearPharmacyFilters() {
  document.getElementById('searchInput').value = '';
  const productNameFilter = document.getElementById('productNameFilter');
  const categoryFilter = document.getElementById('categoryFilter');
  const stockStatusFilter = document.getElementById('stockStatusFilter');
  if (productNameFilter) productNameFilter.value = '';
  if (categoryFilter) categoryFilter.value = '';
  if (stockStatusFilter) stockStatusFilter.value = '';
  filterPharmacyInventory();
}

// View Pharmacy Inventory Details Modal
function openViewPharmacyInventoryModal(item) {
  const modal = document.getElementById('viewPharmacyInventoryModal');
  if (!modal) {
    console.error('View pharmacy inventory modal not found');
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
  const expiryDate = item.expiry_date ? new Date(item.expiry_date) : null;
  const now = new Date();
  let expiryHtml = 'N/A';
  
  if (expiryDate && item.expiry_date !== '0000-00-00') {
    const expiryStr = formatDate(item.expiry_date);
    if (expiryDate < now) {
      expiryHtml = `<span style="color: #dc2626; font-weight: bold;">${expiryStr} (EXPIRED)</span>`;
    } else {
      expiryHtml = expiryStr;
    }
  }
  
  // Populate modal with inventory data
  document.getElementById('viewPharmacyInventoryId').textContent = '#' + (item.pharmacy_id || 'N/A');
  document.getElementById('viewProduct').textContent = item.brand_name || 'N/A';
  document.getElementById('viewGenericName').textContent = item.generic_name || 'N/A';
  document.getElementById('viewStrength').textContent = item.strength || 'N/A';
  document.getElementById('viewBatchNo').textContent = item.batch_no || 'N/A';
  document.getElementById('viewExpiryDate').innerHTML = expiryHtml;
  document.getElementById('viewUnitsIn').textContent = number_format(parseInt(item.quantity_units_in || 0));
  document.getElementById('viewUnitsOut').textContent = number_format(parseInt(item.quantity_units_out || 0));
  document.getElementById('viewUnitsBalance').textContent = number_format(parseInt(item.quantity_units_balance || 0));
  document.getElementById('viewLastUpdated').textContent = formatDateTime(item.updated_at);
  
  // Stop any running auto-scroll
  if (movementLogsAutoScrollInterval) {
    clearInterval(movementLogsAutoScrollInterval);
    movementLogsAutoScrollInterval = null;
    movementLogsIsScrolling = false;
  }
  
  // Clear debounce timer if any
  if (movementLogsFilterDebounceTimer) {
    clearTimeout(movementLogsFilterDebounceTimer);
    movementLogsFilterDebounceTimer = null;
  }
  
  // Reset filters when opening modal
  clearMovementLogFilters();
  
  // Show loading state for movement logs
  document.getElementById('movementLogsLoading').classList.remove('hidden');
  document.getElementById('movementLogsContent').classList.add('hidden');
  document.getElementById('movementLogsEmpty').classList.add('hidden');
  document.getElementById('movementLogsAutoScrollControls').classList.add('hidden');
  
  // Fetch and display movement logs
  // Use delivery_id if available, otherwise fallback to batch_id for backward compatibility
  const deliveryId = item.delivery_id || item.batch_id;
  if (deliveryId) {
    fetchBatchMovementLogs(deliveryId);
  } else {
    document.getElementById('movementLogsLoading').classList.add('hidden');
    document.getElementById('movementLogsEmpty').classList.remove('hidden');
  }
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeViewPharmacyInventoryModal() {
  const modal = document.getElementById('viewPharmacyInventoryModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    
    // Stop auto-scroll when closing modal
    if (movementLogsAutoScrollInterval) {
      clearInterval(movementLogsAutoScrollInterval);
      movementLogsAutoScrollInterval = null;
      movementLogsIsScrolling = false;
    }
  }
}

// Print Pharmacy Inventory Details Function
function printPharmacyInventoryDetails() {
  // Extract all data from modal
  const inventoryId = document.getElementById('viewPharmacyInventoryId')?.textContent?.trim() || 'N/A';
  const product = document.getElementById('viewProduct')?.textContent?.trim() || 'N/A';
  const genericName = document.getElementById('viewGenericName')?.textContent?.trim() || 'N/A';
  const strength = document.getElementById('viewStrength')?.textContent?.trim() || 'N/A';
  const batchNo = document.getElementById('viewBatchNo')?.textContent?.trim() || 'N/A';
  const expiryDate = document.getElementById('viewExpiryDate')?.textContent?.trim() || 'N/A';
  const unitsIn = document.getElementById('viewUnitsIn')?.textContent?.trim() || '0';
  const unitsOut = document.getElementById('viewUnitsOut')?.textContent?.trim() || '0';
  const unitsBalance = document.getElementById('viewUnitsBalance')?.textContent?.trim() || '0';
  const lastUpdated = document.getElementById('viewLastUpdated')?.textContent?.trim() || 'N/A';
  
  // Extract movement log table data
  const movementLogsTable = document.getElementById('movementLogsTableBody');
  let movementLogsHTML = '';
  
  if (movementLogsTable && movementLogsTable.querySelectorAll('tr').length > 0) {
    const rows = movementLogsTable.querySelectorAll('tr');
    rows.forEach(row => {
      const cells = row.querySelectorAll('td');
      if (cells.length >= 7) {
        const dateTime = cells[0]?.textContent?.trim() || 'N/A';
        const type = cells[1]?.textContent?.trim() || 'N/A';
        const fromTo = cells[2]?.textContent?.trim() || 'N/A';
        const packages = cells[3]?.textContent?.trim() || '0';
        const units = cells[4]?.textContent?.trim() || '0';
        const reference = cells[5]?.textContent?.trim() || 'N/A';
        const user = cells[6]?.textContent?.trim() || 'N/A';
        
        movementLogsHTML += `
          <tr>
            <td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(dateTime)}</td>
            <td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(type)}</td>
            <td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(fromTo)}</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${escapeHtml(packages)}</td>
            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${escapeHtml(units)}</td>
            <td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(reference)}</td>
            <td style="padding: 8px; border: 1px solid #ddd;">${escapeHtml(user)}</td>
          </tr>
        `;
      }
    });
  } else {
    movementLogsHTML = `
      <tr>
        <td colspan="7" style="padding: 20px; text-align: center; border: 1px solid #ddd; color: #666;">
          No movement logs available
        </td>
      </tr>
    `;
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
      <title>Pharmacy Inventory - ${escapeHtml(product)}</title>
      <meta charset="UTF-8">
      <style>
        @media print {
          @page {
            margin: 1cm;
            size: A4;
          }
        }
        * {
          box-sizing: border-box;
        }
        body {
          font-family: Arial, sans-serif;
          padding: 20px;
          font-size: 11px;
          color: #000;
          line-height: 1.4;
        }
        .header-info {
          margin-bottom: 20px;
          padding-bottom: 15px;
          border-bottom: 2px solid #333;
        }
        h1 {
          font-size: 20px;
          margin: 0 0 5px 0;
          color: #000;
          font-weight: bold;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 15px;
          font-size: 10px;
        }
        th {
          background-color: #374151;
          color: #fff;
          padding: 10px 8px;
          text-align: left;
          font-weight: bold;
          border: 1px solid #1f2937;
          font-size: 10px;
          text-transform: uppercase;
        }
        th.text-right {
          text-align: right;
        }
        td {
          border: 1px solid #ddd;
          padding: 8px;
          text-align: left;
        }
        td.text-right {
          text-align: right;
        }
        tr:nth-child(even) {
          background-color: #f9fafb;
        }
      </style>
    </head>
    <body>
      <div class="header-info">
        <h1>${escapeHtml(product)}</h1>
        <p style="font-size: 9px; color: #666; margin-top: 5px;">Generated: ${new Date().toLocaleString()}</p>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>Date/Time</th>
            <th>Movement Type</th>
            <th>From → To</th>
            <th class="text-right">Packages</th>
            <th class="text-right">Units</th>
            <th>Reference</th>
            <th>User</th>
          </tr>
        </thead>
        <tbody>
          ${movementLogsHTML}
        </tbody>
      </table>
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

// Store original logs data for filtering
let originalMovementLogs = [];

// Auto-scroll variables for movement logs
let movementLogsAutoScrollInterval = null;
let movementLogsIsScrolling = false;
const MOVEMENT_LOGS_SCROLL_SPEED = 50; // pixels per scroll
const MOVEMENT_LOGS_ROW_LIMIT = 10; // Show auto-scroll when 10+ rows

// Debounce timer for filter search
let movementLogsFilterDebounceTimer = null;

// Fetch batch movement logs
function fetchBatchMovementLogs(batchId) {
  const loadingEl = document.getElementById('movementLogsLoading');
  const contentEl = document.getElementById('movementLogsContent');
  const emptyEl = document.getElementById('movementLogsEmpty');
  
  if (loadingEl) loadingEl.classList.remove('hidden');
  if (contentEl) contentEl.classList.add('hidden');
  if (emptyEl) emptyEl.classList.add('hidden');
  
  fetch(`../../Controllers/getControllers/getBatchMovementLogs.php?delivery_id=${batchId}`)
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      if (loadingEl) loadingEl.classList.add('hidden');
      
      if (data.success && data.logs && data.logs.length > 0) {
        // Store original logs
        originalMovementLogs = data.logs;
        // Display with current filters (will apply any active filters)
        filterMovementLogs();
      } else {
        originalMovementLogs = [];
        if (emptyEl) emptyEl.classList.remove('hidden');
        if (contentEl) contentEl.classList.add('hidden');
        // Hide auto-scroll controls when no data
        const controlsEl = document.getElementById('movementLogsAutoScrollControls');
        if (controlsEl) controlsEl.classList.add('hidden');
      }
    })
    .catch(error => {
      console.error('Error fetching movement logs:', error);
      if (loadingEl) loadingEl.classList.add('hidden');
      if (emptyEl) emptyEl.classList.remove('hidden');
      if (contentEl) contentEl.classList.add('hidden');
      originalMovementLogs = [];
      
      // Hide auto-scroll controls on error
      const controlsEl = document.getElementById('movementLogsAutoScrollControls');
      if (controlsEl) controlsEl.classList.add('hidden');
    });
}

// Display movement logs in table
function displayMovementLogs(logs) {
  const tbody = document.getElementById('movementLogsTableBody');
  const contentEl = document.getElementById('movementLogsContent');
  const emptyEl = document.getElementById('movementLogsEmpty');
  const controlsEl = document.getElementById('movementLogsAutoScrollControls');
  
  if (!tbody) return;
  
  tbody.innerHTML = '';
  
  if (!logs || logs.length === 0) {
    if (contentEl) contentEl.classList.add('hidden');
    if (emptyEl) emptyEl.classList.remove('hidden');
    if (controlsEl) controlsEl.classList.add('hidden');
    // Stop auto-scroll if running
    if (movementLogsAutoScrollInterval) {
      clearInterval(movementLogsAutoScrollInterval);
      movementLogsAutoScrollInterval = null;
      movementLogsIsScrolling = false;
    }
    return;
  }
  
  if (contentEl) contentEl.classList.remove('hidden');
  if (emptyEl) emptyEl.classList.add('hidden');
  
  // Show/hide auto-scroll controls based on row count
  if (logs.length >= MOVEMENT_LOGS_ROW_LIMIT) {
    if (controlsEl) controlsEl.classList.remove('hidden');
    updateMovementLogsVisibleRowsCount();
  } else {
    if (controlsEl) controlsEl.classList.add('hidden');
    // Stop auto-scroll if running
    if (movementLogsAutoScrollInterval) {
      clearInterval(movementLogsAutoScrollInterval);
      movementLogsAutoScrollInterval = null;
      movementLogsIsScrolling = false;
      updateMovementLogsToggleButton();
    }
  }
  
  // Movement type colors and labels
  const movementTypes = {
    'delivery': { label: 'Delivery', color: 'emerald', bg: 'bg-emerald-100', text: 'text-emerald-800' },
    'transfer': { label: 'Transfer', color: 'blue', bg: 'bg-blue-100', text: 'text-blue-800' },
    'sale': { label: 'Sale', color: 'purple', bg: 'bg-purple-100', text: 'text-purple-800' },
    'adjustment': { label: 'Adjustment', color: 'orange', bg: 'bg-orange-100', text: 'text-orange-800' }
  };
  
  logs.forEach(log => {
    const row = document.createElement('tr');
    row.className = 'hover:bg-gray-50 movement-log-row';
    row.setAttribute('data-date', log.movement_date || '');
    row.setAttribute('data-type', log.movement_type || '');
    row.setAttribute('data-user', (log.username || '').toLowerCase());
    row.setAttribute('data-reference', `${(log.reference_type || '')} ${(log.reference_id || '')}`.toLowerCase());
    row.setAttribute('data-from-to', `${(log.from_inventory || '')} ${(log.to_inventory || '')}`.toLowerCase());
    
    const typeInfo = movementTypes[log.movement_type] || { 
      label: log.movement_type ? log.movement_type.charAt(0).toUpperCase() + log.movement_type.slice(1) : 'N/A',
      bg: 'bg-gray-100',
      text: 'text-gray-800'
    };
    
    // Format date/time
    let dateTimeHtml = 'N/A';
    if (log.movement_date) {
      try {
        const date = new Date(log.movement_date);
        const dateStr = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        const timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        dateTimeHtml = `<div class="text-sm text-gray-900">${dateStr}</div><div class="text-xs text-gray-500">${timeStr}</div>`;
      } catch (e) {
        dateTimeHtml = log.movement_date;
      }
    }
    
    // Format From → To
    const fromLocation = log.from_inventory ? log.from_inventory.charAt(0).toUpperCase() + log.from_inventory.slice(1) : 'N/A';
    const toLocation = log.to_inventory ? log.to_inventory.charAt(0).toUpperCase() + log.to_inventory.slice(1) : 'N/A';
    const fromTo = `${fromLocation} → ${toLocation}`;
    
    // Reference
    let reference = 'N/A';
    if (log.reference_type && log.reference_id) {
      reference = `${log.reference_type.charAt(0).toUpperCase() + log.reference_type.slice(1)} #${log.reference_id}`;
    }
    
    row.innerHTML = `
      <td class="px-4 py-3 whitespace-nowrap">${dateTimeHtml}</td>
      <td class="px-4 py-3 whitespace-nowrap">
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold ${typeInfo.bg} ${typeInfo.text}">
          ${typeInfo.label}
        </span>
      </td>
      <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">${fromTo}</td>
      <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-gray-900">${number_format(parseInt(log.quantity_packages || 0))}</td>
      <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-gray-900">${number_format(parseInt(log.quantity_units || 0))} ${log.unit_name || ''}</td>
      <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${reference}</td>
      <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">${log.username || 'N/A'}</td>
    `;
    
    tbody.appendChild(row);
  });
  
  // Update visible rows count
  updateMovementLogsVisibleRowsCount();
  
  // Add scroll event listener to update visible rows count on manual scroll
  const wrapper = document.getElementById('movementLogsTableWrapper');
  if (wrapper) {
    wrapper.addEventListener('scroll', updateMovementLogsVisibleRowsCount);
  }
}

// Filter movement logs with debouncing support
function filterMovementLogs(useDebounce = false) {
  // Safety check - ensure originalMovementLogs is initialized
  if (typeof originalMovementLogs === 'undefined') {
    originalMovementLogs = [];
  }
  
  // Clear existing debounce timer if any
  if (movementLogsFilterDebounceTimer) {
    clearTimeout(movementLogsFilterDebounceTimer);
    movementLogsFilterDebounceTimer = null;
  }
  
  // Apply debounce for search inputs (300ms delay)
  if (useDebounce) {
    movementLogsFilterDebounceTimer = setTimeout(() => {
      filterMovementLogs(false);
    }, 300);
    return;
  }
  
  // Return early if no logs loaded
  if (!originalMovementLogs || originalMovementLogs.length === 0) {
    // Hide content and show empty state if no original logs
    const content = document.getElementById('movementLogsContent');
    const empty = document.getElementById('movementLogsEmpty');
    if (content) content.classList.add('hidden');
    if (empty) empty.classList.remove('hidden');
    return;
  }
  
  // Get filter values
  const startDateInput = document.getElementById('movementLogStartDate');
  const endDateInput = document.getElementById('movementLogEndDate');
  const typeInput = document.getElementById('movementLogType');
  const userInput = document.getElementById('movementLogUser');
  const searchInput = document.getElementById('movementLogSearch');
  
  const startDate = startDateInput?.value || '';
  const endDate = endDateInput?.value || '';
  const movementType = typeInput?.value || '';
  const userFilter = (userInput?.value || '').toLowerCase().trim();
  const searchFilter = (searchInput?.value || '').toLowerCase().trim();
  
  // Filter the logs
  const filteredLogs = originalMovementLogs.filter(log => {
    // Date range filter
    if (startDate || endDate) {
      if (!log.movement_date || log.movement_date === '0000-00-00 00:00:00') {
        return false;
      }
      
      try {
        const logDate = new Date(log.movement_date);
        if (isNaN(logDate.getTime())) return false;
        
        const logDateStr = logDate.toISOString().split('T')[0];
        
        if (startDate && logDateStr < startDate) {
          return false;
        }
        if (endDate && logDateStr > endDate) {
          return false;
        }
      } catch (e) {
        console.error('Error parsing date:', log.movement_date, e);
        return false;
      }
    }
    
    // Movement type filter
    if (movementType && log.movement_type !== movementType) {
      return false;
    }
    
    // User filter (case-insensitive partial match)
    if (userFilter) {
      const username = (log.username || '').toLowerCase().trim();
      if (!username.includes(userFilter)) {
        return false;
      }
    }
    
    // Search filter (searches in reference, from-to, user, movement type)
    if (searchFilter) {
      const reference = `${(log.reference_type || '')} ${(log.reference_id || '')}`.toLowerCase();
      const fromTo = `${(log.from_inventory || '')} ${(log.to_inventory || '')}`.toLowerCase();
      const user = (log.username || '').toLowerCase();
      const type = (log.movement_type || '').toLowerCase();
      
      if (!reference.includes(searchFilter) && 
          !fromTo.includes(searchFilter) && 
          !user.includes(searchFilter) &&
          !type.includes(searchFilter)) {
        return false;
      }
    }
    
    return true;
  });
  
  // Display filtered logs
  displayMovementLogs(filteredLogs);
  
  // Reset scroll position when filtering
  resetMovementLogsScroll();
}

// Clear movement log filters
function clearMovementLogFilters() {
  const startDateInput = document.getElementById('movementLogStartDate');
  const endDateInput = document.getElementById('movementLogEndDate');
  const typeInput = document.getElementById('movementLogType');
  const userInput = document.getElementById('movementLogUser');
  const searchInput = document.getElementById('movementLogSearch');
  
  if (startDateInput) startDateInput.value = '';
  if (endDateInput) endDateInput.value = '';
  if (typeInput) typeInput.value = '';
  if (userInput) userInput.value = '';
  if (searchInput) searchInput.value = '';
  
  // Only filter if we have logs loaded
  if (originalMovementLogs.length > 0) {
    filterMovementLogs();
  }
}

// Auto-scroll functions for movement logs
function toggleMovementLogsAutoScroll() {
  const wrapper = document.getElementById('movementLogsTableWrapper');
  if (!wrapper) return;
  
  if (movementLogsIsScrolling) {
    // Stop scrolling
    clearInterval(movementLogsAutoScrollInterval);
    movementLogsAutoScrollInterval = null;
    movementLogsIsScrolling = false;
  } else {
    // Start scrolling
    movementLogsAutoScrollInterval = setInterval(() => {
      if (wrapper.scrollTop + wrapper.clientHeight >= wrapper.scrollHeight - 5) {
        // Reached bottom, scroll back to top
        wrapper.scrollTop = 0;
      } else {
        wrapper.scrollTop += MOVEMENT_LOGS_SCROLL_SPEED;
      }
      updateMovementLogsVisibleRowsCount();
    }, 100); // Update every 100ms
    movementLogsIsScrolling = true;
  }
  
  updateMovementLogsToggleButton();
}

function resetMovementLogsScroll() {
  const wrapper = document.getElementById('movementLogsTableWrapper');
  if (wrapper) {
    wrapper.scrollTop = 0;
    updateMovementLogsVisibleRowsCount();
  }
  
  // Stop auto-scroll if running
  if (movementLogsAutoScrollInterval) {
    clearInterval(movementLogsAutoScrollInterval);
    movementLogsAutoScrollInterval = null;
    movementLogsIsScrolling = false;
    updateMovementLogsToggleButton();
  }
}

function updateMovementLogsToggleButton() {
  const button = document.getElementById('movementLogsToggleScroll');
  if (!button) return;
  
  if (movementLogsIsScrolling) {
    button.innerHTML = `
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      Stop Auto Scroll
    `;
    button.classList.remove('bg-purple-600', 'hover:bg-purple-700');
    button.classList.add('bg-red-600', 'hover:bg-red-700');
  } else {
    button.innerHTML = `
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      Start Auto Scroll
    `;
    button.classList.remove('bg-red-600', 'hover:bg-red-700');
    button.classList.add('bg-purple-600', 'hover:bg-purple-700');
  }
}

function updateMovementLogsVisibleRowsCount() {
  const wrapper = document.getElementById('movementLogsTableWrapper');
  const countSpan = document.getElementById('movementLogsVisibleCount');
  if (!wrapper || !countSpan) return;
  
  const tbody = document.getElementById('movementLogsTableBody');
  const rows = tbody ? tbody.querySelectorAll('tr') : [];
  
  if (rows.length === 0) return;
  
  // Calculate visible rows based on scroll position
  const rowHeight = rows[0] ? rows[0].offsetHeight : 50;
  const visibleStart = Math.floor(wrapper.scrollTop / rowHeight);
  const visibleEnd = Math.min(visibleStart + Math.ceil(wrapper.clientHeight / rowHeight), rows.length);
  const visibleCount = Math.max(1, visibleEnd - visibleStart);
  
  countSpan.textContent = `Showing ${visibleCount} of ${rows.length} rows`;
}

// Expose functions to global scope
window.openAddPharmacyInventoryModal = openAddPharmacyInventoryModal;
window.closeAddPharmacyInventoryModal = closeAddPharmacyInventoryModal;
window.openViewPharmacyInventoryModal = openViewPharmacyInventoryModal;
window.closeViewPharmacyInventoryModal = closeViewPharmacyInventoryModal;
window.printPharmacyInventoryDetails = printPharmacyInventoryDetails;
// Expose all functions to global scope
window.filterPharmacyInventory = filterPharmacyInventory;
window.filterMovementLogs = filterMovementLogs;
window.clearMovementLogFilters = clearMovementLogFilters;
window.toggleMovementLogsAutoScroll = toggleMovementLogsAutoScroll;
window.resetMovementLogsScroll = resetMovementLogsScroll;

// Close modal on outside click
document.addEventListener('click', function(e) {
  const modal = document.getElementById('viewPharmacyInventoryModal');
  if (e.target === modal) {
    closeViewPharmacyInventoryModal();
  }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('viewPharmacyInventoryModal');
    if (modal && !modal.classList.contains('hidden')) {
      closeViewPharmacyInventoryModal();
    }
  }
});

// Auto-scroll variables for pharmacy inventory table
let pharmacyAutoScrollInterval = null;
let pharmacyIsScrolling = false;
const PHARMACY_SCROLL_SPEED = 50; // milliseconds between scrolls
const PHARMACY_ROW_LIMIT = 10; // Start auto-scroll when >= 10 rows

// Auto-scroll function for pharmacy inventory table
function togglePharmacyAutoScroll() {
  const wrapper = document.getElementById('pharmacyInventoryTableWrapper');
  if (!wrapper) return;
  
  const tbody = document.getElementById('pharmacyInventoryTableBody');
  if (!tbody) return;
  
  const visibleRows = Array.from(tbody.querySelectorAll('tr')).filter(row => row.style.display !== 'none');
  
  if (visibleRows.length < PHARMACY_ROW_LIMIT) {
    stopPharmacyAutoScroll();
    return;
  }
  
  pharmacyIsScrolling = !pharmacyIsScrolling;
  
  if (pharmacyIsScrolling) {
    pharmacyAutoScrollInterval = setInterval(() => {
      if (wrapper.scrollHeight > wrapper.clientHeight) {
        const scrollAmount = 1;
        wrapper.scrollTop += scrollAmount;
        
        // Reset to top when reaching bottom
        if (wrapper.scrollTop >= wrapper.scrollHeight - wrapper.clientHeight - 5) {
          setTimeout(() => {
            wrapper.scrollTop = 0;
          }, 1000);
        }
      } else {
        stopPharmacyAutoScroll();
      }
    }, PHARMACY_SCROLL_SPEED);
  } else {
    stopPharmacyAutoScroll();
  }
  
  updatePharmacyToggleButton();
}

function stopPharmacyAutoScroll() {
  if (pharmacyAutoScrollInterval) {
    clearInterval(pharmacyAutoScrollInterval);
    pharmacyAutoScrollInterval = null;
  }
  pharmacyIsScrolling = false;
  updatePharmacyToggleButton();
}

function resetPharmacyScroll() {
  const wrapper = document.getElementById('pharmacyInventoryTableWrapper');
  if (wrapper) {
    wrapper.scrollTop = 0;
  }
  stopPharmacyAutoScroll();
}

function updatePharmacyToggleButton() {
  const btn = document.getElementById('pharmacyAutoScrollToggle');
  if (btn) {
    if (pharmacyIsScrolling) {
      btn.textContent = 'Stop Auto Scroll';
      btn.classList.remove('bg-green-600', 'hover:bg-green-700');
      btn.classList.add('bg-red-600', 'hover:bg-red-700');
    } else {
      btn.textContent = 'Start Auto Scroll';
      btn.classList.remove('bg-red-600', 'hover:bg-red-700');
      btn.classList.add('bg-green-600', 'hover:bg-green-700');
    }
  }
}

// Check if auto-scroll should be enabled on load
function checkPharmacyAutoScroll() {
  const tbody = document.getElementById('pharmacyInventoryTableBody');
  const controls = document.getElementById('pharmacyAutoScrollControls');
  
  if (!tbody || !controls) return;
  
  const visibleRows = Array.from(tbody.querySelectorAll('tr')).filter(row => row.style.display !== 'none');
  
  if (visibleRows.length >= PHARMACY_ROW_LIMIT) {
    controls.classList.remove('hidden');
    // Auto-start scrolling if >= 10 rows
    if (!pharmacyIsScrolling) {
      togglePharmacyAutoScroll();
    }
  } else {
    controls.classList.add('hidden');
    stopPharmacyAutoScroll();
  }
}

// Print all visible pharmacy inventory
function printFullReport() {
    const tableBody = document.getElementById('pharmacyInventoryTableBody');
    if (!tableBody) {
        showErrorModal('Error', 'No pharmacy inventory table found.');
        return;
    }
    
    // Collect all visible rows (not hidden by filters)
    const allRows = tableBody.querySelectorAll('tr');
    const visibleRows = [];
    allRows.forEach(row => {
        // Only include rows that are visible (not hidden by display:none)
        if (row.style.display !== 'none' && row.offsetParent !== null && !row.querySelector('td[colspan]')) {
            visibleRows.push(row.cloneNode(true));
        }
    });
    
    if (visibleRows.length === 0) {
        showErrorModal('No Data', 'No pharmacy inventory data to print. Please check your filters.');
        return;
    }
    
    // Calculate totals from visible rows
    let totalItems = 0;
    let totalUnitsIn = 0;
    let totalUnitsOut = 0;
    let totalUnitsBalance = 0;
    
    visibleRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 6) {
            totalItems++;
            // Columns: 0=Inventory ID, 1=Product, 2=Batch, 3=Units IN, 4=Units OUT, 5=Units Balance, 6=Expiry Date, 7=Last Updated
            const unitsInText = cells[3]?.textContent.replace(/,/g, '').trim() || '0';
            const unitsOutText = cells[4]?.textContent.replace(/,/g, '').trim() || '0';
            const unitsBalanceText = cells[5]?.textContent.replace(/,/g, '').trim() || '0';
            
            totalUnitsIn += parseFloat(unitsInText) || 0;
            totalUnitsOut += parseFloat(unitsOutText) || 0;
            totalUnitsBalance += parseFloat(unitsBalanceText) || 0;
        }
    });
    
    // Get filter information
    const searchInput = document.getElementById('searchInput')?.value || '';
    const productNameFilter = document.getElementById('productNameFilter')?.value || '';
    const categoryFilter = document.getElementById('categoryFilter')?.value || '';
    const stockStatusFilter = document.getElementById('stockStatusFilter')?.value || '';
    
    // Create print window
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Pharmacy Inventory Report</title>
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
                    border-bottom: 3px solid #9333ea;
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
                    border-left: 4px solid #9333ea;
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
                    background: linear-gradient(135deg, #7e22ce 0%, #9333ea 100%);
                }
                .print-table th {
                    color: #fff;
                    font-weight: 700;
                    padding: 12px 8px;
                    text-align: left;
                    border: 1px solid #6b21a8;
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
                <h1>💊 Pharmacy Inventory Report</h1>
            </div>
            
            <div class="print-summary-cards">
                <div class="summary-card">
                    <div class="label">Total Items</div>
                    <div class="value">${totalItems}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Total Units IN</div>
                    <div class="value">${totalUnitsIn.toLocaleString()}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Total Units OUT</div>
                    <div class="value">${totalUnitsOut.toLocaleString()}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Total Balance</div>
                    <div class="value">${totalUnitsBalance.toLocaleString()}</div>
                </div>
            </div>
            
            <div class="print-info">
                ${searchInput ? `<p><strong>Search:</strong> ${searchInput}</p>` : ''}
                ${productNameFilter ? `<p><strong>Product:</strong> ${productNameFilter}</p>` : ''}
                ${categoryFilter ? `<p><strong>Category:</strong> ${categoryFilter}</p>` : ''}
                ${stockStatusFilter ? `<p><strong>Stock Status:</strong> ${stockStatusFilter}</p>` : ''}
            </div>
            
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">Inventory ID</th>
                        <th style="width: 25%;">Product</th>
                        <th style="width: 12%;">Batch</th>
                        <th style="width: 10%;" class="text-right">Units IN</th>
                        <th style="width: 10%;" class="text-right">Units OUT</th>
                        <th style="width: 10%;" class="text-right">Balance</th>
                        <th style="width: 12%;">Expiry Date</th>
                        <th style="width: 13%;">Last Updated</th>
                    </tr>
                </thead>
                <tbody>
    `);
    
    // Add all visible rows to print
    visibleRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 6) {
            // Columns: 0=Inventory ID, 1=Product, 2=Batch, 3=Units IN, 4=Units OUT, 5=Units Balance, 6=Expiry Date, 7=Last Updated
            const inventoryId = cells[0]?.textContent.trim() || '';
            const product = cells[1]?.textContent.trim() || '';
            const batch = cells[2]?.textContent.trim() || '';
            const unitsIn = cells[3]?.textContent.trim() || '0';
            const unitsOut = cells[4]?.textContent.trim() || '0';
            const balance = cells[5]?.textContent.trim() || '0';
            const expiryDate = cells[6]?.textContent.trim() || 'N/A';
            const lastUpdated = cells[7]?.textContent.trim() || 'N/A';
            
            printWindow.document.write(`
                <tr>
                    <td style="padding: 10px 8px;">${inventoryId}</td>
                    <td style="padding: 10px 8px;">${product}</td>
                    <td style="padding: 10px 8px;">${batch}</td>
                    <td style="padding: 10px 8px; text-align: right;">${unitsIn}</td>
                    <td style="padding: 10px 8px; text-align: right;">${unitsOut}</td>
                    <td style="padding: 10px 8px; text-align: right;">${balance}</td>
                    <td style="padding: 10px 8px;">${expiryDate}</td>
                    <td style="padding: 10px 8px;">${lastUpdated}</td>
                </tr>
            `);
        }
    });
    
    printWindow.document.write(`
                </tbody>
            </table>
            
            <div class="print-footer">
                <p><strong>Generated by OCT Pharmacy</strong> | ${new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Expose functions globally
window.togglePharmacyAutoScroll = togglePharmacyAutoScroll;
window.resetPharmacyScroll = resetPharmacyScroll;
window.filterPharmacyInventory = filterPharmacyInventory;
window.clearPharmacyFilters = clearPharmacyFilters;
window.printFullReport = printFullReport;

// Add click handlers for table rows and mobile cards
document.addEventListener('DOMContentLoaded', function() {
  // Handle desktop table row clicks
  const tableBody = document.getElementById('pharmacyInventoryTableBody');
  if (tableBody) {
    tableBody.addEventListener('click', function(e) {
      // Find the closest row
      const row = e.target.closest('tr');
      if (!row || row.querySelector('td[colspan]')) return; // Skip empty state row
      
      // Don't trigger if clicking on buttons or links
      if (e.target.closest('button') || e.target.closest('a')) return;
      
      // Get row data from data attribute
      const rowData = row.getAttribute('data-inventory');
      if (rowData) {
        try {
          const item = JSON.parse(rowData);
          openViewPharmacyInventoryModal(item);
        } catch (error) {
          console.error('Error parsing inventory data:', error);
        }
      }
    });
  }
  
  // Event delegation for movement log filters (handles dynamically loaded modal content)
  document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('movement-log-filter')) {
      const filterType = e.target.getAttribute('data-filter-type');
      if (filterType === 'date' || filterType === 'select') {
        filterMovementLogs();
      }
    }
  });
  
  document.addEventListener('keyup', function(e) {
    if (e.target && e.target.classList.contains('movement-log-filter')) {
      const filterType = e.target.getAttribute('data-filter-type');
      if (filterType === 'search') {
        filterMovementLogs(true); // Use debounce for search inputs
      }
    }
  });
  
  // Check for auto-scroll on page load and after filtering
  checkPharmacyAutoScroll();
  
  // Update auto-scroll when filtering
  const originalFilterPharmacyInventory = window.filterPharmacyInventory;
  if (originalFilterPharmacyInventory) {
    window.filterPharmacyInventory = function() {
      originalFilterPharmacyInventory();
      setTimeout(checkPharmacyAutoScroll, 100);
    };
  }
});

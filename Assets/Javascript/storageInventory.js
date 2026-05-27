// Storage Inventory Modal Functions
function openAddStorageInventoryModal() {
  const modal = document.getElementById('addStorageInventoryModal');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const form = document.getElementById('addStorageInventoryForm');
    if (form) form.reset();
  }
}

function closeAddStorageInventoryModal() {
  const modal = document.getElementById('addStorageInventoryModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function openEditStorageInventoryModal(item) {
  const modal = document.getElementById('editStorageInventoryModal');
  if (modal) {
    document.getElementById('editStorageId').value = item.storage_id || '';
    document.getElementById('editStorageProductName').value = (item.brand_name || 'N/A') + (item.generic_name ? ' (' + item.generic_name + ')' : '');
    document.getElementById('editStorageBatchNo').value = item.batch_no || 'N/A';
    document.getElementById('editQuantityPackagesIn').value = item.quantity_packages_in || 0;
    document.getElementById('editQuantityPackagesOut').value = item.quantity_packages_out || 0;
    document.getElementById('editQuantityUnitsIn').value = item.quantity_units_in || 0;
    document.getElementById('editQuantityUnitsOut').value = item.quantity_units_out || 0;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
}

function closeEditStorageInventoryModal() {
  const modal = document.getElementById('editStorageInventoryModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function openDeleteStorageInventoryModal(storageId, itemName) {
  const modal = document.getElementById('deleteStorageInventoryModal');
  if (modal) {
    document.getElementById('deleteStorageInventoryName').textContent = itemName;
    document.getElementById('deleteStorageIdHidden').value = storageId;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
}

function closeDeleteStorageInventoryModal() {
  const modal = document.getElementById('deleteStorageInventoryModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function filterStorageInventory() {
  const searchInput = document.getElementById('searchInput').value.toLowerCase();
  const productNameFilter = document.getElementById('productNameFilter')?.value || '';
  const categoryFilter = document.getElementById('categoryFilter')?.value || '';
  const stockStatusFilter = document.getElementById('stockStatusFilter')?.value || '';
  const table = document.getElementById('storageInventoryTableBody');
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
  setTimeout(checkStorageAutoScroll, 100);
}

function clearStorageFilters() {
  document.getElementById('searchInput').value = '';
  const productNameFilter = document.getElementById('productNameFilter');
  const categoryFilter = document.getElementById('categoryFilter');
  const stockStatusFilter = document.getElementById('stockStatusFilter');
  if (productNameFilter) productNameFilter.value = '';
  if (categoryFilter) categoryFilter.value = '';
  if (stockStatusFilter) stockStatusFilter.value = '';
  filterStorageInventory();
}

// View Storage Inventory Details Modal
function openViewStorageInventoryModal(item) {
  const modal = document.getElementById('viewStorageInventoryModal');
  if (!modal) {
    console.error('View storage inventory modal not found');
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
  
  // Populate main modal with all details
  populateStorageInventoryModal(item);
  
  // Stop any running auto-scroll
  if (storageMovementLogsAutoScrollInterval) {
    clearInterval(storageMovementLogsAutoScrollInterval);
    storageMovementLogsAutoScrollInterval = null;
    storageMovementLogsIsScrolling = false;
  }
  
  // Clear debounce timer if any
  if (storageMovementLogsFilterDebounceTimer) {
    clearTimeout(storageMovementLogsFilterDebounceTimer);
    storageMovementLogsFilterDebounceTimer = null;
  }
  
  // Reset filters when opening modal
  clearStorageMovementLogFilters();
  
  // Show loading state for movement logs
  const loadingEl = document.getElementById('movementLogsLoading');
  const contentEl = document.getElementById('movementLogsContent');
  const emptyEl = document.getElementById('movementLogsEmpty');
  
  if (loadingEl) loadingEl.classList.remove('hidden');
  if (contentEl) contentEl.classList.add('hidden');
  if (emptyEl) emptyEl.classList.add('hidden');
  
  const controlsEl = document.getElementById('storageMovementLogsAutoScrollControls');
  if (controlsEl) controlsEl.classList.add('hidden');
  
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

// Populate storage inventory modal with all details
function populateStorageInventoryModal(item) {
  try {
  // Format expiry date
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
  
    // Populate modal with all details - with null checks
    const idEl = document.getElementById('viewStorageInventoryId');
    const productEl = document.getElementById('viewProduct');
    const genericEl = document.getElementById('viewGenericName');
    const strengthEl = document.getElementById('viewStrength');
    const batchEl = document.getElementById('viewBatchNo');
    const expiryEl = document.getElementById('viewExpiryDate');
    const packagesInEl = document.getElementById('viewPackagesIn');
    const packagesOutEl = document.getElementById('viewPackagesOut');
    const packagesBalanceEl = document.getElementById('viewPackagesBalance');
    const unitsInEl = document.getElementById('viewUnitsIn');
    const unitsOutEl = document.getElementById('viewUnitsOut');
    const unitsBalanceEl = document.getElementById('viewUnitsBalance');
    const lastUpdatedEl = document.getElementById('viewLastUpdated');
    
    if (!idEl || !productEl || !genericEl || !strengthEl || !batchEl || !expiryEl || 
        !packagesInEl || !packagesOutEl || !packagesBalanceEl || 
        !unitsInEl || !unitsOutEl || !unitsBalanceEl || !lastUpdatedEl) {
      console.error('Storage inventory modal elements not found. Please refresh the page.');
      return;
    }
    
    idEl.textContent = '#' + (item.storage_id || 'N/A');
    productEl.textContent = item.brand_name || 'N/A';
    genericEl.textContent = item.generic_name || 'N/A';
    strengthEl.textContent = item.strength || 'N/A';
    batchEl.textContent = item.batch_no || 'N/A';
    expiryEl.innerHTML = expiryHtml;
    packagesInEl.textContent = number_format(parseInt(item.quantity_packages_in || 0));
    packagesOutEl.textContent = number_format(parseInt(item.quantity_packages_out || 0));
    packagesBalanceEl.textContent = number_format(parseInt(item.quantity_packages_balance || 0));
    unitsInEl.textContent = number_format(parseInt(item.quantity_units_in || 0));
    unitsOutEl.textContent = number_format(parseInt(item.quantity_units_out || 0));
    unitsBalanceEl.textContent = number_format(parseInt(item.quantity_units_balance || 0));
    lastUpdatedEl.textContent = formatDateTime(item.updated_at);
  } catch (error) {
    console.error('Error populating storage inventory modal:', error);
  }
}

// Print storage inventory details
function printStorageInventoryDetails() {
  const element = document.getElementById('printableStorageInventoryContent');
  if (!element) {
    alert('No content to print');
    return;
  }
  
  const inventoryId = document.getElementById('viewStorageInventoryId')?.textContent?.trim() || 'N/A';
  const product = document.getElementById('viewProduct')?.textContent?.trim() || 'N/A';
  
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('Please allow popups to print this document');
    return;
  }
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Storage Inventory Details - ${escapeHtml(product)}</title>
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
        <h2>Storage Inventory Details</h2>
        <p>Storage ID: ${escapeHtml(inventoryId)} | Product: ${escapeHtml(product)}</p>
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

// Store original logs data for filtering
let originalStorageMovementLogs = [];

// Auto-scroll variables for movement logs
let storageMovementLogsAutoScrollInterval = null;
let storageMovementLogsIsScrolling = false;
const STORAGE_MOVEMENT_LOGS_SCROLL_SPEED = 50; // pixels per scroll
const STORAGE_MOVEMENT_LOGS_ROW_LIMIT = 10; // Show auto-scroll when 10+ rows

// Debounce timer for filter search
let storageMovementLogsFilterDebounceTimer = null;

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
        originalStorageMovementLogs = data.logs;
        // Display with current filters (will apply any active filters)
        filterStorageMovementLogs();
      } else {
        originalStorageMovementLogs = [];
        if (emptyEl) emptyEl.classList.remove('hidden');
        if (contentEl) contentEl.classList.add('hidden');
        // Hide auto-scroll controls when no data
        const controlsEl = document.getElementById('storageMovementLogsAutoScrollControls');
        if (controlsEl) controlsEl.classList.add('hidden');
      }
    })
    .catch(error => {
      console.error('Error fetching movement logs:', error);
      if (loadingEl) loadingEl.classList.add('hidden');
      if (emptyEl) emptyEl.classList.remove('hidden');
      if (contentEl) contentEl.classList.add('hidden');
      originalStorageMovementLogs = [];
      
      // Hide auto-scroll controls on error
      const controlsEl = document.getElementById('storageMovementLogsAutoScrollControls');
      if (controlsEl) controlsEl.classList.add('hidden');
    });
}

// Display movement logs in table
function displayMovementLogs(logs) {
  const tbody = document.getElementById('movementLogsTableBody');
  const contentEl = document.getElementById('movementLogsContent');
  const emptyEl = document.getElementById('movementLogsEmpty');
  const controlsEl = document.getElementById('storageMovementLogsAutoScrollControls');
  
  if (!tbody) return;
  
  tbody.innerHTML = '';
  
  if (!logs || logs.length === 0) {
    if (contentEl) contentEl.classList.add('hidden');
    if (emptyEl) emptyEl.classList.remove('hidden');
    if (controlsEl) controlsEl.classList.add('hidden');
    // Stop auto-scroll if running
    if (storageMovementLogsAutoScrollInterval) {
      clearInterval(storageMovementLogsAutoScrollInterval);
      storageMovementLogsAutoScrollInterval = null;
      storageMovementLogsIsScrolling = false;
    }
    return;
  }
  
  if (contentEl) contentEl.classList.remove('hidden');
  if (emptyEl) emptyEl.classList.add('hidden');
  
  // Show/hide auto-scroll controls based on row count
  if (logs.length >= STORAGE_MOVEMENT_LOGS_ROW_LIMIT) {
    if (controlsEl) controlsEl.classList.remove('hidden');
    updateStorageMovementLogsVisibleRowsCount();
  } else {
    if (controlsEl) controlsEl.classList.add('hidden');
    // Stop auto-scroll if running
    if (storageMovementLogsAutoScrollInterval) {
      clearInterval(storageMovementLogsAutoScrollInterval);
      storageMovementLogsAutoScrollInterval = null;
      storageMovementLogsIsScrolling = false;
      updateStorageMovementLogsToggleButton();
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
  updateStorageMovementLogsVisibleRowsCount();
  
  // Add scroll event listener to update visible rows count on manual scroll
  const wrapper = document.getElementById('storageMovementLogsTableWrapper');
  if (wrapper) {
    wrapper.addEventListener('scroll', updateStorageMovementLogsVisibleRowsCount);
  }
}

// Filter movement logs with debouncing support
function filterStorageMovementLogs(useDebounce = false) {
  // Safety check - ensure originalStorageMovementLogs is initialized
  if (typeof originalStorageMovementLogs === 'undefined') {
    originalStorageMovementLogs = [];
  }
  
  // Clear existing debounce timer if any
  if (storageMovementLogsFilterDebounceTimer) {
    clearTimeout(storageMovementLogsFilterDebounceTimer);
    storageMovementLogsFilterDebounceTimer = null;
  }
  
  // Apply debounce for search inputs (300ms delay)
  if (useDebounce) {
    storageMovementLogsFilterDebounceTimer = setTimeout(() => {
      filterStorageMovementLogs(false);
    }, 300);
    return;
  }
  
  // Return early if no logs loaded
  if (!originalStorageMovementLogs || originalStorageMovementLogs.length === 0) {
    // Hide content and show empty state if no original logs
    const content = document.getElementById('movementLogsContent');
    const empty = document.getElementById('movementLogsEmpty');
    if (content) content.classList.add('hidden');
    if (empty) empty.classList.remove('hidden');
    return;
  }
  
  // Get filter values
  const startDateInput = document.getElementById('storageMovementLogStartDate');
  const endDateInput = document.getElementById('storageMovementLogEndDate');
  const typeInput = document.getElementById('storageMovementLogType');
  const userInput = document.getElementById('storageMovementLogUser');
  const searchInput = document.getElementById('storageMovementLogSearch');
  
  const startDate = startDateInput?.value || '';
  const endDate = endDateInput?.value || '';
  const movementType = typeInput?.value || '';
  const userFilter = (userInput?.value || '').toLowerCase().trim();
  const searchFilter = (searchInput?.value || '').toLowerCase().trim();
  
  // Filter the logs
  const filteredLogs = originalStorageMovementLogs.filter(log => {
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
  resetStorageMovementLogsScroll();
}

// Clear storage movement log filters
function clearStorageMovementLogFilters() {
  const startDateInput = document.getElementById('storageMovementLogStartDate');
  const endDateInput = document.getElementById('storageMovementLogEndDate');
  const typeInput = document.getElementById('storageMovementLogType');
  const userInput = document.getElementById('storageMovementLogUser');
  const searchInput = document.getElementById('storageMovementLogSearch');
  
  if (startDateInput) startDateInput.value = '';
  if (endDateInput) endDateInput.value = '';
  if (typeInput) typeInput.value = '';
  if (userInput) userInput.value = '';
  if (searchInput) searchInput.value = '';
  
  // Only filter if we have logs loaded
  if (originalStorageMovementLogs.length > 0) {
    filterStorageMovementLogs();
  }
}

// Auto-scroll functions for storage movement logs
function toggleStorageMovementLogsAutoScroll() {
  const wrapper = document.getElementById('storageMovementLogsTableWrapper');
  if (!wrapper) return;
  
  if (storageMovementLogsIsScrolling) {
    // Stop scrolling
    clearInterval(storageMovementLogsAutoScrollInterval);
    storageMovementLogsAutoScrollInterval = null;
    storageMovementLogsIsScrolling = false;
  } else {
    // Start scrolling
    storageMovementLogsAutoScrollInterval = setInterval(() => {
      if (wrapper.scrollTop + wrapper.clientHeight >= wrapper.scrollHeight - 5) {
        // Reached bottom, scroll back to top
        wrapper.scrollTop = 0;
      } else {
        wrapper.scrollTop += STORAGE_MOVEMENT_LOGS_SCROLL_SPEED;
      }
      updateStorageMovementLogsVisibleRowsCount();
    }, 100); // Update every 100ms
    storageMovementLogsIsScrolling = true;
  }
  
  updateStorageMovementLogsToggleButton();
}

function resetStorageMovementLogsScroll() {
  const wrapper = document.getElementById('storageMovementLogsTableWrapper');
  if (wrapper) {
    wrapper.scrollTop = 0;
    updateStorageMovementLogsVisibleRowsCount();
  }
  
  // Stop auto-scroll if running
  if (storageMovementLogsAutoScrollInterval) {
    clearInterval(storageMovementLogsAutoScrollInterval);
    storageMovementLogsAutoScrollInterval = null;
    storageMovementLogsIsScrolling = false;
    updateStorageMovementLogsToggleButton();
  }
}

function updateStorageMovementLogsToggleButton() {
  const button = document.getElementById('storageMovementLogsToggleScroll');
  if (!button) return;
  
  if (storageMovementLogsIsScrolling) {
    button.innerHTML = `
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      Stop Auto Scroll
    `;
    button.classList.remove('bg-blue-600', 'hover:bg-blue-700');
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
    button.classList.add('bg-blue-600', 'hover:bg-blue-700');
  }
}

function updateStorageMovementLogsVisibleRowsCount() {
  const wrapper = document.getElementById('storageMovementLogsTableWrapper');
  const countSpan = document.getElementById('storageMovementLogsVisibleCount');
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

function closeViewStorageInventoryModal() {
  const modal = document.getElementById('viewStorageInventoryModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    
    // Stop auto-scroll when closing modal
    if (storageMovementLogsAutoScrollInterval) {
      clearInterval(storageMovementLogsAutoScrollInterval);
      storageMovementLogsAutoScrollInterval = null;
      storageMovementLogsIsScrolling = false;
    }
  }
}

// Print Storage Inventory Details Function
function printStorageInventoryDetails() {
  // Extract product name
  const product = document.getElementById('viewProduct')?.textContent?.trim() || 'N/A';
  
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
      <title>Storage Inventory - ${escapeHtml(product)}</title>
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

// Expose functions to global scope
window.openAddStorageInventoryModal = openAddStorageInventoryModal;
window.closeAddStorageInventoryModal = closeAddStorageInventoryModal;
window.openEditStorageInventoryModal = openEditStorageInventoryModal;
window.closeEditStorageInventoryModal = closeEditStorageInventoryModal;
window.openDeleteStorageInventoryModal = openDeleteStorageInventoryModal;
window.closeDeleteStorageInventoryModal = closeDeleteStorageInventoryModal;
window.openViewStorageInventoryModal = openViewStorageInventoryModal;
window.closeViewStorageInventoryModal = closeViewStorageInventoryModal;
window.printStorageInventoryDetails = printStorageInventoryDetails;
window.filterStorageInventory = filterStorageInventory;
window.filterStorageMovementLogs = filterStorageMovementLogs;
window.clearStorageMovementLogFilters = clearStorageMovementLogFilters;
window.toggleStorageMovementLogsAutoScroll = toggleStorageMovementLogsAutoScroll;
window.resetStorageMovementLogsScroll = resetStorageMovementLogsScroll;

// Close modal on outside click
document.addEventListener('click', function(e) {
  const modal = document.getElementById('viewStorageInventoryModal');
  if (e.target === modal) {
    closeViewStorageInventoryModal();
  }
  
  const detailsModal = document.getElementById('viewStorageInventoryDetailsModal');
  if (e.target === detailsModal) {
    closeViewStorageInventoryDetailsModal();
  }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const detailsModal = document.getElementById('viewStorageInventoryDetailsModal');
    if (detailsModal && !detailsModal.classList.contains('hidden')) {
      closeViewStorageInventoryDetailsModal();
      return;
    }
    
    const modal = document.getElementById('viewStorageInventoryModal');
    if (modal && !modal.classList.contains('hidden')) {
      closeViewStorageInventoryModal();
    }
  }
});

// Auto-scroll variables for storage inventory table
let storageAutoScrollInterval = null;
let storageIsScrolling = false;
const STORAGE_SCROLL_SPEED = 50; // milliseconds between scrolls
const STORAGE_ROW_LIMIT = 10; // Start auto-scroll when >= 10 rows

// Auto-scroll function for storage inventory table
function toggleStorageAutoScroll() {
  const wrapper = document.getElementById('storageInventoryTableWrapper');
  if (!wrapper) return;
  
  const tbody = document.getElementById('storageInventoryTableBody');
  if (!tbody) return;
  
  const visibleRows = Array.from(tbody.querySelectorAll('tr')).filter(row => row.style.display !== 'none');
  
  if (visibleRows.length < STORAGE_ROW_LIMIT) {
    stopStorageAutoScroll();
    return;
  }
  
  storageIsScrolling = !storageIsScrolling;
  
  if (storageIsScrolling) {
    storageAutoScrollInterval = setInterval(() => {
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
        stopStorageAutoScroll();
      }
    }, STORAGE_SCROLL_SPEED);
  } else {
    stopStorageAutoScroll();
  }
  
  updateStorageToggleButton();
}

function stopStorageAutoScroll() {
  if (storageAutoScrollInterval) {
    clearInterval(storageAutoScrollInterval);
    storageAutoScrollInterval = null;
  }
  storageIsScrolling = false;
  updateStorageToggleButton();
}

function resetStorageScroll() {
  const wrapper = document.getElementById('storageInventoryTableWrapper');
  if (wrapper) {
    wrapper.scrollTop = 0;
  }
  stopStorageAutoScroll();
}

function updateStorageToggleButton() {
  const btn = document.getElementById('storageAutoScrollToggle');
  if (btn) {
    if (storageIsScrolling) {
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
function checkStorageAutoScroll() {
  const tbody = document.getElementById('storageInventoryTableBody');
  const controls = document.getElementById('storageAutoScrollControls');
  
  if (!tbody || !controls) return;
  
  const visibleRows = Array.from(tbody.querySelectorAll('tr')).filter(row => row.style.display !== 'none');
  
  if (visibleRows.length >= STORAGE_ROW_LIMIT) {
    controls.classList.remove('hidden');
    // Auto-start scrolling if >= 10 rows
    if (!storageIsScrolling) {
      toggleStorageAutoScroll();
    }
  } else {
    controls.classList.add('hidden');
    stopStorageAutoScroll();
  }
}

// Expose functions globally
window.toggleStorageAutoScroll = toggleStorageAutoScroll;
window.resetStorageScroll = resetStorageScroll;
window.filterStorageInventory = filterStorageInventory;
window.clearStorageFilters = clearStorageFilters;

// Add click handlers for table rows and mobile cards
document.addEventListener('DOMContentLoaded', function() {
  // Handle desktop table row clicks
  const tableBody = document.getElementById('storageInventoryTableBody');
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
          openViewStorageInventoryModal(item);
        } catch (error) {
          console.error('Error parsing inventory data:', error);
        }
      }
    });
  }
  
  // Event delegation for storage movement log filters
  document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('storage-movement-log-filter')) {
      const filterType = e.target.getAttribute('data-filter-type');
      if (filterType === 'date' || filterType === 'select') {
        filterStorageMovementLogs();
      }
    }
  });
  
  document.addEventListener('keyup', function(e) {
    if (e.target && e.target.classList.contains('storage-movement-log-filter')) {
      const filterType = e.target.getAttribute('data-filter-type');
      if (filterType === 'search') {
        filterStorageMovementLogs(true); // Use debounce for search
      }
    }
  });
  
  // Check for auto-scroll on page load and after filtering
  checkStorageAutoScroll();
  
  // Update auto-scroll when filtering
  const originalFilterStorageInventory = window.filterStorageInventory;
  if (originalFilterStorageInventory) {
    window.filterStorageInventory = function() {
      originalFilterStorageInventory();
      setTimeout(checkStorageAutoScroll, 100);
    };
  }
});

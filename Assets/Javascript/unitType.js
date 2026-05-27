// Modal handlers for Unit Types
function openAddUnitTypeModal() {
  const modal = document.getElementById('addUnitTypeModal');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const input = document.getElementById('addUnitTypeName');
    if (input) input.value = '';
  }
}

function closeAddUnitTypeModal() {
  const modal = document.getElementById('addUnitTypeModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function openEditUnitTypeModal(ut) {
  const modal = document.getElementById('editUnitTypeModal');
  if (!modal) return;
  document.getElementById('editUnitTypeId').value = ut.unit_type_id;
  document.getElementById('editUnitTypeName').value = ut.unit_name;
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeEditUnitTypeModal() {
  const modal = document.getElementById('editUnitTypeModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function openDeleteUnitTypeModal(id, name) {
  const modal = document.getElementById('deleteUnitTypeModal');
  if (!modal) return;
  document.getElementById('deleteUnitTypeIdHidden').value = id;
  document.getElementById('deleteUnitTypeName').textContent = name;
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeDeleteUnitTypeModal() {
  const modal = document.getElementById('deleteUnitTypeModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

let currentStatusFilter = 'all';

function filterUnitTypes() {
  const q = (document.getElementById('searchInput').value || '').toLowerCase();
  
  // Filter desktop table
  const tbody = document.getElementById('unitTypesTableBody');
  if (tbody) {
    const rows = tbody.getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      const nameText = row.cells[1]?.textContent.toLowerCase() || '';
      const status = row.getAttribute('data-status') || '';
      
      const matchesSearch = nameText.includes(q);
      const matchesStatus = currentStatusFilter === 'all' || status === currentStatusFilter;
      
      row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    }
  }
  
  // Filter mobile cards
  const cardsContainer = document.getElementById('unitTypesCardsContainer');
  if (cardsContainer) {
    const cards = cardsContainer.getElementsByClassName('cursor-pointer');
    for (let i = 0; i < cards.length; i++) {
      const card = cards[i];
      const nameElement = card.querySelector('h3');
      const nameText = nameElement ? nameElement.textContent.toLowerCase() : '';
      const status = card.getAttribute('data-status') || '';
      
      const matchesSearch = nameText.includes(q);
      const matchesStatus = currentStatusFilter === 'all' || status === currentStatusFilter;
      
      if (matchesSearch && matchesStatus) card.style.display = '';
      else card.style.display = 'none';
    }
  }
}

// Filter by Status
function filterByStatus(status) {
  currentStatusFilter = status;
  
  // Update button styles
  const baseClass = 'flex-1 sm:flex-none px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl transition-colors font-semibold text-xs sm:text-sm whitespace-nowrap';
  document.getElementById('filterAll').className = status === 'all' 
    ? baseClass + ' bg-blue-500 text-white hover:bg-blue-600'
    : baseClass + ' bg-gray-200 text-gray-700 hover:bg-gray-300';
  
  document.getElementById('filterActive').className = status === 'active'
    ? baseClass + ' bg-blue-500 text-white hover:bg-blue-600'
    : baseClass + ' bg-gray-200 text-gray-700 hover:bg-gray-300';
  
  document.getElementById('filterInactive').className = status === 'inactive'
    ? baseClass + ' bg-blue-500 text-white hover:bg-blue-600'
    : baseClass + ' bg-gray-200 text-gray-700 hover:bg-gray-300';
  
  filterUnitTypes();
}

// View Products Modal Functions
let currentUnitTypeId = null;
let allProducts = [];

function openViewProductsModal(unitTypeId, unitTypeName) {
  currentUnitTypeId = unitTypeId;
  document.getElementById('viewUnitTypeNameHeader').textContent = unitTypeName;
  document.getElementById('viewProductsModal').classList.remove('hidden');
  document.getElementById('viewProductsModal').classList.add('flex');
  
  document.getElementById('productSearchInput').value = '';
  document.getElementById('productsLoading').classList.remove('hidden');
  document.getElementById('productsTableContainer').classList.add('hidden');
  document.getElementById('productsEmpty').classList.add('hidden');
  
  fetchUnitTypeProducts(unitTypeId);
}

function closeViewProductsModal() {
  currentUnitTypeId = null;
  allProducts = [];
  document.getElementById('viewProductsModal').classList.add('hidden');
  document.getElementById('viewProductsModal').classList.remove('flex');
  document.getElementById('productsTableBody').innerHTML = '';
}

// View Unit Type Details Modal
function openViewUnitTypeModal(unitType) {
  const modal = document.getElementById('viewUnitTypeModal');
  if (!modal) {
    console.error('View unit type modal not found');
    return;
  }
  
  // Populate modal with unit type data
  document.getElementById('viewUnitTypeId').textContent = '#' + (unitType.unit_type_id || 'N/A');
  document.getElementById('viewUnitTypeName').textContent = unitType.unit_name || 'N/A';
  
  // Status badge
  const statusEl = document.getElementById('viewUnitTypeStatus');
  const isActive = unitType.status === 'Active' || unitType.status === 'active' || (parseInt(unitType.product_count) || 0) > 0;
  if (isActive) {
    statusEl.innerHTML = '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>';
  } else {
    statusEl.innerHTML = '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">Inactive</span>';
  }
  
  // Product count
  const productCount = parseInt(unitType.product_count) || 0;
  document.getElementById('viewUnitTypeProducts').textContent = productCount.toLocaleString() + ' product(s)';
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  
  // Fetch and display products using this unit type
  fetchUnitTypeProductsList(unitType.unit_type_id);
}

// Immediately expose to window for inline handlers (if any)
window.openViewUnitTypeModal = openViewUnitTypeModal;

function closeViewUnitTypeModal() {
  const modal = document.getElementById('viewUnitTypeModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

// Print Unit Type Details Function
function printUnitTypeDetails() {
  const element = document.getElementById('printableUnitTypeContent');
  if (!element) {
    alert('No content to print');
    return;
  }
  
  const unitTypeId = document.getElementById('viewUnitTypeId')?.textContent || 'N/A';
  const unitTypeName = document.getElementById('viewUnitTypeName')?.textContent || 'N/A';
  
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('Please allow popups to print this document');
    return;
  }
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Unit Type Details - ${unitTypeName}</title>
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
      </style>
    </head>
    <body>
      <div class="header-info">
        <h2>Unit Type Details</h2>
        <p>Unit Type ID: ${unitTypeId} | Name: ${unitTypeName}</p>
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

// Fetch and display products list in the detail modal
async function fetchUnitTypeProductsList(unitTypeId) {
  try {
    // Show loading state
    document.getElementById('unitProductsLoading').classList.remove('hidden');
    document.getElementById('unitProductsContainer').classList.add('hidden');
    document.getElementById('unitProductsEmpty').classList.add('hidden');
    
    const response = await fetch(`../../Controllers/getControllers/getUnitTypeProducts.php?unit_type_id=${unitTypeId}`);
    const result = await response.json();
    
    // Hide loading state
    document.getElementById('unitProductsLoading').classList.add('hidden');
    
    if (result.success && result.products && result.products.length > 0) {
      displayUnitTypeProductsList(result.products);
    } else {
      // Show empty state
      document.getElementById('unitProductsEmpty').classList.remove('hidden');
    }
  } catch (error) {
    console.error('Error fetching unit type products:', error);
    document.getElementById('unitProductsLoading').classList.add('hidden');
    document.getElementById('unitProductsEmpty').classList.remove('hidden');
  }
}

function displayUnitTypeProductsList(products) {
  const tbody = document.getElementById('unitProductsTableBody');
  tbody.innerHTML = '';
  
  products.forEach(product => {
    const row = document.createElement('tr');
    row.className = 'hover:bg-gray-50 transition-colors';

    row.innerHTML = `
      <td class="px-4 py-3">
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
          #${product.product_id}
        </span>
      </td>
      <td class="px-4 py-3">
        <div class="font-semibold text-gray-900">${escapeHtml(product.brand_name || 'N/A')}</div>
      </td>
      <td class="px-4 py-3">
        <div class="text-sm text-gray-600">${escapeHtml(product.generic_name || 'N/A')}</div>
      </td>
      <td class="px-4 py-3">
        <div class="text-sm text-gray-700">${escapeHtml(product.category_name || 'N/A')}</div>
      </td>
    `;
    
    tbody.appendChild(row);
  });
  
  // Show the container
  document.getElementById('unitProductsContainer').classList.remove('hidden');
}

function number_format(number) {
  return parseInt(number).toLocaleString('en-US');
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
  return text.replace(/[&<>"']/g, m => map[m]);
}

async function fetchUnitTypeProducts(unitTypeId) {
  try {
    const response = await fetch(`../../Controllers/getControllers/getUnitTypeProducts.php?unit_type_id=${unitTypeId}`);
    const result = await response.json();
    
    document.getElementById('productsLoading').classList.add('hidden');
    
    if (result.success && result.products && result.products.length > 0) {
      allProducts = result.products;
      document.getElementById('viewProductCount').textContent = result.count;
      document.getElementById('productsTableContainer').classList.remove('hidden');
      renderProducts(allProducts);
    } else {
      document.getElementById('productsEmpty').classList.remove('hidden');
      document.getElementById('viewProductCount').textContent = '0';
    }
  } catch (error) {
    console.error('Error fetching products:', error);
    document.getElementById('productsLoading').classList.add('hidden');
    document.getElementById('productsEmpty').classList.remove('hidden');
    alert('Error loading products. Please try again.');
  }
}

function renderProducts(products) {
  const tbody = document.getElementById('productsTableBody');
  tbody.innerHTML = '';
  
  products.forEach(product => {
    const row = document.createElement('tr');
    row.className = 'hover:bg-gray-50 transition-colors';
    
    const expiryDate = product.nearest_expiry ? new Date(product.nearest_expiry).toLocaleDateString() : 'N/A';
    const expiryClass = product.nearest_expiry && new Date(product.nearest_expiry) < new Date(Date.now() + 30*24*60*60*1000) ? 'text-red-600 font-medium' : 'text-gray-700';
    
    const stockClass = product.total_units_available > 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium';
    const stockText = product.total_units_available > 0 ? product.total_units_available.toLocaleString() : 'Out of Stock';
    
    row.innerHTML = `
      <td class="px-4 py-3 text-sm text-gray-900">#${product.product_id}</td>
      <td class="px-4 py-3 text-sm font-medium text-gray-900">${escapeHtml(product.brand_name || 'N/A')}</td>
      <td class="px-4 py-3 text-sm text-gray-600">${escapeHtml(product.generic_name || 'N/A')}</td>
      <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(product.strength || 'N/A')}</td>
      <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(product.category_name || 'N/A')}</td>
      <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(product.package_type || 'N/A')}</td>
      <td class="px-4 py-3 text-sm text-right ${stockClass}">${stockText}</td>
      <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">₱${product.latest_unit_price.toFixed(2)}</td>
      <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(product.supplier_name || 'N/A')}</td>
      <td class="px-4 py-3 text-sm ${expiryClass}">${expiryDate}</td>
    `;
    
    tbody.appendChild(row);
  });
}

function filterProducts() {
  const searchInput = document.getElementById('productSearchInput').value.toLowerCase();
  
  if (!searchInput.trim()) {
    renderProducts(allProducts);
    return;
  }
  
  const filtered = allProducts.filter(product => {
    const brand = (product.brand_name || '').toLowerCase();
    const generic = (product.generic_name || '').toLowerCase();
    const strength = (product.strength || '').toLowerCase();
    const supplier = (product.supplier_name || '').toLowerCase();
    const category = (product.category_name || '').toLowerCase();
    const packageType = (product.package_type || '').toLowerCase();
    
    return brand.includes(searchInput) || 
           generic.includes(searchInput) || 
           strength.includes(searchInput) ||
           supplier.includes(searchInput) ||
           category.includes(searchInput) ||
           packageType.includes(searchInput);
  });
  
  if (filtered.length > 0) {
    document.getElementById('productsEmpty').classList.add('hidden');
    document.getElementById('productsTableContainer').classList.remove('hidden');
    renderProducts(filtered);
  } else {
    document.getElementById('productsTableContainer').classList.add('hidden');
    document.getElementById('productsEmpty').classList.remove('hidden');
  }
}

// Close modal on outside click
document.addEventListener('click', function(e) {
  const modal = document.getElementById('viewProductsModal');
  if (e.target === modal) {
    closeViewProductsModal();
  }
});

// PDF Export Function
function exportToPDF(type) {
  const element = document.getElementById('printableContent');
  if (!element) {
    alert('No content to export');
    return;
  }
  
  const typeName = type === 'unit' ? document.getElementById('viewUnitTypeNameHeader')?.textContent || 'Unit Type' : document.getElementById('viewPackageTypeNameHeader')?.textContent || 'Package Type';
  const productCount = document.getElementById('viewProductCount')?.textContent || '0';
  
  const opt = {
    margin: [10, 10, 10, 10],
    filename: `Products_${typeName}_${new Date().getTime()}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
  };
  
  // Add header to the element temporarily
  const originalHTML = element.innerHTML;
  const header = `
    <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #ddd;">
      <h2 style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">Products with ${type === 'unit' ? 'Unit Type' : 'Package Type'}: ${typeName}</h2>
      <p style="font-size: 12px; color: #666;">Total: ${productCount} products | Generated: ${new Date().toLocaleString()}</p>
    </div>
  `;
  element.innerHTML = header + originalHTML;
  
  html2pdf().set(opt).from(element).save().then(() => {
    // Restore original HTML
    element.innerHTML = originalHTML;
  });
}

// Print Function
function printProductsTable() {
  const element = document.getElementById('printableContent');
  if (!element) {
    alert('No content to print');
    return;
  }
  
  const typeName = document.getElementById('viewUnitTypeNameHeader')?.textContent || document.getElementById('viewPackageTypeNameHeader')?.textContent || 'Type';
  const productCount = document.getElementById('viewProductCount')?.textContent || '0';
  
  // Create print window
  const printWindow = window.open('', '_blank');
  const type = document.getElementById('viewUnitTypeNameHeader') ? 'unit' : 'package';
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Products - ${typeName}</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          padding: 20px;
          font-size: 12px;
        }
        h2 {
          font-size: 18px;
          margin-bottom: 10px;
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
        th, td {
          border: 1px solid #ddd;
          padding: 8px;
          text-align: left;
        }
        th {
          background-color: #f3f4f6;
          font-weight: bold;
        }
        tr:nth-child(even) {
          background-color: #f9fafb;
        }
      </style>
    </head>
    <body>
      <div class="header-info">
        <h2>Products with ${type === 'unit' ? 'Unit Type' : 'Package Type'}: ${typeName}</h2>
        <p>Total: ${productCount} products | Generated: ${new Date().toLocaleString()}</p>
      </div>
      ${element.outerHTML}
    </body>
    </html>
  `);
  
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
  }, 250);
}

// Expose functions globally
window.exportToPDF = exportToPDF;
window.printProductsTable = printProductsTable;

// Expose to global
window.openAddUnitTypeModal = openAddUnitTypeModal;
window.closeAddUnitTypeModal = closeAddUnitTypeModal;
window.openEditUnitTypeModal = openEditUnitTypeModal;
window.closeEditUnitTypeModal = closeEditUnitTypeModal;
window.openDeleteUnitTypeModal = openDeleteUnitTypeModal;
window.closeDeleteUnitTypeModal = closeDeleteUnitTypeModal;
window.filterUnitTypes = filterUnitTypes;
window.filterByStatus = filterByStatus;
window.openViewProductsModal = openViewProductsModal;
window.closeViewProductsModal = closeViewProductsModal;
window.filterProducts = filterProducts;
window.openViewUnitTypeModal = openViewUnitTypeModal;
window.closeViewUnitTypeModal = closeViewUnitTypeModal;
window.printUnitTypeDetails = printUnitTypeDetails;


// Close modal on outside click and ESC key
document.addEventListener('click', function(e) {
  const productsModal = document.getElementById('viewProductsModal');
  const unitTypeModal = document.getElementById('viewUnitTypeModal');
  if (e.target === productsModal) {
    closeViewProductsModal();
  }
  if (e.target === unitTypeModal) {
    closeViewUnitTypeModal();
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const productsModal = document.getElementById('viewProductsModal');
    const unitTypeModal = document.getElementById('viewUnitTypeModal');
    if (productsModal && !productsModal.classList.contains('hidden')) {
      closeViewProductsModal();
    }
    if (unitTypeModal && !unitTypeModal.classList.contains('hidden')) {
      closeViewUnitTypeModal();
    }
  }
});



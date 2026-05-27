// Modal handlers
function openAddPackageTypeModal() {
  const modal = document.getElementById('addPackageTypeModal');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const input = document.getElementById('addPackageName');
    if (input) input.value = '';
  }
}

function closeAddPackageTypeModal() {
  const modal = document.getElementById('addPackageTypeModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function openEditPackageTypeModal(pt) {
  const modal = document.getElementById('editPackageTypeModal');
  if (!modal) return;
  document.getElementById('editPackageTypeId').value = pt.package_type_id;
  document.getElementById('editPackageName').value = pt.package_name;
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeEditPackageTypeModal() {
  const modal = document.getElementById('editPackageTypeModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function openDeletePackageTypeModal(id, name) {
  const modal = document.getElementById('deletePackageTypeModal');
  if (!modal) return;
  document.getElementById('deletePackageTypeIdHidden').value = id;
  document.getElementById('deletePackageTypeName').textContent = name;
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeDeletePackageTypeModal() {
  const modal = document.getElementById('deletePackageTypeModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

let currentStatusFilter = 'all';

function filterPackageTypes() {
  const q = (document.getElementById('searchInput').value || '').toLowerCase();
  
  // Filter desktop table
  const tbody = document.getElementById('packageTypesTableBody');
  if (tbody) {
    const rows = tbody.getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      const nameText = row.cells[1]?.textContent.toLowerCase() || '';
      const status = row.getAttribute('data-status') || '';
      
      const matchesSearch = nameText.includes(q);
      const matchesStatus = currentStatusFilter === 'all' || status === currentStatusFilter;
      
      if (matchesSearch && matchesStatus) row.style.display = '';
      else row.style.display = 'none';
    }
  }
  
  // Filter mobile cards
  const cardsContainer = document.getElementById('packageTypesCardsContainer');
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
  
  filterPackageTypes();
}

// View Products Modal Functions
let currentPackageTypeId = null;
let allProducts = [];

function openViewProductsModal(packageTypeId, packageTypeName) {
  currentPackageTypeId = packageTypeId;
  document.getElementById('viewPackageTypeNameHeader').textContent = packageTypeName;
  document.getElementById('viewProductsModal').classList.remove('hidden');
  document.getElementById('viewProductsModal').classList.add('flex');
  
  document.getElementById('productSearchInput').value = '';
  document.getElementById('productsLoading').classList.remove('hidden');
  document.getElementById('productsTableContainer').classList.add('hidden');
  document.getElementById('productsEmpty').classList.add('hidden');
  
  fetchPackageTypeProducts(packageTypeId);
}

// Immediately expose to window for inline onclick handlers
window.openViewProductsModal = openViewProductsModal;

function closeViewProductsModal() {
  currentPackageTypeId = null;
  allProducts = [];
  document.getElementById('viewProductsModal').classList.add('hidden');
  document.getElementById('viewProductsModal').classList.remove('flex');
  document.getElementById('productsTableBody').innerHTML = '';
}

// View Package Type Details Modal
function openViewPackageTypeModal(packageType) {
  const modal = document.getElementById('viewPackageTypeModal');
  if (!modal) {
    console.error('View package type modal not found');
    return;
  }
  
  // Populate modal with package type data
  document.getElementById('viewPackageTypeId').textContent = '#' + (packageType.package_type_id || 'N/A');
  document.getElementById('viewPackageTypeName').textContent = packageType.package_name || 'N/A';
  
  // Status badge
  const statusEl = document.getElementById('viewPackageTypeStatus');
  const isActive = packageType.status === 'Active' || packageType.status === 'active';
  statusEl.innerHTML = isActive
    ? '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-green-500 to-green-600 text-white"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Active</span>'
    : '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Inactive</span>';
  
  // Product count
  const productCount = parseInt(packageType.product_count) || 0;
  document.getElementById('viewPackageTypeProducts').textContent = productCount.toLocaleString() + ' product(s)';
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  
  // Fetch and display products using this package type
  fetchPackageTypeProductsList(packageType.package_type_id);
}

function closeViewPackageTypeModal() {
  const modal = document.getElementById('viewPackageTypeModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

// Print Package Type Details Function
function printPackageTypeDetails() {
  const element = document.getElementById('printablePackageTypeContent');
  if (!element) {
    alert('No content to print');
    return;
  }
  
  const packageTypeId = document.getElementById('viewPackageTypeId')?.textContent || 'N/A';
  const packageTypeName = document.getElementById('viewPackageTypeName')?.textContent || 'N/A';
  
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('Please allow popups to print this document');
    return;
  }
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Package Type Details - ${packageTypeName}</title>
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
        <h2>Package Type Details</h2>
        <p>Package Type ID: ${packageTypeId} | Name: ${packageTypeName}</p>
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
async function fetchPackageTypeProductsList(packageTypeId) {
  try {
    // Show loading state
    document.getElementById('packageProductsLoading').classList.remove('hidden');
    document.getElementById('packageProductsContainer').classList.add('hidden');
    document.getElementById('packageProductsEmpty').classList.add('hidden');
    
    const response = await fetch(`../../Controllers/getControllers/getPackageTypeProducts.php?package_type_id=${packageTypeId}`);
    const result = await response.json();
    
    // Hide loading state
    document.getElementById('packageProductsLoading').classList.add('hidden');
    
    if (result.success && result.products && result.products.length > 0) {
      displayPackageTypeProductsList(result.products);
    } else {
      // Show empty state
      document.getElementById('packageProductsEmpty').classList.remove('hidden');
    }
  } catch (error) {
    console.error('Error fetching package type products:', error);
    document.getElementById('packageProductsLoading').classList.add('hidden');
    document.getElementById('packageProductsEmpty').classList.remove('hidden');
  }
}

function displayPackageTypeProductsList(products) {
  const tbody = document.getElementById('packageProductsTableBody');
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
  document.getElementById('packageProductsContainer').classList.remove('hidden');
}

function number_format(number) {
  return parseInt(number).toLocaleString('en-US');
}

async function fetchPackageTypeProducts(packageTypeId) {
  try {
    const response = await fetch(`../../Controllers/getControllers/getPackageTypeProducts.php?package_type_id=${packageTypeId}`);
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
    
    return brand.includes(searchInput) || 
           generic.includes(searchInput) || 
           strength.includes(searchInput) ||
           supplier.includes(searchInput) ||
           category.includes(searchInput);
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
  
  const typeName = type === 'package' ? document.getElementById('viewPackageTypeNameHeader')?.textContent || 'Package Type' : document.getElementById('viewUnitTypeNameHeader')?.textContent || 'Unit Type';
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
      <h2 style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">Products with ${type === 'package' ? 'Package Type' : 'Unit Type'}: ${escapeHtml(typeName)}</h2>
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
  
  const typeName = document.getElementById('viewPackageTypeNameHeader')?.textContent || document.getElementById('viewUnitTypeNameHeader')?.textContent || 'Type';
  const productCount = document.getElementById('viewProductCount')?.textContent || '0';
  
  // Create print window
  const printWindow = window.open('', '_blank');
  const type = document.getElementById('viewPackageTypeNameHeader') ? 'package' : 'unit';
  
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
        <h2>Products with ${type === 'package' ? 'Package Type' : 'Unit Type'}: ${escapeHtml(typeName)}</h2>
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

// Event delegation for view products button (safer than inline onclick)
document.addEventListener('DOMContentLoaded', function() {
  document.addEventListener('click', function(e) {
    if (e.target.closest('.view-package-products-btn')) {
      const button = e.target.closest('.view-package-products-btn');
      const packageId = button.getAttribute('data-package-id');
      const packageName = button.getAttribute('data-package-name');
      
      if (packageId && packageName) {
        if (typeof openViewProductsModal === 'function') {
          openViewProductsModal(parseInt(packageId), packageName);
        } else if (typeof window.openViewProductsModal === 'function') {
          window.openViewProductsModal(parseInt(packageId), packageName);
        } else {
          console.error('openViewProductsModal not available');
          alert('Function not loaded. Please refresh the page.');
        }
      }
    }
  });
});

// Expose functions globally
window.exportToPDF = exportToPDF;
window.printProductsTable = printProductsTable;
window.openAddPackageTypeModal = openAddPackageTypeModal;
window.closeAddPackageTypeModal = closeAddPackageTypeModal;
window.openEditPackageTypeModal = openEditPackageTypeModal;
window.closeEditPackageTypeModal = closeEditPackageTypeModal;
window.openDeletePackageTypeModal = openDeletePackageTypeModal;
window.closeDeletePackageTypeModal = closeDeletePackageTypeModal;
window.filterPackageTypes = filterPackageTypes;
window.filterByStatus = filterByStatus;
window.openViewProductsModal = openViewProductsModal;
window.closeViewProductsModal = closeViewProductsModal;
window.filterProducts = filterProducts;
window.openViewPackageTypeModal = openViewPackageTypeModal;
window.closeViewPackageTypeModal = closeViewPackageTypeModal;
window.printPackageTypeDetails = printPackageTypeDetails;

// Close modal on outside click and ESC key
document.addEventListener('click', function(e) {
  const productsModal = document.getElementById('viewProductsModal');
  const packageTypeModal = document.getElementById('viewPackageTypeModal');
  if (e.target === productsModal) {
    closeViewProductsModal();
  }
  if (e.target === packageTypeModal) {
    closeViewPackageTypeModal();
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const productsModal = document.getElementById('viewProductsModal');
    const packageTypeModal = document.getElementById('viewPackageTypeModal');
    if (productsModal && !productsModal.classList.contains('hidden')) {
      closeViewProductsModal();
    }
    if (packageTypeModal && !packageTypeModal.classList.contains('hidden')) {
      closeViewPackageTypeModal();
    }
  }
});

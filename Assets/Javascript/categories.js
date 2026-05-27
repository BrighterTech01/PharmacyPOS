

let deleteCategoryId = null;

// No AJAX interception; forms submit natively
document.addEventListener('DOMContentLoaded', function() {});

let currentStatusFilter = 'all';

// Filter Categories Function - Handles both desktop table and mobile cards
function filterCategories() {
  const searchInput = document.getElementById('searchInput').value.toLowerCase();
  
  // Filter desktop table rows
  const table = document.getElementById('categoriesTableBody');
  if (table) {
    const rows = table.getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      const categoryName = row.cells[1]?.textContent.toLowerCase() || '';
      const status = row.getAttribute('data-status') || '';
      
      const matchesSearch = categoryName.includes(searchInput);
      const matchesStatus = currentStatusFilter === 'all' || status === currentStatusFilter;
      
      if (matchesSearch && matchesStatus) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    }
  }
  
  // Filter mobile/tablet cards
  const cardsContainer = document.getElementById('categoriesCardsContainer');
  if (cardsContainer) {
    const cards = cardsContainer.querySelectorAll('div.bg-white.rounded-xl');
    cards.forEach(card => {
      const cardText = card.textContent.toLowerCase();
      const status = card.getAttribute('data-status') || '';
      const matchesSearch = cardText.includes(searchInput);
      const matchesStatus = currentStatusFilter === 'all' || status === currentStatusFilter;
      
      if (matchesSearch && matchesStatus) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  }
}

// Filter by Status
function filterByStatus(status) {
  currentStatusFilter = status;
  
  // Update button styles
  document.getElementById('filterAll').className = status === 'all' 
    ? 'px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-semibold text-sm'
    : 'px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold text-sm';
  
  document.getElementById('filterActive').className = status === 'active'
    ? 'px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-semibold text-sm'
    : 'px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold text-sm';
  
  document.getElementById('filterInactive').className = status === 'inactive'
    ? 'px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-semibold text-sm'
    : 'px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold text-sm';
  
  // Apply filter
  filterCategories();
}

function setupFormHandlers() {}

// Modal Functions
function openAddCategoryModal() {
  document.getElementById('addCategoryModal').classList.remove('hidden');
  document.getElementById('addCategoryModal').classList.add('flex');
  document.getElementById('addCategoryForm').reset();
}

function closeAddCategoryModal() {
  document.getElementById('addCategoryModal').classList.add('hidden');
  document.getElementById('addCategoryModal').classList.remove('flex');
}

function openEditCategoryModal(category) {
  document.getElementById('editCategoryId').value = category.category_id;
  document.getElementById('editCategoryName').value = category.category_name;
  
  document.getElementById('editCategoryModal').classList.remove('hidden');
  document.getElementById('editCategoryModal').classList.add('flex');
}

function closeEditCategoryModal() {
  document.getElementById('editCategoryModal').classList.add('hidden');
  document.getElementById('editCategoryModal').classList.remove('flex');
}

function openDeleteCategoryModal(categoryId, categoryName) {
  deleteCategoryId = categoryId;
  document.getElementById('deleteCategoryName').textContent = categoryName;
  var hidden = document.getElementById('deleteCategoryIdHidden');
  if (hidden) hidden.value = categoryId;
  document.getElementById('deleteCategoryModal').classList.remove('hidden');
  document.getElementById('deleteCategoryModal').classList.add('flex');
}

function closeDeleteCategoryModal() {
  deleteCategoryId = null;
  document.getElementById('deleteCategoryModal').classList.add('hidden');
  document.getElementById('deleteCategoryModal').classList.remove('flex');
}

// View Category Details Modal
function openViewCategoryModal(category) {
  const modal = document.getElementById('viewCategoryModal');
  if (!modal) {
    console.error('View category modal not found');
    return;
  }
  
  // Populate modal with category data
  document.getElementById('viewCategoryId').textContent = '#' + (category.category_id || 'N/A');
  document.getElementById('viewCategoryName').textContent = category.category_name || 'N/A';
  
  // Status badge
  const statusEl = document.getElementById('viewCategoryStatus');
  const isActive = category.status === 'Active' || category.status === 'active' || (parseInt(category.product_count) || 0) > 0;
  if (isActive) {
    statusEl.innerHTML = '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>';
  } else {
    statusEl.innerHTML = '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">Inactive</span>';
  }
  
  // Product count
  const productCount = parseInt(category.product_count) || 0;
  document.getElementById('viewCategoryProducts').textContent = productCount.toLocaleString() + ' product(s)';
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  
  // Fetch and display products using this category
  fetchCategoryProductsList(category.category_id);
}

// Immediately expose to window for inline handlers (if any)
window.openViewCategoryModal = openViewCategoryModal;

function closeViewCategoryModal() {
  const modal = document.getElementById('viewCategoryModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

// Print Category Details Function
function printCategoryDetails() {
  const element = document.getElementById('printableCategoryContent');
  if (!element) {
    alert('No content to print');
    return;
  }
  
  const categoryId = document.getElementById('viewCategoryId')?.textContent || 'N/A';
  const categoryName = document.getElementById('viewCategoryName')?.textContent || 'N/A';
  
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('Please allow popups to print this document');
    return;
  }
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Category Details - ${escapeHtml(categoryName)}</title>
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
        <h2>Category Details</h2>
        <p>Category ID: ${escapeHtml(categoryId)} | Name: ${escapeHtml(categoryName)}</p>
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

// View Products Modal Functions
let currentCategoryId = null;
let allProducts = [];

function openViewProductsModal(categoryId, categoryName) {
  currentCategoryId = categoryId;
  document.getElementById('viewCategoryNameHeader').textContent = categoryName;
  document.getElementById('viewProductsModal').classList.remove('hidden');
  document.getElementById('viewProductsModal').classList.add('flex');
  
  // Reset search
  document.getElementById('productSearchInput').value = '';
  
  // Show loading, hide table
  document.getElementById('productsLoading').classList.remove('hidden');
  document.getElementById('productsTableContainer').classList.add('hidden');
  document.getElementById('productsEmpty').classList.add('hidden');
  
  // Fetch products
  fetchCategoryProducts(categoryId);
}

function closeViewProductsModal() {
  currentCategoryId = null;
  allProducts = [];
  document.getElementById('viewProductsModal').classList.add('hidden');
  document.getElementById('viewProductsModal').classList.remove('flex');
  document.getElementById('productsTableBody').innerHTML = '';
}

// Fetch and display products list in the detail modal
async function fetchCategoryProductsList(categoryId) {
  try {
    // Show loading state
    document.getElementById('categoryProductsLoading').classList.remove('hidden');
    document.getElementById('categoryProductsContainer').classList.add('hidden');
    document.getElementById('categoryProductsEmpty').classList.add('hidden');
    
    const response = await fetch(`../../Controllers/getControllers/getCategoryProducts.php?category_id=${categoryId}`);
    const result = await response.json();
    
    // Hide loading state
    document.getElementById('categoryProductsLoading').classList.add('hidden');
    
    if (result.success && result.products && result.products.length > 0) {
      displayCategoryProductsList(result.products);
    } else {
      // Show empty state
      document.getElementById('categoryProductsEmpty').classList.remove('hidden');
    }
  } catch (error) {
    console.error('Error fetching category products:', error);
    document.getElementById('categoryProductsLoading').classList.add('hidden');
    document.getElementById('categoryProductsEmpty').classList.remove('hidden');
  }
}

function displayCategoryProductsList(products) {
  const tbody = document.getElementById('categoryProductsTableBody');
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
        <div class="text-sm text-gray-700">${escapeHtml(product.supplier_name || 'N/A')}</div>
      </td>
    `;
    
    tbody.appendChild(row);
  });
  
  // Show the container
  document.getElementById('categoryProductsContainer').classList.remove('hidden');
}

function number_format(number) {
  return parseInt(number).toLocaleString('en-US');
}

async function fetchCategoryProducts(categoryId) {
  try {
    const response = await fetch(`../../Controllers/getControllers/getCategoryProducts.php?category_id=${categoryId}`);
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
    const expiryClass = product.nearest_expiry && new Date(product.nearest_expiry) < new Date(Date.now() + 30*24*60*60*1000) ? 'text-red-600 font-semibold' : 'text-gray-700';
    
    const stockClass = product.total_units_available > 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold';
    const stockText = product.total_units_available > 0 ? product.total_units_available.toLocaleString() : 'Out of Stock';
    
    row.innerHTML = `
      <td class="px-4 py-3 whitespace-nowrap">
        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
          #${product.product_id}
        </span>
      </td>
      <td class="px-4 py-3 whitespace-nowrap">
        <div class="font-semibold text-gray-900">${escapeHtml(product.brand_name || 'N/A')}</div>
      </td>
      <td class="px-4 py-3 whitespace-nowrap">
        <div class="text-sm text-gray-600">${escapeHtml(product.generic_name || 'N/A')}</div>
      </td>
      <td class="px-4 py-3 whitespace-nowrap">
        <div class="text-sm text-gray-700">${escapeHtml(product.strength || 'N/A')}</div>
      </td>
      <td class="px-4 py-3 whitespace-nowrap">
        <div class="text-sm text-gray-700">${escapeHtml(product.base_unit || 'N/A')}</div>
      </td>
      <td class="px-4 py-3 whitespace-nowrap text-right">
        <span class="${stockClass}">${stockText}</span>
      </td>
      <td class="px-4 py-3 whitespace-nowrap text-right">
        <span class="font-semibold text-gray-900">₱${product.latest_unit_price.toFixed(2)}</span>
      </td>
      <td class="px-4 py-3 whitespace-nowrap">
        <div class="text-sm text-gray-700">${escapeHtml(product.supplier_name || 'N/A')}</div>
      </td>
      <td class="px-4 py-3 whitespace-nowrap">
        <span class="${expiryClass} text-sm">${expiryDate}</span>
      </td>
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
    
    return brand.includes(searchInput) || 
           generic.includes(searchInput) || 
           strength.includes(searchInput) ||
           supplier.includes(searchInput);
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
  const productsModal = document.getElementById('viewProductsModal');
  const categoryModal = document.getElementById('viewCategoryModal');
  if (e.target === productsModal) {
    closeViewProductsModal();
  }
  if (e.target === categoryModal) {
    closeViewCategoryModal();
  }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const productsModal = document.getElementById('viewProductsModal');
    const categoryModal = document.getElementById('viewCategoryModal');
    if (productsModal && !productsModal.classList.contains('hidden')) {
      closeViewProductsModal();
    }
    if (categoryModal && !categoryModal.classList.contains('hidden')) {
      closeViewCategoryModal();
    }
  }
});

// Expose functions globally
window.openAddCategoryModal = openAddCategoryModal;
window.closeAddCategoryModal = closeAddCategoryModal;
window.openEditCategoryModal = openEditCategoryModal;
window.closeEditCategoryModal = closeEditCategoryModal;
window.openDeleteCategoryModal = openDeleteCategoryModal;
window.closeDeleteCategoryModal = closeDeleteCategoryModal;
window.openViewCategoryModal = openViewCategoryModal;
window.closeViewCategoryModal = closeViewCategoryModal;
window.printCategoryDetails = printCategoryDetails;
window.filterCategories = filterCategories;
window.filterByStatus = filterByStatus;
window.openViewProductsModal = openViewProductsModal;
window.closeViewProductsModal = closeViewProductsModal;
window.filterProducts = filterProducts;



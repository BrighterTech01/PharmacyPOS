

let deleteProductId = null;

// No Delete Permission Function - used by restricted actions
function noDeletePermission() {
  if (typeof showErrorModal === 'function') {
    showErrorModal('Permission Denied', 'You do not have permission to delete items.');
  } else {
    alert('You do not have permission to delete items.');
  }
}

// Clear Product Filters Function - Define early for inline handlers
function clearProductFilters() {
  const searchInput = document.getElementById('searchInput');
  const categoryFilter = document.getElementById('categoryFilter');
  const supplierFilter = document.getElementById('supplierFilter');
  const statusFilter = document.getElementById('statusFilter');
  
  if (searchInput) searchInput.value = '';
  if (categoryFilter) categoryFilter.value = '';
  if (supplierFilter) supplierFilter.value = '';
  if (statusFilter) statusFilter.value = '';
  
  // Re-apply filters (which will show all rows now)
  if (typeof filterProducts === 'function') {
    filterProducts();
  }
}

// Expose immediately for inline handlers
window.noDeletePermission = (typeof noDeletePermission === 'function')
  ? noDeletePermission
  : function() { alert('You do not have permission to delete items.'); };
window.clearProductFilters = clearProductFilters;

// Load form handlers on page load
document.addEventListener('DOMContentLoaded', function() {
  // Debug: Check if modal elements exist
  console.log('Add Product Modal:', document.getElementById('addProductModal'));
  console.log('Add Product Form:', document.getElementById('addProductForm'));
  console.log('Edit Product Modal:', document.getElementById('editProductModal'));
  console.log('Edit Product Form:', document.getElementById('editProductForm'));
  console.log('Delete Product Modal:', document.getElementById('deleteProductModal'));
  
  setupFormHandlers();
  
  // Apply filters on page load if any filters are set
  const searchInput = document.getElementById('searchInput');
  const categoryFilter = document.getElementById('categoryFilter');
  const supplierFilter = document.getElementById('supplierFilter');
  const statusFilter = document.getElementById('statusFilter');
  
  if (searchInput?.value || categoryFilter?.value || supplierFilter?.value || statusFilter?.value) {
    // Small delay to ensure table is fully rendered
    setTimeout(() => {
      filterProducts();
    }, 100);
  }
  
  // Event delegation for view product button (safer than inline onclick)
  document.addEventListener('click', function(e) {
    if (e.target.closest('.view-product-btn')) {
      const button = e.target.closest('.view-product-btn');
      const productData = button.getAttribute('data-product');
      
      if (productData) {
        try {
          const product = JSON.parse(productData);
          if (typeof openViewProductModal === 'function') {
            openViewProductModal(product);
          } else if (typeof window.openViewProductModal === 'function') {
            window.openViewProductModal(product);
          } else {
            console.error('openViewProductModal not available');
            alert('Function not loaded. Please refresh the page.');
          }
        } catch (error) {
          console.error('Error parsing product data:', error);
          alert('Error loading product details. Please try again.');
        }
      }
    }
  });
});

// Filter Products Function
function filterProducts() {
  const searchInput = document.getElementById('searchInput')?.value.trim().toLowerCase() || '';
  const categoryFilter = document.getElementById('categoryFilter')?.value || '';
  const supplierFilter = document.getElementById('supplierFilter')?.value || '';
  const statusFilter = document.getElementById('statusFilter')?.value || '';
  const table = document.getElementById('productsTableBody');
  
  if (!table) {
    console.warn('Products table not found');
    return;
  }
  
  const rows = table.getElementsByTagName('tr');
  let visibleCount = 0;
  
  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    
    // Skip if row doesn't have cells (header row or empty row)
    if (!row.cells || row.cells.length === 0) {
      continue;
    }
    
    // Get text content from cells (column 1 = product details, column 2 = category, column 3 = supplier)
    const productDetailsCell = row.cells[1];
    const categoryCell = row.cells[2];
    const supplierCell = row.cells[3];
    
    // Extract all text from cells (textContent gets all nested text)
    const productText = productDetailsCell ? productDetailsCell.textContent.trim().toLowerCase() : '';
    const categoryText = categoryCell ? categoryCell.textContent.trim().toLowerCase() : '';
    const supplierText = supplierCell ? supplierCell.textContent.trim().toLowerCase() : '';
    
    // Get data attributes (more reliable for filtering, these match the option values)
    let rowCategory = (row.getAttribute('data-category') || '').trim();
    let rowSupplier = (row.getAttribute('data-supplier') || '').trim();
    const rowStatus = (row.getAttribute('data-status') || '').trim();
    
    // Normalize "N/A" values to empty string for comparison
    if (rowCategory === 'N/A' || rowCategory === 'n/a') rowCategory = '';
    if (rowSupplier === 'N/A' || rowSupplier === 'n/a') rowSupplier = '';
    
    rowCategory = rowCategory.toLowerCase();
    rowSupplier = rowSupplier.toLowerCase();
    
    // Search filter - check if search term is in product details, category, or supplier
    const matchesSearch = !searchInput || 
      productText.includes(searchInput) || 
      categoryText.includes(searchInput) || 
      supplierText.includes(searchInput) ||
      rowCategory.includes(searchInput) ||
      rowSupplier.includes(searchInput);
    
    // Category filter - match against data attribute (preferred) or cell text
    const categoryFilterValue = categoryFilter.trim().toLowerCase();
    let matchesCategory = true;
    if (categoryFilter) {
      // Normalize whitespace for comparison
      const normalizedRowCategory = rowCategory.replace(/\s+/g, ' ').trim();
      const normalizedCategoryText = categoryText.replace(/\s+/g, ' ').trim();
      const normalizedFilterValue = categoryFilterValue.replace(/\s+/g, ' ').trim();
      
      matchesCategory = 
        normalizedRowCategory === normalizedFilterValue ||
        normalizedCategoryText === normalizedFilterValue ||
        (normalizedRowCategory && normalizedRowCategory.includes(normalizedFilterValue)) ||
        (normalizedCategoryText && normalizedCategoryText.includes(normalizedFilterValue));
    }
    
    // Supplier filter - match against data attribute (preferred) or cell text
    const supplierFilterValue = supplierFilter.trim().toLowerCase();
    let matchesSupplier = true;
    if (supplierFilter) {
      // Normalize whitespace for comparison
      const normalizedRowSupplier = rowSupplier.replace(/\s+/g, ' ').trim();
      const normalizedSupplierText = supplierText.replace(/\s+/g, ' ').trim();
      const normalizedFilterValue = supplierFilterValue.replace(/\s+/g, ' ').trim();
      
      matchesSupplier = 
        normalizedRowSupplier === normalizedFilterValue ||
        normalizedSupplierText === normalizedFilterValue ||
        (normalizedRowSupplier && normalizedRowSupplier.includes(normalizedFilterValue)) ||
        (normalizedSupplierText && normalizedSupplierText.includes(normalizedFilterValue));
    }
    
    // Status filter - exact match with data-status attribute
    const matchesStatus = !statusFilter || rowStatus === statusFilter.trim();
    
    // Show row if all filters match
    if (matchesSearch && matchesCategory && matchesSupplier && matchesStatus) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  }
  
  // Optional: Log filtered count for debugging
  // console.log(`Filtered: ${visibleCount} of ${rows.length} products visible`);
}

// No AJAX interception; forms submit natively
function setupFormHandlers() {
  // Setup searchable category dropdowns
  setupCategorySearch('addCategorySearch', 'addCategorySelect', 'addCategory');
  setupCategorySearch('editCategorySearch', 'editCategorySelect', 'editCategory');
  
  // Setup searchable unit dropdowns
  setupCategorySearch('addUnitSearch', 'addUnitSelect', 'addUnit');
  setupCategorySearch('editUnitSearch', 'editUnitSelect', 'editUnit');
  
  // Setup searchable supplier dropdowns
  setupCategorySearch('addSupplierSearch', 'addSupplierSelect', 'addSupplier');
  setupCategorySearch('editSupplierSearch', 'editSupplierSelect', 'editSupplier');
  
  // Setup searchable package type dropdowns
  setupCategorySearch('addPackageTypeSearch', 'addPackageTypeSelect', 'addPackageType');
  setupCategorySearch('editPackageTypeSearch', 'editPackageTypeSelect', 'editPackageType');
}

// Setup searchable category dropdown
function setupCategorySearch(searchInputId, selectId, hiddenInputId) {
  const searchInput = document.getElementById(searchInputId);
  const categorySelect = document.getElementById(selectId);
  const hiddenInput = document.getElementById(hiddenInputId);

  if (!searchInput || !categorySelect || !hiddenInput) {
    return; // Elements not found, skip setup
  }

  let isSelectOpen = false;
  let hideTimeout = null;

  // Show/hide select based on input focus
  searchInput.addEventListener('focus', function() {
    clearTimeout(hideTimeout);
    categorySelect.classList.remove('hidden');
    categorySelect.style.display = 'block';
    isSelectOpen = true;
    filterCategoryOptions();
  });

  searchInput.addEventListener('blur', function(e) {
    clearTimeout(hideTimeout);
    const relatedTarget = e.relatedTarget;
    if (relatedTarget === categorySelect || (relatedTarget && categorySelect.contains(relatedTarget))) {
      return; // Don't hide if clicking on select
    }
    hideTimeout = setTimeout(function() {
      if (document.activeElement !== categorySelect && !isSelectOpen) {
        categorySelect.classList.add('hidden');
        categorySelect.style.display = 'none';
      }
    }, 250);
  });

  // Filter options as user types
  searchInput.addEventListener('input', function() {
    clearTimeout(hideTimeout);
    categorySelect.classList.remove('hidden');
    categorySelect.style.display = 'block';
    isSelectOpen = true;
    filterCategoryOptions();
  });

  // Handle select focus
  categorySelect.addEventListener('focus', function() {
    clearTimeout(hideTimeout);
    isSelectOpen = true;
  });

  // Prevent blur from closing when clicking on select
  categorySelect.addEventListener('mousedown', function(e) {
    e.stopPropagation();
    clearTimeout(hideTimeout);
    isSelectOpen = true;
  });

  // Handle option selection
  categorySelect.addEventListener('change', function() {
    clearTimeout(hideTimeout);
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption && selectedOption.value && selectedOption.value !== '') {
      hiddenInput.value = selectedOption.value;
      const displayName = selectedOption.getAttribute('data-name') || selectedOption.text.trim();
      searchInput.value = displayName;
      isSelectOpen = false;
      categorySelect.classList.add('hidden');
      categorySelect.style.display = 'none';
      setTimeout(function() {
        searchInput.focus();
      }, 50);
    }
  });

  // Handle double-click for better compatibility
  categorySelect.addEventListener('dblclick', function(e) {
    e.stopPropagation();
    clearTimeout(hideTimeout);
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption && selectedOption.value && selectedOption.value !== '') {
      hiddenInput.value = selectedOption.value;
      const displayName = selectedOption.getAttribute('data-name') || selectedOption.text.trim();
      searchInput.value = displayName;
      isSelectOpen = false;
      categorySelect.classList.add('hidden');
      categorySelect.style.display = 'none';
      searchInput.focus();
    }
  });

  // Handle click on the select element itself
  categorySelect.addEventListener('click', function(e) {
    e.stopPropagation();
    clearTimeout(hideTimeout);
  });

  categorySelect.addEventListener('blur', function() {
    isSelectOpen = false;
    setTimeout(function() {
      if (document.activeElement !== searchInput) {
        categorySelect.classList.add('hidden');
        categorySelect.style.display = 'none';
      }
    }, 200);
  });

  // Prevent closing when clicking inside select
  categorySelect.addEventListener('mouseenter', function() {
    clearTimeout(hideTimeout);
  });

  // Filter function
  function filterCategoryOptions() {
    const filter = searchInput.value.toLowerCase().trim();
    const options = categorySelect.options;
    
    let visibleCount = 0;
    for (let i = 0; i < options.length; i++) {
      const option = options[i];
      
      // Skip the first empty option
      if (i === 0 && option.value === '') {
        option.style.display = 'none';
        option.disabled = true;
        continue;
      }
      
      const searchText = option.getAttribute('data-search') || option.text.toLowerCase().trim();
      
      if (filter === '' || searchText.includes(filter)) {
        // Show the option
        option.style.removeProperty('display');
        option.removeAttribute('disabled');
        option.disabled = false;
        visibleCount++;
      } else {
        // Hide the option
        option.style.display = 'none';
        option.setAttribute('disabled', 'disabled');
        option.disabled = true;
      }
    }
    
    // Hide select if no visible options
    if (visibleCount === 0) {
      categorySelect.classList.add('hidden');
      categorySelect.style.display = 'none';
      isSelectOpen = false;
      return;
    }
    
    // Show select if there are visible options
    categorySelect.classList.remove('hidden');
    categorySelect.style.display = 'block';
    isSelectOpen = true;
    
    // Adjust select size based on visible options (max 7, min 2)
    const maxSize = Math.min(Math.max(visibleCount, 2), 7);
    categorySelect.size = maxSize;
  }
}

// Modal Functions
function openAddProductModal() {
  const modal = document.getElementById('addProductModal');
  const form = document.getElementById('addProductForm');
  
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  } else {
    console.error('Add product modal not found');
    return;
  }
  
  if (form) {
    form.reset();
  }
}

function closeAddProductModal() {
  const modal = document.getElementById('addProductModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function openEditProductModal(product) {
  const modal = document.getElementById('editProductModal');
  if (!modal) {
    console.error('Edit product modal not found');
    return;
  }
  
  // Basic product info
  const productIdEl = document.getElementById('editProductId');
  if (productIdEl) productIdEl.value = product.product_id || '';
  
  const brandNameEl = document.getElementById('editBrandName');
  if (brandNameEl) brandNameEl.value = product.brand_name || '';
  
  const genericNameEl = document.getElementById('editGenericName');
  if (genericNameEl) genericNameEl.value = product.generic_name || '';
  
  const strengthEl = document.getElementById('editStrength');
  if (strengthEl) strengthEl.value = product.strength || '';
  
  const barcodeEl = document.getElementById('editBarcode');
  if (barcodeEl) barcodeEl.value = product.barcode || '';
  
  // Category - set both hidden input and search input
  const categoryEl = document.getElementById('editCategory');
  const categorySearchEl = document.getElementById('editCategorySearch');
  const categorySelectEl = document.getElementById('editCategorySelect');
  
  if (categoryEl) {
    categoryEl.value = product.category_id || '';
    
    // Find and set the category name in the search input
    if (categorySelectEl && categorySearchEl && product.category_id) {
      const option = categorySelectEl.querySelector(`option[value="${product.category_id}"]`);
      if (option) {
        const categoryName = option.getAttribute('data-name') || option.text.trim();
        categorySearchEl.value = categoryName;
      }
    } else if (categorySearchEl) {
      categorySearchEl.value = '';
    }
  }
  
  // Unit type - set both hidden input and search input
  const unitEl = document.getElementById('editUnit');
  const unitSearchEl = document.getElementById('editUnitSearch');
  const unitSelectEl = document.getElementById('editUnitSelect');
  
  if (unitEl) {
    unitEl.value = product.base_unit_type_id || '';
    
    // Find and set the unit name in the search input
    if (unitSelectEl && unitSearchEl && product.base_unit_type_id) {
      const option = unitSelectEl.querySelector(`option[value="${product.base_unit_type_id}"]`);
      if (option) {
        const unitName = option.getAttribute('data-name') || option.text.trim();
        unitSearchEl.value = unitName;
      }
    } else if (unitSearchEl) {
      unitSearchEl.value = '';
    }
  }
  
  // Supplier - set both hidden input and search input
  const supplierEl = document.getElementById('editSupplier');
  const supplierSearchEl = document.getElementById('editSupplierSearch');
  const supplierSelectEl = document.getElementById('editSupplierSelect');
  
  if (supplierEl) {
    supplierEl.value = product.supplier_id || '';
    
    // Find and set the supplier name in the search input
    if (supplierSelectEl && supplierSearchEl && product.supplier_id) {
      const option = supplierSelectEl.querySelector(`option[value="${product.supplier_id}"]`);
      if (option) {
        const supplierName = option.getAttribute('data-name') || option.text.trim();
        supplierSearchEl.value = supplierName;
      }
    } else if (supplierSearchEl) {
      supplierSearchEl.value = '';
    }
  }
  
  // Package type - set both hidden input and search input (can be null/empty)
  const pkgEl = document.getElementById('editPackageType');
  const pkgSearchEl = document.getElementById('editPackageTypeSearch');
  const pkgSelectEl = document.getElementById('editPackageTypeSelect');
  
  if (pkgEl) {
    const packageTypeId = (product.package_type_id && product.package_type_id !== '0') ? product.package_type_id : '';
    pkgEl.value = packageTypeId;
    
    // Find and set the package type name in the search input
    if (pkgSelectEl && pkgSearchEl && packageTypeId) {
      const option = pkgSelectEl.querySelector(`option[value="${packageTypeId}"]`);
      if (option) {
        const packageName = option.getAttribute('data-name') || option.text.trim();
        pkgSearchEl.value = packageName;
      }
    } else if (pkgSearchEl) {
      pkgSearchEl.value = '';
    }
  }
  
  // Thresholds
  const productThresholdEl = document.getElementById('editProductThreshold');
  if (productThresholdEl) {
    const productThreshold = parseInt(product.product_threshold) || 0;
    productThresholdEl.value = productThreshold;
  }
  
  const expireThresholdEl = document.getElementById('editExpireThreshold');
  if (expireThresholdEl) {
    const expireThreshold = parseInt(product.expire_threshold) || 0;
    expireThresholdEl.value = expireThreshold;
  }
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeEditProductModal() {
  const modal = document.getElementById('editProductModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function openDeleteProductModal(productId, productName) {
  const modal = document.getElementById('deleteProductModal');
  if (!modal) {
    console.error('Delete product modal not found');
    return;
  }
  
  deleteProductId = productId;
  document.getElementById('deleteProductName').textContent = productName;
  var hidden = document.getElementById('deleteProductIdHidden');
  if (hidden) hidden.value = productId;
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeDeleteProductModal() {
  const modal = document.getElementById('deleteProductModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
  deleteProductId = null;
}

// View Product Details Modal
function openViewProductModal(product) {
  const modal = document.getElementById('viewProductModal');
  if (!modal) {
    console.error('View product modal not found');
    return;
  }
  
  // Populate modal with product data
  document.getElementById('viewProductId').textContent = '#' + (product.product_id || 'N/A');
  document.getElementById('viewBrandName').textContent = product.brand_name || 'N/A';
  document.getElementById('viewGenericName').textContent = product.generic_name || 'N/A';
  document.getElementById('viewStrength').textContent = product.strength || 'N/A';
  document.getElementById('viewBarcode').textContent = product.barcode || 'N/A';
  document.getElementById('viewCategory').textContent = product.category_name || 'N/A';
  document.getElementById('viewUnitType').textContent = product.base_unit || 'N/A';
  document.getElementById('viewSupplier').textContent = product.supplier_name || 'N/A';
  document.getElementById('viewPackageType').textContent = product.package_type_name || 'N/A';
  
  // Product Threshold (Low Stock)
  const productThresholdEl = document.getElementById('viewProductThreshold');
  if (productThresholdEl) {
    const productThreshold = parseFloat(product.product_threshold) || 0;
    if (productThreshold > 0) {
      productThresholdEl.textContent = productThreshold.toLocaleString('en-US', { maximumFractionDigits: 0 }) + ' units';
    } else {
      productThresholdEl.textContent = 'Not set';
    }
  }
  
  // Expire Threshold
  const expireThresholdEl = document.getElementById('viewExpireThreshold');
  if (expireThresholdEl) {
    const expireThreshold = parseFloat(product.expire_threshold) || 0;
    if (expireThreshold > 0) {
      expireThresholdEl.textContent = expireThreshold.toLocaleString('en-US', { maximumFractionDigits: 0 }) + ' days';
    } else {
      expireThresholdEl.textContent = 'Not set';
    }
  }
  
  // Status with badge
  const statusEl = document.getElementById('viewStatus');
  const isActive = product.is_active === 1 || product.is_active === '1' || product.is_active === true;
  if (isActive) {
    statusEl.innerHTML = '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>';
  } else {
    statusEl.innerHTML = '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">Inactive</span>';
  }
  
  // Created At - format date
  const createdAt = product.created_at || product.created_at;
  if (createdAt && createdAt !== '0000-00-00 00:00:00' && createdAt !== '') {
    try {
      const date = new Date(createdAt);
      const formattedDate = date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
      document.getElementById('viewCreatedAt').textContent = formattedDate;
    } catch (e) {
      document.getElementById('viewCreatedAt').textContent = createdAt;
    }
  } else {
    document.getElementById('viewCreatedAt').textContent = 'N/A';
  }
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

// Immediately expose to window for inline handlers (if any)
window.openViewProductModal = openViewProductModal;

function closeViewProductModal() {
  const modal = document.getElementById('viewProductModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

// Immediately expose to window
window.closeViewProductModal = closeViewProductModal;

// Print Product Details Function
function printProductDetails() {
  const element = document.getElementById('printableProductContent');
  if (!element) {
    alert('No content to print');
    return;
  }
  
  const productId = document.getElementById('viewProductId')?.textContent || 'N/A';
  const brandName = document.getElementById('viewBrandName')?.textContent || 'N/A';
  
  // Create print window
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('Please allow popups to print this document');
    return;
  }
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Product Details - ${escapeHtml(brandName)}</title>
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
        <h2>Product Details</h2>
        <p>Product ID: ${escapeHtml(productId)} | Brand: ${escapeHtml(brandName)}</p>
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

// Escape HTML function for print
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

// Close modal on outside click
document.addEventListener('click', function(e) {
  const modal = document.getElementById('viewProductModal');
  if (e.target === modal) {
    closeViewProductModal();
  }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('viewProductModal');
    if (modal && !modal.classList.contains('hidden')) {
      closeViewProductModal();
    }
  }
});

// Deletion handled via POST form

// Expose all modal functions globally
window.openAddProductModal = openAddProductModal;
window.closeAddProductModal = closeAddProductModal;
window.openEditProductModal = openEditProductModal;
window.closeEditProductModal = closeEditProductModal;
window.openDeleteProductModal = openDeleteProductModal;
window.closeDeleteProductModal = closeDeleteProductModal;
window.openViewProductModal = openViewProductModal;
window.closeViewProductModal = closeViewProductModal;
window.printProductDetails = printProductDetails;
window.filterProducts = filterProducts;
window.noDeletePermission = (typeof noDeletePermission === 'function')
  ? noDeletePermission
  : function() { alert('You do not have permission to delete items.'); };


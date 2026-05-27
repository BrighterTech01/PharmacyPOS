


// No AJAX interception; forms submit natively
document.addEventListener('DOMContentLoaded', function() {
  // Hook batch change to auto-fill units per package
  function wireBatchAutoUnits(selectId, unitsInputId, packagesInputId) {
    var sel = document.getElementById(selectId);
    var unitsEl = document.getElementById(unitsInputId);
    var pkgEl = document.getElementById(packagesInputId);
    if (!sel || !unitsEl) return;
    sel.addEventListener('change', function() {
      var opt = sel.options[sel.selectedIndex];
      if (!opt) return;
      var per = opt.getAttribute('data-units-per-package');
      var defPkgs = opt.getAttribute('data-quantity-packages');
      if (per !== null) unitsEl.value = per || 0;
      if (pkgEl && defPkgs !== null) pkgEl.value = defPkgs || 0;
    });
    // Initial sync on load (if a batch preselected)
    var initOpt = sel.options[sel.selectedIndex];
    if (initOpt) {
      var per0 = initOpt.getAttribute('data-units-per-package');
      var defPkgs0 = initOpt.getAttribute('data-quantity-packages');
      if (per0 !== null) unitsEl.value = per0 || 0;
      if (pkgEl && defPkgs0 !== null && (pkgEl.value === '' || pkgEl.value === '0')) pkgEl.value = defPkgs0 || 0;
    }
  }
  wireBatchAutoUnits('addBatch', 'addQuantityUnits', 'addQuantityPackages');
  wireBatchAutoUnits('editBatch', 'editQuantityUnits', 'editQuantityPackages');
});
// Format number inputs to 2 decimal places on blur
document.addEventListener('blur', function(e){
  const decimalFields = [
    'addQuantityPackages', 'addQuantityUnits', 'addCostPerPackage', 'addCostPerUnit', 'addTotalCost',
    'addSellingPricePackage', 'addSellingPriceUnit',
    'editQuantityPackages', 'editQuantityUnits', 'editCostPerPackage', 'editCostPerUnit', 'editTotalCost',
    'editSellingPricePackage', 'editSellingPriceUnit'
  ];
  
  if (decimalFields.includes(e.target.id) && e.target.type === 'number' && !e.target.readOnly) {
    const value = parseFloat(e.target.value);
    if (!isNaN(value)) {
      e.target.value = value.toFixed(2);
    } else if (e.target.value === '' || e.target.value === null) {
      e.target.value = '0.00';
    }
  }
}, true);

// Auto-calc total cost and cost_per_unit on add/edit
document.addEventListener('input', function(e){
  // Calculate total cost: quantity_packages * cost_per_package
  const idPairs = [
    { qty: 'addQuantityPackages', cost: 'addCostPerPackage', total: 'addTotalCost' },
    { qty: 'editQuantityPackages', cost: 'editCostPerPackage', total: 'editTotalCost' }
  ];
  idPairs.forEach(p => {
    const qtyEl = document.getElementById(p.qty);
    const costEl = document.getElementById(p.cost);
    const totalEl = document.getElementById(p.total);
    if (!qtyEl || !costEl || !totalEl) return;
    const qty = parseFloat(qtyEl.value || '0');
    const cost = parseFloat(costEl.value || '0');
    const tot = isFinite(qty * cost) ? ((qty * cost) > 0 ? (qty * cost).toFixed(2) : '0.00') : '0.00';
    totalEl.value = tot;
  });
  
  // Calculate cost_per_unit: cost_per_package / units_per_package
  const costPerUnitPairs = [
    { costPkg: 'addCostPerPackage', units: 'addQuantityUnits', costUnit: 'addCostPerUnit' },
    { costPkg: 'editCostPerPackage', units: 'editQuantityUnits', costUnit: 'editCostPerUnit' }
  ];
  costPerUnitPairs.forEach(p => {
    const costPkgEl = document.getElementById(p.costPkg);
    const unitsEl = document.getElementById(p.units);
    const costUnitEl = document.getElementById(p.costUnit);
    if (!costPkgEl || !unitsEl || !costUnitEl) return;
    const costPkg = parseFloat(costPkgEl.value || '0');
    const units = parseFloat(unitsEl.value || '0');
    if (units > 0 && isFinite(costPkg / units)) {
      const costUnit = costPkg / units;
      costUnitEl.value = costUnit > 0 ? costUnit.toFixed(2) : '0.00';
    } else {
      costUnitEl.value = '0.00';
    }
  });
  
  // Calculate total units: quantity_packages * quantity_units
  const totalUnitsPairs = [
    { qtyPkg: 'addQuantityPackages', qtyUnits: 'addQuantityUnits', total: 'addTotalUnits' }
  ];
  totalUnitsPairs.forEach(p => {
    const qtyPkgEl = document.getElementById(p.qtyPkg);
    const qtyUnitsEl = document.getElementById(p.qtyUnits);
    const totalEl = document.getElementById(p.total);
    if (!qtyPkgEl || !qtyUnitsEl || !totalEl) return;
    const qtyPkg = parseFloat(qtyPkgEl.value || '0');
    const qtyUnits = parseFloat(qtyUnitsEl.value || '0');
    const total = isFinite(qtyPkg * qtyUnits) ? ((qtyPkg * qtyUnits) > 0 ? (qtyPkg * qtyUnits).toFixed(2) : '0.00') : '0.00';
    totalEl.value = total;
  });
});

// Filter Deliveries Function - Handles both desktop table and mobile cards
function filterDeliveries() {
  const searchEl = document.getElementById('searchInput');
  const supplierFilterEl = document.getElementById('supplierFilter');
  const userFilterEl = document.getElementById('userFilter');

  const searchValue = (searchEl?.value || '').trim().toLowerCase();
  const supplierFilter = supplierFilterEl ? supplierFilterEl.value : '';
  const userFilter = userFilterEl ? userFilterEl.value : '';

  // Filter desktop table rows
  const table = document.getElementById('deliveriesTableBody');
  if (table) {
    const rows = table.querySelectorAll('tr');
    rows.forEach(row => {
      const dataSupplier = row.getAttribute('data-supplier') || '';
      const dataUser = row.getAttribute('data-user') || '';
      const productKeywords = row.dataset.product || '';
      const deliveryKeywords = row.dataset.deliveryDate || '';

      const matchesSearch =
        !searchValue ||
        productKeywords.includes(searchValue) ||
        deliveryKeywords.includes(searchValue);

      const matchesSupplier = !supplierFilter || dataSupplier === supplierFilter;
      const matchesUser = !userFilter || dataUser === userFilter;

      row.style.display = matchesSearch && matchesSupplier && matchesUser ? '' : 'none';
    });
  }

  // Filter mobile/tablet cards
  const cardsContainer = document.getElementById('deliveriesCardsContainer');
  if (cardsContainer) {
    const cards = cardsContainer.querySelectorAll('div.bg-white.rounded-xl');
    cards.forEach(card => {
      const cardText = card.textContent.toLowerCase();
      const matchesSearch = !searchValue || cardText.includes(searchValue);
      const cardSupplier = card.getAttribute('data-supplier') || '';
      const cardUser = card.getAttribute('data-user') || '';

      const matchesSupplier = !supplierFilter || cardSupplier === supplierFilter;
      const matchesUser = !userFilter || cardUser === userFilter;

      card.style.display = matchesSearch && matchesSupplier && matchesUser ? '' : 'none';
    });
  }
}

function setupFormHandlers() {}

// Delivery Items Array
let deliveryItems = [];

// Modal Functions
function openAddDeliveryModal() {
  document.getElementById('addDeliveryModal').classList.remove('hidden');
  document.getElementById('addDeliveryModal').classList.add('flex');
  // Reset form
  deliveryItems = [];
  resetDeliveryItemForm();
  updateDeliveryItemsTable();
  // Set default date to today
  document.getElementById('addDeliveryDate').value = new Date().toISOString().split('T')[0];
  // Clear reference_no input (will be auto-generated if left empty)
  // Delivery No is auto-increment, removed from form
  const refNoInput = document.getElementById('addReferenceNo');
  if (refNoInput) refNoInput.value = '';
  
  // Setup product search when modal opens
  setTimeout(function() {
    setupProductSearch();
  }, 100);
}

function closeAddDeliveryModal() {
  document.getElementById('addDeliveryModal').classList.add('hidden');
  document.getElementById('addDeliveryModal').classList.remove('flex');
  deliveryItems = [];
  resetDeliveryItemForm();
}

function resetDeliveryItemForm() {
  // Keep reference_no when resetting item form (delivery_no is auto-increment, removed from form)
  // Keep all item-specific fields (batch, expiry, quantities, costs, prices) so user can add multiple items with same values
  // Only clear product selection so user can select a different product
  
  const productHidden = document.getElementById('addProduct');
  const productSearch = document.getElementById('productSearch');
  const productSelect = document.getElementById('addProductSelect');
  
  // Clear product selection only
  if (productHidden) productHidden.value = '';
  if (productSearch) {
    productSearch.value = '';
    productSearch.placeholder = 'Type to search products...';
  }
  if (productSelect) {
    productSelect.selectedIndex = 0;
    productSelect.classList.add('hidden');
    productSelect.style.display = 'none';
  }
  
  // Keep all item-specific fields (batch, expiry, quantities, costs, prices remain)
  // Note: Manufacturing Date is now in Delivery Information section, so don't clear it here
  
  // Focus on product search for next entry
  if (productSearch) {
    setTimeout(() => {
      productSearch.focus();
    }, 100);
  }
}

async function addDeliveryItem() {
  const productHidden = document.getElementById('addProduct');
  const productSearch = document.getElementById('productSearch');
  const productSelect = document.getElementById('addProductSelect');
  const productId = productHidden ? productHidden.value : '';
  let productName = productSearch ? productSearch.value : '';
  
  if (!productId || productId === '') {
    alert('Please select a product from the dropdown');
    return;
  }
  
  // If productName is empty, try to get it from the select
  if (!productName && productId && productSelect) {
    const selectedOption = productSelect.querySelector(`option[value="${productId}"]`);
    if (selectedOption) {
      productName = selectedOption.getAttribute('data-name') || selectedOption.text;
    }
  }
  const batchNo = document.getElementById('addBatchNo').value.trim();
  const expiryDate = document.getElementById('addExpiryDate').value;
  const manufacturingDate = document.getElementById('addManufacturingDate').value;
  const quantityPackages = parseFloat(document.getElementById('addQuantityPackages').value) || 0;
  const costPerPackage = parseFloat(document.getElementById('addCostPerPackage').value) || 0;
  const quantityUnits = parseFloat(document.getElementById('addQuantityUnits').value) || 0;
  const costPerUnit = parseFloat(document.getElementById('addCostPerUnit').value) || 0;
  const totalCost = parseFloat(document.getElementById('addTotalCost').value) || 0;
  const sellingPricePackage = parseFloat(document.getElementById('addSellingPricePackage').value) || 0;
  const sellingPriceUnit = parseFloat(document.getElementById('addSellingPriceUnit').value) || 0;

  // Validation
  if (!productId || productId === '') {
    alert('Please select a product');
    return;
  }
  if (!batchNo) {
    alert('Please enter batch number');
    return;
  }
  if (quantityPackages <= 0) {
    alert('Quantity packages must be greater than 0');
    return;
  }
  if (costPerPackage <= 0) {
    alert('Cost per package must be greater than 0');
    return;
  }
  if (quantityUnits <= 0) {
    alert('Units per package must be greater than 0');
    return;
  }
  if (sellingPricePackage <= 0) {
    alert('Selling price per package must be greater than 0');
    return;
  }
  if (sellingPriceUnit <= 0) {
    alert('Selling price per unit must be greater than 0');
    return;
  }
  // Validate selling prices are not less than cost prices
  if (sellingPricePackage < costPerPackage) {
    alert(`Selling price per package (₱${sellingPricePackage.toFixed(2)}) cannot be less than cost per package (₱${costPerPackage.toFixed(2)})`);
    return;
  }
  if (sellingPriceUnit < costPerUnit) {
    alert(`Selling price per unit (₱${sellingPriceUnit.toFixed(2)}) cannot be less than cost per unit (₱${costPerUnit.toFixed(2)})`);
    return;
  }

  // Batch number duplicate check removed - same batch numbers are now allowed

  // Calculate total units
  const totalUnits = quantityPackages * quantityUnits;

  // Add item to array
  const item = {
    product_id: productId,
    product_name: productName,
    batch_no: batchNo,
    expiry_date: expiryDate || null,
    manufacturing_date: manufacturingDate || null,
    quantity_packages: quantityPackages,
    quantity_units: quantityUnits,
    total_units: totalUnits,
    cost_per_package: costPerPackage,
    cost_per_unit: costPerUnit,
    selling_price_package: sellingPricePackage,
    selling_price_unit: sellingPriceUnit,
    total_cost: totalCost
  };

  deliveryItems.push(item);
  updateDeliveryItemsTable();
  resetDeliveryItemForm();
}

function removeDeliveryItem(index) {
  if (confirm('Are you sure you want to remove this item?')) {
    deliveryItems.splice(index, 1);
    updateDeliveryItemsTable();
  }
}

function updateDeliveryItemsTable() {
  const tbody = document.getElementById('deliveryItemsTableBody');
  const emptyRow = document.getElementById('emptyDeliveryItemsRow');
  const submitBtn = document.getElementById('submitDeliveryBtn');

  if (deliveryItems.length === 0) {
    if (emptyRow) {
      emptyRow.style.display = '';
    }
    if (submitBtn) {
      submitBtn.disabled = true;
    }
    // Remove all rows except empty row
    const rows = tbody.querySelectorAll('tr:not(#emptyDeliveryItemsRow)');
    rows.forEach(row => row.remove());
    return;
  }

  if (emptyRow) {
    emptyRow.style.display = 'none';
  }
  if (submitBtn) {
    submitBtn.disabled = false;
  }

  // Remove all rows except empty row
  const rows = tbody.querySelectorAll('tr:not(#emptyDeliveryItemsRow)');
  rows.forEach(row => row.remove());

  // Add items
  deliveryItems.forEach((item, index) => {
    const row = document.createElement('tr');
    row.className = 'hover:bg-gray-50';
    row.innerHTML = `
      <td class="border border-gray-300 px-3 py-2 text-sm">${escapeHtml(item.product_name)}</td>
      <td class="border border-gray-300 px-3 py-2 text-sm">${escapeHtml(item.batch_no)}</td>
      <td class="border border-gray-300 px-3 py-2 text-sm text-right">${number_format(item.quantity_packages)}</td>
      <td class="border border-gray-300 px-3 py-2 text-sm text-right">${number_format(item.quantity_units)}</td>
      <td class="border border-gray-300 px-3 py-2 text-sm text-right font-semibold">${number_format(item.total_units)}</td>
      <td class="border border-gray-300 px-3 py-2 text-sm text-right">₱${parseFloat(item.cost_per_package).toFixed(2)}</td>
      <td class="border border-gray-300 px-3 py-2 text-sm text-right">₱${parseFloat(item.selling_price_package).toFixed(2)}</td>
      <td class="border border-gray-300 px-3 py-2 text-sm text-right">₱${parseFloat(item.selling_price_unit).toFixed(2)}</td>
      <td class="border border-gray-300 px-3 py-2 text-sm text-right font-semibold">₱${parseFloat(item.total_cost).toFixed(2)}</td>
      <td class="border border-gray-300 px-3 py-2 text-center">
        <button onclick="removeDeliveryItem(${index})" class="text-red-600 hover:text-red-800 font-semibold text-xs">
          Remove
        </button>
      </td>
    `;
    tbody.appendChild(row);
  });
}

// Product search and dropdown functionality - Simple and reliable
function setupProductSearch() {
  const searchInput = document.getElementById('productSearch');
  const productSelect = document.getElementById('addProductSelect');
  const hiddenInput = document.getElementById('addProduct');
  
  if (!searchInput || !productSelect) {
    console.error('Product search elements not found');
    return;
  }
  
  let isSelectOpen = false;
  let hideTimeout = null;
  
  // Show/hide select based on input focus
  searchInput.addEventListener('focus', function() {
    clearTimeout(hideTimeout);
    productSelect.classList.remove('hidden');
    productSelect.style.display = 'block';
    isSelectOpen = true;
    filterProductOptions();
  });
  
  searchInput.addEventListener('blur', function(e) {
    // Don't hide immediately - wait to see if user clicked on select
    clearTimeout(hideTimeout);
    // Check if the related target is the select or inside it
    const relatedTarget = e.relatedTarget;
    if (relatedTarget === productSelect || (relatedTarget && productSelect.contains(relatedTarget))) {
      return; // Don't hide if clicking on select
    }
    hideTimeout = setTimeout(function() {
      // Check if select is focused or if click was on select
      if (document.activeElement !== productSelect && !isSelectOpen) {
        productSelect.classList.add('hidden');
        productSelect.style.display = 'none';
      }
    }, 300);
  });
  
  // Filter options as user types
  searchInput.addEventListener('input', function() {
    clearTimeout(hideTimeout);
    productSelect.classList.remove('hidden');
    productSelect.style.display = 'block';
    isSelectOpen = true;
    filterProductOptions();
  });
  
  // Handle select focus
  productSelect.addEventListener('focus', function() {
    clearTimeout(hideTimeout);
    isSelectOpen = true;
  });
  
  // Prevent blur from closing when clicking on select
  productSelect.addEventListener('mousedown', function(e) {
    e.stopPropagation();
    clearTimeout(hideTimeout);
    isSelectOpen = true;
  });
  
  // Handle option selection - use change event (fires when option is selected)
  productSelect.addEventListener('change', function() {
    clearTimeout(hideTimeout);
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption && selectedOption.value && selectedOption.value !== '') {
      hiddenInput.value = selectedOption.value;
      const displayName = selectedOption.getAttribute('data-name') || selectedOption.text.trim();
      searchInput.value = displayName;
      isSelectOpen = false;
      productSelect.classList.add('hidden');
      productSelect.style.display = 'none';
      // Small delay before focusing to ensure selection is processed
      setTimeout(function() {
        searchInput.focus();
      }, 50);
    }
  });
  
  // Handle double-click for better compatibility
  productSelect.addEventListener('dblclick', function(e) {
    e.stopPropagation();
    clearTimeout(hideTimeout);
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption && selectedOption.value && selectedOption.value !== '') {
      hiddenInput.value = selectedOption.value;
      const displayName = selectedOption.getAttribute('data-name') || selectedOption.text.trim();
      searchInput.value = displayName;
      isSelectOpen = false;
      productSelect.classList.add('hidden');
      productSelect.style.display = 'none';
      searchInput.focus();
    }
  });
  
  // Handle click on the select element itself
  productSelect.addEventListener('click', function(e) {
    e.stopPropagation();
    clearTimeout(hideTimeout);
    // Don't close immediately - let change event handle it
  });
  
  productSelect.addEventListener('blur', function() {
    isSelectOpen = false;
    setTimeout(function() {
      if (document.activeElement !== searchInput) {
        productSelect.classList.add('hidden');
        productSelect.style.display = 'none';
      }
    }, 200);
  });
  
  // Prevent closing when clicking inside select
  productSelect.addEventListener('mouseenter', function() {
    clearTimeout(hideTimeout);
  });
  
  // Filter function
  function filterProductOptions() {
    const filter = searchInput.value.toLowerCase().trim();
    const options = productSelect.options;
    
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
      productSelect.classList.add('hidden');
      productSelect.style.display = 'none';
      isSelectOpen = false;
      return;
    }
    
    // Show select if there are visible options
    productSelect.classList.remove('hidden');
    productSelect.style.display = 'block';
    isSelectOpen = true;
    
    // Adjust select size based on visible options (max 7, min 2)
    const maxSize = Math.min(Math.max(visibleCount, 2), 7);
    productSelect.size = maxSize;
  }
}


// Auto-calculate cost per unit and total cost
document.addEventListener('DOMContentLoaded', function() {
  const addCostPerPackage = document.getElementById('addCostPerPackage');
  const addQuantityUnits = document.getElementById('addQuantityUnits');
  const addCostPerUnit = document.getElementById('addCostPerUnit');
  const addQuantityPackages = document.getElementById('addQuantityPackages');
  const addTotalCost = document.getElementById('addTotalCost');

  const addTotalUnits = document.getElementById('addTotalUnits');

  function calculateAddCosts() {
    const costPerPackage = parseFloat(addCostPerPackage?.value || 0);
    const unitsPerPackage = parseFloat(addQuantityUnits?.value || 0);
    const quantityPackages = parseFloat(addQuantityPackages?.value || 0);

    // Calculate total units: quantity_packages * units_per_package
    if (addTotalUnits) {
      const totalUnits = quantityPackages * unitsPerPackage;
      addTotalUnits.value = totalUnits > 0 ? totalUnits.toFixed(2) : '0.00';
    }

    // Calculate cost per unit: cost_per_package / units_per_package
    if (unitsPerPackage > 0 && addCostPerUnit) {
      const costPerUnit = costPerPackage / unitsPerPackage;
      addCostPerUnit.value = costPerUnit > 0 ? costPerUnit.toFixed(2) : '0.00';
    } else if (addCostPerUnit) {
      addCostPerUnit.value = '0.00';
    }

    // Calculate total cost: cost_per_package * quantity_packages
    if (addTotalCost) {
      const totalCost = costPerPackage * quantityPackages;
      addTotalCost.value = totalCost > 0 ? totalCost.toFixed(2) : '0.00';
    }
  }

  // Validate selling prices against cost prices in real-time
  function validateSellingPrices() {
    const costPerPackage = parseFloat(addCostPerPackage?.value || 0);
    const costPerUnit = parseFloat(addCostPerUnit?.value || 0);
    const sellingPricePackage = parseFloat(document.getElementById('addSellingPricePackage')?.value || 0);
    const sellingPriceUnit = parseFloat(document.getElementById('addSellingPriceUnit')?.value || 0);
    
    const sellingPricePackageEl = document.getElementById('addSellingPricePackage');
    const sellingPriceUnitEl = document.getElementById('addSellingPriceUnit');
    
    // Validate selling price package
    if (sellingPricePackageEl) {
      if (sellingPricePackage > 0 && costPerPackage > 0 && sellingPricePackage < costPerPackage) {
        sellingPricePackageEl.classList.add('border-red-500', 'ring-2', 'ring-red-200');
        sellingPricePackageEl.classList.remove('border-gray-300', 'border-emerald-500');
        sellingPricePackageEl.title = `Selling price must be at least ₱${costPerPackage.toFixed(2)} (cost per package)`;
      } else {
        sellingPricePackageEl.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
        sellingPricePackageEl.classList.add('border-gray-300');
        sellingPricePackageEl.title = '';
      }
    }
    
    // Validate selling price unit
    if (sellingPriceUnitEl) {
      if (sellingPriceUnit > 0 && costPerUnit > 0 && sellingPriceUnit < costPerUnit) {
        sellingPriceUnitEl.classList.add('border-red-500', 'ring-2', 'ring-red-200');
        sellingPriceUnitEl.classList.remove('border-gray-300', 'border-emerald-500');
        sellingPriceUnitEl.title = `Selling price must be at least ₱${costPerUnit.toFixed(2)} (cost per unit)`;
      } else {
        sellingPriceUnitEl.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
        sellingPriceUnitEl.classList.add('border-gray-300');
        sellingPriceUnitEl.title = '';
      }
    }
  }

  // Add event listeners for cost calculations and validation
  if (addCostPerPackage) {
    addCostPerPackage.addEventListener('input', function() {
      calculateAddCosts();
      validateSellingPrices();
    });
  }
  if (addQuantityUnits) {
    addQuantityUnits.addEventListener('input', function() {
      calculateAddCosts();
      validateSellingPrices();
    });
  }
  if (addQuantityPackages) {
    addQuantityPackages.addEventListener('input', calculateAddCosts);
  }

  // Add event listeners for selling price validation
  const addSellingPricePackage = document.getElementById('addSellingPricePackage');
  const addSellingPriceUnit = document.getElementById('addSellingPriceUnit');
  
  if (addSellingPricePackage) {
    addSellingPricePackage.addEventListener('input', validateSellingPrices);
    addSellingPricePackage.addEventListener('blur', validateSellingPrices);
  }
  if (addSellingPriceUnit) {
    addSellingPriceUnit.addEventListener('input', validateSellingPrices);
    addSellingPriceUnit.addEventListener('blur', validateSellingPrices);
  }

  // Handle form submission
  const addDeliveryForm = document.getElementById('addDeliveryForm');
  if (addDeliveryForm) {
    addDeliveryForm.addEventListener('submit', function(e) {
      e.preventDefault();

      // Validate delivery header
      const deliveryDate = document.getElementById('addDeliveryDate').value;
      const userId = document.getElementById('addUser').value;
      const referenceNo = document.getElementById('addReferenceNo') ? document.getElementById('addReferenceNo').value.trim() : '';
      // Delivery No is auto-increment, not needed from form
      const deliveryNo = '';

      if (!deliveryDate) {
        alert('Please select delivery date');
        return;
      }
      if (deliveryItems.length === 0) {
        alert('Please add at least one delivery item');
        return;
      }

      // Prepare data
      const formData = {
        delivery_date: deliveryDate,
        user_id: userId,
        delivery_no: deliveryNo || null, // null if empty, will be auto-generated by backend
        reference_no: referenceNo || null, // null if empty, will be auto-generated by backend
        items: deliveryItems
      };

      // Set hidden input
      document.getElementById('deliveryItemsData').value = JSON.stringify(formData);

      // Submit form
      this.submit();
    });
  }
});

function openEditDeliveryModal(delivery) {
  const editDeliveryId = document.getElementById('editDeliveryId');
  const editUser = document.getElementById('editUser');
  const editDeliveryDate = document.getElementById('editDeliveryDate');
  const editReferenceNo = document.getElementById('editReferenceNo');
  const editProduct = document.getElementById('editProduct');
  const editBatchNo = document.getElementById('editBatchNo');
  const editStatus = document.getElementById('editStatus');
  const editExpiryDate = document.getElementById('editExpiryDate');
  const editManufacturingDate = document.getElementById('editManufacturingDate');
  const editQuantityPackages = document.getElementById('editQuantityPackages');
  const editQuantityUnits = document.getElementById('editQuantityUnits');
  const editCostPerPackage = document.getElementById('editCostPerPackage');
  const editCostPerUnit = document.getElementById('editCostPerUnit');
  const editSellingPricePackage = document.getElementById('editSellingPricePackage');
  const editSellingPriceUnit = document.getElementById('editSellingPriceUnit');
  const editTotalCost = document.getElementById('editTotalCost');
  
  if (editDeliveryId) editDeliveryId.value = delivery.delivery_id || '';
  if (editUser) editUser.value = delivery.user_id || '';
  if (editDeliveryDate) editDeliveryDate.value = delivery.delivery_date || '';
  if (editReferenceNo) editReferenceNo.value = delivery.reference_no || '';
  if (editProduct) editProduct.value = delivery.product_id || '';
  if (editBatchNo) editBatchNo.value = delivery.batch_no || '';
  if (editStatus) editStatus.value = delivery.status || 'Active';
  if (editExpiryDate) editExpiryDate.value = delivery.expiry_date || '';
  if (editManufacturingDate) editManufacturingDate.value = delivery.manufacturing_date || '';
  if (editQuantityPackages) editQuantityPackages.value = (parseFloat(delivery.quantity_packages) || 0).toFixed(2);
  if (editQuantityUnits) editQuantityUnits.value = (parseFloat(delivery.quantity_units) || 0).toFixed(2);
  if (editCostPerPackage) editCostPerPackage.value = (parseFloat(delivery.cost_per_package) || 0).toFixed(2);
  if (editCostPerUnit) editCostPerUnit.value = (parseFloat(delivery.cost_per_unit) || 0).toFixed(2);
  if (editSellingPricePackage) editSellingPricePackage.value = (parseFloat(delivery.selling_price_package) || 0).toFixed(2);
  if (editSellingPriceUnit) editSellingPriceUnit.value = (parseFloat(delivery.selling_price_unit) || 0).toFixed(2);
  if (editTotalCost) editTotalCost.value = (parseFloat(delivery.total_cost) || 0).toFixed(2);
  
  const modal = document.getElementById('editDeliveryModal');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
}

function closeEditDeliveryModal() {
  document.getElementById('editDeliveryModal').classList.add('hidden');
  document.getElementById('editDeliveryModal').classList.remove('flex');
}


// Deletion handled by standard POST form

// View Delivery Details Modal
function openViewDeliveryModal(delivery) {
  const modal = document.getElementById('viewDeliveryModal');
  if (!modal) {
    console.error('View delivery modal not found');
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
  
  // Populate modal with delivery data
  const viewDeliveryId = document.getElementById('viewDeliveryId');
  const viewReferenceNo = document.getElementById('viewReferenceNo');
  const viewDeliveryDate = document.getElementById('viewDeliveryDate');
  const viewReceivedBy = document.getElementById('viewReceivedBy');
  const viewBatch = document.getElementById('viewBatch');
  const viewProduct = document.getElementById('viewProduct');
  const viewQuantityPackages = document.getElementById('viewQuantityPackages');
  const viewQuantityUnits = document.getElementById('viewQuantityUnits');
  const viewTotalUnits = document.getElementById('viewTotalUnits');
  const viewCostPerPackage = document.getElementById('viewCostPerPackage');
  const viewSellingPricePackage = document.getElementById('viewSellingPricePackage');
  const viewSellingPriceUnit = document.getElementById('viewSellingPriceUnit');
  const viewTotalCost = document.getElementById('viewTotalCost');
  const viewCreatedAt = document.getElementById('viewCreatedAt');
  
  if (viewDeliveryId) viewDeliveryId.textContent = '#' + (delivery.delivery_id || 'N/A');
  if (viewReferenceNo) viewReferenceNo.textContent = delivery.reference_no || 'N/A';
  if (viewDeliveryDate) viewDeliveryDate.textContent = formatDate(delivery.delivery_date);
  if (viewReceivedBy) viewReceivedBy.textContent = delivery.username || 'N/A';
  if (viewBatch) viewBatch.textContent = delivery.batch_no ? `#${delivery.delivery_id} - ${delivery.batch_no}` : (delivery.delivery_id ? `#${delivery.delivery_id}` : 'N/A');
  if (viewProduct) viewProduct.textContent = delivery.brand_name || 'N/A';
  if (viewQuantityPackages) viewQuantityPackages.textContent = delivery.quantity_packages ? number_format(parseInt(delivery.quantity_packages)) : '0';
  if (viewQuantityUnits) viewQuantityUnits.textContent = delivery.quantity_units ? number_format(parseInt(delivery.quantity_units)) : '0';
  if (viewTotalUnits) viewTotalUnits.textContent = delivery.total_units ? number_format(parseInt(delivery.total_units)) : '0';
  if (viewCostPerPackage) {
    const costPkg = parseFloat(delivery.cost_per_package) || 0;
    viewCostPerPackage.textContent = costPkg > 0 ? '₱' + costPkg.toFixed(2) : '₱0.00';
  }
  if (viewSellingPricePackage) {
    const sellPkg = parseFloat(delivery.selling_price_package) || 0;
    viewSellingPricePackage.textContent = sellPkg > 0 ? '₱' + sellPkg.toFixed(2) : '₱0.00';
  }
  if (viewSellingPriceUnit) {
    const sellUnit = parseFloat(delivery.selling_price_unit) || 0;
    viewSellingPriceUnit.textContent = sellUnit > 0 ? '₱' + sellUnit.toFixed(2) : '₱0.00';
  }
  if (viewTotalCost) {
    const total = parseFloat(delivery.total_cost) || 0;
    viewTotalCost.textContent = total > 0 ? '₱' + total.toFixed(2) : '₱0.00';
  }
  if (viewCreatedAt) viewCreatedAt.textContent = formatDateTime(delivery.created_at);
  
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeViewDeliveryModal() {
  const modal = document.getElementById('viewDeliveryModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

// Print Delivery Details Function
function printDeliveryDetails() {
  const element = document.getElementById('printableDeliveryContent');
  if (!element) {
    alert('No content to print');
    return;
  }
  
  const deliveryId = document.getElementById('viewDeliveryId')?.textContent || 'N/A';
  const referenceNo = document.getElementById('viewReferenceNo')?.textContent || 'N/A';
  
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('Please allow popups to print this document');
    return;
  }
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Delivery Details - ${escapeHtml(referenceNo)}</title>
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
        <h2>Delivery Details</h2>
        <p>Delivery ID: ${escapeHtml(deliveryId)} | Reference: ${escapeHtml(referenceNo)}</p>
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

// Print functionality
function printFullReport() {
  // Get all visible rows (respecting filters)
  const tableBody = document.getElementById('deliveriesTableBody');
  if (!tableBody) {
    alert('No deliveries table found.');
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
    alert('No deliveries to print. Please check your filters.');
    return;
  }
  
  // Calculate totals from visible rows
  let totalDeliveries = 0;
  let totalPackages = 0;
  let totalUnits = 0;
  let totalCost = 0;
  
  visibleRows.forEach(row => {
    totalDeliveries++;
    
    // Get quantities from data attributes (more reliable)
    const packages = parseInt(row.getAttribute('data-packages') || '0');
    const units = parseInt(row.getAttribute('data-total-units') || '0');
    const cost = parseFloat(row.getAttribute('data-total-cost') || '0');
    
    totalPackages += packages;
    totalUnits += units;
    totalCost += cost;
  });
  
  // Create print window
  const printWindow = window.open('', '_blank');
  
  // Get filter information
  const dateFrom = document.getElementById('date_from')?.value || '';
  const dateTo = document.getElementById('date_to')?.value || '';
  const supplier = document.getElementById('supplierFilter')?.value || '';
  const user = document.getElementById('userFilter')?.value || '';
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Deliveries Report</title>
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
          border-bottom: 3px solid #10b981;
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
          border-left: 4px solid #10b981;
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
          background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }
        .print-table th {
          color: #fff;
          font-weight: 700;
          padding: 12px 8px;
          text-align: left;
          border: 1px solid #059669;
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
        <h1>📦 Deliveries Report</h1>
        ${dateFrom && dateTo ? `<p><strong>Date Range:</strong> ${dateFrom} to ${dateTo}</p>` : dateFrom ? `<p><strong>From Date:</strong> ${dateFrom}</p>` : dateTo ? `<p><strong>To Date:</strong> ${dateTo}</p>` : '<p><strong>Date Range:</strong> All dates</p>'}
        ${user ? `<p><strong>User:</strong> ${user}</p>` : ''}
      </div>
      
      <div class="print-summary-cards">
        <div class="summary-card">
          <div class="label">Total Deliveries</div>
          <div class="value">${visibleRows.length}</div>
        </div>
        <div class="summary-card">
          <div class="label">Total Packages</div>
          <div class="value">${totalPackages.toLocaleString()}</div>
        </div>
        <div class="summary-card">
          <div class="label">Total Units</div>
          <div class="value">${totalUnits.toLocaleString()}</div>
        </div>
        <div class="summary-card">
          <div class="label">Total Cost</div>
          <div class="value">₱${totalCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
        </div>
      </div>
      
      <div class="print-info">
        <p><strong>Total Items:</strong> ${visibleRows.length} deliver${visibleRows.length !== 1 ? 'ies' : 'y'}</p>
        ${dateFrom && dateTo ? `<p><strong>Date Range:</strong> ${dateFrom} to ${dateTo}</p>` : dateFrom ? `<p><strong>From Date:</strong> ${dateFrom}</p>` : dateTo ? `<p><strong>To Date:</strong> ${dateTo}</p>` : '<p><strong>Date Range:</strong> All dates</p>'}
        ${user ? `<p><strong>User:</strong> ${user}</p>` : ''}
      </div>
      
      <table class="print-table">
        <thead>
          <tr>
            <th style="width: 8%;">ID</th>
            <th style="width: 20%;">Supplier</th>
            <th style="width: 8%;" class="text-right">Pkg</th>
            <th style="width: 10%;" class="text-right">Units</th>
            <th style="width: 10%;" class="text-right">Total Units</th>
            <th style="width: 10%;" class="text-right">Cost/Pkg</th>
            <th style="width: 10%;" class="text-right">Sell/Pkg</th>
            <th style="width: 10%;" class="text-right">Sell/Unit</th>
            <th style="width: 14%;">Delivery Date</th>
          </tr>
        </thead>
        <tbody>
  `);
  
  visibleRows.forEach(row => {
    // Clean up the row HTML for printing
    const cells = row.querySelectorAll('td');
    let rowHtml = '<tr>';
    
    cells.forEach((cell, index) => {
      const text = cell.textContent.trim();
      let className = '';
      
      // Determine alignment based on column (ID=0, Product=1, Batch=2, Pkg=3, Units/Pkg=4, Total Units=5, Cost/Pkg=6, Cost/Unit=7, Sell/Pkg=8, Sell/Unit=9, Expiry=10, Delivery Date=11, Status=12, Actions=13)
      if (index === 3 || index === 4 || index === 5 || index === 6 || index === 7 || index === 8 || index === 9) {
        className = 'text-right';
      } else if (index === 12) {
        className = 'text-center';
      }
      
      // Skip Actions column (last column)
      if (index === 13) {
        return;
      }
      
      // Clean up cell content
      let cellContent = text.replace(/\s+/g, ' ');
      
      // Special handling for product column (index 1)
      if (index === 1 && cell.querySelector('.min-w-0')) {
        const brandName = cell.querySelector('.font-semibold')?.textContent?.trim() || '';
        const genericName = cell.querySelector('.text-gray-500')?.textContent?.trim() || '';
        const strength = cell.querySelector('.text-gray-400')?.textContent?.trim() || '';
        cellContent = brandName + (genericName ? ' / ' + genericName : '') + (strength ? ' (' + strength + ')' : '');
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
            <td colspan="2" class="text-right" style="padding: 12px 8px;">TOTAL:</td>
            <td class="text-right" style="padding: 12px 8px;">${totalPackages.toLocaleString()}</td>
            <td class="text-right" style="padding: 12px 8px;">${totalUnits.toLocaleString()}</td>
            <td class="text-right" style="padding: 12px 8px;">${totalUnits.toLocaleString()}</td>
            <td colspan="4" class="text-right" style="padding: 12px 8px;">Total Cost: ₱${totalCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
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

// Expose functions globally
window.openViewDeliveryModal = openViewDeliveryModal;
window.closeViewDeliveryModal = closeViewDeliveryModal;
window.printDeliveryDetails = printDeliveryDetails;
window.filterDeliveries = filterDeliveries;
window.printFullReport = printFullReport;
window.addDeliveryItem = addDeliveryItem;
window.removeDeliveryItem = removeDeliveryItem;
window.openAddDeliveryModal = openAddDeliveryModal;
window.closeAddDeliveryModal = closeAddDeliveryModal;
window.setupProductSearch = setupProductSearch;


// Close modal on outside click
document.addEventListener('click', function(e) {
  const modal = document.getElementById('viewDeliveryModal');
  if (e.target === modal) {
    closeViewDeliveryModal();
  }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('viewDeliveryModal');
    if (modal && !modal.classList.contains('hidden')) {
      closeViewDeliveryModal();
    }
  }
});



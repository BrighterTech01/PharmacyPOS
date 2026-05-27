<?php
  $currentUserId = 0;
  $currentUsername = 'Unknown';
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (!empty($_SESSION['user']['user_id'])) {
    $currentUserId = (int)$_SESSION['user']['user_id'];
    if (!empty($_SESSION['user']['username'])) { $currentUsername = $_SESSION['user']['username']; }
  } elseif (!empty($_SESSION['user_id'])) {
    $currentUserId = (int)$_SESSION['user_id'];
    if (!empty($_SESSION['username'])) { $currentUsername = $_SESSION['username']; }
  }
?>
<div id="addDeliveryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full mx-auto flex flex-col max-h-[90vh] overflow-hidden">
    <!-- Header -->
    <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 px-5 py-4 flex-shrink-0 rounded-t-xl">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="p-1.5 bg-white/20 rounded-lg">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
    </div>
          <div>
            <h3 class="text-lg font-bold text-white">Add New Delivery</h3>
            <p class="text-xs text-emerald-50">Add products to delivery list</p>
          </div>
        </div>
        <button onclick="closeAddDeliveryModal()" class="p-1.5 hover:bg-white/20 rounded-lg transition-colors">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    </div>
    
    <div class="p-4 overflow-y-auto flex-1" style="overflow-y: auto; overflow-x: hidden;">
      <!-- Delivery Header Information -->
      <div class="mb-4 bg-gradient-to-br from-emerald-50 to-blue-50 p-3 rounded-lg border border-emerald-100">
        <div class="flex items-center gap-2 mb-3">
          <div class="p-1 bg-emerald-100 rounded">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
          </div>
          <h4 class="text-sm font-bold text-gray-800">Delivery Information</h4>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
              Delivery No <span class="text-xs text-gray-500">(Optional)</span>
            </label>
            <input type="text" id="addReferenceNo" placeholder="Auto-generated if left empty" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">
              Delivery Date <span class="text-red-500">*</span>
            </label>
            <input type="date" id="addDeliveryDate" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Manufacturing Date</label>
            <input type="date" id="addManufacturingDate" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all">
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Received By</label>
            <div class="flex items-center gap-2">
              <div class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <?php echo htmlspecialchars($currentUsername); ?>
              </div>
            </div>
            <input type="hidden" id="addUser" value="<?php echo $currentUserId; ?>">
          </div>
        </div>
      </div>

      <!-- Add Item Form -->
      <div class="mb-4 bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
        <div class="flex items-center gap-2 mb-3 pb-2 border-b border-gray-200">
          <div class="p-1 bg-blue-100 rounded">
            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
          </div>
          <h4 class="text-sm font-bold text-gray-800">Add Delivery Item</h4>
        </div>
        
        <!-- Product Information Section -->
        <div class="mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
          <h5 class="text-xs font-bold text-gray-700 mb-2 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            Product Information
          </h5>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold text-gray-700 mb-1">
                Select Product <span class="text-red-500">*</span>
              </label>
              <div class="relative" style="position: relative;">
                <input 
                  type="text" 
                  id="productSearch" 
                  placeholder="Type to search products..." 
                  class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all"
                  autocomplete="off"
                >
                <input type="hidden" id="addProduct" value="">
                <select 
                  id="addProductSelect" 
                  size="6"
                  class="w-full mt-1.5 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all hidden bg-white shadow-lg"
                  style="max-height: 180px; overflow-y: auto; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; display: none; cursor: pointer;"
                  tabindex="0"
                >
                  <option value="" disabled style="display: none;">-- Select a product --</option>
                  <?php foreach ($products as $product): 
                    $displayName = $product['brand_name'];
                    if (!empty($product['generic_name'])) {
                      $displayName .= ' (' . $product['generic_name'] . ')';
                    }
                    if (!empty($product['strength'])) {
                      $displayName .= ' - ' . $product['strength'];
                    }
                    $fullName = $product['brand_name'];
                    if (!empty($product['generic_name'])) {
                      $fullName .= ' ' . $product['generic_name'];
                    }
                    if (!empty($product['strength'])) {
                      $fullName .= ' ' . $product['strength'];
                    }
                  ?>
                    <option 
                      value="<?php echo $product['product_id']; ?>" 
                      data-name="<?php echo htmlspecialchars($displayName); ?>"
                      data-search="<?php echo htmlspecialchars(strtolower($fullName)); ?>">
                      <?php echo htmlspecialchars($displayName); ?>
                    </option>
              <?php endforeach; ?>
            </select>
              </div>
          </div>
          <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">
                Batch No <span class="text-red-500">*</span>
              </label>
              <input type="text" id="addBatchNo" required class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all" placeholder="Enter batch number">
          </div>
          <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Expire Date</label>
              <input type="date" id="addExpiryDate" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all">
          </div>
        </div>
      </div>

      <!-- Quantity Information Section -->
        <div class="mb-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
          <h5 class="text-xs font-bold text-gray-700 mb-2 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            Quantity Information
          </h5>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">
                Qty (Packages) <span class="text-red-500">*</span>
              </label>
              <input type="number" id="addQuantityPackages" min="0" step="0.01" value="0.00" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all">
          </div>
          <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">
                Units per Package <span class="text-red-500">*</span>
              </label>
              <input type="number" id="addQuantityUnits" min="0" step="0.01" value="0.00" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">
                Total Units <span class="text-xs text-gray-500 font-normal">(auto)</span>
              </label>
              <input type="number" id="addTotalUnits" min="0" step="0.01" value="0.00" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg bg-gray-100 text-gray-700 font-semibold cursor-not-allowed" readonly>
          </div>
        </div>
      </div>

      <!-- Cost Information Section -->
        <div class="mb-3 p-3 bg-orange-50 rounded-lg border border-orange-200">
          <h5 class="text-xs font-bold text-gray-700 mb-2 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Cost Information
          </h5>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">
                Cost per Package <span class="text-red-500">*</span>
              </label>
              <input type="number" id="addCostPerPackage" min="0" step="0.01" value="0.00" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all">
          </div>
          <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">
                Cost per Unit <span class="text-xs text-gray-500 font-normal">(auto)</span>
              </label>
              <input type="number" id="addCostPerUnit" min="0" step="0.01" value="0.00" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg bg-gray-100 text-gray-700 font-semibold cursor-not-allowed" readonly>
          </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">
                Total Cost <span class="text-xs text-gray-500 font-normal">(auto)</span>
              </label>
              <input type="number" id="addTotalCost" min="0" step="0.01" value="0.00" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg bg-gray-100 text-gray-700 font-semibold cursor-not-allowed" readonly>
          </div>
        </div>
      </div>

      <!-- Selling Price Information Section -->
        <div class="mb-3 p-3 bg-green-50 rounded-lg border border-green-200">
          <h5 class="text-xs font-bold text-gray-700 mb-2 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
            </svg>
            Selling Price Information
          </h5>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">
                Selling Price (Package) <span class="text-red-500">*</span>
              </label>
              <input type="number" id="addSellingPricePackage" min="0" step="0.01" value="0.00" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all">
          </div>
          <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">
                Selling Price (Unit) <span class="text-red-500">*</span>
              </label>
              <input type="number" id="addSellingPriceUnit" min="0" step="0.01" value="0.00" class="w-full px-3 py-2 text-sm border-2 border-gray-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 focus:outline-none transition-all">
          </div>
        </div>
      </div>

        <div class="mt-4">
          <button type="button" onclick="addDeliveryItem()" class="w-full bg-gradient-to-r from-emerald-500 to-emerald-600 text-white py-2.5 rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all font-semibold text-sm shadow-md hover:shadow-lg flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Add to Delivery List
          </button>
          </div>
        </div>

      <!-- Delivery Items List -->
      <div class="mb-4 bg-white p-3 rounded-lg border border-gray-200 shadow-sm">
        <div class="flex items-center gap-2 mb-3 pb-2 border-b border-gray-200">
          <div class="p-1 bg-purple-100 rounded">
            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
      </div>
          <h4 class="text-sm font-bold text-gray-800">Delivery Items List</h4>
        </div>
        <div id="deliveryItemsList" class="overflow-x-auto rounded border border-gray-200 max-h-64 overflow-y-auto">
          <table class="w-full border-collapse text-xs">
            <thead class="sticky top-0 bg-gradient-to-r from-gray-50 to-gray-100 z-10">
              <tr>
                <th class="border border-gray-300 px-2 py-2 text-left text-xs font-bold text-gray-700 uppercase">Product</th>
                <th class="border border-gray-300 px-2 py-2 text-left text-xs font-bold text-gray-700 uppercase">Batch</th>
                <th class="border border-gray-300 px-2 py-2 text-right text-xs font-bold text-gray-700 uppercase">Pkg</th>
                <th class="border border-gray-300 px-2 py-2 text-right text-xs font-bold text-gray-700 uppercase">Units/Pkg</th>
                <th class="border border-gray-300 px-2 py-2 text-right text-xs font-bold text-gray-700 uppercase">Total</th>
                <th class="border border-gray-300 px-2 py-2 text-right text-xs font-bold text-gray-700 uppercase">Cost/Pkg</th>
                <th class="border border-gray-300 px-2 py-2 text-right text-xs font-bold text-gray-700 uppercase">Sell/Pkg</th>
                <th class="border border-gray-300 px-2 py-2 text-right text-xs font-bold text-gray-700 uppercase">Sell/Unit</th>
                <th class="border border-gray-300 px-2 py-2 text-right text-xs font-bold text-gray-700 uppercase">Total Cost</th>
                <th class="border border-gray-300 px-2 py-2 text-center text-xs font-bold text-gray-700 uppercase">Action</th>
              </tr>
            </thead>
            <tbody id="deliveryItemsTableBody" class="bg-white">
              <tr id="emptyDeliveryItemsRow">
                <td colspan="10" class="border border-gray-300 px-3 py-6 text-center">
                  <div class="flex flex-col items-center justify-center text-gray-400">
                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p class="text-xs font-semibold text-gray-500">No items added yet</p>
                    <p class="text-xs text-gray-400 mt-0.5">Add items using the form above</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Submit Form -->
      <form id="addDeliveryForm" action="../../Controllers/manageDeliveryController.php" method="POST">
        <input type="hidden" name="create_delivery" value="1">
        <input type="hidden" name="delivery_items" id="deliveryItemsData">
        <div class="flex gap-2 pt-3 border-t border-gray-200 flex-shrink-0">
          <button type="submit" id="submitDeliveryBtn" class="flex-1 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white py-2.5 rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all font-semibold text-sm shadow-md hover:shadow-lg disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed disabled:shadow-none flex items-center justify-center gap-2" disabled>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Submit Delivery
        </button>
          <button type="button" onclick="closeAddDeliveryModal()" class="flex-1 bg-gray-500 text-white py-2.5 rounded-lg hover:bg-gray-600 transition-all font-semibold text-sm shadow-md hover:shadow-lg flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          Cancel
        </button>
      </div>
    </form>
    </div>
  </div>
</div>

<!-- Edit Delivery Modal -->
<div id="editDeliveryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4 overflow-y-auto" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full mx-auto my-8 overflow-hidden flex flex-col max-h-[90vh]">
    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 flex-shrink-0">
      <h3 class="text-xl font-bold text-white">Edit Delivery</h3>
    </div>
    <form id="editDeliveryForm" action="../../Controllers/manageDeliveryController.php" method="POST" class="p-6 overflow-y-auto flex-1">
      <input type="hidden" id="editDeliveryId" name="delivery_id">
      <input type="hidden" name="update_delivery" value="1">
      
      <!-- Delivery Information Section -->
      <div class="mb-6">
        <h4 class="text-sm font-semibold text-gray-800 mb-3 pb-2 border-b border-gray-200">Delivery Information</h4>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Delivery No <span class="text-xs text-gray-500">(Optional)</span></label>
            <input type="text" id="editReferenceNo" name="reference_no" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Delivery Date *</label>
            <input type="date" id="editDeliveryDate" name="delivery_date" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Manufacturing Date</label>
            <input type="date" id="editManufacturingDate" name="manufacturing_date" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
        </div>
      </div>

      <!-- Product Information Section -->
      <div class="mb-6">
        <h4 class="text-sm font-semibold text-gray-800 mb-3 pb-2 border-b border-gray-200">Product Information</h4>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Product *</label>
            <select id="editProduct" name="product_id" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
              <option value="">Select Product</option>
              <?php foreach ($products as $product): ?>
                <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['brand_name'] . ($product['generic_name'] ? ' (' . $product['generic_name'] . ')' : '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Batch No *</label>
            <input type="text" id="editBatchNo" name="batch_no" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors" placeholder="Enter batch number">
          </div>
          <input type="hidden" id="editStatus" name="status" value="Active">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Expiry Date</label>
            <input type="date" id="editExpiryDate" name="expiry_date" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
        </div>
      </div>

      <!-- Quantity Information Section -->
      <div class="mb-6">
        <h4 class="text-sm font-semibold text-gray-800 mb-3 pb-2 border-b border-gray-200">Quantity Information</h4>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Qty (Packages) *</label>
            <input type="number" id="editQuantityPackages" name="quantity_packages" required min="0" step="0.01" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Units per Package *</label>
            <input type="number" id="editQuantityUnits" name="quantity_units" required min="0" step="0.01" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
        </div>
      </div>

      <!-- Cost Information Section -->
      <div class="mb-6">
        <h4 class="text-sm font-semibold text-gray-800 mb-3 pb-2 border-b border-gray-200">Cost Information</h4>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Cost per Package *</label>
            <input type="number" id="editCostPerPackage" name="cost_per_package" required min="0" step="0.01" value="0.00" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Cost per Unit (auto)</label>
            <input type="number" id="editCostPerUnit" name="cost_per_unit" min="0" step="0.01" value="0.00" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg bg-gray-50 text-gray-700" readonly>
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Total Cost (auto)</label>
            <input type="number" id="editTotalCost" min="0" step="0.01" value="0.00" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg bg-gray-50 text-gray-700" readonly>
          </div>
        </div>
      </div>

      <!-- Selling Price Information Section -->
      <div class="mb-6">
        <h4 class="text-sm font-semibold text-gray-800 mb-3 pb-2 border-b border-gray-200">Selling Price Information</h4>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Selling Price (Package) *</label>
            <input type="number" id="editSellingPricePackage" name="selling_price_package" required min="0" step="0.01" value="0.00" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Selling Price (Unit) *</label>
            <input type="number" id="editSellingPriceUnit" name="selling_price_unit" required min="0" step="0.01" value="0.00" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
          </div>
        </div>
      </div>

      <!-- User Information Section -->
      <div class="mb-6">
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Received By</label>
          <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
            <?php echo htmlspecialchars($currentUsername); ?>
          </div>
          <input type="hidden" id="editUser" name="user_id" value="<?php echo $currentUserId; ?>">
        </div>
      </div>
      <div class="flex gap-3 mt-6 pt-4 border-t border-gray-200 flex-shrink-0">
        <button type="submit" class="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 transition-colors font-semibold">
          Update Delivery
        </button>
        <button type="button" onclick="closeEditDeliveryModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- View Delivery Details Modal -->
<div id="viewDeliveryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full mx-auto my-8 overflow-hidden flex flex-col max-h-[90vh]">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-4 flex-shrink-0">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="bg-white bg-opacity-20 p-1.5 rounded-lg mr-3">
            <svg class="w-5 h-5 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-white">Delivery Details</h3>
            <p class="text-xs text-indigo-100 mt-0.5">Complete delivery information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="closeViewDeliveryModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 overflow-y-auto flex-1">
      <div id="printableDeliveryContent">
        <!-- Delivery Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Delivery Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Delivery ID</p>
                  <p class="text-sm font-medium text-gray-900" id="viewDeliveryId">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Reference Number</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewReferenceNo">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Product Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Product Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Product</p>
                  <p class="text-sm text-gray-900" id="viewProduct">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Batch</p>
                  <p class="text-sm text-gray-900" id="viewBatch">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Received By</p>
                  <p class="text-sm text-gray-900" id="viewReceivedBy">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Quantity Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Quantity Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Quantity Packages</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewQuantityPackages">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Units per Package</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewQuantityUnits">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Total Units</p>
                  <p class="text-sm font-bold text-indigo-600" id="viewTotalUnits">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Pricing Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Pricing Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Cost per Package</p>
                  <p class="text-sm text-gray-900" id="viewCostPerPackage">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Selling Price (Package)</p>
                  <p class="text-sm text-gray-900" id="viewSellingPricePackage">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Selling Price (Unit)</p>
                  <p class="text-sm font-semibold text-green-600" id="viewSellingPriceUnit">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Total Cost</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewTotalCost">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Additional Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Additional Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Delivery Date</p>
                  <p class="text-sm text-gray-900" id="viewDeliveryDate">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Created At</p>
                  <p class="text-sm text-gray-900" id="viewCreatedAt">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal Footer -->
    <div class="bg-gray-50 px-5 py-3 border-t border-gray-200 flex-shrink-0">
      <div class="flex justify-end">
        <button onclick="closeViewDeliveryModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Print Styles -->
<style>
@media print {
  body * {
    visibility: hidden;
  }
  #printableDeliveryContent, #printableDeliveryContent * {
    visibility: visible;
  }
  #printableDeliveryContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  #printableDeliveryContent table {
    border-collapse: collapse;
    width: 100%;
  }
  #printableDeliveryContent th, #printableDeliveryContent td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
  }
  #printableDeliveryContent td:first-child {
    background-color: #f3f4f6;
    font-weight: bold;
    width: 30%;
  }
}
</style>

<script>
// Check for duplicate batch_no and reference_no
async function checkDuplicates(field, value, deliveryId = 0) {
    if (!value || value.trim() === '') {
        return { exists: false, message: '' };
    }

    // Reference numbers are now allowed to be reused; skip duplicate checking for reference_no
    if (field === 'reference_no') {
        return { exists: false, message: '' };
    }
    
    try {
        const params = new URLSearchParams();
        if (field === 'batch_no') {
            params.append('batch_no', value.trim());
        } else if (field === 'reference_no') {
            params.append('reference_no', value.trim());
        }
        if (deliveryId > 0) {
            params.append('delivery_id', deliveryId);
        }
        
        const response = await fetch(`../../Controllers/getControllers/checkDeliveryDuplicates.php?${params.toString()}`);
        const data = await response.json();
        
        if (data.success) {
            if (field === 'batch_no') {
                return { exists: data.batch_exists, message: data.batch_message };
            } else if (field === 'reference_no') {
                return { exists: data.reference_exists, message: data.reference_message };
            }
        }
        return { exists: false, message: '' };
    } catch (error) {
        console.error('Error checking duplicates:', error);
        return { exists: false, message: '' };
    }
}

// Real-time validation for batch_no and reference_no
function setupDuplicateValidation() {
    // Add Delivery Modal - Batch No validation
    const addBatchNo = document.getElementById('addBatchNo');
    const addReferenceNo = document.getElementById('addReferenceNo');
    const addForm = document.getElementById('addDeliveryForm');
    
    let addBatchNoTimeout;
    let addReferenceNoTimeout;
    let addBatchNoValid = true;
    let addReferenceNoValid = true;
    
    if (addBatchNo) {
        addBatchNo.addEventListener('input', function() {
            // Remove previous error styling
            this.classList.remove('border-red-500', 'border-green-500', 'border-gray-300');
            const errorMsg = this.parentElement.querySelector('.error-message');
            if (errorMsg) errorMsg.remove();
            
            // Batch number is required but duplicates are allowed
            const batchValue = this.value.trim();
            if (batchValue !== '') {
                this.classList.add('border-green-500');
            } else {
                this.classList.add('border-gray-300');
            }
        });
    }
    
    // Add Delivery Modal - Reference No validation
    if (addReferenceNo) {
        addReferenceNo.addEventListener('input', function() {
            clearTimeout(addReferenceNoTimeout);
            const refValue = this.value.trim();
            
            // Remove previous error styling
            this.classList.remove('border-red-500', 'border-green-500');
            const errorMsg = this.parentElement.querySelector('.error-message');
            if (errorMsg) errorMsg.remove();
            
            if (refValue === '') {
                addReferenceNoValid = true;
                return;
            }
            
            addReferenceNoTimeout = setTimeout(async () => {
                const result = await checkDuplicates('reference_no', refValue);
                addReferenceNoValid = !result.exists;
                
                if (result.exists) {
                    this.classList.add('border-red-500');
                    this.classList.remove('border-gray-300');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message text-red-600 text-xs mt-1';
                    errorDiv.textContent = result.message;
                    this.parentElement.appendChild(errorDiv);
                } else {
                    this.classList.add('border-green-500');
                    this.classList.remove('border-gray-300');
                }
            }, 500);
        });
    }
    
    // Add form submission validation
    if (addForm) {
        addForm.addEventListener('submit', async function(e) {
            const batchValue = addBatchNo ? addBatchNo.value.trim() : '';
            const refValue = addReferenceNo ? addReferenceNo.value.trim() : '';
            
            // Batch number is required (handled by HTML5 required attribute)
            // Duplicates are allowed, so no validation needed
            
            // Check reference_no
            if (refValue !== '') {
                const refResult = await checkDuplicates('reference_no', refValue);
                if (refResult.exists) {
                    e.preventDefault();
                    alert(refResult.message);
                    if (addReferenceNo) addReferenceNo.focus();
                    return false;
                }
            }
        });
    }
    
    // Edit Delivery Modal - Batch No validation
    const editBatchNo = document.getElementById('editBatchNo');
    const editReferenceNo = document.getElementById('editReferenceNo');
    const editForm = document.getElementById('editDeliveryForm');
    const editDeliveryId = document.getElementById('editDeliveryId');
    
    let editBatchNoTimeout, editReferenceNoTimeout;
    let editBatchNoValid = true;
    let editReferenceNoValid = true;
    
    if (editBatchNo) {
        editBatchNo.addEventListener('input', function() {
            // Remove previous error styling
            this.classList.remove('border-red-500', 'border-green-500', 'border-gray-300');
            const errorMsg = this.parentElement.querySelector('.error-message');
            if (errorMsg) errorMsg.remove();
            
            // Batch number is required but duplicates are allowed
            const batchValue = this.value.trim();
            if (batchValue !== '') {
                this.classList.add('border-green-500');
            } else {
                this.classList.add('border-gray-300');
            }
        });
    }
    
    if (editReferenceNo) {
        editReferenceNo.addEventListener('input', function() {
            clearTimeout(editReferenceNoTimeout);
            const refValue = this.value.trim();
            const currentDeliveryId = editDeliveryId ? parseInt(editDeliveryId.value) || 0 : 0;
            
            // Remove previous error styling
            this.classList.remove('border-red-500', 'border-green-500');
            const errorMsg = this.parentElement.querySelector('.error-message');
            if (errorMsg) errorMsg.remove();
            
            if (refValue === '') {
                editReferenceNoValid = true;
                return;
            }
            
            editReferenceNoTimeout = setTimeout(async () => {
                const result = await checkDuplicates('reference_no', refValue, currentDeliveryId);
                editReferenceNoValid = !result.exists;
                
                if (result.exists) {
                    this.classList.add('border-red-500');
                    this.classList.remove('border-gray-300');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message text-red-600 text-xs mt-1';
                    errorDiv.textContent = result.message;
                    this.parentElement.appendChild(errorDiv);
                } else {
                    this.classList.add('border-green-500');
                    this.classList.remove('border-gray-300');
                }
            }, 500);
        });
    }
    
    // Edit form submission validation
    if (editForm) {
        editForm.addEventListener('submit', async function(e) {
            const batchValue = editBatchNo ? editBatchNo.value.trim() : '';
            const refValue = editReferenceNo ? editReferenceNo.value.trim() : '';
            const currentDeliveryId = editDeliveryId ? parseInt(editDeliveryId.value) || 0 : 0;
            
            // Batch number is required (handled by HTML5 required attribute)
            // Duplicates are allowed, so no validation needed
            
            // Check reference_no
            if (refValue !== '') {
                const refResult = await checkDuplicates('reference_no', refValue, currentDeliveryId);
                if (refResult.exists) {
                    e.preventDefault();
                    alert(refResult.message);
                    if (editReferenceNo) editReferenceNo.focus();
                    return false;
                }
            }
        });
    }
}

// Prevent negative values in number inputs
function preventNegativeInput(input) {
    if (input) {
        input.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });
        
        input.addEventListener('keydown', function(e) {
            // Prevent minus sign
            if (e.key === '-' || e.key === 'e' || e.key === 'E' || e.key === '+') {
                e.preventDefault();
            }
        });
        
        input.addEventListener('paste', function(e) {
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            if (paste && (paste.includes('-') || isNaN(paste) || parseFloat(paste) < 0)) {
                e.preventDefault();
                this.value = 0;
            }
        });
    }
}

// Auto-calculate cost_per_unit and total_cost for Add Delivery Modal
document.addEventListener('DOMContentLoaded', function() {
    // Setup duplicate validation
    setupDuplicateValidation();
    
    // Prevent negative values in all number inputs
    const numberInputs = [
        'addQuantityPackages', 'addQuantityUnits', 'addCostPerPackage', 'addCostPerUnit', 
        'addTotalCost', 'addSellingPricePackage', 'addSellingPriceUnit',
        'editQuantityPackages', 'editQuantityUnits', 'editCostPerPackage', 'editCostPerUnit',
        'editTotalCost', 'editSellingPricePackage', 'editSellingPriceUnit'
    ];
    
    numberInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            preventNegativeInput(input);
        }
    });
    
    // Add Delivery Modal calculations
    const addCostPerPackage = document.getElementById('addCostPerPackage');
    const addQuantityUnits = document.getElementById('addQuantityUnits');
    const addCostPerUnit = document.getElementById('addCostPerUnit');
    const addQuantityPackages = document.getElementById('addQuantityPackages');
    const addTotalCost = document.getElementById('addTotalCost');
    
    function calculateAddCosts() {
        const costPerPackage = parseFloat(addCostPerPackage.value) || 0;
        const unitsPerPackage = parseFloat(addQuantityUnits.value) || 0;
        const quantityPackages = parseFloat(addQuantityPackages.value) || 0;
        
        // Calculate cost per unit: cost_per_package / units_per_package
        if (unitsPerPackage > 0) {
            const costPerUnit = costPerPackage / unitsPerPackage;
            addCostPerUnit.value = costPerUnit > 0 ? costPerUnit.toFixed(2) : '0.00';
        } else {
            addCostPerUnit.value = '0.00';
        }
        
        // Calculate total cost: cost_per_package * quantity_packages
        const totalCost = costPerPackage * quantityPackages;
        addTotalCost.value = totalCost > 0 ? totalCost.toFixed(2) : '0.00';
    }
    
    if (addCostPerPackage && addQuantityUnits && addCostPerUnit && addQuantityPackages && addTotalCost) {
        addCostPerPackage.addEventListener('input', calculateAddCosts);
        addQuantityUnits.addEventListener('input', calculateAddCosts);
        addQuantityPackages.addEventListener('input', calculateAddCosts);
    }
    
    // Edit Delivery Modal calculations
    const editCostPerPackage = document.getElementById('editCostPerPackage');
    const editQuantityUnits = document.getElementById('editQuantityUnits');
    const editCostPerUnit = document.getElementById('editCostPerUnit');
    const editQuantityPackages = document.getElementById('editQuantityPackages');
    const editTotalCost = document.getElementById('editTotalCost');
    
    function calculateEditCosts() {
        const costPerPackage = parseFloat(editCostPerPackage.value) || 0;
        const unitsPerPackage = parseFloat(editQuantityUnits.value) || 0;
        const quantityPackages = parseFloat(editQuantityPackages.value) || 0;
        
        // Calculate cost per unit: cost_per_package / units_per_package
        if (unitsPerPackage > 0) {
            const costPerUnit = costPerPackage / unitsPerPackage;
            editCostPerUnit.value = costPerUnit > 0 ? costPerUnit.toFixed(2) : '0.00';
        } else {
            editCostPerUnit.value = '0.00';
        }
        
        // Calculate total cost: cost_per_package * quantity_packages
        const totalCost = costPerPackage * quantityPackages;
        editTotalCost.value = totalCost > 0 ? totalCost.toFixed(2) : '0.00';
    }
    
    if (editCostPerPackage && editQuantityUnits && editCostPerUnit && editQuantityPackages && editTotalCost) {
        editCostPerPackage.addEventListener('input', calculateEditCosts);
        editQuantityUnits.addEventListener('input', calculateEditCosts);
        editQuantityPackages.addEventListener('input', calculateEditCosts);
    }
});
</script>
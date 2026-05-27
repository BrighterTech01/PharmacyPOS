<!-- Add Product Modal -->
<div id="addProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Add New Product</h3>
    </div>
    <form id="addProductForm" action="../../Controllers/manageProductController.php" method="POST" class="p-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Brand Name *</label>
          <input type="text" name="brand_name" id="addBrandName" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Generic Name *</label>
          <input type="text" name="generic_name" id="addGenericName" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Strength</label>
          <input type="text" name="strength" id="addStrength" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Barcode</label>
          <input type="text" name="barcode" id="addBarcode" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Category *</label>
          <div class="relative" style="position: relative;">
            <input
              type="text"
              id="addCategorySearch"
              placeholder="Type to search categories..."
              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none transition-all"
              autocomplete="off"
            >
            <input type="hidden" name="category_id" id="addCategory" value="" required>
            <select
              id="addCategorySelect"
              size="6"
              class="w-full mt-1.5 text-sm border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none transition-all bg-white shadow-lg hidden"
              style="max-height: 180px; overflow-y: auto; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; display: none; cursor: pointer;"
              tabindex="0"
            >
              <option value="" disabled style="display: none;">-- Select a category --</option>
              <?php foreach ($categories as $category): ?>
                <option
                  value="<?php echo $category['category_id']; ?>"
                  data-name="<?php echo htmlspecialchars($category['category_name']); ?>"
                  data-search="<?php echo htmlspecialchars(strtolower($category['category_name'])); ?>">
                  <?php echo htmlspecialchars($category['category_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Base Unit Type *</label>
          <div class="relative" style="position: relative;">
            <input
              type="text"
              id="addUnitSearch"
              placeholder="Type to search units..."
              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none transition-all"
              autocomplete="off"
            >
            <input type="hidden" name="base_unit_type_id" id="addUnit" value="" required>
            <select
              id="addUnitSelect"
              size="6"
              class="w-full mt-1.5 text-sm border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none transition-all bg-white shadow-lg hidden"
              style="max-height: 180px; overflow-y: auto; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; display: none; cursor: pointer;"
              tabindex="0"
            >
              <option value="" disabled style="display: none;">-- Select a unit --</option>
              <?php foreach ($units as $unit): ?>
                <option
                  value="<?php echo $unit['unit_type_id']; ?>"
                  data-name="<?php echo htmlspecialchars($unit['unit_name']); ?>"
                  data-search="<?php echo htmlspecialchars(strtolower($unit['unit_name'])); ?>">
                  <?php echo htmlspecialchars($unit['unit_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier *</label>
          <div class="relative" style="position: relative;">
            <input
              type="text"
              id="addSupplierSearch"
              placeholder="Type to search suppliers..."
              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none transition-all"
              autocomplete="off"
            >
            <input type="hidden" name="supplier_id" id="addSupplier" value="">
            <select
              id="addSupplierSelect"
              size="6"
              class="w-full mt-1.5 text-sm border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none transition-all bg-white shadow-lg hidden"
              style="max-height: 180px; overflow-y: auto; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; display: none; cursor: pointer;"
              tabindex="0"
            >
              <option value="" disabled style="display: none;">-- Select a supplier --</option>
              <?php foreach ($suppliers as $supplier): ?>
                <option
                  value="<?php echo $supplier['supplier_id']; ?>"
                  data-name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>"
                  data-search="<?php echo htmlspecialchars(strtolower($supplier['supplier_name'])); ?>">
                  <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Package Type</label>
          <div class="relative" style="position: relative;">
            <input
              type="text"
              id="addPackageTypeSearch"
              placeholder="Type to search package types..."
              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none transition-all"
              autocomplete="off"
            >
            <input type="hidden" name="package_type_id" id="addPackageType" value="">
            <select
              id="addPackageTypeSelect"
              size="6"
              class="w-full mt-1.5 text-sm border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-200 focus:outline-none transition-all bg-white shadow-lg hidden"
              style="max-height: 180px; overflow-y: auto; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; display: none; cursor: pointer;"
              tabindex="0"
            >
              <option value="" disabled style="display: none;">-- Select a package type --</option>
              <?php foreach ($packageTypes as $pkg): ?>
                <option
                  value="<?php echo $pkg['package_type_id']; ?>"
                  data-name="<?php echo htmlspecialchars($pkg['package_name']); ?>"
                  data-search="<?php echo htmlspecialchars(strtolower($pkg['package_name'])); ?>">
                  <?php echo htmlspecialchars($pkg['package_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Low Stock Threshold</label>
          <input type="number" name="product_threshold" id="addProductThreshold" min="0" step="1" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors" placeholder="0">
          <p class="text-xs text-gray-500 mt-1">Alert when stock ≤ this number of units</p>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Expiry Alert Threshold</label>
          <input type="number" name="expire_threshold" id="addExpireThreshold" min="0" step="1" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors" placeholder="0">
          <p class="text-xs text-gray-500 mt-1">Alert when product expires within this number of days</p>
        </div>
      </div>
      <div class="flex justify-end space-x-3 mt-6">
        <button type="button" onclick="closeAddProductModal()" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
          Cancel
        </button>
        <button type="submit" name="create_product" value="1" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
          Add Product
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Product Modal -->
<div id="editProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Edit Product</h3>
    </div>
    <form id="editProductForm" action="../../Controllers/manageProductController.php" method="POST" class="p-6">
      <input type="hidden" name="product_id" id="editProductId">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Brand Name *</label>
          <input type="text" name="brand_name" id="editBrandName" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Generic Name *</label>
          <input type="text" name="generic_name" id="editGenericName" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Strength</label>
          <input type="text" name="strength" id="editStrength" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Barcode</label>
          <input type="text" name="barcode" id="editBarcode" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Category *</label>
          <div class="relative" style="position: relative;">
            <input
              type="text"
              id="editCategorySearch"
              placeholder="Type to search categories..."
              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-1 focus:ring-green-200 focus:outline-none transition-all"
              autocomplete="off"
            >
            <input type="hidden" name="category_id" id="editCategory" value="" required>
            <select
              id="editCategorySelect"
              size="6"
              class="w-full mt-1.5 text-sm border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-1 focus:ring-green-200 focus:outline-none transition-all bg-white shadow-lg hidden"
              style="max-height: 180px; overflow-y: auto; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; display: none; cursor: pointer;"
              tabindex="0"
            >
              <option value="" disabled style="display: none;">-- Select a category --</option>
              <?php foreach ($categories as $category): ?>
                <option
                  value="<?php echo $category['category_id']; ?>"
                  data-name="<?php echo htmlspecialchars($category['category_name']); ?>"
                  data-search="<?php echo htmlspecialchars(strtolower($category['category_name'])); ?>">
                  <?php echo htmlspecialchars($category['category_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Base Unit Type *</label>
          <div class="relative" style="position: relative;">
            <input
              type="text"
              id="editUnitSearch"
              placeholder="Type to search units..."
              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-1 focus:ring-green-200 focus:outline-none transition-all"
              autocomplete="off"
            >
            <input type="hidden" name="base_unit_type_id" id="editUnit" value="" required>
            <select
              id="editUnitSelect"
              size="6"
              class="w-full mt-1.5 text-sm border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-1 focus:ring-green-200 focus:outline-none transition-all bg-white shadow-lg hidden"
              style="max-height: 180px; overflow-y: auto; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; display: none; cursor: pointer;"
              tabindex="0"
            >
              <option value="" disabled style="display: none;">-- Select a unit --</option>
              <?php foreach ($units as $unit): ?>
                <option
                  value="<?php echo $unit['unit_type_id']; ?>"
                  data-name="<?php echo htmlspecialchars($unit['unit_name']); ?>"
                  data-search="<?php echo htmlspecialchars(strtolower($unit['unit_name'])); ?>">
                  <?php echo htmlspecialchars($unit['unit_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier *</label>
          <div class="relative" style="position: relative;">
            <input
              type="text"
              id="editSupplierSearch"
              placeholder="Type to search suppliers..."
              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-1 focus:ring-green-200 focus:outline-none transition-all"
              autocomplete="off"
            >
            <input type="hidden" name="supplier_id" id="editSupplier" value="">
            <select
              id="editSupplierSelect"
              size="6"
              class="w-full mt-1.5 text-sm border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-1 focus:ring-green-200 focus:outline-none transition-all bg-white shadow-lg hidden"
              style="max-height: 180px; overflow-y: auto; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; display: none; cursor: pointer;"
              tabindex="0"
            >
              <option value="" disabled style="display: none;">-- Select a supplier --</option>
              <?php foreach ($suppliers as $supplier): ?>
                <option
                  value="<?php echo $supplier['supplier_id']; ?>"
                  data-name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>"
                  data-search="<?php echo htmlspecialchars(strtolower($supplier['supplier_name'])); ?>">
                  <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Package Type</label>
          <div class="relative" style="position: relative;">
            <input
              type="text"
              id="editPackageTypeSearch"
              placeholder="Type to search package types..."
              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-1 focus:ring-green-200 focus:outline-none transition-all"
              autocomplete="off"
            >
            <input type="hidden" name="package_type_id" id="editPackageType" value="">
            <select
              id="editPackageTypeSelect"
              size="6"
              class="w-full mt-1.5 text-sm border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-1 focus:ring-green-200 focus:outline-none transition-all bg-white shadow-lg hidden"
              style="max-height: 180px; overflow-y: auto; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; display: none; cursor: pointer;"
              tabindex="0"
            >
              <option value="" disabled style="display: none;">-- Select a package type --</option>
              <?php foreach ($packageTypes as $pkg): ?>
                <option
                  value="<?php echo $pkg['package_type_id']; ?>"
                  data-name="<?php echo htmlspecialchars($pkg['package_name']); ?>"
                  data-search="<?php echo htmlspecialchars(strtolower($pkg['package_name'])); ?>">
                  <?php echo htmlspecialchars($pkg['package_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Low Stock Threshold</label>
          <input type="number" name="product_threshold" id="editProductThreshold" min="0" step="1" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors" placeholder="0">
          <p class="text-xs text-gray-500 mt-1">Alert when stock ≤ this number of units</p>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Expiry Alert Threshold</label>
          <input type="number" name="expire_threshold" id="editExpireThreshold" min="0" step="1" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors" placeholder="0">
          <p class="text-xs text-gray-500 mt-1">Alert when product expires within this number of days</p>
        </div>
      </div>
      <div class="flex justify-end space-x-3 mt-6">
        <button type="button" onclick="closeEditProductModal()" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
          Cancel
        </button>
        <button type="submit" name="update_product" value="1" class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
          Update Product
        </button>
      </div>
    </form>
  </div>
</div>

<!-- View Product Details Modal -->
<div id="viewProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-auto overflow-hidden">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="bg-white bg-opacity-20 p-1.5 rounded-lg mr-3">
            <svg class="w-5 h-5 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-white">Product Details</h3>
            <p class="text-xs text-indigo-100 mt-0.5">Complete product information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="closeViewProductModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 max-h-[65vh] overflow-y-auto">
      <div id="printableProductContent">
        <!-- Product Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Product Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Product ID</p>
                  <p class="text-sm font-medium text-gray-900" id="viewProductId">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Brand Name</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewBrandName">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Generic Name</p>
                  <p class="text-sm text-gray-900" id="viewGenericName">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Strength</p>
                  <p class="text-sm text-gray-900" id="viewStrength">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Barcode</p>
                  <p class="text-xs font-mono text-gray-900" id="viewBarcode">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Category</p>
                  <p class="text-sm text-gray-900" id="viewCategory">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Classification Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Classification Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Base Unit Type</p>
                  <p class="text-sm text-gray-900" id="viewUnitType">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Supplier</p>
                  <p class="text-sm text-gray-900" id="viewSupplier">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Package Type</p>
                  <p class="text-sm text-gray-900" id="viewPackageType">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Threshold Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Threshold Settings</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-amber-50 p-3 rounded-lg border border-amber-100">
              <div class="flex items-center">
                <div class="bg-amber-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Low Stock Threshold</p>
                  <p class="text-sm font-semibold text-amber-700" id="viewProductThreshold">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-orange-50 p-3 rounded-lg border border-orange-100">
              <div class="flex items-center">
                <div class="bg-orange-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Expiry Alert Threshold</p>
                  <p class="text-sm font-semibold text-orange-700" id="viewExpireThreshold">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Additional Information Section -->
        <div>
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Additional Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Status</p>
                  <p class="text-sm" id="viewStatus">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">-</span>
                  </p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
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
    <div class="bg-gray-50 px-5 py-3 border-t border-gray-200">
      <div class="flex justify-end">
        <button onclick="closeViewProductModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
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
  #printableProductContent, #printableProductContent * {
    visibility: visible;
  }
  #printableProductContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  #printableProductContent table {
    border-collapse: collapse;
    width: 100%;
  }
  #printableProductContent th, #printableProductContent td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
  }
  #printableProductContent td:first-child {
    background-color: #f3f4f6;
    font-weight: bold;
    width: 30%;
  }
}
</style>

<!-- Delete Product Modal -->
<div id="deleteProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Delete Product</h3>
    </div>
    <div class="p-6">
      <p class="text-gray-700 mb-4">Are you sure you want to delete the product <strong id="deleteProductName"></strong>?</p>
      <p class="text-sm text-red-600 mb-6">This action cannot be undone.</p>
      <form action="../../Controllers/manageProductController.php" method="POST">
        <input type="hidden" name="product_id" id="deleteProductIdHidden">
        <div class="flex justify-end space-x-3">
          <button type="button" onclick="closeDeleteProductModal()" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
            Cancel
          </button>
          <button type="submit" name="delete_product" value="1" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
            Delete Product
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

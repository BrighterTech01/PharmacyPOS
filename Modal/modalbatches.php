<div id="addBatchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Add New Drug Batch</h3>
    </div>
    <form id="addBatchForm" action="../../Controllers/manageDrugbatchController.php" method="POST" class="p-6">
      <input type="hidden" name="create_batch" value="1">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Product *</label>
          <select id="addProduct" name="product_id" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
            <option value="">Select Product</option>
            <?php foreach ($products as $product): ?>
              <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['brand_name'] . ' - ' . $product['generic_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Batch Number *</label>
          <input type="text" id="addBatchNo" name="batch_no" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity (Packages) *</label>
          <input type="number" id="addQuantityPackages" name="quantity_packages" required min="0" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity (Units) *</label>
          <input type="number" id="addQuantityUnits" name="quantity_units" required min="0" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Manufacturing Date *</label>
          <input type="date" id="addManufacturingDate" name="manufacturing_date" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Expiry Date *</label>
          <input type="date" id="addExpiryDate" name="expiry_date" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Received Date *</label>
          <input type="date" id="addReceivedDate" name="received_date" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit" class="flex-1 bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition-colors font-semibold">
          Add Batch
        </button>
        <button type="button" onclick="closeAddBatchModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Batch Modal -->
<div id="editBatchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Edit Drug Batch</h3>
    </div>
    <form id="editBatchForm" action="../../Controllers/manageDrugbatchController.php" method="POST" class="p-6">
      <input type="hidden" id="editBatchId" name="batch_id">
      <input type="hidden" name="update_batch" value="1">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Product *</label>
          <select id="editProduct" name="product_id" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
            <option value="">Select Product</option>
            <?php foreach ($products as $product): ?>
              <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['brand_name'] . ' - ' . $product['generic_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Batch Number *</label>
          <input type="text" id="editBatchNo" name="batch_no" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity (Packages) *</label>
          <input type="number" id="editQuantityPackages" name="quantity_packages" required min="0" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity (Units) *</label>
          <input type="number" id="editQuantityUnits" name="quantity_units" required min="0" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Manufacturing Date *</label>
          <input type="date" id="editManufacturingDate" name="manufacturing_date" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Expiry Date *</label>
          <input type="date" id="editExpiryDate" name="expiry_date" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Received Date *</label>
          <input type="date" id="editReceivedDate" name="received_date" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit" class="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 transition-colors font-semibold">
          Update Batch
        </button>
        <button type="button" onclick="closeEditBatchModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteBatchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Delete Drug Batch</h3>
    </div>
    <div class="p-6">
      <p class="text-gray-700 mb-6">Are you sure you want to delete batch "<span id="deleteBatchName" class="font-bold"></span>"? This action cannot be undone.</p>
      <form id="deleteBatchForm" action="../../Controllers/manageDrugbatchController.php" method="POST">
        <input type="hidden" id="deleteBatchIdHidden" name="batch_id">
        <input type="hidden" name="delete_batch" value="1">
        <div class="flex gap-3">
          <button type="submit" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 transition-colors font-semibold">
            Delete
          </button>
          <button type="button" onclick="closeDeleteBatchModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition-colors font-semibold">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Batch Details Modal -->
<div id="viewBatchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-auto overflow-hidden">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="bg-white bg-opacity-20 p-1.5 rounded-lg mr-3">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-white">Batch Details</h3>
            <p class="text-xs text-indigo-100 mt-0.5">Complete batch information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="printBatchDetails()" class="flex items-center px-3 py-1.5 bg-white bg-opacity-20 hover:bg-opacity-30 text-white rounded-lg transition-all text-xs font-medium">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print
          </button>
          <button onclick="closeViewBatchModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 max-h-[65vh] overflow-y-auto">
      <div id="printableBatchContent">
        <!-- Batch Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Batch Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Batch ID</p>
                  <p class="text-sm font-medium text-gray-900" id="viewBatchId">-</p>
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
                  <p class="text-xs text-gray-500">Batch Number</p>
                  <p class="text-sm font-mono font-medium text-gray-900" id="viewBatchNo">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg md:col-span-2">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Product</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewProduct">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Date Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Date Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Manufacturing Date</p>
                  <p class="text-sm text-gray-900" id="viewManufacturingDate">-</p>
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
                  <p class="text-xs text-gray-500">Expiry Date</p>
                  <p class="text-sm" id="viewExpiryDate">-</p>
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
                  <p class="text-xs text-gray-500">Received Date</p>
                  <p class="text-sm text-gray-900" id="viewReceivedDate">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Quantity Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Quantity Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
              <div class="flex items-center">
                <div class="bg-blue-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Packages</p>
                  <p class="text-sm font-semibold text-blue-700" id="viewQuantityPackages">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-purple-50 p-3 rounded-lg border border-purple-100">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Units per Package</p>
                  <p class="text-sm font-semibold text-purple-700" id="viewQuantityUnits">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-green-50 p-3 rounded-lg border border-green-100">
              <div class="flex items-center">
                <div class="bg-green-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Total Units</p>
                  <p class="text-sm font-bold text-green-700" id="viewTotalUnits">-</p>
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
        <button onclick="closeViewBatchModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
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
  #printableBatchContent, #printableBatchContent * {
    visibility: visible;
  }
  #printableBatchContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  #printableBatchContent table {
    border-collapse: collapse;
    width: 100%;
  }
  #printableBatchContent th, #printableBatchContent td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
  }
  #printableBatchContent td:first-child {
    background-color: #f3f4f6;
    font-weight: bold;
    width: 30%;
  }
}
</style>
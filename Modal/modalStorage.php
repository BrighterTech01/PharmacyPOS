<!-- Add Storage Inventory Modal -->
<div id="addStorageInventoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Add Storage Inventory</h3>
    </div>
    <form id="addStorageInventoryForm" action="../../Controllers/storageInventoryController.php" method="POST" class="p-6">
      <input type="hidden" name="create_storage_inventory" value="1">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Batch *</label>
          <select id="addBatch" name="batch_id" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
            <option value="">Select Batch</option>
            <?php foreach ($batches as $b): ?>
              <option value="<?php echo $b['batch_id']; ?>"><?php echo '#'.$b['batch_id'].' - '.htmlspecialchars($b['brand_name'].' / '.$b['batch_no']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit" class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white py-2 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all font-semibold">
          Add Inventory Item
        </button>
        <button type="button" onclick="closeAddStorageInventoryModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Storage Inventory Modal -->
<div id="editStorageInventoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Edit Storage Inventory</h3>
    </div>
    <form id="editStorageInventoryForm" action="../../Controllers/storageInventoryController.php" method="POST" class="p-6">
      <input type="hidden" name="update_storage_inventory" value="1">
      <input type="hidden" name="storage_id" id="editStorageId">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Product</label>
          <input type="text" id="editStorageProductName" readonly class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg bg-gray-100 text-gray-600">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Batch</label>
          <input type="text" id="editStorageBatchNo" readonly class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg bg-gray-100 text-gray-600">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Packages IN *</label>
            <input type="number" id="editQuantityPackagesIn" name="quantity_packages_in" min="0" step="1" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors" placeholder="Enter packages in">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Packages OUT *</label>
            <input type="number" id="editQuantityPackagesOut" name="quantity_packages_out" min="0" step="1" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors" placeholder="Enter packages out">
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Units IN *</label>
            <input type="number" id="editQuantityUnitsIn" name="quantity_units_in" min="0" step="1" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors" placeholder="Enter units in">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Units OUT *</label>
            <input type="number" id="editQuantityUnitsOut" name="quantity_units_out" min="0" step="1" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors" placeholder="Enter units out">
          </div>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
          <p class="text-sm text-blue-800">
            <strong>Note:</strong> Packages Balance and Units Balance will be calculated automatically as (IN - OUT).
          </p>
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit" class="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 transition-colors font-semibold">
          Update Inventory Item
        </button>
        <button type="button" onclick="closeEditStorageInventoryModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Storage Inventory Modal -->
<div id="deleteStorageInventoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Delete Storage Inventory</h3>
    </div>
    <div class="p-6">
      <p class="text-gray-700 mb-4">Are you sure you want to delete this inventory item for <strong id="deleteStorageInventoryName"></strong>?</p>
      <p class="text-sm text-red-600 mb-6">This action cannot be undone.</p>
      <form action="../../Controllers/storageInventoryController.php" method="POST">
        <input type="hidden" name="delete_storage_inventory" value="1">
        <input type="hidden" name="storage_id" id="deleteStorageIdHidden">
        <div class="flex justify-end space-x-3">
          <button type="button" onclick="closeDeleteStorageInventoryModal()" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
            Cancel
          </button>
          <button type="submit" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
            Delete Inventory Item
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Storage Inventory Details Modal (With Movement Log) -->
<div id="viewStorageInventoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-auto overflow-hidden">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="bg-white bg-opacity-20 p-1.5 rounded-lg mr-3">
            <svg class="w-5 h-5 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-white">Storage Details</h3>
            <p class="text-xs text-indigo-100 mt-0.5">Complete inventory information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="printStorageInventoryDetails()" class="flex items-center px-3 py-1.5 bg-white/80 hover:bg-white text-gray-900 rounded-lg transition-all text-xs font-medium">
            <svg class="w-3.5 h-3.5 mr-1 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print
          </button>
          <button onclick="closeViewStorageInventoryModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 max-h-[65vh] overflow-y-auto">
      <div id="printableStorageInventoryContent">
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
                  <p class="text-xs text-gray-500">Storage ID</p>
                  <p class="text-sm font-medium text-gray-900" id="viewStorageInventoryId">-</p>
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
                  <p class="text-sm font-semibold text-gray-900" id="viewProduct">-</p>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Batch Number</p>
                  <p class="text-sm font-medium text-gray-900" id="viewBatchNo">-</p>
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
                  <p class="text-xs text-gray-500">Expiry Date</p>
                  <p class="text-sm text-gray-900" id="viewExpiryDate">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Packages Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Packages Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100">
              <div class="flex items-center">
                <div class="bg-emerald-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Packages IN</p>
                  <p class="text-sm font-semibold text-emerald-700" id="viewPackagesIn">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-red-50 p-3 rounded-lg border border-red-100">
              <div class="flex items-center">
                <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Packages OUT</p>
                  <p class="text-sm font-semibold text-red-700" id="viewPackagesOut">-</p>
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
                  <p class="text-xs text-gray-500">Packages Balance</p>
                  <p class="text-sm font-semibold text-emerald-800" id="viewPackagesBalance">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Units Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Units Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100">
              <div class="flex items-center">
                <div class="bg-emerald-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Units IN</p>
                  <p class="text-sm font-semibold text-emerald-700" id="viewUnitsIn">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-red-50 p-3 rounded-lg border border-red-100">
              <div class="flex items-center">
                <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Units OUT</p>
                  <p class="text-sm font-semibold text-red-700" id="viewUnitsOut">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-indigo-50 p-3 rounded-lg border border-indigo-100">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Units Balance</p>
                  <p class="text-sm font-semibold text-indigo-800" id="viewUnitsBalance">-</p>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Last Updated</p>
                  <p class="text-sm text-gray-900" id="viewLastUpdated">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Stock Movement Log Section -->
        <div>
          <div class="flex items-center justify-between mb-3 pb-1.5 border-b border-gray-200">
            <h4 class="text-base font-semibold text-gray-800 flex items-center gap-2">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
              </svg>
              Stock Movement Log
            </h4>
            <button onclick="clearStorageMovementLogFilters()" class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
              Clear Filters
            </button>
          </div>
          
          <!-- Filters Section -->
          <div class="bg-gray-50 rounded-lg p-3 mb-4 border border-gray-200">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2">
              <!-- Start Date -->
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="storageMovementLogStartDate" 
                  class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white storage-movement-log-filter"
                  data-filter-type="date">
              </div>
              
              <!-- End Date -->
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="storageMovementLogEndDate" 
                  class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white storage-movement-log-filter"
                  data-filter-type="date">
              </div>
              
              <!-- Movement Type -->
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Movement Type</label>
                <select id="storageMovementLogType" 
                  class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white storage-movement-log-filter"
                  data-filter-type="select">
                  <option value="">All Types</option>
                  <option value="delivery">Delivery</option>
                  <option value="transfer">Transfer</option>
                  <option value="sale">Sale</option>
                  <option value="adjustment">Adjustment</option>
                </select>
              </div>
              
              <!-- User Filter -->
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">User</label>
                <input type="text" id="storageMovementLogUser" placeholder="User name..." 
                  class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white storage-movement-log-filter"
                  data-filter-type="search">
              </div>
              
              <!-- Search -->
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <div class="relative">
                  <input type="text" id="storageMovementLogSearch" placeholder="Search reference, location, user..." 
                    class="w-full pl-8 pr-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white storage-movement-log-filter"
                    data-filter-type="search">
                  <svg class="absolute left-2 top-1/2 transform -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                  </svg>
                </div>
              </div>
            </div>
          </div>
          
          <div id="movementLogsContainer" class="overflow-x-auto">
            <div id="movementLogsLoading" class="text-center py-8 hidden">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
              <p class="mt-2 text-sm text-gray-500">Loading movement logs...</p>
            </div>
            
            <div id="movementLogsContent" class="hidden">
              <div id="storageMovementLogsTableWrapper" class="overflow-y-auto max-h-[400px] scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                <table class="w-full border-collapse bg-white text-sm">
                  <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase bg-gradient-to-r from-gray-50 to-gray-100">Date/Time</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase bg-gradient-to-r from-gray-50 to-gray-100">Movement Type</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase bg-gradient-to-r from-gray-50 to-gray-100">From → To</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase bg-gradient-to-r from-gray-50 to-gray-100">Packages</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase bg-gradient-to-r from-gray-50 to-gray-100">Units</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase bg-gradient-to-r from-gray-50 to-gray-100">Reference</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase bg-gradient-to-r from-gray-50 to-gray-100">User</th>
                    </tr>
                  </thead>
                  <tbody id="movementLogsTableBody" class="divide-y divide-gray-200">
                    <!-- Logs will be populated here -->
                  </tbody>
                </table>
              </div>
            </div>
            
            <div id="movementLogsEmpty" class="hidden text-center py-8">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
              </svg>
              <p class="mt-2 text-sm text-gray-500">No movement logs found for this batch</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal Footer -->
    <div class="bg-gray-50 px-5 py-3 border-t border-gray-200">
      <div class="flex justify-end">
        <button onclick="closeViewStorageInventoryModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
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
  #printableStorageInventoryContent, #printableStorageInventoryContent * {
    visibility: visible;
  }
  #printableStorageInventoryContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  #printableStorageInventoryContent table {
    border-collapse: collapse;
    width: 100%;
  }
  #printableStorageInventoryContent th, #printableStorageInventoryContent td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
  }
  #printableStorageInventoryContent td:first-child {
    background-color: #f3f4f6;
    font-weight: bold;
    width: 30%;
  }
}

/* Scrollbar styles for movement logs */
.scrollbar-thin {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 #f1f5f9;
}

.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.scrollbar-thumb-gray-300::-webkit-scrollbar-thumb {
  background: #d1d5db;
}

.scrollbar-track-gray-100::-webkit-scrollbar-track {
  background: #f3f4f6;
}
</style>

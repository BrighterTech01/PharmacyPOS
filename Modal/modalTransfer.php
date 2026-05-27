<!-- View Transfer Details Modal -->
<?php
// Fetch deliveries available in storage for transfer
require_once '../../dbConnection/connection.php';

// Get deliveries that exist in storage_inventory with available balance
// Note: pharmacy_inventory doesn't have batch_id column, so we can't join it here
$storageBatchesQuery = "SELECT DISTINCT 
                         si.delivery_id AS batch_id,
                         si.quantity_packages_balance AS available_packages,
                         si.quantity_units_balance AS available_units,
                         d.batch_no,
                         d.expiry_date,
                         d.quantity_units AS units_per_package,
                         p.product_id,
                         p.brand_name,
                         p.generic_name,
                         p.strength,
                         0 AS pharmacy_current_units
                       FROM storage_inventory si
                       LEFT JOIN deliveries d ON si.delivery_id = d.delivery_id
                       LEFT JOIN products p ON d.product_id = p.product_id
                       WHERE d.delivery_id IS NOT NULL 
                         AND si.quantity_packages_balance > 0
                       ORDER BY d.expiry_date ASC, p.brand_name ASC";
$storageBatchesResult = mysqli_query($conn, $storageBatchesQuery);
$storageBatches = [];
if ($storageBatchesResult && mysqli_num_rows($storageBatchesResult) > 0) {
    while ($row = mysqli_fetch_assoc($storageBatchesResult)) {
        $storageBatches[] = $row;
    }
}
?>

<!-- Transfer Stock Modal -->
<div id="transferStockModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Transfer Stock to Pharmacy</h3>
    </div>
    <form id="transferStockForm" action="../../Controllers/stockTransferController.php" method="POST" class="p-6">
      <input type="hidden" name="transfer_stock" value="1">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Batch (from Storage) *</label>
          <select id="transferBatch" name="batch_id" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
            <option value="">Select Batch</option>
            <?php foreach ($storageBatches as $batch): ?>
              <option value="<?php echo $batch['batch_id']; ?>" 
                      data-units-per-package="<?php echo (int)$batch['units_per_package']; ?>"
                      data-available-packages="<?php echo (int)($batch['available_packages'] ?? 0); ?>"
                      data-product="<?php echo htmlspecialchars($batch['brand_name'] . ' / ' . $batch['batch_no']); ?>">
                #<?php echo $batch['batch_id']; ?> - <?php echo htmlspecialchars($batch['brand_name']); ?> 
                (Batch: <?php echo htmlspecialchars($batch['batch_no']); ?> | Available: <?php echo number_format((int)($batch['available_packages'] ?? 0)); ?> packages)
              </option>
            <?php endforeach; ?>
          </select>
          <p class="text-xs text-gray-500 mt-1" id="batchInfo"></p>
        </div>
        
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity (Packages) *</label>
          <input type="number" id="transferQuantityPackages" name="quantity_packages" min="1" step="1" required 
                 class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors" 
                 placeholder="Enter packages">
        </div>
        
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Total Units (auto)</label>
          <input type="number" id="transferTotalUnits" min="0" step="1" readonly 
                 class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg bg-gray-50 text-gray-700" 
                 value="0">
          <p class="text-xs text-gray-500 mt-1">Calculated: Packages × Units per Package</p>
        </div>
      </div>
      
      <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
        <div class="flex items-start">
          <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <div class="text-sm text-blue-800">
            <strong>Transfer Process:</strong> This will update storage inventory and add to pharmacy inventory. 
            The movement will be logged in the stock movement log with movement_type='transfer'.
          </div>
        </div>
      </div>
      
      <div class="flex gap-3 mt-6">
        <button type="submit" class="flex-1 bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition-colors font-semibold">
          Transfer to Pharmacy
        </button>
        <button type="button" onclick="closeTransferStockModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>



<div id="viewTransferModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-auto overflow-hidden">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="bg-white bg-opacity-20 p-1.5 rounded-lg mr-3">
            <svg class="w-5 h-5 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-white">Transfer Details</h3>
            <p class="text-xs text-purple-100 mt-0.5">Complete transfer information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="closeViewTransferModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 max-h-[65vh] overflow-y-auto">
      <div id="printableTransferContent">
        <!-- Transfer Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Transfer Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Date</p>
                  <p class="text-sm font-medium text-gray-900" id="viewTransferDate">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Time</p>
                  <p class="text-sm font-medium text-gray-900" id="viewTransferTime">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Transferred By</p>
                  <p class="text-sm font-medium text-gray-900" id="viewTransferUser">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Movement Type</p>
                  <p class="text-sm font-medium text-gray-900" id="viewTransferType">Transfer</p>
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
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Brand Name</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewTransferBrandName">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Generic Name</p>
                  <p class="text-sm text-gray-900" id="viewTransferGenericName">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Batch Number</p>
                  <p class="text-sm text-gray-900" id="viewTransferBatchNo">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Expiry Date</p>
                  <p class="text-sm text-gray-900" id="viewTransferExpiry">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Transfer Details Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Transfer Details</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
              <div class="flex items-center">
                <div class="bg-blue-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">From</p>
                  <p class="text-sm font-semibold text-blue-700 capitalize" id="viewTransferFrom">Storage</p>
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
                  <p class="text-xs text-gray-500">To</p>
                  <p class="text-sm font-semibold text-green-700 capitalize" id="viewTransferTo">Pharmacy</p>
                </div>
              </div>
            </div>
            
            <div class="bg-amber-50 p-3 rounded-lg border border-amber-100">
              <div class="flex items-center">
                <div class="bg-amber-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Packages</p>
                  <p class="text-sm font-semibold text-amber-700" id="viewTransferPackages">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-purple-50 p-3 rounded-lg border border-purple-100">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Units</p>
                  <p class="text-sm font-semibold text-purple-700" id="viewTransferUnits">-</p>
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
        <button onclick="closeViewTransferModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
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
  #printableTransferContent, #printableTransferContent * {
    visibility: visible;
  }
  #printableTransferContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}
</style>


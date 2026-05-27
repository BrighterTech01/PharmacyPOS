<!-- View Movement Details Modal -->
<div id="viewMovementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-auto overflow-hidden">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="bg-white bg-opacity-20 p-1.5 rounded-lg mr-3">
            <svg class="w-5 h-5 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-white">Movement Details</h3>
            <p class="text-xs text-indigo-100 mt-0.5">Complete movement information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="closeViewMovementModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 max-h-[65vh] overflow-y-auto">
      <div id="printableMovementContent">
        <!-- Movement Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Movement Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Date</p>
                  <p class="text-sm font-medium text-gray-900" id="viewMovementDate">-</p>
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
                  <p class="text-xs text-gray-500">Time</p>
                  <p class="text-sm font-medium text-gray-900" id="viewMovementTime">-</p>
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
                  <p class="text-xs text-gray-500">Movement Type</p>
                  <p class="text-sm font-medium text-gray-900" id="viewMovementType">-</p>
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
                  <p class="text-xs text-gray-500">User</p>
                  <p class="text-sm font-medium text-gray-900" id="viewMovementUser">-</p>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Brand Name</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewMovementBrandName">-</p>
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
                  <p class="text-sm text-gray-900" id="viewMovementGenericName">-</p>
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
                  <p class="text-xs text-gray-500">Batch Number</p>
                  <p class="text-sm text-gray-900" id="viewMovementBatchNo">-</p>
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
                  <p class="text-sm text-gray-900" id="viewMovementExpiry">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Movement Details Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Movement Details</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
              <div class="flex items-center">
                <div class="bg-blue-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">From Inventory</p>
                  <p class="text-sm font-semibold text-blue-700 capitalize" id="viewMovementFrom">-</p>
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
                  <p class="text-xs text-gray-500">To Inventory</p>
                  <p class="text-sm font-semibold text-green-700 capitalize" id="viewMovementTo">-</p>
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
                  <p class="text-sm font-semibold text-amber-700" id="viewMovementPackages">-</p>
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
                  <p class="text-sm font-semibold text-purple-700" id="viewMovementUnits">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Reference Information Section -->
        <div>
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Reference Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                </div>
        <div>
                  <p class="text-xs text-gray-500">Reference Type</p>
                  <p class="text-sm text-gray-900 capitalize" id="viewMovementRefType">-</p>
                </div>
        </div>
      </div>
      
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
          </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Reference ID</p>
                  <p class="text-sm text-gray-900" id="viewMovementRefId">-</p>
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
        <button onclick="closeViewMovementModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
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
  #printableMovementContent, #printableMovementContent * {
    visibility: visible;
  }
  #printableMovementContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}
</style>

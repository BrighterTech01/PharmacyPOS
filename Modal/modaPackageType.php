<!-- Add Package Type Modal -->
<div id="addPackageTypeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Add Package Type</h3>
    </div>
    <form action="../../Controllers/managePackageTypeController.php" method="POST" class="p-6">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Package Name *</label>
        <input type="text" name="package_name" id="addPackageName" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
      </div>
      <div class="flex justify-end space-x-3 mt-6">
        <button type="button" onclick="closeAddPackageTypeModal()" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">Cancel</button>
        <button type="submit" name="create_package_type" value="1" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Package Type Modal -->
<div id="editPackageTypeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Edit Package Type</h3>
    </div>
    <form action="../../Controllers/managePackageTypeController.php" method="POST" class="p-6">
      <input type="hidden" name="package_type_id" id="editPackageTypeId">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Package Name *</label>
        <input type="text" name="package_name" id="editPackageName" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
      </div>
      <div class="flex justify-end space-x-3 mt-6">
        <button type="button" onclick="closeEditPackageTypeModal()" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">Cancel</button>
        <button type="submit" name="update_package_type" value="1" class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Package Type Modal -->
<div id="deletePackageTypeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Delete Package Type</h3>
    </div>
    <div class="p-6">
      <p class="text-gray-700 mb-4">Are you sure you want to delete <strong id="deletePackageTypeName"></strong>?</p>
      <p class="text-sm text-red-600 mb-6">This action cannot be undone.</p>
      <form action="../../Controllers/managePackageTypeController.php" method="POST">
        <input type="hidden" name="package_type_id" id="deletePackageTypeIdHidden">
        <div class="flex justify-end space-x-3">
          <button type="button" onclick="closeDeletePackageTypeModal()" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">Cancel</button>
          <button type="submit" name="delete_package_type" value="1" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Package Type Details Modal -->
<div id="viewPackageTypeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4" style="background: rgba(0,0,0,0.6);">
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
            <h3 class="text-lg font-bold text-white">Package Type Details</h3>
            <p class="text-xs text-indigo-100 mt-0.5">Complete package type information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="printPackageTypeDetails()" class="flex items-center px-3 py-1.5 bg-white/80 hover:bg-white text-gray-900 rounded-lg transition-all text-xs font-medium">
            <svg class="w-3.5 h-3.5 mr-1 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print
          </button>
          <button onclick="closeViewPackageTypeModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 max-h-[65vh] overflow-y-auto">
      <div id="printablePackageTypeContent">
        <!-- Package Type Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Package Type Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Package Type ID</p>
                  <p class="text-sm font-medium text-gray-900" id="viewPackageTypeId">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Package Name</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewPackageTypeName">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Status & Usage Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Status & Usage Information</h4>
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
                  <p class="text-sm" id="viewPackageTypeStatus">-</p>
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
                  <p class="text-xs text-gray-500">Total Products</p>
                  <p class="text-sm font-semibold text-indigo-600" id="viewPackageTypeProducts">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Products Using This Package Type Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Products Using This Package Type</h4>
          
          <!-- Loading State -->
          <div id="packageProductsLoading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            <p class="mt-2 text-gray-600">Loading products...</p>
          </div>
          
          <!-- Products Table Container -->
          <div id="packageProductsContainer" class="hidden">
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="w-full border-collapse">
                <thead>
                  <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Product ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Brand Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Generic Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Category</th>
                  </tr>
                </thead>
                <tbody id="packageProductsTableBody" class="bg-white divide-y divide-gray-100">
                  <!-- Products will be loaded here -->
                </tbody>
              </table>
            </div>
          </div>
          
          <!-- Empty State -->
          <div id="packageProductsEmpty" class="hidden text-center py-8 text-gray-500">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            <p class="font-semibold">No products are using this package type</p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal Footer -->
    <div class="bg-gray-50 px-5 py-3 border-t border-gray-200">
      <div class="flex justify-end">
        <button onclick="closeViewPackageTypeModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Print Styles for Package Type Modal -->
<style>
@media print {
  body * {
    visibility: hidden;
  }
  #printablePackageTypeContent, #printablePackageTypeContent * {
    visibility: visible;
  }
  #printablePackageTypeContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}
</style>

<!-- View Products Modal -->
<div id="viewProductsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full mx-4 my-8 overflow-hidden">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-xl font-bold text-white">Products with Package Type: <span id="viewPackageTypeNameHeader" class="text-purple-100"></span></h3>
          <p class="text-sm text-purple-100 mt-1">Total: <span id="viewProductCount" class="font-medium text-purple-100">0</span> products</p>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="exportToPDF('package')" class="flex items-center px-3 py-2 bg-white/80 hover:bg-white text-gray-900 rounded-lg transition-colors text-sm font-medium">
            <svg class="w-4 h-4 mr-1 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            PDF
          </button>
          <button onclick="printProductsTable()" class="flex items-center px-3 py-2 bg-white/80 hover:bg-white text-gray-900 rounded-lg transition-colors text-sm font-medium">
            <svg class="w-4 h-4 mr-1 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print
          </button>
          <button onclick="closeViewProductsModal()" class="p-2 text-white hover:bg-purple-700 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <div class="p-6">
      <!-- Search Bar -->
      <div class="mb-4">
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </div>
          <input 
            type="text" 
            id="productSearchInput" 
            placeholder="Search products..." 
            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
            onkeyup="filterProducts()"
          >
        </div>
      </div>

      <!-- Loading State -->
      <div id="productsLoading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-10 w-10 border-2 border-purple-600 border-t-transparent"></div>
        <p class="mt-3 text-gray-600">Loading products...</p>
      </div>

      <!-- Products Table -->
      <div id="productsTableContainer" class="hidden">
        <div id="printableContent" class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-200">
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">ID</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Brand Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Generic Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Strength</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Category</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Stock</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Price</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Supplier</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Expiry</th>
              </tr>
            </thead>
            <tbody id="productsTableBody" class="bg-white divide-y divide-gray-100">
              <!-- Products will be loaded here -->
            </tbody>
          </table>
        </div>
        <div id="productsEmpty" class="hidden text-center py-12 text-gray-500">
          <svg class="w-12 h-12 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
          </svg>
          <p class="font-medium">No products found</p>
        </div>
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
  #printableContent, #printableContent * {
    visibility: visible;
  }
  #printableContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  #printableContent table {
    border-collapse: collapse;
    width: 100%;
  }
  #printableContent th, #printableContent td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
  }
  #printableContent th {
    background-color: #f3f4f6;
    font-weight: bold;
  }
}
</style>

<!-- PDF Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

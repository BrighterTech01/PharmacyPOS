<div id="addSupplierModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Add New Supplier</h3>
    </div>
    <form id="addSupplierForm" action="../../Controllers/manageSupplierController.php" method="POST" class="p-6">
      <input type="hidden" name="create_supplier" value="1">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier Name *</label>
          <input type="text" id="addSupplierName" name="supplier_name" maxlength="150" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Contact Person</label>
          <input type="text" id="addContactPerson" name="contact_person" maxlength="100" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
          <input type="text" id="addPhone" name="phone" maxlength="50" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
          <input type="email" id="addEmail" name="email" maxlength="100" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors">
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
          <textarea id="addAddress" name="address" rows="2" placeholder="Enter supplier address" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none transition-colors"></textarea>
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit" name="create_supplier" class="flex-1 bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition-colors font-semibold">
          Add Supplier
        </button>
        <button type="button" onclick="closeAddSupplierModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Supplier Modal -->
<div id="editSupplierModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Edit Supplier</h3>
    </div>
    <form id="editSupplierForm" action="../../Controllers/manageSupplierController.php" method="POST" class="p-6">
      <input type="hidden" id="editSupplierId" name="supplier_id">
      <input type="hidden" name="update_supplier" value="1">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier Name *</label>
          <input type="text" id="editSupplierName" name="supplier_name" maxlength="150" required class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Contact Person</label>
          <input type="text" id="editContactPerson" name="contact_person" maxlength="100" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
          <input type="text" id="editPhone" name="phone" maxlength="50" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
          <input type="email" id="editEmail" name="email" maxlength="100" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors">
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
          <textarea id="editAddress" name="address" rows="2" placeholder="Enter supplier address" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:outline-none transition-colors"></textarea>
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit" name="update_supplier" class="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 transition-colors font-semibold">
          Update Supplier
        </button>
        <button type="button" onclick="closeEditSupplierModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition-colors font-semibold">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteSupplierModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
    <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4">
      <h3 class="text-xl font-bold text-white">Delete Supplier</h3>
    </div>
    <div class="p-6">
      <p class="text-gray-700 mb-6">Are you sure you want to delete "<span id="deleteSupplierName" class="font-bold"></span>"? This action cannot be undone.</p>
      <form id="deleteSupplierForm" action="../../Controllers/manageSupplierController.php" method="POST">
        <input type="hidden" id="deleteSupplierIdHidden" name="supplier_id">
        <input type="hidden" name="delete_supplier" value="1">
        <div class="flex gap-3">
          <button type="submit" name="delete_supplier" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 transition-colors font-semibold">
            Delete
          </button>
          <button type="button" onclick="closeDeleteSupplierModal()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition-colors font-semibold">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Supplier Details Modal -->
<div id="viewSupplierModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4" style="background: rgba(0,0,0,0.6);">
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
            <h3 class="text-lg font-bold text-white">Supplier Details</h3>
            <p class="text-xs text-indigo-100 mt-0.5">Complete supplier information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="printSupplierDetails()" class="flex items-center px-3 py-1.5 bg-white/80 hover:bg-white text-gray-900 rounded-lg transition-all text-xs font-medium">
            <svg class="w-3.5 h-3.5 mr-1 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print
          </button>
          <button onclick="closeViewSupplierModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 max-h-[65vh] overflow-y-auto">
      <div id="printableSupplierContent">
        <!-- Supplier Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Supplier Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Supplier ID</p>
                  <p class="text-sm font-medium text-gray-900" id="viewSupplierId">-</p>
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
                  <p class="text-xs text-gray-500">Supplier Name</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewSupplierName">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Contact Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Contact Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Contact Person</p>
                  <p class="text-sm text-gray-900" id="viewContactPerson">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Phone</p>
                  <p class="text-sm text-gray-900" id="viewPhone">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg md:col-span-2">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Email</p>
                  <p class="text-sm text-gray-900" id="viewEmail">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg md:col-span-2">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Address</p>
                  <p class="text-sm text-gray-900" id="viewAddress">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Delivery Details Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Delivery History</h4>
          
          <!-- Loading State -->
          <div id="deliveriesLoading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            <p class="text-sm text-gray-500 mt-2">Loading deliveries...</p>
          </div>
          
          <!-- Empty State -->
          <div id="deliveriesEmpty" class="bg-gray-50 rounded-lg p-8 text-center border border-gray-200 hidden">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
              </svg>
            </div>
            <p class="font-semibold text-lg text-gray-600 mb-1">No Deliveries Found</p>
            <p class="text-sm text-gray-500">This supplier has no delivery records yet.</p>
          </div>
          
          <!-- Deliveries Table -->
          <div id="deliveriesTableContainer" class="hidden overflow-x-auto rounded-lg border border-gray-200">
            <table class="w-full min-w-[800px] divide-y divide-gray-200">
              <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Date</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Reference No</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Product</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Batch No</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Packages</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Units</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Total Cost</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Received By</th>
                </tr>
              </thead>
              <tbody id="deliveriesTableBody" class="bg-white divide-y divide-gray-200">
                <!-- Delivery rows will be inserted here by JavaScript -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal Footer -->
    <div class="bg-gray-50 px-5 py-3 border-t border-gray-200">
      <div class="flex justify-end">
        <button onclick="closeViewSupplierModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
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
  #printableSupplierContent, #printableSupplierContent * {
    visibility: visible;
  }
  #printableSupplierContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  #printableSupplierContent table {
    border-collapse: collapse;
    width: 100%;
  }
  #printableSupplierContent th, #printableSupplierContent td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
  }
  #printableSupplierContent td:first-child {
    background-color: #f3f4f6;
    font-weight: bold;
    width: 30%;
  }
}
</style>
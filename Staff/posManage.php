<?php 
include '../includes/CashierHeader.php';

// Resolve user_id for JavaScript (used in sale transactions)
$currentUserId = 0;
if (!empty($_SESSION['user']['user_id'])) { 
    $currentUserId = (int)$_SESSION['user']['user_id']; 
} elseif (!empty($_SESSION['user_id'])) { 
    $currentUserId = (int)$_SESSION['user_id']; 
} else {
    // Fallback: resolve from username if user_id not in session
    $uname = '';
    if (!empty($_SESSION['user']['username'])) { $uname = $_SESSION['user']['username']; }
    elseif (!empty($_SESSION['username'])) { $uname = $_SESSION['username']; }
    
    if ($uname !== '') {
        $unameEsc = mysqli_real_escape_string($conn, $uname);
        $rs = @mysqli_query($conn, "SELECT user_id FROM users WHERE username = '$unameEsc' LIMIT 1");
        if ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $currentUserId = (int)($row['user_id'] ?? 0);
        }
        if ($rs instanceof mysqli_result) { @mysqli_free_result($rs); }
    }
}
?>
    <!-- POS Dashboard - Inspired by provided UI -->
    <main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 md:pl-20 bg-gradient-to-br from-gray-50 to-emerald-50/30 min-h-screen">
        <style>
            .product-card { transition: transform .18s ease, box-shadow .18s ease; }
            .product-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,.08); }
            .scroll-slim::-webkit-scrollbar { width: 8px; height: 8px; }
            .scroll-slim::-webkit-scrollbar-thumb { background: rgba(107,114,128,.35); border-radius: 8px; }
            .scroll-slim { scrollbar-width: thin; scrollbar-color: rgba(107,114,128,.35) transparent; }
            .chip { padding: .5rem .9rem; border-radius: 9999px; border: 1px solid #e5e7eb; font-size: .85rem; white-space: nowrap; }
            .chip-active { background: #111827; color: #fff; border-color: #111827; }
            .sb-item.active { color: #1d4ed8; background: #eff6ff; }
            .sb-item { transition: background .15s ease, color .15s ease; }
            .badge-discount { position: absolute; left: .5rem; top: .5rem; background: #ebf5ff; color: #1d4ed8; font-weight: 700; font-size: .75rem; padding: .15rem .45rem; border-radius: .5rem; border: 1px solid #bfdbfe; }
            .price-old { color: #9ca3af; text-decoration: line-through; font-size: .8rem; }
            .card-shadow { box-shadow: 0 10px 25px rgba(0,0,0,.06); }
            .product-grid-scroll { max-height: 640px; overflow-y: auto; padding-right: 0.25rem; }
            @keyframes alertBorderPulse {
                0% { opacity: 0.2; }
                50% { opacity: 1; }
                100% { opacity: 0.2; }
            }
            .alert-blink {
                position: relative;
            }
            .alert-blink::after {
                content: '';
                position: absolute;
                inset: 2px 6px;
                border: 2px solid rgba(239,68,68,0.8);
                border-radius: 14px;
                pointer-events: none;
                animation: alertBorderPulse 1.2s ease-in-out infinite;
            }
            .low-stock-row {
                background-color: #fffbea;
            }
        </style>

        <!-- QZ Tray URL Helper - Shows your URL for easy copying -->
        <div id="qzUrlHelper" style="position: fixed; top: 10px; right: 10px; background: #4CAF50; color: white; padding: 10px 15px; border-radius: 8px; z-index: 9999; font-size: 12px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.2); display: none;" onclick="copyQZUrl()" title="Click to copy URL for QZ Tray">
            <strong>QZ Tray URL:</strong><br>
            <span id="qzUrlText" style="font-family: monospace; font-size: 11px;">Loading...</span><br>
            <small style="opacity: 0.9;">Click to copy</small>
        </div>

        <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
            <!-- Main Row: product area + checkout (responsive) -->
            <div class="flex flex-col lg:flex-row gap-4 lg:gap-6">

                <!-- Center: Categories + Products -->
                <section class="flex-1 min-w-0">
                    <!-- Categories (Pharmacy) -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 sm:p-5 mb-4 card-shadow">
                        <h3 class="text-sm font-semibold text-gray-800 mb-3">Categories</h3>
                        <div id="categoryChips" class="flex items-center gap-2 overflow-x-auto scroll-slim">
                            <button class="chip chip-active" data-cat="all">All Medicines</button>
                            <button class="chip" data-cat="rx">Prescription (Rx)</button>
                            <button class="chip" data-cat="otc">Over-the-Counter</button>
                            <button class="chip" data-cat="vitamins">Vitamins</button>
                            <button class="chip" data-cat="personal">Personal Care</button>
                            <button class="chip" data-cat="supplies">Medical Supplies</button>
                        </div>
                    </div>

                    <!-- Product Grid -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-3 sm:p-5 card-shadow">
                        <div class="flex flex-col gap-4 mb-4">
                            <!-- Header with title and filters -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-800">Available Medicines</h3>
                                    <p class="text-xs text-gray-500 product-count">Loading products...</p>
                                </div>
                                <div class="w-full sm:w-auto">
                                    <div class="relative w-full sm:min-w-[260px] lg:min-w-[320px]">
                                        <input id="searchInput" placeholder="Search medicines by name, generic name, or category..." class="w-full pl-10 pr-10 py-2 sm:py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                                        <svg class="w-5 h-5 text-gray-400 absolute left-4 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        <button id="clearSearchBtn" onclick="clearSearch()" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 hidden">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Barcode Scanner - Always Visible -->
                            <div class="bg-gradient-to-r from-purple-50 to-blue-50 p-3 sm:p-4 rounded-lg border border-purple-200">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                    <div class="flex items-center gap-2 flex-1">
                                        <div id="scannerStatus" class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                        <label class="text-xs sm:text-sm font-semibold text-purple-800 whitespace-nowrap">Barcode Scanner:</label>
                                        <input type="text" id="barcodeInput" placeholder="Scan or type barcode..." class="flex-1 min-w-[200px] px-3 py-2 border-2 border-purple-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all text-sm font-mono" autocomplete="off">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button onclick="scanBarcode()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg shadow-md transition-all text-xs sm:text-sm font-semibold whitespace-nowrap">Scan</button>
                                        <div id="scannerMode" class="text-xs text-purple-600 font-medium hidden sm:block">Auto-detect</div>
                                    </div>
                                </div>
                                <!-- Physical Scanner Status (Hidden on mobile, shown on larger screens) -->
                                <div id="scannerDeviceCard" class="hidden sm:flex mt-2 p-2 bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded items-center gap-2">
                                    <div id="scannerDeviceStatus" class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                    <p id="scannerDeviceText" class="text-xs font-medium text-green-800">Physical Scanner Ready - Just Scan!</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto rounded-xl border border-gray-200">
                            <table class="w-full min-w-[1000px]">
                                <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Product</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Generic Name</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Stock</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Package Price</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="productTableBody" class="bg-white divide-y divide-gray-200">
                                    <!-- Products injected by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Right: Checkout Panel -->
                <aside class="w-full lg:max-w-[420px] lg:flex-[0_0_380px] xl:flex-[0_0_400px] 2xl:flex-[0_0_420px] bg-white card-shadow rounded-2xl border border-gray-200 flex flex-col lg:h-[calc(100vh-120px)] lg:sticky lg:top-6 mt-6 lg:mt-0">
                    <!-- Header -->
                    <div class="p-4 sm:p-6 flex items-center justify-between border-b">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Detail Items</h2>
                            <p class="text-gray-500 text-xs" id="cartCount">0 items</p>
                        </div>
                    </div>

                    <!-- Items -->
                    <div id="cartItemsContainer" class="flex-1 overflow-y-auto p-4 sm:p-6 bg-gray-50 scroll-slim">
                        <div id="cartItems" class="space-y-3">
                            <div class="text-center text-gray-500 py-16" id="emptyCart">
                                <svg class="w-20 h-20 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 11-4 0v-6m-4 0a2 2 0 104 0m-4 0h4"></path>
                                </svg>
                                <p class="text-lg font-semibold">Your cart is empty</p>
                                <p class="text-sm mt-2">Add products to get started</p>
                            </div>
                        </div>
                    </div>

                    <!-- Totals & Payment -->
                    <div class="border-t border-gray-200 bg-white p-4 sm:p-6 space-y-4">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-gray-600">Item</span><span id="itemCount">0 (items)</span></div>
                            <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span id="subtotal">₱0.00</span></div>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t"><span class="font-semibold text-gray-800">Total</span><span class="font-bold text-gray-900 text-lg" id="grandTotal">₱0.00</span></div>
                        
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Customer Name</label>
                            <input type="text" id="customerNameDash" placeholder="Walk-in Customer" class="w-full p-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        </div>
                        <button id="processBtn" class="w-full flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 text-white px-4 py-3 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 font-semibold">Process Transaction</button>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Payment Modal (Dashboard) -->
		<div id="paymentModalDash" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4 sm:p-6" style="background: rgba(0,0,0,0.6);">
            <div class="bg-white/95 backdrop-blur-sm p-4 sm:p-6 rounded-xl w-full max-w-3xl max-h-[90vh] shadow-2xl border border-gray-200/50 flex flex-col overflow-hidden">
                <div class="flex items-center mb-4 flex-shrink-0">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">Process Payment</h2>
                </div>
                <div class="flex-1 overflow-y-auto pr-2">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <!-- Left Column: Payment Summary -->
                        <div class="flex-1 space-y-4">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <div class="flex justify-between mb-2"><span class="text-gray-600 text-sm">Subtotal:</span><span class="font-semibold text-sm" id="modalSubtotalDash">₱0.00</span></div>
                                <div class="flex flex-col gap-2 mb-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600 text-sm">Discount Type:</span>
                                        <select id="discountTypeDash" class="text-xs font-semibold border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-green-500 focus:border-green-500" onchange="handleDiscountTypeChange()">
                                            <option value="None">None</option>
                                            <option value="Regular">Regular</option>
                                            <option value="PWD">PWD</option>
                                            <option value="Senior">Senior</option>
                                        </select>
                                    </div>
                                    <div class="flex justify-between items-center" id="discountPercentRow" style="display: none;">
                                        <span class="text-gray-600 text-sm">Discount %:</span>
                                        <div class="flex items-center gap-2">
                                            <input type="number" id="discountPercentDash" min="0" max="100" step="0.01" value="0" placeholder="0" class="w-16 text-xs font-semibold border border-gray-300 rounded-lg px-2 py-1 text-center focus:ring-2 focus:ring-green-500 focus:border-green-500" oninput="calculateDiscounts()">
                                            <span class="text-xs font-semibold text-gray-600">%</span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center" id="discountRow" style="display: none;">
                                        <span class="text-gray-600 text-sm">Discount Amount:</span><span class="font-semibold text-red-600 text-sm" id="modalDiscountDash">₱0.00</span>
                                    </div>
                                </div>
                                <div class="flex justify-between mb-2 pt-2 border-t border-gray-300"><span class="text-gray-700 font-bold text-sm">Total Amount:</span><span class="font-bold text-base" id="modalTotalDash">₱0.00</span></div>
                                <div class="flex justify-between mb-1"><span class="text-gray-600 text-xs">Payment Method:</span><span class="font-semibold text-green-600 text-xs">Cash Payment</span></div>
                                <div class="flex justify-between"><span class="text-gray-600 text-xs">Customer:</span><span class="font-semibold text-xs" id="modalCustomerDash">Walk-in Customer</span></div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Amount Received</label>
                                <input type="number" id="amountReceivedDashModal" placeholder="Enter amount received" step="0.01" class="w-full p-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all" required>
                            </div>
                            <div class="bg-green-50 p-2 rounded-lg">
                                <div class="flex justify-between"><span class="text-green-800 font-medium text-sm">Change:</span><span class="text-green-800 font-bold text-sm" id="changeAmountDashModal">₱0.00</span></div>
                            </div>
                        </div>
                        <!-- Right Column: Items Table -->
                        <div class="flex-1">
                            <div id="paymentItemsWrapper" class="overflow-x-auto border rounded-lg">
                                <table class="min-w-full text-left text-xs">
                                    <thead class="bg-gray-50 text-gray-600 sticky top-0">
                                        <tr>
                                            <th class="px-2 py-1.5">Item</th>
                                            <th class="px-2 py-1.5 text-center">Qty</th>
                                            <th class="px-2 py-1.5 text-right">Price</th>
                                            <th class="px-2 py-1.5 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody id="paymentItemsBody" class="text-gray-700"></tbody>
                                    <tfoot class="bg-gray-50 text-xs sticky bottom-0">
                                        <tr>
                                            <td colspan="3" class="px-2 py-1.5 text-right font-semibold">Subtotal</td>
                                            <td class="px-2 py-1.5 text-right font-bold" id="paymentItemsSubtotal">₱0.00</td>
                                        </tr>
                                        <tr id="discountRowTable" style="display: none;">
                                            <td colspan="3" class="px-2 py-1.5 text-right text-red-600 font-semibold">Discount</td>
                                            <td class="px-2 py-1.5 text-right text-red-600 font-bold" id="paymentItemsDiscount">₱0.00</td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="px-2 py-1.5 text-right font-semibold">Total</td>
                                            <td class="px-2 py-1.5 text-right font-bold" id="paymentItemsTotal">₱0.00</td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="px-2 py-1.5 text-right text-gray-600">Payment</td>
                                            <td class="px-2 py-1.5 text-right text-gray-900" id="paymentAmountLabel">₱0.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-4 pt-4 border-t border-gray-200 flex-shrink-0">
                    <button type="button" onclick="closePaymentModalDash()" class="px-6 py-3 bg-gray-400 hover:bg-gray-500 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200">Cancel</button>
                    <button type="button" onclick="completeTransactionDash()" class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200">Complete Transaction</button>
                </div>
            </div>
        </div>

        <!-- Error Modal -->
		<div id="errorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4" style="background: rgba(0,0,0,0.6);">
            <div class="bg-white rounded-xl w-full max-w-md shadow-2xl border border-gray-200 animate-fadeIn">
                <div class="p-6 border-b">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800" id="errorModalTitle">Error</h2>
                    </div>
                </div>
                <div class="p-6">
                    <p class="text-gray-700" id="errorModalMessage">An error occurred.</p>
                </div>
                <div class="p-6 border-t flex justify-end">
                    <button onclick="closeErrorModal()" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200 font-semibold">OK</button>
                </div>
            </div>
        </div>

        <!-- Success Modal -->
		<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4" style="background: rgba(0,0,0,0.6);">
            <div class="bg-white rounded-xl w-full max-w-md shadow-2xl border border-gray-200 animate-fadeIn">
                <div class="p-6 border-b">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800" id="successModalTitle">Success</h2>
                    </div>
                </div>
                <div class="p-6">
                    <p class="text-gray-700" id="successModalMessage">Operation completed successfully.</p>
                </div>
                <div class="p-6 border-t flex justify-end">
                    <button onclick="closeSuccessModal()" class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200 font-semibold">OK</button>
                </div>
            </div>
        </div>

        <style>
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            .animate-fadeIn {
                animation: fadeIn 0.2s ease-out;
            }
        </style>

        <script>
            // Modal Error/Success Handling Functions
            function showErrorModal(title = 'Error', message = 'An error occurred.') {
                const modal = document.getElementById('errorModal');
                const titleEl = document.getElementById('errorModalTitle');
                const messageEl = document.getElementById('errorModalMessage');
                
                if (titleEl) titleEl.textContent = title;
                if (messageEl) messageEl.textContent = message;
                
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    
                    // Auto-close after 5 seconds
                    setTimeout(() => {
                        closeErrorModal();
                    }, 5000);
                }
            }
            
            function closeErrorModal() {
                const modal = document.getElementById('errorModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }
            
            function showSuccessModal(title = 'Success', message = 'Operation completed successfully.') {
                const modal = document.getElementById('successModal');
                const titleEl = document.getElementById('successModalTitle');
                const messageEl = document.getElementById('successModalMessage');
                
                if (titleEl) titleEl.textContent = title;
                if (messageEl) messageEl.textContent = message;
                
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    
                    // Auto-close after 3 seconds for success messages
                    setTimeout(() => {
                        closeSuccessModal();
                    }, 3000);
                }
            }
            
            function closeSuccessModal() {
                const modal = document.getElementById('successModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }
            
            // Close modals when clicking outside
            document.addEventListener('DOMContentLoaded', function() {
                const errorModal = document.getElementById('errorModal');
                const successModal = document.getElementById('successModal');
                
                if (errorModal) {
                    errorModal.addEventListener('click', function(e) {
                        if (e.target === errorModal) {
                            closeErrorModal();
                        }
                    });
                }
                
                if (successModal) {
                    successModal.addEventListener('click', function(e) {
                        if (e.target === successModal) {
                            closeSuccessModal();
                        }
                    });
                }
                
                // Close modals with Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeErrorModal();
                        closeSuccessModal();
                    }
                });
            });
            
            // Basic data + state
            let products = [];
            let categories = [];
            let categoryNameMap = {};
            let activeCategory = 'all';
            let units = [];
            let unitNameMap = {};
            let activeUnit = 'all';
            let cart = [];
            let discountAmount = 0;

            async function loadCategories() {
                try {
                    const res = await fetch('../Controllers/getControllers/getCategories.php');
                    const data = await res.json();
                    if (data?.success && Array.isArray(data.categories)) {
                        categories = data.categories;
                        categoryNameMap = Object.fromEntries(data.categories.map(cat => [String(cat.category_id), cat.category_name]));
                        renderCategoryChips();
                    } else {
                        // Fallback to sample categories if API fails
                        categories = [
                            { category_id: 1, category_name: 'Pain Reliever' },
                            { category_id: 2, category_name: 'Prescription (Rx)' },
                            { category_id: 3, category_name: 'Over-the-Counter' },
                            { category_id: 4, category_name: 'Vitamins' },
                            { category_id: 5, category_name: 'Personal Care' },
                            { category_id: 6, category_name: 'Medical Supplies' },
                            { category_id: 7, category_name: 'Antibiotics' },
                            { category_id: 8, category_name: 'Cold & Flu' },
                            { category_id: 9, category_name: 'Digestive Health' },
                            { category_id: 10, category_name: 'Skin Care' },
                            { category_id: 11, category_name: 'First Aid' }
                        ];
                        categoryNameMap = Object.fromEntries(categories.map(cat => [String(cat.category_id), cat.category_name]));
                        renderCategoryChips();
                    }
                } catch (e) {
                    console.error('Error loading categories:', e);
                    // Fallback to sample categories
                    categories = [
                        { category_id: 1, category_name: 'Pain Reliever' },
                        { category_id: 2, category_name: 'Prescription (Rx)' },
                        { category_id: 3, category_name: 'Over-the-Counter' },
                        { category_id: 4, category_name: 'Vitamins' },
                        { category_id: 5, category_name: 'Personal Care' },
                        { category_id: 6, category_name: 'Medical Supplies' },
                        { category_id: 7, category_name: 'Antibiotics' },
                        { category_id: 8, category_name: 'Cold & Flu' },
                        { category_id: 9, category_name: 'Digestive Health' },
                        { category_id: 10, category_name: 'Skin Care' },
                        { category_id: 11, category_name: 'First Aid' }
                    ];
                    categoryNameMap = Object.fromEntries(categories.map(cat => [String(cat.category_id), cat.category_name]));
                    renderCategoryChips();
                }
            }

            async function loadUnits() {
                try {
                    const res = await fetch('../Controllers/getControllers/getUnits.php');
                    const data = await res.json();
                    if (data?.success && Array.isArray(data.units)) {
                        units = data.units;
                        unitNameMap = Object.fromEntries(data.units.map(unit => [String(unit.unit_id), unit.unit_name]));
                        renderUnitFilter();
                    } else {
                        // Fallback to sample units if API fails
                        units = [
                            { unit_id: 1, unit_name: 'Tablet' },
                            { unit_id: 2, unit_name: 'Capsule' },
                            { unit_id: 3, unit_name: 'Syrup' },
                            { unit_id: 4, unit_name: 'Injection' },
                            { unit_id: 5, unit_name: 'Cream' },
                            { unit_id: 6, unit_name: 'Ointment' },
                            { unit_id: 7, unit_name: 'Drops' },
                            { unit_id: 8, unit_name: 'Powder' },
                            { unit_id: 9, unit_name: 'Patch' },
                            { unit_id: 10, unit_name: 'Suppository' }
                        ];
                        unitNameMap = Object.fromEntries(units.map(unit => [String(unit.unit_id), unit.unit_name]));
                        renderUnitFilter();
                    }
                } catch (e) {
                    console.error('Error loading units:', e);
                    // Fallback to sample units
                    units = [
                        { unit_id: 1, unit_name: 'Tablet' },
                        { unit_id: 2, unit_name: 'Capsule' },
                        { unit_id: 3, unit_name: 'Syrup' },
                        { unit_id: 4, unit_name: 'Injection' },
                        { unit_id: 5, unit_name: 'Cream' },
                        { unit_id: 6, unit_name: 'Ointment' },
                        { unit_id: 7, unit_name: 'Drops' },
                        { unit_id: 8, unit_name: 'Powder' },
                        { unit_id: 9, unit_name: 'Patch' },
                        { unit_id: 10, unit_name: 'Suppository' }
                    ];
                    unitNameMap = Object.fromEntries(units.map(unit => [String(unit.unit_id), unit.unit_name]));
                    renderUnitFilter();
                }
            }

            function renderUnitFilter() {
                const unitFilter = document.getElementById('unitFilter');
                if (!unitFilter) return;

                // Clear existing options except "All Units"
                unitFilter.innerHTML = '<option value="all">All Units</option>';

                if (units.length) {
                    units.forEach(unit => {
                        const option = document.createElement('option');
                        option.value = String(unit.unit_id);
                        option.textContent = unit.unit_name;
                        unitFilter.appendChild(option);
                    });
                }
            }

            function renderCategoryChips() {
                const chipContainer = document.getElementById('categoryChips');
                if (!chipContainer) return;

                const chips = [
                    `<button class="chip ${activeCategory === 'all' ? 'chip-active' : ''}" data-cat="all">All Medicines</button>`
                ];

                if (categories.length) {
                    categories.forEach(cat => {
                        const id = String(cat.category_id);
                        const label = cat.category_name || 'Category';
                        chips.push(`<button class="chip ${activeCategory === id ? 'chip-active' : ''}" data-cat="${id}">${label}</button>`);
                    });
                }

                chipContainer.innerHTML = chips.join('');
            }

            function categoryIdForProduct(rawProduct) {
                if (!rawProduct) return 'all';
                
                // First try to use category_id if available (this is the primary method)
                if (rawProduct.category_id != null && rawProduct.category_id !== undefined) {
                    return String(rawProduct.category_id);
                }
                
                // Then try to match by category_name
                if (rawProduct.category_name) {
                    const match = Object.entries(categoryNameMap).find(([id, name]) => 
                        name && (name || '').toLowerCase().trim() === (rawProduct.category_name || '').toLowerCase().trim()
                    );
                    if (match) {
                        return match[0];
                    }
                }
                
                // Fallback to keyword-based categorization (only if no category_id or category_name)
                return fallbackCategoryKey(rawProduct);
            }

            function fallbackCategoryKey(p) {
                const name = ((p.brand_name || '') + ' ' + (p.generic_name || '')).toLowerCase();
                if (name.includes('rx') || name.includes('prescription')) return '2'; // Prescription (Rx)
                if (name.includes('vitamin')) return '4'; // Vitamins
                if (name.includes('bandage') || name.includes('mask') || name.includes('syringe') || name.includes('first aid')) return '11'; // First Aid
                if (name.includes('shampoo') || name.includes('soap') || name.includes('skin')) return '10'; // Skin Care
                if (name.includes('paracetamol') || name.includes('ibuprofen') || name.includes('pain') || name.includes('relief')) return '1'; // Pain Reliever
                if (name.includes('otc') || name.includes('over-the-counter')) return '3'; // Over-the-Counter
                if (name.includes('antibiotic') || name.includes('amoxicillin') || name.includes('penicillin')) return '7'; // Antibiotics
                if (name.includes('cold') || name.includes('flu') || name.includes('cough') || name.includes('fever')) return '8'; // Cold & Flu
                if (name.includes('digestive') || name.includes('stomach') || name.includes('acid') || name.includes('antacid')) return '9'; // Digestive Health
                if (name.includes('supplies') || name.includes('medical')) return '6'; // Medical Supplies
                if (name.includes('personal') || name.includes('care')) return '5'; // Personal Care
                return 'all';
            }

            function getUnitFromStrength(strength) {
                if (!strength) return 'Unit';
                const str = strength.toLowerCase();
                if (str.includes('tablet') || str.includes('tab')) return 'Tablet';
                if (str.includes('capsule') || str.includes('cap')) return 'Capsule';
                if (str.includes('syrup') || str.includes('suspension') || str.includes('elixir')) return 'Syrup';
                if (str.includes('injection') || str.includes('inject') || str.includes('ampule')) return 'Injection';
                if (str.includes('cream') || str.includes('topical cream')) return 'Cream';
                if (str.includes('ointment') || str.includes('salve')) return 'Ointment';
                if (str.includes('drops') || str.includes('eye drops') || str.includes('ear drops')) return 'Drops';
                if (str.includes('powder') || str.includes('sachet')) return 'Powder';
                if (str.includes('patch') || str.includes('transdermal')) return 'Patch';
                if (str.includes('suppository') || str.includes('supp')) return 'Suppository';
                if (str.includes('gel')) return 'Gel';
                if (str.includes('lotion')) return 'Lotion';
                if (str.includes('spray') || str.includes('aerosol')) return 'Spray';
                if (str.includes('inhaler') || str.includes('puffer')) return 'Inhaler';
                if (str.includes('strip') || str.includes('blister')) return 'Strip';
                return 'Unit';
            }

            // Load products from API, fallback to samples
            async function loadProducts() {
                try {
                    const res = await fetch('../Controllers/getControllers/getPOSData.php');
                    const data = await res.json();
                    if (data?.success && Array.isArray(data.products)) {
                        // First map the products
                        let mappedProducts = data.products.map(p => {
                            // Determine category - prioritize category_id from database
                            const categoryId = p.category_id != null ? String(p.category_id) : categoryIdForProduct(p);
                            return {
                                id: String(p.product_id),
                                name: (p.brand_name || 'Medicine') + (p.strength ? ' ' + p.strength : ''),
                                price: parseFloat((p.price ?? p.selling_price_unit ?? 0)) || 0,
                                stock: Math.floor(parseFloat((p.total_stock ?? p.total_units_available ?? 0)) || 0),
                                category: categoryId,
                                generic_name: p.generic_name || '',
                                category_id: p.category_id || null,
                                category_name: p.category_name || '',
                            unit_id: p.unit_id || null,
                            unit_name: p.unit_name || getUnitFromStrength(p.strength),
                            unit_type: p.unit_type || null, // From view
                            package_type: p.package_type || null, // From view
                            strength: p.strength || '',
                            barcode: p.barcode_value || p.barcode || null,
                            batch_no: p.batch_no || null,
                            manufacturing_date: p.manufacturing_date || null,
                            stock_status: p.stock_status || 'Available',
                            stock_condition: p.stock_condition || '',
                            is_low_stock: Boolean(p.is_low_stock || (typeof p.stock_status === 'string' && p.stock_status.toLowerCase() === 'low stock') || (typeof p.stock_condition === 'string' && p.stock_condition.toLowerCase() === 'low stock')),
                            product_threshold: Number(p.product_threshold ?? p.threshold ?? 0) || 0,
                            total_units_available: Number(p.total_units_available ?? p.total_stock ?? 0) || 0,
                            // Inventory availability
                            available_units_pharmacy: Number(p.available_units_pharmacy ?? p.pharmacy_units_balance ?? 0) || 0,
                            available_packages: Number(p.available_packages ?? p.storage_packages_balance ?? 0) || 0,
                            available_units_storage: Number(p.available_units_storage ?? p.storage_units_balance ?? 0) || 0,
                            selling_price_unit: parseFloat((p.selling_price_unit ?? p.price ?? 0)) || 0,
                            selling_price_package: parseFloat((p.selling_price_package ?? p.price ?? 0)) || 0,
                            batch_id: p.batch_id || null,
                            batch_count: p.batch_count || 0,
                            expiry_date: p.expiry_date || p.earliest_expiry_date || null,
                            received_date: p.received_date || p.delivery_date || p.earliest_delivery_date || null,
                            delivery_date: p.delivery_date || p.earliest_delivery_date || p.received_date || null,
                            expire_threshold: Number(p.expire_threshold ?? 0) || 0,
                            is_expiring_soon: Boolean(p.is_expiring_soon),
                            days_until_expiry: Number.isFinite(p.days_until_expiry) ? Number(p.days_until_expiry) : null
                            };
                        });
                        
                        // Auto-remove expired batches before aggregation
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        const beforeCount = mappedProducts.length;
                        mappedProducts = mappedProducts.filter(p => {
                            if (p.expiry_date) {
                                const expiryDate = new Date(p.expiry_date);
                                if (!isNaN(expiryDate) && expiryDate < today) {
                                    return false;
                                }
                            }
                            if (typeof p.stock_status === 'string' && p.stock_status.toLowerCase() === 'expired') {
                                return false;
                            }
                            return true;
                        });
                        if (beforeCount !== mappedProducts.length) {
                            console.log(`Removed ${beforeCount - mappedProducts.length} expired batch entries`);
                        }
                        
                        // Backend aggregates by product_id, but if there are still duplicates, merge them
                        const productMap = new Map();
                        mappedProducts.forEach(p => {
                            const existing = productMap.get(p.id);
                            if (existing) {
                                // Merge stock values (sum them) if backend didn't aggregate properly
                                existing.stock = existing.stock + p.stock;
                                existing.available_units_pharmacy = existing.available_units_pharmacy + p.available_units_pharmacy;
                                existing.available_packages = existing.available_packages + p.available_packages;
                                existing.available_units_storage = existing.available_units_storage + p.available_units_storage;
                                existing.batch_count = existing.batch_count + (p.batch_count || 1);
                                
                                // Preserve category information (use the one with category_id if available)
                                if (p.category_id && !existing.category_id) {
                                    existing.category_id = p.category_id;
                                    existing.category = String(p.category_id);
                                }
                                if (p.category_name && !existing.category_name) {
                                    existing.category_name = p.category_name;
                                }
                                
                                // Preserve earliest expiry_date (FEFO)
                                if (p.expiry_date && (!existing.expiry_date || new Date(p.expiry_date) < new Date(existing.expiry_date))) {
                                    existing.expiry_date = p.expiry_date;
                                }
                                
                                // Preserve earliest delivery_date (FIFO)
                                const pDelivery = p.delivery_date || p.received_date;
                                const existingDelivery = existing.delivery_date || existing.received_date;
                                if (pDelivery && (!existingDelivery || new Date(pDelivery) < new Date(existingDelivery))) {
                                    existing.delivery_date = pDelivery;
                                    existing.received_date = pDelivery;
                                }
                                
                                // Use the better price if available
                                if (p.selling_price_unit && (!existing.selling_price_unit || p.selling_price_unit > 0)) {
                                    existing.selling_price_unit = p.selling_price_unit;
                                }
                                if (p.selling_price_package && (!existing.selling_price_package || p.selling_price_package > 0)) {
                                    existing.selling_price_package = p.selling_price_package;
                                }
                            } else {
                                // Ensure category is set as string of category_id for proper filtering
                                if (p.category_id) {
                                    p.category = String(p.category_id);
                                }
                                productMap.set(p.id, p);
                            }

                        });
                        products = Array.from(productMap.values());
                        console.log(`Loaded ${products.length} unique products from API (${mappedProducts.length} raw products)`);
                    } else {
                        products = sampleProducts();
                    }
                } catch (e) {
                    console.error('Error loading products:', e);
                    products = sampleProducts();
                }
                renderProducts();
            }

            function sampleProducts() {
                return [
                    { id: '1', name: 'Paracetamol 500mg', generic_name: 'Analgesic/Antipyretic', price: 1.20, stock: 120, category: 'otc', unit_id: 1, unit_name: 'Tablet', strength: '500mg', discountPercent: 10 },
                    { id: '2', name: 'Amoxicillin 500mg', generic_name: 'Antibiotic (Rx)', price: 3.50, stock: 45, category: 'rx', unit_id: 2, unit_name: 'Capsule', strength: '500mg' },
                    { id: '3', name: 'Surgical Face Mask', generic_name: 'Medical Mask (50s)', price: 2.80, stock: 80, category: 'supplies', unit_id: 15, unit_name: 'Strip', strength: '50s', discountPercent: 15 },
                    { id: '4', name: 'Vitamin C 1000mg', generic_name: 'Ascorbic Acid', price: 4.20, stock: 60, category: 'vitamins', unit_id: 1, unit_name: 'Tablet', strength: '1000mg' },
                    { id: '5', name: 'Cetirizine 10mg', generic_name: 'Antihistamine', price: 1.75, stock: 75, category: 'otc', unit_id: 1, unit_name: 'Tablet', strength: '10mg' },
                    { id: '6', name: 'Digital Thermometer', generic_name: 'Medical Thermometer', price: 6.90, stock: 20, category: 'supplies', unit_id: 16, unit_name: 'Piece', strength: '1 unit' },
                    { id: '7', name: 'Isopropyl Alcohol', generic_name: 'Antiseptic 500ml', price: 2.10, stock: 100, category: 'personal', unit_id: 13, unit_name: 'Spray', strength: '500ml', discountPercent: 5 },
                    { id: '8', name: 'Blood Pressure Monitor', generic_name: 'Digital BP Monitor', price: 24.99, stock: 10, category: 'supplies', unit_id: 16, unit_name: 'Piece', strength: '1 unit' },
                ];
            }

            // Barcode scanner logic
            let scannerDeviceConnected = true;
            let lastScanTime = 0;
            let scanBuffer = '';
            let isUserTyping = false;
            let typingTimeout = null;

            function initializeScanner() {
                const barcodeInput = document.getElementById('barcodeInput');
                if (!barcodeInput) return;
                
                // Remove old listeners
                document.removeEventListener('keydown', handleScannerInput);
                barcodeInput.removeEventListener('input', handleBarcodeInput);
                barcodeInput.removeEventListener('focus', handleBarcodeFocus);
                barcodeInput.removeEventListener('blur', handleBarcodeBlur);
                
                // Add new listeners
                document.addEventListener('keydown', handleScannerInput);
                barcodeInput.addEventListener('input', handleBarcodeInput);
                barcodeInput.addEventListener('focus', handleBarcodeFocus);
                barcodeInput.addEventListener('blur', handleBarcodeBlur);
                
                // Focus on barcode input for scanner
                focusScannerInput();
                updateScannerStatus();
            }

            function focusScannerInput() {
                const barcodeInput = document.getElementById('barcodeInput');
                if (barcodeInput) {
                    // Only focus if user is not actively typing elsewhere
                    if (!isUserTyping) {
                        barcodeInput.focus();
                    }
                }
            }

            function handleBarcodeInput(event) {
                // User is typing manually in the barcode input
                // Check if this is slow typing (user) vs fast scanning (scanner)
                const currentTime = Date.now();
                const timeDiff = currentTime - lastScanTime;
                
                // If time between keystrokes is > 50ms, it's likely manual typing
                if (timeDiff > 50) {
                    isUserTyping = true;
                    scanBuffer = ''; // Clear scanner buffer when user types manually
                }
                
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    isUserTyping = false;
                }, 2000); // Reset after 2 seconds of no typing
            }

            function handleBarcodeFocus(event) {
                // When barcode input is focused, check if user is typing
                // Don't immediately set isUserTyping to true - wait for actual input
                // This allows scanner to work when input is focused
            }

            function handleBarcodeBlur(event) {
                // When barcode input loses focus, reset typing flag after a delay
                setTimeout(() => {
                    isUserTyping = false;
                    scanBuffer = '';
                }, 500);
            }

            function updateScannerStatus(status = null) {
                const statusEl = document.getElementById('scannerStatus');
                const deviceStatusEl = document.getElementById('scannerDeviceStatus');
                const modeEl = document.getElementById('scannerMode');
                
                if (!statusEl) return; // Element not found, skip update
                
                if (status === 'processing') {
                    statusEl.className = 'w-3 h-3 bg-blue-500 rounded-full animate-spin';
                    if (deviceStatusEl) deviceStatusEl.className = 'w-3 h-3 bg-blue-500 rounded-full animate-spin';
                    if (modeEl) modeEl.textContent = 'Processing...';
                } else if (status === 'success') {
                    statusEl.className = 'w-3 h-3 bg-green-500 rounded-full';
                    if (deviceStatusEl) deviceStatusEl.className = 'w-3 h-3 bg-green-500 rounded-full';
                    if (modeEl) modeEl.textContent = 'Success!';
                } else if (status === 'error') {
                    statusEl.className = 'w-3 h-3 bg-red-500 rounded-full';
                    if (deviceStatusEl) deviceStatusEl.className = 'w-3 h-3 bg-red-500 rounded-full';
                    if (modeEl) modeEl.textContent = 'Error';
                } else if (scannerDeviceConnected) {
                    statusEl.className = 'w-3 h-3 bg-green-500 rounded-full animate-pulse';
                    if (deviceStatusEl) deviceStatusEl.className = 'w-3 h-3 bg-green-500 rounded-full animate-pulse';
                    if (modeEl) modeEl.textContent = 'Auto-detect';
                } else {
                    statusEl.className = 'w-3 h-3 bg-yellow-500 rounded-full animate-pulse';
                    if (deviceStatusEl) deviceStatusEl.className = 'w-3 h-3 bg-yellow-500 rounded-full animate-pulse';
                    if (modeEl) modeEl.textContent = 'Manual Only';
                }
            }

            function handleScannerInput(event) {
                const barcodeInput = document.getElementById('barcodeInput');
                if (!barcodeInput) return;
                
                // Don't process if user is actively typing in any input field (except barcodeInput when not typing)
                const activeElement = document.activeElement;
                const isInputField = activeElement && (
                    activeElement.tagName === 'INPUT' || 
                    activeElement.tagName === 'TEXTAREA' ||
                    activeElement.isContentEditable
                );
                
                // If user is typing in an input field other than barcodeInput, let them type normally
                if (isInputField && activeElement !== barcodeInput) {
                    return;
                }
                
                // If user is typing in barcodeInput manually, let them type normally
                if (activeElement === barcodeInput && isUserTyping) {
                    return;
                }
                
                // Ignore special keys except Enter
                if (event.key.length > 1 && event.key !== 'Enter') {
                    return;
                }
                
                const currentTime = Date.now();
                const timeDiff = currentTime - lastScanTime;
                
                // Reset buffer if too much time has passed (user stopped scanning)
                if (timeDiff > 100) {
                    scanBuffer = '';
                }
                lastScanTime = currentTime;
                
                // Handle scanner input
                if (event.key !== 'Enter') {
                    // Character input - this is likely from scanner
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Add character to buffer
                    scanBuffer += event.key;
                    
                    // Update barcode input field in real-time and focus it
                    barcodeInput.value = scanBuffer;
                    barcodeInput.focus();
                } else if (event.key === 'Enter' && scanBuffer.length >= 3) {
                    // Complete scan detected (Enter pressed with valid barcode)
                    event.preventDefault();
                    event.stopPropagation();
                    
                    updateScannerStatus('processing');
                    const scannedBarcode = scanBuffer.trim();
                    scanBuffer = '';
                    barcodeInput.value = scannedBarcode;
                    
                    // Process the scanned barcode
                    processScannedBarcode(scannedBarcode);
                    
                    setTimeout(() => {
                        updateScannerStatus();
                        // Clear input after processing
                        barcodeInput.value = '';
                        scanBuffer = '';
                        // Refocus for next scan
                        if (!isUserTyping) {
                            barcodeInput.focus();
                        }
                    }, 1500);
                } else if (event.key === 'Enter') {
                    // Enter pressed but buffer too short - might be user pressing Enter manually
                    // Clear buffer and let normal Enter behavior work
                    scanBuffer = '';
                }
            }

            // Helper function to clean barcode by removing "00" prefix/suffix
            function cleanBarcode(barcode) {
                if (!barcode) return '';
                let cleaned = barcode.trim();
                // Remove "00" from the beginning
                while (cleaned.startsWith('00') && cleaned.length > 2) {
                    cleaned = cleaned.substring(2);
                }
                // Remove "00" from the end
                while (cleaned.endsWith('00') && cleaned.length > 2) {
                    cleaned = cleaned.substring(0, cleaned.length - 2);
                }
                return cleaned.trim();
            }

            function processScannedBarcode(barcode) {
                const barcodeInput = document.getElementById('barcodeInput');
                if (!barcodeInput) return;
                
                // Clean the barcode and set it in the input
                const cleanedBarcode = cleanBarcode(barcode);
                barcodeInput.value = cleanedBarcode;
                
                // Process the barcode
                scanBarcode();
            }

            async function scanBarcode() {
                const barcodeInput = document.getElementById('barcodeInput');
                if (!barcodeInput) return;
                
                const barcode = cleanBarcode(barcodeInput.value);
                if (!barcode || barcode.length < 3) { 
                    barcodeInput.focus(); 
                    return; 
                }
                
                updateScannerStatus('processing');
                
                try {
                    // Use cleaned barcode for the API call
                    const cleanedBarcode = cleanBarcode(barcode);
                    // Update input field with cleaned value
                    barcodeInput.value = cleanedBarcode;
                    const res = await fetch('../Controllers/barcodeScannerController.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ barcode: cleanedBarcode })
                    });
                    
                    if (!res.ok) {
                        throw new Error('Failed to scan barcode');
                    }
                    
                    const data = await res.json();
                    
                    if (data.success && data.product) {
                        const p = data.product;
                        
                        // NOTE: We will determine stock via our local product dataset (more up-to-date for UI)
                        // and pick sale type dynamically rather than trusting p.has_stock/p.sale_type blindly.
                        
                        // Find the product in our products array to get full details
                        const product = products.find(prod => String(prod.id) === String(p.product_id));
                        
                        if (product) {
                            // Decide availability and sale type from current UI inventory snapshot
                            const hasUnits = Number(product.available_units_pharmacy || 0) > 0;
                            const hasPackages = Number(product.available_packages || 0) > 0;
                            
                            if (!hasUnits && !hasPackages) {
                                showErrorModal('Out of Stock', 'Product found but out of stock. Please restock.');
                                updateScannerStatus('error');
                                barcodeInput.value = '';
                                return;
                            }
                            
                            // Prefer unit sale if units are available, else package if packages are available
                            const saleType = hasUnits ? 'unit' : (hasPackages ? 'package' : (p.sale_type || 'unit'));
                            
                            // Determine price based on sale type
                            const price = saleType === 'package' 
                                ? (product.selling_price_package || product.price) 
                                : (product.selling_price_unit || product.price);
                            
                            // Check stock status before adding to cart
                            const stockStatus = product.stock_status || 'Available';
                            if (stockStatus === 'Expired') {
                                showErrorModal('Product Expired', 'This product has expired and cannot be sold.');
                                updateScannerStatus('error');
                                barcodeInput.value = '';
                                return;
                            }
                            if (stockStatus === 'Out of Stock') {
                                showErrorModal('Out of Stock', 'This product is out of stock.');
                                updateScannerStatus('error');
                                barcodeInput.value = '';
                                return;
                            }
                            
                            // Check stock availability for the determined sale type (with graceful fallback)
                            if (saleType === 'package' && (!product.available_packages || product.available_packages <= 0)) {
                                // If package sale not available, try unit sale
                                if (product.available_units_pharmacy > 0) {
                                    // Fallback to unit sale
                                    addBarcodeProductToCart(product, 'unit', p.selling_price_unit || price);
                                } else {
                                    showErrorModal('No Stock Available', 'No stock available for this product.');
                                    updateScannerStatus('error');
                                }
                            } else if (saleType === 'unit' && (!product.available_units_pharmacy || product.available_units_pharmacy <= 0)) {
                                // If unit sale not available, try package sale
                                if (product.available_packages > 0) {
                                    // Fallback to package sale
                                    addBarcodeProductToCart(product, 'package', p.selling_price_package || price);
                                } else {
                                    showErrorModal('No Stock Available', 'No stock available for this product.');
                                    updateScannerStatus('error');
                                }
                            } else {
                                // Use the determined sale type
                                addBarcodeProductToCart(product, saleType, price);
                            }
                            
                            barcodeInput.value = '';
                            updateScannerStatus('success');
                        } else {
                            // Product not in current list – attempt a quick refresh and retry
                            try {
                                await loadProducts();
                            } catch (_) {}
                            const refreshed = products.find(prod => String(prod.id) === String(p.product_id));
                            if (refreshed) {
                                const rHasUnits = Number(refreshed.available_units_pharmacy || 0) > 0;
                                const rHasPackages = Number(refreshed.available_packages || 0) > 0;
                                if (!rHasUnits && !rHasPackages) {
                                    showErrorModal('Out of Stock', 'Product found but out of stock. Please restock.');
                                    updateScannerStatus('error');
                                } else {
                                    const chosen = rHasUnits ? 'unit' : 'package';
                                    const rPrice = chosen === 'package'
                                        ? (refreshed.selling_price_package || refreshed.price || 0)
                                        : (refreshed.selling_price_unit || refreshed.price || 0);
                                    addBarcodeProductToCart(refreshed, chosen, rPrice);
                                    barcodeInput.value = '';
                                    updateScannerStatus('success');
                                }
                                return;
                            }
                            
                            // As a final fallback, use payload stock hints (if provided) to add a minimal product
                            const fallbackUnits = Number(
                                p.available_units_pharmacy ??
                                p.pharmacy_units_balance ??
                                p.quantity_units_balance ??
                                p.total_units_available ??
                                p.total_stock ??
                                0
                            ) || 0;
                            const fallbackPackages = Number(
                                p.available_packages ??
                                p.storage_packages_balance ??
                                p.storage_packages ??
                                0
                            ) || 0;
                            const storageUnitsOnly = Number(p.available_units_storage ?? p.storage_units_balance ?? p.storage_units ?? 0) || 0;
                            
                            const hasUnits = fallbackUnits > 0;
                            // Consider package sale available if we have explicit packages OR storage units (as last resort)
                            const hasPackages = (fallbackPackages > 0) || (!hasUnits && storageUnitsOnly > 0);
                            
                            if (!hasUnits && !hasPackages) {
                                showErrorModal('Product Not Available', 'Product found for barcode but shows no available stock. Please refresh the page.');
                                updateScannerStatus('error');
                                return;
                            }
                            
                            const saleType = hasUnits ? 'unit' : 'package';
                            const fallbackProduct = {
                                id: String(p.product_id),
                                name: (p.brand_name || 'Medicine') + (p.strength ? ' ' + p.strength : ''),
                                generic_name: p.generic_name || '',
                                price: Number(saleType === 'package' ? (p.selling_price_package ?? p.price ?? 0) : (p.selling_price_unit ?? p.price ?? 0)) || 0,
                                available_units_pharmacy: hasUnits ? fallbackUnits : 0,
                                available_packages: hasPackages ? (fallbackPackages || Math.max(0, storageUnitsOnly)) : 0,
                                selling_price_unit: Number(p.selling_price_unit ?? p.price ?? 0) || 0,
                                selling_price_package: Number(p.selling_price_package ?? p.price ?? 0) || 0
                            };
                            
                            addBarcodeProductToCart(fallbackProduct, saleType, fallbackProduct.price);
                            barcodeInput.value = '';
                            updateScannerStatus('success');
                        }
                    } else {
                        showErrorModal('Barcode Not Found', data.error || 'Product not found');
                        updateScannerStatus('error');
                    }
                } catch (error) {
                    console.error('Barcode scan error:', error);
                    showErrorModal('Scan Error', 'Error scanning barcode. Please try again.');
                    updateScannerStatus('error');
                } finally {
                    setTimeout(() => updateScannerStatus(), 1500);
                }
            }
            
            function addBarcodeProductToCart(product, saleType, price) {
                // Validate sale type availability
                if (saleType === 'unit' && (!product.available_units_pharmacy || product.available_units_pharmacy <= 0)) {
                    showErrorModal('No Units Available', 'No units available in pharmacy inventory.');
                    return;
                }
                if (saleType === 'package' && (!product.available_packages || product.available_packages <= 0)) {
                    showErrorModal('No Packages Available', 'No packages available in storage inventory.');
                    return;
                }
                
                // Ensure price is a number
                const finalPrice = Number(price) || Number(product.selling_price_unit || product.price || 0);
                
                // Batch/delivery selection is handled by backend using FEFO/FIFO logic
                // We don't need to specify batch_id here - the controller will select the appropriate delivery
                
                // Check if item already exists with same sale type (batch selection happens on backend)
                const existing = cart.find(i => String(i.id) === String(product.id) && i.sale_type === saleType);
                if (existing) {
                    const maxQty = saleType === 'unit' ? Number(product.available_units_pharmacy || 0) : Number(product.available_packages || 0);
                    if (Number(existing.quantity) < maxQty) {
                        existing.quantity = Number(existing.quantity) + 1;
                        updateCart();
                    } else {
                        showErrorModal('Maximum Quantity Reached', 'Maximum available quantity reached for this item.');
                    }
                } else {
                    const hasDisc = !!product.discountPercent;
                    const originalPrice = hasDisc ? Number(product.price) / (product.discountPercent ? (1 - product.discountPercent/100) : 1) : Number(product.price);
                    
                    cart.push({ 
                        id: String(product.id), 
                        name: product.name, 
                        generic_name: product.generic_name || '',
                        price: finalPrice,
                        originalPrice: originalPrice, 
                        quantity: 1, 
                        stock: saleType === 'unit' ? Number(product.available_units_pharmacy || 0) : Number(product.available_packages || 0),
                        sale_type: saleType, // 'unit' or 'package'
                        batch_id: null, // Backend will determine using FEFO/FIFO
                        image: imageForProduct(product) 
                    });
                    updateCart();
                }
            }

            function renderProducts() {
                const grid = document.getElementById('productGrid');
                const searchInput = document.getElementById('searchInput');
                const unitFilter = document.getElementById('unitFilter');
                const term = (searchInput ? searchInput.value : '').toLowerCase().trim();
                const selectedUnit = unitFilter ? unitFilter.value : 'all';
                
                let filtered = products.filter(p => {
                    // Category filter - match by category_id (as string) or category field
                    const productCategoryId = p.category_id ? String(p.category_id) : (p.category || 'all');
                    const byCategory = activeCategory === 'all' ? true : (productCategoryId === activeCategory || p.category === activeCategory);
                    
                    // Unit filter
                    const byUnit = selectedUnit === 'all' ? true : p.unit_id == selectedUnit;
                    
                    // Search filter - search in name, generic name, category, and unit
                    const bySearch = !term || 
                        p.name.toLowerCase().includes(term) ||
                        (p.generic_name && p.generic_name.toLowerCase().includes(term)) ||
                        (p.category_name && p.category_name.toLowerCase().includes(term)) ||
                        (p.unit_name && p.unit_name.toLowerCase().includes(term));
                    
                    // Stock filter - only show products with available stock (pharmacy units OR storage packages)
                    const hasPharmacyUnits = (p.available_units_pharmacy || 0) > 0;
                    const hasStoragePackages = (p.available_packages || 0) > 0;
                    const hasAvailableStock = hasPharmacyUnits || hasStoragePackages;
                    const byStock = hasAvailableStock;
                    
                    // Only treat as unavailable if truly out of stock (no non-expired inventory)
                    const stockStatus = (p.stock_status || '').toLowerCase();
                    const isOutOfStock = stockStatus === 'out of stock' || !hasAvailableStock;
                    const byStockStatus = !isOutOfStock;
                    
                    return byCategory && byUnit && bySearch && byStock && byStockStatus;
                });
                
                // Sort products by FEFO (First Expiry, First Out) then FIFO (First In, First Out)
                filtered.sort((a, b) => {
                    // FEFO: Sort by expiry_date (earliest expiry first)
                    const aExpiry = a.expiry_date ? new Date(a.expiry_date) : new Date('9999-12-31');
                    const bExpiry = b.expiry_date ? new Date(b.expiry_date) : new Date('9999-12-31');
                    if (aExpiry.getTime() !== bExpiry.getTime()) {
                        return aExpiry.getTime() - bExpiry.getTime();
                    }
                    
                    // FIFO: If expiry dates are same, sort by delivery_date (earliest delivery first)
                    const aDelivery = a.delivery_date || a.received_date;
                    const bDelivery = b.delivery_date || b.received_date;
                    if (aDelivery && bDelivery) {
                        const aDelDate = new Date(aDelivery);
                        const bDelDate = new Date(bDelivery);
                        if (aDelDate.getTime() !== bDelDate.getTime()) {
                            return aDelDate.getTime() - bDelDate.getTime();
                        }
                    } else if (aDelivery && !bDelivery) {
                        return -1; // a has delivery date, b doesn't - a comes first
                    } else if (!aDelivery && bDelivery) {
                        return 1; // b has delivery date, a doesn't - b comes first
                    }
                    
                    // If both expiry and delivery dates are same, sort alphabetically by name
                    return a.name.localeCompare(b.name);
                });
                
                // Limit to 10 products for display
                const totalFiltered = filtered.length;
                const displayLimit = 10;
                const productsToDisplay = filtered.slice(0, displayLimit);
                
                // Update product count display
                const productCount = document.querySelector('.product-count');
                if (productCount) {
                    const categoryName = activeCategory === 'all' ? 'All Categories' :
                        (categoryNameMap[activeCategory] || 'Unknown Category');
                    if (totalFiltered > displayLimit) {
                        productCount.textContent = `Showing ${productsToDisplay.length} of ${totalFiltered} products in ${categoryName}`;
                    } else {
                        productCount.textContent = `${totalFiltered} products found in ${categoryName}`;
                    }
                }
                
                // Render products or show no results message
                const tableBody = document.getElementById('productTableBody');
                if (!tableBody) return;
                
                if (totalFiltered === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center text-gray-500">
                                <svg class="w-20 h-20 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709M15 6.291A7.962 7.962 0 0112 4c-2.34 0-4.29 1.009-5.824 2.709M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"/>
                                </svg>
                                <p class="text-lg font-semibold">No products found</p>
                                <p class="text-sm mt-2">Try adjusting your search or category filter</p>
                            </td>
                        </tr>
                    `;
                } else {
                    let tableHTML = productsToDisplay.map(p => tableRowHTML(p)).join('');
                    
                    // Add message row if there are more products than the limit
                    if (totalFiltered > displayLimit) {
                        tableHTML += `
                            <tr class="bg-blue-50">
                                <td colspan="7" class="px-4 py-3 text-center text-sm text-blue-700 font-medium">
                                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Showing first ${displayLimit} products. Use search or filters to find more products.
                                </td>
                            </tr>
                        `;
                    }
                    
                    tableBody.innerHTML = tableHTML;
                }
            }

            function tableRowHTML(p) {
                const hasDisc = !!p.discountPercent;
                const oldPrice = hasDisc ? p.price / (1 - p.discountPercent/100) : null;
                // Determine available stock types
                const hasPharmacyUnits = (p.available_units_pharmacy || 0) > 0;
                const hasStoragePackages = (p.available_packages || 0) > 0;
                const hasStorageUnits = (p.available_units_storage || 0) > 0;
                const stockStatusRaw = typeof p.stock_status === 'string' ? p.stock_status.trim() : '';
                const stockStatus = stockStatusRaw || 'Available';
                const stockStatusLower = stockStatus.toLowerCase();
                const isLowStock = Boolean(p.is_low_stock || stockStatusLower === 'low stock' || (typeof p.stock_condition === 'string' && p.stock_condition.toLowerCase() === 'low stock'));
                const threshold = Number(p.product_threshold || 0);
                const pharmacyUnitsAvailable = Number(p.available_units_pharmacy || 0);
                
                // Calculate expiry status for badge and stock status
                let expiryBadge = '';
                let stockStatusBadge = '';
                let isExpiringSoon = false;
                
                const apiExpiringSoon = Boolean(p.is_expiring_soon);
                const rawThreshold = Number(p.expire_threshold ?? 0);
                const expireThreshold = rawThreshold > 0 ? rawThreshold : 30;
                let daysUntilExpiry = Number.isFinite(p.days_until_expiry) ? Number(p.days_until_expiry) : null;

                // Show stock status badge
                if (!hasPharmacyUnits && !hasStoragePackages) {
                    if (stockStatusLower === 'expired') {
                        stockStatusBadge = '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-50 text-red-700 border border-red-200">🚨 Expired</span>';
                    } else {
                        stockStatusBadge = '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-50 text-gray-700 border border-gray-200">⚠️ Out of Stock</span>';
                    }
                } else if (stockStatusLower === 'out of stock') {
                    stockStatusBadge = '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-50 text-gray-700 border border-gray-200">⚠️ Out of Stock</span>';
                } else {
                    // Show "Available" or "Active" status for products that are low stock or expiring soon
                    stockStatusBadge = '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700 border border-green-200">✓ Available</span>';
                    
                    // Highlight if some batches expired but product still has sellable stock
                    if (stockStatusLower === 'expired') {
                        expiryBadge = '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-50 text-red-700 border border-red-200">⚠️ Remove expired batches</span>';
                    }
                    
                    // Check if expiring soon based on API flag/threshold
                    if (apiExpiringSoon && (daysUntilExpiry === null || daysUntilExpiry >= 0)) {
                        isExpiringSoon = true;
                        let label = '⏰ Expiring Soon';
                        if (daysUntilExpiry !== null) {
                            label = daysUntilExpiry === 0 ? '⏰ Expires Today' : `⏰ Expires in ${daysUntilExpiry}d`;
                        }
                        expiryBadge = `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-700 border border-orange-200">${label}</span>`;
                    } else if (p.expiry_date) {
                        const expiryDate = new Date(p.expiry_date);
                        const today = new Date();
                        const computedDays = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                        if (!Number.isNaN(computedDays)) {
                            if (daysUntilExpiry === null) {
                                daysUntilExpiry = computedDays;
                            }
                            if (computedDays <= expireThreshold && computedDays > 0) {
                                isExpiringSoon = true;
                                const label = computedDays === 0 ? '⏰ Expires Today' : `⏰ Expires in ${computedDays}d`;
                                expiryBadge = `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-700 border border-orange-200">${label}</span>`;
                            }
                        }
                    }
                }
                
                // Format stock display
                const formatStock = (num) => {
                    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
                    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
                    return num.toString();
                };
                const unitsColorClass = isLowStock ? 'text-red-700' : 'text-blue-700';
                const borderClass = isExpiringSoon ? 'alert-blink' : '';
                const backgroundClass = isLowStock ? 'low-stock-row' : 'hover:bg-gray-50';
                const unitPrice = hasPharmacyUnits && p.selling_price_unit && p.selling_price_unit > 0 
                    ? p.selling_price_unit 
                    : (p.price || 0);
                const packagePrice = hasStoragePackages && p.selling_price_package && p.selling_price_package > 0 
                    ? p.selling_price_package 
                    : (p.price || 0);
                
                return `
                <tr class="transition-colors ${backgroundClass} ${borderClass}">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-900 truncate">${p.name}</p>
                                ${p.strength ? `<p class="text-xs text-gray-500">⚡ ${p.strength}</p>` : ''}
                                ${p.expiry_date ? `
                                <p class="text-xs text-gray-400 mt-0.5">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        Exp: ${new Date(p.expiry_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                    </span>
                                </p>
                                ` : ''}
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <p class="text-sm text-gray-700">${p.generic_name || 'Generic Medicine'}</p>
                        <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                            ${p.unit_type ? `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">📏 ${p.unit_type}</span>` : ''}
                            ${p.package_type ? `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700 border border-green-200">📦 ${p.package_type}</span>` : ''}
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-center">
                        <div class="space-y-1">
                            ${hasPharmacyUnits ? `
                            <div class="flex items-center justify-center gap-1">
                                <span class="text-blue-600 font-bold text-xs">💊</span>
                                <span class="text-xs font-bold ${unitsColorClass}">${formatStock(p.available_units_pharmacy)} units</span>
                            </div>
                            ` : ''}
                            ${hasStoragePackages ? `
                            <div class="flex items-center justify-center gap-1">
                                <span class="text-green-600 font-bold text-xs">📦</span>
                                <span class="text-xs font-bold text-green-700">${formatStock(p.available_packages)} packages</span>
                            </div>
                            ` : ''}
                            ${!hasPharmacyUnits && !hasStoragePackages ? `
                            <span class="text-xs text-gray-400">0</span>
                            ` : ''}
                            ${p.batch_count && p.batch_count > 1 ? `
                            <div class="text-xs text-gray-500 mt-1">📊 ${p.batch_count} batches</div>
                            ` : ''}
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-right">
                        ${hasPharmacyUnits ? `
                        <div class="flex flex-col items-end">
                            ${hasDisc ? `<span class="text-xs text-gray-400 line-through">₱${oldPrice.toFixed(2)}</span>` : ''}
                            <span class="text-sm font-bold text-gray-900">₱${unitPrice.toFixed(2)}</span>
                        </div>
                        ` : '<span class="text-sm text-gray-400">-</span>'}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-right">
                        ${hasStoragePackages ? `
                        <div class="flex flex-col items-end">
                            ${hasDisc ? `<span class="text-xs text-gray-400 line-through">₱${oldPrice.toFixed(2)}</span>` : ''}
                            <span class="text-sm font-bold text-gray-900">₱${packagePrice.toFixed(2)}</span>
                        </div>
                        ` : '<span class="text-sm text-gray-400">-</span>'}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-center">
                        <div class="flex flex-col items-center gap-1">
                            ${stockStatusBadge}
                            ${expiryBadge}
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-center">
                        <div class="flex items-center justify-center gap-2">
                            ${(((hasPharmacyUnits || hasStoragePackages) && !['expired','out of stock'].includes(stockStatusLower)) || isLowStock) ? `
                                ${hasPharmacyUnits ? `
                                <button onclick="addToCart('${p.id}', 'unit')" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold rounded-lg transition-all shadow-sm hover:shadow-md flex items-center gap-1">
                                    <span>💊</span>
                                    <span>Unit</span>
                                </button>
                                ` : ''}
                                ${hasStoragePackages ? `
                                <button onclick="addToCart('${p.id}', 'package')" class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white text-xs font-bold rounded-lg transition-all shadow-sm hover:shadow-md flex items-center gap-1">
                                    <span>📦</span>
                                    <span>Package</span>
                                </button>
                                ` : ''}
                                ${!hasPharmacyUnits && !hasStoragePackages ? `
                                <span class="text-xs text-gray-400">Out of Stock</span>
                                ` : ''}
                            ` : `
                                <span class="text-xs text-gray-400">${stockStatusLower === 'expired' ? '🚨 Expired' : '⚠️ Out of Stock'}</span>
                            `}
                        </div>
                    </td>
                </tr>`;
            }

            function addToCart(id, saleType = 'unit') {
                // saleType: 'unit' (from pharmacy) or 'package' (from storage)
                const product = products.find(p => p.id === id);
                if (!product) {
                    showErrorModal('Product Not Found', 'The selected product could not be found.');
                    return;
                }
                
                // Check stock status before adding to cart
                const stockStatus = product.stock_status || 'Available';
                if (stockStatus === 'Expired') {
                    showErrorModal('Product Expired', 'This product has expired and cannot be sold.');
                    return;
                }
                if (stockStatus === 'Out of Stock') {
                    showErrorModal('Out of Stock', 'This product is out of stock.');
                    return;
                }
                
                // Validate sale type availability
                if (saleType === 'unit' && (!product.available_units_pharmacy || product.available_units_pharmacy <= 0)) {
                    showErrorModal('No Units Available', 'No units available in pharmacy inventory. Please restock from storage.');
                    return;
                }
                if (saleType === 'package' && (!product.available_packages || product.available_packages <= 0)) {
                    showErrorModal('No Packages Available', 'No packages available in storage inventory.');
                    return;
                }
                
                // Batch/delivery selection is handled by backend using FEFO/FIFO logic
                // We don't need to specify batch_id here - the controller will select the appropriate delivery
                // For cart tracking, we use product_id + sale_type as unique identifier
                const batch_id = null; // Backend will determine using FEFO/FIFO
                
                // Check if item already exists with same sale type (batch selection happens on backend)
                const existing = cart.find(i => String(i.id) === String(id) && i.sale_type === saleType);
                if (existing) {
                    const maxQty = saleType === 'unit' ? Number(product.available_units_pharmacy || 0) : Number(product.available_packages || 0);
                    if (Number(existing.quantity) < maxQty) {
                        existing.quantity = Number(existing.quantity) + 1;
                    } else {
                        showErrorModal('Maximum Quantity Reached', 'Maximum available quantity reached for this item.');
                    }
                } else {
                    const hasDisc = !!product.discountPercent;
                    const originalPrice = hasDisc ? Number(product.price) / (product.discountPercent ? (1 - product.discountPercent/100) : 1) : Number(product.price);
                    const price = saleType === 'package' ? Number(product.selling_price_package || product.price || 0) : Number(product.selling_price_unit || product.price || 0);
                    
                    cart.push({ 
                        id: String(product.id), 
                        name: product.name, 
                        generic_name: product.generic_name || '',
                        price: price,
                        originalPrice: originalPrice, 
                        quantity: 1, 
                        stock: saleType === 'unit' ? Number(product.available_units_pharmacy || 0) : Number(product.available_packages || 0),
                        sale_type: saleType, // 'unit' or 'package'
                        batch_id: null, // Backend will determine using FEFO/FIFO based on product_id
                        image: imageForProduct(product) 
                    });
                }
                updateCart();
            }

            function updateQty(id, saleType, delta) {
                // Find item by id AND sale_type to handle separate unit/package entries
                const item = cart.find(i => String(i.id) === String(id) && i.sale_type === saleType);
                if (!item) return;
                
                // Ensure quantity is a number
                item.quantity = Number(item.quantity) || 1;
                
                // Find the product to check current stock
                const product = products.find(p => String(p.id) === String(id));
                if (product) {
                    const maxStock = saleType === 'unit' ? Number(product.available_units_pharmacy || 0) : Number(product.available_packages || 0);
                    item.stock = maxStock; // Update stock from product data
                    
                    item.quantity = Number(item.quantity) + Number(delta);
                    if (item.quantity <= 0) { 
                        cart = cart.filter(i => !(String(i.id) === String(id) && i.sale_type === saleType)); 
                    }
                    if (item.quantity > item.stock) { 
                        item.quantity = item.stock; 
                        showErrorModal('Maximum Quantity', `Maximum available: ${item.stock} ${saleType === 'unit' ? 'units' : 'packages'}`);
                    }
                    
                    // Ensure quantity stays as number
                    item.quantity = Number(item.quantity);
                    
                    // Update input field value if it exists
                    const inputField = document.getElementById(`qty-input-${id}-${saleType}`);
                    if (inputField) {
                        inputField.value = item.quantity;
                    }
                } else {
                    item.quantity = Number(item.quantity) + Number(delta);
                    if (item.quantity <= 0) { 
                        cart = cart.filter(i => !(String(i.id) === String(id) && i.sale_type === saleType)); 
                    }
                    if (item.quantity > Number(item.stock || 0)) { 
                        item.quantity = Number(item.stock || 0); 
                    }
                    
                    // Ensure quantity stays as number
                    item.quantity = Number(item.quantity);
                    
                    // Update input field value if it exists
                    const inputField = document.getElementById(`qty-input-${id}-${saleType}`);
                    if (inputField) {
                        inputField.value = item.quantity;
                    }
                }
                updateCart();
            }

            function updateQtyByInput(id, saleType) {
                // Find item by id AND sale_type
                const item = cart.find(i => String(i.id) === String(id) && i.sale_type === saleType);
                if (!item) return;
                
                // Get input field value
                const inputField = document.getElementById(`qty-input-${id}-${saleType}`);
                if (!inputField) return;
                
                let newQty = parseInt(inputField.value) || 1;
                
                // Find the product to check current stock
                const product = products.find(p => String(p.id) === String(id));
                if (product) {
                    const maxStock = saleType === 'unit' ? Number(product.available_units_pharmacy || 0) : Number(product.available_packages || 0);
                    item.stock = maxStock; // Update stock from product data
                }
                
                // Validate quantity
                if (newQty <= 0) {
                    newQty = 1;
                    inputField.value = 1;
                }
                if (newQty > Number(item.stock || 0)) {
                    newQty = Number(item.stock || 0);
                    inputField.value = newQty;
                    showErrorModal('Maximum Quantity', `Maximum available: ${newQty} ${saleType === 'unit' ? 'units' : 'packages'}`);
                }
                
                // Update item quantity - ensure it's a number
                item.quantity = Number(newQty);
                
                // If quantity is 0 or negative, remove item
                if (newQty <= 0) {
                    cart = cart.filter(i => !(String(i.id) === String(id) && i.sale_type === saleType));
                }
                
                updateCart();
            }

            function removeItem(id, saleType) {
                cart = cart.filter(i => !(String(i.id) === String(id) && i.sale_type === saleType));
                updateCart();
            }

            // Remove out-of-stock items from cart
            function removeOutOfStockFromCart() {
                const itemsToRemove = [];
                cart.forEach(item => {
                    const product = products.find(p => String(p.id) === String(item.id));
                    if (product) {
                        const hasStock = item.sale_type === 'unit' 
                            ? (product.available_units_pharmacy || 0) > 0
                            : (product.available_packages || 0) > 0;
                        
                        if (!hasStock) {
                            itemsToRemove.push({ id: item.id, saleType: item.sale_type });
                        } else {
                            // Update stock in cart item
                            item.stock = item.sale_type === 'unit' 
                                ? Number(product.available_units_pharmacy || 0)
                                : Number(product.available_packages || 0);
                            
                            // If quantity exceeds new stock, reduce it
                            if (item.quantity > item.stock) {
                                item.quantity = item.stock;
                            }
                        }
                    } else {
                        // Product no longer exists, remove from cart
                        itemsToRemove.push({ id: item.id, saleType: item.sale_type });
                    }
                });
                
                // Remove out-of-stock items
                itemsToRemove.forEach(({ id, saleType }) => {
                    cart = cart.filter(i => !(String(i.id) === String(id) && i.sale_type === saleType));
                });
                
                if (itemsToRemove.length > 0) {
                    updateCart();
                    showErrorModal('Cart Updated', `${itemsToRemove.length} item(s) were removed from cart because they are out of stock.`);
                }
            }

            function updateCart() {
                const list = document.getElementById('cartItems');
                // Ensure all quantities and prices are numbers
                cart.forEach(item => {
                    item.quantity = Number(item.quantity) || 1;
                    item.price = Number(item.price) || 0;
                });
                const count = cart.reduce((a,i)=>a+Number(i.quantity),0);
                const subtotal = cart.reduce((a,i)=>a + (Number(i.price)*Number(i.quantity)), 0);
                const total = subtotal;

                document.getElementById('cartCount').textContent = `${count} item${count!==1?'s':''}`;
                document.getElementById('itemCount').textContent = `${count} (items)`;
                document.getElementById('subtotal').textContent = `₱${subtotal.toFixed(2)}`;
                document.getElementById('grandTotal').textContent = `₱${total.toFixed(2)}`;

                if (cart.length === 0) {
                    list.innerHTML = `
                        <div class=\"text-center text-gray-500 py-16\" id=\"emptyCart\">
                            <svg class=\"w-20 h-20 mx-auto mb-4 text-gray-300\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 11-4 0v-6m-4 0a2 2 0 104 0m-4 0h4\"></path></svg>
                            <p class=\"text-lg font-semibold\">Your cart is empty</p>
                            <p class=\"text-sm mt-2\">Add products to get started</p>
                        </div>`;
                    return;
                }

                list.innerHTML = cart.map(i => {
                    const saleTypeLabel = i.sale_type === 'package' ? '📦 Package (FEFO+FIFO)' : '💊 Unit (FEFO+FIFO)';
                    const saleTypeColor = i.sale_type === 'package' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                    const itemId = `cart-item-${i.id}-${i.sale_type}`;
                    return `
                    <div class=\"bg-white rounded-lg p-3 shadow-sm border border-gray-200\" id="${itemId}">
                        <div class=\"flex items-center gap-3\">
                            <div class=\"w-14 h-14 bg-gray-100 rounded-md overflow-hidden flex items-center justify-center text-gray-400\"> 
                                <img src=\"${i.image || imageForProduct(i)}\" alt=\"${i.name}\" class=\"w-full h-full object-cover\"> 
                            </div>
                            <div class=\"flex-1\">
                                <p class=\"text-sm font-semibold text-gray-800 truncate\">${i.name}</p>
                                <p class=\"text-xs text-gray-500 truncate\">${i.generic_name || ''}</p>
                                <span class=\"inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium ${saleTypeColor}\">${saleTypeLabel}</span>
                                <div class=\"flex items-center gap-2 mt-2\">
                                    <button onclick=\"updateQty('${i.id}','${i.sale_type}',-1)\" class=\"w-7 h-7 rounded bg-gray-100 text-gray-700 hover:bg-gray-200 flex items-center justify-center font-bold\">−</button>
                                    <input 
                                        type=\"number\" 
                                        id=\"qty-input-${i.id}-${i.sale_type}\"
                                        value=\"${i.quantity}\" 
                                        min=\"1\" 
                                        max=\"${i.stock}\"
                                        class=\"w-16 px-2 py-1 text-sm font-bold text-center border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all\"
                                        onchange=\"updateQtyByInput('${i.id}','${i.sale_type}')\"
                                        onkeydown=\"if(event.key==='Enter'){this.blur()}\"
                                    />
                                    <button onclick=\"updateQty('${i.id}','${i.sale_type}',1)\" class=\"w-7 h-7 rounded bg-gray-100 text-gray-700 hover:bg-gray-200 flex items-center justify-center font-bold\">+</button>
                                    <span class=\"text-xs text-gray-500 ml-1\">/ ${i.stock}</span>
                                </div>
                            </div>
                            <div class=\"text-right\">
                                <p class=\"text-sm font-bold text-gray-900\">₱${(Number(i.price)*Number(i.quantity)).toFixed(2)}</p>
                                <p class=\"text-xs text-gray-500\">₱${Number(i.price).toFixed(2)} each</p>
                                <button onclick=\"removeItem('${i.id}','${i.sale_type}')\" class=\"text-xs text-red-600 hover:underline mt-1\">Remove</button>
                            </div>
                        </div>
                    </div>`;
                }).join('');
            }

            function imageForProduct(p) {
                const category = p.category || 'all';
                const theme = {
                    rx: { bg: '#eef2ff', fg: '#4338ca', icon: 'Rx' },
                    otc: { bg: '#ecfeff', fg: '#0891b2', icon: '💊' },
                    vitamins: { bg: '#f0fdf4', fg: '#16a34a', icon: '🧴' },
                    personal: { bg: '#fff7ed', fg: '#c2410c', icon: '🧼' },
                    supplies: { bg: '#f1f5f9', fg: '#0f172a', icon: '⛑️' },
                    all: { bg: '#f3f4f6', fg: '#111827', icon: '💊' }
                }[category] || { bg: '#f3f4f6', fg: '#111827', icon: '💊' };
                const label = (p.name || 'Medicine').slice(0, 18).replace(/&/g, 'and');
                const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="320" height="200" viewBox="0 0 320 200">
  <rect width="320" height="200" fill="${theme.bg}"/>
  <text x="24" y="110" font-size="72" text-anchor="start">${theme.icon}</text>
  <text x="24" y="170" font-size="28" fill="${theme.fg}" font-family="Arial, Helvetica, sans-serif">${label}</text>
  <rect x="220" y="36" width="68" height="28" rx="6" fill="#fff" stroke="#e5e7eb"/>
  <text x="254" y="56" font-size="16" text-anchor="middle" fill="#374151">Pharm</text>
</svg>`;
                return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
            }

            // Category filters
            document.getElementById('categoryChips').addEventListener('click', (e)=>{
                const btn = e.target.closest('button[data-cat]');
                if (!btn) return;
                activeCategory = btn.getAttribute('data-cat');
                Array.from(document.querySelectorAll('#categoryChips .chip')).forEach(b=>b.classList.remove('chip-active'));
                btn.classList.add('chip-active');
                renderProducts();
            });

            // Unit filter
            const unitFilterEl = document.getElementById('unitFilter');
            if (unitFilterEl) {
                unitFilterEl.addEventListener('change', renderProducts);
            }

            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function() {
                const searchInput = this;
                const clearBtn = document.getElementById('clearSearchBtn');
                
                // Show/hide clear button
                if (searchInput.value.trim()) {
                    clearBtn.classList.remove('hidden');
                } else {
                    clearBtn.classList.add('hidden');
                }
                
                // Render products with search filter
                renderProducts();
            });

            // Clear search function
            function clearSearch() {
                const searchInput = document.getElementById('searchInput');
                const clearBtn = document.getElementById('clearSearchBtn');
                
                searchInput.value = '';
                clearBtn.classList.add('hidden');
                searchInput.focus();
                renderProducts();
            }

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Escape to clear search
                if (e.key === 'Escape') {
                    const searchInput = document.getElementById('searchInput');
                    if (document.activeElement === searchInput) {
                        clearSearch();
                    }
                }
                
                // Ctrl/Cmd + K to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    document.getElementById('searchInput').focus();
                }
            });

            // Payment modal helpers
            function handleDiscountTypeChange() {
                const discountType = document.getElementById('discountTypeDash').value;
                const discountPercentRow = document.getElementById('discountPercentRow');
                const discountPercentInput = document.getElementById('discountPercentDash');
                
                if (discountType === 'None') {
                    // Hide percentage input for None
                    discountPercentRow.style.display = 'none';
                    discountPercentInput.value = '0';
                } else {
                    // Show percentage input for all discount types (Regular, PWD, Senior)
                    discountPercentRow.style.display = 'flex';
                    
                    // Set default value to 0 if empty, or keep current value
                    if (!discountPercentInput.value || discountPercentInput.value === '0') {
                        discountPercentInput.value = '0';
                    }
                    
                    // Focus on input field
                    discountPercentInput.focus();
                }
                
                calculateDiscounts();
            }
            
            function calculateDiscounts() {
                const subtotal = cart.reduce((a,i)=>a + (Number(i.price)*Number(i.quantity)), 0);
                const discountType = document.getElementById('discountTypeDash').value;
                const discountPercentInput = document.getElementById('discountPercentDash');
                let discountPercent = 0;
                let discountAmount = 0;
                let total = subtotal;
                
                if (discountType === 'None') {
                    discountPercent = 0;
                } else {
                    // All discount types (Regular, PWD, Senior) use the input value
                    discountPercent = parseFloat(discountPercentInput.value) || 0;
                }
                
                // Validate discount percentage (0-100)
                let validDiscountPercent = Math.max(0, Math.min(100, discountPercent));
                if (discountPercent !== validDiscountPercent && discountType !== 'None') {
                    discountPercentInput.value = validDiscountPercent;
                    discountPercent = validDiscountPercent;
                }
                
                if (validDiscountPercent > 0) {
                    // Calculate discount amount based on percentage
                    discountAmount = subtotal * (validDiscountPercent / 100);
                    total = subtotal - discountAmount;
                }
                
                // Update display
                document.getElementById('modalSubtotalDash').textContent = `₱${subtotal.toFixed(2)}`;
                document.getElementById('paymentItemsSubtotal').textContent = `₱${subtotal.toFixed(2)}`;
                
                const discountRow = document.getElementById('discountRow');
                const discountRowTable = document.getElementById('discountRowTable');
                const discountAmountEl = document.getElementById('modalDiscountDash');
                const paymentItemsDiscountEl = document.getElementById('paymentItemsDiscount');
                
                if (discountAmount > 0) {
                    discountRow.style.display = 'flex';
                    discountRowTable.style.display = 'table-row';
                    if (discountAmountEl) discountAmountEl.textContent = `₱${discountAmount.toFixed(2)}`;
                    if (paymentItemsDiscountEl) paymentItemsDiscountEl.textContent = `₱${discountAmount.toFixed(2)}`;
                } else {
                    discountRow.style.display = 'none';
                    discountRowTable.style.display = 'none';
                    if (discountAmountEl) discountAmountEl.textContent = '₱0.00';
                    if (paymentItemsDiscountEl) paymentItemsDiscountEl.textContent = '₱0.00';
                }
                
                document.getElementById('modalTotalDash').textContent = `₱${total.toFixed(2)}`;
                document.getElementById('paymentItemsTotal').textContent = `₱${total.toFixed(2)}`;
                
                // Update change calculation
                const amount = parseFloat(document.getElementById('amountReceivedDashModal').value) || 0;
                const change = amount - total;
                document.getElementById('changeAmountDashModal').textContent = `₱${change.toFixed(2)}`;
                
                return { subtotal, discountAmount, total, discountType, discountPercent: validDiscountPercent };
            }

            function openPaymentModalDash() {
                const customer = (document.getElementById('customerNameDash').value || 'Walk-in Customer').trim();
                const subtotal = cart.reduce((a,i)=>a + (Number(i.price)*Number(i.quantity)), 0);
                
                // Reset discount type to None
                const discountTypeSelect = document.getElementById('discountTypeDash');
                const discountPercentInput = document.getElementById('discountPercentDash');
                const discountPercentRow = document.getElementById('discountPercentRow');
                
                if (discountTypeSelect) {
                    discountTypeSelect.value = 'None';
                }
                if (discountPercentInput) {
                    discountPercentInput.value = '0';
                }
                if (discountPercentRow) {
                    discountPercentRow.style.display = 'none';
                }
                
                // Calculate and display
                calculateDiscounts();
                
                document.getElementById('modalCustomerDash').textContent = customer;
                document.getElementById('amountReceivedDashModal').value = '';
                document.getElementById('changeAmountDashModal').textContent = '₱0.00';
                
                // Populate line items table
                const tbody = document.getElementById('paymentItemsBody');
				if (tbody) {
                    tbody.innerHTML = cart.map(i => `
                        <tr>
                            <td class=\"px-2 py-1.5 text-xs\">${i.name}</td>
                            <td class=\"px-2 py-1.5 text-center text-xs\">${Number(i.quantity)}</td>
                            <td class=\"px-2 py-1.5 text-right text-xs\">₱${Number(i.price).toFixed(2)}</td>
                            <td class=\"px-2 py-1.5 text-right text-xs\">₱${(Number(i.price)*Number(i.quantity)).toFixed(2)}</td>
                        </tr>
                    `).join('');
					
					// Enable auto-scroll when items reach 4 or more
					const wrapper = document.getElementById('paymentItemsWrapper');
					if (wrapper) {
						const rows = tbody.querySelectorAll('tr').length;
						if (rows >= 4) {
							// Show scrollbar when 4 or more items
							wrapper.style.maxHeight = '400px';
							wrapper.style.overflowY = 'auto';
							wrapper.classList.add('scroll-slim');
						} else {
							// Remove height restriction when less than 4 items
							wrapper.style.maxHeight = 'none';
							wrapper.style.overflowY = 'visible';
							wrapper.classList.remove('scroll-slim');
						}
					}
                }
                
                const payAmtEl = document.getElementById('paymentAmountLabel');
                if (payAmtEl) payAmtEl.textContent = '₱0.00';
                
                const m = document.getElementById('paymentModalDash');
                m.classList.remove('hidden');
                m.classList.add('flex');
                setTimeout(()=>document.getElementById('amountReceivedDashModal').focus(), 50);
            }

            function closePaymentModalDash() {
                const m = document.getElementById('paymentModalDash');
                m.classList.add('hidden');
                m.classList.remove('flex');
            }

            // Discount type change handler (will be initialized in initPOS if element exists)
            
            document.getElementById('amountReceivedDashModal').addEventListener('input', ()=>{
                const amount = parseFloat(document.getElementById('amountReceivedDashModal').value) || 0;
                const { total } = calculateDiscounts();
                const change = amount - total;
                document.getElementById('changeAmountDashModal').textContent = `₱${change.toFixed(2)}`;
                const payAmtEl = document.getElementById('paymentAmountLabel');
                if (payAmtEl) payAmtEl.textContent = `₱${amount.toFixed(2)}`;
            });

            // Open modal on process click
            document.getElementById('processBtn').addEventListener('click', ()=>{
                if (cart.length === 0) { 
                    showErrorModal('Empty Cart', 'Your cart is empty. Please add items before processing the transaction.');
                    return; 
                }
                openPaymentModalDash();
            });

            async function completeTransactionDash() {
                if (cart.length === 0) {
                    showErrorModal('Empty Cart', 'Your cart is empty. Please add items before completing the transaction.');
                    return;
                }
                const customer = (document.getElementById('customerNameDash').value || 'Walk-in Customer').trim();
                const amount = parseFloat(document.getElementById('amountReceivedDashModal').value) || 0;
                
                // Calculate discounts
                const { subtotal, discountAmount, total, discountType } = calculateDiscounts();
                
                if (amount < total) {
                    showErrorModal('Insufficient Payment', `Amount received (₱${amount.toFixed(2)}) is insufficient. Total amount: ₱${total.toFixed(2)}`);
                    return;
                }

                // Prepare sale items with correct structure
                const saleData = {
                    user_id: <?php echo $currentUserId; ?>,
                    customer_name: customer,
                    cash_received: amount,
                    allocation: 'FEFO',
                    discount_type: discountType || 'None',
                    discount_amount: discountAmount || 0,
                    items: cart.map(i => {
                        const item = {
                            product_id: parseInt(i.id),
                            batch_id: parseInt(i.batch_id) || null,
                            sale_type: i.sale_type || 'unit', // 'unit' or 'package'
                            unit_price: parseFloat(i.price),
                            brand_name: i.name,
                            generic_name: i.generic_name || ''
                        };
                        // Map sale_unit_type_id: 1 = unit, 2 = package
                        item.sale_unit_type_id = (i.sale_type === 'package') ? 2 : 1;
                        
                        // Set quantities based on sale type
                        if (i.sale_type === 'package') {
                            item.quantity_packages = parseInt(i.quantity);
                            item.quantity_units = 0; // Will be calculated from units_per_package on backend
                        } else {
                            item.quantity_packages = 0;
                            item.quantity_units = parseInt(i.quantity);
                        }
                        
                        // Compute line subtotal
                        item.subtotal = Number(item.unit_price) * Number(i.quantity);
                        
                        return item;
                    })
                };

                const btn = document.querySelector('#paymentModalDash button[onclick="completeTransactionDash()"]');
                btn.disabled = true;
                btn.textContent = 'Processing...';
                try {
                    const res = await fetch('../Controllers/processPOSSaleController.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(saleData)
                    });
                    
                    // Check if response is ok and has content
                    if (!res.ok) {
                        const errorText = await res.text();
                        console.error('Server error response:', errorText);
                        let errorData;
                        try {
                            errorData = JSON.parse(errorText);
                        } catch (e) {
                            errorData = { success: false, error: 'Server returned: ' + res.status + ' ' + res.statusText };
                        }
                        showErrorModal('Transaction Error', errorData.error || 'Failed to complete transaction');
                        return;
                    }
                    
                    const responseText = await res.text();
                    if (!responseText || responseText.trim() === '') {
                        console.error('Empty response from server');
                        showErrorModal('Server Error', 'Empty response from server. Please try again.');
                        return;
                    }
                    
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (e) {
                        console.error('JSON parse error:', e, 'Response text:', responseText);
                        showErrorModal('Server Error', 'Invalid response from server. Please try again.');
                        return;
                    }
                    if (res.ok && result.success) {
                        // Show success message with receipt printing option
                        showReceiptModal(result.sale);
                        
                        // Reset cart and form
                        cart = [];
                        updateCart();
                        document.getElementById('customerNameDash').value = '';
                        closePaymentModalDash();
                        
                        // Reload products to update stock quantities after sale
                        // This will automatically remove out-of-stock products
                        await loadProducts();
                        
                        // Remove any cart items that are now out of stock
                        removeOutOfStockFromCart();
                    } else {
                        // Show error with debug info if available
                        const errorMsg = result.error || 'Failed to complete transaction';
                        const debugMsg = result.debug ? '\n\nDebug: ' + result.debug : '';
                        showErrorModal('Transaction Error', errorMsg + debugMsg);
                        console.error('Transaction error:', result);
                    }
                } catch (e) {
                    showErrorModal('Connection Error', 'Error connecting to server. Please check your connection and try again.');
                    console.error(e);
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Complete Transaction';
                }
            }

            // Function to show receipt printing modal
            function showReceiptModal(saleData) {
                // Create modal for receipt options
				const modalHTML = `
				<div id="receiptModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" style="background: rgba(0,0,0,0.6);">
                    <div class="bg-white rounded-xl w-full max-w-md shadow-2xl">
                        <div class="p-6 border-b">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <h2 class="text-2xl font-bold text-gray-800">Transaction Complete!</h2>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-700">Receipt #:</span>
                                    <span class="font-semibold">${saleData.receipt_number || '#' + saleData.sale_id}</span>
                                </div>
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-700">Sale ID:</span>
                                    <span class="font-semibold">#${saleData.sale_id}</span>
                                </div>
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-700">Subtotal:</span>
                                    <span class="font-semibold">₱${(saleData.subtotal || saleData.total_amount || 0).toFixed(2)}</span>
                                </div>
                                ${saleData.discount_type && saleData.discount_type !== 'None' && saleData.discount_amount > 0 ? `
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-700">Discount (${saleData.discount_type}):</span>
                                    <span class="font-semibold text-red-600">-₱${saleData.discount_amount.toFixed(2)}</span>
                                </div>
                                ` : ''}
                                <div class="flex justify-between mb-2 pt-2 border-t border-gray-300">
                                    <span class="text-gray-700 font-bold">Total Amount:</span>
                                    <span class="font-bold text-lg">₱${(saleData.total_amount || saleData.final_amount || 0).toFixed(2)}</span>
                                </div>
                                ${saleData.cash_received ? `
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-700">Cash Received:</span>
                                    <span class="font-semibold">₱${parseFloat(saleData.cash_received || 0).toFixed(2)}</span>
                                </div>
                                ` : ''}
                                ${saleData.change_amount !== undefined && saleData.change_amount !== null ? `
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-700 font-bold text-green-600">Change:</span>
                                    <span class="font-bold text-lg text-green-600">₱${parseFloat(saleData.change_amount || 0).toFixed(2)}</span>
                                </div>
                                ` : ''}
                                <div class="flex justify-between pt-2 border-t border-gray-300">
                                    <span class="text-gray-700">Customer:</span>
                                    <span class="font-semibold">${saleData.customer_name || 'Walk-in Customer'}</span>
                                </div>
                            </div>
                            <p class="text-gray-600 text-sm">Your transaction has been processed successfully.</p>
                        </div>
                        <div class="p-6 border-t flex justify-end gap-3">
                            <button onclick="closeReceiptModal()" class="px-6 py-3 bg-gray-400 hover:bg-gray-500 text-white rounded-lg transition-all">
                                Close
                            </button>
                            <button onclick="printReceipt(${saleData.sale_id})" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                Print Receipt
                            </button>
                        </div>
                    </div>
                </div>
                `;
                
                document.body.insertAdjacentHTML('beforeend', modalHTML);
            }

            function closeReceiptModal() {
                const modal = document.getElementById('receiptModal');
                if (modal) modal.remove();
            }

            async function printReceipt(saleId) {
                // Close the receipt modal
                closeReceiptModal();
                
                // Show loading indicator
                const loadingModal = document.createElement('div');
                loadingModal.id = 'printLoadingModal';
                loadingModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                loadingModal.innerHTML = `
                    <div class="bg-white rounded-lg p-6 flex items-center gap-3">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <span class="text-gray-700 font-semibold">Printing receipt automatically...</span>
                    </div>
                `;
                document.body.appendChild(loadingModal);
                
                    try {
                    // Check if QZ Tray is available
                    if (typeof isQZTrayAvailable === 'function' && isQZTrayAvailable() && typeof printReceiptWithQZTray === 'function') {
                        // Use QZ Tray for silent printing
                        const result = await printReceiptWithQZTray(saleId);
                        loadingModal.remove();
                        
                        if (result.success) {
                            showSuccessModal('Print Success', `Receipt printed successfully via ${result.printer || 'QZ Tray'}!`);
                        } else {
                            // Show helpful error message
                            const errorMsg = result.error || 'QZ Tray printing failed';
                            if (errorMsg.includes('certificate') || errorMsg.includes('blocked') || errorMsg.includes('allowed')) {
                                showErrorModal('QZ Tray Setup Required', 
                                    'QZ Tray connection blocked. Please:\n\n' +
                                    '1. Right-click QZ Tray icon in system tray\n' +
                                    '2. Click "Certificate" or "Settings"\n' +
                                    '3. Allow this website (localhost/pharmacy)\n' +
                                    '4. Refresh this page and try again\n\n' +
                                    'Falling back to browser print...');
                            } else {
                                showErrorModal('Print Error', errorMsg + '\n\nFalling back to browser print...');
                            }
                            // Fallback to window.print() if QZ Tray fails
                            setTimeout(() => fallbackToBrowserPrint(saleId), 2000);
                        }
                    } else {
                        // Fallback to browser print if QZ Tray is not available
                        console.log('QZ Tray not available, using browser print');
                        loadingModal.remove();
                        fallbackToBrowserPrint(saleId);
                    }
                } catch (error) {
                    loadingModal.remove();
                    console.error('Print error:', error);
                    // Show error and fallback
                    showErrorModal('Print Error', 
                        'QZ Tray error: ' + (error.message || 'Unknown error') + 
                        '\n\nFalling back to browser print...');
                    setTimeout(() => fallbackToBrowserPrint(saleId), 2000);
                }
            }
            
            // Fallback function for browser print (shows print dialog)
            function fallbackToBrowserPrint(saleId) {
                const printWindow = window.open(`../Controllers/printReceipt.php?id=${saleId}&autoprint=1`, '_blank');
                if (!printWindow) {
                    showErrorModal('Print Error', 'Please allow popups to print the receipt.');
                }
            }

            // QZ Tray URL Helper Function
            function showQZUrlHelper() {
                const urlHelper = document.getElementById('qzUrlHelper');
                const urlText = document.getElementById('qzUrlText');
                
                if (urlHelper && urlText) {
                    const currentUrl = window.location.href;
                    const urlObj = new URL(currentUrl);
                    const baseUrl = urlObj.origin; // e.g., http://localhost
                    
                    urlText.textContent = baseUrl;
                    urlHelper.style.display = 'block';
                    
                    // Hide after 10 seconds
                    setTimeout(() => {
                        if (urlHelper) {
                            urlHelper.style.display = 'none';
                        }
                    }, 10000);
                }
            }
            
            function copyQZUrl() {
                const urlText = document.getElementById('qzUrlText');
                if (urlText) {
                    const textToCopy = urlText.textContent;
                    navigator.clipboard.writeText(textToCopy).then(function() {
                        const helper = document.getElementById('qzUrlHelper');
                        if (helper) {
                            helper.style.background = '#2e7d32';
                            helper.innerHTML = '<strong>✅ Copied!</strong><br><span style="font-family: monospace;">' + textToCopy + '</span><br><small>Paste this in QZ Tray</small>';
                            setTimeout(() => {
                                helper.style.display = 'none';
                            }, 3000);
                        }
                    }, function(err) {
                        // Fallback
                        const textArea = document.createElement('textarea');
                        textArea.value = textToCopy;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        alert('✅ Copied to clipboard: ' + textToCopy + '\n\nPaste this in QZ Tray Site Manager when adding a new site.');
                    });
                }
            }
            
            // Show URL helper on page load (only once)
            if (typeof sessionStorage !== 'undefined' && !sessionStorage.getItem('qzUrlShown')) {
                setTimeout(showQZUrlHelper, 1000);
                sessionStorage.setItem('qzUrlShown', 'true');
            }

            // Initialize
            (async function initPOS(){
                await loadCategories();
                await loadUnits();
                await loadProducts();
                // Initialize barcode scanner automatically
                initializeScanner();
                
                // Discount dropdown will be initialized when modal opens
            })();

            // Sidebar active state
            (function initSidebar(){
                try {
                    const path = (location.pathname.split('/').pop() || '').toLowerCase();
                    const key = path.includes('posmanage') ? 'pos' : path.includes('dashboard') ? 'dashboard' : '';
                    if (!key) return;
                    document.querySelectorAll('.sb-item').forEach(el=>{
                        if (el.getAttribute('data-key') === key) {
                            el.classList.add('active');
                        }
                    });
                } catch(e) {}
            })();
        </script>
        
        <!-- QZ Tray Library for Automatic Receipt Printing -->
        <!-- Using local copy to avoid CDN tracking prevention issues -->
        <script src="../Assets/Javascript/qz-tray.min.js"></script>
        <!-- QZ Tray Print Functions -->
        <script src="../Assets/Javascript/qzTrayPrint.js"></script>
    </main>
    </body>
    </html>
    </html>
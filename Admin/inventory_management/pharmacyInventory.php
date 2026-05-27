<?php 
// Include database connection
require_once '../../dbConnection/connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isAdmin = false;
if (!empty($_SESSION['user']['role'])) { $isAdmin = strtolower($_SESSION['user']['role']) === 'admin'; }
elseif (!empty($_SESSION['role'])) { $isAdmin = strtolower($_SESSION['role']) === 'admin'; }

// Fetch all pharmacy inventory from database
// Note: pharmacy_inventory uses delivery_id to reference deliveries table
$query = "SELECT pi.pharmacy_id, pi.delivery_id,
                 pi.quantity_units_in, pi.quantity_units_out, pi.quantity_units_balance,
                 pi.updated_at,
                 d.batch_no, d.expiry_date, 
                 p.product_id, p.brand_name, p.generic_name, p.strength, p.category_id,
                 c.category_name
          FROM pharmacy_inventory pi
          LEFT JOIN deliveries d ON pi.delivery_id = d.delivery_id
          LEFT JOIN products p ON d.product_id = p.product_id
          LEFT JOIN Categories c ON p.category_id = c.category_id
          ORDER BY pi.updated_at DESC";

$result = mysqli_query($conn, $query);
$pharmacyInventory = [];

// Check if query was successful and has results
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $pharmacyInventory[] = $row;
    }
}

// Note: Pharmacy inventory is system-managed
// - Created automatically during transfers from storage
// - Updated automatically during sales
// - No manual CREATE/UPDATE/DELETE allowed

// Calculate statistics
$totalItems = count($pharmacyInventory);
$totalUnitsIn = array_sum(array_column($pharmacyInventory, 'quantity_units_in'));
$totalUnitsOut = array_sum(array_column($pharmacyInventory, 'quantity_units_out'));
$totalUnitsBalance = array_sum(array_column($pharmacyInventory, 'quantity_units_balance'));
$uniqueProducts = count(array_unique(array_filter(array_column($pharmacyInventory, 'product_id'))));
$uniqueBatches = count(array_unique(array_filter(array_column($pharmacyInventory, 'delivery_id'))));

include '../../includes/adminHeader.php'; 
?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>

<!-- Pharmacy Inventory Management -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gradient-to-br from-gray-50 to-purple-50/30 min-h-screen">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white rounded-xl shadow-sm border border-purple-100">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Pharmacy Inventory</h1>
                            <p class="text-sm text-gray-600 mt-1">View pharmacy stock levels (system-managed)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Units Balance -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Units Balance</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($totalUnitsBalance) ?></p>
                        <div class="flex items-center mt-2">
                            <div class="w-2 h-2 bg-purple-500 rounded-full mr-2"></div>
                            <p class="text-xs text-gray-500">Available units</p>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            <?= number_format($totalUnitsIn) ?> IN - <?= number_format($totalUnitsOut) ?> OUT
                        </div>
                    </div>
                    <div class="p-3 bg-purple-50 rounded-xl group-hover:bg-purple-100 transition-colors">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Unique Products -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($uniqueProducts) ?></p>
                        <div class="flex items-center mt-2">
                            <div class="w-2 h-2 bg-emerald-500 rounded-full mr-2"></div>
                            <p class="text-xs text-gray-500">Unique products</p>
                        </div>
                    </div>
                    <div class="p-3 bg-emerald-50 rounded-xl group-hover:bg-emerald-100 transition-colors">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Batches -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Batches</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($uniqueBatches) ?></p>
                        <div class="flex items-center mt-2">
                            <div class="w-2 h-2 bg-orange-500 rounded-full mr-2"></div>
                            <p class="text-xs text-gray-500">Active batches</p>
                        </div>
                    </div>
                    <div class="p-3 bg-orange-50 rounded-xl group-hover:bg-orange-100 transition-colors">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Items -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Items</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($totalItems) ?></p>
                        <div class="flex items-center mt-2">
                            <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                            <p class="text-xs text-gray-500">Inventory entries</p>
                        </div>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-xl group-hover:bg-blue-100 transition-colors">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 border border-white/50 mb-6 sm:mb-8">
            <div class="flex items-center gap-2 sm:gap-3 mb-4 sm:mb-6">
                <div class="p-1.5 sm:p-2 bg-purple-50 rounded-lg">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                    </div>
                    <div>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900">Filters</h3>
                    <p class="text-xs sm:text-sm text-gray-500">Search and filter inventory</p>
                </div>
                </div>
                
            <div class="flex flex-col gap-3 sm:gap-4 lg:gap-5">
                    <!-- Search Bar -->
                <div class="w-full">
                        <div class="relative">
                            <input 
                                type="text" 
                                id="searchInput" 
                                placeholder="Search by product name or batch number..." 
                            class="w-full pl-10 sm:pl-12 lg:pl-14 pr-4 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 shadow-sm transition-all duration-200 text-sm lg:text-base"
                                onkeyup="filterPharmacyInventory()"
                            >
                        <svg class="absolute left-3 sm:left-4 lg:left-5 top-1/2 transform -translate-y-1/2 w-4 h-4 lg:w-5 lg:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                    
                <!-- Filter Row -->
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 lg:gap-4">
                    <!-- Product Name Filter -->
                    <div class="w-full sm:w-52 lg:w-56 xl:w-60 2xl:w-64">
                        <select id="productNameFilter" onchange="filterPharmacyInventory()" class="w-full px-3 sm:px-4 lg:px-5 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 shadow-sm transition-all duration-200 text-sm lg:text-base">
                            <option value="">All Products</option>
                            <?php
                            $uniqueProducts = array_unique(array_filter(array_column($pharmacyInventory, 'brand_name')));
                            sort($uniqueProducts);
                            foreach ($uniqueProducts as $product): ?>
                                <option value="<?= htmlspecialchars(strtolower($product)) ?>"><?= htmlspecialchars($product) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="w-full sm:w-52 lg:w-56 xl:w-60 2xl:w-64">
                        <select id="categoryFilter" onchange="filterPharmacyInventory()" class="w-full px-3 sm:px-4 lg:px-5 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 shadow-sm transition-all duration-200 text-sm lg:text-base">
                            <option value="">All Categories</option>
                            <?php
                            $uniqueCategories = array_unique(array_filter(array_column($pharmacyInventory, 'category_name')));
                            foreach ($uniqueCategories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                    
                    <!-- Stock Status Filter -->
                    <div class="w-full sm:w-52 lg:w-56 xl:w-60 2xl:w-64">
                        <select id="stockStatusFilter" onchange="filterPharmacyInventory()" class="w-full px-3 sm:px-4 lg:px-5 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 shadow-sm transition-all duration-200 text-sm lg:text-base">
                            <option value="">All Stock Status</option>
                            <option value="in_stock">In Stock</option>
                            <option value="low_stock">Low Stock</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                        </div>
                    
                    <!-- Clear Filters Button -->
                    <button onclick="clearPharmacyFilters()" class="px-3 sm:px-4 lg:px-6 py-2.5 sm:py-3 lg:py-3.5 bg-gray-200 text-gray-700 rounded-lg sm:rounded-xl hover:bg-gray-300 transition-all duration-200 text-xs sm:text-sm lg:text-base font-semibold flex items-center justify-center gap-2 whitespace-nowrap">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Inventory Table Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 border border-white/50">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4 sm:mb-6 gap-3 sm:gap-4">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-1.5 sm:p-2 bg-purple-50 rounded-lg">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900">Inventory List</h3>
                        <p class="text-xs sm:text-sm text-gray-500">View pharmacy stock levels</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button onclick="printFullReport()" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all text-sm font-semibold shadow-sm hover:shadow-md">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Print Report
                    </button>
                </div>
            </div>
            
            <!-- Auto-scroll Controls -->
            <div id="pharmacyAutoScrollControls" class="hidden mb-4 flex items-center justify-between bg-purple-50 rounded-lg p-3 border border-purple-200">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <span class="text-sm font-medium text-purple-900">Auto-scroll enabled (10+ rows)</span>
                            </div>
                <div class="flex gap-2">
                    <button id="pharmacyAutoScrollToggle" onclick="togglePharmacyAutoScroll()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all text-sm font-semibold">
                        Start Auto Scroll
                    </button>
                    <button onclick="resetPharmacyScroll()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all text-sm font-semibold">
                        Reset
                    </button>
                </div>
            </div>
            
            <!-- Transactions Table -->
            <div id="pharmacyInventoryTableWrapper" class="overflow-x-auto rounded-xl border border-gray-200">
                <style>
                    /* Custom scrollbar for responsive tables */
                    #pharmacyInventoryTableWrapper::-webkit-scrollbar {
                        height: 8px;
                    }
                    #pharmacyInventoryTableWrapper::-webkit-scrollbar-track {
                        background: #f1f1f1;
                        border-radius: 10px;
                    }
                    #pharmacyInventoryTableWrapper::-webkit-scrollbar-thumb {
                        background: #888;
                        border-radius: 10px;
                    }
                    #pharmacyInventoryTableWrapper::-webkit-scrollbar-thumb:hover {
                        background: #555;
                    }
                    /* Vertical scrollbar - scroll entire wrapper */
                    #pharmacyInventoryTableWrapper {
                        max-height: 640px; /* Approximately 10 table rows */
                        overflow-y: auto;
                        scrollbar-width: thin;
                        scrollbar-color: #888 #f1f1f1;
                    }
                    #pharmacyInventoryTableWrapper table thead {
                        position: sticky;
                        top: 0;
                        z-index: 10;
                        background: linear-gradient(to right, #f9fafb, #f3f4f6);
                    }
                </style>
                <table class="w-full min-w-[1200px]">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                        <tr>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Inventory ID</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Product</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Batch</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Units IN</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Units OUT</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Units Balance</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Expiry Date</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody id="pharmacyInventoryTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if (count($pharmacyInventory) > 0): ?>
                            <?php foreach ($pharmacyInventory as $item): ?>
                                <?php 
                                $balance = (int)($item['quantity_units_balance'] ?? 0);
                                $stockStatus = 'out_of_stock';
                                if ($balance > 10) {
                                    $stockStatus = 'in_stock';
                                } elseif ($balance > 0) {
                                    $stockStatus = 'low_stock';
                                }
                                ?>
                                <tr class="hover:bg-gray-50/50 transition-colors" data-inventory='<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>' data-product="<?= htmlspecialchars(strtolower($item['brand_name'] ?? '')) ?>" data-batch="<?= htmlspecialchars(strtolower($item['batch_no'] ?? '')) ?>" data-category="<?= htmlspecialchars(strtolower($item['category_name'] ?? '')) ?>" data-stock-status="<?= $stockStatus ?>" data-balance="<?= $balance ?>">
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                                            #<?= $item['pharmacy_id'] ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm mr-3">
                                                <?= strtoupper(substr($item['brand_name'] ?? 'N/A', 0, 1)) ?>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($item['brand_name'] ?? 'N/A') ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($item['generic_name'] ?? '') ?></div>
                                                <?php if (!empty($item['strength'])): ?>
                                                    <div class="text-xs text-gray-400">⚡ <?= htmlspecialchars($item['strength']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <?php if (!empty($item['batch_no'])): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                                                <?= htmlspecialchars($item['batch_no']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-semibold text-emerald-700">
                                            <?= number_format((int)($item['quantity_units_in'] ?? 0)) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="text-sm font-semibold text-red-700">
                                            <?= number_format((int)($item['quantity_units_out'] ?? 0)) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold bg-purple-100 text-purple-800">
                                            <?= number_format((int)($item['quantity_units_balance'] ?? 0)) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <?php 
                                        $expiryDate = $item['expiry_date'] ?? null;
                                        $isExpired = false;
                                        if ($expiryDate && $expiryDate !== '0000-00-00' && $expiryDate !== '2099-12-31') {
                                            $expiryTimestamp = strtotime($expiryDate);
                                            $todayTimestamp = strtotime('today');
                                            if ($expiryTimestamp < $todayTimestamp) {
                                                $isExpired = true;
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($expiryDate) && $expiryDate !== '0000-00-00' && $expiryDate !== '2099-12-31'): ?>
                                            <div class="text-sm <?= $isExpired ? 'text-red-700 font-semibold' : 'text-gray-700' ?>">
                                                <?= date('M d, Y', strtotime($expiryDate)) ?>
                                            </div>
                                            <?php if ($isExpired): ?>
                                                <div class="text-xs text-red-600 mt-0.5">⚠ Expired</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-sm text-gray-600">
                                            <?= date('M d, Y', strtotime($item['updated_at'])) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= date('h:i A', strtotime($item['updated_at'])) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                        </div>
                                        <p class="font-semibold text-lg text-gray-600 mb-1">No Inventory Found</p>
                                        <p class="text-sm">No pharmacy inventory items found in the system.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
</main>

<?php
// Fetch deliveries for modal dropdown (if needed)
$batchesQuery = "SELECT d.delivery_id, d.batch_no, p.brand_name FROM deliveries d LEFT JOIN products p ON d.product_id = p.product_id ORDER BY d.created_at DESC";
$batchesResult = mysqli_query($conn, $batchesQuery);
$batches = [];
if ($batchesResult && mysqli_num_rows($batchesResult) > 0) {
    while ($row = mysqli_fetch_assoc($batchesResult)) {
        $batches[] = $row;
    }
}
?>
<?php include '../../Modal/modalPharmacy.php'; ?>

<script src="../../Assets/Javascript/errorAndsuccessHander.js"></script>
<script src="../../Assets/Javascript/pharmacyInventory.js?v=<?= time() ?>"></script>
<script>
// Initialize modal handlers
const successMessages = {
    'updated': 'Pharmacy inventory updated successfully.',
    'deleted': 'Pharmacy inventory deleted successfully.'
};

const errorMessages = {
    'forbidden': 'You do not have permission to perform this action.',
    'invalid_id': 'Invalid inventory ID provided.',
    'invalid_quantity': 'Invalid quantity values. Quantities must be non-negative.',
    'has_stock': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const balance = urlParams.get('balance') || '0';
        return 'Cannot delete inventory with existing stock balance.\nCurrent balance: ' + parseInt(balance).toLocaleString() + ' units';
    },
    'update_failed': 'Failed to update pharmacy inventory. Please try again.',
    'delete_failed': 'Failed to delete pharmacy inventory. Please try again.',
    'system_managed': 'Manual creation operations are disabled for system-managed inventory.'
};

initModalHandlers(successMessages, errorMessages);

// Handle has_stock error with balance
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('error') === 'has_stock') {
    const balance = urlParams.get('balance') || '0';
    showErrorModal('Error', 'Cannot delete inventory with existing stock balance.\nCurrent balance: ' + parseInt(balance).toLocaleString() + ' units');
}
</script>
</body>
</html>

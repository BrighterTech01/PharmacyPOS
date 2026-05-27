<?php 
// Include database connection
require_once '../../dbConnection/connection.php';

// Get date filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Fetch all deliveries from database
$query = "SELECT d.delivery_id, d.user_id, d.product_id, d.batch_no, d.expiry_date, d.manufacturing_date,
                 d.quantity_packages, d.quantity_units, d.total_units, d.cost_per_package, d.cost_per_unit,
                 d.selling_price_package, d.selling_price_unit, d.total_cost, d.delivery_date, d.reference_no,
                 d.delivery_no, d.status, d.created_at, d.updated_at,
                 u.username, p.brand_name, p.generic_name, p.strength, p.expire_threshold
          FROM deliveries d 
          LEFT JOIN users u ON d.user_id = u.user_id 
          LEFT JOIN products p ON d.product_id = p.product_id
          WHERE 1=1";

// Add date filter conditions (filter by delivery date)
if (!empty($dateFrom)) {
    $dateFrom = mysqli_real_escape_string($conn, $dateFrom);
    $query .= " AND DATE(d.delivery_date) >= '$dateFrom'";
}

if (!empty($dateTo)) {
    $dateTo = mysqli_real_escape_string($conn, $dateTo);
    $query .= " AND DATE(d.delivery_date) <= '$dateTo'";
}

$query .= " ORDER BY d.delivery_date DESC";

$result = mysqli_query($conn, $query);
$deliveries = [];

// Check if query was successful and has results
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $deliveries[] = $row;
    }
}

// Fetch users for dropdown
$usersQuery = "SELECT user_id, username FROM users ORDER BY username ASC";
$usersResult = mysqli_query($conn, $usersQuery);
$users = [];
if ($usersResult && mysqli_num_rows($usersResult) > 0) {
    while ($row = mysqli_fetch_assoc($usersResult)) {
        $users[] = $row;
    }
}

// Fetch products for dropdown
$productsQuery = "SELECT product_id, brand_name, generic_name, strength FROM products WHERE is_active = 1 ORDER BY brand_name ASC";
$productsResult = mysqli_query($conn, $productsQuery);
$products = [];
if ($productsResult && mysqli_num_rows($productsResult) > 0) {
    while ($row = mysqli_fetch_assoc($productsResult)) {
        $products[] = $row;
    }
}

// Calculate statistics
$totalDeliveries = count($deliveries);
$thisMonthDeliveries = count(array_filter($deliveries, function($d) {
    return !empty($d['delivery_date']) && $d['delivery_date'] !== '0000-00-00' && date('Y-m', strtotime($d['delivery_date'])) === date('Y-m');
}));
$totalPackages = array_sum(array_column($deliveries, 'quantity_packages'));
$totalUnits = array_sum(array_column($deliveries, 'total_units'));

include '../../includes/adminHeader.php'; 
?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>

<!-- Modern Deliveries Management -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gradient-to-br from-gray-50 to-emerald-50/30 min-h-screen">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header Section -->
        <div class="mb-6 sm:mb-8 lg:mb-10">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 sm:gap-6">
                <div class="space-y-2 sm:space-y-3">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="p-1.5 sm:p-2 lg:p-2 bg-white rounded-lg sm:rounded-xl shadow-sm border border-emerald-100">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-3xl xl:text-4xl font-bold text-gray-900">Deliveries Management</h1>
                            <p class="text-xs sm:text-sm lg:text-base text-gray-600 mt-0.5 sm:mt-1">Track and manage supplier deliveries</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards - Modern Design -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-5 xl:gap-6 mb-5 sm:mb-6 lg:mb-8">
            <!-- Total Deliveries -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Total Deliveries</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($totalDeliveries) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-emerald-500 rounded-full mr-1.5 sm:mr-2"></div>
                            <p class="text-xs text-gray-500 truncate">All time deliveries</p>
                        </div>
                    </div>
                    <div class="p-2 sm:p-3 bg-emerald-50 rounded-lg sm:rounded-xl group-hover:bg-emerald-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Packages -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Total Packages</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($totalPackages) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-orange-500 rounded-full mr-1.5 sm:mr-2"></div>
                            <p class="text-xs text-gray-500 truncate">Packages delivered</p>
                        </div>
                    </div>
                    <div class="p-2 sm:p-3 bg-orange-50 rounded-lg sm:rounded-xl group-hover:bg-orange-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Units -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Total Units</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($totalUnits) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-indigo-500 rounded-full mr-1.5 sm:mr-2"></div>
                            <p class="text-xs text-gray-500 truncate">Units delivered</p>
                        </div>
                    </div>
                    <div class="p-2 sm:p-3 bg-indigo-50 rounded-lg sm:rounded-xl group-hover:bg-indigo-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- This Month -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">This Month</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($thisMonthDeliveries) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-purple-500 rounded-full mr-1.5 sm:mr-2"></div>
                            <p class="text-xs text-gray-500 truncate">Current month deliveries</p>
                        </div>
                    </div>
                    <div class="p-2 sm:p-3 bg-purple-50 rounded-lg sm:rounded-xl group-hover:bg-purple-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 border border-white/50 mb-6 sm:mb-8">
            <div class="flex items-center gap-2 sm:gap-3 mb-4 sm:mb-6">
                    <div class="p-1.5 sm:p-2 bg-emerald-50 rounded-lg">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                    </div>
                    <div>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900">Filters</h3>
                    <p class="text-xs sm:text-sm text-gray-500">Search and filter deliveries</p>
                    </div>
                </div>
                
            <div class="flex flex-col gap-3 sm:gap-4 lg:gap-5">
                    <!-- Search Bar -->
                    <div class="w-full">
                        <div class="relative">
                            <input 
                                type="text" 
                                id="searchInput" 
                                placeholder="Search by product name or delivery date..." 
                            class="w-full pl-10 sm:pl-12 lg:pl-14 pr-4 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm transition-all duration-200 text-sm lg:text-base"
                                onkeyup="filterDeliveries()"
                            >
                        <svg class="absolute left-3 sm:left-4 lg:left-5 top-1/2 transform -translate-y-1/2 w-4 h-4 lg:w-5 lg:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                    
                <!-- Filter Row -->
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 lg:gap-4">
                    <!-- Date Filter -->
                    <form method="GET" action="" id="dateFilterForm" class="flex flex-col sm:flex-row gap-2 sm:gap-3 flex-1 sm:min-w-0">
                        <div class="relative flex-1 sm:flex-initial sm:min-w-[140px] lg:min-w-[160px] xl:min-w-[180px]">
                            <label for="date_from" class="sr-only">From Delivery Date</label>
                            <input 
                                type="date" 
                                id="date_from" 
                                name="date_from" 
                                value="<?= htmlspecialchars($dateFrom) ?>"
                                placeholder="From Delivery Date"
                                class="w-full sm:w-auto px-3 sm:px-4 lg:px-5 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm transition-all duration-200 text-sm lg:text-base date-filter-input"
                                title="Filter by delivery date (From)"
                            >
                        </div>
                        <div class="relative flex-1 sm:flex-initial sm:min-w-[140px] lg:min-w-[160px] xl:min-w-[180px]">
                            <label for="date_to" class="sr-only">To Delivery Date</label>
                            <input 
                                type="date" 
                                id="date_to" 
                                name="date_to" 
                                value="<?= htmlspecialchars($dateTo) ?>"
                                placeholder="To Delivery Date"
                                class="w-full sm:w-auto px-3 sm:px-4 lg:px-5 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm transition-all duration-200 text-sm lg:text-base date-filter-input"
                                title="Filter by delivery date (To)"
                            >
                        </div>
                    </form>
                    
                    <!-- User Filter -->
                    <div class="w-full sm:w-52 lg:w-56 xl:w-60 2xl:w-64">
                        <select id="userFilter" onchange="filterDeliveries()" class="w-full px-3 sm:px-4 lg:px-5 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm transition-all duration-200 text-sm lg:text-base">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= htmlspecialchars($user['username']) ?>"><?= htmlspecialchars($user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Clear Filters Button -->
                    <button onclick="clearDeliveryFilters()" class="px-3 sm:px-4 lg:px-6 py-2.5 sm:py-3 lg:py-3.5 bg-gray-200 text-gray-700 rounded-lg sm:rounded-xl hover:bg-gray-300 transition-all duration-200 text-xs sm:text-sm lg:text-base font-semibold flex items-center justify-center gap-2 whitespace-nowrap">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    <span class="hidden sm:inline">Clear</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Deliveries Table Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 border border-white/50">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4 sm:mb-6 gap-3 sm:gap-4">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-1.5 sm:p-2 bg-emerald-50 rounded-lg">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900">Deliveries List</h3>
                        <p class="text-xs sm:text-sm text-gray-500">Track and manage supplier deliveries</p>
                    </div>
                            </div>
                
                <!-- Action Controls -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <button onclick="printFullReport()" class="px-3 sm:px-4 py-2 sm:py-2.5 bg-emerald-600 text-white rounded-lg sm:rounded-xl hover:bg-emerald-700 transition-all text-xs sm:text-sm font-semibold shadow-sm hover:shadow-md flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Print Full Report
                    </button>
                    <button onclick="openAddDeliveryModal()" class="px-3 sm:px-4 py-2 sm:py-2.5 bg-gradient-to-r from-emerald-600 to-emerald-700 text-white rounded-lg sm:rounded-xl hover:from-emerald-700 hover:to-emerald-800 transition-all duration-200 text-xs sm:text-sm font-semibold flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add Delivery
                    </button>
                </div>
            </div>
            
            <!-- Deliveries Table -->
            <div id="deliveriesTableWrapper" class="overflow-x-auto rounded-xl border border-gray-200">
                <style>
                    /* Custom scrollbar for responsive tables */
                    #deliveriesTableWrapper::-webkit-scrollbar {
                        height: 8px;
                    }
                    #deliveriesTableWrapper::-webkit-scrollbar-track {
                        background: #f1f1f1;
                        border-radius: 10px;
                    }
                    #deliveriesTableWrapper::-webkit-scrollbar-thumb {
                        background: #888;
                        border-radius: 10px;
                    }
                    #deliveriesTableWrapper::-webkit-scrollbar-thumb:hover {
                        background: #555;
                    }
                    #deliveriesTableWrapper {
                        scrollbar-width: thin;
                        scrollbar-color: #888 #f1f1f1;
                        max-height: 640px; /* Approximately 10 table rows */
                        overflow-y: auto;
                    }
                </style>
                <table class="w-full min-w-[1000px]">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                        <tr>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">ID</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Delivery No</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[120px]">Product</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Batch No</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Pkg</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Units/Pkg</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Total Units</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Cost/Pkg</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Cost/Unit</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Sell/Pkg</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Sell/Unit</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Expiry Date</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Delivery Date</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Status</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap sticky right-0 bg-gradient-to-r from-gray-50 to-gray-100/80">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="deliveriesTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if (count($deliveries) > 0): ?>
                            <?php foreach ($deliveries as $delivery): ?>
                                <?php
                                    $productKeywords = strtolower(trim(($delivery['brand_name'] ?? '') . ' ' . ($delivery['generic_name'] ?? '')));
                                    $deliveryDateRaw = strtolower((string)($delivery['delivery_date'] ?? ''));
                                    $deliveryDateFormatted = '';
                                    if (!empty($delivery['delivery_date']) && $delivery['delivery_date'] !== '0000-00-00') {
                                        $timeStamp = strtotime($delivery['delivery_date']);
                                        if ($timeStamp) {
                                            $deliveryDateFormatted = strtolower(date('M d, Y', $timeStamp) . ' ' . date('Y-m-d', $timeStamp));
                                        }
                                    }
                                    $deliveryKeywords = trim($deliveryDateRaw . ' ' . $deliveryDateFormatted);
                                ?>
                                <tr class="hover:bg-gray-50/50 transition-colors duration-150 cursor-pointer"
                                    onclick="openViewDeliveryModal(<?= htmlspecialchars(json_encode($delivery)) ?>)"
                                    data-delivery-id="<?= $delivery['delivery_id'] ?>"
                                    data-user="<?= htmlspecialchars($delivery['username'] ?? '') ?>"
                                    data-packages="<?= (int)($delivery['quantity_packages'] ?? 0) ?>"
                                    data-total-units="<?= (int)($delivery['total_units'] ?? 0) ?>"
                                    data-total-cost="<?= (float)($delivery['total_cost'] ?? 0) ?>"
                                    data-product="<?= htmlspecialchars($productKeywords) ?>"
                                    data-delivery-date="<?= htmlspecialchars($deliveryKeywords) ?>">
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                            #<?= $delivery['delivery_id'] ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                            <?= !empty($delivery['reference_no']) ? htmlspecialchars($delivery['reference_no']) : 'N/A' ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap min-w-[120px] delivery-product-cell">
                                        <div class="text-xs sm:text-sm font-semibold text-gray-900">
                                            <?= htmlspecialchars($delivery['brand_name'] ?? 'N/A') ?>
                                        </div>
                                        <?php if (!empty($delivery['generic_name'])): ?>
                                            <div class="text-xs text-gray-500">
                                                <?= htmlspecialchars($delivery['generic_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($delivery['strength'])): ?>
                                            <div class="text-xs text-gray-400">
                                                ⚡ <?= htmlspecialchars($delivery['strength']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="text-xs sm:text-sm font-medium text-gray-700">
                                            <?= htmlspecialchars($delivery['batch_no'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="text-xs sm:text-sm font-semibold text-gray-900">
                                            <?= number_format((int)($delivery['quantity_packages'] ?? 0)) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="text-xs sm:text-sm font-semibold text-gray-900">
                                            <?= number_format((int)($delivery['quantity_units'] ?? 0)) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="text-xs sm:text-sm font-semibold text-indigo-600">
                                            <?= number_format((int)($delivery['total_units'] ?? 0)) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right text-xs sm:text-sm text-gray-700">
                                        ₱<?= number_format((float)($delivery['cost_per_package'] ?? 0), 2) ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right text-xs sm:text-sm text-gray-700">
                                        ₱<?= number_format((float)($delivery['cost_per_unit'] ?? 0), 2) ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right text-xs sm:text-sm text-gray-700">
                                        ₱<?= number_format((float)($delivery['selling_price_package'] ?? 0), 2) ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right text-xs sm:text-sm font-semibold text-green-600">
                                        ₱<?= number_format((float)($delivery['selling_price_unit'] ?? 0), 2) ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-left">
                                        <?php 
                                        $expiryDate = $delivery['expiry_date'] ?? null;
                                        $isExpired = false;
                                        $isExpiringSoon = false;
                                        $expireThresholdValue = isset($delivery['expire_threshold']) ? (int)$delivery['expire_threshold'] : 0;
                                        if ($expireThresholdValue <= 0) {
                                            $expireThresholdValue = 30;
                                        }
                                        $daysUntilExpiry = null;
                                        if ($expiryDate && $expiryDate !== '0000-00-00' && $expiryDate !== '2099-12-31') {
                                            $expiryTimestamp = strtotime($expiryDate);
                                            $todayTimestamp = strtotime('today');
                                            if ($expiryTimestamp < $todayTimestamp) {
                                                $isExpired = true;
                                            } else {
                                                $daysUntilExpiry = (int)floor(($expiryTimestamp - $todayTimestamp) / 86400);
                                                if ($daysUntilExpiry <= $expireThresholdValue) {
                                                    $isExpiringSoon = true;
                                                }
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($expiryDate) && $expiryDate !== '0000-00-00' && $expiryDate !== '2099-12-31'): ?>
                                            <div class="text-xs sm:text-sm <?= $isExpired ? 'text-red-700 font-semibold' : 'text-gray-700' ?>">
                                                <?= date('M d, Y', strtotime($expiryDate)) ?>
                                            </div>
                                            <?php if ($isExpired): ?>
                                                <div class="text-xs text-red-600 mt-0.5">⚠ Expired</div>
                                            <?php elseif ($isExpiringSoon): ?>
                                                <div class="text-xs text-amber-600 mt-0.5">
                                                    ⚠ Expiring Soon<?= is_int($daysUntilExpiry) ? ' · ' . max($daysUntilExpiry, 0) . 'd left' : '' ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-left delivery-date-cell">
                                        <?php if (!empty($delivery['delivery_date']) && $delivery['delivery_date'] !== '0000-00-00'): ?>
                                            <div class="text-xs sm:text-sm text-gray-700">
                                                <?= date('M d, Y', strtotime($delivery['delivery_date'])) ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?= date('h:i A', strtotime($delivery['delivery_date'])) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                        <?php
                                        $status = $delivery['status'] ?? 'Active';
                                        $statusColors = [
                                            'Active' => 'bg-green-100 text-green-800',
                                            'Expired' => 'bg-red-100 text-red-800',
                                            'Sold Out' => 'bg-gray-100 text-gray-800'
                                        ];
                                        $statusColor = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold <?= $statusColor ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                        <?php if ($isExpiringSoon && !$isExpired): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 mt-1">
                                                Expiring Soon
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center sticky right-0 bg-white" onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-center gap-1 sm:gap-1.5">
                                            <button onclick='openEditDeliveryModal(<?= json_encode($delivery) ?>)' class="inline-flex items-center px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-all duration-200 text-xs font-semibold" title="Edit">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="14" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                            </svg>
                                        </div>
                                        <p class="font-semibold text-lg text-gray-600 mb-1">No Deliveries Found</p>
                                        <p class="text-sm">No deliveries found in the system.</p>
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

<!-- Add Delivery Modal -->
<?php include '../../Modal/modalDeliveries.php'; ?>

<script src="../../Assets/Javascript/errorAndsuccessHander.js"></script>
<script src="../../Assets/Javascript/deliveries.js?v=<?= time() ?>"></script>
<script>
// Initialize modal handlers
const successMessages = {
    'created': 'Delivery created successfully.',
    'updated': 'Delivery updated successfully.',
};

const errorMessages = {
    'missing_fields': 'Product, Batch No, Received By and Delivery Date are required.',
    'duplicate_batch': 'Batch number already exists. Please use a different one.',
    'invalid_id': 'Invalid delivery selected.',
};

initModalHandlers(successMessages, errorMessages);

// Auto-filter on date change
document.addEventListener('DOMContentLoaded', function() {
    const dateInputs = document.querySelectorAll('.date-filter-input');
    const dateFilterForm = document.getElementById('dateFilterForm');
    let filterTimeout;
    
    if (dateInputs.length > 0 && dateFilterForm) {
        dateInputs.forEach(input => {
            input.addEventListener('change', function() {
                // Clear any existing timeout
                clearTimeout(filterTimeout);
                
                // Auto-submit after a short delay to allow both dates to be selected
                filterTimeout = setTimeout(function() {
                    // Submit the form
                    dateFilterForm.submit();
                }, 500); // 500ms delay
            });
        });
        
        // Also auto-filter when both dates are filled and user leaves the second input
        const dateTo = document.getElementById('date_to');
        if (dateTo) {
            dateTo.addEventListener('blur', function() {
                const dateFrom = document.getElementById('date_from');
                if (dateFrom && dateFrom.value && dateTo.value) {
                    clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(function() {
                        dateFilterForm.submit();
                    }, 300);
                }
            });
        }
    }
});

// Ensure function is available immediately after script loads
(function() {
  function verifyFunction() {
    if (typeof window.openViewDeliveryModal === 'function') {
      if (typeof openViewDeliveryModal === 'undefined') {
        openViewDeliveryModal = window.openViewDeliveryModal;
      }
      return true;
    }
    return false;
  }
  
  // Check immediately
  if (!verifyFunction()) {
    setTimeout(function() {
      verifyFunction();
    }, 100);
  }
  
})();

// Filter functionality for deliveries (fallback wrapper in case external script fails)
function filterDeliveries() {
  const searchEl = document.getElementById('searchInput');
  const userFilterEl = document.getElementById('userFilter');
  const table = document.getElementById('deliveriesTableBody');

  if (!table) return;

  const searchValue = (searchEl?.value || '').trim().toLowerCase();
  const userFilter = userFilterEl ? userFilterEl.value : '';

  const rows = table.querySelectorAll('tr');
  rows.forEach(row => {
    const dataUser = row.getAttribute('data-user') || '';
    const productKeywords = row.dataset.product || '';
    const deliveryKeywords = row.dataset.deliveryDate || '';

    const matchesSearch =
      !searchValue ||
      productKeywords.includes(searchValue) ||
      deliveryKeywords.includes(searchValue);

    const matchesUser = !userFilter || dataUser === userFilter;

    row.style.display = matchesSearch && matchesUser ? '' : 'none';
  });
}

function clearDeliveryFilters() {
  document.getElementById('searchInput').value = '';
  document.getElementById('userFilter').value = '';
  // Note: Date filters are cleared by reloading the page
  window.location.href = window.location.pathname;
}

window.filterDeliveries = filterDeliveries;
window.clearDeliveryFilters = clearDeliveryFilters;

</script>
</body>
</html>
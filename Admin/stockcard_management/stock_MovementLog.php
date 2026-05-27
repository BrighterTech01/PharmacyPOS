<?php 
// Start session if not started
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Check if user is logged in and is admin
$isAdmin = false;
if (!empty($_SESSION['user']['role'])) { 
    $isAdmin = strtolower($_SESSION['user']['role']) === 'admin'; 
} elseif (!empty($_SESSION['role'])) { 
    $isAdmin = strtolower($_SESSION['role']) === 'admin'; 
}

if (!$isAdmin) {
    header('Location: ../../auth/login.php?error=unauthorized');
    exit();
}

// Include database connection
require_once '../../dbConnection/connection.php';

// Get filters from URL parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$movementTypeFilter = isset($_GET['movement_type']) ? $_GET['movement_type'] : '';
$userFilter = isset($_GET['user']) ? $_GET['user'] : '';

// Build WHERE clause
$whereConditions = [];
if ($startDate && $endDate) {
    $whereConditions[] = "DATE(sml.movement_date) BETWEEN '" . mysqli_real_escape_string($conn, $startDate) . "' AND '" . mysqli_real_escape_string($conn, $endDate) . "'";
} elseif ($startDate) {
    $whereConditions[] = "DATE(sml.movement_date) >= '" . mysqli_real_escape_string($conn, $startDate) . "'";
} elseif ($endDate) {
    $whereConditions[] = "DATE(sml.movement_date) <= '" . mysqli_real_escape_string($conn, $endDate) . "'";
}

if ($movementTypeFilter) {
    $whereConditions[] = "sml.movement_type = '" . mysqli_real_escape_string($conn, $movementTypeFilter) . "'";
}

if ($userFilter) {
    $whereConditions[] = "u.username = '" . mysqli_real_escape_string($conn, $userFilter) . "'";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Fetch all stock movement logs from database
// Note: batch_id column was removed from stock_movement_log, batch info now comes from deliveries via reference_id
// For sales: reference_id = sale_id, we need to get delivery_id from sales_details
// Since sales_details doesn't have product_id, we'll get the first delivery_id for that sale
// For transfers: reference_id = transfer_id, join stock_transfers to get delivery_id, then deliveries for batch_no
// For deliveries: reference_id = delivery_id, directly join deliveries
$query = "SELECT 
            sml.*,
            p.brand_name,
            p.generic_name,
            p.strength,
            CASE 
                WHEN sml.reference_type = 'delivery' THEN d.batch_no
                WHEN sml.reference_type = 'transfer' THEN d_transfer.batch_no
                WHEN sml.reference_type = 'sale' THEN (
                    SELECT d_sale_sub.batch_no 
                    FROM sales_details sd_sub
                    INNER JOIN deliveries d_sale_sub ON sd_sub.delivery_id = d_sale_sub.delivery_id
                    WHERE sd_sub.sale_id = sml.reference_id
                    LIMIT 1
                )
                ELSE NULL
            END AS batch_no,
            CASE 
                WHEN sml.reference_type = 'delivery' THEN d.expiry_date
                WHEN sml.reference_type = 'transfer' THEN d_transfer.expiry_date
                WHEN sml.reference_type = 'sale' THEN (
                    SELECT d_sale_sub.expiry_date 
                    FROM sales_details sd_sub
                    INNER JOIN deliveries d_sale_sub ON sd_sub.delivery_id = d_sale_sub.delivery_id
                    WHERE sd_sub.sale_id = sml.reference_id
                    LIMIT 1
                )
                ELSE NULL
            END AS expiry_date,
            u.username,
            ut.unit_name
          FROM stock_movement_log sml
          LEFT JOIN products p ON sml.product_id = p.product_id
          LEFT JOIN deliveries d ON sml.reference_type = 'delivery' AND sml.reference_id = d.delivery_id
          LEFT JOIN stock_transfers st ON sml.reference_type = 'transfer' AND sml.reference_id = st.transfer_id
          LEFT JOIN deliveries d_transfer ON st.delivery_id = d_transfer.delivery_id
          LEFT JOIN users u ON sml.user_id = u.user_id
          LEFT JOIN unit_types ut ON sml.unit_type_id = ut.unit_type_id
          $whereClause
          ORDER BY sml.movement_date DESC";
$result = mysqli_query($conn, $query);
$movements = [];

// Check if query was successful and has results
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $movements[] = $row;
    }
}

// Calculate statistics
$totalMovements = count($movements);
$deliveries = count(array_filter($movements, function($m) { return $m['movement_type'] === 'delivery'; }));
$sales = count(array_filter($movements, function($m) { return $m['movement_type'] === 'sale'; }));
$transfers = count(array_filter($movements, function($m) { return $m['movement_type'] === 'transfer'; }));
$adjustments = count(array_filter($movements, function($m) { return $m['movement_type'] === 'adjustment'; }));

// Group by type for display
$movementTypes = [
    'delivery' => ['label' => 'Delivery', 'color' => 'emerald', 'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4'],
    'sale' => ['label' => 'Sale', 'color' => 'blue', 'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z'],
    'transfer' => ['label' => 'Transfer', 'color' => 'purple', 'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
    'adjustment' => ['label' => 'Adjustment', 'color' => 'orange', 'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4']
];

// Get unique users for filter dropdown
$usersQuery = "SELECT DISTINCT u.username 
               FROM stock_movement_log sml
               LEFT JOIN users u ON sml.user_id = u.user_id
               WHERE u.username IS NOT NULL
               ORDER BY u.username";
$usersResult = mysqli_query($conn, $usersQuery);
$uniqueUsers = [];
if ($usersResult) {
    while ($row = mysqli_fetch_assoc($usersResult)) {
        $uniqueUsers[] = $row['username'];
    }
}

include '../../includes/adminHeader.php';
?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>
<?php 
?>
<!-- Stock Movement Log -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gradient-to-br from-gray-50 to-indigo-50/30 min-h-screen">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white rounded-xl shadow-sm border border-indigo-100">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Stock Movement Log</h1>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8">
            <!-- Total Movements -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Total Movements</p>
                        <p class="text-lg sm:text-2xl font-bold text-gray-900"><?= number_format($totalMovements) ?></p>
                    </div>
                    <div class="p-2 sm:p-3 bg-indigo-50 rounded-lg sm:rounded-xl">
                        <svg class="w-4 h-4 sm:w-6 sm:h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Deliveries -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Deliveries</p>
                        <p class="text-lg sm:text-2xl font-bold text-gray-900"><?= number_format($deliveries) ?></p>
                    </div>
                    <div class="p-2 sm:p-3 bg-emerald-50 rounded-lg sm:rounded-xl">
                        <svg class="w-4 h-4 sm:w-6 sm:h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Sales -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Sales</p>
                        <p class="text-lg sm:text-2xl font-bold text-gray-900"><?= number_format($sales) ?></p>
                    </div>
                    <div class="p-2 sm:p-3 bg-blue-50 rounded-lg sm:rounded-xl">
                        <svg class="w-4 h-4 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Transfers -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Transfers</p>
                        <p class="text-lg sm:text-2xl font-bold text-gray-900"><?= number_format($transfers) ?></p>
                    </div>
                    <div class="p-2 sm:p-3 bg-purple-50 rounded-lg sm:rounded-xl">
                        <svg class="w-4 h-4 sm:w-6 sm:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Movement Log Table Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 border border-white/50">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4 sm:mb-6 gap-4">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-1.5 sm:p-2 bg-indigo-50 rounded-lg">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900">Movement History</h3>
                        <p class="text-xs sm:text-sm text-gray-500">Complete audit trail</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button onclick="printFullReport()" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-all text-sm font-semibold shadow-sm hover:shadow-md flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Print Full Report
                    </button>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="bg-gray-50 rounded-xl p-4 sm:p-6 mb-6 border border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-base sm:text-lg font-semibold text-gray-900">Filters</h4>
                    <button onclick="clearFilters()" class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Clear Filters
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
                    <!-- Start Date -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Start Date</label>
                        <div class="relative">
                            <input type="date" id="filterStartDate" value="<?= htmlspecialchars($startDate) ?>" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                            <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- End Date -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">End Date</label>
                        <div class="relative">
                            <input type="date" id="filterEndDate" value="<?= htmlspecialchars($endDate) ?>" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                            <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Movement Type -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Movement Type</label>
                        <select id="filterMovementType" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                            onchange="applyFilters()">
                            <option value="">All Types</option>
                            <option value="delivery" <?= $movementTypeFilter === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                            <option value="sale" <?= $movementTypeFilter === 'sale' ? 'selected' : '' ?>>Sale</option>
                            <option value="transfer" <?= $movementTypeFilter === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                            <option value="adjustment" <?= $movementTypeFilter === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                        </select>
                    </div>
                    
                    <!-- User Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">User</label>
                        <select id="filterUser" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                            onchange="applyFilters()">
                            <option value="">All Users</option>
                            <?php foreach ($uniqueUsers as $user): ?>
                                <option value="<?= htmlspecialchars($user) ?>" <?= $userFilter === $user ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Search -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Search</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="searchInput" 
                                placeholder="Product, batch..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                                onkeyup="filterMovements()"
                            >
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Movement Log Table - Desktop View -->
            <style>
                .movement-table-scroll {
                    max-height: 640px; /* Approximately 10 table rows */
                    overflow-y: auto;
                }
                .movement-table-scroll::-webkit-scrollbar {
                    width: 8px;
                }
                .movement-table-scroll::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 10px;
                }
                .movement-table-scroll::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 10px;
                }
                .movement-table-scroll::-webkit-scrollbar-thumb:hover {
                    background: #555;
                }
            </style>
            <div class="hidden lg:block overflow-hidden rounded-xl border border-gray-200">
                <div class="movement-table-scroll">
                <table class="w-full min-w-full">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                        <tr>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Date/Time</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Product</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Batch</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Movement Type</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">From → To</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Quantity</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Reference</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">User</th>
                        </tr>
                    </thead>
                    <tbody id="movementsTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if (count($movements) > 0): ?>
                            <?php foreach ($movements as $movement): 
                                $typeInfo = $movementTypes[$movement['movement_type']] ?? ['label' => ucfirst($movement['movement_type']), 'color' => 'gray', 'icon' => ''];
                            ?>
                                <tr class="hover:bg-gray-50/50 transition-colors cursor-pointer movement-row" onclick="openViewMovementModal(<?= htmlspecialchars(json_encode($movement)) ?>)" data-product="<?= htmlspecialchars(strtolower($movement['brand_name'] ?? '')) ?>" data-batch="<?= htmlspecialchars(strtolower($movement['batch_no'] ?? '')) ?>" data-type="<?= htmlspecialchars(strtolower($movement['movement_type'] ?? '')) ?>" data-packages="<?= (int)($movement['quantity_packages'] ?? 0) ?>" data-units="<?= (int)($movement['quantity_units'] ?? 0) ?>">
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-xs sm:text-sm text-gray-900">
                                            <?= date('M d, Y', strtotime($movement['movement_date'])) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= date('h:i A', strtotime($movement['movement_date'])) ?>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="min-w-0">
                                                <div class="text-xs sm:text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($movement['brand_name'] ?? 'N/A') ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($movement['generic_name'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <?php if (!empty($movement['batch_no'])): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                                <?= htmlspecialchars($movement['batch_no']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-<?= $typeInfo['color'] ?>-100 text-<?= $typeInfo['color'] ?>-800">
                                            <?= $typeInfo['label'] ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-600 capitalize"><?= htmlspecialchars($movement['from_inventory']) ?></span>
                                            <svg class="w-3 h-3 sm:w-4 sm:h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                            </svg>
                                            <span class="text-xs text-gray-600 capitalize"><?= htmlspecialchars($movement['to_inventory']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <div class="text-xs sm:text-sm text-gray-900">
                                            <?php if ($movement['quantity_packages'] > 0): ?>
                                                <span class="font-semibold"><?= number_format($movement['quantity_packages']) ?> pkg</span>
                                            <?php endif; ?>
                                            <?php if ($movement['quantity_units'] > 0): ?>
                                                <span class="text-gray-600">
                                                    (<?= number_format($movement['quantity_units']) ?> <?= htmlspecialchars($movement['unit_name'] ?? 'units') ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-xs sm:text-sm text-gray-700">
                                            <?php if ($movement['reference_type'] && $movement['reference_id']): ?>
                                                <?php
                                                    $prefixMap = [
                                                        'sale' => 'S',
                                                        'delivery' => 'D',
                                                        'transfer' => 'T',
                                                        'adjustment' => 'A'
                                                    ];
                                                    $prefix = $prefixMap[strtolower($movement['reference_type'])] ?? strtoupper(substr($movement['reference_type'], 0, 1));
                                                    $formattedId = str_pad((string)$movement['reference_id'], 7, '0', STR_PAD_LEFT);
                                                ?>
                                                <span class="uppercase"><?= htmlspecialchars($prefix) ?>#<?= htmlspecialchars($formattedId) ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="text-xs sm:text-sm text-gray-700"><?= htmlspecialchars($movement['username'] ?? 'System') ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                            </svg>
                                        </div>
                                        <p class="font-semibold text-lg text-gray-600 mb-1">No Movements Found</p>
                                        <p class="text-sm">No stock movements recorded yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Mobile/Tablet Card View -->
            <div class="lg:hidden space-y-4 mt-6">
                <?php if (count($movements) > 0): ?>
                    <?php foreach ($movements as $movement): 
                        $typeInfo = $movementTypes[$movement['movement_type']] ?? ['label' => ucfirst($movement['movement_type']), 'color' => 'gray', 'icon' => ''];
                    ?>
                        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-200 movement-card cursor-pointer" onclick="openViewMovementModal(<?= htmlspecialchars(json_encode($movement)) ?>)" data-product="<?= htmlspecialchars(strtolower($movement['brand_name'] ?? '')) ?>" data-batch="<?= htmlspecialchars(strtolower($movement['batch_no'] ?? '')) ?>" data-type="<?= htmlspecialchars(strtolower($movement['movement_type'] ?? '')) ?>">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-<?= $typeInfo['color'] ?>-100 text-<?= $typeInfo['color'] ?>-800">
                                            <?= $typeInfo['label'] ?>
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            <?= date('M d, Y h:i A', strtotime($movement['movement_date'])) ?>
                                        </span>
                                    </div>
                                    <h4 class="text-sm font-semibold text-gray-900 mb-1"><?= htmlspecialchars($movement['brand_name'] ?? 'N/A') ?></h4>
                                    <?php if (!empty($movement['generic_name'])): ?>
                                        <p class="text-xs text-gray-500 mb-2"><?= htmlspecialchars($movement['generic_name']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="space-y-2 text-sm">
                                <?php if (!empty($movement['batch_no'])): ?>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-600">Batch:</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800">
                                            <?= htmlspecialchars($movement['batch_no']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-medium text-gray-600">From → To:</span>
                                    <span class="text-xs text-gray-700 capitalize"><?= htmlspecialchars($movement['from_inventory']) ?></span>
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                    <span class="text-xs text-gray-700 capitalize"><?= htmlspecialchars($movement['to_inventory']) ?></span>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-medium text-gray-600">Quantity:</span>
                                    <span class="text-xs text-gray-900">
                                        <?php if ($movement['quantity_packages'] > 0): ?>
                                            <span class="font-semibold"><?= number_format($movement['quantity_packages']) ?> pkg</span>
                                        <?php endif; ?>
                                        <?php if ($movement['quantity_units'] > 0): ?>
                                            <span class="text-gray-600">
                                                (<?= number_format($movement['quantity_units']) ?> <?= htmlspecialchars($movement['unit_name'] ?? 'units') ?>)
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if ($movement['reference_type'] && $movement['reference_id']): ?>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-600">Reference:</span>
                                        <?php
                                            $prefixMap = [
                                                'sale' => 'S',
                                                'delivery' => 'D',
                                                'transfer' => 'T',
                                                'adjustment' => 'A'
                                            ];
                                            $prefix = $prefixMap[strtolower($movement['reference_type'])] ?? strtoupper(substr($movement['reference_type'], 0, 1));
                                            $formattedId = str_pad((string)$movement['reference_id'], 7, '0', STR_PAD_LEFT);
                                        ?>
                                        <span class="text-xs text-gray-700 uppercase"><?= htmlspecialchars($prefix) ?>#<?= htmlspecialchars($formattedId) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-medium text-gray-600">User:</span>
                                    <span class="text-xs text-gray-700"><?= htmlspecialchars($movement['username'] ?? 'System') ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white rounded-xl p-8 text-center border border-gray-200">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <p class="font-semibold text-lg text-gray-600 mb-1">No Movements Found</p>
                        <p class="text-sm text-gray-500">No stock movements recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- View Movement Details Modal -->
<?php include '../../Modal/modalStockMovement.php'; ?>

<script src="../../Assets/Javascript/stockMovement.js?v=<?= time() ?>"></script>
<script src="../../Assets/Javascript/errorAndsuccessHander.js"></script>
<script>
// Initialize modal handlers (no URL parameters for stock movement log)
initModalHandlers({}, {});
</script>
</body>
</html>


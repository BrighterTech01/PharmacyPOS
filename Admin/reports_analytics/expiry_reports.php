<?php include '../../includes/adminHeader.php' ?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>
<?php
// Database connection
require_once '../../dbConnection/connection.php';

// Get date range from URL parameters or default to next 90 days
$daysAhead = isset($_GET['days_ahead']) ? (int)$_GET['days_ahead'] : 90;
$criticalDays = isset($_GET['critical_days']) ? (int)$_GET['critical_days'] : 30;

// Check if view_expiry_report exists
$hasExpireView = false;
$expireViewCheck = mysqli_query($conn, "SHOW TABLES LIKE 'view_expiry_report'");
if ($expireViewCheck && mysqli_num_rows($expireViewCheck) > 0) {
    $hasExpireView = true;
}
if ($expireViewCheck) {
    mysqli_free_result($expireViewCheck);
}

// Get expiring products data
$expiringProducts = [];
$expiryStats = [
    'total_expiring' => 0,
    'critical_expiring' => 0,
    'expired_products' => 0,
    'total_units_expiring' => 0
];

if ($hasExpireView) {
    // Get products expiring within specified days using view_expiry_report
    $query = "SELECT 
                ver.delivery_id,
                ver.product_id,
                ver.brand_name,
                ver.generic_name,
                ver.strength,
                ver.batch_no,
                ver.expiry_date,
                DATEDIFF(ver.expiry_date, CURDATE()) as days_until_expiry,
                ver.status as expiry_status,
                ver.storage_packages_balance as storage_packages,
                ver.storage_packages_balance as storage_units,
                ver.pharmacy_units_balance as pharmacy_units,
                (COALESCE(ver.storage_packages_balance, 0) + COALESCE(ver.pharmacy_units_balance, 0)) as quantity,
                COALESCE(p.expire_threshold, 30) as expire_threshold,
                COALESCE(s.supplier_name, 'N/A') as supplier_name,
                p.barcode
              FROM view_expiry_report ver
              LEFT JOIN products p ON ver.product_id = p.product_id
              LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
              WHERE ver.expiry_date <= DATE_ADD(CURDATE(), INTERVAL $daysAhead DAY)
              ORDER BY ver.expiry_date ASC, days_until_expiry ASC";
    
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $expiringProducts[] = $row;
        }
    }
    
    // Get expiry statistics
    $statsQuery = "SELECT 
                    COUNT(*) as total_expiring,
                    SUM(CASE WHEN ver.expiry_date < CURDATE() THEN 1 
                             WHEN DATEDIFF(ver.expiry_date, CURDATE()) <= COALESCE(p.expire_threshold, 30) THEN 1 
                             ELSE 0 END) as critical_expiring,
                    SUM(CASE WHEN ver.expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired_products,
                    SUM(COALESCE(ver.storage_packages_balance, 0) + COALESCE(ver.pharmacy_units_balance, 0)) as total_units_expiring
                  FROM view_expiry_report ver
                  LEFT JOIN products p ON ver.product_id = p.product_id
                  WHERE ver.expiry_date <= DATE_ADD(CURDATE(), INTERVAL $daysAhead DAY)";
    
    $statsResult = mysqli_query($conn, $statsQuery);
    if ($statsResult && ($statsRow = mysqli_fetch_assoc($statsResult))) {
        $expiryStats = [
            'total_expiring' => (int)($statsRow['total_expiring'] ?? 0),
            'critical_expiring' => (int)($statsRow['critical_expiring'] ?? 0),
            'expired_products' => (int)($statsRow['expired_products'] ?? 0),
            'total_units_expiring' => (int)($statsRow['total_units_expiring'] ?? 0)
        ];
    }
} else {
    // Fallback to basic query using deliveries and inventory
    $query = "SELECT 
                d.delivery_id as batch_id,
                d.batch_no,
                p.product_id,
                p.brand_name,
                p.generic_name,
                p.strength,
                COALESCE(s.supplier_name, 'N/A') as supplier_name,
                p.barcode,
                d.expiry_date,
                DATEDIFF(d.expiry_date, CURDATE()) as days_until_expiry,
                CASE 
                    WHEN DATEDIFF(d.expiry_date, CURDATE()) < 0 THEN 'Expired'
                    WHEN DATEDIFF(d.expiry_date, CURDATE()) <= COALESCE(p.expire_threshold, 30) THEN 'Critical'
                    ELSE 'Warning'
                END as expiry_status,
                COALESCE(si.quantity_packages_balance, 0) as storage_packages,
                COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0) as storage_units,
                COALESCE(pi.quantity_units_balance, 0) as pharmacy_units,
                COALESCE(pi.quantity_units_balance, 0) + COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0) as quantity,
                COALESCE(p.expire_threshold, 30) as expire_threshold
              FROM deliveries d
              JOIN products p ON d.product_id = p.product_id
              LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
              LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
              LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
              WHERE d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL $daysAhead DAY)
                AND d.status = 'Active'
              ORDER BY d.expiry_date ASC";
    
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $expiringProducts[] = $row;
        }
    }
    
    // Calculate statistics for fallback
    $totalExpiring = count($expiringProducts);
    $criticalExpiring = count(array_filter($expiringProducts, function($product) use ($criticalDays) {
        return isset($product['days_until_expiry']) && $product['days_until_expiry'] <= $criticalDays && $product['days_until_expiry'] > 0;
    }));
    $expiredProducts = count(array_filter($expiringProducts, function($product) {
        return isset($product['days_until_expiry']) && $product['days_until_expiry'] < 0;
    }));
    $totalUnitsExpiring = array_sum(array_column($expiringProducts, 'quantity'));
    
    $expiryStats = [
        'total_expiring' => $totalExpiring,
        'critical_expiring' => $criticalExpiring,
        'expired_products' => $expiredProducts,
        'total_units_expiring' => $totalUnitsExpiring
    ];
}

// Categorize products by expiry status (using expiry_status from view or calculated)
$criticalProducts = array_filter($expiringProducts, function($product) {
    return isset($product['expiry_status']) && strtolower($product['expiry_status']) == 'critical';
});

$expiredProductsList = array_filter($expiringProducts, function($product) {
    return isset($product['expiry_status']) && strtolower($product['expiry_status']) == 'expired';
});

$warningProducts = array_filter($expiringProducts, function($product) {
    return isset($product['expiry_status']) && strtolower($product['expiry_status']) == 'warning';
});

// Calculate additional statistics
$totalProductsAtRisk = count($expiringProducts);
$totalQuantityAtRisk = array_sum(array_column($expiringProducts, 'quantity'));
$daysArray = array_filter(array_column($expiringProducts, 'days_until_expiry'), function($d) {
    return $d !== null;
});
$averageDaysToExpiry = count($daysArray) > 0 ? 
    array_sum($daysArray) / count($daysArray) : 0;
?>

<!-- Expiry Reports -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gray-50">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Expiry Report</h1>
                    <p class="text-sm text-gray-500 mt-1">Monitor and manage product expiration dates</p>
                </div>
            </div>
            
            <!-- Report Period Info -->
            <!-- Expiry Monitoring header removed as requested -->
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <!-- Total Products at Risk -->
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 border border-gray-100 group">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-orange-50 rounded-lg group-hover:bg-orange-100 transition-colors">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-medium">Products at Risk</p>
                        <p class="text-lg font-bold text-gray-900"><?= number_format($totalProductsAtRisk) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">Within <?= $daysAhead ?> days</p>
                    </div>
                </div>
            </div>
            
            <!-- Total Units at Risk -->
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 border border-gray-100 group">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-amber-50 rounded-lg group-hover:bg-amber-100 transition-colors">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-medium">Units at Risk</p>
                        <p class="text-lg font-bold text-gray-900"><?= number_format($expiryStats['total_units_expiring'] ?? 0, 0) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">Total items</p>
                    </div>
                </div>
            </div>
            
            <!-- Expired Products -->
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 border border-gray-100 group">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-gray-50 rounded-lg group-hover:bg-gray-100 transition-colors">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-medium">Already Expired</p>
                        <p class="text-lg font-bold text-gray-900"><?= number_format($expiryStats['expired_products']) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">Immediate Action</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-8">
            <!-- Total Quantity at Risk -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">Quantity at Risk</p>
                        <h3 class="text-2xl sm:text-3xl font-bold text-gray-900"><?= number_format($totalQuantityAtRisk) ?></h3>
                        <p class="text-gray-500 text-xs mt-1">Total items expiring</p>
                    </div>
                    <div class="w-14 h-14 bg-orange-50 rounded-xl flex items-center justify-center">
                        <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- Average Days to Expiry -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">Avg. Days to Expiry</p>
                        <h3 class="text-2xl sm:text-3xl font-bold text-gray-900"><?= number_format($averageDaysToExpiry, 1) ?></h3>
                        <p class="text-gray-500 text-xs mt-1">Average timeline</p>
                    </div>
                    <div class="w-14 h-14 bg-blue-50 rounded-xl flex items-center justify-center">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100 mb-6">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Filters</h3>
                    <button onclick="clearFilters()" class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Clear Filters
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
                    <!-- Days Ahead Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Days Ahead</label>
                        <input type="number" id="filterDaysAhead" value="<?= htmlspecialchars($daysAhead) ?>" min="1" max="365"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            onchange="applyFilters()">
                    </div>
                    <!-- Search Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Search</label>
                        <div class="relative">
                            <input type="text" id="searchExpiry" placeholder="Search products, batch, supplier..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                onkeyup="filterExpiryTable()">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiry Report Table -->
        <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 border border-gray-200">
            <style>
                .expiry-table-scroll {
                    max-height: 640px; /* Approximately 10 table rows */
                    overflow-y: auto;
                }
                .expiry-table-scroll::-webkit-scrollbar {
                    width: 8px;
                }
                .expiry-table-scroll::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 10px;
                }
                .expiry-table-scroll::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 10px;
                }
                .expiry-table-scroll::-webkit-scrollbar-thumb:hover {
                    background: #555;
                }
            </style>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4 pb-4 border-b border-gray-200">
                <div>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900">Detailed Expiry Report</h3>
                    <p class="text-sm text-gray-500 mt-1">Complete list of products expiring within the selected period</p>
                </div>
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button onclick="printFullReport()" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all text-sm font-semibold shadow-sm hover:shadow-md">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Print Full Report
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <div class="expiry-table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                            <tr>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Product Details</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Supplier</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Batch No</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Storage</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Pharmacy</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Total Units</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Expiry Date</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Days Left</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody id="expiryTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if (count($expiringProducts) > 0): ?>
                            <?php foreach ($expiringProducts as $product): 
                                $rawDaysLeft = $product['days_until_expiry'] ?? null;
                                $daysLeft = is_numeric($rawDaysLeft) ? (int)$rawDaysLeft : null;
                                $productThreshold = isset($product['expire_threshold']) && (int)$product['expire_threshold'] > 0
                                    ? (int)$product['expire_threshold']
                                    : $criticalDays;

                                // Check if product is out of stock
                                $quantity = isset($product['quantity']) ? (float)$product['quantity'] : 0;
                                $hasStock = $quantity > 0;

                                $statusKey = 'upcoming';
                                $statusText = 'Upcoming';
                                $statusClass = 'bg-blue-50 text-blue-700 border border-blue-200';

                                // If out of stock, set status to Inactive
                                if (!$hasStock) {
                                    $statusKey = 'inactive';
                                    $statusText = 'Inactive';
                                    $statusClass = 'bg-gray-100 text-gray-500 border border-gray-300';
                                } elseif ($daysLeft === null) {
                                    $statusKey = 'unknown';
                                    $statusText = 'Unknown';
                                    $statusClass = 'bg-gray-50 text-gray-600 border border-gray-200';
                                } elseif ($daysLeft < 0) {
                                    $statusKey = 'expired';
                                    $statusText = 'Expired';
                                    $statusClass = 'bg-gray-100 text-gray-800 border border-gray-300';
                                } elseif ($daysLeft <= $productThreshold) {
                                    $statusKey = 'expiring';
                                    $statusText = 'Expiring Soon';
                                    $statusClass = 'bg-orange-100 text-orange-800 border border-orange-300';
                                }

                                $rowPayload = $product;
                                $rowPayload['computed_status'] = $statusText;
                                $rowPayload['computed_status_key'] = $statusKey;
                                $rowPayload['days_until_expiry'] = $daysLeft;
                                $encodedProduct = htmlspecialchars(json_encode($rowPayload), ENT_QUOTES, 'UTF-8');
                            ?>
                                <tr class="hover:bg-red-50 transition-colors duration-150 cursor-pointer" onclick="openViewExpiryDetailsModal(<?= $encodedProduct ?>)" data-quantity="<?= htmlspecialchars($product['quantity'] ?? 0) ?>" data-days-left="<?= htmlspecialchars($daysLeft ?? '') ?>" data-status="<?= htmlspecialchars($statusKey) ?>" data-expire-threshold="<?= htmlspecialchars($productThreshold) ?>" data-storage-units="<?= htmlspecialchars($product['storage_units'] ?? 0) ?>" data-pharmacy-units="<?= htmlspecialchars($product['pharmacy_units'] ?? 0) ?>">
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0 border border-red-200">
                                                <svg class="w-3 h-3 sm:w-4 sm:h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs sm:text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($product['brand_name']) ?></div>
                                                <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($product['generic_name']) ?></div>
                                                <?php if (!empty($product['strength'])): ?>
                                                    <div class="text-xs text-gray-400 truncate"><?= htmlspecialchars($product['strength']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($product['barcode'])): ?>
                                                    <div class="text-xs text-gray-400 truncate font-mono"><?= htmlspecialchars($product['barcode']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="text-xs sm:text-sm text-gray-900 font-medium"><?= htmlspecialchars($product['supplier_name'] ?? 'N/A') ?></span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                            <?= htmlspecialchars($product['batch_no']) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs sm:text-sm font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                                <?= number_format($product['storage_packages'] ?? 0) ?>
                                            </span>
                                            <span class="text-xs text-gray-500">packages</span>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs sm:text-sm font-semibold bg-green-50 text-green-700 border border-green-200">
                                            <?= number_format($product['pharmacy_units'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs sm:text-sm font-bold text-gray-900 bg-gray-100 border border-gray-300">
                                            <?= number_format($product['quantity'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                        <div class="text-xs sm:text-sm font-medium text-gray-900"><?= $product['expiry_date'] ? date('M d, Y', strtotime($product['expiry_date'])) : 'N/A' ?></div>
                                        <div class="text-xs text-gray-500 mt-0.5"><?= $product['expiry_date'] ? date('D', strtotime($product['expiry_date'])) : '' ?></div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                        <?php if (!$hasStock): ?>
                                            <div class="flex flex-col items-center">
                                                <span class="text-xs sm:text-sm font-bold text-gray-500">N/A</span>
                                                <span class="text-xs text-gray-400">Out of Stock</span>
                                            </div>
                                        <?php elseif ($daysLeft < 0): ?>
                                            <div class="flex flex-col items-center">
                                                <span class="text-xs sm:text-sm font-bold text-gray-700">Expired</span>
                                                <span class="text-xs text-gray-500"><?= abs($daysLeft) ?> days ago</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center">
                                                <span class="font-bold text-sm sm:text-lg <?= $daysLeft <= $criticalDays ? 'text-red-600' : 'text-orange-600' ?>">
                                                    <?= $daysLeft ?>
                                                </span>
                                                <span class="text-xs text-gray-500">days</span>
                                                <?php if (isset($product['expire_threshold']) && $product['expire_threshold'] > 0): ?>
                                                    <span class="text-xs text-gray-400 mt-0.5">Threshold: <?= $product['expire_threshold'] ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-bold <?= $statusClass ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <p class="font-bold text-lg text-gray-900 mb-1">No expiring products</p>
                                        <p class="text-sm text-gray-500">No products are expiring within the selected period.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($expiringProducts)): ?>
            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-500 gap-2 bg-gray-50 p-4 rounded-xl border border-gray-100">
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Showing <?= count($expiringProducts) ?> products
                </div>
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Generated on <?= date('F j, Y \a\t g:i A') ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- View Expiry Details Modal -->
<div id="viewExpiryDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-auto overflow-hidden">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="bg-white bg-opacity-20 p-1.5 rounded-lg mr-3">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-white">Expiry Details</h3>
            <p class="text-xs text-indigo-100 mt-0.5">Complete product expiration information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="closeViewExpiryDetailsModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 max-h-[65vh] overflow-y-auto">
      <div id="printableExpiryDetailsContent">
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
                  <p class="text-sm font-semibold text-gray-900" id="viewBrandName">-</p>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Barcode</p>
                  <p class="text-xs font-mono text-gray-900" id="viewBarcode">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Supplier</p>
                  <p class="text-sm text-gray-900" id="viewSupplier">-</p>
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
          </div>
        </div>
        
        <!-- Stock Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Stock Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="bg-purple-50 p-3 rounded-lg border border-purple-100">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Storage Units</p>
                  <p class="text-sm font-semibold text-purple-700" id="viewStorageUnits">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-green-50 p-3 rounded-lg border border-green-100">
              <div class="flex items-center">
                <div class="bg-green-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Pharmacy Units</p>
                  <p class="text-sm font-semibold text-green-700" id="viewPharmacyUnits">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-orange-50 p-3 rounded-lg border border-orange-100">
              <div class="flex items-center">
                <div class="bg-orange-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Total Units</p>
                  <p class="text-sm font-semibold text-orange-700" id="viewTotalUnits">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Expiry Information Section -->
        <div>
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Expiry Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-red-50 p-3 rounded-lg border border-red-100">
              <div class="flex items-center">
                <div class="bg-red-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Expiry Date</p>
                  <p class="text-sm font-semibold text-red-700" id="viewExpiryDate">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-orange-50 p-3 rounded-lg border border-orange-100">
              <div class="flex items-center">
                <div class="bg-orange-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Days Left</p>
                  <p class="text-sm font-bold text-orange-700" id="viewDaysLeft">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-gray-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Status</p>
                  <p class="text-sm" id="viewStatus">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-gray-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Threshold</p>
                  <p class="text-sm text-gray-900" id="viewThreshold">-</p>
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
        <button onclick="closeViewExpiryDetailsModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
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
  #printableExpiryDetailsContent, #printableExpiryDetailsContent * {
    visibility: visible;
  }
  #printableExpiryDetailsContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}
</style>

<script>
const DEFAULT_EXPIRY_THRESHOLD = <?= (int)$criticalDays ?>;

function applyFilters() {
    const daysAhead = document.getElementById('filterDaysAhead').value;
    
    // Build URL with current parameters
    const url = new URL(window.location.href);
    
    if (daysAhead) {
        url.searchParams.set('days_ahead', daysAhead);
    } else {
        url.searchParams.delete('days_ahead');
    }
    
    // Remove date range params if they exist
    url.searchParams.delete('start_date');
    url.searchParams.delete('end_date');
    
    // Reload page with new filters
    window.location.href = url.toString();
}

function clearFilters() {
    // Reset to default (90 days ahead, no date range)
    const url = new URL(window.location.href);
    url.searchParams.delete('start_date');
    url.searchParams.delete('end_date');
    url.searchParams.set('days_ahead', '90');
    
    // Reload page with cleared filters
    window.location.href = url.toString();
}

function filterExpiryTable() {
    const searchInput = document.getElementById('searchExpiry').value.toLowerCase();
    const table = document.getElementById('expiryTableBody');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent.toLowerCase();
        if (text.includes(searchInput)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

function printFullReport() {
    // Get all visible rows (respecting filters)
    const tableBody = document.getElementById('expiryTableBody');
    if (!tableBody) {
        showErrorModal('Error', 'No expiry table found.');
        return;
    }
    
    // Collect all visible rows (not hidden by filters)
    const allRows = tableBody.querySelectorAll('tr');
    const visibleRows = [];
    allRows.forEach(row => {
        // Only include rows that are visible (not hidden by display:none)
        if (row.style.display !== 'none' && row.offsetParent !== null) {
            visibleRows.push(row.cloneNode(true));
        }
    });
    
    if (visibleRows.length === 0) {
        showErrorModal('No Data', 'No expiry data to print. Please check your filters.');
        return;
    }
    
    // Calculate totals from visible rows
    let totalProducts = 0;
    let totalUnits = 0;
    let expiringProducts = 0;
    let expiredProducts = 0;
    
    visibleRows.forEach(row => {
        totalProducts++;
        
        // Get quantity from data attribute (more reliable)
        const quantity = parseInt(row.getAttribute('data-quantity') || '0');
        totalUnits += quantity;
        
        // Get days left and status from data attributes
        const daysLeftAttr = row.getAttribute('data-days-left');
        const daysLeft = daysLeftAttr === '' ? null : parseInt(daysLeftAttr, 10);
        const statusKey = (row.getAttribute('data-status') || '').toLowerCase();
        const expireThreshold = parseInt(row.getAttribute('data-expire-threshold') || '30');
        
        if (statusKey === 'expired' || (daysLeft !== null && daysLeft < 0)) {
            expiredProducts++;
        } else if (statusKey === 'expiring' || (daysLeft !== null && daysLeft >= 0 && daysLeft <= expireThreshold)) {
            expiringProducts++;
        }
    });
    
    // Create print window
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Expiry Report - Selected Items</title>
            <meta charset="UTF-8">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body { 
                    font-family: 'Segoe UI', Arial, sans-serif; 
                    font-size: 11px;
                    margin: 0;
                    padding: 20px;
                    color: #000;
                    line-height: 1.5;
                    background: #fff;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 3px solid #dc2626;
                    padding-bottom: 15px;
                }
                .print-header h1 {
                    font-size: 28px;
                    margin: 0 0 8px 0;
                    color: #1e293b;
                    font-weight: 700;
                }
                .print-header p {
                    margin: 3px 0;
                    color: #64748b;
                    font-size: 11px;
                }
                .print-summary-cards {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 15px;
                    margin: 20px 0;
                    margin-bottom: 25px;
                }
                .summary-card {
                    border-radius: 8px;
                    padding: 18px;
                    text-align: center;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
                    border: 2px solid;
                }
                .summary-card:nth-child(1) {
                    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                    border-color: #f59e0b;
                }
                .summary-card:nth-child(2) {
                    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
                    border-color: #3b82f6;
                }
                .summary-card:nth-child(3) {
                    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
                    border-color: #ef4444;
                }
                .summary-card:nth-child(4) {
                    background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
                    border-color: #6b7280;
                }
                .summary-card .label {
                    font-size: 10px;
                    color: #374151;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 10px;
                }
                .summary-card .value {
                    font-size: 24px;
                    font-weight: 800;
                    color: #111827;
                    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
                }
                .print-info {
                    margin-bottom: 20px;
                    padding: 12px 15px;
                    background: #f1f5f9;
                    border-left: 4px solid #dc2626;
                    border-radius: 4px;
                }
                .print-info p {
                    margin: 4px 0;
                    font-size: 11px;
                    color: #334155;
                }
                .print-info strong {
                    color: #1e293b;
                    font-weight: 600;
                }
                .print-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .print-table thead {
                    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
                }
                .print-table th {
                    color: #fff;
                    font-weight: 700;
                    padding: 12px 8px;
                    text-align: left;
                    border: 1px solid #991b1b;
                    font-size: 10px;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                .print-table tbody td {
                    padding: 10px 8px;
                    border: 1px solid #e2e8f0;
                    font-size: 10px;
                    color: #334155;
                }
                .print-table tbody tr {
                    border-bottom: 1px solid #e2e8f0;
                }
                .print-table tbody tr:nth-child(even) {
                    background: #f8fafc;
                }
                .print-table tbody tr:hover {
                    background: #fef2f2;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .print-footer {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 2px solid #cbd5e1;
                    text-align: center;
                    font-size: 10px;
                    color: #64748b;
                }
                @media print {
                    body { 
                        margin: 0;
                        padding: 15px;
                    }
                    .print-summary-cards {
                        page-break-inside: avoid;
                    }
                    .print-table {
                        page-break-inside: auto;
                    }
                    .print-table thead {
                        display: table-header-group;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>📋 Expiry Report</h1>
                ${document.getElementById('filterDaysAhead')?.value ? `<p><strong>Days Ahead:</strong> ${document.getElementById('filterDaysAhead').value} days</p>` : ''}
                ${document.getElementById('searchExpiry')?.value ? `<p><strong>Search:</strong> ${document.getElementById('searchExpiry').value}</p>` : ''}
            </div>
            
            <div class="print-summary-cards">
                <div class="summary-card">
                    <div class="label">Total Products</div>
                    <div class="value">${totalProducts}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Total Units</div>
                    <div class="value">${totalUnits.toLocaleString()}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Expiring Soon</div>
                    <div class="value">${expiringProducts}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Expired</div>
                    <div class="value">${expiredProducts}</div>
                </div>
            </div>
            
            <div class="print-info">
                ${document.getElementById('filterDaysAhead')?.value ? `<p><strong>Days Ahead Filter:</strong> ${document.getElementById('filterDaysAhead').value} days</p>` : ''}
                ${document.getElementById('searchExpiry')?.value ? `<p><strong>Search Filter:</strong> ${document.getElementById('searchExpiry').value}</p>` : ''}
            </div>
            
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width: 18%;">Product Details</th>
                        <th style="width: 10%;">Supplier</th>
                        <th style="width: 8%;">Batch No</th>
                        <th style="width: 10%;" class="text-center">Storage</th>
                        <th style="width: 10%;" class="text-center">Pharmacy</th>
                        <th style="width: 10%;" class="text-center">Total Units</th>
                        <th style="width: 12%;" class="text-center">Expiry Date</th>
                        <th style="width: 10%;" class="text-center">Days Left</th>
                        <th style="width: 12%;" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
    `);
    
    visibleRows.forEach(row => {
        // Get data from attributes for more reliable extraction
        const storageUnits = parseInt(row.getAttribute('data-storage-units') || '0');
        const pharmacyUnits = parseInt(row.getAttribute('data-pharmacy-units') || '0');
        
        // Extract data from cells
        const cells = row.querySelectorAll('td');
        
        // Build row with separated Storage and Pharmacy columns
        let rowHtml = '<tr>';
        
        // Product Details (index 0)
        const productCell = cells[0];
        const brandName = productCell.querySelector('.font-semibold')?.textContent?.trim() || '';
        const genericName = productCell.querySelector('.text-gray-500')?.textContent?.trim() || '';
        const strength = productCell.querySelector('.text-gray-400')?.textContent?.trim() || '';
        const productContent = brandName + (genericName ? ' / ' + genericName : '') + (strength ? ' (' + strength + ')' : '');
        rowHtml += `<td style="padding: 10px 8px;">${productContent}</td>`;
        
        // Supplier (index 1)
        rowHtml += `<td style="padding: 10px 8px;">${cells[1].textContent.trim()}</td>`;
        
        // Batch No (index 2)
        rowHtml += `<td style="padding: 10px 8px;">${cells[2].textContent.trim()}</td>`;
        
        // Storage (from data attribute)
        rowHtml += `<td class="text-center" style="padding: 10px 8px;">${storageUnits.toLocaleString()}</td>`;
        
        // Pharmacy (from data attribute)
        rowHtml += `<td class="text-center" style="padding: 10px 8px;">${pharmacyUnits.toLocaleString()}</td>`;
        
        // Total Units (index 3)
        const totalUnitsText = cells[3].textContent.replace(/,/g, '').trim();
        rowHtml += `<td class="text-center" style="padding: 10px 8px;">${totalUnitsText}</td>`;
        
        // Expiry Date (index 4)
        rowHtml += `<td class="text-center" style="padding: 10px 8px;">${cells[4].textContent.trim()}</td>`;
        
        // Days Left (index 5)
        rowHtml += `<td class="text-center" style="padding: 10px 8px;">${cells[5].textContent.trim()}</td>`;
        
        // Status (index 6)
        rowHtml += `<td class="text-center" style="padding: 10px 8px;">${cells[6].textContent.trim()}</td>`;
        
        rowHtml += '</tr>';
        printWindow.document.write(rowHtml);
    });
    
    printWindow.document.write(`
                </tbody>
            </table>
            
            <div class="print-footer">
                <p><strong>Generated by OCT Pharmacy</strong> | ${new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}


function printExpiryReport() {
    window.print();
}

// View Expiry Details Modal Functions
function openViewExpiryDetailsModal(product) {
    document.getElementById('viewBrandName').textContent = product.brand_name || '-';
    document.getElementById('viewGenericName').textContent = product.generic_name || '-';
    document.getElementById('viewStrength').textContent = product.strength || 'N/A';
    document.getElementById('viewBarcode').textContent = product.barcode || 'N/A';
    document.getElementById('viewSupplier').textContent = product.supplier_name || 'N/A';
    document.getElementById('viewBatchNo').textContent = product.batch_no || 'N/A';
    
    document.getElementById('viewStorageUnits').textContent = product.storage_units ? number_format(product.storage_units) : '0';
    document.getElementById('viewPharmacyUnits').textContent = product.pharmacy_units ? number_format(product.pharmacy_units) : '0';
    document.getElementById('viewTotalUnits').textContent = product.quantity ? number_format(product.quantity) : '0';
    
    const expiryDate = product.expiry_date ? new Date(product.expiry_date) : null;
    document.getElementById('viewExpiryDate').textContent = expiryDate ? 
        expiryDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
    
    const parsedDays = product.days_until_expiry;
    const daysLeft = (parsedDays === null || parsedDays === undefined || parsedDays === '') ? null : parseInt(parsedDays, 10);
    const threshold = (product.expire_threshold && parseInt(product.expire_threshold, 10) > 0)
        ? parseInt(product.expire_threshold, 10)
        : DEFAULT_EXPIRY_THRESHOLD;

    // Check if product is out of stock
    const quantity = parseFloat(product.quantity || 0);
    const hasStock = quantity > 0;
    
    let daysLeftHtml;
    if (!hasStock) {
        daysLeftHtml = '<span class="text-gray-500">N/A</span><br><span class="text-xs text-gray-400">Out of Stock</span>';
    } else if (daysLeft === null || Number.isNaN(daysLeft)) {
        daysLeftHtml = '<span class="text-gray-500">N/A</span>';
    } else if (daysLeft < 0) {
        daysLeftHtml = `<span class="text-red-600 font-semibold">Expired ${Math.abs(daysLeft)} days ago</span>`;
    } else {
        daysLeftHtml = `<span class="text-orange-600 font-semibold">${daysLeft} days</span>`;
    }
    document.getElementById('viewDaysLeft').innerHTML = daysLeftHtml;
    
    let statusLabel = product.computed_status || product.expiry_status || 'Unknown';
    let statusClass = 'bg-gray-100 text-gray-800';
    
    // If out of stock, set status to Inactive (priority check)
    if (!hasStock) {
        statusLabel = 'Inactive';
        statusClass = 'bg-gray-100 text-gray-500';
    } else if (daysLeft === null || Number.isNaN(daysLeft)) {
        statusLabel = 'Unknown';
        statusClass = 'bg-gray-100 text-gray-700';
    } else if (daysLeft < 0) {
        statusLabel = 'Expired';
        statusClass = 'bg-gray-100 text-gray-800';
    } else if (daysLeft <= threshold) {
        statusLabel = 'Expiring Soon';
        statusClass = 'bg-orange-100 text-orange-800';
    } else {
        statusLabel = 'Upcoming';
        statusClass = 'bg-blue-100 text-blue-800';
    }
    document.getElementById('viewStatus').innerHTML = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${statusClass}">${statusLabel}</span>`;
    
    document.getElementById('viewThreshold').textContent = threshold ? `${threshold} days` : 'N/A';
    
    document.getElementById('viewExpiryDetailsModal').classList.remove('hidden');
    document.getElementById('viewExpiryDetailsModal').classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeViewExpiryDetailsModal() {
    document.getElementById('viewExpiryDetailsModal').classList.add('hidden');
    document.getElementById('viewExpiryDetailsModal').classList.remove('flex');
    document.body.style.overflow = 'auto';
}

function printExpiryDetails() {
    window.print();
}

function number_format(number) {
    return parseInt(number).toLocaleString('en-US');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('viewExpiryDetailsModal');
    if (event.target === modal) {
        closeViewExpiryDetailsModal();
    }
}
</script>
<script src="../../Assets/Javascript/errorAndsuccessHander.js"></script>
<script>
// Initialize modal handlers (no URL parameters for reports)
initModalHandlers({}, {});
</script>

</body>
</html>
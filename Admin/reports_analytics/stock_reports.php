<?php include '../../includes/adminHeader.php' ?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>
<?php
// Database connection
require_once '../../dbConnection/connection.php';

// Get filters
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

// Get stock data
$stockData = [];

// Check for view_stock_report
$viewCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'view_stock_report'");
$hasStockReportView = $viewCheck && mysqli_num_rows($viewCheck) > 0;
if ($viewCheck) {
    mysqli_free_result($viewCheck);
}

if ($hasStockReportView) {
    // Use view_stock_report - already aggregated at product level
    // Join with products, package_types, and unit_types to get package_name and unit_name
    // Try to match products by extracting product_id from product_details or matching by name
    $stockQuery = "SELECT 
        vsr.product_details,
        vsr.supplier as supplier_name,
        vsr.total_stock,
        vsr.storage_stock as storage_units,
        vsr.pharmacy_stock as pharmacy_units,
        vsr.threshold as threshold_limit,
        vsr.package_unit,
        (SELECT pt.package_name 
         FROM products p 
         LEFT JOIN package_types pt ON p.package_type_id = pt.package_type_id
         WHERE vsr.product_details LIKE CONCAT('%', p.brand_name, '%') 
            OR vsr.product_details LIKE CONCAT('%', p.generic_name, '%')
         LIMIT 1) as package_name,
        (SELECT ut.unit_name 
         FROM products p 
         LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
         WHERE vsr.product_details LIKE CONCAT('%', p.brand_name, '%') 
            OR vsr.product_details LIKE CONCAT('%', p.generic_name, '%')
         LIMIT 1) as unit_name,
        vsr.expiry_info,
        vsr.expiry_info as latest_expiry,
        vsr.status as stock_status,
        NULL as brand_name,
        NULL as generic_name,
        NULL as strength,
        NULL as barcode,
        NULL as package_type,
        NULL as base_unit,
        NULL as last_storage_update,
        NULL as last_pharmacy_update
    FROM view_stock_report vsr
    WHERE 1=1";
    
    // Note: Category filter may not be available in the new view structure
    // If category filtering is needed, it would need to be added to the view or joined with products table
    
    $stockQuery .= " ORDER BY vsr.total_stock ASC, vsr.expiry_info ASC";
} else {
    // Fallback - use basic products query
    $stockQuery = "SELECT 
        p.product_id,
        p.brand_name,
        p.generic_name,
        p.strength,
        p.barcode,
        NULL as supplier_name,
        NULL as package_type,
        NULL as base_unit,
        0 as storage_units,
        0 as pharmacy_units,
        0 as total_stock,
        0 as threshold_limit,
        NULL as latest_expiry,
        'Out of Stock' as stock_status,
        NULL as last_storage_update,
        NULL as last_pharmacy_update
    FROM products p
    LEFT JOIN Categories c ON p.category_id = c.category_id
    WHERE 1=1";
    
    if ($categoryFilter) {
        $stockQuery .= " AND c.category_name = '" . mysqli_real_escape_string($conn, $categoryFilter) . "'";
    }
    
    $stockQuery .= " ORDER BY p.brand_name ASC";
}

$result = @mysqli_query($conn, $stockQuery);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $stockData[] = $row;
    }
    if ($result) {
        mysqli_free_result($result);
    }
} else {
    // Debug: Log query error if any
    if ($result === false) {
        error_log("Stock Report Query Error: " . mysqli_error($conn));
    }
}

function stockReportRowIsExpired(array $product): bool {
    $expiryInfo = strtolower(trim($product['expiry_info'] ?? ''));
    if ($expiryInfo !== '' && $expiryInfo !== 'n/a' && strpos($expiryInfo, 'expired') !== false) {
        return true;
    }

    $latestExpiry = $product['latest_expiry'] ?? null;
    if ($latestExpiry && strtolower($latestExpiry) !== 'n/a') {
        $timestamp = strtotime($latestExpiry);
        if ($timestamp !== false && $timestamp < strtotime('today')) {
            return true;
        }
    }

    return false;
}

// Get default product threshold (same logic as Dashboard)
$productThresholdQuery = "SELECT product_threshold, COUNT(*) as count FROM products WHERE product_threshold > 0 GROUP BY product_threshold ORDER BY count DESC LIMIT 1";
$productThresholdRes = @mysqli_query($conn, $productThresholdQuery);
$defaultProductThreshold = 10; // Default
if ($productThresholdRes && mysqli_num_rows($productThresholdRes) > 0) {
    $productRow = mysqli_fetch_assoc($productThresholdRes);
    $defaultProductThreshold = (int)($productRow['product_threshold'] ?? 10);
}
if ($productThresholdRes) {
    mysqli_free_result($productThresholdRes);
}

// Calculate low stock count using EXACT same query logic as Dashboard
// Dashboard uses buildInventoryTotalsSubquery first, then falls back to view_inventory_status_alerts
function buildInventoryTotalsSubqueryForReport($defaultThreshold) {
    $defaultThreshold = (int)$defaultThreshold;
    return "
        SELECT 
            p.product_id,
            p.brand_name,
            p.generic_name,
            COALESCE(s.supplier_name, 'N/A') AS supplier_name,
            COALESCE(ph.pharmacy_units, 0) AS pharmacy_units,
            COALESCE(st.storage_units, 0) AS storage_units,
            (COALESCE(ph.pharmacy_units, 0) + COALESCE(st.storage_units, 0)) AS total_units,
            CASE 
                WHEN COALESCE(p.product_threshold, 0) > 0 THEN p.product_threshold
                ELSE $defaultThreshold
            END AS threshold
        FROM products p
        LEFT JOIN (
            SELECT 
                d.product_id, 
                SUM(COALESCE(pi.quantity_units_balance, 0)) AS pharmacy_units
            FROM pharmacy_inventory pi
            INNER JOIN deliveries d ON pi.delivery_id = d.delivery_id
            WHERE d.status = 'Active'
            GROUP BY d.product_id
        ) ph ON p.product_id = ph.product_id
        LEFT JOIN (
            SELECT 
                d.product_id,
                SUM(
                    COALESCE(si.quantity_packages_balance, 0) *
                    CASE 
                        WHEN d.quantity_packages IS NOT NULL AND d.quantity_packages > 0 THEN d.quantity_units / d.quantity_packages
                        ELSE d.quantity_units
                    END
                ) AS storage_units
            FROM storage_inventory si
            INNER JOIN deliveries d ON si.delivery_id = d.delivery_id
            WHERE d.status = 'Active'
            GROUP BY d.product_id
        ) st ON p.product_id = st.product_id
        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    ";
}

// Use EXACT same logic as Dashboard - buildInventoryTotalsSubquery first
$inventoryTotalsSubquery = buildInventoryTotalsSubqueryForReport($defaultProductThreshold);
$lowStockQuery = "
    SELECT COUNT(*) AS low_count
    FROM (
        $inventoryTotalsSubquery
    ) totals
    WHERE totals.threshold > 0
      AND totals.total_units > 0
      AND totals.total_units <= totals.threshold
";
$lowStockResult = @mysqli_query($conn, $lowStockQuery);
$lowStockCount = 0;
if ($lowStockResult) {
    $row = mysqli_fetch_assoc($lowStockResult);
    $lowStockCount = (int)($row['low_count'] ?? 0);
    mysqli_free_result($lowStockResult);
}

// Fallback to view_inventory_status_alerts if first query returns 0 (same as Dashboard)
if ($lowStockCount === 0) {
    $hasInventoryStatusView = false;
    $viewCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'view_inventory_status_alerts'");
    if ($viewCheck && mysqli_num_rows($viewCheck) > 0) {
        $hasInventoryStatusView = true;
    }
    if ($viewCheck) {
        mysqli_free_result($viewCheck);
    }
    
    if ($hasInventoryStatusView) {
        // Use view_inventory_status_alerts - same as Dashboard fallback
        $lowStockQuery = "SELECT COUNT(DISTINCT CONCAT(v.product_id, '-', v.batch_no)) AS low_count
                          FROM view_inventory_status_alerts v
                          LEFT JOIN products p ON v.product_id = p.product_id
                          WHERE COALESCE(p.product_threshold, 0) > 0
                            AND v.total_stock > 0
                            AND v.total_stock <= p.product_threshold";
        $lowStockResult = @mysqli_query($conn, $lowStockQuery);
        if ($lowStockResult) {
            $row = mysqli_fetch_assoc($lowStockResult);
            $lowStockCount = (int)($row['low_count'] ?? 0);
            mysqli_free_result($lowStockResult);
        }
    }
}

// Calculate summary statistics
$totalProducts = count($stockData);
$lowStockCount = 0;
$outOfStockCount = 0;

foreach ($stockData as $product) {
    $totalStock = (float)($product['total_stock'] ?? 0);
    $statusValue = strtolower($product['stock_status'] ?? $product['status'] ?? '');
    $isExpired = stockReportRowIsExpired($product);
    $hasStock = $totalStock > 0;

    if (!$hasStock || $isExpired) {
        $outOfStockCount++;
        continue;
    }

    if ($statusValue === 'low stock') {
        $lowStockCount++;
    }
}

// Get categories for filter dropdown
$categoriesQuery = "SELECT DISTINCT c.category_name 
                   FROM Categories c 
                   INNER JOIN products p ON c.category_id = p.category_id 
                   WHERE c.category_name IS NOT NULL 
                   ORDER BY c.category_name";

$categoriesResult = mysqli_query($conn, $categoriesQuery);
$categories = [];
if ($categoriesResult) {
    while ($row = mysqli_fetch_assoc($categoriesResult)) {
        $categories[] = $row['category_name'];
    }
}
?>

<!-- Stock Reports -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gray-50">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Stock Report</h1>
                    <p class="text-sm text-gray-500 mt-1">Product inventory stock report</p>
                </div>
            </div>
            
            <!-- Report Period Info -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between">
                    <div>
                        <h2 class="text-base sm:text-lg font-semibold text-gray-900">
                            Inventory Status Overview
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">
                            <?= $categoryFilter ? 'Filtered by: ' . htmlspecialchars($categoryFilter) : 'All product categories' ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-1">
                            Generated: <?= date('M j, Y g:i A') ?>
                        </p>
                    </div>
                    <div class="mt-2 sm:mt-0 flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= $outOfStockCount > 0 ? 'bg-red-100 text-red-800' : ($lowStockCount > 0 ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800') ?>">
                            <?= $outOfStockCount > 0 ? 'Action Required' : ($lowStockCount > 0 ? 'Low Stock Alert' : 'All Stocked') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
            <!-- Total Products -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-blue-100 text-sm font-semibold">Total Products</p>
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                        <svg class="w-5 h-5 text-black" fill="none" stroke="black" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl sm:text-4xl font-bold mb-1"><?= number_format($totalProducts) ?></h3>
                <p class="text-blue-100 text-xs">In inventory</p>
            </div>

            <!-- Total Units -->
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-green-100 text-sm font-semibold">Total Units</p>
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                        <svg class="w-5 h-5 text-black" fill="none" stroke="black" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl sm:text-4xl font-bold mb-1"><?= number_format(array_sum(array_column($stockData, 'total_stock'))) ?></h3>
                <p class="text-green-100 text-xs">All products</p>
            </div>

            <!-- Low Stock -->
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-orange-100 text-sm font-semibold">Low Stock</p>
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                        <svg class="w-5 h-5 text-black" fill="none" stroke="black" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl sm:text-4xl font-bold mb-1"><?= number_format($lowStockCount) ?></h3>
                <p class="text-orange-100 text-xs">Need reordering</p>
            </div>

            <!-- Out of Stock -->
            <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-red-100 text-sm font-semibold">Out of Stock</p>
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                        <svg class="w-5 h-5 text-black" fill="none" stroke="black" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl sm:text-4xl font-bold mb-1"><?= number_format($outOfStockCount) ?></h3>
                <p class="text-red-100 text-xs">Urgent attention</p>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100 mb-6">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Filters</h3>
                    <button onclick="clearFilters()" class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="black" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Clear Filters
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
                    <!-- Category Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Category</label>
                        <select id="filterCategory" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                            onchange="applyFilters()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Search Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Search</label>
                        <div class="relative">
                            <input type="text" id="searchStock" placeholder="Search products, batch, supplier..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onkeyup="filterStockTable()">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Report Table -->
        <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 border border-gray-200">
            <style>
                .stock-table-scroll {
                    max-height: 640px; /* Approximately 10 table rows */
                    overflow-y: auto;
                }
                .stock-table-scroll::-webkit-scrollbar {
                    width: 8px;
                }
                .stock-table-scroll::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 10px;
                }
                .stock-table-scroll::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 10px;
                }
                .stock-table-scroll::-webkit-scrollbar-thumb:hover {
                    background: #555;
                }
            </style>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4 pb-4 border-b border-gray-200">
                <div>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900">Product Stock Report</h3>
                    <p class="text-sm text-gray-500 mt-1">Complete inventory overview with all product details</p>
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
                    <div class="stock-table-scroll">
                    <table class="w-full min-w-[1200px] divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                            <tr>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Product Details</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Supplier</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Total Stock</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Storage</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Pharmacy</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Threshold</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Package/Unit</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Expiry Info</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody id="stockTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if (empty($stockData)): ?>
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                        </div>
                                        <p class="font-bold text-lg text-gray-900 mb-1">No Stock Data</p>
                                        <p class="text-sm text-gray-500">No products found matching the selected criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: 
                            foreach ($stockData as $product): 
                                $totalStock = (float)($product['total_stock'] ?? 0);
                                $statusValueRaw = $product['stock_status'] ?? $product['status'] ?? '';
                                $statusLower = strtolower($statusValueRaw);
                                $isExpired = stockReportRowIsExpired($product);
                                $hasStock = $totalStock > 0;
                                $isAvailable = $hasStock && !$isExpired;
                                $isLowStockAvailable = $isAvailable && $statusLower === 'low stock';

                                if ($isExpired) {
                                    $statusText = 'Expired';
                                    $statusClass = 'bg-red-100 text-red-800';
                                } elseif (!$hasStock) {
                                    $statusText = 'Out of Stock';
                                    $statusClass = 'bg-red-100 text-red-800';
                                } elseif ($isLowStockAvailable) {
                                    $statusText = 'Low Stock';
                                    $statusClass = 'bg-orange-100 text-orange-800';
                                } elseif (strpos($statusLower, 'expiring') !== false) {
                                    $statusText = 'Expiring Soon';
                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                } else {
                                    $statusText = 'In Stock';
                                    $statusClass = 'bg-green-100 text-green-800';
                                }
                                
                                // Format expiry info - use expiry_info from view if available, otherwise calculate from latest_expiry
                                $expiryInfo = 'N/A';
                                if (!empty($product['expiry_info']) && $product['expiry_info'] !== 'N/A') {
                                    $expiryInfo = $product['expiry_info'];
                                } elseif (!empty($product['latest_expiry'])) {
                                    $expiryDate = date('M d, Y', strtotime($product['latest_expiry']));
                                    $daysLeft = floor((strtotime($product['latest_expiry']) - time()) / (60 * 60 * 24));
                                    if ($daysLeft < 0) {
                                        $expiryInfo = $expiryDate . '<br><span class="text-red-600 text-xs font-medium">Expired</span>';
                                    } elseif ($daysLeft <= 30) {
                                        $expiryInfo = $expiryDate . '<br><span class="text-orange-600 text-xs font-medium">' . $daysLeft . ' days left</span>';
                                    } else {
                                        $expiryInfo = $expiryDate . '<br><span class="text-gray-500 text-xs">' . $daysLeft . ' days left</span>';
                                    }
                                }
                            ?>
                            <tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer" onclick="openViewStockDetailsModal(<?= htmlspecialchars(json_encode($product)) ?>)" data-total-stock="<?= htmlspecialchars($product['total_stock'] ?? 0) ?>" data-status="<?= htmlspecialchars(strtolower($statusText)) ?>" data-available="<?= $isAvailable ? '1' : '0' ?>" data-low-stock="<?= $isLowStockAvailable ? '1' : '0' ?>">
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 sm:w-8 sm:h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0 border border-blue-200">
                                            <svg class="w-3 h-3 sm:w-4 sm:h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <?php if (!empty($product['product_details'])): ?>
                                                <div class="text-xs sm:text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($product['product_details']) ?></div>
                                            <?php else: ?>
                                                <div class="text-xs sm:text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($product['brand_name'] ?? 'N/A') ?></div>
                                                <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($product['generic_name'] ?? '') ?></div>
                                                <?php if (!empty($product['strength'])): ?>
                                                    <div class="text-xs text-gray-400 truncate"><?= htmlspecialchars($product['strength']) ?></div>
                                                <?php endif; ?>
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
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                    <div class="flex flex-col items-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs sm:text-sm font-bold text-gray-900 bg-gray-100 border border-gray-300">
                                            <?= number_format($product['total_stock'] ?? 0) ?>
                                        </span>
                                        <span class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($product['package_unit'] ?? 'units') ?></span>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs sm:text-sm font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                        <?= number_format($product['storage_units'] ?? 0) ?>
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($product['package_unit'] ?? 'units') ?></div>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs sm:text-sm font-semibold bg-green-50 text-green-700 border border-green-200">
                                        <?= number_format($product['pharmacy_units'] ?? 0) ?>
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($product['package_unit'] ?? 'units') ?></div>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                    <?php 
                                    $threshold = $product['threshold_limit'] ?? null;
                                    if ($threshold !== null && $threshold !== '' && $threshold > 0): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs sm:text-sm font-semibold bg-orange-50 text-orange-700 border border-orange-200">
                                            <?= number_format($threshold) ?>
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($product['package_unit'] ?? 'units') ?></div>
                                    <?php else: ?>
                                        <span class="text-xs sm:text-sm text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center text-xs sm:text-sm text-gray-900">
                                    <div class="flex flex-col items-center gap-1">
                                        <?php if (!empty($product['package_name'])): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                                                <?= htmlspecialchars($product['package_name']) ?>
                                            </span>
                                        <?php elseif (!empty($product['package_unit'])): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                                                <?= htmlspecialchars($product['package_unit']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($product['unit_name'])): ?>
                                            <span class="text-xs text-gray-600"><?= htmlspecialchars($product['unit_name']) ?></span>
                                        <?php elseif (!empty($product['package_unit'])): ?>
                                            <span class="text-xs text-gray-400"><?= htmlspecialchars($product['package_unit']) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center text-xs sm:text-sm text-gray-600">
                                    <?php if (!empty($product['expiry_info']) && $product['expiry_info'] !== 'N/A'): ?>
                                        <div><?= htmlspecialchars($product['expiry_info']) ?></div>
                                    <?php else: ?>
                                        <?= $expiryInfo ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-bold <?= $statusClass ?> border <?= strtolower($statusText) == 'out of stock' ? 'border-red-300' : (strpos(strtolower($statusText), 'low') !== false ? 'border-orange-300' : 'border-green-300') ?>">
                                        <?= htmlspecialchars($statusText) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($stockData)): ?>
            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-500 gap-2 bg-gray-50 p-4 rounded-xl border border-gray-100">
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Showing <?= count($stockData) ?> products
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

<!-- View Stock Details Modal -->
<div id="viewStockDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4" style="background: rgba(0,0,0,0.6);">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-auto overflow-hidden">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="bg-white bg-opacity-20 p-1.5 rounded-lg mr-3">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-white">Product Details</h3>
            <p class="text-xs text-indigo-100 mt-0.5">Complete product information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="closeViewStockDetailsModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 max-h-[65vh] overflow-y-auto">
      <div id="printableStockDetailsContent">
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
                  <p class="text-xs text-gray-500">Product ID</p>
                  <p class="text-sm font-medium text-gray-900" id="viewProductId">-</p>
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
          </div>
        </div>
        
        <!-- Stock Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Stock Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
              <div class="flex items-center">
                <div class="bg-blue-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Package Type</p>
                  <p class="text-sm font-medium text-gray-900" id="viewPackageType">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
              <div class="flex items-center">
                <div class="bg-blue-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Base Unit</p>
                  <p class="text-sm font-medium text-gray-900" id="viewBaseUnit">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
              <div class="flex items-center">
                <div class="bg-blue-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Total Stock</p>
                  <p class="text-sm font-semibold text-gray-900" id="viewTotalStock">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Stock Distribution Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Stock Distribution</h4>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Threshold Limit</p>
                  <p class="text-sm font-semibold text-orange-700" id="viewThreshold">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Additional Information Section -->
        <div>
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Additional Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Latest Expiry</p>
                  <p class="text-sm text-gray-900" id="viewExpiry">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Last Storage Update</p>
                  <p class="text-sm text-gray-900" id="viewLastStorageUpdate">-</p>
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
                  <p class="text-xs text-gray-500">Last Pharmacy Update</p>
                  <p class="text-sm text-gray-900" id="viewLastPharmacyUpdate">-</p>
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
        <button onclick="closeViewStockDetailsModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function closeViewStockDetailsModal() {
  document.getElementById('viewStockDetailsModal').classList.add('hidden');
}

function printStockDetails() {
  // Print functionality would go here
  window.print();
}
</script>



<!-- Print Styles -->
<style>
@media print {
  body * {
    visibility: hidden;
  }
  #printableStockDetailsContent, #printableStockDetailsContent * {
    visibility: visible;
  }
  #printableStockDetailsContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  #printableStockDetailsContent table {
    border-collapse: collapse;
    width: 100%;
  }
  #printableStockDetailsContent td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
  }
  #printableStockDetailsContent td:first-child {
    background-color: #f3f4f6;
    font-weight: bold;
    width: 30%;
  }
}
</style>

<script>
function applyFilters() {
    const category = document.getElementById('filterCategory').value;
    
    // Build URL with current parameters
    const url = new URL(window.location.href);
    
    // Remove date range params if they exist
    url.searchParams.delete('start_date');
    url.searchParams.delete('end_date');
    
    if (category) {
        url.searchParams.set('category', category);
    } else {
        url.searchParams.delete('category');
    }
    
    // Reload page with new filters
    window.location.href = url.toString();
}

function clearFilters() {
    // Reset all filters
    const url = new URL(window.location.href);
    url.searchParams.delete('start_date');
    url.searchParams.delete('end_date');
    url.searchParams.delete('category');
    
    // Reload page with cleared filters
    window.location.href = url.toString();
}

function filterStockTable() {
    const searchInput = document.getElementById('searchStock').value.toLowerCase();
    const table = document.getElementById('stockTableBody');
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
    const tableBody = document.getElementById('stockTableBody');
    if (!tableBody) {
        showErrorModal('Error', 'No stock table found.');
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
        showErrorModal('No Data', 'No stock data to print. Please check your filters.');
        return;
    }
    
    // Calculate totals from visible rows
    let totalProducts = 0;
    let totalUnits = 0;
    let lowStock = 0;
    let outOfStock = 0;
    
    visibleRows.forEach(row => {
        totalProducts++;
        
        const totalStock = parseInt(row.getAttribute('data-total-stock') || '0');
        const isAvailable = row.getAttribute('data-available') === '1';
        const isLowStock = row.getAttribute('data-low-stock') === '1';
        
        if (isAvailable) {
            totalUnits += totalStock;
        }
        
        if (!isAvailable) {
            outOfStock++;
        } else if (isLowStock) {
            lowStock++;
        }
    });
    
    // Create print window
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Stock Report - Selected Items</title>
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
                    border-bottom: 3px solid #2563eb;
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
                    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    padding: 15px;
                    text-align: center;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .summary-card .label {
                    font-size: 10px;
                    color: #475569;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                }
                .summary-card .value {
                    font-size: 20px;
                    font-weight: 700;
                    color: #1e293b;
                }
                .print-info {
                    margin-bottom: 20px;
                    padding: 12px 15px;
                    background: #f1f5f9;
                    border-left: 4px solid #2563eb;
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
                    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
                }
                .print-table th {
                    color: #fff;
                    font-weight: 700;
                    padding: 12px 8px;
                    text-align: left;
                    border: 1px solid #1e3a8a;
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
                    background: #f1f5f9;
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
                <h1>📦 Stock Report</h1>
                ${document.getElementById('filterCategory')?.value ? `<p><strong>Category:</strong> ${document.getElementById('filterCategory').value}</p>` : ''}
                ${document.getElementById('searchStock')?.value ? `<p><strong>Search:</strong> ${document.getElementById('searchStock').value}</p>` : ''}
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
                    <div class="label">Low Stock</div>
                    <div class="value">${lowStock}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Out of Stock</div>
                    <div class="value">${outOfStock}</div>
                </div>
            </div>
            
            <div class="print-info">
                ${document.getElementById('filterCategory')?.value ? `<p><strong>Category Filter:</strong> ${document.getElementById('filterCategory').value}</p>` : ''}
                ${document.getElementById('searchStock')?.value ? `<p><strong>Search Filter:</strong> ${document.getElementById('searchStock').value}</p>` : ''}
            </div>
            
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Product Details</th>
                        <th style="width: 15%;">Supplier</th>
                        <th style="width: 10%;" class="text-center">Total Stock</th>
                        <th style="width: 10%;" class="text-center">Storage</th>
                        <th style="width: 10%;" class="text-center">Pharmacy</th>
                        <th style="width: 10%;" class="text-center">Threshold</th>
                        <th style="width: 10%;" class="text-center">Package/Unit</th>
                        <th style="width: 10%;" class="text-center">Expiry Info</th>
                        <th style="width: 10%;" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
    `);
    
    visibleRows.forEach(row => {
        // Clean up the row HTML for printing
        const cells = row.querySelectorAll('td');
        let rowHtml = '<tr>';
        
        cells.forEach((cell, index) => {
            const text = cell.textContent.trim();
            // Column indices: Product Details=0, Supplier=1, Total Stock=2, Storage=3, Pharmacy=4, Threshold=5, Package/Unit=6, Expiry Info=7, Status=8
            const className = index >= 2 && index <= 8 ? 'text-center' : '';
            
            // Clean up product name
            let cellContent = text.replace(/\s+/g, ' ');
            
            if (index === 0 && cell.querySelector('.font-semibold')) {
                // Product column - keep brand name and generic name
                const brandName = cell.querySelector('.font-semibold')?.textContent?.trim() || '';
                const genericName = cell.querySelector('.text-gray-500')?.textContent?.trim() || '';
                cellContent = brandName + (genericName ? ' / ' + genericName : '');
            }
            
            rowHtml += `<td class="${className}" style="padding: 10px 8px;">${cellContent}</td>`;
        });
        
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


function printStockReport() {
    window.print();
}

// View Stock Details Modal Functions
function openViewStockDetailsModal(product) {
    try {
        // Ensure product is an object
        if (typeof product === 'string') {
            product = JSON.parse(product);
        }
        
        // Populate modal with product data
        const viewProductId = document.getElementById('viewProductId');
        const viewBrandName = document.getElementById('viewBrandName');
        const viewGenericName = document.getElementById('viewGenericName');
        const viewStrength = document.getElementById('viewStrength');
        const viewBarcode = document.getElementById('viewBarcode');
        const viewSupplier = document.getElementById('viewSupplier');
        const viewPackageType = document.getElementById('viewPackageType');
        const viewBaseUnit = document.getElementById('viewBaseUnit');
        const viewTotalStock = document.getElementById('viewTotalStock');
        const viewStorageUnits = document.getElementById('viewStorageUnits');
        const viewPharmacyUnits = document.getElementById('viewPharmacyUnits');
        const viewThreshold = document.getElementById('viewThreshold');
        
        if (viewProductId) viewProductId.textContent = product.product_id || '-';
        // Handle product_details (combined field) or separate fields
        if (product.product_details) {
            // If product_details exists, use it for brand name, and try to parse if needed
            if (viewBrandName) viewBrandName.textContent = product.product_details;
            if (viewGenericName) viewGenericName.textContent = product.generic_name || '-';
        } else {
            if (viewBrandName) viewBrandName.textContent = product.brand_name || '-';
            if (viewGenericName) viewGenericName.textContent = product.generic_name || '-';
        }
        if (viewStrength) viewStrength.textContent = product.strength || 'N/A';
        if (viewBarcode) viewBarcode.textContent = product.barcode || 'N/A';
        if (viewSupplier) viewSupplier.textContent = product.supplier_name || product.supplier || 'N/A';
        if (viewPackageType) viewPackageType.textContent = product.package_type || product.package_unit || 'N/A';
        if (viewBaseUnit) viewBaseUnit.textContent = product.base_unit || product.package_unit || 'N/A';
        
        // Format numbers
        if (viewTotalStock) viewTotalStock.textContent = product.total_stock ? number_format(product.total_stock) : '0';
        if (viewStorageUnits) viewStorageUnits.textContent = product.storage_units ? number_format(product.storage_units) : '0';
        if (viewPharmacyUnits) viewPharmacyUnits.textContent = product.pharmacy_units ? number_format(product.pharmacy_units) : '0';
        if (viewThreshold) viewThreshold.textContent = product.threshold_limit ? number_format(product.threshold_limit) : 'N/A';
    
        // Format expiry - use expiry_info if available, otherwise latest_expiry
        const viewExpiry = document.getElementById('viewExpiry');
        let expiryHtml = 'N/A';
        if (product.expiry_info && product.expiry_info !== 'N/A') {
            expiryHtml = product.expiry_info;
        } else if (product.latest_expiry) {
            try {
                const expiryDate = new Date(product.latest_expiry);
                if (!isNaN(expiryDate.getTime())) {
                    const formattedDate = expiryDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    const daysLeft = Math.floor((expiryDate - new Date()) / (1000 * 60 * 60 * 24));
                    let expiryColor = 'text-gray-600';
                    let expiryText = '';
                    
                    if (daysLeft < 0) {
                        expiryColor = 'text-red-600 font-semibold';
                        expiryText = ' (Expired)';
                    } else if (daysLeft <= 30) {
                        expiryColor = 'text-orange-600 font-semibold';
                        expiryText = ` (${daysLeft} days left)`;
                    } else {
                        expiryColor = 'text-green-600';
                        expiryText = ` (${daysLeft} days left)`;
                    }
                    expiryHtml = `${formattedDate}<span class="${expiryColor}">${expiryText}</span>`;
                }
            } catch (e) {
                console.error('Error formatting expiry date:', e);
            }
        }
        if (viewExpiry) viewExpiry.innerHTML = expiryHtml;
        
        // Format status
        const viewStatus = document.getElementById('viewStatus');
        const statusText = product.stock_status || 'Unknown';
        let statusClass = 'bg-gray-100 text-gray-800';
        if (statusText.toLowerCase().includes('out of stock')) {
            statusClass = 'bg-red-100 text-red-800';
        } else if (statusText.toLowerCase().includes('low stock')) {
            statusClass = 'bg-orange-100 text-orange-800';
        } else if (statusText.toLowerCase().includes('expiring')) {
            statusClass = 'bg-yellow-100 text-yellow-800';
        } else if (statusText.toLowerCase().includes('in stock')) {
            statusClass = 'bg-green-100 text-green-800';
        }
        if (viewStatus) viewStatus.innerHTML = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${statusClass}">${statusText}</span>`;
        
        // Format dates
        const viewLastStorageUpdate = document.getElementById('viewLastStorageUpdate');
        const viewLastPharmacyUpdate = document.getElementById('viewLastPharmacyUpdate');
        
        if (viewLastStorageUpdate) {
            if (product.last_storage_update) {
                try {
                    const date = new Date(product.last_storage_update);
                    if (!isNaN(date.getTime())) {
                        viewLastStorageUpdate.textContent = date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    } else {
                        viewLastStorageUpdate.textContent = 'N/A';
                    }
                } catch (e) {
                    viewLastStorageUpdate.textContent = 'N/A';
                }
            } else {
                viewLastStorageUpdate.textContent = 'N/A';
            }
        }
        
        if (viewLastPharmacyUpdate) {
            if (product.last_pharmacy_update) {
                try {
                    const date = new Date(product.last_pharmacy_update);
                    if (!isNaN(date.getTime())) {
                        viewLastPharmacyUpdate.textContent = date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    } else {
                        viewLastPharmacyUpdate.textContent = 'N/A';
                    }
                } catch (e) {
                    viewLastPharmacyUpdate.textContent = 'N/A';
                }
            } else {
                viewLastPharmacyUpdate.textContent = 'N/A';
            }
        }
        
        // Show modal
        const modal = document.getElementById('viewStockDetailsModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
    } catch (error) {
        console.error('Error opening stock details modal:', error);
        showErrorModal('Error', 'An error occurred while opening the product details. Please try again.');
    }
}

function closeViewStockDetailsModal() {
    const modal = document.getElementById('viewStockDetailsModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }
}

function printStockDetails() {
    try {
        const printContent = document.getElementById('printableStockDetailsContent');
        if (!printContent) {
            showErrorModal('Error', 'Print content not found.');
            return;
        }
        
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            showErrorModal('Pop-up Blocked', 'Please allow pop-ups to print this document.');
            return;
        }
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Stock Details Report</title>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    td { padding: 8px; border: 1px solid #ddd; }
                    td:first-child { background-color: #f3f4f6; font-weight: bold; width: 30%; }
                </style>
            </head>
            <body>${printContent.innerHTML}</body>
            </html>
        `);
        printWindow.document.close();
        
        // Wait for content to load before printing
        setTimeout(() => {
            printWindow.print();
        }, 250);
    } catch (error) {
        console.error('Error printing stock details:', error);
        showErrorModal('Error', 'An error occurred while printing. Please try again.');
    }
}

function number_format(number) {
    if (number === null || number === undefined || isNaN(number)) {
        return '0';
    }
    return parseInt(number).toLocaleString('en-US');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('viewStockDetailsModal');
    if (event.target === modal) {
        closeViewStockDetailsModal();
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
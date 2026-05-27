<?php 
// Include database connection
require_once '../../dbConnection/connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isAdmin = false;
if (!empty($_SESSION['user']['role'])) { $isAdmin = strtolower($_SESSION['user']['role']) === 'admin'; }
elseif (!empty($_SESSION['role'])) { $isAdmin = strtolower($_SESSION['role']) === 'admin'; }

// Fetch all products from database with inventory statistics
// Note: Pricing is now in deliveries table, not drug_batches
$query = "SELECT 
            p.*, 
            c.category_name, 
            ut.unit_name AS base_unit, 
            s.supplier_name,
            pt.package_name AS package_type_name,
            p.is_active,
            COALESCE(p.product_threshold, 0) AS product_threshold,
            COALESCE(p.expire_threshold, 0) AS expire_threshold,
            COALESCE(inv_stats.total_storage_packages, 0) AS total_storage_packages,
            COALESCE(inv_stats.total_pharmacy_units, 0) AS total_pharmacy_units,
            COALESCE(inv_stats.total_units_available, 0) AS total_inventory_units,
            inv_stats.latest_expiry_date AS nearest_expiry,
            COALESCE(price_stats.latest_unit_price, 0) AS latest_unit_price,
            COALESCE(price_stats.latest_package_price, 0) AS latest_package_price
          FROM products p 
          LEFT JOIN Categories c ON p.category_id = c.category_id 
          LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
          LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
          LEFT JOIN package_types pt ON p.package_type_id = pt.package_type_id
          LEFT JOIN (
            SELECT 
              vpfs.product_id,
              COALESCE(SUM(COALESCE(vpfs.storage_packages_balance, 0)), 0) AS total_storage_packages,
              COALESCE(SUM(COALESCE(vpfs.pharmacy_units_balance, 0)), 0) AS total_pharmacy_units,
              COALESCE(SUM(COALESCE(vpfs.pharmacy_units_balance, 0)), 0) AS total_units_available,
              MIN(vpfs.expiry_date) AS latest_expiry_date
            FROM view_products_for_sale vpfs
            WHERE (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE())
              AND (vpfs.availability_status IS NULL OR vpfs.availability_status = 'Available')
            GROUP BY vpfs.product_id
          ) inv_stats ON p.product_id = inv_stats.product_id
          LEFT JOIN (
            SELECT 
              d1.product_id,
              d1.selling_price_unit AS latest_unit_price,
              d1.selling_price_package AS latest_package_price
            FROM deliveries d1
            INNER JOIN (
              SELECT 
                product_id, 
                MAX(created_at) as max_created
              FROM deliveries
              WHERE status = 'Active' AND product_id IS NOT NULL
              GROUP BY product_id
            ) latest ON d1.product_id = latest.product_id 
              AND d1.created_at = latest.max_created
            INNER JOIN (
              SELECT 
                product_id,
                created_at,
                MAX(delivery_id) as max_delivery_id
              FROM deliveries
              WHERE status = 'Active' AND product_id IS NOT NULL
              GROUP BY product_id, created_at
            ) latest_delivery ON d1.product_id = latest_delivery.product_id
              AND d1.created_at = latest_delivery.created_at
              AND d1.delivery_id = latest_delivery.max_delivery_id
            WHERE d1.status = 'Active' AND d1.product_id IS NOT NULL
          ) price_stats ON p.product_id = price_stats.product_id
          ORDER BY p.created_at DESC";
$result = mysqli_query($conn, $query);
$products = [];

// Check if query was successful and has results
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
}

// Fetch categories for dropdown
$categoriesQuery = "SELECT category_id, category_name FROM Categories ORDER BY category_name ASC";
$categoriesResult = mysqli_query($conn, $categoriesQuery);
$categories = [];
if ($categoriesResult && mysqli_num_rows($categoriesResult) > 0) {
    while ($row = mysqli_fetch_assoc($categoriesResult)) {
        $categories[] = $row;
    }
}

// Fetch unit types for dropdown (used as base unit types)
// Show all unit types regardless of status
$unitsQuery = "SELECT unit_type_id, unit_name FROM unit_types ORDER BY unit_name ASC";
$unitsResult = @mysqli_query($conn, $unitsQuery);
$units = [];
if ($unitsResult && mysqli_num_rows($unitsResult) > 0) {
    while ($row = mysqli_fetch_assoc($unitsResult)) {
        $units[] = $row;
    }
    mysqli_free_result($unitsResult);
}

// Fetch suppliers for dropdown
$suppliersQuery = "SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC";
$suppliersResult = mysqli_query($conn, $suppliersQuery);
$suppliers = [];
if ($suppliersResult && mysqli_num_rows($suppliersResult) > 0) {
    while ($row = mysqli_fetch_assoc($suppliersResult)) {
        $suppliers[] = $row;
    }
}

// Fetch package types for dropdown
// Show all package types regardless of status
$packageTypesQuery = "SELECT package_type_id, package_name FROM package_types ORDER BY package_name ASC";
$packageTypesResult = @mysqli_query($conn, $packageTypesQuery);
$packageTypes = [];
if ($packageTypesResult && mysqli_num_rows($packageTypesResult) > 0) {
    while ($row = mysqli_fetch_assoc($packageTypesResult)) {
        $packageTypes[] = $row;
    }
    mysqli_free_result($packageTypesResult);
}

// Calculate statistics directly from inventory tables
// Simplified query since drug_batches table was removed
$inventoryStatsQuery = "SELECT 
    COALESCE(SUM(COALESCE(pi.quantity_units_balance, pi.quantity_units_in - pi.quantity_units_out, 0)), 0) as total_system_units
FROM pharmacy_inventory pi";
$inventoryStatsResult = mysqli_query($conn, $inventoryStatsQuery);
$inventoryStats = $inventoryStatsResult ? mysqli_fetch_assoc($inventoryStatsResult) : [];

// Calculate product statistics
$totalProducts = count($products);
$totalCategories = count($categories);
// $totalSuppliers (not displayed currently)
$totalSuppliers = count($suppliers);

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

function checkViewExists($conn, $viewName) {
    $result = @mysqli_query($conn, "SHOW TABLES LIKE '$viewName'");
    $exists = $result && mysqli_num_rows($result) > 0;
    if ($result) {
        mysqli_free_result($result);
    }
    return $exists;
}

// Calculate low stock count using EXACT same query logic as Dashboard
function buildInventoryTotalsSubqueryForProducts($defaultThreshold) {
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

function fetchLowStockProductsCount(mysqli $conn = null, int $defaultThreshold = 10): int {
    if (!$conn) {
        return 0;
    }

    if (checkViewExists($conn, 'view_stock_report')) {
        $viewQuery = "
            SELECT COUNT(*) AS low_count
            FROM view_stock_report
            WHERE LOWER(COALESCE(status, '')) = 'low stock'
              AND COALESCE(total_stock, 0) > 0
              AND (
                    expiry_info IS NULL
                 OR expiry_info = ''
                 OR LOWER(expiry_info) NOT LIKE '%expired%'
              )";
        $result = @mysqli_query($conn, $viewQuery);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            if ($row && isset($row['low_count'])) {
                return (int)$row['low_count'];
            }
        }
    }

    $inventoryTotalsSubquery = buildInventoryTotalsSubqueryForProducts($defaultThreshold);
    $nearestExpirySubquery = "
        SELECT product_id, MIN(expiry_date) AS nearest_expiry
        FROM deliveries
        WHERE status = 'Active'
        GROUP BY product_id
    ";
    $fallbackQuery = "
        SELECT COUNT(*) AS low_count
        FROM (
            $inventoryTotalsSubquery
        ) totals
        LEFT JOIN (
            $nearestExpirySubquery
        ) exp ON totals.product_id = exp.product_id
        WHERE totals.threshold > 0
          AND totals.total_units > 0
          AND totals.total_units <= totals.threshold
          AND (exp.nearest_expiry IS NULL OR exp.nearest_expiry >= CURDATE())
    ";
    $result = @mysqli_query($conn, $fallbackQuery);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row && isset($row['low_count']) && (int)$row['low_count'] > 0) {
            return (int)$row['low_count'];
        }
    }

    if (checkViewExists($conn, 'view_inventory_status_alerts')) {
        $viewAlertQuery = "
            SELECT COUNT(DISTINCT CONCAT(v.product_id, '-', v.batch_no)) AS low_count
            FROM view_inventory_status_alerts v
            LEFT JOIN products p ON v.product_id = p.product_id
            WHERE COALESCE(p.product_threshold, 0) > 0
              AND v.total_stock > 0
              AND v.total_stock <= p.product_threshold
              AND (v.expiry_date IS NULL OR v.expiry_date >= CURDATE())
        ";
        $alertResult = @mysqli_query($conn, $viewAlertQuery);
        if ($alertResult) {
            $row = mysqli_fetch_assoc($alertResult);
            mysqli_free_result($alertResult);
            if ($row && isset($row['low_count'])) {
                return (int)$row['low_count'];
            }
        }
    }

    return 0;
}

$productsLowStock = fetchLowStockProductsCount($conn ?? null, $defaultProductThreshold);

// Calculate additional product statistics
$productsWithBatches = 0;
$productsWithStock = 0;
$productsExpired = 0;
$todayTimestamp = strtotime('today');

foreach ($products as $product) {
    $totalUnits = isset($product['total_pharmacy_units']) ? (float)$product['total_pharmacy_units'] : ((float)($product['total_inventory_units'] ?? 0));
    $storagePackages = isset($product['total_storage_packages']) ? (float)$product['total_storage_packages'] : 0.0;
    $combinedInventory = $totalUnits + $storagePackages;

    if ($combinedInventory > 0) {
        $productsWithBatches++;
        $productsWithStock++;
    }
    
    $isProductExpired = false;
    if (!empty($product['nearest_expiry']) && $product['nearest_expiry'] !== '0000-00-00') {
        $expiryDate = strtotime($product['nearest_expiry']);
        if ($expiryDate && $expiryDate < $todayTimestamp) {
            $isProductExpired = true;
        }
    }
    
    $productThresholdValue = (int)($product['product_threshold'] ?? 0);
    if ($productThresholdValue <= 0) {
        $productThresholdValue = (int)$defaultProductThreshold;
    }
    
}

// Recompute expired products using live deliveries + balances:
// Count DISTINCT products that have at least one Active delivery already expired AND with remaining stock
$expiredProductsQuery = "
    SELECT COUNT(DISTINCT d.product_id) AS expired_count
    FROM deliveries d
    LEFT JOIN (
        SELECT delivery_id, SUM(quantity_packages_balance) AS storage_packages_balance
        FROM storage_inventory
        GROUP BY delivery_id
    ) si ON si.delivery_id = d.delivery_id
    LEFT JOIN (
        SELECT delivery_id, SUM(quantity_units_balance) AS pharmacy_units_balance
        FROM pharmacy_inventory
        GROUP BY delivery_id
    ) pi ON pi.delivery_id = d.delivery_id
    WHERE d.status = 'Active'
      AND d.expiry_date IS NOT NULL
      AND YEAR(d.expiry_date) > 0
      AND d.expiry_date < CURDATE()
      AND (
            COALESCE(si.storage_packages_balance, 0) > 0
         OR COALESCE(pi.pharmacy_units_balance, 0) > 0
      )
";
$expiredRes = @mysqli_query($conn, $expiredProductsQuery);
if ($expiredRes) {
    $row = mysqli_fetch_assoc($expiredRes);
    $productsExpired = (int)($row['expired_count'] ?? 0);
    mysqli_free_result($expiredRes);
} else {
    // Fallback: keep existing $productsExpired (from loop) if query fails
}
include '../../includes/adminHeader.php'; 
?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>

<!-- Product Management Content -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gradient-to-br from-gray-50 to-blue-50/30 min-h-screen">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header Section -->
        <div class="mb-6 sm:mb-8 lg:mb-10">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 sm:gap-6">
                <div class="space-y-2 sm:space-y-3">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="p-1.5 sm:p-2 lg:p-2 bg-white rounded-lg sm:rounded-xl shadow-sm border border-blue-100">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-3xl xl:text-4xl font-bold text-gray-900">Products Management</h1>
                            <p class="text-xs sm:text-sm lg:text-base text-gray-600 mt-0.5 sm:mt-1">Manage pharmacy products with real-time inventory tracking</p>
                        </div>
                    </div>
                        </div>
            </div>
        </div>

        <!-- Summary Cards - Modern Design -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4 lg:gap-5 xl:gap-6 mb-5 sm:mb-6 lg:mb-8">
            <!-- Total Products -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
      <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Total Products</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($totalProducts) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-blue-500 rounded-full mr-1.5 sm:mr-2"></div>
                            <p class="text-xs text-gray-500 truncate">Registered products</p>
                        </div>
        </div>
                    <div class="p-2 sm:p-3 bg-blue-50 rounded-lg sm:rounded-xl group-hover:bg-blue-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
          </svg>
        </div>
      </div>
    </div>
    
            <!-- In Stock -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
      <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">In Stock</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($productsWithStock) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-green-500 rounded-full mr-1.5 sm:mr-2"></div>
                            <p class="text-xs text-gray-500 truncate"><?= number_format($inventoryStats['total_system_units'] ?? 0) ?> total units</p>
                        </div>
        </div>
                    <div class="p-2 sm:p-3 bg-green-50 rounded-lg sm:rounded-xl group-hover:bg-green-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
          </svg>
        </div>
      </div>
    </div>
    
            <!-- Low Stock -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
      <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Low Stock</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($productsLowStock) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-amber-500 rounded-full mr-1.5 sm:mr-2"></div>
                            <p class="text-xs text-gray-500 truncate">Need reordering</p>
                        </div>
        </div>
                    <div class="p-2 sm:p-3 bg-amber-50 rounded-lg sm:rounded-xl group-hover:bg-amber-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
        </div>
      </div>
    </div>
    
            <!-- Expired -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
      <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Expired</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($productsExpired) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-red-500 rounded-full mr-1.5 sm:mr-2"></div>
                            <p class="text-xs text-gray-500 truncate">Require attention</p>
                        </div>
        </div>
                    <div class="p-2 sm:p-3 bg-red-50 rounded-lg sm:rounded-xl group-hover:bg-red-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
      </div>
    </div>
  </div>

        <!-- Filters Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 border border-white/50 mb-6 sm:mb-8">
            <div class="flex items-center gap-2 sm:gap-3 mb-4 sm:mb-6">
                <div class="p-1.5 sm:p-2 bg-blue-50 rounded-lg">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
              </svg>
            </div>
                    <div>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900">Filters</h3>
                    <p class="text-xs sm:text-sm text-gray-500">Search and filter products</p>
        </div>
      </div>

            <div class="flex flex-col lg:flex-row gap-3 sm:gap-4 lg:gap-5 w-full">
                <!-- Search Bar -->
                <div class="w-full lg:flex-1 lg:min-w-[280px] xl:min-w-[320px] 2xl:min-w-[400px]">
        <div class="relative">
          <input 
            type="text" 
            id="searchInput" 
            placeholder="Search by brand name, generic name, or category..." 
                            class="w-full pl-10 sm:pl-12 lg:pl-14 pr-4 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200 text-sm lg:text-base"
            onkeyup="filterProducts()"
          >
                        <svg class="absolute left-3 sm:left-4 lg:left-5 top-1/2 transform -translate-y-1/2 w-4 h-4 lg:w-5 lg:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
        </div>
      </div>
                
                <!-- Category Filter -->
                <div class="w-full lg:w-52 xl:w-56 2xl:w-64">
                    <select id="categoryFilter" onchange="filterProducts()" class="w-full px-3 sm:px-4 lg:px-5 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200 text-sm lg:text-base">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['category_name']) ?>"><?= htmlspecialchars($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Supplier Filter -->
                <div class="w-full lg:w-52 xl:w-56 2xl:w-64">
                    <select id="supplierFilter" onchange="filterProducts()" class="w-full px-3 sm:px-4 lg:px-5 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200 text-sm lg:text-base">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= htmlspecialchars($supplier['supplier_name']) ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div class="w-full lg:w-52 xl:w-56 2xl:w-64">
                    <select id="statusFilter" onchange="filterProducts()" class="w-full px-3 sm:px-4 lg:px-5 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200 text-sm lg:text-base">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <!-- Clear Filters Button -->
                <button onclick="clearProductFilters()" class="px-3 sm:px-4 lg:px-6 py-2.5 sm:py-3 lg:py-3.5 bg-gray-200 text-gray-700 rounded-lg sm:rounded-xl hover:bg-gray-300 transition-all duration-200 text-xs sm:text-sm lg:text-base font-semibold flex items-center justify-center gap-2 whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Clear Filters
                </button>
            </div>
        </div>

        <!-- Products Table Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 border border-white/50">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4 sm:mb-6 gap-3 sm:gap-4">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-1.5 sm:p-2 bg-blue-50 rounded-lg">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900">Products List</h3>
                        <p class="text-xs sm:text-sm text-gray-500">Manage pharmacy products with real-time inventory tracking</p>
                    </div>
                </div>
                
                <!-- Action Controls -->
                <button onclick="openAddProductModal()" class="px-3 sm:px-4 py-2 sm:py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg sm:rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-200 text-xs sm:text-sm font-semibold flex items-center justify-center gap-2 shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Product
                </button>
    </div>
    
            <!-- Products Table -->
            <div id="productsTableWrapper" class="overflow-x-auto rounded-xl border border-gray-200">
                <style>
                    /* Custom scrollbar for responsive tables */
                    #productsTableWrapper::-webkit-scrollbar {
                        height: 8px;
                    }
                    #productsTableWrapper::-webkit-scrollbar-track {
                        background: #f1f1f1;
                        border-radius: 10px;
                    }
                    #productsTableWrapper::-webkit-scrollbar-thumb {
                        background: #888;
                        border-radius: 10px;
                    }
                    #productsTableWrapper::-webkit-scrollbar-thumb:hover {
                        background: #555;
                    }
                    #productsTableWrapper {
                        scrollbar-width: thin;
                        scrollbar-color: #888 #f1f1f1;
                        max-height: 640px; /* Approximately 10 table rows */
                        overflow-y: auto;
                    }
                </style>
                <table class="w-full min-w-[1200px]">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                        <tr>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Product ID</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[200px]">Product Details</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Category</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Supplier</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Barcode</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Status</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Threshold</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap sticky right-0 bg-gradient-to-r from-gray-50 to-gray-100/80">Actions</th>
          </tr>
        </thead>
        <tbody id="productsTableBody" class="bg-white divide-y divide-gray-200">
          <?php if (count($products) > 0): ?>
            <?php foreach ($products as $product): 
              $totalUnits = isset($product['total_pharmacy_units']) ? (float)$product['total_pharmacy_units'] : ((float)($product['total_inventory_units'] ?? 0));
              $storagePackages = isset($product['total_storage_packages']) ? (float)$product['total_storage_packages'] : 0.0;
              $hasInventory = ($totalUnits > 0) || ($storagePackages > 0);
              $product_threshold = isset($product['product_threshold']) ? (int)$product['product_threshold'] : 0;
              $expire_threshold = isset($product['expire_threshold']) ? (int)$product['expire_threshold'] : 0;
              
              // Low stock check: ONLY based on inventory units <= product_threshold (independent of expiry)
              $isLowStock = false;
              if ($product_threshold > 0 && $totalUnits > 0 && $totalUnits <= $product_threshold) {
                  $isLowStock = true;
              }
              
              // Check expiration status (independent of low stock)
              $hasExpired = false;
              $isExpiringSoon = false;
              $daysUntilExpiry = null;
              if (!empty($product['nearest_expiry']) && $product['nearest_expiry'] !== '0000-00-00') {
                  $expiryDate = strtotime($product['nearest_expiry']);
                  $today = strtotime('today');
                  $daysUntilExpiry = floor(($expiryDate - $today) / (60 * 60 * 24));
                  
                  // Check if expired (priority: always show expired status)
                  if ($expiryDate < $today) {
                      $hasExpired = true;
                  } 
                  // Check if expiring soon (within expire_threshold days, but not expired yet)
                  elseif ($expire_threshold > 0 && $daysUntilExpiry <= $expire_threshold && $daysUntilExpiry >= 0) {
                      $isExpiringSoon = true;
                  }
              }
            ?>
              <?php
                // Determine status for filtering (Active/Inactive only)
                $statusFilter = $hasInventory ? 'active' : 'inactive';
              ?>
              <tr class="hover:bg-gray-50/50 transition-colors duration-150 cursor-pointer" onclick="openViewProductModal(<?= htmlspecialchars(json_encode($product)) ?>)" data-product-id="<?php echo $product['product_id']; ?>" data-category="<?= htmlspecialchars($product['category_name'] ?? 'N/A') ?>" data-supplier="<?= htmlspecialchars($product['supplier_name'] ?? 'N/A') ?>" data-status="<?= $statusFilter ?>">
                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                    #<?php echo $product['product_id']; ?>
                  </span>
                </td>
                <td class="px-3 sm:px-4 py-2 sm:py-3 min-w-[200px]">
                  <div class="flex items-center">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-xs mr-2 flex-shrink-0">
                      <?php echo strtoupper(substr($product['brand_name'], 0, 1)); ?>
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs sm:text-sm font-semibold text-gray-900 truncate max-w-[150px]" title="<?php echo htmlspecialchars($product['brand_name']); ?>"><?php echo htmlspecialchars($product['brand_name']); ?></p>
                      <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($product['generic_name']); ?></p>
                      <?php if (!empty($product['strength'])): ?>
                        <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($product['strength']); ?></p>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    <?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?>
                  </span>
                </td>
                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-xs sm:text-sm text-gray-600">
                  <?php echo htmlspecialchars($product['supplier_name'] ?? 'N/A'); ?>
                </td>
                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                  <?php if (!empty($product['barcode'])): ?>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 font-mono">
                      <?php echo htmlspecialchars($product['barcode']); ?>
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-400">
                      N/A
                    </span>
                  <?php endif; ?>
                </td>
                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                  <?php if ($hasInventory): ?>
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-emerald-100 text-emerald-700">
                      <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                      </svg>
                      Active
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-gray-100 text-gray-500">
                      <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-12.728 12.728m12.728 0L5.636 5.636"></path>
                      </svg>
                      Inactive
                    </span>
                  <?php endif; ?>
                </td>
                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                  <?php if ($product_threshold > 0 || $expire_threshold > 0): ?>
                    <div class="flex flex-col gap-1">
                      <?php if ($product_threshold > 0): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                          Low: ≤<?php echo number_format($product_threshold); ?>
                        </span>
                      <?php endif; ?>
                      <?php if ($expire_threshold > 0): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800">
                          Exp: ≤<?php echo number_format($expire_threshold); ?>d
                        </span>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600">
                      Not set
                    </span>
                  <?php endif; ?>
                </td>
                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center sticky right-0 bg-white" onclick="event.stopPropagation()">
                  <div class="flex items-center justify-center gap-1 sm:gap-1.5">
                    <button onclick='openEditProductModal(<?php echo json_encode($product); ?>)' class="inline-flex items-center px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-all duration-200 text-xs font-semibold" title="Edit">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                  </button>
                  <?php if ($isAdmin): ?>
                    <button onclick="openDeleteProductModal(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['brand_name']); ?>')" class="inline-flex items-center px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-all duration-200 text-xs font-semibold" title="Delete">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                  </button>
                  <?php else: ?>
                      <button type="button" onclick="noDeletePermission();" class="inline-flex items-center px-2 py-1 bg-red-400 text-white rounded opacity-60 cursor-not-allowed text-xs font-semibold" title="Delete (No Permission)">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                      </svg>
                    </button>
                  <?php endif; ?>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                  </svg>
                                        </div>
                                        <p class="font-semibold text-lg text-gray-600 mb-1">No Products Found</p>
                                        <p class="text-sm">Get started by adding your first product</p>
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

<!-- Add Product Modal -->
<?php include '../../Modal/modalProducts.php'; ?>

<script src="../../Assets/Javascript/errorAndsuccessHander.js"></script>
<script src="../../Assets/Javascript/product.js?v=<?= time() ?>"></script>
<script>
// Safe fallbacks in case cached scripts delay exposure
window.clearProductFilters = window.clearProductFilters || function(){
    var si = document.getElementById('searchInput');
    var cf = document.getElementById('categoryFilter');
    var sf = document.getElementById('supplierFilter');
    var st = document.getElementById('statusFilter');
    if (si) si.value = '';
    if (cf) cf.value = '';
    if (sf) sf.value = '';
    if (st) st.value = '';
    if (typeof window.filterProducts === 'function') { window.filterProducts(); }
};
window.noDeletePermission = window.noDeletePermission || function(){
    if (typeof showErrorModal === 'function') {
        showErrorModal('Permission Denied', 'You do not have permission to delete items.');
    } else {
        alert('You do not have permission to delete items.');
    }
};
</script>
<script>
// Initialize modal handlers
const successMessages = {
    'created': 'Product created successfully!',
    'updated': 'Product updated successfully!',
    'deleted': 'Product deleted successfully!'
};

const errorMessages = {
    'missing_fields': 'Please fill in all required fields.',
    'duplicate_product': 'A product with this brand name and generic name already exists.',
    'duplicate_barcode': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const barcode = urlParams.get('barcode') || '';
        const product = urlParams.get('product') || '';
        if (product) {
            return "Barcode '" + barcode + "' is already assigned to product '" + product + "'. Please use a different barcode.";
        }
        return "Barcode '" + barcode + "' is already in use. Please use a different barcode.";
    },
    'invalid_category': 'Invalid category selected.',
    'invalid_unit': 'Invalid unit selected.',
    'invalid_supplier': 'Invalid supplier selected.',
    'invalid_package_type': 'Invalid package type selected.',
    'invalid_id': 'Invalid product selected.',
    'product_not_found': 'Product not found.',
    'forbidden': 'You do not have permission to perform this action.',
    'create_failed': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg') || '';
        return 'Failed to create product.' + (msg ? ' ' + msg : ' Please try again.');
    },
    'update_failed': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg') || '';
        return 'Failed to update product.' + (msg ? ' ' + msg : ' Please try again.');
    },
    'delete_failed': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg') || '';
        return msg || 'Failed to delete product. Please try again.';
    },
    'product_in_use': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const details = urlParams.get('details') || '';
        if (details) {
            // New format with details parameter
            const detailsText = decodeURIComponent(details);
            showErrorModal('Cannot Delete Product', 'Cannot delete this product because it is referenced by: ' + detailsText + '. Remove those links first, then try again.');
        } else {
            // Fallback to old format for backward compatibility
            const batches = urlParams.get('batches') || urlParams.get('deliveries') || '0';
            const barcodes = urlParams.get('barcodes') || '0';
            const stock = urlParams.get('stock') || '0';
            const parts = [];
            if (parseInt(batches) > 0) { parts.push(batches + ' delivery(ies)'); }
            if (parseInt(barcodes) > 0) { parts.push(barcodes + ' barcode(s)'); }
            if (parseInt(stock) > 0) { parts.push(stock + ' stock record(s)'); }
            showErrorModal('Error', 'Cannot delete this product because it is referenced by ' + parts.join(' and ') + '. Remove those links first, then try again.');
        }
    }
};

initModalHandlers(successMessages, errorMessages);

// Handle product_in_use error with details
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('error') === 'product_in_use') {
    const details = urlParams.get('details');
    if (details) {
        // New format with details parameter
        const detailsText = decodeURIComponent(details);
        showErrorModal('Cannot Delete Product', 'Cannot delete this product because it is referenced by: ' + detailsText + '. Remove those links first, then try again.');
    } else {
        // Fallback to old format for backward compatibility
        const batches = urlParams.get('batches') || urlParams.get('deliveries') || '0';
        const barcodes = urlParams.get('barcodes') || '0';
        const stock = urlParams.get('stock') || '0';
        const parts = [];
        if (parseInt(batches) > 0) { parts.push(batches + ' delivery(ies)'); }
        if (parseInt(barcodes) > 0) { parts.push(barcodes + ' barcode(s)'); }
        if (parseInt(stock) > 0) { parts.push(stock + ' stock record(s)'); }
        showErrorModal('Error', 'Cannot delete this product because it is referenced by ' + parts.join(' and ') + '. Remove those links first, then try again.');
    }
}

// Ensure function is available immediately after script loads
(function() {
  function verifyFunction() {
    if (typeof window.openViewProductModal === 'function') {
      if (typeof openViewProductModal === 'undefined') {
        openViewProductModal = window.openViewProductModal;
      }
      return true;
    }
    return false;
  }
  
  if (!verifyFunction()) {
    setTimeout(function() {
      verifyFunction();
    }, 100);
  }
})();
</script>
<script src="../../Assets/Javascript/barcode.js"></script>
</body>
</html>
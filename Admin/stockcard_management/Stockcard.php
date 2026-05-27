<?php 
// Include database connection
require_once '../../dbConnection/connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isAdmin = false;
$currentUsername = '';
if (!empty($_SESSION['user']['username'])) { $currentUsername = $_SESSION['user']['username']; }
elseif (!empty($_SESSION['username'])) { $currentUsername = $_SESSION['username']; }
$isPharmacyAccount = (strtolower($currentUsername) === 'pharmacy');
if (!empty($_SESSION['user']['role'])) { $isAdmin = strtolower($_SESSION['user']['role']) === 'admin'; }
elseif (!empty($_SESSION['role'])) { $isAdmin = strtolower($_SESSION['role']) === 'admin'; }
$isAdmin = $isPharmacyAccount || $isAdmin;

// Fetch only products that are available in POS (same filtering as getPOSData.php)
// Check for available views (priority: products_for_sale > fifofefo > ready_for_sale > available_products)
$has_products_for_sale_view = false;
$has_fifofefo_view = false;
$has_ready_for_sale_view = false;
$has_available_products_view = false;

$views_check = mysqli_query($conn, "SHOW FULL TABLES WHERE TABLE_TYPE = 'VIEW'");
if ($views_check) {
    while ($vr = mysqli_fetch_array($views_check)) {
        $viewName = strtolower($vr[0]);
        if ($viewName === 'view_products_for_sale') { 
            $has_products_for_sale_view = true; 
        }
        if ($viewName === 'view_fifofefo_available_products') { 
            $has_fifofefo_view = true; 
        }
        if ($viewName === 'view_ready_for_sale') { 
            $has_ready_for_sale_view = true; 
        }
        if ($viewName === 'view_available_products') { 
            $has_available_products_view = true; 
        }
    }
    mysqli_free_result($views_check);
}

if ($has_products_for_sale_view) {
    // Use view_products_for_sale (same as POS)
    $query = "SELECT 
                p.product_id,
                p.brand_name,
                p.generic_name,
                p.strength,
                ut.unit_name,
                pt.package_name,
                COALESCE(SUM(CASE WHEN (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE()) THEN COALESCE(vpfs.pharmacy_units_balance, 0) ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE()) THEN COALESCE(vpfs.storage_packages_balance, 0) ELSE 0 END), 0) AS total_inventory_units
              FROM view_products_for_sale vpfs
              LEFT JOIN products p ON vpfs.product_id = p.product_id
              LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
              LEFT JOIN package_types pt ON p.package_type_id = pt.package_type_id
              WHERE (vpfs.availability_status = 'Available' OR vpfs.availability_status IS NULL)
                AND (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE())
                AND (p.is_active = 1 OR p.is_active IS NULL)
              GROUP BY p.product_id, p.brand_name, p.generic_name, p.strength, ut.unit_name, pt.package_name
              HAVING (
                  SUM(CASE WHEN (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE()) THEN COALESCE(vpfs.storage_packages_balance, 0) ELSE 0 END) > 0
                  OR
                  SUM(CASE WHEN (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE()) THEN COALESCE(vpfs.pharmacy_units_balance, 0) ELSE 0 END) > 0
              )
              ORDER BY p.brand_name ASC";
} elseif ($has_fifofefo_view) {
    // Use view_fifofefo_available_products
    $query = "SELECT 
                p.product_id,
                p.brand_name,
                p.generic_name,
                p.strength,
                ut.unit_name,
                pt.package_name,
                COALESCE(SUM(vfap.total_units_available), 0) AS total_inventory_units
              FROM view_fifofefo_available_products vfap
              LEFT JOIN products p ON vfap.product_id = p.product_id
              LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
              LEFT JOIN package_types pt ON p.package_type_id = pt.package_type_id
              WHERE (vfap.storage_packages_balance > 0 OR vfap.pharmacy_units_balance > 0 OR vfap.total_units_available > 0)
                AND (vfap.expiry_date IS NULL OR vfap.expiry_date >= CURDATE())
                AND (p.is_active = 1 OR p.is_active IS NULL)
              GROUP BY p.product_id, p.brand_name, p.generic_name, p.strength, ut.unit_name, pt.package_name
              ORDER BY p.brand_name ASC";
} elseif ($has_ready_for_sale_view) {
    // Use view_ready_for_sale
    $query = "SELECT 
                p.product_id,
                p.brand_name,
                p.generic_name,
                p.strength,
                ut.unit_name,
                pt.package_name,
                COALESCE(SUM(vrs.total_units_available), 0) AS total_inventory_units
              FROM view_ready_for_sale vrs
              LEFT JOIN products p ON vrs.product_id = p.product_id
              LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
              LEFT JOIN package_types pt ON p.package_type_id = pt.package_type_id
              WHERE (vrs.storage_packages_available > 0 OR vrs.pharmacy_units_available > 0 OR vrs.total_units_available > 0)
                AND (vrs.expiry_date IS NULL OR vrs.expiry_date >= CURDATE())
                AND (p.is_active = 1 OR p.is_active IS NULL)
              GROUP BY p.product_id, p.brand_name, p.generic_name, p.strength, ut.unit_name, pt.package_name
              ORDER BY p.brand_name ASC";
} elseif ($has_available_products_view) {
    // Use view_available_products
    $query = "SELECT 
                p.product_id,
                p.brand_name,
                p.generic_name,
                p.strength,
                ut.unit_name,
                pt.package_name,
                COALESCE(SUM(vap.total_units_available), 0) AS total_inventory_units
              FROM view_available_products vap
              LEFT JOIN products p ON vap.product_id = p.product_id
              LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
              LEFT JOIN package_types pt ON p.package_type_id = pt.package_type_id
              WHERE (vap.expiry_date IS NULL OR vap.expiry_date >= CURDATE())
                AND (p.is_active = 1 OR p.is_active IS NULL)
              GROUP BY p.product_id, p.brand_name, p.generic_name, p.strength, ut.unit_name, pt.package_name
              HAVING (SUM(vap.available_packages) > 0 OR SUM(vap.available_units_storage) > 0 OR SUM(vap.available_units_pharmacy) > 0 OR SUM(vap.total_units_available) > 0)
              ORDER BY p.brand_name ASC";
} else {
    // Fallback: use products with active deliveries and inventory
    $query = "SELECT 
                p.product_id,
                p.brand_name,
                p.generic_name,
                p.strength,
                ut.unit_name,
                pt.package_name,
                COALESCE(SUM(COALESCE(pi.quantity_units_balance, 0)), 0) +
                COALESCE(SUM(COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0)), 0) AS total_inventory_units
              FROM products p 
              LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
              LEFT JOIN package_types pt ON p.package_type_id = pt.package_type_id
              LEFT JOIN deliveries d ON p.product_id = d.product_id AND d.status = 'Active'
              LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
              LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
              WHERE (p.is_active = 1 OR p.is_active IS NULL)
                AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
              GROUP BY p.product_id, p.brand_name, p.generic_name, p.strength, ut.unit_name, pt.package_name
              HAVING total_inventory_units > 0
              ORDER BY p.brand_name ASC";
}

$result = mysqli_query($conn, $query);
$products = [];

// Check if query was successful and has results
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $product_id = (int)$row['product_id'];
        
        // Calculate total in, total out from stock_movement_log
        $movementStatsQuery = "SELECT 
                                COALESCE(SUM(CASE 
                                    WHEN sml.movement_type IN ('delivery', 'transfer', 'return') AND sml.quantity_units > 0 THEN sml.quantity_units
                                    WHEN sml.movement_type = 'adjustment' AND sml.quantity_units > 0 THEN sml.quantity_units
                                    ELSE 0 
                                END), 0) AS total_in,
                                COALESCE(SUM(CASE 
                                    WHEN sml.movement_type = 'sale' THEN sml.quantity_units
                                    WHEN sml.movement_type = 'adjustment' AND sml.quantity_units < 0 THEN ABS(sml.quantity_units)
                                    ELSE 0 
                                END), 0) AS total_out
                              FROM stock_movement_log sml
                              WHERE sml.product_id = $product_id";
        $movementStatsResult = mysqli_query($conn, $movementStatsQuery);
        $movementStats = mysqli_fetch_assoc($movementStatsResult);
        if ($movementStatsResult) mysqli_free_result($movementStatsResult);
        
        // Get current stock from inventory and calculate total value
        // Includes: pharmacy_inventory units + storage_inventory (packages converted to units + direct units)
        // Note: d.quantity_units in deliveries table represents units_per_package
        $currentStockQuery = "SELECT 
                                COALESCE(SUM(pi.quantity_units_balance), 0) +
                                COALESCE(SUM(si.quantity_packages_balance * COALESCE(d.quantity_units, 0)), 0) +
                                COALESCE(SUM(si.quantity_units_balance), 0) AS current_stock,
                                COALESCE(SUM(pi.quantity_units_balance * COALESCE(d.cost_per_unit, 0)), 0) +
                                COALESCE(SUM(si.quantity_packages_balance * COALESCE(d.quantity_units, 0) * COALESCE(d.cost_per_unit, 0)), 0) +
                                COALESCE(SUM(si.quantity_units_balance * COALESCE(d.cost_per_unit, 0)), 0) AS total_value
                              FROM deliveries d
                              LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
                              LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
                              WHERE d.product_id = $product_id
                                AND d.status = 'Active'
                                AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())";
        $currentStockResult = mysqli_query($conn, $currentStockQuery);
        $currentStockData = mysqli_fetch_assoc($currentStockResult);
        if ($currentStockResult) mysqli_free_result($currentStockResult);
        
        $currentStock = (float)($currentStockData['current_stock'] ?? 0);
        $totalValue = (float)($currentStockData['total_value'] ?? 0);
        
        $row['total_in'] = (float)($movementStats['total_in'] ?? 0);
        $row['total_out'] = (float)($movementStats['total_out'] ?? 0);
        $row['current_stock'] = $currentStock;
        $row['total_value'] = $totalValue;
        
        $products[] = $row;
    }
}

include '../../includes/adminHeader.php'; 
?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>
<style>
    body {
        background-color: #f8fafc !important;
    }
</style>

<!-- Stock Card Management -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gradient-to-br from-gray-50 to-purple-50/30 min-h-screen">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white rounded-xl shadow-sm border border-purple-100">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Stock Cards</h1>
                            <p class="text-sm text-gray-600 mt-1">View product stock movements and inventory history</p>
                        </div>
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
                    <p class="text-xs sm:text-sm text-gray-500">Search and filter products</p>
                </div>
            </div>
            
            <div class="flex flex-col gap-3 sm:gap-4 lg:gap-5">
                <!-- Search Bar -->
                <div class="w-full">
                    <div class="relative">
                        <input 
                            type="text" 
                            id="searchInput" 
                            placeholder="Search by product name..." 
                            class="w-full pl-10 sm:pl-12 lg:pl-14 pr-4 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 shadow-sm transition-all duration-200 text-sm lg:text-base"
                            onkeyup="filterProducts()"
                        >
                        <svg class="absolute left-3 sm:left-4 lg:left-5 top-1/2 transform -translate-y-1/2 w-4 h-4 lg:w-5 lg:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Table Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 border border-white/50">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4 sm:mb-6 gap-3 sm:gap-4">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-1.5 sm:p-2 bg-purple-50 rounded-lg">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900">Products</h3>
                        <p class="text-xs sm:text-sm text-gray-500">Click on a product to view its stock card</p>
                    </div>
                </div>
            </div>
            
            <!-- Products Table -->
            <div id="productsTableWrapper" class="overflow-x-auto rounded-xl border border-gray-200">
                <style>
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
                </style>
                <table class="w-full min-w-[1200px]">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                        <tr>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Product ID</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Product Name</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Generic Name</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Strength</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Unit</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Total In</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Total Out</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Current Stock</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Total Value</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $product): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors cursor-pointer" onclick="viewStockCard(<?= $product['product_id'] ?>)">
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                                            #<?= $product['product_id'] ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm mr-3">
                                                <?= strtoupper(substr($product['brand_name'] ?? 'N/A', 0, 1)) ?>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($product['brand_name'] ?? 'N/A') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-sm text-gray-600"><?= htmlspecialchars($product['generic_name'] ?? 'N/A') ?></div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-sm text-gray-700"><?= htmlspecialchars($product['strength'] ?? 'N/A') ?></div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-sm text-gray-700"><?= htmlspecialchars($product['unit_name'] ?? 'N/A') ?></div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                                            <?= number_format((float)($product['total_in'] ?? 0)) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-red-100 text-red-800">
                                            <?= number_format((float)($product['total_out'] ?? 0)) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <?php 
                                        $currentStock = (float)($product['current_stock'] ?? 0);
                                        $stockClass = 'bg-purple-100 text-purple-800';
                                        if ($currentStock === 0) {
                                            $stockClass = 'bg-red-100 text-red-800';
                                        } elseif ($currentStock <= 10) {
                                            $stockClass = 'bg-orange-100 text-orange-800';
                                        }
                                        ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold <?= $stockClass ?>">
                                            <?= number_format($currentStock) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-green-100 text-green-800">
                                            ₱<?= number_format((float)($product['total_value'] ?? 0), 2) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                        </div>
                                        <p class="font-semibold text-lg text-gray-600 mb-1">No Products Found</p>
                                        <p class="text-sm">No products found in the system.</p>
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

<!-- Stock Card Modal -->
<div id="stockCardModal" class="fixed inset-0 bg-gradient-to-br from-gray-50/80 via-purple-50/70 to-white/90 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-7xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-white">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-600 rounded-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900" id="modalProductName">Stock Card</h2>
                    <p class="text-sm text-gray-600" id="modalProductDetails"></p>
                </div>
            </div>
            <button onclick="closeStockCardModal()" class="p-2 hover:bg-gray-200 rounded-lg transition-colors">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto p-6 bg-gradient-to-br from-gray-50 to-purple-50/30">
            <!-- Product Summary -->
            <div id="productSummary" class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                <!-- Summary cards will be populated by JavaScript -->
            </div>
            
            <!-- Date Range Filter -->
            <div class="mb-6 bg-gray-50 rounded-xl p-4 border border-gray-200">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <label class="text-sm font-semibold text-gray-700">Date Range:</label>
                    </div>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 flex-1">
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-600 whitespace-nowrap">From:</label>
                            <input type="date" id="stockCardDateFrom" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500" onchange="filterStockCardMovements()">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-600 whitespace-nowrap">To:</label>
                            <input type="date" id="stockCardDateTo" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500" onchange="filterStockCardMovements()">
                        </div>
                        <button onclick="clearStockCardDateFilter()" class="px-4 py-2 text-xs bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold whitespace-nowrap">
                            Clear Filter
                        </button>
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-500">
                    <p>Showing latest 10 movements by default. Use quick filters or date range to see all filtered results.</p>
                </div>
            </div>

            <!-- Stock Card Table -->
            <div class="overflow-x-auto" id="stockCardTableContainer" style="max-height: none; overflow-y: visible;">
                <style>
                    #stockCardTableContainer::-webkit-scrollbar {
                        width: 8px;
                    }
                    #stockCardTableContainer::-webkit-scrollbar-track {
                        background: #f1f1f1;
                        border-radius: 10px;
                    }
                    #stockCardTableContainer::-webkit-scrollbar-thumb {
                        background: #888;
                        border-radius: 10px;
                    }
                    #stockCardTableContainer::-webkit-scrollbar-thumb:hover {
                        background: #555;
                    }
                </style>
                <table class="w-full min-w-[1600px] border-collapse">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Particulars</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Username</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">In (Pkg)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">In (Unit)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">In Total (Pkg×Unit)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">In (Cost/Unit)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">In (Total)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Out (Pkg)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Out (Unit)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Out Total (Pkg×Unit)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Out (Cost/Unit)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Out (Total)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Balance (Pkg)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Balance (Unit)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Balance (Total)</th>
                        </tr>
                    </thead>
                    <tbody id="stockCardTableBody" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="16" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                    </div>
                                    <p class="font-semibold text-lg text-gray-600 mb-1">Loading stock card...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center bg-gray-50">
            <button onclick="printStockCard()" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Print Stock Card
            </button>
            <button onclick="closeStockCardModal()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Close
            </button>
        </div>
    </div>
</div>

<script src="../../Assets/Javascript/errorAndsuccessHander.js"></script>
<script src="../../Assets/Javascript/stock_Card.js?v=<?= time() ?>"></script>
</body>
</html>


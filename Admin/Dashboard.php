<?php
// Start session if not started
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Check if user is logged in and is admin
$isAdmin = false;
$currentUsername = '';
if (!empty($_SESSION['user']['username'])) { $currentUsername = $_SESSION['user']['username']; }
elseif (!empty($_SESSION['username'])) { $currentUsername = $_SESSION['username']; }

// Pharmacy account always has admin access
$isPharmacyAccount = (strtolower($currentUsername) === 'pharmacy');

if (!empty($_SESSION['user']['role'])) { 
    $isAdmin = strtolower($_SESSION['user']['role']) === 'admin'; 
} elseif (!empty($_SESSION['role'])) { 
    $isAdmin = strtolower($_SESSION['role']) === 'admin'; 
}

// Pharmacy account always has full access
$isAdmin = $isPharmacyAccount || $isAdmin;

if (!$isAdmin) {
    header('Location: ../auth/login.php?error=unauthorized');
    exit();
}

include '../includes/adminHeader.php'; 
?>
<!-- Include Modal Handler -->
<?php include '../Modal/modalAlertHandling.php'; ?>
<?php
require_once '../dbConnection/connection.php';

function fetchDashboardSummary(mysqli $conn = null): array {
    $defaults = [
        'total_items_sold' => 0,
        'total_expiring_alerts' => 0,
        'total_low_stock_alerts' => 0,
        'total_products_available' => 0,
        'sales_revenue_today' => 0.0,
        'sales_revenue_week' => 0.0,
        'sales_revenue_month' => 0.0,
        'sales_revenue_year' => 0.0,
        'sales_transactions_today' => 0,
        'sales_transactions_week' => 0,
        'sales_transactions_month' => 0,
        'sales_transactions_year' => 0,
        'avg_daily_sales_month' => 0.0,
        'top_selling_product_month' => 'N/A',
    ];

    if (!$conn) {
        return $defaults;
    }

    $query = "SELECT * FROM view_dashboard_summary LIMIT 1";
    $result = @mysqli_query($conn, $query);

    if ($result && ($data = mysqli_fetch_assoc($result))) {
        mysqli_free_result($result);
        return [
            'total_items_sold' => isset($data['total_items_sold']) ? (float)$data['total_items_sold'] : 0,
            'total_expiring_alerts' => isset($data['total_expiring_alerts']) ? (int)$data['total_expiring_alerts'] : 0,
            'total_low_stock_alerts' => isset($data['total_low_stock_alerts']) ? (int)$data['total_low_stock_alerts'] : 0,
            'total_products_available' => isset($data['total_products_available']) ? (int)$data['total_products_available'] : 0,
            'sales_revenue_today' => isset($data['sales_revenue_today']) ? (float)$data['sales_revenue_today'] : 0.0,
            'sales_revenue_week' => isset($data['sales_revenue_week']) ? (float)$data['sales_revenue_week'] : 0.0,
            'sales_revenue_month' => isset($data['sales_revenue_month']) ? (float)$data['sales_revenue_month'] : 0.0,
            'sales_revenue_year' => isset($data['sales_revenue_year']) ? (float)$data['sales_revenue_year'] : 0.0,
            'sales_transactions_today' => isset($data['sales_transactions_today']) ? (int)$data['sales_transactions_today'] : 0,
            'sales_transactions_week' => isset($data['sales_transactions_week']) ? (int)$data['sales_transactions_week'] : 0,
            'sales_transactions_month' => isset($data['sales_transactions_month']) ? (int)$data['sales_transactions_month'] : 0,
            'sales_transactions_year' => isset($data['sales_transactions_year']) ? (int)$data['sales_transactions_year'] : 0,
            'avg_daily_sales_month' => isset($data['avg_daily_sales_month']) ? (float)$data['avg_daily_sales_month'] : 0.0,
            'top_selling_product_month' => $data['top_selling_product_month'] ?? 'N/A',
        ];
    }

    if ($result) {
        mysqli_free_result($result);
    }

    return $defaults;
}

function fetchTransactionsByPeriodFallback(mysqli $conn = null): array {
    $defaults = [
        'day' => 0,
        'week' => 0,
        'month' => 0,
        'year' => 0,
    ];

    if (!$conn) {
        return $defaults;
    }

    $queries = [
        'day' => "SELECT COUNT(DISTINCT sale_id) AS total FROM sales WHERE DATE(created_at) = CURDATE()",
        'week' => "SELECT COUNT(DISTINCT sale_id) AS total FROM sales WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)",
        'month' => "SELECT COUNT(DISTINCT sale_id) AS total FROM sales WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())",
        'year' => "SELECT COUNT(DISTINCT sale_id) AS total FROM sales WHERE YEAR(created_at) = YEAR(CURDATE())",
    ];

    $totals = $defaults;

    foreach ($queries as $key => $sql) {
        $result = @mysqli_query($conn, $sql);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $totals[$key] = (int)($row['total'] ?? 0);
            mysqli_free_result($result);
        } elseif ($result) {
            mysqli_free_result($result);
        }
    }

    return $totals;
}

function getDefaultExpireThreshold(mysqli $conn = null, int $fallback = 30): int {
    if (!$conn) {
        return $fallback;
    }

    $result = @mysqli_query($conn, "SELECT expire_threshold, COUNT(*) as count FROM products WHERE expire_threshold > 0 GROUP BY expire_threshold ORDER BY count DESC LIMIT 1");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row && isset($row['expire_threshold'])) {
            return (int)$row['expire_threshold'];
        }
    }

    return $fallback;
}

function getDefaultProductThreshold(mysqli $conn = null, int $fallback = 10): int {
    if (!$conn) {
        return $fallback;
    }

    $result = @mysqli_query($conn, "SELECT product_threshold, COUNT(*) as count FROM products WHERE product_threshold > 0 GROUP BY product_threshold ORDER BY count DESC LIMIT 1");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row && isset($row['product_threshold'])) {
            return (int)$row['product_threshold'];
        }
    }

    return $fallback;
}

function fetchExpiringAlertsFallback(mysqli $conn = null, int $default = 0): int {
    if (!$conn) {
        return $default;
    }

    $defaultThreshold = getDefaultExpireThreshold($conn, 30);

    // Check if view_expiry_report exists (same as expiry_reports.php)
    if (checkViewExists($conn, 'view_expiry_report')) {
        // Use view_expiry_report - count batches/deliveries expiring soon
        $query = "
            SELECT COUNT(*) AS expiring_count
            FROM view_expiry_report ver
            LEFT JOIN products p ON ver.product_id = p.product_id
            WHERE ver.expiry_date IS NOT NULL
              AND YEAR(ver.expiry_date) > 0
              AND ver.expiry_date >= CURDATE()
              AND (COALESCE(ver.storage_packages_balance, 0) + COALESCE(ver.pharmacy_units_balance, 0)) > 0
              AND DATEDIFF(ver.expiry_date, CURDATE()) >= 0
              AND DATEDIFF(ver.expiry_date, CURDATE()) <= COALESCE(p.expire_threshold, $defaultThreshold)
        ";
        
        $result = @mysqli_query($conn, $query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            if ($row && isset($row['expiring_count'])) {
                return (int)$row['expiring_count'];
            }
        }
    }

    // Fallback: use deliveries and inventory tables
    $storageInventorySubquery = "
        SELECT 
            delivery_id,
            SUM(quantity_packages_balance) AS storage_packages_balance,
            SUM(quantity_units_balance) AS storage_units_balance
        FROM storage_inventory
        GROUP BY delivery_id
    ";

    $pharmacyInventorySubquery = "
        SELECT 
            delivery_id,
            SUM(quantity_units_balance) AS pharmacy_units_balance
        FROM pharmacy_inventory
        GROUP BY delivery_id
    ";

    $deliveryStockSubquery = "
        SELECT 
            d.delivery_id,
            d.product_id,
            d.expiry_date,
            d.quantity_units,
            d.quantity_packages,
            COALESCE(si.storage_packages_balance, 0) AS storage_packages_balance,
            COALESCE(si.storage_units_balance, 0) AS storage_units_balance,
            COALESCE(pi.pharmacy_units_balance, 0) AS pharmacy_units_balance,
            (COALESCE(pi.pharmacy_units_balance, 0) + COALESCE(si.storage_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0)) AS total_quantity
        FROM deliveries d
        LEFT JOIN ($storageInventorySubquery) si ON si.delivery_id = d.delivery_id
        LEFT JOIN ($pharmacyInventorySubquery) pi ON pi.delivery_id = d.delivery_id
        WHERE d.status = 'Active'
    ";

    // Count batches/deliveries (not products) that are expiring soon - matching expiry_reports.php logic
    $query = "
        SELECT COUNT(*) AS expiring_count
        FROM ($deliveryStockSubquery) ds
        LEFT JOIN products p ON ds.product_id = p.product_id
        WHERE ds.expiry_date IS NOT NULL
          AND YEAR(ds.expiry_date) > 0
          AND ds.expiry_date >= CURDATE()
          AND ds.total_quantity > 0
          AND DATEDIFF(ds.expiry_date, CURDATE()) >= 0
          AND DATEDIFF(ds.expiry_date, CURDATE()) <= COALESCE(p.expire_threshold, $defaultThreshold)
    ";

    $result = @mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row && isset($row['expiring_count'])) {
            return (int)$row['expiring_count'];
        }
    }

    return $default;
}

function fetchLowStockAlerts(mysqli $conn = null, int $default = 0, int $defaultProductThreshold = 10): int {
    if (!$conn) {
        return $default;
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

    $inventoryTotalsSubquery = buildInventoryTotalsSubquery($defaultProductThreshold);
    $nearestExpirySubquery = "
        SELECT product_id, MIN(expiry_date) AS nearest_expiry
        FROM deliveries
        WHERE status = 'Active'
        GROUP BY product_id
    ";
    $lowStockQuery = "
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
    $result = @mysqli_query($conn, $lowStockQuery);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row && isset($row['low_count']) && (int)$row['low_count'] > 0) {
            return (int)$row['low_count'];
        }
    }

    if (checkViewExists($conn, 'view_inventory_status_alerts')) {
        $fallbackQuery = "
            SELECT COUNT(DISTINCT CONCAT(v.product_id, '-', v.batch_no)) AS low_count
            FROM view_inventory_status_alerts v
            LEFT JOIN products p ON v.product_id = p.product_id
            WHERE COALESCE(p.product_threshold, 0) > 0
              AND v.total_stock > 0
              AND v.total_stock <= p.product_threshold
              AND (v.expiry_date IS NULL OR v.expiry_date >= CURDATE())
        ";
        $fallbackResult = @mysqli_query($conn, $fallbackQuery);
        if ($fallbackResult) {
            $row = mysqli_fetch_assoc($fallbackResult);
            mysqli_free_result($fallbackResult);
            if ($row && isset($row['low_count'])) {
                return (int)$row['low_count'];
            }
        }
    }

    return $default;
}

function buildInventoryTotalsSubquery(int $defaultThreshold): string {
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

$dashboardSummary = fetchDashboardSummary($conn ?? null);
$dashboardSummary['total_expiring_alerts'] = fetchExpiringAlertsFallback($conn ?? null, (int)($dashboardSummary['total_expiring_alerts'] ?? 0));

$periodLabels = [
    'day' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'year' => 'This Year',
];
$validPeriods = array_keys($periodLabels);

$selectedRevenuePeriod = isset($_GET['period']) && in_array($_GET['period'], $validPeriods, true) ? $_GET['period'] : 'month';
$selectedTransactionsPeriod = isset($_GET['transactions_period']) && in_array($_GET['transactions_period'], $validPeriods, true) ? $_GET['transactions_period'] : 'week';

$revenueHiddenParams = $_GET;
unset($revenueHiddenParams['period']);
$transactionsHiddenParams = $_GET;
unset($transactionsHiddenParams['transactions_period']);

$revenueByPeriod = [
    'day' => $dashboardSummary['sales_revenue_today'],
    'week' => $dashboardSummary['sales_revenue_week'],
    'month' => $dashboardSummary['sales_revenue_month'],
    'year' => $dashboardSummary['sales_revenue_year'],
];
$transactionsByPeriod = [
    'day' => $dashboardSummary['sales_transactions_today'],
    'week' => $dashboardSummary['sales_transactions_week'],
    'month' => $dashboardSummary['sales_transactions_month'],
    'year' => $dashboardSummary['sales_transactions_year'],
];

if (!array_filter($transactionsByPeriod)) {
    $transactionsByPeriod = fetchTransactionsByPeriodFallback($conn ?? null);
}

$revenueDisplay = match ($selectedRevenuePeriod) {
    'day' => $revenueByPeriod['day'] ?? 0,
    'week' => $revenueByPeriod['week'] ?? 0,
    'month' => $revenueByPeriod['month'] ?? 0,
    'year' => $revenueByPeriod['year'] ?? 0,
    default => $revenueByPeriod['day'] ?? 0,
};
$transactionsDisplay = match ($selectedTransactionsPeriod) {
    'day' => $transactionsByPeriod['day'] ?? 0,
    'week' => $transactionsByPeriod['week'] ?? 0,
    'month' => $transactionsByPeriod['month'] ?? 0,
    'year' => $transactionsByPeriod['year'] ?? 0,
    default => $transactionsByPeriod['day'] ?? 0,
};

// Initialize dashboard data
$dashboardData = [
    'totalProducts' => 0,
    'totalStockIn' => 0,
    'totalStockOut' => 0,
    'lowStockAlerts' => 0,
    'salesToday' => ['revenue' => 0, 'sales' => 0],
    'salesThisWeek' => ['revenue' => 0, 'sales' => 0],
    'salesThisMonth' => ['revenue' => 0, 'sales' => 0],
    'recentSales' => [],
    'salesAnalyticsData' => [],
    'topMedicines' => [],
    'expiryAlerts' => ['expired' => 0, 'within_30_days' => 0, 'within_90_days' => 0, 'items' => []],
    'lowStockItems' => [],
    'stockTotalItems' => 0,
    'stockInventoryValue' => 0.0,
    'stockCategoryData' => []
];

// Helper function to safely execute queries
function executeQuery($conn, $query, $default = 0) {
    $result = @mysqli_query($conn, $query);
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        mysqli_free_result($result);
        return $row;
    }
    if ($result) {
        mysqli_free_result($result);
    }
    return ['value' => $default];
}

// Get basic statistics with fallbacks for views
$viewCheck = mysqli_query($conn, "SHOW TABLES LIKE 'view_available_products'");
$hasView = $viewCheck && mysqli_num_rows($viewCheck) > 0;
if ($viewCheck) mysqli_free_result($viewCheck);

if ($hasView) {
    $totalProductsResult = executeQuery($conn, "SELECT COUNT(DISTINCT product_id) AS total_products FROM view_available_products");
    $dashboardData['totalProducts'] = (int)($totalProductsResult['total_products'] ?? 0);
} else {
    $totalProductsResult = executeQuery($conn, "SELECT COUNT(DISTINCT product_id) AS total_products FROM products");
    $dashboardData['totalProducts'] = (int)($totalProductsResult['total_products'] ?? 0);
}

// Stock card data from stock_movement_log
$viewStockCardCheck = mysqli_query($conn, "SHOW TABLES LIKE 'view_stock_card'");
$hasStockCardView = $viewStockCardCheck && mysqli_num_rows($viewStockCardCheck) > 0;
if ($viewStockCardCheck) mysqli_free_result($viewStockCardCheck);

if ($hasStockCardView) {
    $stockCardResult = executeQuery($conn, "SELECT SUM(total_in) AS total_in, SUM(total_out) AS total_out FROM view_stock_card");
    $dashboardData['totalStockIn'] = (int)($stockCardResult['total_in'] ?? 0);
    $dashboardData['totalStockOut'] = (int)($stockCardResult['total_out'] ?? 0);
} else {
    // Get from stock_movement_log
    $stockCardResult = executeQuery($conn, "SELECT 
        SUM(CASE WHEN movement_type IN ('delivery', 'transfer') AND to_inventory IN ('storage', 'pharmacy') THEN quantity_units ELSE 0 END) AS total_in,
        SUM(CASE WHEN movement_type = 'sale' THEN quantity_units ELSE 0 END) AS total_out
        FROM stock_movement_log");
    $dashboardData['totalStockIn'] = (int)($stockCardResult['total_in'] ?? 0);
    $dashboardData['totalStockOut'] = (int)($stockCardResult['total_out'] ?? 0);
}

$defaultExpireThreshold = getDefaultExpireThreshold($conn ?? null, 30);

$defaultProductThreshold = getDefaultProductThreshold($conn ?? null, 10);

// Check for view_inventory_status_alerts (unified view for all alerts) - check early so it can be used in multiple places
$hasInventoryStatusView = checkViewExists($conn, 'view_inventory_status_alerts');

$dashboardData['lowStockAlerts'] = fetchLowStockAlerts($conn ?? null, 0, $defaultProductThreshold);
$dashboardSummary['total_low_stock_alerts'] = $dashboardData['lowStockAlerts'];

function checkViewExists($conn, $viewName) {
    // Simple and reliable view check
    $result = @mysqli_query($conn, "SHOW TABLES LIKE '$viewName'");
    $exists = $result && mysqli_num_rows($result) > 0;
    if ($result) mysqli_free_result($result);
    return $exists;
}

// Get sales data
$dashboardData['salesToday'] = getSalesData($conn, 'today');
$dashboardData['salesThisWeek'] = getSalesData($conn, 'week');
$dashboardData['salesThisMonth'] = getSalesData($conn, 'month');

function getSalesData($conn, $period) {
    $queries = [
        'today' => "SELECT COALESCE(SUM(s.total_amount), 0) AS total_revenue, COUNT(s.sale_id) AS total_orders FROM sales s WHERE DATE(s.created_at) = CURDATE()",
        'week' => "SELECT COALESCE(SUM(s.total_amount), 0) AS total_revenue, COUNT(s.sale_id) AS total_orders FROM sales s WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND s.created_at <= CURDATE()",
        'month' => "SELECT COALESCE(SUM(s.total_amount), 0) AS total_revenue, COUNT(s.sale_id) AS total_orders FROM sales s WHERE MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())"
    ];
    
    $query = $queries[$period] ?? $queries['today'];
    $result = executeQuery($conn, $query);
    
    return [
        'revenue' => (float)($result['total_revenue'] ?? 0),
        'sales' => (int)($result['total_orders'] ?? 0)
    ];
}

// Get recent sales and analytics data
$dashboardData['recentSales'] = getRecentSales($conn);
$dashboardData['salesAnalyticsData'] = getSalesAnalytics($conn);
$dashboardData['topMedicines'] = getTopMedicines($conn);

function getRecentSales($conn) {
    $query = "SELECT 
                s.sale_id, 
                s.created_at AS sale_date, 
                s.total_amount,
                CONCAT(u.firstname, ' ', u.lastname) AS cashier_name,
                GROUP_CONCAT(DISTINCT COALESCE(p.brand_name, p.generic_name, 'Unknown Product') ORDER BY p.brand_name SEPARATOR ', ') as products
              FROM sales s
              LEFT JOIN users u ON s.user_id = u.user_id
              LEFT JOIN sales_details sd ON s.sale_id = sd.sale_id
              LEFT JOIN deliveries d ON sd.delivery_id = d.delivery_id
              LEFT JOIN products p ON d.product_id = p.product_id
              GROUP BY s.sale_id, s.created_at, s.total_amount, u.firstname, u.lastname
              ORDER BY s.created_at DESC LIMIT 5";
    
    $result = @mysqli_query($conn, $query);
    $sales = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $sales[] = $row;
        }
        mysqli_free_result($result);
    }
    return $sales;
}

function getSalesAnalytics($conn) {
    $query = "SELECT 
        DATE_FORMAT(s.created_at, '%m') as month_num,
        DATE_FORMAT(s.created_at, '%b') as month_name,
        SUM(s.total_amount) as monthly_revenue,
        COUNT(DISTINCT s.sale_id) as monthly_sales
        FROM sales s
        WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(s.created_at, '%Y-%m'), DATE_FORMAT(s.created_at, '%m'), DATE_FORMAT(s.created_at, '%b')
        ORDER BY DATE_FORMAT(s.created_at, '%Y-%m') ASC";
    
    $result = @mysqli_query($conn, $query);
    $analytics = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $analytics[] = $row;
        }
        mysqli_free_result($result);
    }
    return $analytics;
}

function getTopMedicines($conn) {
    $query = "SELECT 
        COALESCE(p.brand_name, p.generic_name) as brand_name,
        p.generic_name,
        SUM(sd.quantity_units + (sd.quantity_packages * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0))) as total_quantity,
        SUM(sd.subtotal) as total_revenue
        FROM sales s
        INNER JOIN sales_details sd ON s.sale_id = sd.sale_id
        INNER JOIN deliveries d ON sd.delivery_id = d.delivery_id
        INNER JOIN products p ON d.product_id = p.product_id
        WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        GROUP BY p.brand_name, p.generic_name
        ORDER BY total_quantity DESC
        LIMIT 3";
    
    $result = @mysqli_query($conn, $query);
    $medicines = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $medicines[] = $row;
        }
        mysqli_free_result($result);
    }
    return $medicines;
}

// Get remaining dashboard data
// Pass view name and threshold to function (hasInventoryStatusView already checked above)
$dashboardData['expiryAlerts'] = getExpiryAlerts($conn, $hasInventoryStatusView, 'view_inventory_status_alerts', $defaultExpireThreshold);
$dashboardData['lowStockItems'] = getLowStockItems($conn, $hasInventoryStatusView, $defaultProductThreshold);
$dashboardData['stockTotalItems'] = getStockTotalItems($conn);
$dashboardData['stockInventoryValue'] = getStockInventoryValue($conn);
$dashboardData['stockCategoryData'] = getStockCategoryData($conn);

// Define variables for backward compatibility
$expiringProductsCount = $dashboardData['expiryAlerts']['within_30_days'];
$criticalExpiringCount = $dashboardData['expiryAlerts']['expired'];
$lowStockAlerts = $dashboardData['lowStockAlerts'];
$totalProducts = $dashboardData['totalProducts'];
$stockInventoryValue = $dashboardData['stockInventoryValue'];
$recentSales = $dashboardData['recentSales'];
$salesAnalyticsData = $dashboardData['salesAnalyticsData'];
$topMedicines = $dashboardData['topMedicines'];
$salesToday = $dashboardData['salesToday'];
$salesThisWeek = $dashboardData['salesThisWeek'];
$salesThisMonth = $dashboardData['salesThisMonth'];

function getExpiryAlerts($conn, $hasView, $viewName = 'view_inventory_status_alerts', $expireThreshold = 30) {
    try {
        if ($hasView) {
            // Use view_inventory_status_alerts - count distinct PRODUCTS (not batches) with stock
            $expiredResult = executeQuery($conn, "SELECT COUNT(DISTINCT product_id) AS count FROM view_inventory_status_alerts WHERE expiry_date < CURDATE() AND total_stock > 0");
            $withinThresholdResult = executeQuery($conn, "SELECT COUNT(DISTINCT product_id) AS count FROM view_inventory_status_alerts WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL $expireThreshold DAY) AND total_stock > 0");
            $within90Result = executeQuery($conn, "SELECT COUNT(DISTINCT product_id) AS count FROM view_inventory_status_alerts WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND total_stock > 0");
            
            $itemsResult = @mysqli_query($conn, "SELECT 
                delivery_id,
                product_id,
                brand_name,
                generic_name,
                batch_no,
                expiry_date,
                delivery_date,
                total_stock,
                stock_status,
                DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
                FROM view_inventory_status_alerts 
                WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL $expireThreshold DAY)
                ORDER BY expiry_date ASC, days_until_expiry ASC LIMIT 5");
        } else {
            // Fallback query using deliveries and inventory tables - count distinct PRODUCTS (not batches) with stock
            $expiredResult = executeQuery($conn, "SELECT COUNT(DISTINCT d.product_id) AS count 
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
                WHERE d.expiry_date < CURDATE() 
                  AND d.status = 'Active'
                  AND (COALESCE(si.storage_packages_balance, 0) > 0 OR COALESCE(pi.pharmacy_units_balance, 0) > 0)");
            $withinThresholdResult = executeQuery($conn, "SELECT COUNT(DISTINCT d.product_id) AS count 
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
                WHERE d.expiry_date >= CURDATE() 
                  AND d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL $expireThreshold DAY) 
                  AND d.status = 'Active'
                  AND (COALESCE(si.storage_packages_balance, 0) > 0 OR COALESCE(pi.pharmacy_units_balance, 0) > 0)");
            $within90Result = executeQuery($conn, "SELECT COUNT(DISTINCT d.product_id) AS count 
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
                WHERE d.expiry_date >= CURDATE() 
                  AND d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) 
                  AND d.status = 'Active'
                  AND (COALESCE(si.storage_packages_balance, 0) > 0 OR COALESCE(pi.pharmacy_units_balance, 0) > 0)");
            
            $itemsResult = @mysqli_query($conn, "SELECT 
                d.delivery_id,
                d.product_id,
                p.brand_name,
                p.generic_name,
                d.batch_no,
                d.expiry_date,
                d.delivery_date,
                (COALESCE(pi.quantity_units_balance, 0) + COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0)) as total_stock,
                CASE 
                    WHEN d.expiry_date < CURDATE() THEN 'Expired'
                    WHEN DATEDIFF(d.expiry_date, CURDATE()) <= $expireThreshold THEN 'Expiring Soon'
                    ELSE 'Active'
                END as stock_status,
                DATEDIFF(d.expiry_date, CURDATE()) as days_until_expiry
                FROM deliveries d
                LEFT JOIN products p ON d.product_id = p.product_id
                LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
                LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
                WHERE d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL $expireThreshold DAY)
                    AND d.status = 'Active'
                ORDER BY d.expiry_date ASC, days_until_expiry ASC LIMIT 5");
        }
        
        $items = [];
        if ($itemsResult) {
            while ($row = mysqli_fetch_assoc($itemsResult)) {
                $items[] = $row;
            }
            mysqli_free_result($itemsResult);
        }
        
        return [
            'expired' => (int)($expiredResult['count'] ?? 0),
            'within_30_days' => (int)($withinThresholdResult['count'] ?? 0),
            'within_90_days' => (int)($within90Result['count'] ?? 0),
            'items' => $items
        ];
    } catch (Exception $e) {
        error_log('Expiry alerts error: ' . $e->getMessage());
        return ['expired' => 0, 'within_30_days' => 0, 'within_90_days' => 0, 'items' => []];
    }
}

function getLowStockItems($conn, $hasView, $defaultThreshold = 10) {
    try {
        $inventoryTotalsSubquery = buildInventoryTotalsSubquery($defaultThreshold);
        $query = "
            SELECT 
                totals.product_id,
                totals.brand_name,
                totals.generic_name,
                totals.supplier_name,
                totals.total_units,
                totals.storage_units,
                totals.pharmacy_units,
                totals.threshold,
                CASE 
                    WHEN totals.total_units <= GREATEST(1, ROUND(totals.threshold * 0.3)) THEN 'Critical'
                    ELSE 'Low Stock'
                END AS status
            FROM (
                $inventoryTotalsSubquery
            ) totals
            WHERE totals.threshold > 0
              AND totals.total_units > 0
              AND totals.total_units <= totals.threshold
            ORDER BY totals.total_units ASC
            LIMIT 5
        ";
        $result = @mysqli_query($conn, $query);

        if ((!$result || mysqli_num_rows($result) === 0) && $hasView) {
            $result = @mysqli_query($conn, "SELECT 
                delivery_id,
                product_id,
                brand_name,
                generic_name,
                batch_no,
                expiry_date,
                delivery_date,
                total_stock,
                threshold,
                stock_status
                FROM view_inventory_status_alerts 
                WHERE COALESCE(threshold, 0) > 0
                  AND total_stock > 0
                  AND total_stock <= threshold
                ORDER BY total_stock ASC LIMIT 5");
            $items = [];
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $items[] = $row;
                }
                mysqli_free_result($result);
            }
            return $items;
        }
        
        $items = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $productDetails = trim(($row['brand_name'] ?? '') . ' ' . ($row['generic_name'] ?? ''));
                $items[] = [
                    'product_details' => $productDetails !== '' ? $productDetails : 'Unknown Product',
                    'supplier' => $row['supplier_name'] ?? 'N/A',
                    'total_stock' => (float)($row['total_units'] ?? 0),
                    'storage_stock' => (float)($row['storage_units'] ?? 0),
                    'pharmacy_stock' => (float)($row['pharmacy_units'] ?? 0),
                    'threshold' => (int)($row['threshold'] ?? 0),
                    'package_unit' => '',
                    'expiry_info' => '',
                    'status' => $row['status'] ?? 'Low Stock',
                ];
            }
            mysqli_free_result($result);
        }
        return $items;
    } catch (Exception $e) {
        error_log('Low stock items error: ' . $e->getMessage());
        return [];
    }
}

function getStockTotalItems($conn) {
    // Use pharmacy_inventory table as primary source for total units available
    // Count distinct products that have stock in pharmacy_inventory
    $result = executeQuery($conn, "SELECT COUNT(DISTINCT d.product_id) AS total_items 
        FROM pharmacy_inventory pi
        INNER JOIN deliveries d ON pi.delivery_id = d.delivery_id
        WHERE pi.quantity_units_balance > 0");
    
    $totalItems = (int)($result['total_items'] ?? 0);
    
    // If no items found in pharmacy_inventory, check storage_inventory as fallback
    if ($totalItems == 0) {
        $result = executeQuery($conn, "SELECT COUNT(DISTINCT d.product_id) AS total_items 
            FROM storage_inventory si
            INNER JOIN deliveries d ON si.delivery_id = d.delivery_id
            WHERE si.quantity_packages_balance > 0");
        $totalItems = (int)($result['total_items'] ?? 0);
    }
    
    return $totalItems;
}

function getStockInventoryValue($conn) {
    // Use pharmacy_inventory table as primary source for inventory value calculation
    // Calculate: sum of (quantity_units_balance * selling_price_unit) for all deliveries
    
    $result = executeQuery($conn, "SELECT COALESCE(SUM(
        pi.quantity_units_balance * COALESCE(d.selling_price_unit, 0)
    ), 0) AS inventory_value
    FROM pharmacy_inventory pi
    INNER JOIN deliveries d ON pi.delivery_id = d.delivery_id
    WHERE pi.quantity_units_balance > 0");
    
    $pharmacyValue = (float)($result['inventory_value'] ?? 0);
    
    // Also include storage_inventory value as fallback/additional
    $result = executeQuery($conn, "SELECT COALESCE(SUM(
        (si.quantity_packages_balance * d.quantity_units) * COALESCE(d.selling_price_unit, 0)
    ), 0) AS inventory_value
    FROM storage_inventory si
    INNER JOIN deliveries d ON si.delivery_id = d.delivery_id
    WHERE si.quantity_packages_balance > 0");
    
    $storageValue = (float)($result['inventory_value'] ?? 0);
    
    return $pharmacyValue + $storageValue;
}

function getStockCategoryData($conn) {
    // Use pharmacy_inventory table directly and join with categories table
    // Count distinct products per category that have stock in pharmacy_inventory
    
    $result = @mysqli_query($conn, "SELECT 
        c.category_name, 
        COUNT(DISTINCT d.product_id) AS item_count 
    FROM pharmacy_inventory pi
    INNER JOIN deliveries d ON pi.delivery_id = d.delivery_id
    INNER JOIN products p ON d.product_id = p.product_id
    INNER JOIN categories c ON p.category_id = c.category_id
    WHERE pi.quantity_units_balance > 0
        AND c.status = 'Active'
    GROUP BY c.category_name 
    ORDER BY item_count DESC 
    LIMIT 3");
    
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        mysqli_free_result($result);
    }
    
    // If no data from pharmacy_inventory, also check storage_inventory
    if (empty($data)) {
        $result = @mysqli_query($conn, "SELECT 
            c.category_name, 
            COUNT(DISTINCT d.product_id) AS item_count 
        FROM storage_inventory si
        INNER JOIN deliveries d ON si.delivery_id = d.delivery_id
        INNER JOIN products p ON d.product_id = p.product_id
        INNER JOIN categories c ON p.category_id = c.category_id
        WHERE si.quantity_packages_balance > 0
            AND c.status = 'Active'
        GROUP BY c.category_name 
        ORDER BY item_count DESC 
        LIMIT 3");
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
            mysqli_free_result($result);
        }
    }
    
    return $data;
}
?>

<!-- Modern Pharmacy Dashboard Content -->
<div class="min-h-screen bg-gray-50 p-6">
  <style>
    body {
      background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
    }
    .metric-card {
      position: relative;
      border-radius: 1.75rem;
      background: linear-gradient(145deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.9));
      border: 1px solid rgba(226, 232, 240, 0.7);
      padding: 2rem;
      box-shadow: 0 25px 45px -20px rgba(15, 23, 42, 0.25);
      backdrop-filter: blur(6px);
      overflow: hidden;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .metric-card::before {
      content: "";
      position: absolute;
      top: -60px;
      right: -60px;
      width: 160px;
      height: 160px;
      border-radius: 50%;
      background: radial-gradient(circle at center, rgba(59, 130, 246, 0.12), transparent 70%);
      transform: scale(0.85);
      transition: transform 0.35s ease, opacity 0.35s ease;
      opacity: 0.8;
    }
    .metric-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 35px 55px -25px rgba(15, 23, 42, 0.35);
    }
    .metric-card:hover::before {
      transform: scale(1.05);
      opacity: 1;
    }
    .metric-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
      padding: 0.4rem 0.85rem;
      background: rgba(15, 118, 110, 0.12);
      color: #0f766e;
      box-shadow: inset 0 0 0 1px rgba(14, 116, 144, 0.15);
    }
    .metric-dropdown {
      cursor: pointer;
      transition: box-shadow 0.2s ease, transform 0.2s ease;
      background: rgba(248, 250, 252, 0.9);
      border: 1px solid rgba(203, 213, 225, 0.7);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.55);
    }
    .metric-dropdown:hover {
      box-shadow: 0 8px 22px -14px rgba(15, 118, 110, 0.5);
      transform: translateY(-1px);
    }
    .metric-dropdown:focus {
      border-color: rgba(13, 148, 136, 0.6);
      box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.35);
    }
    .metric-icon {
      width: 3rem;
      height: 3rem;
      border-radius: 1.1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.45rem;
      margin-right: 0.9rem;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
    }
    .metric-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
    }
    .metric-title {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .dashboard-grid {
      display: grid;
      gap: 1.75rem;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      align-items: stretch;
    }
    @media (min-width: 1280px) {
      .dashboard-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }
    .dashboard-hero {
      position: relative;
      border-radius: 2.25rem;
      background: linear-gradient(135deg, rgba(15, 118, 110, 0.16) 0%, rgba(30, 64, 175, 0.12) 45%, rgba(15, 23, 42, 0.75) 100%);
      padding: 1.5rem;
      overflow: hidden;
      box-shadow: 0 35px 80px -40px rgba(15, 23, 42, 0.55);
      color: #0f172a;
      isolation: isolate;
    }
    .dashboard-hero::before,
    .dashboard-hero::after {
      content: "";
      position: absolute;
      border-radius: 50%;
      filter: blur(0px);
      opacity: 0.55;
      z-index: -1;
      mix-blend-mode: screen;
    }
    .dashboard-hero::before {
      width: 320px;
      height: 320px;
      top: -120px;
      right: -80px;
      background: radial-gradient(circle at center, rgba(59, 130, 246, 0.35), transparent 70%);
    }
    .dashboard-hero::after {
      width: 260px;
      height: 260px;
      bottom: -140px;
      left: -40px;
      background: radial-gradient(circle at center, rgba(45, 212, 191, 0.4), transparent 70%);
    }
    .hero-content {
      display: flex;
      flex-direction: column;
      gap: 1.75rem;
    }
    .hero-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1.75rem;
      flex-wrap: wrap;
    }
    .hero-text {
      max-width: 540px;
    }
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.45rem 0.9rem;
      border-radius: 999px;
      background: rgba(15, 118, 110, 0.18);
      color: #0f766e;
      font-weight: 600;
      font-size: 0.8rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.35);
      margin-bottom: 0.85rem;
    }
    .hero-text h1 {
      font-size: 2.35rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      color: #0f172a;
      margin-bottom: 0.75rem;
    }
    .hero-text p {
      font-size: 0.95rem;
      line-height: 1.65;
      color: #1e293b;
    }
    .hero-meta {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex-wrap: wrap;
    }
    .hero-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.65);
      color: #0f172a;
      font-weight: 600;
      font-size: 0.8rem;
      box-shadow: 0 10px 20px -12px rgba(15, 23, 42, 0.45);
    }
    .hero-stats {
      display: grid;
      gap: 1.5rem;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    }
    .hero-stat {
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
      padding: 1rem 1.15rem;
      border-radius: 1.25rem;
      background: rgba(255, 255, 255, 0.55);
      box-shadow: inset 0 0 0 1px rgba(226, 232, 240, 0.5);
    }
    .hero-stat span {
      font-size: 0.65rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #64748b;
      font-weight: 600;
    }
    .hero-stat strong {
      font-size: 1.35rem;
      font-weight: 700;
      color: #0f172a;
    }
  </style>
  <div class="max-w-7xl mx-auto space-y-8">
    
    <!-- Hero Header -->
    <section class="dashboard-hero">
      <div class="hero-content">
        <div class="hero-header">
          <div class="hero-text">
            <span class="hero-badge">Admin Portal</span>
            <h1>Admin Dashboard Overview</h1>
            </div>
          <div class="hero-meta">
            <div class="hero-chip">📅 <?= date('F j, Y'); ?></div>
            <div class="hero-chip">⚡ Live sync enabled</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Statistics Cards -->
    <section class="dashboard-grid">
      <article class="metric-card">
        <div class="metric-header">
          <div class="metric-title">
            <span class="metric-icon" style="background: linear-gradient(135deg, #22d3ee 0%, #0ea5e9 100%);">🛒</span>
            <div>
              <h2 class="text-sm uppercase tracking-wide text-gray-500">Items Sold Today</h2>
              <p class="text-xs text-gray-400">Total quantity processed</p>
                    </div>
          </div>
          <span class="metric-badge">Live</span>
        </div>
        <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($dashboardSummary['total_items_sold']); ?></p>
      </article>

      <article class="metric-card" onclick="openExpiringProductsModal()" style="cursor:pointer;">
        <div class="metric-header">
          <div class="metric-title">
            <span class="metric-icon" style="background: linear-gradient(135deg, #fca5a5 0%, #f87171 100%);">⏳</span>
                    <div>
              <h2 class="text-sm uppercase tracking-wide text-gray-500">Expiring Alerts</h2>
              <p class="text-xs text-gray-400">Driven by product expiry thresholds</p>
                    </div>
                  </div>
          <span class="metric-badge bg-amber-100 text-amber-700">Alerts</span>
                  </div>
        <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($dashboardSummary['total_expiring_alerts']); ?></p>
      </article>

      <article class="metric-card" onclick="openLowStockModal()" style="cursor:pointer;">
        <div class="metric-header">
          <div class="metric-title">
            <span class="metric-icon" style="background: linear-gradient(135deg, #bbf7d0 0%, #4ade80 100%);">📦</span>
            <div>
              <h2 class="text-sm uppercase tracking-wide text-gray-500">Low Stock Alerts</h2>
              <p class="text-xs text-gray-400">Flagged by product-specific thresholds</p>
                </div>
              </div>
          <span class="metric-badge">Critical</span>
          </div>
        <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($dashboardSummary['total_low_stock_alerts']); ?></p>
      </article>

      <article class="metric-card">
        <div class="metric-header">
          <div class="metric-title">
            <span class="metric-icon" style="background: linear-gradient(135deg, #bae6fd 0%, #2563eb 100%);">✅</span>
            <div>
              <h2 class="text-sm uppercase tracking-wide text-gray-500">Products Available</h2>
            
        </div>
          </div>
          <span class="metric-badge">Active</span>
        </div>
        <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($dashboardSummary['total_products_available']); ?></p>
      </article>

      <article class="metric-card">
        <div class="metric-header">
          <div class="metric-title">
            <span class="metric-icon" style="background: linear-gradient(135deg, #fde68a 0%, #f59e0b 100%);">💰</span>
            <div>
              <h2 class="text-sm uppercase tracking-wide text-gray-500">Sales Revenue</h2>
              <p class="text-xs text-gray-400">Select period to update</p>
            </div>
          </div>
          <form method="get" class="flex items-center gap-2" onsubmit="this.querySelector('select').disabled = true;">
            <label for="adminRevenuePeriod" class="sr-only">Revenue Period</label>
<?php foreach ($revenueHiddenParams as $paramKey => $paramValue):
    if (is_array($paramValue)) { continue; }
?>
            <input type="hidden" name="<?= htmlspecialchars($paramKey); ?>" value="<?= htmlspecialchars($paramValue); ?>">
<?php endforeach; ?>
            <select id="adminRevenuePeriod" name="period" class="metric-dropdown text-xs font-semibold text-gray-600 rounded-lg px-2 py-1 focus:outline-none" onchange="this.form.submit()">
<?php foreach ($validPeriods as $period): ?>
              <option value="<?= htmlspecialchars($period); ?>" <?= $period === $selectedRevenuePeriod ? 'selected="selected"' : ''; ?>><?= htmlspecialchars($periodLabels[$period]); ?></option>
<?php endforeach; ?>
            </select>
          </form>
          </div>
        <p class="text-4xl font-bold text-gray-900 mb-2">₱<?= number_format($revenueDisplay, 2); ?></p>
      </article>

      <article class="metric-card">
        <div class="metric-header">
          <div class="metric-title">
            <span class="metric-icon" style="background: linear-gradient(135deg, #c7d2fe 0%, #6366f1 100%);">🧾</span>
            <div>
              <h2 class="text-sm uppercase tracking-wide text-gray-500">Sales Transactions</h2>
              <p class="text-xs text-gray-400">Period filter applies instantly</p>
            </div>
          </div>
          <form method="get" class="flex items-center gap-2" onsubmit="this.querySelector('select').disabled = true;">
            <label for="adminTransactionsPeriod" class="sr-only">Transactions Period</label>
<?php foreach ($transactionsHiddenParams as $paramKey => $paramValue):
    if (is_array($paramValue)) { continue; }
?>
            <input type="hidden" name="<?= htmlspecialchars($paramKey); ?>" value="<?= htmlspecialchars($paramValue); ?>">
<?php endforeach; ?>
            <select id="adminTransactionsPeriod" name="transactions_period" class="metric-dropdown text-xs font-semibold text-gray-600 rounded-lg px-2 py-1 focus:outline-none" onchange="this.form.submit()">
<?php foreach ($validPeriods as $period): ?>
              <option value="<?= htmlspecialchars($period); ?>" <?= $period === $selectedTransactionsPeriod ? 'selected="selected"' : ''; ?>><?= htmlspecialchars($periodLabels[$period]); ?></option>
<?php endforeach; ?>
            </select>
          </form>
        </div>
        <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($transactionsDisplay); ?></p>
      </article>
    </section>

<style>
/* Custom scrollbar */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
}

::-webkit-scrollbar-thumb {
  background: linear-gradient(45deg, #1A202C, #D9F99D);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(45deg, #0F172A, #A7F3D0);
}

/* Mobile optimizations */
@media (max-width: 768px) {
  .grid.grid-cols-1.lg\:grid-cols-4 {
    grid-template-columns: 1fr !important;
  }
  
  .text-3xl {
    font-size: 1.5rem !important;
  }
  
  .p-6 {
    padding: 1rem !important;
  }
}

/* Tablet optimizations */
@media (min-width: 768px) and (max-width: 1024px) {
  .grid.grid-cols-1.lg\:grid-cols-4 {
    grid-template-columns: repeat(2, 1fr) !important;
  }
}
</style>

<script src="../Assets/Javascript/errorAndsuccessHander.js"></script>
<script>
// Initialize modal handlers (no URL parameters for dashboard)
initModalHandlers({}, {});
</script>

</div>




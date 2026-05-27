<?php include '../includes/CashierHeader.php'; ?>

<?php
function fetchDashboardSummary(mysqli $conn = null): array {
    $defaults = [
        'total_items_sold_today' => 0,
        'total_expiring_alerts' => 0,
        'total_low_stock_alerts' => 0,
        'total_products_available' => 0,
        'sales_revenue_today' => 0.0,
        'sales_transactions_today' => 0,
        'sales_revenue_month' => 0.0,
        'total_expired_products' => 0,
        'total_package_products' => 0,
    ];

    if (!$conn) {
        return $defaults;
    }

    $result = @mysqli_query($conn, "SELECT * FROM view_dashboard_summary LIMIT 1");

    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        if ($row) {
            $rowLower = array_change_key_case($row, CASE_LOWER);
            $alias = function (array $source, array $keys, $default = 0) {
                foreach ($keys as $key) {
                    $lookup = strtolower($key);
                    if (array_key_exists($lookup, $source) && $source[$lookup] !== null) {
                        return $source[$lookup];
                    }
                }
                return $default;
            };

            return [
                'total_items_sold_today' => (float)$alias($rowLower, ['total_items_sold_today', 'items_sold_today', 'total_items_sold', 'items_sold'], 0),
                'total_expiring_alerts' => (int)$alias($rowLower, ['total_expiring_alerts', 'expiring_alerts', 'expiring_count'], 0),
                'total_low_stock_alerts' => (int)$alias($rowLower, ['total_low_stock_alerts', 'low_stock_alerts', 'low_stock_count'], 0),
                'total_products_available' => (int)$alias($rowLower, ['total_products_available', 'products_available', 'available_products'], 0),
                'sales_revenue_today' => (float)$alias($rowLower, ['sales_revenue_today', 'today_revenue', 'revenue_today'], 0.0),
                'sales_transactions_today' => (int)$alias($rowLower, ['sales_transactions_today', 'transactions_today', 'today_transactions'], 0),
                'sales_revenue_month' => (float)$alias($rowLower, ['sales_revenue_month', 'month_revenue', 'revenue_month'], 0.0),
                'total_expired_products' => (int)$alias($rowLower, ['total_expired_products', 'expired_products', 'expired_count'], 0),
                'total_package_products' => (float)$alias($rowLower, ['total_package_products', 'package_products', 'packages_available', 'total_packages'], 0),
            ];
        }
    } else {
        error_log('Staff Dashboard: failed to query view_dashboard_summary - ' . mysqli_error($conn));
    }

    return $defaults;
}

function fetchExpiryThreshold(mysqli $conn = null, int $default = 30): int {
    if (!$conn) {
        return $default;
    }

    $result = @mysqli_query($conn, "SELECT expire_threshold FROM products WHERE expire_threshold IS NOT NULL AND expire_threshold > 0 ORDER BY expire_threshold ASC LIMIT 1");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row && isset($row['expire_threshold'])) {
            return (int)$row['expire_threshold'];
        }
    }

    return $default;
}

function fetchRevenueByPeriod(mysqli $conn = null): array {
    $defaults = [
        'today_revenue' => 0.0,
        'week_revenue' => 0.0,
        'month_revenue' => 0.0,
        'year_revenue' => 0.0,
    ];

    if (!$conn) {
        return $defaults;
    }

    $queries = [
        'today_revenue' => "SELECT COALESCE(SUM(total_amount), 0) AS value FROM sales WHERE DATE(created_at) = CURDATE()",
        'week_revenue' => "SELECT COALESCE(SUM(total_amount), 0) AS value FROM sales WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)",
        'month_revenue' => "SELECT COALESCE(SUM(total_amount), 0) AS value FROM sales WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())",
        'year_revenue' => "SELECT COALESCE(SUM(total_amount), 0) AS value FROM sales WHERE YEAR(created_at) = YEAR(CURDATE())",
    ];

    $revenues = $defaults;

    foreach ($queries as $key => $sql) {
        $result = @mysqli_query($conn, $sql);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            $revenues[$key] = isset($row['value']) ? (float)$row['value'] : 0.0;
        } else {
            error_log('Staff Dashboard: failed to compute ' . $key . ' - ' . mysqli_error($conn));
        }
    }

    return $revenues;
}

function fetchTransactionsByPeriod(mysqli $conn = null): array {
    $defaults = [
        'today_transactions' => 0,
        'week_transactions' => 0,
        'month_transactions' => 0,
        'year_transactions' => 0,
    ];

    if (!$conn) {
        return $defaults;
    }

    $queries = [
        'today_transactions' => "SELECT COUNT(DISTINCT sale_id) AS value FROM sales WHERE DATE(created_at) = CURDATE()",
        'week_transactions' => "SELECT COUNT(DISTINCT sale_id) AS value FROM sales WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)",
        'month_transactions' => "SELECT COUNT(DISTINCT sale_id) AS value FROM sales WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())",
        'year_transactions' => "SELECT COUNT(DISTINCT sale_id) AS value FROM sales WHERE YEAR(created_at) = YEAR(CURDATE())",
    ];

    $counts = $defaults;

    foreach ($queries as $key => $sql) {
        $result = @mysqli_query($conn, $sql);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            $counts[$key] = isset($row['value']) ? (int)$row['value'] : 0;
    } else {
            error_log('Staff Dashboard: failed to compute ' . $key . ' - ' . mysqli_error($conn));
        }
    }

    return $counts;
}

function fetchExpiredProductsFallback(mysqli $conn = null, int $default = 0): int {
    if (!$conn) {
        return $default;
    }

    $query = "SELECT COUNT(DISTINCT CONCAT(product_id, '-', batch_no)) AS expired_count FROM deliveries WHERE expiry_date < CURDATE() AND status = 'Active'";
    $result = @mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row && isset($row['expired_count'])) {
            return (int)$row['expired_count'];
        }
    }

    return $default;
}

function fetchPackageProductsFallback(mysqli $conn = null, float $default = 0.0): float {
    if (!$conn) {
        return $default;
    }

    $query = "SELECT COALESCE(SUM(quantity_packages_balance), 0) AS total_packages FROM storage_inventory";
    $result = @mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row && isset($row['total_packages'])) {
            return (float)$row['total_packages'];
        }
    }

    return $default;
}

function checkViewExists($conn, $viewName) {
    $result = @mysqli_query($conn, "SHOW TABLES LIKE '$viewName'");
    $exists = $result && mysqli_num_rows($result) > 0;
    if ($result) {
        mysqli_free_result($result);
    }
    return $exists;
}

function fetchLowStockAlertsFallback(mysqli $conn = null, int $default = 0): int {
    if (!$conn) {
        return $default;
    }

    $productThresholdQuery = "SELECT product_threshold, COUNT(*) as count FROM products WHERE product_threshold > 0 GROUP BY product_threshold ORDER BY count DESC LIMIT 1";
    $productThresholdRes = @mysqli_query($conn, $productThresholdQuery);
    $defaultProductThreshold = 10;
    if ($productThresholdRes && mysqli_num_rows($productThresholdRes) > 0) {
        $productRow = mysqli_fetch_assoc($productThresholdRes);
        $defaultProductThreshold = (int)($productRow['product_threshold'] ?? 10);
    }
    if ($productThresholdRes) {
        mysqli_free_result($productThresholdRes);
    }

    $inventoryTotalsSubquery = "
        SELECT 
            p.product_id,
            COALESCE(ph.pharmacy_units, 0) AS pharmacy_units,
            COALESCE(st.storage_units, 0) AS storage_units,
            (COALESCE(ph.pharmacy_units, 0) + COALESCE(st.storage_units, 0)) AS total_units,
            CASE 
                WHEN COALESCE(p.product_threshold, 0) > 0 THEN p.product_threshold
                ELSE $defaultProductThreshold
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
    ";

    $nearestExpirySubquery = "
        SELECT product_id, MIN(expiry_date) AS nearest_expiry
        FROM deliveries
        WHERE status = 'Active'
        GROUP BY product_id
    ";

    $query = "
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

    $result = @mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        if ($row && isset($row['low_count'])) {
            return (int)$row['low_count'];
        }
    }

    return $default;
}

function fetchLowStockAlerts(mysqli $conn = null, int $default = 0): int {
    if (!$conn) {
        return $default;
    }

    if (checkViewExists($conn, 'view_stock_report')) {
        $query = "
            SELECT COUNT(*) AS low_count
            FROM view_stock_report
            WHERE LOWER(COALESCE(status, '')) = 'low stock'
              AND COALESCE(total_stock, 0) > 0
              AND (
                    expiry_info IS NULL
                 OR expiry_info = ''
                 OR LOWER(expiry_info) NOT LIKE '%expired%'
              )";
        $result = @mysqli_query($conn, $query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            if ($row && isset($row['low_count'])) {
                return (int)$row['low_count'];
            }
        }
    }

    return fetchLowStockAlertsFallback($conn, $default);
}

function fetchExpiringAlertsFallback(mysqli $conn = null, int $default = 0): int {
    if (!$conn) {
        return $default;
    }

    $defaultThreshold = fetchExpiryThreshold($conn, 30);

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

$metrics = fetchDashboardSummary($conn ?? null);
if (empty($metrics['total_expired_products'])) {
    $metrics['total_expired_products'] = fetchExpiredProductsFallback($conn ?? null);
}
if (empty($metrics['total_package_products'])) {
    $metrics['total_package_products'] = fetchPackageProductsFallback($conn ?? null);
}
$metrics['total_expiring_alerts'] = fetchExpiringAlertsFallback($conn ?? null, (int)($metrics['total_expiring_alerts'] ?? 0));
$metrics['total_low_stock_alerts'] = fetchLowStockAlerts($conn ?? null, (int)($metrics['total_low_stock_alerts'] ?? 0));
$expiryThresholdDays = fetchExpiryThreshold($conn ?? null);
$revenueByPeriod = fetchRevenueByPeriod($conn ?? null);
$transactionsByPeriod = fetchTransactionsByPeriod($conn ?? null);

$validPeriods = ['day', 'week', 'month', 'year'];
$periodLabels = [
    'day' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'year' => 'This Year',
];

$selectedPeriod = isset($_GET['period']) && in_array($_GET['period'], $validPeriods, true) ? $_GET['period'] : 'day';
$selectedTransactionsPeriod = isset($_GET['transactions_period']) && in_array($_GET['transactions_period'], $validPeriods, true) ? $_GET['transactions_period'] : 'day';

$revenue = match ($selectedPeriod) {
    'day' => $revenueByPeriod['today_revenue'],
    'week' => $revenueByPeriod['week_revenue'],
    'month' => $revenueByPeriod['month_revenue'],
    'year' => $revenueByPeriod['year_revenue'],
    default => $revenueByPeriod['today_revenue'],
};

$transactionsCount = match ($selectedTransactionsPeriod) {
    'day' => $transactionsByPeriod['today_transactions'],
    'week' => $transactionsByPeriod['week_transactions'],
    'month' => $transactionsByPeriod['month_transactions'],
    'year' => $transactionsByPeriod['year_transactions'],
    default => $transactionsByPeriod['today_transactions'],
};
?>

    <main class="w-full overflow-y-auto p-6 md:pl-20 bg-gray-50 min-h-screen">
        <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        }
        .metric-card {
            position: relative;
            border-radius: 1.75rem;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.9));
            border: 1px solid rgba(226, 232, 240, 0.7);
            padding: 1.75rem 1.85rem;
            box-shadow: 0 25px 45px -20px rgba(15, 23, 42, 0.25);
            backdrop-filter: blur(6px);
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 190px;
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
        .metric-icon {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-right: 0.8rem;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
        }
        .metric-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.35rem;
            gap: 0.9rem;
        }
        .metric-header form {
            margin-left: auto;
            display: flex;
            align-items: center;
        }
        .metric-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }
        .metric-title > div {
            display: flex;
            flex-direction: column;
            gap: 0.12rem;
            min-width: 0;
        }
        .dashboard-grid {
            display: grid;
            gap: 1.6rem;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            align-items: stretch;
        }
        @media (min-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (min-width: 1280px) {
            .dashboard-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        </style>

        <div class="mx-auto max-w-7xl">
        <header class="mb-10">
            <h1 class="text-3xl font-bold text-gray-900">Staff Dashboard Summary</h1>
            <p class="text-gray-500 mt-2">Snapshot of today’s operational statistics.</p>
        </header>

        <section class="dashboard-grid">
            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-title">
                        <span class="metric-icon" style="background: linear-gradient(135deg, #22d3ee 0%, #0ea5e9 100%);">🛒</span>
                <div>
                            <h2 class="text-sm uppercase tracking-wide text-gray-500">Total Items Sold Today</h2>
                            <p class="text-xs text-gray-400">Real-time sales movement</p>
                        </div>
                    </div>
                    <span class="metric-badge">Live</span>
                </div>
                <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($metrics['total_items_sold_today']); ?></p>
                <p class="text-xs text-gray-500">Total quantity processed through sales for the day.</p>
            </article>

            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-title">
                        <span class="metric-icon" style="background: linear-gradient(135deg, #fbbf24 0%, #f97316 100%);">⏳</span>
                        <div>
                            <h2 class="text-sm uppercase tracking-wide text-gray-500">Total Expiring Alerts</h2>
                            <p class="text-xs text-gray-400">Expiry monitoring</p>
                        </div>
                    </div>
                    <span class="metric-badge"><?= $expiryThresholdDays; ?> days</span>
                </div>
                <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($metrics['total_expiring_alerts']); ?></p>
                <p class="text-xs text-gray-500">Products approaching expiry within the monitoring window.</p>
            </article>

            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-title">
                        <span class="metric-icon" style="background: linear-gradient(135deg, #fca5a5 0%, #f87171 100%);">⚠️</span>
                        <div>
                            <h2 class="text-sm uppercase tracking-wide text-gray-500">Expired Products</h2>
                            <p class="text-xs text-gray-400">Batches requiring attention</p>
                        </div>
                    </div>
                    <span class="metric-badge bg-red-100 text-red-700">Expired</span>
                </div>
                <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($metrics['total_expired_products']); ?></p>
                <p class="text-xs text-gray-500">Distinct product batches marked as expired and still active in inventory.</p>
            </article>

            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-title">
                        <span class="metric-icon" style="background: linear-gradient(135deg, #bbf7d0 0%, #4ade80 100%);">📦</span>
                        <div>
                            <h2 class="text-sm uppercase tracking-wide text-gray-500">Package Products</h2>
                            <p class="text-xs text-gray-400">Storage package balance</p>
                        </div>
                    </div>
                    <span class="metric-badge">Packages</span>
                </div>
                <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($metrics['total_package_products']); ?></p>
                <p class="text-xs text-gray-500">Total packages remaining across storage inventory balances.</p>
            </article>

            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-title">
                        <span class="metric-icon" style="background: linear-gradient(135deg, #fbcfe8 0%, #ec4899 100%);">📉</span>
                        <div>
                            <h2 class="text-sm uppercase tracking-wide text-gray-500">Total Low Stock Alerts</h2>
                            <p class="text-xs text-gray-400">Below threshold deliveries</p>
                        </div>
                    </div>
                    <span class="metric-badge">Threshold</span>
                </div>
                <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($metrics['total_low_stock_alerts']); ?></p>
                <p class="text-xs text-gray-500">Deliveries with remaining stock below configured threshold.</p>
            </article>

            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-title">
                        <span class="metric-icon" style="background: linear-gradient(135deg, #bae6fd 0%, #2563eb 100%);">✅</span>
                            <div>
                            <h2 class="text-sm uppercase tracking-wide text-gray-500">Total Products Available</h2>
                            <p class="text-xs text-gray-400">Ready for dispensing</p>
                        </div>
                    </div>
                    <span class="metric-badge">Active</span>
                        </div>
                <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($metrics['total_products_available']); ?></p>
                <p class="text-xs text-gray-500">Distinct products currently available for sale.</p>
            </article>

            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-title">
                        <span class="metric-icon" style="background: linear-gradient(135deg, #fde68a 0%, #f59e0b 100%);">💰</span>
                        <div>
                            <h2 class="text-sm uppercase tracking-wide text-gray-500">Sales Revenue</h2>
                            <p class="text-xs text-gray-400">Financial snapshot</p>
                        </div>
                    </div>
                    <form method="get" class="flex items-center gap-2">
                        <label for="periodSelectRevenue" class="sr-only">Revenue Period</label>
                        <select id="periodSelectRevenue" name="period" class="bg-gray-50 border border-gray-200 text-xs font-semibold text-gray-600 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-teal-500" onchange="this.form.submit()">
<?php foreach ($validPeriods as $period): ?>
                            <option value="<?= htmlspecialchars($period); ?>" <?= $period === $selectedPeriod ? 'selected' : ''; ?>><?= htmlspecialchars($periodLabels[$period]); ?></option>
<?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <p class="text-4xl font-bold text-gray-900 mb-2">₱<?= number_format($revenue, 2); ?></p>
                <p class="text-xs text-gray-500">Total revenue captured for <?= strtolower($periodLabels[$selectedPeriod]); ?>.</p>
            </article>

            <article class="metric-card">
                <div class="metric-header">
                    <div class="metric-title">
                        <span class="metric-icon" style="background: linear-gradient(135deg, #c7d2fe 0%, #6366f1 100%);">🧾</span>
                            <div>
                            <h2 class="text-sm uppercase tracking-wide text-gray-500">Sales Transactions</h2>
                            <p class="text-xs text-gray-400">Receipts issued</p>
                        </div>
                    </div>
                    <form method="get" class="flex items-center gap-2">
                        <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod); ?>">
                        <label for="periodSelectTransactions" class="sr-only">Transactions Period</label>
                        <select id="periodSelectTransactions" name="transactions_period" class="bg-gray-50 border border-gray-200 text-xs font-semibold text-gray-600 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-teal-500" onchange="this.form.submit()">
<?php foreach ($validPeriods as $period): ?>
                            <option value="<?= htmlspecialchars($period); ?>" <?= $period === $selectedTransactionsPeriod ? 'selected' : ''; ?>><?= htmlspecialchars($periodLabels[$period]); ?></option>
<?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <p class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($transactionsCount, 0); ?></p>
                <p class="text-xs text-gray-500">Number of unique sales transactions for <?= strtolower($periodLabels[$selectedTransactionsPeriod]); ?>.</p>
            </article>
            </section>
        </div>
    </main>

    </body>
    </html>

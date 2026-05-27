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

include '../../includes/adminHeader.php' ?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>
<script src="../../Assets/Javascript/chart.umd.min.js"></script>
<script>
if (typeof window.Chart === 'undefined') {
  window.Chart = function() { return { destroy: function(){}, update: function(){} }; };
}
</script>
<?php
// Database connection
require_once '../../dbConnection/connection.php';

// Get date range from URL parameters or default to current month
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Sanitize dates for SQL usage
$startDateEsc = mysqli_real_escape_string($conn, $startDate);
$endDateEsc = mysqli_real_escape_string($conn, $endDate);

// Sales Summary Data
$salesSummary = [
    'total_revenue' => 0,
    'total_transactions' => 0,
    'average_transaction' => 0,
    'total_items_sold' => 0
];

// Check if view_sales_analytics exists
$hasSalesAnalyticsView = false;
$analyticsViewCheck = mysqli_query($conn, "SHOW TABLES LIKE 'view_sales_analytics'");
if ($analyticsViewCheck && mysqli_num_rows($analyticsViewCheck) > 0) {
    $hasSalesAnalyticsView = true;
}
if ($analyticsViewCheck) {
    mysqli_free_result($analyticsViewCheck);
}

if ($hasSalesAnalyticsView) {
    // Get total revenue - sum distinct sale amounts (one row per sale_id)
    $resRevenue = mysqli_query($conn, "SELECT 
        SUM(total_amount) as total_revenue
        FROM (
            SELECT DISTINCT sale_id, total_amount
            FROM view_sales_analytics 
            WHERE DATE(sale_date) BETWEEN '$startDateEsc' AND '$endDateEsc'
        ) as distinct_sales");
    
    // Get transaction count and items sold
    $res = mysqli_query($conn, "SELECT 
        COUNT(DISTINCT sale_id) as total_transactions,
        SUM(COALESCE(quantity_packages, 0) + COALESCE(quantity_units, 0)) as total_items_sold
        FROM view_sales_analytics 
        WHERE DATE(sale_date) BETWEEN '$startDateEsc' AND '$endDateEsc'");
    
    $totalRevenue = 0;
    if ($resRevenue && ($rowRevenue = mysqli_fetch_assoc($resRevenue))) {
        $totalRevenue = (float)($rowRevenue['total_revenue'] ?? 0);
    }
    
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $totalTransactions = (int)($row['total_transactions'] ?? 0);
        
        $salesSummary['total_revenue'] = $totalRevenue;
        $salesSummary['total_transactions'] = $totalTransactions;
        $salesSummary['average_transaction'] = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;
        $salesSummary['total_items_sold'] = (int)($row['total_items_sold'] ?? 0);
    }
    
    // Get daily sales data for chart - need to get distinct sales per day
    $dailySales = [];
    $res2 = mysqli_query($conn, "SELECT 
        DATE(sale_date) as sale_date,
        SUM(sale_total) as daily_revenue,
        COUNT(DISTINCT sale_id) as daily_transactions
        FROM (
            SELECT 
                DATE(sale_date) as sale_date,
                sale_id,
                MAX(total_amount) as sale_total
            FROM view_sales_analytics 
            WHERE DATE(sale_date) BETWEEN '$startDateEsc' AND '$endDateEsc'
            GROUP BY DATE(sale_date), sale_id
        ) as daily_sales
        GROUP BY DATE(sale_date)
        ORDER BY sale_date ASC");
    
    if ($res2) {
        while ($row2 = mysqli_fetch_assoc($res2)) {
            $dailySales[] = $row2;
        }
    }
    
    // Get top selling products
    $topProducts = [];
    $res3 = mysqli_query($conn, "SELECT 
        brand_name,
        generic_name,
        SUM(COALESCE(quantity_packages, 0) + COALESCE(quantity_units, 0)) as total_quantity,
        SUM(subtotal) as total_revenue
        FROM view_sales_analytics 
        WHERE DATE(sale_date) BETWEEN '$startDateEsc' AND '$endDateEsc'
        GROUP BY brand_name, generic_name
        ORDER BY total_quantity DESC
        LIMIT 10");
    
    if ($res3) {
        while ($row3 = mysqli_fetch_assoc($res3)) {
            $topProducts[] = $row3;
        }
    }
    
    // Get sales by cashier
    $salesByCashier = [];
    $res4 = mysqli_query($conn, "SELECT 
        cashier_name,
        COUNT(DISTINCT sale_id) as transaction_count,
        SUM(total_amount) as total_revenue
        FROM view_sales_analytics 
        WHERE DATE(sale_date) BETWEEN '$startDateEsc' AND '$endDateEsc'
        GROUP BY cashier_name
        ORDER BY total_revenue DESC");
    
    if ($res4) {
        while ($row4 = mysqli_fetch_assoc($res4)) {
            $salesByCashier[] = $row4;
        }
    }
} else {
    // Fallback to basic sales table
    $res = mysqli_query($conn, "SELECT 
        SUM(total_amount) as total_revenue,
        COUNT(sale_id) as total_transactions,
        AVG(total_amount) as average_transaction
        FROM sales 
        WHERE DATE(created_at) BETWEEN '$startDateEsc' AND '$endDateEsc'");
    
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $salesSummary['total_revenue'] = (float)($row['total_revenue'] ?? 0);
        $salesSummary['total_transactions'] = (int)($row['total_transactions'] ?? 0);
        $salesSummary['average_transaction'] = (float)($row['average_transaction'] ?? 0);
    }

    // Total items sold from sales_details
    $itemsResult = mysqli_query($conn, "SELECT 
        SUM(COALESCE(sd.quantity_packages, 0) + COALESCE(sd.quantity_units, 0)) as total_items_sold
        FROM sales_details sd
        INNER JOIN sales s ON sd.sale_id = s.sale_id
        WHERE DATE(s.created_at) BETWEEN '$startDateEsc' AND '$endDateEsc'");
    if ($itemsResult && ($itemsRow = mysqli_fetch_assoc($itemsResult))) {
        $salesSummary['total_items_sold'] = (int)($itemsRow['total_items_sold'] ?? 0);
    }
    
    // Daily sales using sales table
    $dailySales = [];
    $dailyResult = mysqli_query($conn, "SELECT 
        DATE(created_at) as sale_date,
        SUM(total_amount) as daily_revenue,
        COUNT(*) as daily_transactions
        FROM sales
        WHERE DATE(created_at) BETWEEN '$startDateEsc' AND '$endDateEsc'
        GROUP BY DATE(created_at)
        ORDER BY sale_date ASC");
    if ($dailyResult) {
        while ($rowDaily = mysqli_fetch_assoc($dailyResult)) {
            $dailySales[] = $rowDaily;
        }
    }

    // Top products using sales_details and products
    $topProducts = [];
    $topResult = mysqli_query($conn, "SELECT 
        COALESCE(p.brand_name, p.generic_name, 'Unknown Product') AS brand_name,
        COALESCE(p.generic_name, '') AS generic_name,
        SUM(COALESCE(sd.quantity_packages, 0) + COALESCE(sd.quantity_units, 0)) AS total_quantity,
        SUM(COALESCE(sd.subtotal, 0)) AS total_revenue
        FROM sales_details sd
        INNER JOIN sales s ON sd.sale_id = s.sale_id
        LEFT JOIN deliveries d ON sd.delivery_id = d.delivery_id
        LEFT JOIN products p ON d.product_id = p.product_id
        WHERE DATE(s.created_at) BETWEEN '$startDateEsc' AND '$endDateEsc'
        GROUP BY brand_name, generic_name
        ORDER BY total_quantity DESC
        LIMIT 10");
    if ($topResult) {
        while ($rowTop = mysqli_fetch_assoc($topResult)) {
            $topProducts[] = $rowTop;
        }
    }

    // Sales by cashier using sales table
    $salesByCashier = [];
    $cashierResult = mysqli_query($conn, "SELECT 
        COALESCE(u.username, 'Unknown Cashier') AS cashier_name,
        COUNT(s.sale_id) AS transaction_count,
        SUM(s.total_amount) AS total_revenue
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.user_id
        WHERE DATE(s.created_at) BETWEEN '$startDateEsc' AND '$endDateEsc'
        GROUP BY cashier_name
        ORDER BY total_revenue DESC");
    if ($cashierResult) {
        while ($rowCashier = mysqli_fetch_assoc($cashierResult)) {
            $salesByCashier[] = $rowCashier;
        }
    }
}
?>

<!-- Modern Sales Summary -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gradient-to-br from-gray-50 to-blue-50/30 min-h-screen">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white rounded-xl shadow-sm border border-blue-100">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Sales Analytics</h1>
                        </div>
                    </div>
                </div>
                
                <!-- Date Range Controls -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex gap-2">
                        <div class="relative">
                            <input type="date" id="startDate" value="<?= $startDate ?>" class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200 w-full">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <span class="text-gray-500 text-sm hidden sm:flex items-center">to</span>
                        <div class="relative">
                            <input type="date" id="endDate" value="<?= $endDate ?>" class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200 w-full">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <button onclick="updateDateRange()" class="px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-200 text-sm font-semibold flex items-center gap-2 shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Update Range
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards - Modern Design -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Revenue -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">₱<?= number_format($salesSummary['total_revenue'], 2) ?></p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-xl group-hover:bg-green-100 transition-colors">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Transactions -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Transactions</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($salesSummary['total_transactions']) ?></p>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-xl group-hover:bg-blue-100 transition-colors">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Average Transaction -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Avg. Transaction</p>
                        <p class="text-2xl font-bold text-gray-900">₱<?= number_format($salesSummary['average_transaction'], 2) ?></p>
                    </div>
                    <div class="p-3 bg-purple-50 rounded-xl group-hover:bg-purple-100 transition-colors">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Items Sold -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Items Sold</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($salesSummary['total_items_sold']) ?></p>
                    </div>
                    <div class="p-3 bg-orange-50 rounded-xl group-hover:bg-orange-100 transition-colors">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
            <!-- Daily Sales Chart -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Daily Sales Trend</h3>
                    <div class="flex items-center gap-3 text-xs">
                        <div class="flex items-center gap-1">
                            <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                            <span>Revenue</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span>Transactions</span>
                        </div>
                    </div>
                </div>
                <div class="h-80">
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>

            <!-- Top Products Chart -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Top Selling Products</h3>
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <div class="w-2 h-2 bg-purple-500 rounded-full"></div>
                        Quantity Sold
                    </div>
                </div>
                <div class="h-80">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Data Tables Section -->
        <div class="grid grid-cols-1 gap-8">
            <!-- Sales by Cashier -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-50 rounded-lg">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Sales by Cashier</h3>
                            <p class="text-sm text-gray-500">Performance by team members</p>
                        </div>
                    </div>
                    <span class="bg-green-100 text-green-800 text-sm font-medium px-3 py-1 rounded-full">
                        <?= count($salesByCashier) ?> cashiers
                    </span>
                </div>
                
                <div class="space-y-4">
                    <?php if (empty($salesByCashier)): ?>
                        <div class="text-center py-8 text-gray-400">
                            <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <p class="font-medium text-gray-600">No Cashier Data</p>
                            <p class="text-sm">No sales recorded by cashiers</p>
                        </div>
                    <?php else: 
                        foreach ($salesByCashier as $cashier): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50/50 rounded-xl border border-gray-100 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-white text-sm font-medium">
                                        <?= strtoupper(substr($cashier['cashier_name'], 0, 1)) ?>
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars($cashier['cashier_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= number_format($cashier['transaction_count']) ?> transactions</p>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0 ml-3">
                                <p class="font-semibold text-gray-900 text-sm whitespace-nowrap">₱<?= number_format($cashier['total_revenue'], 2) ?></p>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Hidden element to store PHP data for JavaScript -->
<div id="salesData" 
     data-daily-sales='<?= json_encode($dailySales ?? []) ?>'
     data-top-products='<?= json_encode(array_slice($topProducts ?? [], 0, 5)) ?>'
     style="display: none;">
</div>

<!-- Include external JavaScript file -->
<script src="../../Assets/Javascript/salesSummary.js"></script>

<script src="../../Assets/Javascript/errorAndsuccessHander.js"></script>
<script>
// Initialize modal handlers (no URL parameters for sales summary)
initModalHandlers({}, {});
</script>
</body>
</html>
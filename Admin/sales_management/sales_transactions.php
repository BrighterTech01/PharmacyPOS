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
    header('Location: ../../auth/login.php?error=unauthorized');
    exit();
}

// Include database connection
require_once '../../dbConnection/connection.php';

// Get date range from URL parameters or default to current month
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Check if view_sales_analytics exists (preferred view)
$hasSalesAnalyticsView = false;
$analyticsViewCheck = mysqli_query($conn, "SHOW TABLES LIKE 'view_sales_analytics'");
if ($analyticsViewCheck && mysqli_num_rows($analyticsViewCheck) > 0) {
    $hasSalesAnalyticsView = true;
}
if ($analyticsViewCheck) {
    mysqli_free_result($analyticsViewCheck);
}

// Fetch sales transactions from database
$sales = [];
if ($hasSalesAnalyticsView) {
  // Use view_sales_analytics which should have the proper structure
  $query = "SELECT 
              sale_id, 
              cashier_name, 
              total_amount, 
              sale_date,
              COUNT(DISTINCT detail_id) as item_count,
              SUM(COALESCE(quantity_packages, 0) + COALESCE(quantity_units, 0)) as total_quantity,
              GROUP_CONCAT(DISTINCT brand_name ORDER BY brand_name SEPARATOR ', ') as products
            FROM view_sales_analytics
            WHERE DATE(sale_date) BETWEEN '$startDate' AND '$endDate'
            GROUP BY sale_id, cashier_name, total_amount, sale_date
            ORDER BY sale_date DESC";
} else {
  // Use sales_details table directly
  $query = "SELECT 
              s.sale_id, 
              s.user_id, 
              u.username, 
              s.total_amount,
              COALESCE(s.final_amount, s.total_amount, 0) as final_amount,
              s.discount_type,
              s.discount_amount,
              s.created_at,
              COUNT(sd.detail_id) as item_count,
              SUM(COALESCE(sd.quantity_packages, 0) + COALESCE(sd.quantity_units, 0)) as total_quantity,
              GROUP_CONCAT(DISTINCT COALESCE(p.brand_name, p.generic_name, 'Unknown Product') ORDER BY p.brand_name SEPARATOR ', ') as products
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN sales_details sd ON s.sale_id = sd.sale_id
            LEFT JOIN deliveries d ON sd.delivery_id = d.delivery_id
            LEFT JOIN products p ON d.product_id = p.product_id
            WHERE DATE(s.created_at) BETWEEN '$startDate' AND '$endDate'
            GROUP BY s.sale_id, s.user_id, u.username, s.total_amount, s.final_amount, s.discount_type, s.discount_amount, s.created_at
            ORDER BY s.created_at DESC";
}

$result = mysqli_query($conn, $query);

// Check if query was successful and has results
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $sales[] = $row;
    }
}

// Helper function to convert currency string to float
function parseAmount($value) {
    if (is_numeric($value)) {
        return (float)$value;
    }
    // Remove currency symbols and convert to float
    $clean = preg_replace('/[^0-9.\-]/', '', (string)$value);
    return $clean === '' ? 0.0 : (float)$clean;
}

// Normalize sales data
foreach ($sales as &$sale) {
    $sale['total_amount'] = parseAmount($sale['total_amount']);
    // Use final_amount if available, otherwise use total_amount
    if (isset($sale['final_amount'])) {
        $sale['total_amount'] = parseAmount($sale['final_amount']);
    }
    // Map sale_date to created_at for consistency
    if (isset($sale['sale_date'])) {
        $sale['created_at'] = $sale['sale_date'];
    }
    // Map cashier_name to username for consistency
    if (isset($sale['cashier_name'])) {
        $sale['username'] = $sale['cashier_name'];
    }
    // Ensure products field exists
    if (!isset($sale['products'])) {
        $sale['products'] = 'No products';
    }
    // Ensure discount fields exist
    if (!isset($sale['discount_type'])) {
        $sale['discount_type'] = 'None';
    }
    if (!isset($sale['discount_amount'])) {
        $sale['discount_amount'] = 0;
    }
}
unset($sale);

// Calculate statistics
$totalSales = count($sales);
$totalRevenue = array_sum(array_column($sales, 'total_amount'));
$todaySales = count(array_filter($sales, function($s) {
    return date('Y-m-d', strtotime($s['created_at'])) === date('Y-m-d');
}));
$todayRevenue = array_sum(array_column(array_filter($sales, function($s) {
    return date('Y-m-d', strtotime($s['created_at'])) === date('Y-m-d');
}), 'total_amount'));

// Get sales by status (completed, pending, etc.) - currently not used but kept for future use
$salesByStatus = [];
if ($hasSalesAnalyticsView) {
    $statusQuery = "SELECT 
                      'Completed' as status,
                      COUNT(DISTINCT sale_id) as count,
                      SUM(total_amount) as revenue
                    FROM view_sales_analytics 
                    WHERE DATE(sale_date) BETWEEN '$startDate' AND '$endDate'";
    $statusResult = mysqli_query($conn, $statusQuery);
    if ($statusResult && ($statusRow = mysqli_fetch_assoc($statusResult))) {
        $salesByStatus[] = $statusRow;
    }
}

include '../../includes/adminHeader.php';
?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>

<!-- Modern Sales Transactions -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gradient-to-br from-gray-50 to-blue-50/30 min-h-screen">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white rounded-xl shadow-sm border border-blue-100">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Sales Transactions</h1>
                            <p class="text-sm text-gray-600 mt-1">Manage and review all sales transactions</p>
                        </div>
                    </div>
                </div>
                
                <!-- Action Controls -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                        <div class="relative w-full sm:w-48 md:w-56">
                            <input type="date" id="startDate" value="<?= $startDate ?>" class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200 w-full">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <span class="text-gray-500 text-sm flex items-center justify-center sm:px-1">to</span>
                        <div class="relative w-full sm:w-48 md:w-56">
                            <input type="date" id="endDate" value="<?= $endDate ?>" class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200 w-full">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <button onclick="printFullReport()" id="printFullReportBtn" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all text-sm font-semibold shadow-sm hover:shadow-md">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards - Modern Design -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Transactions -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Transactions</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($totalSales) ?></p>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-xl group-hover:bg-blue-100 transition-colors">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">₱<?= number_format($totalRevenue, 2) ?></p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-xl group-hover:bg-green-100 transition-colors">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Today's Transactions -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Today's Transactions</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($todaySales) ?></p>
                    </div>
                    <div class="p-3 bg-purple-50 rounded-xl group-hover:bg-purple-100 transition-colors">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Today's Revenue -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Today's Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">₱<?= number_format($todayRevenue, 2) ?></p>
                    </div>
                    <div class="p-3 bg-orange-50 rounded-xl group-hover:bg-orange-100 transition-colors">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg p-6 border border-white/50">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-6 gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Transaction History</h3>
                        <p class="text-sm text-gray-500">Complete history of all sales transactions</p>
                    </div>
                </div>
                
                <!-- Selection Actions -->
                <div class="flex items-center gap-4">
                    <span id="selectedCount" class="text-sm text-gray-600 hidden">
                        <span id="selectedNumber">0</span> selected
                    </span>
                    
                    <!-- Filters -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="relative">
                            <select id="cashierFilter" class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200 w-full">
                                <option value="">All Cashiers</option>
                                <?php
                                $uniqueCashiers = array_unique(array_column($sales, 'username'));
                                foreach ($uniqueCashiers as $cashier): ?>
                                    <option value="<?= htmlspecialchars($cashier) ?>"><?= htmlspecialchars($cashier) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="mb-6">
                <div class="relative">
                    <input 
                        type="text" 
                        id="searchInput" 
                        placeholder="Search by sale ID, cashier, or products..." 
                        class="w-full pl-12 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200"
                        onkeyup="filterSales()"
                    >
                    <svg class="absolute left-4 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Transactions Table -->
            <style>
                .transactions-table-scroll {
                    max-height: 640px; /* Approximately 10 table rows */
                    overflow-y: auto;
                }
                .transactions-table-scroll::-webkit-scrollbar {
                    width: 8px;
                }
                .transactions-table-scroll::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 10px;
                }
                .transactions-table-scroll::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 10px;
                }
                .transactions-table-scroll::-webkit-scrollbar-thumb:hover {
                    background: #555;
                }
                .expand-btn {
                    transition: all 0.2s ease;
                }
                .expand-btn svg {
                    transition: transform 0.2s ease;
                }
                .expand-btn:hover {
                    color: #2563eb;
                }
                #salesTableBody tr[data-sale-id] {
                    cursor: pointer;
                }
                #salesTableBody tr[data-sale-id]:hover {
                    background-color: #f9fafb;
                }
            </style>
            <div class="overflow-hidden rounded-xl border border-gray-200">
                <div class="transactions-table-scroll">
                <table class="w-full min-w-full">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                        <tr>
                            <th class="px-2 sm:px-3 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap w-8"></th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Time</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Cashier</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Products</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Items</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Amount</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Discount</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Total</th>
                        </tr>
                    </thead>
                    <tbody id="salesTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if (count($sales) > 0): ?>
                            <?php foreach ($sales as $sale): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors" data-sale-id="<?= $sale['sale_id'] ?>" data-amount="<?= htmlspecialchars($sale['total_amount']) ?>" data-items="<?= htmlspecialchars($sale['item_count'] ?? 0) ?>" data-total-quantity="<?= htmlspecialchars($sale['total_quantity'] ?? 0) ?>">
                                    <td class="px-2 sm:px-3 py-2 sm:py-3 whitespace-nowrap">
                                        <button onclick="toggleSaleDetails(<?= $sale['sale_id'] ?>, this)" class="expand-btn text-gray-400 hover:text-gray-600 transition-colors focus:outline-none">
                                            <svg class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-xs sm:text-sm text-gray-600">
                                        <?= date('m/d/y, g:i A', strtotime($sale['created_at'])) ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-xs sm:text-sm font-semibold text-gray-900"><?= htmlspecialchars($sale['username']) ?></div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3">
                                        <div class="text-xs sm:text-sm font-medium text-gray-900 max-w-xs truncate" title="<?= htmlspecialchars($sale['products'] ?? 'No products') ?>">
                                            <?= htmlspecialchars($sale['products'] ?? 'No products') ?>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                        <span class="text-xs sm:text-sm text-gray-700"><?= $sale['item_count'] ?></span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <div class="text-xs sm:text-sm font-semibold text-gray-900">₱<?= number_format($sale['total_amount'], 2) ?></div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                        <?php 
                                        $discountAmount = floatval($sale['discount_amount'] ?? 0);
                                        $discountType = $sale['discount_type'] ?? 'None';
                                        if ($discountAmount > 0 && $discountType !== 'None'): ?>
                                            <span class="text-xs font-semibold text-red-600">-₱<?= number_format($discountAmount, 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">₱0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <div class="text-xs sm:text-sm font-bold text-gray-900">₱<?= number_format($sale['total_amount'], 2) ?></div>
                                    </td>
                                </tr>
                                <!-- Expanded Row for Item Details -->
                                <tr id="details-<?= $sale['sale_id'] ?>" class="hidden bg-gray-50/50">
                                    <td colspan="8" class="px-0 py-0 border-t border-gray-200">
                                        <div class="px-6 py-4">
                                            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                                                <div id="items-content-<?= $sale['sale_id'] ?>" class="p-0">
                                                    <div class="flex items-center justify-center py-8">
                                                        <div class="text-center">
                                                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
                                                            <p class="text-xs text-gray-500">Loading items...</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                            </svg>
                                        </div>
                                        <p class="font-semibold text-lg text-gray-600 mb-1">No Transactions Found</p>
                                        <p class="text-sm">No sales transactions found for the selected date range.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Sale Details Modal -->
<div id="saleDetailsModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm hidden items-center justify-center z-50 p-4 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-2xl w-[70vw] h-[70vh] mx-auto overflow-hidden border border-gray-300 flex flex-col">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 flex-shrink-0">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-white">Transaction Details</h3>
                    <p class="text-xs text-blue-100 mt-0.5" id="modalReceiptNumber">Loading...</p>
                </div>
                <button onclick="closeSaleDetailsModal()" class="text-white hover:text-gray-200 transition-colors p-1 hover:bg-white/20 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Modal Content -->
        <div id="saleDetailsContent" class="p-3 bg-gray-50 flex-1 overflow-y-auto">
            <!-- Loading State -->
            <div class="flex items-center justify-center py-12">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-500 font-medium">Loading transaction details...</p>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="bg-white px-3 py-2 flex justify-end gap-2 border-t border-gray-200 flex-shrink-0">
            <button onclick="printSaleReceipt()" id="printReceiptBtn" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 transition-all text-xs font-semibold shadow-sm hidden flex items-center">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Print
            </button>
            <button onclick="closeSaleDetailsModal()" class="px-3 py-1.5 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-all text-xs font-semibold shadow-sm">
                Close
            </button>
        </div>
    </div>
</div>

<script src="../../Assets/Javascript/salesTransaction.js?v=<?= time() ?>"></script>
<script src="../../Assets/Javascript/errorAndsuccessHander.js"></script>
<script>
// Initialize modal handlers (no URL parameters for sales transactions)
initModalHandlers({}, {});
</script>
</body>
</html>
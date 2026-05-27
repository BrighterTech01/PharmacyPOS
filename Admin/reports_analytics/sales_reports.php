<?php include '../../includes/adminHeader.php' ?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>
<?php
// Database connection
require_once '../../dbConnection/connection.php';

// Get date range and filters from URL parameters
// Default to showing ALL sales (no date filter)
$startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : '';
$cashierFilter = isset($_GET['cashier']) ? $_GET['cashier'] : '';

// Sales Summary Data
$salesSummary = [
    'total_revenue' => 0,
    'total_transactions' => 0,
    'average_transaction' => 0,
    'total_items_sold' => 0
];

// Get unique cashiers for filter dropdown
$cashiersQuery = "SELECT DISTINCT CONCAT(u.firstname, ' ', u.lastname) as cashier_name 
                  FROM sales s
                  INNER JOIN users u ON s.user_id = u.user_id
                  WHERE u.firstname IS NOT NULL AND u.lastname IS NOT NULL 
                  ORDER BY cashier_name";
$cashiersResult = mysqli_query($conn, $cashiersQuery);
$cashiers = [];
if ($cashiersResult) {
    while ($row = mysqli_fetch_assoc($cashiersResult)) {
        $cashiers[] = $row['cashier_name'];
    }
}

// Build WHERE clause - only apply date filter if dates are provided
$whereConditions = [];
if ($startDate && $endDate) {
    $whereConditions[] = "DATE(s.created_at) BETWEEN '$startDate' AND '$endDate'";
} elseif ($startDate) {
    $whereConditions[] = "DATE(s.created_at) >= '$startDate'";
} elseif ($endDate) {
    $whereConditions[] = "DATE(s.created_at) <= '$endDate'";
}

if ($cashierFilter) {
    $whereConditions[] = "CONCAT(u.firstname, ' ', u.lastname) = '" . mysqli_real_escape_string($conn, $cashierFilter) . "'";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get sales summary data
$summaryQuery = "SELECT 
    SUM(COALESCE(s.final_amount, s.total_amount, 0)) as total_revenue,
    COUNT(DISTINCT s.sale_id) as total_transactions,
    AVG(COALESCE(s.final_amount, s.total_amount, 0)) as average_transaction
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.user_id
    $whereClause";

$res = mysqli_query($conn, $summaryQuery);

if ($res && ($row = mysqli_fetch_assoc($res))) {
    $salesSummary['total_revenue'] = (float)($row['total_revenue'] ?? 0);
    $salesSummary['total_transactions'] = (int)($row['total_transactions'] ?? 0);
    $salesSummary['average_transaction'] = (float)($row['average_transaction'] ?? 0);
}

// Calculate total items sold from sales_details
$itemsQuery = "SELECT SUM(COALESCE(sd.quantity_packages, 0) + COALESCE(sd.quantity_units, 0)) as total_items
               FROM sales_details sd
               INNER JOIN sales s ON sd.sale_id = s.sale_id
               LEFT JOIN users u ON s.user_id = u.user_id
               $whereClause";
$itemsResult = mysqli_query($conn, $itemsQuery);
if ($itemsResult && ($itemsRow = mysqli_fetch_assoc($itemsResult))) {
    $salesSummary['total_items_sold'] = (int)($itemsRow['total_items'] ?? 0);
}

// Get detailed sales data
$detailedQuery = "SELECT 
    s.sale_id,
    s.receipt_number,
    s.created_at as sale_date,
    CONCAT(u.firstname, ' ', u.lastname) as cashier,
    s.payment_method,
    COALESCE(s.final_amount, s.total_amount, 0) as total_amount,
    s.discount_type,
    s.discount_amount,
    s.cash_recieving,
    s.change_amount,
    s.customer_name,
    p.brand_name,
    p.generic_name,
    p.strength,
    d.batch_no,
    d.expiry_date,
    sd.quantity_packages,
    sd.quantity_units,
    sd.unit_price,
    COALESCE(sd.final_subtotal, sd.subtotal, 0) as subtotal,
    sd.discount_type as item_discount_type,
    sd.discount_amount as item_discount_amount,
    ut.unit_name as sale_unit,
    DATEDIFF(d.expiry_date, CURDATE()) as days_before_expiry,
    CASE 
        WHEN d.expiry_date < CURDATE() THEN 'Expired'
        WHEN DATEDIFF(d.expiry_date, CURDATE()) <= 30 THEN 'Critical'
        ELSE 'Warning'
    END as expiry_status
    FROM sales s
    INNER JOIN sales_details sd ON s.sale_id = sd.sale_id
    LEFT JOIN users u ON s.user_id = u.user_id
    LEFT JOIN deliveries d ON sd.delivery_id = d.delivery_id
    LEFT JOIN products p ON d.product_id = p.product_id
    LEFT JOIN unit_types ut ON sd.sale_unit_type_id = ut.unit_type_id
    $whereClause
    ORDER BY s.created_at DESC, s.sale_id DESC";

$res7 = mysqli_query($conn, $detailedQuery);
$detailedSales = [];

if ($res7) {
    while ($row7 = mysqli_fetch_assoc($res7)) {
        $detailedSales[] = $row7;
    }
}
?>

<!-- Sales Reports -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gray-50">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Sales Report</h1>
                <p class="text-sm text-gray-500">Sales transaction report</p>
            </div>
        </div>

        <!-- Report Period Info -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 mb-6 shadow-sm border border-blue-100">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between">
                <div>
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900">
                        <?= 
                            ($startDate && $endDate) 
                            ? 'Custom Period Report' 
                            : 'All Sales Report'
                        ?>
                    </h2>
                    <p class="text-sm text-gray-500">
                        <?php if ($startDate && $endDate): ?>
                            <?= date('F j, Y', strtotime($startDate)) ?> - <?= date('F j, Y', strtotime($endDate)) ?>
                        <?php elseif ($startDate): ?>
                            From <?= date('F j, Y', strtotime($startDate)) ?>
                        <?php elseif ($endDate): ?>
                            Until <?= date('F j, Y', strtotime($endDate)) ?>
                        <?php else: ?>
                            All Time
                        <?php endif; ?>
                    </p>
                    <?php if ($cashierFilter): ?>
                        <p class="text-xs text-gray-400 mt-1">
                            Filtered by: <?= htmlspecialchars($cashierFilter) ?>
                        </p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-400 mt-1">
                        Generated: <?= date('M j, Y g:i A') ?>
                    </p>
                </div>
                <div class="mt-2 sm:mt-0 flex flex-col items-end space-y-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                        <?= $salesSummary['total_revenue'] > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                        <?= $salesSummary['total_revenue'] > 0 ? 'Active Period' : 'No Sales Data' ?>
                    </span>
                    <?php if ($salesSummary['total_revenue'] > 0): ?>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Daily Average</p>
                        <p class="text-sm font-semibold text-gray-900">
                            ₱<?= number_format($salesSummary['total_revenue'] / max(1, (strtotime($endDate) - strtotime($startDate)) / 86400), 2) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Total Revenue -->
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 border border-gray-100 group">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-green-50 rounded-lg group-hover:bg-green-100 transition-colors">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-medium">Total Revenue</p>
                        <p class="text-lg font-bold text-gray-900">₱<?= number_format($salesSummary['total_revenue'], 2) ?></p>
                        <div class="flex items-center justify-end mt-1">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span class="text-xs text-green-500 ml-1">+12%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Transactions -->
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 border border-gray-100 group">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-blue-50 rounded-lg group-hover:bg-blue-100 transition-colors">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-medium">Total Transactions</p>
                        <p class="text-lg font-bold text-gray-900"><?= number_format($salesSummary['total_transactions']) ?></p>
                        <div class="flex items-center justify-end mt-1">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span class="text-xs text-green-500 ml-1">+8%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Average Transaction -->
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 border border-gray-100 group">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-purple-50 rounded-lg group-hover:bg-purple-100 transition-colors">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-medium">Avg. Transaction</p>
                        <p class="text-lg font-bold text-gray-900">₱<?= number_format($salesSummary['average_transaction'], 2) ?></p>
                        <div class="flex items-center justify-end mt-1">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span class="text-xs text-green-500 ml-1">+4%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Items Sold -->
            <div class="bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 border border-gray-100 group">
                <div class="flex items-center justify-between">
                    <div class="p-2 bg-orange-50 rounded-lg group-hover:bg-orange-100 transition-colors">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-medium">Items Sold</p>
                        <p class="text-lg font-bold text-gray-900"><?= number_format($salesSummary['total_items_sold']) ?></p>
                        <div class="flex items-center justify-end mt-1">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span class="text-xs text-green-500 ml-1">+15%</span>
                        </div>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Start Date Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Start Date (Optional)</label>
                        <input type="date" id="filterStartDate" value="<?= htmlspecialchars($startDate) ?>" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Leave empty for all sales"
                            onchange="applyFilters()">
                    </div>
                    <!-- End Date Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">End Date (Optional)</label>
                        <input type="date" id="filterEndDate" value="<?= htmlspecialchars($endDate) ?>" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Leave empty for all sales"
                            onchange="applyFilters()">
                    </div>
                    <!-- User/Cashier Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Cashier/User</label>
                        <select id="filterCashier" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                            onchange="applyFilters()">
                            <option value="">All Cashiers</option>
                            <?php foreach ($cashiers as $cashier): ?>
                                <option value="<?= htmlspecialchars($cashier) ?>" <?= $cashierFilter === $cashier ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cashier) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Search Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Search</label>
                        <div class="relative">
                            <input type="text" id="searchSales" placeholder="Search sales..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onkeyup="filterSalesTable()">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Report Table -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100 mb-8">
            <style>
                .sales-table-scroll {
                    max-height: 640px; /* Approximately 10 table rows */
                    overflow-y: auto;
                }
                .sales-table-scroll::-webkit-scrollbar {
                    width: 8px;
                }
                .sales-table-scroll::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 10px;
                }
                .sales-table-scroll::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 10px;
                }
                .sales-table-scroll::-webkit-scrollbar-thumb:hover {
                    background: #555;
                }
            </style>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
                <div>
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Sales Report</h3>
                    <p class="text-sm text-gray-500">Complete transaction details for the selected period</p>
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
                    <div class="sales-table-scroll">
                    <table class="w-full min-w-[1400px] divide-y divide-gray-200" id="salesReportTable">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                            <tr>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Receipt #</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Date & Time</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Cashier</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Product</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Batch</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Qty (Pkg)</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Qty (Unit)</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Unit Price</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Subtotal</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Discount</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($detailedSales)): ?>
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                        </div>
                                        <p class="font-bold text-lg text-gray-900 mb-1">No Sales Data</p>
                                        <p class="text-sm text-gray-500">No sales data available for the selected period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: 
                            foreach ($detailedSales as $sale): ?>
                            <tr class="hover:bg-green-50 transition-colors duration-150 cursor-pointer" onclick="openViewSalesDetailsModal(<?= htmlspecialchars(json_encode($sale)) ?>)">
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($sale['receipt_number'] ?? $sale['sale_id']) ?>
                                    </span>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-xs sm:text-sm text-gray-600">
                                    <div class="text-xs sm:text-sm font-medium"><?= date('M j, Y', strtotime($sale['sale_date'])) ?></div>
                                    <div class="text-xs text-gray-500"><?= date('g:i A', strtotime($sale['sale_date'])) ?></div>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-7 h-7 sm:w-8 sm:h-8 bg-green-100 rounded-full flex items-center justify-center mr-2 border border-green-200">
                                            <span class="text-xs font-semibold text-green-700">
                                                <?= strtoupper(substr($sale['cashier'] ?? '', 0, 1)) ?>
                                            </span>
                                        </div>
                                        <span class="text-xs sm:text-sm font-medium text-gray-900"><?= htmlspecialchars($sale['cashier'] ?? 'N/A') ?></span>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-900">
                                    <div class="max-w-xs">
                                        <div class="font-semibold truncate"><?= htmlspecialchars($sale['brand_name']) ?></div>
                                        <div class="text-gray-500 text-xs truncate"><?= htmlspecialchars($sale['generic_name']) ?></div>
                                        <?php if (!empty($sale['strength'])): ?>
                                            <div class="text-gray-400 text-xs truncate"><?= htmlspecialchars($sale['strength']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-xs sm:text-sm">
                                    <div class="flex flex-col">
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200 mb-1">
                                            <?= htmlspecialchars($sale['batch_no'] ?? 'N/A') ?>
                                        </span>
                                        <?php if (!empty($sale['expiry_date'])): ?>
                                            <div class="text-xs text-gray-500">Exp: <?= date('M d, Y', strtotime($sale['expiry_date'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center text-xs sm:text-sm text-gray-900">
                                    <?php if (isset($sale['quantity_packages']) && $sale['quantity_packages'] > 0): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                            <?= number_format($sale['quantity_packages']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center text-xs sm:text-sm text-gray-900">
                                    <?php if (isset($sale['quantity_units']) && $sale['quantity_units'] > 0): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold bg-green-50 text-green-700 border border-green-200">
                                            <?= number_format($sale['quantity_units']) ?> <?= htmlspecialchars($sale['sale_unit'] ?? '') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right text-xs sm:text-sm text-gray-900">
                                    ₱<?= number_format($sale['unit_price'] ?? 0, 2) ?>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right text-xs sm:text-sm font-semibold text-gray-900">
                                    ₱<?= number_format($sale['subtotal'] ?? 0, 2) ?>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                    <?php 
                                    $itemDiscount = floatval($sale['item_discount_amount'] ?? 0);
                                    $itemDiscountType = $sale['item_discount_type'] ?? 'None';
                                    if ($itemDiscount > 0 && $itemDiscountType !== 'None'): ?>
                                        <div class="flex flex-col items-center">
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-red-50 text-red-700 border border-red-200 mb-0.5">
                                                <?= htmlspecialchars($itemDiscountType) ?>
                                            </span>
                                            <span class="text-xs font-bold text-red-600">-₱<?= number_format($itemDiscount, 2) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                    <div class="flex flex-col items-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold <?= strtolower($sale['payment_method'] ?? 'cash') == 'cash' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-blue-50 text-blue-700 border border-blue-200' ?> mb-1">
                                            <?= htmlspecialchars(strtoupper($sale['payment_method'] ?? 'Cash')) ?>
                                        </span>
                                        <span class="text-xs font-bold text-gray-900">₱<?= number_format($sale['total_amount'] ?? 0, 2) ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($detailedSales)): ?>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="9" class="px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-bold text-gray-900 text-right">Total:</td>
                            <td class="px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-bold text-gray-900 text-right">₱<?= number_format(array_sum(array_column($detailedSales, 'subtotal')), 2) ?></td>
                            <td class="px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-bold text-gray-900 text-center">
                                -₱<?= number_format(array_sum(array_map(function($s) { return floatval($s['item_discount_amount'] ?? 0); }, $detailedSales)), 2) ?>
                            </td>
                            <td class="px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-bold text-gray-900 text-right">₱<?= number_format(array_sum(array_column($detailedSales, 'total_amount')), 2) ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                    </table>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($detailedSales)): ?>
            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-500 gap-2 bg-gray-50 p-3 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Showing <?= count($detailedSales) ?> transaction items
                </div>
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Generated on <?= date('F j, Y \a\t g:i A') ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- View Sales Details Modal -->
<div id="viewSalesDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto p-4" style="background: rgba(0,0,0,0.6);">
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
            <h3 class="text-lg font-bold text-white">Sales Details</h3>
            <p class="text-xs text-indigo-100 mt-0.5">Complete transaction information</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="closeViewSalesDetailsModal()" class="p-1.5 text-white hover:bg-white hover:bg-opacity-20 rounded-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="p-4 max-h-[65vh] overflow-y-auto">
      <div id="printableSalesDetailsContent">
        <!-- Transaction Information Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Transaction Information</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Receipt Number</p>
                  <p class="text-sm font-medium text-gray-900" id="viewReceiptNo">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Sale Date</p>
                  <p class="text-sm font-medium text-gray-900" id="viewSaleDate">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Cashier</p>
                  <p class="text-sm font-medium text-gray-900" id="viewCashier">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="flex items-center">
                <div class="bg-indigo-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Payment Method</p>
                  <p class="text-sm font-medium text-gray-900" id="viewPaymentMethod">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
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
        
        <!-- Quantity & Pricing Section -->
        <div class="mb-5">
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Quantity & Pricing</h4>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="bg-purple-50 p-3 rounded-lg border border-purple-100">
              <div class="flex items-center">
                <div class="bg-purple-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Packages</p>
                  <p class="text-sm font-semibold text-purple-700" id="viewPackages">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-green-50 p-3 rounded-lg border border-green-100">
              <div class="flex items-center">
                <div class="bg-green-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Units</p>
                  <p class="text-sm font-semibold text-green-700" id="viewUnits">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
              <div class="flex items-center">
                <div class="bg-blue-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Unit Price</p>
                  <p class="text-sm font-semibold text-blue-700" id="viewUnitPrice">-</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Financial Summary Section -->
        <div>
          <h4 class="text-base font-semibold text-gray-800 mb-3 pb-1.5 border-b border-gray-200">Financial Summary</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100">
              <div class="flex items-center">
                <div class="bg-emerald-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Subtotal</p>
                  <p class="text-sm font-semibold text-emerald-700" id="viewSubtotal">-</p>
                </div>
              </div>
            </div>
            
            <div class="bg-green-50 p-3 rounded-lg border border-green-100">
              <div class="flex items-center">
                <div class="bg-green-100 p-1.5 rounded-lg mr-2">
                  <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Total Amount</p>
                  <p class="text-base font-bold text-green-700" id="viewTotalAmount">-</p>
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
        <button onclick="closeViewSalesDetailsModal()" class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-all font-medium text-sm">
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
  #printableSalesDetailsContent, #printableSalesDetailsContent * {
    visibility: visible;
  }
  #printableSalesDetailsContent {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  
  /* Enhanced PDF-like print styles */
  .print-report {
    font-family: "Arial", sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #000;
    margin: 0;
    padding: 0;
  }
  
  .print-header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #333;
    padding-bottom: 10px;
  }
  
  .print-header h1 {
    font-size: 24px;
    margin: 0 0 5px 0;
    color: #333;
  }
  
  .print-header p {
    margin: 0;
    color: #666;
  }
  
  .print-summary {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
  }
  
  .print-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
  }
  
  .print-summary-item {
    text-align: center;
    padding: 10px;
    border: 1px solid #ddd;
    background: white;
  }
  
  .print-summary-item .label {
    font-size: 10px;
    color: #666;
    margin-bottom: 5px;
  }
  
  .print-summary-item .value {
    font-size: 14px;
    font-weight: bold;
    color: #333;
  }
  
  .print-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
  }
  
  .print-table th {
    background: #333;
    color: white;
    font-weight: bold;
    padding: 8px;
    text-align: left;
    border: 1px solid #ddd;
    font-size: 10px;
  }
  
  .print-table td {
    padding: 6px;
    border: 1px solid #ddd;
    font-size: 10px;
  }
  
  .print-table tr:nth-child(even) {
    background: #f9f9f9;
  }
  
  .print-footer {
    margin-top: 20px;
    padding-top: 10px;
    border-top: 1px solid #ddd;
    text-align: center;
    font-size: 10px;
    color: #666;
  }
  
  .text-right {
    text-align: right;
  }
  
  .text-center {
    text-align: center;
  }
  
  .text-bold {
    font-weight: bold;
  }
  
  .page-break {
    page-break-after: always;
  }
  
  .no-print {
    display: none !important;
  }
}
</style>

<script>
function applyFilters() {
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;
    const cashier = document.getElementById('filterCashier').value;
    
    // Build URL with current parameters
    const url = new URL(window.location.href);
    
    // Only set date params if they have values
    if (startDate) {
        url.searchParams.set('start_date', startDate);
    } else {
        url.searchParams.delete('start_date');
    }
    
    if (endDate) {
        url.searchParams.set('end_date', endDate);
    } else {
        url.searchParams.delete('end_date');
    }
    
    if (cashier) {
        url.searchParams.set('cashier', cashier);
    } else {
        url.searchParams.delete('cashier');
    }
    
    // Reload page with new filters
    window.location.href = url.toString();
}

function clearFilters() {
    // Reset to show all sales (no date filter)
    const url = new URL(window.location.href);
    url.searchParams.delete('start_date');
    url.searchParams.delete('end_date');
    url.searchParams.delete('cashier');
    
    // Reload page with cleared filters
    window.location.href = url.toString();
}

function filterSalesTable() {
    const searchInput = document.getElementById('searchSales').value.toLowerCase();
    const table = document.getElementById('salesReportTable');
    const tbody = table.querySelector('tbody');
    const rows = tbody.getElementsByTagName('tr');
    
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
    const tableBody = document.getElementById('salesReportTable')?.querySelector('tbody');
    if (!tableBody) {
        showErrorModal('Error', 'No sales table found.');
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
        showErrorModal('No Data', 'No sales data to print. Please check your filters.');
        return;
    }
    
    // Calculate totals from visible rows
    let totalSubtotal = 0;
    let totalAmount = 0;
    let totalPackages = 0;
    let totalUnits = 0;
    
    visibleRows.forEach(row => {
        // Extract values for totals
        const cells = row.querySelectorAll('td');
        
        // Get subtotal (column 8 - Receipt=0, Date=1, Cashier=2, Product=3, Batch=4, Qty Pkg=5, Qty Unit=6, Unit Price=7, Subtotal=8, Discount=9, Payment=10)
        if (cells.length > 8) {
            const subtotalText = cells[8].textContent.replace('₱', '').replace(/,/g, '').trim();
            totalSubtotal += parseFloat(subtotalText) || 0;
        }
        
        // Get total amount (column 10)
        if (cells.length > 10) {
            const totalText = cells[10].querySelector('span:last-child')?.textContent?.replace('₱', '').replace(/,/g, '').trim() || cells[10].textContent.replace('₱', '').replace(/,/g, '').trim();
            totalAmount += parseFloat(totalText) || 0;
        }
        
        // Get quantities (columns 5 and 6)
        if (cells.length > 5) {
            const pkgText = cells[5].textContent.trim();
            if (pkgText !== '-') {
                totalPackages += parseInt(pkgText.replace(/,/g, '')) || 0;
            }
        }
        
        if (cells.length > 6) {
            const unitText = cells[6].textContent.split(' ')[0].replace(/,/g, '').trim();
            if (unitText !== '-') {
                totalUnits += parseInt(unitText) || 0;
            }
        }
    });
    
    const averageAmount = visibleRows.length > 0 ? totalAmount / visibleRows.length : 0;
    
    // Get filter information
    const startDate = document.getElementById('filterStartDate')?.value || '';
    const endDate = document.getElementById('filterEndDate')?.value || '';
    const cashierFilter = document.getElementById('filterCashier')?.value || '';
    
    // Create print window
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Sales Report</title>
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
                .print-table tfoot {
                    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
                }
                .print-table tfoot td {
                    padding: 12px 8px;
                    border: 2px solid #cbd5e1;
                    font-weight: 700;
                    font-size: 11px;
                    color: #1e293b;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .text-bold {
                    font-weight: 700;
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
                    .print-table tfoot {
                        display: table-footer-group;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>📊 Sales Report</h1>
                ${startDate && endDate ? `<p><strong>Date Range:</strong> ${startDate} to ${endDate}</p>` : startDate ? `<p><strong>From Date:</strong> ${startDate}</p>` : endDate ? `<p><strong>To Date:</strong> ${endDate}</p>` : '<p><strong>Date Range:</strong> All dates</p>'}
                ${cashierFilter ? `<p><strong>Cashier:</strong> ${cashierFilter}</p>` : ''}
            </div>
            
            <div class="print-summary-cards">
                <div class="summary-card">
                    <div class="label">Total Transactions</div>
                    <div class="value">${visibleRows.length}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Total Subtotal</div>
                    <div class="value">₱${totalSubtotal.toFixed(2)}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Total Amount</div>
                    <div class="value">₱${totalAmount.toFixed(2)}</div>
                </div>
                <div class="summary-card">
                    <div class="label">Average per Item</div>
                    <div class="value">₱${averageAmount.toFixed(2)}</div>
                </div>
            </div>
            
            <div class="print-info">
                <p><strong>Total Items:</strong> ${visibleRows.length} transaction${visibleRows.length !== 1 ? 's' : ''}</p>
                ${startDate && endDate ? `<p><strong>Date Range:</strong> ${startDate} to ${endDate}</p>` : startDate ? `<p><strong>From Date:</strong> ${startDate}</p>` : endDate ? `<p><strong>To Date:</strong> ${endDate}</p>` : '<p><strong>Date Range:</strong> All dates</p>'}
                ${cashierFilter ? `<p><strong>Cashier Filter:</strong> ${cashierFilter}</p>` : ''}
            </div>
            
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Receipt #</th>
                        <th style="width: 12%;">Date & Time</th>
                        <th style="width: 10%;">Cashier</th>
                        <th style="width: 20%;">Product</th>
                        <th style="width: 10%;">Batch</th>
                        <th style="width: 8%;" class="text-center">Qty (Pkg)</th>
                        <th style="width: 10%;" class="text-center">Qty (Unit)</th>
                        <th style="width: 8%;" class="text-right">Unit Price</th>
                        <th style="width: 10%;" class="text-right">Subtotal</th>
                        <th style="width: 10%;" class="text-center">Discount</th>
                        <th style="width: 12%;" class="text-right">Payment</th>
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
            // Column indices: Receipt=0, Date=1, Cashier=2, Product=3, Batch=4, Qty Pkg=5, Qty Unit=6, Unit Price=7, Subtotal=8, Discount=9, Payment=10
            const className = index === 7 || index === 8 || index === 10 ? 'text-right' : 
                             index === 5 || index === 6 || index === 9 ? 'text-center' : '';
            
            // Clean up product name (remove extra spaces/newlines)
            let cellContent = text.replace(/\s+/g, ' ');
            
            if (index === 3 && cell.querySelector('.font-semibold')) {
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
                <tfoot>
                    <tr class="text-bold">
                        <td colspan="8" class="text-right" style="padding: 12px 8px;">GRAND TOTAL:</td>
                        <td class="text-right" style="padding: 12px 8px;">₱${totalSubtotal.toFixed(2)}</td>
                        <td class="text-center" style="padding: 12px 8px;">-</td>
                        <td class="text-right" style="padding: 12px 8px; border-left: 2px solid #cbd5e1;">₱${totalAmount.toFixed(2)}</td>
                    </tr>
                </tfoot>
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

function printSalesReport() {
    window.print();
}

// View Sales Details Modal Functions
function openViewSalesDetailsModal(sale) {
    document.getElementById('viewReceiptNo').textContent = sale.receipt_number || sale.sale_id || '-';
    
    const saleDate = sale.sale_date ? new Date(sale.sale_date) : null;
    document.getElementById('viewSaleDate').textContent = saleDate ? 
        saleDate.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A';
    
    document.getElementById('viewCashier').textContent = sale.cashier || 'N/A';
    document.getElementById('viewPaymentMethod').textContent = sale.payment_method ? sale.payment_method.toUpperCase() : 'N/A';
    
    document.getElementById('viewBrandName').textContent = sale.brand_name || '-';
    document.getElementById('viewGenericName').textContent = sale.generic_name || '-';
    document.getElementById('viewStrength').textContent = sale.strength || 'N/A';
    document.getElementById('viewBatchNo').textContent = sale.batch_no || 'N/A';
    
    document.getElementById('viewPackages').textContent = sale.quantity_packages ? number_format(sale.quantity_packages) : '0';
    document.getElementById('viewUnits').textContent = sale.quantity_units ? number_format(sale.quantity_units) + ' ' + (sale.sale_unit || '') : '0';
    document.getElementById('viewUnitPrice').textContent = sale.unit_price ? '₱' + parseFloat(sale.unit_price).toFixed(2) : '₱0.00';
    
    document.getElementById('viewSubtotal').textContent = sale.subtotal ? '₱' + parseFloat(sale.subtotal).toFixed(2) : '₱0.00';
    document.getElementById('viewTotalAmount').textContent = sale.total_amount ? '₱' + parseFloat(sale.total_amount).toFixed(2) : '₱0.00';
    
    document.getElementById('viewSalesDetailsModal').classList.remove('hidden');
    document.getElementById('viewSalesDetailsModal').classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeViewSalesDetailsModal() {
    document.getElementById('viewSalesDetailsModal').classList.add('hidden');
    document.getElementById('viewSalesDetailsModal').classList.remove('flex');
    document.body.style.overflow = 'auto';
}

function printSalesDetails() {
    window.print();
}

function number_format(number) {
    return parseInt(number).toLocaleString('en-US');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('viewSalesDetailsModal');
    if (event.target === modal) {
        closeViewSalesDetailsModal();
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
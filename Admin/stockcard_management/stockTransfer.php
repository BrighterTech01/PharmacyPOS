<?php 
// Include database connection
require_once '../../dbConnection/connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isAdmin = false;
if (!empty($_SESSION['user']['role'])) { $isAdmin = strtolower($_SESSION['user']['role']) === 'admin'; }
elseif (!empty($_SESSION['role'])) { $isAdmin = strtolower($_SESSION['role']) === 'admin'; }

// Get filters from URL parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$userFilter = isset($_GET['user']) ? $_GET['user'] : '';
$productFilter = isset($_GET['product']) ? $_GET['product'] : '';

// Build WHERE clause
$whereConditions = [];

if ($startDate && $endDate) {
    $whereConditions[] = "DATE(st.transfer_date) BETWEEN '" . mysqli_real_escape_string($conn, $startDate) . "' AND '" . mysqli_real_escape_string($conn, $endDate) . "'";
} elseif ($startDate) {
    $whereConditions[] = "DATE(st.transfer_date) >= '" . mysqli_real_escape_string($conn, $startDate) . "'";
} elseif ($endDate) {
    $whereConditions[] = "DATE(st.transfer_date) <= '" . mysqli_real_escape_string($conn, $endDate) . "'";
}

if ($userFilter) {
    $whereConditions[] = "u.username = '" . mysqli_real_escape_string($conn, $userFilter) . "'";
}

if ($productFilter) {
    $whereConditions[] = "p.brand_name LIKE '%" . mysqli_real_escape_string($conn, $productFilter) . "%'";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Fetch transfers from stock_transfers table
$transfersQuery = "SELECT 
                     st.*,
                     p.brand_name,
                     p.generic_name,
                     p.strength,
                     d.batch_no,
                     d.expiry_date,
                     u.username,
                     ut.unit_name
                   FROM stock_transfers st
                   LEFT JOIN deliveries d ON st.delivery_id = d.delivery_id
                   LEFT JOIN products p ON d.product_id = p.product_id
                   LEFT JOIN users u ON st.user_id = u.user_id
                   LEFT JOIN unit_types ut ON st.unit_type_id = ut.unit_type_id
                   $whereClause
                   ORDER BY st.transfer_date DESC";
$transfersResult = mysqli_query($conn, $transfersQuery);
$transfers = [];

if ($transfersResult && mysqli_num_rows($transfersResult) > 0) {
    while ($row = mysqli_fetch_assoc($transfersResult)) {
        $transfers[] = $row;
    }
}

// Fetch deliveries available in storage for transfer
// Note: pharmacy_inventory doesn't have batch_id column, so we can't join it here
$storageBatchesQuery = "SELECT DISTINCT 
                          si.delivery_id AS batch_id,
                          si.storage_id,
                          si.quantity_packages_balance AS available_packages,
                          si.quantity_units_balance AS available_units,
                          d.batch_no,
                          d.expiry_date,
                          d.quantity_units AS units_per_package,
                          p.product_id,
                          p.brand_name,
                          p.generic_name,
                          p.strength,
                          0 AS pharmacy_current_units
                        FROM storage_inventory si
                        LEFT JOIN deliveries d ON si.delivery_id = d.delivery_id
                        LEFT JOIN products p ON d.product_id = p.product_id
                        WHERE d.delivery_id IS NOT NULL 
                          AND si.quantity_packages_balance > 0
                          AND (
                                d.expiry_date IS NULL
                             OR YEAR(d.expiry_date) = 0
                             OR d.expiry_date >= CURDATE()
                          )
                        ORDER BY d.expiry_date ASC, p.brand_name ASC";
$storageBatchesResult = mysqli_query($conn, $storageBatchesQuery);
$availableBatches = [];

if ($storageBatchesResult && mysqli_num_rows($storageBatchesResult) > 0) {
    while ($row = mysqli_fetch_assoc($storageBatchesResult)) {
        $availableBatches[] = $row;
    }
}

// Calculate statistics
$totalTransfers = count($transfers);
$totalTransferredPackages = array_sum(array_column($transfers, 'quantity_packages'));
$totalTransferredUnits = array_sum(array_column($transfers, 'quantity_units'));
$availableBatchesCount = count($availableBatches);

// Get unique users for filter dropdown
$usersQuery = "SELECT DISTINCT u.username 
               FROM stock_transfers st
               LEFT JOIN users u ON st.user_id = u.user_id
               WHERE u.username IS NOT NULL
               ORDER BY u.username";
$usersResult = mysqli_query($conn, $usersQuery);
$uniqueUsers = [];
if ($usersResult) {
    while ($row = mysqli_fetch_assoc($usersResult)) {
        $uniqueUsers[] = $row['username'];
    }
}

// Get unique products for filter dropdown
$productsQuery = "SELECT DISTINCT p.brand_name 
                  FROM stock_transfers st
                  LEFT JOIN deliveries d ON st.delivery_id = d.delivery_id
                  LEFT JOIN products p ON d.product_id = p.product_id
                  WHERE p.brand_name IS NOT NULL
                  ORDER BY p.brand_name";
$productsResult = mysqli_query($conn, $productsQuery);
$uniqueProducts = [];
if ($productsResult) {
    while ($row = mysqli_fetch_assoc($productsResult)) {
        $uniqueProducts[] = $row['brand_name'];
    }
}

include '../../includes/adminHeader.php'; 
?>

<?php if (isset($_GET['error']) || isset($_GET['success'])): ?>
  <div class="px-6">
    <?php if (isset($_GET['success'])): ?>
      <?php if ($_GET['success'] === 'transferred'): ?>
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
          Stock transferred successfully to pharmacy. Movement logged.
        </div>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <?php
        $msg = 'An error occurred while processing your request.';
        if ($_GET['error'] === 'missing_fields') $msg = 'Batch and Quantity are required.';
        if ($_GET['error'] === 'invalid_batch') $msg = 'Invalid batch selected.';
        if ($_GET['error'] === 'not_in_storage') $msg = 'Selected batch is not available in storage.';
        if ($_GET['error'] === 'transfer_failed') $msg = 'Transfer failed. ' . (isset($_GET['msg']) ? urldecode($_GET['msg']) : 'Please try again.');
        if ($_GET['error'] === 'insufficient_stock') $msg = 'Insufficient stock in storage for this batch.';
        if ($_GET['error'] === 'missing_user') $msg = 'We could not identify the current user. Please log in again and retry the transfer.';
      ?>
      <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Stock Transfer Management -->
<main class="w-full overflow-y-auto p-4 sm:p-6 bg-gradient-to-br from-gray-50 to-purple-50/30 min-h-screen">
    <div class="mx-auto max-w-7xl px-2 lg:px-6 2xl:px-8">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white rounded-xl shadow-sm border border-purple-100">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Stock Transfer</h1>
                            <p class="text-sm text-gray-600 mt-1">Transfer stock from storage to pharmacy</p>
                            <a href="stock_MovementLog.php" class="text-xs text-purple-600 hover:text-purple-800 hover:underline mt-1 inline-block">
                              View all movement logs →
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Transfer Button -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button onclick="openTransferStockModal()" class="px-4 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-700 text-white rounded-xl hover:from-purple-700 hover:to-indigo-800 transition-all duration-200 text-sm font-semibold flex items-center gap-2 shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        New Transfer
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Transfers -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Transfers</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($totalTransfers) ?></p>
                    </div>
                    <div class="p-3 bg-purple-50 rounded-xl">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Transferred Packages -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Packages Transferred</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($totalTransferredPackages) ?></p>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-xl">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Transferred Units -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Units Transferred</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($totalTransferredUnits) ?></p>
                    </div>
                    <div class="p-3 bg-emerald-50 rounded-xl">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Available Batches -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Available Batches</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($availableBatchesCount) ?></p>
                    </div>
                    <div class="p-3 bg-orange-50 rounded-xl">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

            <!-- Transfer History Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg p-6 border border-white/50">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Transfer History</h3>
                    <p class="text-sm text-gray-500">All stock transfers from storage to pharmacy</p>
                </div>
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button onclick="printFullReport()" class="px-5 py-2.5 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition-all text-sm font-semibold shadow-sm hover:shadow-md flex items-center gap-2">
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white">
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white">
                            <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- User Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">User</label>
                        <select id="filterUser" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white"
                            onchange="applyFilters()">
                            <option value="">All Users</option>
                            <?php foreach ($uniqueUsers as $user): ?>
                                <option value="<?= htmlspecialchars($user) ?>" <?= $userFilter === $user ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Product Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Product</label>
                        <select id="filterProduct" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white"
                            onchange="applyFilters()">
                            <option value="">All Products</option>
                            <?php foreach ($uniqueProducts as $product): ?>
                                <option value="<?= htmlspecialchars($product) ?>" <?= $productFilter === $product ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($product) ?>
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
                                placeholder="Search..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white"
                                onkeyup="filterTransfers()"
                            >
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="overflow-hidden rounded-xl border border-gray-200">
                <style>
                    .transfer-table-scroll {
                        max-height: 640px; /* Approximately 10 table rows */
                        overflow-y: auto;
                    }
                    .transfer-table-scroll::-webkit-scrollbar {
                        width: 8px;
                    }
                    .transfer-table-scroll::-webkit-scrollbar-track {
                        background: #f1f1f1;
                        border-radius: 10px;
                    }
                    .transfer-table-scroll::-webkit-scrollbar-thumb {
                        background: #888;
                        border-radius: 10px;
                    }
                    .transfer-table-scroll::-webkit-scrollbar-thumb:hover {
                        background: #555;
                    }
                </style>
                <div class="transfer-table-scroll">
                <table class="w-full min-w-full">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                        <tr>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Date/Time</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Product</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Batch</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Packages</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Units</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Transferred By</th>
                        </tr>
                    </thead>
                    <tbody id="transfersTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if (count($transfers) > 0): ?>
                            <?php foreach ($transfers as $transfer): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors cursor-pointer" onclick="openViewTransferModal(<?= htmlspecialchars(json_encode($transfer)) ?>)" data-packages="<?= (int)($transfer['quantity_packages'] ?? 0) ?>" data-units="<?= (int)($transfer['quantity_units'] ?? 0) ?>">
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-xs sm:text-sm text-gray-900">
                                            <?= date('M d, Y', strtotime($transfer['transfer_date'])) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= date('h:i A', strtotime($transfer['transfer_date'])) ?>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="min-w-0">
                                            <div class="text-xs sm:text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($transfer['brand_name'] ?? 'N/A') ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($transfer['generic_name'] ?? '') ?></div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                            <?= htmlspecialchars($transfer['batch_no'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="text-xs sm:text-sm font-semibold text-gray-900">
                                            <?= number_format((int)($transfer['quantity_packages'] ?? 0)) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-right">
                                        <span class="text-xs sm:text-sm text-gray-700">
                                            <?= number_format((int)($transfer['quantity_units'] ?? 0)) ?> <?= htmlspecialchars($transfer['unit_name'] ?? 'units') ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <span class="text-xs sm:text-sm text-gray-700"><?= htmlspecialchars($transfer['username'] ?? 'System') ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <p class="font-semibold text-lg text-gray-600 mb-1">No Transfers Found</p>
                                        <p class="text-sm">No stock transfers recorded yet.</p>
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


<?php include '../../Modal/modalTransfer.php'; ?>

<script src="../../Assets/Javascript/stockTransfer.js?v=<?= time() ?>"></script>
</body>
</html>


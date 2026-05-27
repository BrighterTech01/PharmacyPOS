<?php 
// Include database connection
require_once '../../dbConnection/connection.php';

// Fetch all suppliers from database
$query = "SELECT supplier_id, supplier_name, contact_person, phone, email, address FROM suppliers ORDER BY supplier_name ASC";
$result = mysqli_query($conn, $query);
$suppliers = [];

// Check if query was successful and has results
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $suppliers[] = $row;
    }
}

// Calculate statistics
$totalSuppliers = count($suppliers);

// Count deliveries per supplier (through products)
$deliveriesQuery = "SELECT p.supplier_id, COUNT(*) as delivery_count 
                    FROM deliveries d
                    INNER JOIN products p ON d.product_id = p.product_id
                    WHERE p.supplier_id IS NOT NULL
                    GROUP BY p.supplier_id";
$deliveriesResult = mysqli_query($conn, $deliveriesQuery);
$supplierDeliveries = [];
if ($deliveriesResult && mysqli_num_rows($deliveriesResult) > 0) {
    while ($row = mysqli_fetch_assoc($deliveriesResult)) {
        $supplierDeliveries[$row['supplier_id']] = $row['delivery_count'];
    }
}

include '../../includes/adminHeader.php'; 
?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>

<!-- Modern Suppliers Management -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gradient-to-br from-gray-50 to-sky-50/30 min-h-screen">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header Section -->
        <div class="mb-6 sm:mb-8 lg:mb-10">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 sm:gap-6">
                <div class="space-y-2 sm:space-y-3">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="p-1.5 sm:p-2 lg:p-2 bg-white rounded-lg sm:rounded-xl shadow-sm border border-sky-100">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl lg:text-3xl xl:text-4xl font-bold text-gray-900">Suppliers Management</h1>
                            <p class="text-xs sm:text-sm lg:text-base text-gray-600 mt-0.5 sm:mt-1">Manage supplier information and contacts</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards - Modern Design -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-5 xl:gap-6 mb-5 sm:mb-6 lg:mb-8">
            <!-- Total Suppliers -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Total Suppliers</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format($totalSuppliers) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-sky-500 rounded-full mr-1.5 sm:mr-2"></div>
                            <p class="text-xs text-gray-500 truncate">All registered suppliers</p>
                        </div>
                    </div>
                    <div class="p-2 sm:p-3 bg-sky-50 rounded-lg sm:rounded-xl group-hover:bg-sky-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Active Suppliers -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Active Suppliers</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format(count($supplierDeliveries)) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <svg class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <p class="text-xs text-gray-500 truncate">With deliveries</p>
                        </div>
                    </div>
                    <div class="p-2 sm:p-3 bg-emerald-50 rounded-lg sm:rounded-xl group-hover:bg-emerald-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Deliveries -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-600 mb-1">Total Deliveries</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900"><?= number_format(array_sum($supplierDeliveries)) ?></p>
                        <div class="flex items-center mt-1 sm:mt-2">
                            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-purple-500 rounded-full mr-1.5 sm:mr-2"></div>
                            <p class="text-xs text-gray-500 truncate">From all suppliers</p>
                        </div>
                    </div>
                    <div class="p-2 sm:p-3 bg-purple-50 rounded-lg sm:rounded-xl group-hover:bg-purple-100 transition-colors flex-shrink-0 ml-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 border border-white/50 mb-6 sm:mb-8">
            <div class="flex items-center gap-2 sm:gap-3 mb-4 sm:mb-6">
                <div class="p-1.5 sm:p-2 bg-sky-50 rounded-lg">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                    </div>
                    <div>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900">Filters</h3>
                    <p class="text-xs sm:text-sm text-gray-500">Search and filter suppliers</p>
                </div>
                </div>
                
            <div class="flex flex-col lg:flex-row gap-3 sm:gap-4 lg:gap-5 w-full">
                <!-- Search Bar -->
                <div class="w-full lg:flex-1 lg:min-w-[280px] xl:min-w-[320px] 2xl:min-w-[400px]">
                    <div class="relative">
                        <input 
                            type="text" 
                            id="searchInput" 
                            placeholder="Search by supplier name, contact person, or email..." 
                            class="w-full pl-10 sm:pl-12 lg:pl-14 pr-4 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500 shadow-sm transition-all duration-200 text-sm lg:text-base"
                            onkeyup="filterSuppliers()"
                        >
                        <svg class="absolute left-3 sm:left-4 lg:left-5 top-1/2 transform -translate-y-1/2 w-4 h-4 lg:w-5 lg:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
                
                <!-- Delivery Status Filter -->
                <div class="w-full lg:w-52 xl:w-56 2xl:w-64">
                    <select id="deliveryStatusFilter" onchange="filterSuppliers()" class="w-full px-3 sm:px-4 lg:px-5 py-2.5 sm:py-3 lg:py-3.5 bg-white border border-gray-200 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-sky-500 focus:border-sky-500 shadow-sm transition-all duration-200 text-sm lg:text-base">
                        <option value="">All Suppliers</option>
                        <option value="with_deliveries">With Deliveries</option>
                        <option value="without_deliveries">Without Deliveries</option>
                    </select>
                </div>
                
                <!-- Clear Filters Button -->
                <button onclick="clearSupplierFilters()" class="px-3 sm:px-4 lg:px-6 py-2.5 sm:py-3 lg:py-3.5 bg-gray-200 text-gray-700 rounded-lg sm:rounded-xl hover:bg-gray-300 transition-all duration-200 text-xs sm:text-sm lg:text-base font-semibold flex items-center justify-center gap-2 whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Clear Filters
                </button>
            </div>
        </div>

        <!-- Suppliers Table Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-xl sm:rounded-2xl shadow-lg p-4 sm:p-6 border border-white/50">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4 sm:mb-6 gap-3 sm:gap-4">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-1.5 sm:p-2 bg-sky-50 rounded-lg">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900">Suppliers List</h3>
                        <p class="text-xs sm:text-sm text-gray-500">Manage supplier information and contacts</p>
                    </div>
                </div>
                
                <!-- Action Controls -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <button onclick="printFullReport()" class="px-3 sm:px-4 py-2 sm:py-2.5 bg-sky-600 text-white rounded-lg sm:rounded-xl hover:bg-sky-700 transition-all text-xs sm:text-sm font-semibold shadow-sm hover:shadow-md flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Print Full Report
                    </button>
                    <button onclick="openAddSupplierModal()" class="px-3 sm:px-4 py-2 sm:py-2.5 bg-gradient-to-r from-sky-600 to-sky-700 text-white rounded-lg sm:rounded-xl hover:from-sky-700 hover:to-sky-800 transition-all duration-200 text-xs sm:text-sm font-semibold flex items-center justify-center gap-2 shadow-lg shadow-sky-500/25 hover:shadow-sky-500/40 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add Supplier
                    </button>
                </div>
            </div>
            
            <!-- Suppliers Table -->
            <div id="suppliersTableWrapper" class="overflow-x-auto rounded-xl border border-gray-200">
                <style>
                    /* Custom scrollbar for responsive tables */
                    #suppliersTableWrapper::-webkit-scrollbar {
                        height: 8px;
                    }
                    #suppliersTableWrapper::-webkit-scrollbar-track {
                        background: #f1f1f1;
                        border-radius: 10px;
                    }
                    #suppliersTableWrapper::-webkit-scrollbar-thumb {
                        background: #888;
                        border-radius: 10px;
                    }
                    #suppliersTableWrapper::-webkit-scrollbar-thumb:hover {
                        background: #555;
                    }
                    #suppliersTableWrapper {
                        scrollbar-width: thin;
                        scrollbar-color: #888 #f1f1f1;
                        max-height: 640px; /* Approximately 10 table rows */
                        overflow-y: auto;
                    }
                </style>
                <table class="w-full min-w-[1000px]">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                        <tr>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[150px]">Supplier Details</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Contact Info</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Phone</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Email</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Deliveries</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap sticky right-0 bg-gradient-to-r from-gray-50 to-gray-100/80">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="suppliersTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if (count($suppliers) > 0): ?>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors duration-150 cursor-pointer" onclick="openViewSupplierModal(<?= htmlspecialchars(json_encode($supplier)) ?>)" data-supplier-id="<?= $supplier['supplier_id'] ?>" data-delivery-status="<?= isset($supplierDeliveries[$supplier['supplier_id']]) && $supplierDeliveries[$supplier['supplier_id']] > 0 ? 'with_deliveries' : 'without_deliveries' ?>" data-delivery-count="<?= $supplierDeliveries[$supplier['supplier_id']] ?? 0 ?>">
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap min-w-[150px]">
                                        <div class="flex items-center">
                                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gradient-to-r from-sky-500 to-sky-600 flex items-center justify-center text-white font-bold text-xs mr-2 flex-shrink-0">
                                                <?= strtoupper(substr($supplier['supplier_name'], 0, 1)) ?>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-xs sm:text-sm font-semibold text-gray-900 truncate max-w-[120px]" title="<?= htmlspecialchars($supplier['supplier_name']) ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></div>
                                                <div class="text-xs text-gray-500">ID: #<?= $supplier['supplier_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-xs sm:text-sm text-gray-900"><?= htmlspecialchars($supplier['contact_person'] ?? '-') ?></div>
                                        <div class="text-xs text-gray-500">Contact Person</div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-xs sm:text-sm text-gray-900"><?= htmlspecialchars($supplier['phone'] ?? '-') ?></div>
                                        <div class="text-xs text-gray-500">Phone</div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="text-xs sm:text-sm text-gray-900 truncate max-w-[150px]"><?= htmlspecialchars($supplier['email'] ?? '-') ?></div>
                                        <div class="text-xs text-gray-500">Email</div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-emerald-500 to-emerald-600 text-white">
                                            <?= $supplierDeliveries[$supplier['supplier_id']] ?? 0 ?> Deliveries
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center sticky right-0 bg-white" onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-center gap-1 sm:gap-1.5">
                                            <button onclick='openEditSupplierModal(<?= json_encode($supplier) ?>)' class="inline-flex items-center px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-all duration-200 text-xs font-semibold" title="Edit">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                            <?php if (!empty($isAdmin) && $isAdmin): ?>
                                                <button onclick="openDeleteSupplierModal(<?= $supplier['supplier_id'] ?>, '<?= htmlspecialchars($supplier['supplier_name']) ?>')" class="inline-flex items-center px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-all duration-200 text-xs font-semibold" title="Delete">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" onclick="noDeletePermission()" class="inline-flex items-center px-2 py-1 bg-red-400 text-white rounded opacity-60 cursor-not-allowed text-xs font-semibold" title="Delete (No Permission)">
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
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                        </div>
                                        <p class="font-semibold text-lg text-gray-600 mb-1">No Suppliers Found</p>
                                        <p class="text-sm">No suppliers found in the system.</p>
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

<!-- Add Supplier Modal -->
<?php include '../../Modal/modalSuppliers.php'; ?>

<script src="../../Assets/Javascript/errorAndsuccessHander.js"></script>
<script src="../../Assets/Javascript/supplier.js?v=<?= time() ?>"></script>
<script>
// Initialize modal handlers
const successMessages = {
    'created': 'Supplier created successfully.',
    'updated': 'Supplier updated successfully.',
    'deleted': 'Supplier deleted successfully.'
};

const errorMessages = {
    'missing_name': 'Supplier name is required.',
    'duplicate_name': 'Supplier name already exists. Please use a different name.',
    'forbidden': 'You do not have permission to delete suppliers.',
    'supplier_in_use': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const count = urlParams.get('count') || '0';
        return 'Cannot delete supplier because it is referenced by ' + count + ' deliveries.';
    }
};

initModalHandlers(successMessages, errorMessages);

// Handle supplier_in_use error with count
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('error') === 'supplier_in_use') {
    const count = urlParams.get('count') || '0';
    showErrorModal('Error', 'Cannot delete supplier because it is referenced by ' + count + ' deliveries.');
}

function noDeletePermission(){
    showErrorModal('Permission Denied', 'You do not have permission to delete items.');
}

// Ensure function is available immediately after script loads
(function() {
  function verifyFunction() {
    if (typeof window.openViewSupplierModal === 'function') {
      if (typeof openViewSupplierModal === 'undefined') {
        openViewSupplierModal = window.openViewSupplierModal;
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

// Filter functionality for suppliers (updated in supplier.js)
function clearSupplierFilters() {
  document.getElementById('searchInput').value = '';
  document.getElementById('deliveryStatusFilter').value = '';
  filterSuppliers();
}

window.filterSuppliers = filterSuppliers;
window.clearSupplierFilters = clearSupplierFilters;
</script>
</body>
</html>
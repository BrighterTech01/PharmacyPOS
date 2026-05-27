<?php 
// Include database connection
require_once '../../dbConnection/connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isAdmin = false;
if (!empty($_SESSION['user']['role'])) { $isAdmin = strtolower($_SESSION['user']['role']) === 'admin'; }
elseif (!empty($_SESSION['role'])) { $isAdmin = strtolower($_SESSION['role']) === 'admin'; }

// Fetch all package types with status
$query = "SELECT package_type_id, package_name, status FROM package_types ORDER BY package_name ASC";
$result = mysqli_query($conn, $query);
$packageTypes = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $packageTypes[] = $row;
    }
}

// Count products per package type
$prodQuery = "SELECT package_type_id, COUNT(*) as product_count FROM products GROUP BY package_type_id";
$prodResult = mysqli_query($conn, $prodQuery);
$packageTypeProducts = [];
if ($prodResult && mysqli_num_rows($prodResult) > 0) {
    while ($row = mysqli_fetch_assoc($prodResult)) {
        $packageTypeProducts[$row['package_type_id']] = (int)$row['product_count'];
    }
}

// Auto-update status based on product count: Active if has products, Inactive if no products
foreach ($packageTypes as &$pt) {
    $productCount = $packageTypeProducts[$pt['package_type_id']] ?? 0;
    $newStatus = ($productCount > 0) ? 'Active' : 'Inactive';
    
    // Update status in database if it differs
    if (strtoupper($pt['status'] ?? '') !== strtoupper($newStatus)) {
        $ptId = (int)$pt['package_type_id'];
        mysqli_query($conn, "UPDATE package_types SET status = '$newStatus' WHERE package_type_id = $ptId");
        $pt['status'] = $newStatus;
    }
    
    // Add product count to package type array for JavaScript
    $pt['product_count'] = $productCount;
}
unset($pt);

// Stats
$totalPackageTypes = count($packageTypes);
$withProducts = count(array_filter($packageTypeProducts, function($c){ return $c > 0; }));
$emptyPackageTypes = $totalPackageTypes - $withProducts;
$mostProducts = 0;
if (!empty($packageTypeProducts)) { $mostProducts = max($packageTypeProducts); }

include '../../includes/adminHeader.php'; 
?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>

<!-- Package Type Management -->
<main class="w-full overflow-y-auto p-3 sm:p-4 lg:p-6 xl:p-8 bg-gradient-to-br from-gray-50 to-blue-50/30 min-h-screen">
    <div class="mx-auto max-w-full xl:max-w-7xl 2xl:max-w-[1600px] px-2 sm:px-4 lg:px-6 xl:px-8 2xl:px-12">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white rounded-xl shadow-sm border border-blue-100">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Package Types</h1>
                            <p class="text-sm text-gray-600 mt-1">Manage product package types</p>
                        </div>
                    </div>
                </div>
                
                <button onclick="openAddPackageTypeModal()" class="flex items-center px-4 py-2 bg-blue-500 text-white text-sm font-semibold rounded-lg hover:bg-blue-600 transition-colors shadow-md hover:shadow-lg whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Package Type
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Package Types -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Types</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($totalPackageTypes) ?></p>
                        <div class="flex items-center mt-2">
                            <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                            <p class="text-xs text-gray-500">All package types</p>
                        </div>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-xl group-hover:bg-blue-100 transition-colors">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Active Types -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Active Types</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($withProducts) ?></p>
                        <div class="flex items-center mt-2">
                            <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                            <p class="text-xs text-gray-500">With products</p>
                        </div>
                    </div>
                    <div class="p-3 bg-green-50 rounded-xl group-hover:bg-green-100 transition-colors">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Empty Types -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Empty Types</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($emptyPackageTypes) ?></p>
                        <div class="flex items-center mt-2">
                            <div class="w-2 h-2 bg-amber-500 rounded-full mr-2"></div>
                            <p class="text-xs text-gray-500">No products</p>
                        </div>
                    </div>
                    <div class="p-3 bg-amber-50 rounded-xl group-hover:bg-amber-100 transition-colors">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Most Products -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-white/50 hover:shadow-xl transition-all duration-300 group hover:scale-105">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Most Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($mostProducts) ?></p>
                        <div class="flex items-center mt-2">
                            <div class="w-2 h-2 bg-purple-500 rounded-full mr-2"></div>
                            <p class="text-xs text-gray-500">In one type</p>
                        </div>
                    </div>
                    <div class="p-3 bg-purple-50 rounded-xl group-hover:bg-purple-100 transition-colors">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m-8-8l-8 8"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Package Types Table Section -->
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg p-6 border border-white/50">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-6 gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Package Types List</h3>
                        <p class="text-sm text-gray-500">Manage product package types</p>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                    <div class="relative flex-1 lg:w-80">
                        <input 
                            type="text" 
                            id="searchInput" 
                            placeholder="Search by package type name..." 
                            class="w-full pl-12 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all duration-200"
                            onkeyup="filterPackageTypes()"
                        >
                        <svg class="absolute left-4 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="filterByStatus('all')" id="filterAll" class="flex-1 sm:flex-none px-3 sm:px-4 py-2.5 sm:py-3 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transition-colors font-semibold text-xs sm:text-sm whitespace-nowrap">
                            All
                        </button>
                        <button onclick="filterByStatus('active')" id="filterActive" class="flex-1 sm:flex-none px-3 sm:px-4 py-2.5 sm:py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors font-semibold text-xs sm:text-sm whitespace-nowrap">
                            Active
                        </button>
                        <button onclick="filterByStatus('inactive')" id="filterInactive" class="flex-1 sm:flex-none px-3 sm:px-4 py-2.5 sm:py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors font-semibold text-xs sm:text-sm whitespace-nowrap">
                            Inactive
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Desktop/Tablet Table View -->
            <style>
                #packageTypesTableWrapper {
                    max-height: 640px; /* Approximately 10 table rows */
                    overflow-y: auto;
                }
                #packageTypesTableWrapper::-webkit-scrollbar {
                    width: 8px;
                    height: 8px;
                }
                #packageTypesTableWrapper::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 10px;
                }
                #packageTypesTableWrapper::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 10px;
                }
                #packageTypesTableWrapper::-webkit-scrollbar-thumb:hover {
                    background: #555;
                }
            </style>
            <div id="packageTypesTableWrapper" class="hidden md:block overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full min-w-full">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100/80 border-b border-gray-200">
                        <tr>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">ID</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Package Type</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Status</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">Products</th>
                            <th class="px-3 sm:px-4 py-2 sm:py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap sticky right-0 bg-gradient-to-r from-gray-50 to-gray-100/80">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="packageTypesTableBody" class="bg-white divide-y divide-gray-200">
                        <?php if (count($packageTypes) > 0): ?>
                            <?php foreach ($packageTypes as $pt): 
                              $productCount = $packageTypeProducts[$pt['package_type_id']] ?? 0;
                              $isEmpty = $productCount === 0;
                              $status = strtoupper($pt['status'] ?? 'Inactive');
                              $isActive = ($status === 'ACTIVE' || $productCount > 0);
                              $statusClass = $isActive ? 'status-active' : 'status-inactive';
                            ?>
                                <tr class="hover:bg-gray-50/50 transition-colors duration-150 cursor-pointer <?php echo $statusClass; ?>" onclick="openViewPackageTypeModal(<?= htmlspecialchars(json_encode($pt)) ?>)" data-status="<?php echo $isActive ? 'active' : 'inactive'; ?>">
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap" onclick="event.stopPropagation()">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                            #<?= $pt['package_type_id'] ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-xs mr-2">
                                                <?= strtoupper(substr($pt['package_name'], 0, 1)) ?>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-xs sm:text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($pt['package_name']) ?></div>
                                                <?php if ($isEmpty): ?>
                                                    <div class="text-xs text-amber-600 mt-0.5">No products</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <?php if ($isActive): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-green-500 to-green-600 text-white">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">
                                        <?php if ($productCount > 0): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-purple-500 to-purple-600 text-white">
                                                <?= $productCount ?> Products
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                </svg>
                                                No Products
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap text-center sticky right-0 bg-white" onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-center gap-1">
                                            <button onclick='openEditPackageTypeModal(<?= json_encode($pt) ?>)' class="inline-flex items-center px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-all duration-200 text-xs font-semibold" title="Edit">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <?php if ($isAdmin): ?>
                                                <button onclick="openDeletePackageTypeModal(<?= $pt['package_type_id'] ?>, '<?= htmlspecialchars($pt['package_name'], ENT_QUOTES) ?>')" class="inline-flex items-center px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-all duration-200 text-xs font-semibold" title="Delete">
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
                                <td colspan="5" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"></path>
                                            </svg>
                                        </div>
                                        <p class="font-semibold text-lg text-gray-600 mb-1">No Package Types Found</p>
                                        <p class="text-sm">No package types found in the system.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card View -->
            <div id="packageTypesCardsContainer" class="md:hidden space-y-4">
                <?php if (count($packageTypes) > 0): ?>
                    <?php foreach ($packageTypes as $pt): 
                      $productCount = $packageTypeProducts[$pt['package_type_id']] ?? 0;
                      $isEmpty = $productCount === 0;
                      $status = strtoupper($pt['status'] ?? 'Inactive');
                      $isActive = ($status === 'ACTIVE' || $productCount > 0);
                      $statusClass = $isActive ? 'status-active' : 'status-inactive';
                    ?>
                        <div class="bg-white rounded-xl shadow-md border border-gray-200 p-4 cursor-pointer hover:shadow-lg transition-all duration-200 <?php echo $statusClass; ?>" onclick="openViewPackageTypeModal(<?= htmlspecialchars(json_encode($pt)) ?>)" data-status="<?php echo $isActive ? 'active' : 'inactive'; ?>">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center flex-1 min-w-0">
                                    <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-lg mr-3 flex-shrink-0">
                                        <?= strtoupper(substr($pt['package_name'], 0, 1)) ?>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="text-base font-semibold text-gray-900 truncate"><?= htmlspecialchars($pt['package_name']) ?></h3>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 flex-shrink-0">
                                                #<?= $pt['package_type_id'] ?>
                                            </span>
                                        </div>
                                        <?php if ($isEmpty): ?>
                                            <p class="text-xs text-amber-600">No products</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <?php if ($isActive): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-green-500 to-green-600 text-white">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Inactive
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($productCount > 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-purple-500 to-purple-600 text-white">
                                        <?= $productCount ?> Products
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                        No Products
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100" onclick="event.stopPropagation()">
                                <button onclick='openEditPackageTypeModal(<?= json_encode($pt) ?>)' class="flex items-center px-3 py-1.5 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-xs font-semibold">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Edit
                                </button>
                                <?php if ($isAdmin): ?>
                                    <button onclick="openDeletePackageTypeModal(<?= $pt['package_type_id'] ?>, '<?= htmlspecialchars($pt['package_name'], ENT_QUOTES) ?>')" class="flex items-center px-3 py-1.5 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors text-xs font-semibold">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Delete
                                    </button>
                                <?php else: ?>
                                    <button type="button" onclick="noDeletePermission()" class="flex items-center px-3 py-1.5 bg-red-400 text-white rounded-lg opacity-60 cursor-not-allowed text-xs font-semibold">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-md border border-gray-200 p-12 text-center">
                        <div class="flex flex-col items-center justify-center text-gray-400">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"></path>
                                </svg>
                            </div>
                            <p class="font-semibold text-lg text-gray-600 mb-1">No Package Types Found</p>
                            <p class="text-sm">No package types found in the system.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Modals -->
<?php include '../../Modal/modaPackageType.php'; ?>

<script src="../../Assets/Javascript/errorAndsuccessHander.js"></script>
<script src="../../Assets/Javascript/packageType.js"></script>
<script>
// Initialize modal handlers
const successMessages = {
    'created': 'Package type created successfully.',
    'updated': 'Package type updated successfully.',
    'deleted': 'Package type deleted successfully.'
};

const errorMessages = {
    'missing_fields': 'Please fill in all required fields.',
    'duplicate': 'A package type with this name already exists.',
    'invalid_id': 'Invalid package type selected.',
    'forbidden': 'You do not have permission to perform this action.',
    'in_use': function() {
        const urlParams = new URLSearchParams(window.location.search);
        const count = urlParams.get('count') || '0';
        return 'Cannot delete this package type because it is used by ' + count + ' product(s).';
    },
    'create_failed': 'Failed to create package type.',
    'update_failed': 'Failed to update package type.',
    'delete_failed': 'Failed to delete package type.'
};

initModalHandlers(successMessages, errorMessages);

// Handle in_use error with count
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('error') === 'in_use') {
    const count = urlParams.get('count') || '0';
    showErrorModal('Error', 'Cannot delete this package type because it is used by ' + count + ' product(s).');
}

function noDeletePermission(){
    showErrorModal('Permission Denied', 'You do not have permission to delete items.');
}

(function() {
  function verifyFunction() {
    if (typeof window.openViewProductsModal === 'function') {
      if (typeof openViewProductsModal === 'undefined') {
        openViewProductsModal = window.openViewProductsModal;
      }
      return true;
    }
    return false;
  }
  
  if (!verifyFunction()) {
    if (typeof jQuery !== 'undefined') {
      jQuery(document).ready(function() {
        setTimeout(function() {
          if (!verifyFunction()) {
            console.error('openViewProductsModal failed to load');
          }
        }, 100);
      });
    } else {
      setTimeout(function() {
        verifyFunction();
      }, 100);
    }
  }
})();
</script>

</body>
</html>

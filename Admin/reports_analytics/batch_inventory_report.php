<?php include '../../includes/adminHeader.php' ?>
<!-- Include Modal Handler -->
<?php include '../../Modal/modalAlertHandling.php'; ?>
<?php
// Database connection
require_once '../../dbConnection/connection.php';

// Get filters
$productFilter = isset($_GET['product']) ? $_GET['product'] : '';
$batchFilter = isset($_GET['batch']) ? $_GET['batch'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : ''; // all, active, expired, expiring
$daysAhead = 90; // Fixed at 90 days

// Get batch inventory data
$batchInventory = [];

$query = "SELECT 
    d.delivery_id as batch_id,
    d.batch_no,
    d.manufacturing_date,
    d.expiry_date,
    d.delivery_date as received_date,
    d.quantity_packages as batch_quantity_packages,
    d.quantity_units as batch_quantity_units,
    p.product_id,
    p.brand_name,
    p.generic_name,
    p.strength,
    p.barcode,
    c.category_name,
    COALESCE(s.supplier_name, 'N/A') as supplier_name,
    -- Storage inventory
    COALESCE(si.quantity_packages_balance, 0) as storage_packages,
    COALESCE(si.quantity_units_balance, 0) as storage_units,
    -- Pharmacy inventory
    COALESCE(SUM(pi.quantity_units_balance), 0) as pharmacy_units,
    -- Total inventory
    (COALESCE(si.quantity_units_balance, 0) + COALESCE(SUM(pi.quantity_units_balance), 0)) as total_units,
    -- Product thresholds
    COALESCE(p.product_threshold, 0) as product_threshold,
    COALESCE(p.expire_threshold, 30) as expire_threshold,
    -- Expiry status
    DATEDIFF(d.expiry_date, CURDATE()) as days_until_expiry,
    CASE 
        WHEN (COALESCE(si.quantity_units_balance, 0) + COALESCE(SUM(pi.quantity_units_balance), 0)) > 0 THEN 'Active'
        ELSE 'Inactive'
    END as batch_status,
    -- Last movement
    (SELECT MAX(movement_date) 
     FROM stock_movement_log 
     WHERE reference_type = 'delivery' AND reference_id = d.delivery_id) as last_movement_date
FROM deliveries d
LEFT JOIN products p ON d.product_id = p.product_id
LEFT JOIN Categories c ON p.category_id = c.category_id
LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
WHERE 1=1";

// Apply filters
if ($productFilter) {
    $query .= " AND (p.brand_name LIKE '%" . mysqli_real_escape_string($conn, $productFilter) . "%' 
                OR p.generic_name LIKE '%" . mysqli_real_escape_string($conn, $productFilter) . "%')";
}

if ($batchFilter) {
    $query .= " AND d.batch_no = '" . mysqli_real_escape_string($conn, $batchFilter) . "'";
}

$query .= " GROUP BY d.delivery_id, d.batch_no, d.manufacturing_date, d.expiry_date, d.delivery_date, 
                     d.quantity_packages, d.quantity_units, p.product_id, p.brand_name, p.generic_name, 
                     p.strength, p.barcode, c.category_name, s.supplier_name, si.quantity_packages_balance, 
                     si.quantity_units_balance, p.product_threshold, p.expire_threshold";

// Apply HAVING clause for status filters
if ($statusFilter === 'active') {
    $query .= " HAVING total_units > 0";
} elseif ($statusFilter === 'inactive') {
    $query .= " HAVING total_units <= 0";
}

$query .= " ORDER BY d.expiry_date ASC, d.batch_no ASC";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $batchInventory[] = $row;
    }
}

// Calculate statistics
$totalBatches = count($batchInventory);
$activeBatches = count(array_filter($batchInventory, function($b) { return $b['batch_status'] === 'Active'; }));
$inactiveBatches = $totalBatches - $activeBatches;
$totalUnits = array_sum(array_column($batchInventory, 'total_units'));
$displayLimit = 10;
$displayedBatches = array_slice($batchInventory, 0, $displayLimit);

// Get batch numbers for filter
$batchesQuery = "SELECT DISTINCT d.batch_no 
                 FROM deliveries d
                 WHERE d.batch_no IS NOT NULL AND d.batch_no != ''
                 ORDER BY d.batch_no ASC";
$batchesResult = mysqli_query($conn, $batchesQuery);
$batches = [];
if ($batchesResult) {
    while ($row = mysqli_fetch_assoc($batchesResult)) {
        $batches[] = $row['batch_no'];
    }
}
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6 print-hide">
        <h1 class="text-3xl font-bold text-gray-800">📦 Batch Inventory Report</h1>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                🖨️ Print Report
            </button>
        </div>
    </div>
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6 print-hide">
        <form method="GET" id="filterForm" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                <input type="text" name="product" id="productFilter" value="<?php echo htmlspecialchars($productFilter); ?>" 
                       placeholder="Search product..." 
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 auto-filter">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Batch No</label>
                <select name="batch" id="batchFilter" class="w-full border border-gray-300 rounded-lg px-3 py-2 auto-filter">
                    <option value="">All Batches</option>
                    <?php foreach ($batches as $batch): ?>
                        <option value="<?php echo htmlspecialchars($batch); ?>" <?php echo $batchFilter === $batch ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($batch); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="statusFilter" class="w-full border border-gray-300 rounded-lg px-3 py-2 auto-filter">
                    <option value="all" <?php echo $statusFilter === 'all' || !$statusFilter ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="md:col-span-3 flex gap-2">
                <a href="batch_inventory_report.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
                    🔄 Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Report Table -->
    <div id="batchTableSection" class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="batchTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch No</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Manufacturing Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Until Expiry</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Storage Units</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pharmacy Units</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Units</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($batchInventory)): ?>
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                                No batch inventory found matching the filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($displayedBatches as $batch): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($batch['batch_no']); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <div class="font-medium"><?php echo htmlspecialchars($batch['brand_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($batch['generic_name']); ?></div>
                                    <?php if ($batch['strength']): ?>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($batch['strength']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($batch['category_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($batch['supplier_name']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $batch['manufacturing_date'] ? date('M d, Y', strtotime($batch['manufacturing_date'])) : 'N/A'; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $batch['expiry_date'] ? date('M d, Y', strtotime($batch['expiry_date'])) : 'N/A'; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <?php 
                                    $days = (int)$batch['days_until_expiry'];
                                    if ($days < 0) {
                                        echo '<span class="text-red-600 font-semibold">' . abs($days) . ' days ago</span>';
                                    } else {
                                        echo '<span class="text-gray-700">' . $days . ' days</span>';
                                    }
                                    ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($batch['storage_units']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($batch['pharmacy_units']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    <?php echo number_format($batch['total_units']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php
                                    $status = $batch['batch_status'];
                                    $statusColors = [
                                        'Active' => 'bg-green-100 text-green-800',
                                        'Inactive' => 'bg-gray-200 text-gray-800'
                                    ];
                                    $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $colorClass; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($totalBatches > $displayLimit): ?>
                            <tr>
                                <td colspan="11" class="px-4 py-3 text-center text-sm text-gray-500 bg-gray-50">
                                    Showing first <?php echo $displayLimit; ?> batches out of <?php echo $totalBatches; ?>.
                                    Refine your filters to view more specific results.
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="7" class="px-4 py-3 text-sm font-semibold text-gray-700 text-right">
                            Total Units:
                        </td>
                        <td colspan="4" class="px-4 py-3 text-sm font-bold text-gray-900">
                            <?php echo number_format($totalUnits); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>

<style>
    @media print {
        body {
            background: #fff;
            margin: 0;
            font-size: 12px;
        }
        .print-hide,
        .no-print {
            display: none !important;
        }
        #batchTableSection {
            box-shadow: none;
            border: none;
        }
        #batchTableSection table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        #batchTableSection th,
        #batchTableSection td {
            border: 1px solid #555;
            padding: 6px;
        }
        #batchTableSection thead {
            background: #f3f4f6 !important;
        }
    }
</style>

<script>
// Auto-filter functionality
document.addEventListener('DOMContentLoaded', function() {
    let filterTimeout;
    const filterForm = document.getElementById('filterForm');
    const autoFilterElements = document.querySelectorAll('.auto-filter');
    
    // Auto-submit on select change (immediate)
    autoFilterElements.forEach(function(element) {
        if (element.tagName === 'SELECT') {
            element.addEventListener('change', function() {
                filterForm.submit();
            });
        }
    });
    
    // Auto-submit on input change (with debounce for text input)
    const productFilter = document.getElementById('productFilter');
    
    if (productFilter) {
        productFilter.addEventListener('input', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(function() {
                filterForm.submit();
            }, 800); // Wait 800ms after user stops typing
        });
    }
});

</script>



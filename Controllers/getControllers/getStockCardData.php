<?php
// Suppress warnings and errors to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Check if connection file exists
$connectionFile = '../../dbConnection/connection.php';
if (!file_exists($connectionFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection file not found']);
    exit();
}

include_once $connectionFile;

// Check if database connection is available
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if product_id is provided
$product_id = null;
if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
    $product_id = (int)$_GET['product_id'];
}

if (!$product_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Product ID is required']);
    exit();
}

// Get product details
$productQuery = "SELECT 
                    p.product_id,
                    p.brand_name,
                    p.generic_name,
                    p.strength,
                    ut.unit_name,
                    pt.package_name
                 FROM products p
                 LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
                 LEFT JOIN package_types pt ON p.package_type_id = pt.package_type_id
                 WHERE p.product_id = ?";
$stmt = mysqli_prepare($conn, $productQuery);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database query preparation failed: ' . mysqli_error($conn)]);
    exit();
}

mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$productResult = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($productResult);
mysqli_stmt_close($stmt);

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Product not found']);
    exit();
}

// Get real-time current stock balance from inventory tables
// Includes: pharmacy_inventory units + storage_inventory (packages converted to units + direct units)
// Note: d.quantity_units in deliveries table represents units_per_package
$currentStockQuery = "SELECT 
                        COALESCE(SUM(si.quantity_packages_balance), 0) AS current_stock_packages,
                        COALESCE(SUM(pi.quantity_units_balance), 0) +
                        COALESCE(SUM(si.quantity_packages_balance * COALESCE(d.quantity_units, 0)), 0) +
                        COALESCE(SUM(si.quantity_units_balance), 0) AS current_stock_units,
                        COALESCE(SUM(pi.quantity_units_balance), 0) +
                        COALESCE(SUM(si.quantity_packages_balance * COALESCE(d.quantity_units, 0)), 0) +
                        COALESCE(SUM(si.quantity_units_balance), 0) AS current_stock
                      FROM deliveries d
                      LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
                      LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
                      WHERE d.product_id = ?
                        AND d.status = 'Active'
                        AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())";
$currentStockStmt = mysqli_prepare($conn, $currentStockQuery);
if ($currentStockStmt) {
    mysqli_stmt_bind_param($currentStockStmt, 'i', $product_id);
    mysqli_stmt_execute($currentStockStmt);
    $currentStockResult = mysqli_stmt_get_result($currentStockStmt);
    $currentStockData = mysqli_fetch_assoc($currentStockResult);
    $currentStock = (float)($currentStockData['current_stock'] ?? 0);
    $currentStockPackages = (float)($currentStockData['current_stock_packages'] ?? 0);
    $currentStockUnits = (float)($currentStockData['current_stock_units'] ?? 0);
    mysqli_stmt_close($currentStockStmt);
} else {
    $currentStock = 0;
    $currentStockPackages = 0;
    $currentStockUnits = 0;
}

// Fetch all stock movements for this product
// We need to calculate running balances and get cost information
$movementQuery = "SELECT 
                    sml.movement_id,
                    DATE(sml.movement_date) as movement_date,
                    sml.movement_type,
                    sml.quantity_units,
                    sml.quantity_packages,
                    sml.reference_type,
                    sml.reference_id,
                    sml.from_inventory,
                    CASE 
                        WHEN sml.movement_type = 'sale' THEN (
                            SELECT sd_sale.quantity_packages
                            FROM sales_details sd_sale
                            LEFT JOIN deliveries d_sale_match ON sd_sale.delivery_id = d_sale_match.delivery_id
                            WHERE sd_sale.sale_id = sml.reference_id
                              AND d_sale_match.product_id = sml.product_id
                            LIMIT 1
                        )
                        ELSE NULL
                    END as sale_quantity_packages,
                    CASE 
                        WHEN sml.movement_type = 'sale' THEN (
                            SELECT sd_sale.quantity_units
                            FROM sales_details sd_sale
                            LEFT JOIN deliveries d_sale_match ON sd_sale.delivery_id = d_sale_match.delivery_id
                            WHERE sd_sale.sale_id = sml.reference_id
                              AND d_sale_match.product_id = sml.product_id
                            LIMIT 1
                        )
                        ELSE NULL
                    END as sale_quantity_units,
                    CASE 
                        WHEN sml.movement_type = 'delivery' THEN d.quantity_units
                        WHEN sml.movement_type = 'transfer' THEN (
                            SELECT d_transfer.quantity_units
                            FROM stock_transfers st_transfer
                            LEFT JOIN deliveries d_transfer ON st_transfer.delivery_id = d_transfer.delivery_id
                            WHERE st_transfer.transfer_id = sml.reference_id
                            LIMIT 1
                        )
                        WHEN sml.movement_type = 'sale' THEN (
                            SELECT CASE 
                                WHEN sd_sale.quantity_packages > 0 THEN d_sale.quantity_units
                                ELSE 1
                            END
                            FROM sales_details sd_sale
                            LEFT JOIN deliveries d_sale ON sd_sale.delivery_id = d_sale.delivery_id
                            WHERE sd_sale.sale_id = sml.reference_id
                            LIMIT 1
                        )
                        ELSE 1
                    END as units_per_package,
                    CASE 
                        WHEN sml.movement_type = 'delivery' THEN 'Delivery'
                        WHEN sml.movement_type = 'sale' THEN 'Sales'
                        WHEN sml.movement_type = 'transfer' THEN 'Transfer'
                        WHEN sml.movement_type = 'adjustment' THEN 'Adjustment'
                        WHEN sml.movement_type = 'return' THEN 'Return'
                        ELSE sml.movement_type
                    END as particulars,
                    CASE 
                        WHEN sml.reference_type = 'delivery' THEN d.delivery_id
                        WHEN sml.reference_type = 'sale' THEN s.sale_id
                        WHEN sml.reference_type = 'transfer' THEN st.transfer_id
                        WHEN sml.reference_type = 'adjustment' THEN sml.reference_id
                        WHEN sml.reference_type = 'return' THEN sml.reference_id
                        ELSE NULL
                    END as document_no,
                    u_movement.username as username,
                    CASE 
                        WHEN sml.movement_type = 'delivery' THEN d.cost_per_unit
                        WHEN sml.movement_type = 'sale' THEN (
                            SELECT d_sale.cost_per_unit
                            FROM sales_details sd_sale
                            LEFT JOIN deliveries d_sale ON sd_sale.delivery_id = d_sale.delivery_id
                            WHERE sd_sale.sale_id = sml.reference_id
                            LIMIT 1
                        )
                        WHEN sml.movement_type = 'transfer' THEN (
                            SELECT d_transfer.cost_per_unit
                            FROM stock_transfers st_transfer
                            LEFT JOIN deliveries d_transfer ON st_transfer.delivery_id = d_transfer.delivery_id
                            WHERE st_transfer.transfer_id = sml.reference_id
                            LIMIT 1
                        )
                        WHEN sml.movement_type = 'return' THEN (
                            SELECT d_return.cost_per_unit
                            FROM deliveries d_return
                            WHERE d_return.delivery_id = sml.reference_id
                            LIMIT 1
                        )
                        ELSE 0
                    END as cost_per_unit,
                    CASE 
                        WHEN sml.movement_type = 'delivery' THEN d.batch_no
                        WHEN sml.movement_type = 'transfer' THEN (
                            SELECT d_transfer.batch_no
                            FROM stock_transfers st_transfer
                            LEFT JOIN deliveries d_transfer ON st_transfer.delivery_id = d_transfer.delivery_id
                            WHERE st_transfer.transfer_id = sml.reference_id
                            LIMIT 1
                        )
                        WHEN sml.movement_type = 'sale' THEN (
                            SELECT d_sale.batch_no
                            FROM sales_details sd_sale
                            LEFT JOIN deliveries d_sale ON sd_sale.delivery_id = d_sale.delivery_id
                            WHERE sd_sale.sale_id = sml.reference_id
                            LIMIT 1
                        )
                        WHEN sml.movement_type = 'return' THEN (
                            SELECT d_return.batch_no
                            FROM deliveries d_return
                            WHERE d_return.delivery_id = sml.reference_id
                            LIMIT 1
                        )
                        ELSE NULL
                    END as batch_no
                  FROM stock_movement_log sml
                  LEFT JOIN deliveries d ON sml.reference_type = 'delivery' AND sml.reference_id = d.delivery_id
                  LEFT JOIN sales s ON sml.reference_type = 'sale' AND sml.reference_id = s.sale_id
                  LEFT JOIN stock_transfers st ON sml.reference_type = 'transfer' AND sml.reference_id = st.transfer_id
                  LEFT JOIN users u_movement ON sml.user_id = u_movement.user_id
                  WHERE sml.product_id = ?
                  ORDER BY sml.movement_date ASC, sml.movement_id ASC";

$stmt = mysqli_prepare($conn, $movementQuery);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database query preparation failed: ' . mysqli_error($conn)]);
    exit();
}

mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$movements = [];
$runningBalanceQty = 0;
$runningBalancePackages = 0;
$runningBalanceTotal = 0;

// Get initial inventory on hand (first delivery or transfer)
$initialQuery = "SELECT 
                    COALESCE(SUM(CASE WHEN sml.movement_type = 'delivery' THEN sml.quantity_units ELSE 0 END), 0) as initial_qty,
                    COALESCE(SUM(CASE WHEN sml.movement_type = 'delivery' THEN sml.quantity_units * d.cost_per_unit ELSE 0 END), 0) as initial_total
                 FROM stock_movement_log sml
                 LEFT JOIN deliveries d ON sml.reference_type = 'delivery' AND sml.reference_id = d.delivery_id
                 WHERE sml.product_id = ? 
                 AND sml.movement_type = 'delivery'
                 ORDER BY sml.movement_date ASC
                 LIMIT 1";
$initialStmt = mysqli_prepare($conn, $initialQuery);
mysqli_stmt_bind_param($initialStmt, 'i', $product_id);
mysqli_stmt_execute($initialStmt);
$initialResult = mysqli_stmt_get_result($initialStmt);
$initialData = mysqli_fetch_assoc($initialResult);
mysqli_stmt_close($initialStmt);

// Process movements and calculate running balances
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $qtyUnits = (float)($row['quantity_units'] ?? 0);
        $qtyPackages = (float)($row['quantity_packages'] ?? 0);
        $costPerUnit = (float)($row['cost_per_unit'] ?? 0);
        $unitsPerPackage = (float)($row['units_per_package'] ?? 1);
        
        // Determine if it's IN or OUT
        $isIn = in_array($row['movement_type'], ['delivery', 'transfer', 'return', 'adjustment']);
        $isOut = in_array($row['movement_type'], ['sale']);
        
        // For adjustments, check if quantity is positive (in) or negative (out)
        if ($row['movement_type'] === 'adjustment') {
            $isIn = $qtyUnits > 0;
            $isOut = $qtyUnits < 0;
            $qtyUnits = abs($qtyUnits);
            $qtyPackages = abs($qtyPackages);
        }
        
        // Calculate totals for IN
        // In (Pkg) = quantity_packages from stock_movement_log
        $inPackages = $isIn ? $qtyPackages : 0;
        // In (Unit) = quantity_units from deliveries table as units_per_package
        $inUnitsPerPackage = $isIn ? $unitsPerPackage : 0;
        // In Total (Pkg×Unit) = packages × units_per_package
        $inTotalUnits = $isIn ? ($inPackages * $unitsPerPackage) : 0;
        // In (Total) = total cost (using quantity_units from movement log for cost calculation)
        $inTotal = $isIn ? ($qtyUnits * $costPerUnit) : 0;
        
        // Calculate totals for OUT
        // Determine if it's a unit sale or package sale
        $saleQuantityPackages = (float)($row['sale_quantity_packages'] ?? 0);
        $saleQuantityUnits = (float)($row['sale_quantity_units'] ?? 0);
        $fromInventory = $row['from_inventory'] ?? '';
        
        // Sales from pharmacy inventory are always unit sales
        $isPharmacySale = $isOut && strtolower($fromInventory) === 'pharmacy';
        // Sales from storage with packages > 0 are package sales
        $isPackageSale = $isOut && !$isPharmacySale && $saleQuantityPackages > 0;
        // Other unit sales (from storage but no packages)
        $isUnitSale = $isOut && !$isPharmacySale && $saleQuantityPackages == 0 && $saleQuantityUnits > 0;
        
        if ($isPharmacySale) {
            // Pharmacy sale (always units): Out (Pkg) = 0, Out (Unit) = 1, Out Total = units sold
            $outPackages = 0;
            $outUnitsPerPackage = 1; // For pharmacy sales, always show 1 unit
            $outTotalUnits = $saleQuantityUnits > 0 ? $saleQuantityUnits : $qtyUnits; // Use sale quantity or movement quantity
        } else if ($isPackageSale) {
            // Package sale from storage: Out (Pkg) = packages, Out (Unit) = units_per_package, Out Total = packages × units_per_package
            $outPackages = $qtyPackages;
            $outUnitsPerPackage = $unitsPerPackage;
            $outTotalUnits = $outPackages * $unitsPerPackage;
        } else if ($isUnitSale) {
            // Unit sale from storage: Out (Pkg) = 0, Out (Unit) = 1, Out Total = units sold
            $outPackages = 0;
            $outUnitsPerPackage = 1; // For unit sales, show 1 as the unit multiplier
            $outTotalUnits = $saleQuantityUnits > 0 ? $saleQuantityUnits : $qtyUnits; // Use sale quantity or movement quantity
        } else {
            // Not a sale or other movement type
            $outPackages = $isOut ? $qtyPackages : 0;
            $outUnitsPerPackage = $isOut ? $unitsPerPackage : 0;
            $outTotalUnits = $isOut ? ($outPackages * $unitsPerPackage) : 0;
        }
        
        // Calculate out cost and total using average cost method
        $outCostPerUnit = 0;
        $outTotal = 0;
        if ($isOut) {
            // Use average cost method: calculate cost based on current stock balance
            if ($runningBalanceQty > 0) {
                $avgCost = $runningBalanceTotal / $runningBalanceQty;
                $outCostPerUnit = $avgCost;
                $outTotal = $qtyUnits * $avgCost;
            } else {
                // If no stock available, use the cost from the movement record
                $outCostPerUnit = $costPerUnit;
                $outTotal = $qtyUnits * $costPerUnit;
            }
        }
        
        // Update running balances
        if ($isIn) {
            $runningBalancePackages += $qtyPackages;
            $runningBalanceQty += $qtyUnits;
            $runningBalanceTotal += ($qtyUnits * $costPerUnit);
        } else if ($isOut) {
            $runningBalancePackages -= $qtyPackages;
            $runningBalanceQty -= $qtyUnits;
            $runningBalanceTotal -= $outTotal;
            // Ensure balance doesn't go negative
            if ($runningBalancePackages < 0) {
                $runningBalancePackages = 0;
            }
            if ($runningBalanceQty < 0) {
                $runningBalanceQty = 0;
            }
            if ($runningBalanceTotal < 0) {
                $runningBalanceTotal = 0;
            }
        }
        
        $movements[] = [
            'date' => $row['movement_date'],
            'particulars' => $row['particulars'],
            'document_no' => $row['document_no'] ?? '–',
            'in_packages' => $inPackages,
            'in_units_per_package' => $inUnitsPerPackage,
            'in_total_units' => $inTotalUnits,
            'in_cost_per_unit' => $isIn ? $costPerUnit : 0,
            'in_total' => $inTotal,
            'out_packages' => $outPackages,
            'out_units_per_package' => $outUnitsPerPackage,
            'out_total_units' => $outTotalUnits,
            'out_cost_per_unit' => $outCostPerUnit,
            'out_total' => $outTotal,
            'balance_packages' => $runningBalancePackages,
            'balance_qty' => $runningBalanceQty,
            'balance_total' => $runningBalanceTotal,
            'batch_no' => $row['batch_no'] ?? null,
            'username' => $row['username'] ?? null
        ];
    }
}

mysqli_stmt_close($stmt);

// Calculate totals
$totalInPackages = array_sum(array_column($movements, 'in_packages'));
$totalInTotalUnits = array_sum(array_column($movements, 'in_total_units'));
$totalInTotal = array_sum(array_column($movements, 'in_total'));
$totalOutPackages = array_sum(array_column($movements, 'out_packages'));
$totalOutTotalUnits = array_sum(array_column($movements, 'out_total_units'));
$totalOutTotal = array_sum(array_column($movements, 'out_total'));
$endingBalancePackages = $runningBalancePackages;
$endingBalanceQty = $runningBalanceQty;
$endingBalanceTotal = $runningBalanceTotal;

// Ensure no output before JSON (only if output buffering is active)
if (ob_get_level() > 0) {
    ob_clean();
}

echo json_encode([
    'success' => true,
    'product' => $product,
    'movements' => $movements,
    'current_stock' => $currentStock,
    'current_stock_packages' => $currentStockPackages,
    'current_stock_units' => $currentStockUnits,
    'summary' => [
        'total_in_packages' => $totalInPackages,
        'total_in_total_units' => $totalInTotalUnits,
        'total_in_total' => $totalInTotal,
        'total_out_packages' => $totalOutPackages,
        'total_out_total_units' => $totalOutTotalUnits,
        'total_out_total' => $totalOutTotal,
        'ending_balance_packages' => $endingBalancePackages,
        'ending_balance_qty' => $endingBalanceQty,
        'ending_balance_total' => $endingBalanceTotal
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit();
?>


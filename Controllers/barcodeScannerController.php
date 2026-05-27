<?php
// Barcode Scanner Controller - Ultra Clean Version
// Output buffering to prevent any stray output
ob_start();

// Suppress notices in output, but keep errors in logs
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@error_reporting(E_ALL);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error. Please check logs.'
        ]);
        exit;
    }
});

// Set headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get input
$raw = @file_get_contents("php://input");
$input = @json_decode($raw, true);
$barcode = isset($input['barcode']) ? trim($input['barcode']) : '';

// Validate
if (empty($barcode)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Barcode required']);
    exit;
}

// Connect to database using app connection
try {
    require_once '../dbConnection/connection.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Escape barcode
$barcode_safe = $conn->real_escape_string($barcode);

// Prefer barcodes table if present; otherwise fallback to products.barcode
$row = null;

// Detect if barcodes table exists
$hasBarcodesTbl = false;
$tblCheck = @$conn->query("SHOW TABLES LIKE 'barcodes'");
if ($tblCheck) {
    $hasBarcodesTbl = ($tblCheck->num_rows > 0);
    @$tblCheck->free();
}

if ($hasBarcodesTbl) {
    // Query via barcodes table (delivery-specific)
    // Check if barcodes table has delivery_id column
    $hasDeliveryId = false;
    $colCheck = @$conn->query("SHOW COLUMNS FROM barcodes LIKE 'delivery_id'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasDeliveryId = true;
        @$colCheck->free();
    }
    
    if ($hasDeliveryId) {
        // Use delivery_id if available
        $sql = "SELECT 
                b.barcode_id,
                b.product_id,
                b.delivery_id,
                b.barcode_value,
                p.brand_name,
                p.generic_name,
                p.strength,
                p.base_unit_type_id,
                ut.unit_name,
                d.delivery_id AS batch_id,
                d.batch_no,
                d.manufacturing_date,
                d.expiry_date,
                d.delivery_date AS received_date,
                (d.quantity_units / NULLIF(d.quantity_packages, 0)) AS units_per_package,
                COALESCE(pi.quantity_units_balance, 0) AS pharmacy_units_available,
                COALESCE(si.quantity_packages_balance, 0) AS storage_packages_available,
                COALESCE(d.selling_price_unit, 0) AS selling_price_unit,
                COALESCE(d.selling_price_package, 0) AS selling_price_package,
                COALESCE(d.cost_per_package, 0) AS cost_per_package
            FROM barcodes b
            INNER JOIN products p ON b.product_id = p.product_id
            INNER JOIN deliveries d ON b.delivery_id = d.delivery_id
            LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
            LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
            LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
            WHERE b.barcode_value = '$barcode_safe'
                AND d.status = 'Active'
            ORDER BY 
                CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
                d.expiry_date ASC,
                CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
                d.delivery_date ASC
            LIMIT 1";
    } else {
        // Fallback: use product_id from barcodes and find best delivery (FEFO/FIFO)
        $sql = "SELECT 
                b.barcode_id,
                b.product_id,
                NULL AS delivery_id,
                b.barcode_value,
                p.brand_name,
                p.generic_name,
                p.strength,
                p.base_unit_type_id,
                ut.unit_name,
                d.delivery_id AS batch_id,
                d.batch_no,
                d.manufacturing_date,
                d.expiry_date,
                d.delivery_date AS received_date,
                (d.quantity_units / NULLIF(d.quantity_packages, 0)) AS units_per_package,
                COALESCE(pi.quantity_units_balance, 0) AS pharmacy_units_available,
                COALESCE(si.quantity_packages_balance, 0) AS storage_packages_available,
                COALESCE(d.selling_price_unit, 0) AS selling_price_unit,
                COALESCE(d.selling_price_package, 0) AS selling_price_package,
                COALESCE(d.cost_per_package, 0) AS cost_per_package
            FROM barcodes b
            INNER JOIN products p ON b.product_id = p.product_id
            LEFT JOIN deliveries d ON d.product_id = p.product_id
            LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
            LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
            LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
            WHERE b.barcode_value = '$barcode_safe'
                AND d.status = 'Active'
            ORDER BY 
                CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
                d.expiry_date ASC,
                CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
                d.delivery_date ASC
            LIMIT 1";
    }

    $result = @$conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        @$result->free();
    }
}

if ($row === null) {
    // Fallback: use products.barcode and choose FEFO/FIFO delivery
    $sqlFallback = "SELECT 
            p.product_id,
            p.barcode AS barcode_value,
            p.brand_name,
            p.generic_name,
            p.strength,
            p.base_unit_type_id,
            ut.unit_name,
            d.delivery_id AS batch_id,
            d.batch_no,
            d.manufacturing_date,
            d.expiry_date,
            d.delivery_date AS received_date,
            (d.quantity_units / NULLIF(d.quantity_packages, 0)) AS units_per_package,
            COALESCE(pi.quantity_units_balance, 0) AS pharmacy_units_available,
            COALESCE(si.quantity_packages_balance, 0) AS storage_packages_available,
            COALESCE(d.selling_price_unit, 0) AS selling_price_unit,
            COALESCE(d.selling_price_package, 0) AS selling_price_package,
            COALESCE(d.cost_per_package, 0) AS cost_per_package
        FROM products p
        LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
        LEFT JOIN deliveries d ON d.product_id = p.product_id
        LEFT JOIN pharmacy_inventory pi ON pi.delivery_id = d.delivery_id
        LEFT JOIN storage_inventory si ON si.delivery_id = d.delivery_id
        WHERE p.barcode = '$barcode_safe'
            AND (d.status = 'Active' OR d.status IS NULL)
        ORDER BY 
            CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
            d.expiry_date ASC,
            CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
            d.delivery_date ASC,
            d.delivery_id ASC
        LIMIT 1";

    $res2 = @$conn->query($sqlFallback);
    if ($res2) {
        if ($res2->num_rows > 0) {
            $row = $res2->fetch_assoc();
        }
        @$res2->free();
    }
}

if ($row === null) {
    // Still nothing found
    $errMsg = isset($conn->error) && $conn->error ? (' | DB: ' . $conn->error) : '';
    @$conn->close();
    ob_clean();
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Barcode not found' . $errMsg]);
    exit;
}

// Build product name
$name = trim($row['brand_name']);
if (!empty($row['strength'])) {
    $name .= ' ' . $row['strength'];
}

// Close connection
@$conn->close();

// Determine sale type based on inventory availability
// Priority: Package sale if storage has packages, otherwise unit sale if pharmacy has units
$sale_type = 'unit'; // Default to unit sale
if ($row['storage_packages_available'] > 0) {
    $sale_type = 'package';
    $price = (float)$row['selling_price_package'];
} elseif ($row['pharmacy_units_available'] > 0) {
    $sale_type = 'unit';
    $price = (float)$row['selling_price_unit'];
} else {
    // No stock available, still return product info but mark as out of stock
    $price = (float)$row['selling_price_unit'];
}

// Success response
ob_clean();
http_response_code(200);
echo json_encode([
    'success' => true,
    'product' => [
        'id' => $row['product_id'],
        'product_id' => $row['product_id'],
        'name' => $name,
        'brand_name' => $row['brand_name'],
        'generic_name' => $row['generic_name'],
        'price' => $price,
        'selling_price_unit' => (float)$row['selling_price_unit'],
        'selling_price_package' => (float)$row['selling_price_package'],
        'barcode' => $row['barcode_value'],
        'batch_id' => isset($row['batch_id']) ? $row['batch_id'] : null,
        'batch_no' => isset($row['batch_no']) ? $row['batch_no'] : null,
        'unit_type_id' => (int)$row['base_unit_type_id'],
        'unit_name' => $row['unit_name'],
        'expiry_date' => isset($row['expiry_date']) ? $row['expiry_date'] : null,
        'received_date' => isset($row['received_date']) ? $row['received_date'] : null,
        'cost_per_package' => (float)$row['cost_per_package'],
        'units_per_package' => isset($row['units_per_package']) ? (int)$row['units_per_package'] : 0,
        // Inventory info for sale type determination
        'pharmacy_units_available' => (int)$row['pharmacy_units_available'],
        'storage_packages_available' => (int)$row['storage_packages_available'],
        'sale_type' => $sale_type, // 'unit' or 'package'
        'has_stock' => ($row['pharmacy_units_available'] > 0 || $row['storage_packages_available'] > 0)
    ]
]);
exit;

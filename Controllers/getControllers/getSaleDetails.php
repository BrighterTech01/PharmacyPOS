<?php
header('Content-Type: application/json');

// Check if connection file exists and include it
$connectionPath = __DIR__ . '/../../dbConnection/connection.php';
if (!file_exists($connectionPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection file not found']);
    exit;
}

require_once $connectionPath;

// Check if connection is established
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if (!isset($_GET['sale_id']) || empty($_GET['sale_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sale ID is required']);
    exit;
}

$sale_id = intval($_GET['sale_id']);

// Get sale information
$saleQuery = "SELECT 
    s.sale_id,
    s.receipt_number,
    s.created_at,
    s.payment_method,
    COALESCE(s.final_amount, s.total_amount, 0) as total_amount,
    s.discount_type,
    s.discount_amount,
    s.cash_recieving,
    s.change_amount,
    s.customer_name,
    CONCAT(u.firstname, ' ', u.lastname) as cashier_name,
    u.username
FROM sales s
LEFT JOIN users u ON s.user_id = u.user_id
WHERE s.sale_id = ?";

$stmt = mysqli_prepare($conn, $saleQuery);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $sale_id);
mysqli_stmt_execute($stmt);
$saleResult = mysqli_stmt_get_result($stmt);

if (!$saleResult || mysqli_num_rows($saleResult) === 0) {
    echo json_encode(['success' => false, 'error' => 'Sale not found']);
    exit;
}

$sale = mysqli_fetch_assoc($saleResult);
mysqli_stmt_close($stmt);

// Get sale items/details
$itemsQuery = "SELECT 
    sd.detail_id,
    sd.quantity_packages,
    sd.quantity_units,
    sd.unit_price,
    COALESCE(sd.final_subtotal, sd.subtotal, 0) as subtotal,
    sd.discount_type as item_discount_type,
    sd.discount_amount as item_discount_amount,
    p.product_id,
    p.brand_name,
    p.generic_name,
    p.strength,
    p.barcode,
    d.delivery_id,
    d.batch_no,
    d.expiry_date,
    ut.unit_name as sale_unit,
    DATEDIFF(d.expiry_date, CURDATE()) as days_until_expiry
FROM sales_details sd
LEFT JOIN deliveries d ON sd.delivery_id = d.delivery_id
LEFT JOIN products p ON d.product_id = p.product_id
LEFT JOIN unit_types ut ON sd.sale_unit_type_id = ut.unit_type_id
WHERE sd.sale_id = ?
ORDER BY sd.detail_id ASC";

$stmt = mysqli_prepare($conn, $itemsQuery);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $sale_id);
mysqli_stmt_execute($stmt);
$itemsResult = mysqli_stmt_get_result($stmt);

$items = [];
while ($row = mysqli_fetch_assoc($itemsResult)) {
    $items[] = [
        'detail_id' => $row['detail_id'],
        'product_id' => $row['product_id'],
        'brand_name' => $row['brand_name'] ?? 'Unknown Product',
        'generic_name' => $row['generic_name'] ?? '',
        'strength' => $row['strength'] ?? '',
        'barcode' => $row['barcode'] ?? '',
        'batch_no' => $row['batch_no'] ?? 'N/A',
        'expiry_date' => $row['expiry_date'] ?? null,
        'days_until_expiry' => $row['days_until_expiry'] ?? null,
        'quantity_packages' => intval($row['quantity_packages'] ?? 0),
        'quantity_units' => intval($row['quantity_units'] ?? 0),
        'total_quantity' => intval($row['quantity_packages'] ?? 0) + intval($row['quantity_units'] ?? 0),
        'sale_unit' => $row['sale_unit'] ?? '',
        'unit_price' => floatval($row['unit_price'] ?? 0),
        'subtotal' => floatval($row['subtotal'] ?? 0),
        'item_discount_type' => $row['item_discount_type'] ?? 'None',
        'item_discount_amount' => floatval($row['item_discount_amount'] ?? 0)
    ];
}

mysqli_stmt_close($stmt);

// Calculate totals
$totalItems = count($items);
$totalQuantity = array_sum(array_column($items, 'total_quantity'));
$totalSubtotal = array_sum(array_column($items, 'subtotal'));

echo json_encode([
    'success' => true,
    'sale' => [
        'sale_id' => $sale['sale_id'],
        'receipt_number' => $sale['receipt_number'] ?? 'N/A',
        'created_at' => $sale['created_at'],
        'cashier_name' => $sale['cashier_name'] ?? 'Unknown',
        'username' => $sale['username'] ?? 'Unknown',
        'payment_method' => $sale['payment_method'] ?? 'Cash',
        'total_amount' => floatval($sale['total_amount']),
        'discount_type' => $sale['discount_type'] ?? 'None',
        'discount_amount' => floatval($sale['discount_amount'] ?? 0),
        'cash_recieving' => floatval($sale['cash_recieving'] ?? 0),
        'change_amount' => floatval($sale['change_amount'] ?? 0),
        'customer_name' => $sale['customer_name'] ?? 'Walk-in Customer',
        'total_items' => $totalItems,
        'total_quantity' => $totalQuantity,
        'total_subtotal' => $totalSubtotal
    ],
    'items' => $items
]);
?>


<?php
/**
 * API endpoint to get receipt data in JSON format for QZ Tray printing
 * Returns sale data and items formatted for ESC/POS receipt generation
 */

header('Content-Type: application/json');

session_start();
require_once '../dbConnection/connection.php';

$sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;

if ($sale_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid sale ID'
    ]);
    exit;
}

try {
    // Get sale information
    $saleQuery = "
        SELECT 
            s.sale_id,
            s.receipt_number,
            s.total_amount,
            s.final_amount,
            s.discount_type,
            s.discount_amount,
            s.payment_method,
            s.customer_name,
            s.cash_recieving,
            s.change_amount,
            s.created_at,
            CONCAT(u.firstname, ' ', u.lastname) as cashier_name,
            u.username
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.user_id
        WHERE s.sale_id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $saleQuery);
    if (!$stmt) {
        throw new Exception('Failed to prepare sale query: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $sale_id);
    mysqli_stmt_execute($stmt);
    $saleResult = mysqli_stmt_get_result($stmt);
    
    if (!$saleResult || mysqli_num_rows($saleResult) === 0) {
        throw new Exception('Sale not found');
    }
    
    $sale = mysqli_fetch_assoc($saleResult);
    mysqli_stmt_close($stmt);
    
    // Get sale items
    $itemsQuery = "
        SELECT 
            p.brand_name,
            p.generic_name,
            p.strength,
            sd.quantity_packages,
            sd.quantity_units,
            sd.unit_price,
            sd.subtotal,
            d.batch_no
        FROM sales_details sd
        LEFT JOIN deliveries d ON sd.delivery_id = d.delivery_id
        LEFT JOIN products p ON d.product_id = p.product_id
        WHERE sd.sale_id = ?
        ORDER BY sd.detail_id ASC
    ";
    
    $stmt = mysqli_prepare($conn, $itemsQuery);
    if (!$stmt) {
        throw new Exception('Failed to prepare items query: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $sale_id);
    mysqli_stmt_execute($stmt);
    $itemsResult = mysqli_stmt_get_result($stmt);
    
    $items = [];
    while ($row = mysqli_fetch_assoc($itemsResult)) {
        // Calculate total quantity (packages + units)
        $totalQty = (int)($row['quantity_packages'] ?? 0) + (int)($row['quantity_units'] ?? 0);
        
        $items[] = [
            'brand_name' => $row['brand_name'] ?? 'Unknown',
            'generic_name' => $row['generic_name'] ?? '',
            'strength' => $row['strength'] ?? '',
            'quantity' => $totalQty,
            'quantity_packages' => (int)($row['quantity_packages'] ?? 0),
            'quantity_units' => (int)($row['quantity_units'] ?? 0),
            'price' => floatval($row['unit_price'] ?? 0),
            'subtotal' => floatval($row['subtotal'] ?? 0),
            'batch_no' => $row['batch_no'] ?? ''
        ];
    }
    mysqli_stmt_close($stmt);
    
    // Calculate subtotal from items
    $subtotal = array_sum(array_column($items, 'subtotal'));
    
    // Get discount information
    $discountType = $sale['discount_type'] ?? 'None';
    $discountAmount = floatval($sale['discount_amount'] ?? 0);
    
    // Use final_amount if available, otherwise use total_amount
    $finalTotal = floatval($sale['final_amount'] ?? $sale['total_amount']);
    
    // Format response
    $response = [
        'success' => true,
        'receipt' => [
            'sale_id' => (int)$sale['sale_id'],
            'receipt_number' => $sale['receipt_number'] ?? 'N/A',
            'date' => date('m/d/Y', strtotime($sale['created_at'])),
            'time' => date('h:i A', strtotime($sale['created_at'])),
            'cashier' => $sale['cashier_name'] ?? $sale['username'] ?? 'Cashier',
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_amount' => $discountAmount,
            'total' => floatval($sale['final_amount'] ?? $sale['total_amount']),
            'payment_method' => $sale['payment_method'] ?? 'Cash',
            'customer_name' => !empty(trim($sale['customer_name'] ?? '')) ? trim($sale['customer_name']) : 'Walk-in Customer',
            'cash_received' => isset($sale['cash_recieving']) ? floatval($sale['cash_recieving']) : null,
            'change_amount' => isset($sale['change_amount']) ? floatval($sale['change_amount']) : null
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}


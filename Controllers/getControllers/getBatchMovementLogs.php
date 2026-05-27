<?php
header('Content-Type: application/json');

include_once '../../dbConnection/connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if delivery_id is provided (accept both delivery_id and batch_id for backward compatibility)
$delivery_id = null;
if (isset($_GET['delivery_id']) && !empty($_GET['delivery_id'])) {
    $delivery_id = (int)$_GET['delivery_id'];
} elseif (isset($_GET['batch_id']) && !empty($_GET['batch_id'])) {
    // Backward compatibility: treat batch_id as delivery_id
    $delivery_id = (int)$_GET['batch_id'];
}

if (!$delivery_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Delivery ID is required']);
    exit();
}

// Fetch stock movement logs for the specific delivery
// Movement logs can reference deliveries via reference_id when reference_type = 'delivery'
// Or via stock_transfers which contain delivery_id
$query = "SELECT 
            sml.movement_id,
            sml.movement_type,
            sml.from_inventory,
            sml.to_inventory,
            sml.quantity_packages,
            sml.quantity_units,
            sml.reference_type,
            sml.reference_id,
            sml.movement_date,
            u.username,
            ut.unit_name,
            p.brand_name,
            CASE 
                WHEN sml.reference_type = 'delivery' THEN d.batch_no
                WHEN sml.reference_type = 'transfer' THEN d_transfer.batch_no
                WHEN sml.reference_type = 'sale' THEN (
                    SELECT d_sale.batch_no 
                    FROM sales_details sd_sale 
                    LEFT JOIN deliveries d_sale ON sd_sale.delivery_id = d_sale.delivery_id 
                    WHERE sd_sale.sale_id = sml.reference_id 
                    LIMIT 1
                )
                ELSE NULL
            END AS batch_no
          FROM stock_movement_log sml
          LEFT JOIN users u ON sml.user_id = u.user_id
          LEFT JOIN unit_types ut ON sml.unit_type_id = ut.unit_type_id
          LEFT JOIN products p ON sml.product_id = p.product_id
          LEFT JOIN deliveries d ON sml.reference_type = 'delivery' AND sml.reference_id = d.delivery_id
          LEFT JOIN stock_transfers st ON sml.reference_type = 'transfer' AND sml.reference_id = st.transfer_id
          LEFT JOIN deliveries d_transfer ON st.delivery_id = d_transfer.delivery_id
          WHERE (
            (sml.reference_type = 'delivery' AND sml.reference_id = ?)
            OR (sml.reference_type = 'transfer' AND st.delivery_id = ?)
            OR (sml.reference_type = 'sale' AND EXISTS (
                SELECT 1 FROM sales_details sd 
                WHERE sd.sale_id = sml.reference_id AND sd.delivery_id = ?
            ))
          )
          ORDER BY sml.movement_date DESC";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database query preparation failed: ' . mysqli_error($conn)]);
    exit();
}

mysqli_stmt_bind_param($stmt, 'iii', $delivery_id, $delivery_id, $delivery_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$logs = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
}

mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'logs' => $logs
]);
?>


<?php
// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    ob_clean();
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

// Include database connection
require_once '../../dbConnection/connection.php';

try {
    $supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

    if ($supplier_id <= 0) {
        http_response_code(400);
        ob_clean();
        die(json_encode(['success' => false, 'message' => 'Invalid supplier ID', 'deliveries' => []]));
    }

    // Fetch deliveries for this supplier
    // Join deliveries with products to get supplier_id, then filter by supplier
    $query = "SELECT 
        d.delivery_id,
        d.delivery_date,
        d.reference_no,
        d.batch_no,
        d.quantity_packages,
        d.quantity_units,
        d.total_units,
        d.cost_per_package,
        d.cost_per_unit,
        d.selling_price_package,
        d.selling_price_unit,
        d.total_cost,
        d.expiry_date,
        d.manufacturing_date,
        d.status,
        d.created_at,
        u.username,
        p.product_id,
        p.brand_name,
        p.generic_name,
        p.strength
    FROM deliveries d
    INNER JOIN products p ON d.product_id = p.product_id
    LEFT JOIN users u ON d.user_id = u.user_id
    WHERE p.supplier_id = ?
    ORDER BY d.delivery_date DESC, d.delivery_id DESC";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, 'i', $supplier_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    $deliveries = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $deliveries[] = $row;
        }
    }

    mysqli_stmt_close($stmt);

    ob_clean();
    echo json_encode([
        'success' => true,
        'deliveries' => $deliveries,
        'total' => count($deliveries)
    ]);
    exit();

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
    exit();
}
?>


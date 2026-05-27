<?php
// Prevent any output before JSON
ob_start();

// API endpoint to get products by unit type
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
    $unit_type_id = isset($_GET['unit_type_id']) ? intval($_GET['unit_type_id']) : 0;
    
    if ($unit_type_id <= 0) {
        http_response_code(400);
        ob_clean();
        die(json_encode(['success' => false, 'error' => 'Invalid unit type ID']));
    }

    // Get products in this unit type with inventory and pricing information
    // Updated to use new table structure (deliveries has product_id directly, no drug_batches)
    $sql = "SELECT 
                DISTINCT p.product_id,
                p.brand_name,
                p.generic_name,
                p.strength,
                p.barcode,
                ut.unit_name AS base_unit,
                s.supplier_name,
                c.category_name,
                pt.package_name AS package_type,
                COALESCE(inv_stats.total_storage_packages, 0) AS total_storage_packages,
                COALESCE(inv_stats.total_pharmacy_units, 0) AS total_pharmacy_units,
                COALESCE(inv_stats.total_units_available, 0) AS total_units_available,
                inv_stats.latest_expiry_date AS nearest_expiry,
                COALESCE(price_stats.latest_unit_price, 0) AS latest_unit_price,
                COALESCE(price_stats.latest_package_price, 0) AS latest_package_price
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.category_id 
            LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id 
            LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
            LEFT JOIN package_types pt ON p.package_type_id = pt.package_type_id
            LEFT JOIN (
                SELECT 
                    d.product_id,
                    SUM(COALESCE(si.quantity_packages_balance, 0)) AS total_storage_packages,
                    SUM(COALESCE(pi.quantity_units_balance, 0)) AS total_pharmacy_units,
                    SUM(COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0) + COALESCE(pi.quantity_units_balance, 0)) AS total_units_available,
                    MIN(d.expiry_date) AS latest_expiry_date
                FROM deliveries d
                LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
                LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
                WHERE d.status = 'Active'
                GROUP BY d.product_id
            ) inv_stats ON p.product_id = inv_stats.product_id
            LEFT JOIN (
                SELECT 
                    d1.product_id,
                    d1.selling_price_unit AS latest_unit_price,
                    d1.selling_price_package AS latest_package_price
                FROM deliveries d1
                INNER JOIN (
                    SELECT product_id, MAX(created_at) as max_created
                    FROM deliveries
                    WHERE status = 'Active'
                    GROUP BY product_id
                ) latest ON d1.product_id = latest.product_id AND d1.created_at = latest.max_created
                WHERE d1.status = 'Active'
            ) price_stats ON p.product_id = price_stats.product_id
            WHERE p.base_unit_type_id = ?
            ORDER BY p.brand_name ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, 'i', $unit_type_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($stmt));
    }

    $result = mysqli_stmt_get_result($stmt);
    $products = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = [
                'product_id' => (int)$row['product_id'],
                'brand_name' => $row['brand_name'],
                'generic_name' => $row['generic_name'],
                'strength' => $row['strength'],
                'barcode' => $row['barcode'],
                'base_unit' => $row['base_unit'],
                'supplier_name' => $row['supplier_name'],
                'category_name' => $row['category_name'],
                'package_type' => $row['package_type'],
                'total_storage_packages' => (int)$row['total_storage_packages'],
                'total_pharmacy_units' => (int)$row['total_pharmacy_units'],
                'total_units_available' => (int)$row['total_units_available'],
                'nearest_expiry' => $row['nearest_expiry'],
                'latest_unit_price' => (float)$row['latest_unit_price'],
                'latest_package_price' => (float)$row['latest_package_price']
            ];
        }
        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);

    ob_clean();
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products)
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


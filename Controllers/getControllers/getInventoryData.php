<?php
// API endpoint to get inventory data
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

// Include database connection
require_once '../../dbConnection/connection.php';

try {
    $response = ['success' => true, 'products' => []];

    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 20;
    $show = isset($_GET['show']) ? $_GET['show'] : 'all';

    // Build search conditions
    $conditions = [];
    if ($search !== '') {
        $searchEsc = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(brand_name LIKE '%$searchEsc%' OR generic_name LIKE '%$searchEsc%' OR batch_no LIKE '%$searchEsc%' OR barcode LIKE '%$searchEsc%')";
    }

    $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

    // Check if view_available_products exists
    $has_available_products_view = false;
    $views_check = mysqli_query($conn, "SHOW FULL TABLES WHERE TABLE_TYPE = 'VIEW'");
    if ($views_check) {
        while ($vr = mysqli_fetch_array($views_check)) {
            if (strtolower($vr[0]) === 'view_available_products') { 
                $has_available_products_view = true; 
                break; 
            }
        }
        mysqli_free_result($views_check);
    }

    if ($has_available_products_view) {
        // Use view_available_products if it exists
        $sql = "SELECT 
                    product_id,
                    brand_name,
                    generic_name,
                    strength,
                    NULL as category_name,
                    NULL as manufacturer_name,
                    batch_id,
                    batch_no,
                    expiry_date,
                    selling_price_unit as selling_price,
                    (available_units_pharmacy + available_units_storage) as available_stock,
                    barcode as barcode_value,
                    received_date
                FROM view_available_products" . $whereClause . " ORDER BY brand_name ASC, expiry_date ASC";
    } else {
        // Fallback: Get products with available stock from inventory
        $sql = "SELECT 
                    DISTINCT p.product_id,
                    p.brand_name,
                    p.generic_name,
                    p.strength,
                    c.category_name,
                    NULL as manufacturer_name,
                    db.batch_id,
                    db.batch_no,
                    db.expiry_date,
                    COALESCE(d.selling_price_unit, 0) as selling_price,
                    (COALESCE(pi.quantity_units_balance, 0) + (COALESCE(si.quantity_packages_balance, 0) * db.quantity_units)) as available_stock,
                    b.barcode_value,
                    db.received_date
                FROM products p
                LEFT JOIN Categories c ON p.category_id = c.category_id
                LEFT JOIN drug_batches db ON p.product_id = db.product_id
                LEFT JOIN pharmacy_inventory pi ON db.batch_id = pi.batch_id
                LEFT JOIN storage_inventory si ON db.batch_id = si.batch_id
                LEFT JOIN barcodes b ON db.batch_id = b.batch_id
                LEFT JOIN (
                    SELECT d1.batch_id, d1.selling_price_unit
                    FROM deliveries d1
                    INNER JOIN (
                        SELECT batch_id, MAX(created_at) as max_created
                        FROM deliveries
                        GROUP BY batch_id
                    ) d2 ON d1.batch_id = d2.batch_id AND d1.created_at = d2.max_created
                ) d ON db.batch_id = d.batch_id
                WHERE (COALESCE(pi.quantity_units_balance, 0) > 0 OR COALESCE(si.quantity_packages_balance, 0) > 0)" . 
                ($whereClause ? " AND " . str_replace("WHERE", "", $whereClause) : "") . "
                ORDER BY p.brand_name ASC, db.expiry_date ASC";
    }

    $result = mysqli_query($conn, $sql);
    if ($result) {
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Group by product_id and create batches array
            $product_id = $row['product_id'];
            if (!isset($products[$product_id])) {
                $products[$product_id] = [
                    'product_id' => $product_id,
                    'brand_name' => $row['brand_name'],
                    'generic_name' => $row['generic_name'],
                    'strength' => $row['strength'],
                    'category_name' => $row['category_name'],
                    'manufacturer_name' => $row['manufacturer_name'],
                    'batches' => []
                ];
            }
            
            $products[$product_id]['batches'][] = [
                'batch_id' => $row['batch_id'],
                'batch_no' => $row['batch_no'],
                'expiry_date' => $row['expiry_date'],
                'expiry' => $row['expiry_date'], // alias
                'selling_price' => $row['selling_price'],
                'available_stock' => $row['available_stock'],
                'current_stock' => $row['available_stock'], // alias
                'barcode_value' => $row['barcode_value'],
                'received_date' => $row['received_date'],
                'created_at' => $row['received_date'] // alias
            ];
        }
        mysqli_free_result($result);

        // Convert to array and apply filters
        $products = array_values($products);
        
        // Apply low stock filter if needed
        if ($show === 'low') {
            $products = array_filter($products, function($product) use ($threshold) {
                $totalStock = array_sum(array_column($product['batches'], 'available_stock'));
                return $totalStock <= $threshold;
            });
        }

        $response['products'] = array_values($products);
    } else {
        $response['products'] = [];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>

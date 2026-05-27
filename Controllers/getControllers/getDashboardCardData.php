<?php
header('Content-Type: application/json');
require_once '../../dbConnection/connection.php';

$action = $_GET['action'] ?? '';

// Check which views exist
function checkViewExists($conn, $viewName) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$viewName'");
    $exists = $result && mysqli_num_rows($result) > 0;
    if ($result) mysqli_free_result($result);
    return $exists;
}

$response = ['success' => false, 'data' => [], 'message' => ''];

try {
    if ($action === 'expiring') {
        // Get all expiring products (within 30 days) - use view_inventory_status_alerts
        $hasInventoryStatusView = checkViewExists($conn, 'view_inventory_status_alerts');
        
        // Get default expire threshold (only once)
        $thresholdQuery = "SELECT expire_threshold, COUNT(*) as count FROM products WHERE expire_threshold > 0 GROUP BY expire_threshold ORDER BY count DESC LIMIT 1";
        $thresholdRes = @mysqli_query($conn, $thresholdQuery);
        $expireThreshold = 30;
        if ($thresholdRes && mysqli_num_rows($thresholdRes) > 0) {
            $thresholdRow = mysqli_fetch_assoc($thresholdRes);
            $expireThreshold = (int)($thresholdRow['expire_threshold'] ?? 30);
        }
        if ($thresholdRes) mysqli_free_result($thresholdRes);
        
        if ($hasInventoryStatusView) {
            $query = "SELECT 
                        delivery_id,
                        product_id,
                        brand_name, 
                        generic_name, 
                        batch_no, 
                        expiry_date, 
                        delivery_date,
                        total_stock,
                        stock_status,
                        DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
                     FROM view_inventory_status_alerts 
                     WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL $expireThreshold DAY)
                     ORDER BY expiry_date ASC, days_until_expiry ASC";
        } else {
            // Fallback query using deliveries and inventory tables
            $query = "SELECT 
                        d.delivery_id,
                        d.product_id,
                        p.brand_name, 
                        p.generic_name, 
                        d.batch_no, 
                        d.expiry_date, 
                        d.delivery_date,
                        (COALESCE(pi.quantity_units_balance, 0) + COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0)) as total_stock,
                        CASE 
                            WHEN d.expiry_date < CURDATE() THEN 'Expired'
                            WHEN DATEDIFF(d.expiry_date, CURDATE()) <= $expireThreshold THEN 'Expiring Soon'
                            ELSE 'Active'
                        END as stock_status,
                        DATEDIFF(d.expiry_date, CURDATE()) as days_until_expiry
                     FROM deliveries d
                     LEFT JOIN products p ON d.product_id = p.product_id
                     LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
                     LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
                     WHERE d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL $expireThreshold DAY)
                        AND d.status = 'Active'
                     ORDER BY d.expiry_date ASC, days_until_expiry ASC";
        }
        
        $result = @mysqli_query($conn, $query);
        $items = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $daysBefore = isset($row['days_until_expiry']) ? (int)$row['days_until_expiry'] : 0;
                $totalStock = isset($row['total_stock']) ? (float)$row['total_stock'] : 0;
                
                $items[] = [
                    'delivery_id' => $row['delivery_id'] ?? null,
                    'product_id' => $row['product_id'] ?? null,
                    'brand_name' => $row['brand_name'] ?? 'N/A',
                    'generic_name' => $row['generic_name'] ?? 'N/A',
                    'batch_no' => $row['batch_no'] ?? 'N/A',
                    'expiry_date' => $row['expiry_date'] ?? null,
                    'delivery_date' => $row['delivery_date'] ?? null,
                    'days_until_expiry' => $daysBefore,
                    'days_before_expiry' => $daysBefore, // For backward compatibility
                    'total_stock' => $totalStock,
                    'total_units' => $totalStock, // For backward compatibility
                    'current_stock' => $totalStock, // For backward compatibility
                    'stock_status' => $row['stock_status'] ?? null
                ];
            }
            if ($result) mysqli_free_result($result);
        }
        
        $response['success'] = true;
        $response['data'] = $items;
        
    } elseif ($action === 'low_stock') {
        // Get all low stock products - use view_inventory_status_alerts
        $hasInventoryStatusView = checkViewExists($conn, 'view_inventory_status_alerts');
        
        // Get default product threshold (only once)
        $thresholdQuery = "SELECT product_threshold, COUNT(*) as count FROM products WHERE product_threshold > 0 GROUP BY product_threshold ORDER BY count DESC LIMIT 1";
        $thresholdRes = @mysqli_query($conn, $thresholdQuery);
        $defaultThreshold = 10;
        if ($thresholdRes && mysqli_num_rows($thresholdRes) > 0) {
            $thresholdRow = mysqli_fetch_assoc($thresholdRes);
            $defaultThreshold = (int)($thresholdRow['product_threshold'] ?? 10);
        }
        if ($thresholdRes) mysqli_free_result($thresholdRes);
        
        if ($hasInventoryStatusView) {
            $query = "SELECT 
                        via.delivery_id,
                        via.product_id,
                        via.brand_name,
                        via.generic_name,
                        via.batch_no,
                        via.expiry_date,
                        via.delivery_date,
                        via.total_stock,
                        via.stock_status,
                        COALESCE(p.product_threshold, $defaultThreshold) as threshold
                     FROM view_inventory_status_alerts via
                     LEFT JOIN products p ON via.product_id = p.product_id
                     WHERE via.stock_status = 'Low Stock' OR (via.total_stock <= COALESCE(p.product_threshold, $defaultThreshold) AND via.total_stock > 0)
                     ORDER BY via.total_stock ASC";
        } else {
            // Fallback query using deliveries and inventory tables
            $query = "SELECT 
                        d.delivery_id,
                        d.product_id,
                        p.brand_name,
                        p.generic_name,
                        d.batch_no,
                        d.expiry_date,
                        d.delivery_date,
                        (COALESCE(pi.quantity_units_balance, 0) + COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0)) as total_stock,
                        CASE 
                            WHEN (COALESCE(pi.quantity_units_balance, 0) + COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0)) = 0 THEN 'Out of Stock'
                            WHEN (COALESCE(pi.quantity_units_balance, 0) + COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0)) <= COALESCE(p.product_threshold, $defaultThreshold) THEN 'Low Stock'
                            ELSE 'In Stock'
                        END as stock_status,
                        COALESCE(p.product_threshold, $defaultThreshold) as threshold
                     FROM deliveries d
                     LEFT JOIN products p ON d.product_id = p.product_id
                     LEFT JOIN pharmacy_inventory pi ON d.delivery_id = pi.delivery_id
                     LEFT JOIN storage_inventory si ON d.delivery_id = si.delivery_id
                     WHERE (COALESCE(pi.quantity_units_balance, 0) + COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0)) <= COALESCE(p.product_threshold, $defaultThreshold)
                        AND (COALESCE(pi.quantity_units_balance, 0) + COALESCE(si.quantity_packages_balance, 0) * COALESCE(d.quantity_units / NULLIF(d.quantity_packages, 0), 0)) > 0
                        AND d.status = 'Active'
                     ORDER BY total_stock ASC";
        }
        
        $result = @mysqli_query($conn, $query);
        $items = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $items[] = [
                    'delivery_id' => $row['delivery_id'] ?? null,
                    'product_id' => $row['product_id'] ?? null,
                    'brand_name' => $row['brand_name'] ?? 'N/A',
                    'generic_name' => $row['generic_name'] ?? 'N/A',
                    'batch_no' => $row['batch_no'] ?? 'N/A',
                    'expiry_date' => $row['expiry_date'] ?? null,
                    'delivery_date' => $row['delivery_date'] ?? null,
                    'total_stock' => (float)($row['total_stock'] ?? 0),
                    'current_stock' => (float)($row['total_stock'] ?? 0), // For backward compatibility
                    'threshold' => (float)($row['threshold'] ?? 10),
                    'stock_status' => $row['stock_status'] ?? null
                ];
            }
            if ($result) mysqli_free_result($result);
        }
        
        $response['success'] = true;
        $response['data'] = $items;
        
    } elseif ($action === 'today_sales') {
        // Get all today's sales
        $query = "SELECT 
                    s.sale_id,
                    s.user_id,
                    CONCAT(u.firstname, ' ', u.lastname) as cashier_name,
                    s.total_amount,
                    s.created_at as sale_date,
                    COUNT(sd.detail_id) as item_count,
                    GROUP_CONCAT(DISTINCT COALESCE(p.brand_name, p.generic_name, 'Unknown Product') ORDER BY p.brand_name SEPARATOR ', ') as products
                  FROM sales s
                  LEFT JOIN users u ON s.user_id = u.user_id
                  LEFT JOIN sales_details sd ON s.sale_id = sd.sale_id
                  LEFT JOIN deliveries d ON sd.delivery_id = d.delivery_id
                  LEFT JOIN products p ON d.product_id = p.product_id
                  WHERE DATE(s.created_at) = CURDATE()
                  GROUP BY s.sale_id, s.user_id, u.firstname, u.lastname, s.total_amount, s.created_at
                  ORDER BY s.created_at DESC";
        
        $result = @mysqli_query($conn, $query);
        $sales = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $sales[] = [
                    'sale_id' => (int)$row['sale_id'],
                    'cashier_name' => $row['cashier_name'] ?? 'Unknown',
                    'total_amount' => (float)$row['total_amount'],
                    'sale_date' => $row['sale_date'],
                    'item_count' => (int)($row['item_count'] ?? 0),
                    'products' => $row['products'] ?? 'No products'
                ];
            }
            mysqli_free_result($result);
        }
        
        $response['success'] = true;
        $response['data'] = $sales;
        
    } else {
        $response['message'] = 'Invalid action';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Dashboard Card Data Error: ' . $e->getMessage());
}

echo json_encode($response);
mysqli_close($conn);
?>


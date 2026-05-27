<?php
// API endpoint to get dashboard data for Staff Dashboard
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
    $response = ['success' => true, 'data' => []];
    
    // Cache available database views for reuse
    $availableViews = [];
    $viewsResult = @mysqli_query($conn, "SHOW FULL TABLES WHERE TABLE_TYPE = 'VIEW'");
    if ($viewsResult) {
        while ($viewRow = mysqli_fetch_array($viewsResult)) {
            $availableViews[strtolower($viewRow[0])] = true;
        }
        mysqli_free_result($viewsResult);
    }

    $has_sales_analytics_view = isset($availableViews['view_sales_analytics']);
    $has_products_for_sale_view = isset($availableViews['view_products_for_sale']);
    $has_inventory_alerts_view = isset($availableViews['view_inventory_status_alerts']);
    $has_expiring_view = isset($availableViews['view_expiring_soon_details']);
    $has_available_products_view = isset($availableViews['view_available_products']);
    $has_stock_report_view = isset($availableViews['view_stock_report']);
    $has_batch_priority_view = isset($availableViews['view_batch_priority']);

    // Count Total Items Sold Today from view_sales_analytics or fallback
    if ($has_sales_analytics_view) {
        $total_items_sold_today_query = "SELECT COALESCE(SUM(quantity_packages + quantity_units), 0) AS total FROM view_sales_analytics WHERE DATE(sale_date) = CURDATE()";
    } else {
        // Fallback query using sales_details directly
        $total_items_sold_today_query = "SELECT COALESCE(SUM(quantity_packages + quantity_units), 0) AS total FROM sales_details sd INNER JOIN sales s ON sd.sale_id = s.sale_id WHERE DATE(s.created_at) = CURDATE()";
    }
    $total_items_sold_today_result = mysqli_query($conn, $total_items_sold_today_query);
    if ($total_items_sold_today_result) {
        $total_items_sold_today_row = mysqli_fetch_assoc($total_items_sold_today_result);
        $response['data']['total_items_sold_today'] = (int)$total_items_sold_today_row['total'];
    } else {
        $response['data']['total_items_sold_today'] = 0;
    }

    // Sales Summary - Today's Revenue from view_sales_analytics or fallback
    if ($has_sales_analytics_view) {
        $sales_revenue_today_query = "SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM view_sales_analytics WHERE DATE(sale_date) = CURDATE()";
    } else {
        // Fallback query using sales table directly
        $sales_revenue_today_query = "SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM sales WHERE DATE(created_at) = CURDATE()";
    }
    $sales_revenue_today_result = mysqli_query($conn, $sales_revenue_today_query);
    if ($sales_revenue_today_result) {
        $sales_revenue_today_row = mysqli_fetch_assoc($sales_revenue_today_result);
        $response['data']['sales_revenue_today'] = (float)$sales_revenue_today_row['revenue'];
    } else {
        $response['data']['sales_revenue_today'] = 0.0;
    }

    // Sales Summary - Today's Transactions Count
    if ($has_sales_analytics_view) {
        $sales_transactions_today_query = "SELECT COUNT(DISTINCT sale_id) AS count FROM view_sales_analytics WHERE DATE(sale_date) = CURDATE()";
    } else {
        // Fallback query using sales table directly
        $sales_transactions_today_query = "SELECT COUNT(DISTINCT sale_id) AS count FROM sales WHERE DATE(created_at) = CURDATE()";
    }
    $sales_transactions_today_result = mysqli_query($conn, $sales_transactions_today_query);
    if ($sales_transactions_today_result) {
        $sales_transactions_today_row = mysqli_fetch_assoc($sales_transactions_today_result);
        $response['data']['sales_transactions_today'] = (int)$sales_transactions_today_row['count'];
    } else {
        $response['data']['sales_transactions_today'] = 0;
    }

    // Sales Summary - This Month's Revenue
    if ($has_sales_analytics_view) {
        $sales_revenue_month_query = "SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM view_sales_analytics WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE())";
    } else {
        // Fallback query using sales table directly
        $sales_revenue_month_query = "SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM sales WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
    }
    $sales_revenue_month_result = mysqli_query($conn, $sales_revenue_month_query);
    if ($sales_revenue_month_result) {
        $sales_revenue_month_row = mysqli_fetch_assoc($sales_revenue_month_result);
        $response['data']['sales_revenue_month'] = (float)$sales_revenue_month_row['revenue'];
    } else {
        $response['data']['sales_revenue_month'] = 0.0;
    }
    
    // Get default threshold for low stock (from products table)
    $defaultProductThreshold = 10;
    $threshold_query = @mysqli_query($conn, "SELECT product_threshold FROM products WHERE product_threshold IS NOT NULL AND product_threshold > 0 ORDER BY product_threshold ASC LIMIT 1");
    if ($threshold_query && mysqli_num_rows($threshold_query) > 0) {
        $threshold_row = mysqli_fetch_assoc($threshold_query);
        $defaultProductThreshold = (int)($threshold_row['product_threshold'] ?? 10);
        mysqli_free_result($threshold_query);
    }

    // Count Expiring Alerts (next 30 days)
    if ($has_products_for_sale_view) {
        $expiring_alerts_query = "SELECT COUNT(DISTINCT CONCAT(product_id, '-', batch_no)) AS total FROM view_products_for_sale WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND availability_status != 'Expired'";
    } elseif ($has_inventory_alerts_view) {
        $expiring_alerts_query = "SELECT COUNT(DISTINCT CONCAT(product_id, '-', batch_no)) AS total FROM view_inventory_status_alerts WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND stock_status NOT IN ('Expired', 'Out of Stock')";
    } elseif ($has_expiring_view) {
        $expiring_alerts_query = "SELECT COUNT(*) AS total FROM view_expiring_soon_details";
    } elseif ($has_batch_priority_view) {
        $expiring_alerts_query = "SELECT COUNT(*) AS total FROM view_batch_priority WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    } else {
        $expiring_alerts_query = false;
    }
    $expiring_alerts_result = $expiring_alerts_query ? @mysqli_query($conn, $expiring_alerts_query) : false;
    if ($expiring_alerts_result) {
        $expiring_alerts_row = mysqli_fetch_assoc($expiring_alerts_result);
        $response['data']['total_expiring_alerts'] = (int)$expiring_alerts_row['total'];
        mysqli_free_result($expiring_alerts_result);
    } else {
        $response['data']['total_expiring_alerts'] = 0;
    }

    // Count Low Stock Alerts
    if ($has_products_for_sale_view) {
        $low_stock_alerts_query = "SELECT COUNT(DISTINCT CONCAT(product_id, '-', batch_no)) AS total FROM view_products_for_sale WHERE (storage_packages_balance + pharmacy_units_balance) > 0 AND (storage_packages_balance + pharmacy_units_balance) <= $defaultProductThreshold AND availability_status != 'Expired'";
    } elseif ($has_inventory_alerts_view) {
        $low_stock_alerts_query = "SELECT COUNT(*) AS total FROM view_inventory_status_alerts WHERE stock_status IN ('Low Stock', 'Critical')";
    } else {
        $low_stock_alerts_query = "SELECT COUNT(*) AS total FROM view_low_stock";
    }
    $low_stock_alerts_result = @mysqli_query($conn, $low_stock_alerts_query);
    if ($low_stock_alerts_result) {
        $low_stock_alerts_row = mysqli_fetch_assoc($low_stock_alerts_result);
        $response['data']['total_low_stock_alerts'] = (int)$low_stock_alerts_row['total'];
        mysqli_free_result($low_stock_alerts_result);
    } else {
        $response['data']['total_low_stock_alerts'] = 0;
    }

    // Count Products Available (distinct products with stock > 0)
    if ($has_products_for_sale_view) {
        $products_available_query = "SELECT COUNT(DISTINCT product_id) AS total FROM view_products_for_sale WHERE availability_status = 'Available'";
    } elseif ($has_inventory_alerts_view) {
        $products_available_query = "SELECT COUNT(DISTINCT product_id) AS total FROM view_inventory_status_alerts WHERE total_stock > 0 AND stock_status NOT IN ('Out of Stock', 'Expired')";
    } elseif ($has_available_products_view) {
        $products_available_query = "SELECT COUNT(*) AS total FROM (SELECT DISTINCT product_id FROM view_available_products WHERE available_stock > 0) t";
    } elseif ($has_batch_priority_view) {
        $products_available_query = "SELECT COUNT(*) AS total FROM (SELECT DISTINCT product_id FROM view_batch_priority WHERE available_stock > 0) t";
    } else {
        $products_available_query = false;
    }
    $products_available_result = $products_available_query ? @mysqli_query($conn, $products_available_query) : false;
    if ($products_available_result) {
        $products_available_row = mysqli_fetch_assoc($products_available_result);
        $response['data']['total_products_available'] = (int)$products_available_row['total'];
        mysqli_free_result($products_available_result);
    } else {
        $response['data']['total_products_available'] = 0;
    }

    // Low stock items table - use view_products_for_sale
    $low_stock_items = [];
    if ($has_products_for_sale_view) {
        $result = @mysqli_query($conn, "SELECT brand_name, generic_name, batch_no, expiry_date, (storage_packages_balance + pharmacy_units_balance) as current_stock, NULL as delivery_id, NULL as product_id, NULL as delivery_date, CASE WHEN (storage_packages_balance + pharmacy_units_balance) <= 3 THEN 'Critical' ELSE 'Low Stock' END AS stock_status FROM view_products_for_sale WHERE (storage_packages_balance + pharmacy_units_balance) > 0 AND (storage_packages_balance + pharmacy_units_balance) <= $defaultProductThreshold AND availability_status != 'Expired' ORDER BY (storage_packages_balance + pharmacy_units_balance) ASC, (expiry_date IS NULL), expiry_date ASC");
    } elseif ($has_inventory_alerts_view) {
        $result = @mysqli_query($conn, "SELECT delivery_id, product_id, brand_name, generic_name, batch_no, expiry_date, delivery_date, total_stock AS current_stock, stock_status FROM view_inventory_status_alerts WHERE stock_status IN ('Low Stock', 'Critical') ORDER BY total_stock ASC, (expiry_date IS NULL), expiry_date ASC, brand_name ASC");
    } elseif ($has_stock_report_view) {
        $result = @mysqli_query($conn, "SELECT NULL AS delivery_id, NULL AS product_id, brand_name, generic_name, batch_no, expiry_date, NULL AS delivery_date, total_units AS current_stock, stock_status FROM view_stock_report WHERE stock_status = 'Low Stock' ORDER BY total_units ASC, (expiry_date IS NULL), expiry_date ASC");
    } elseif ($has_available_products_view) {
        $result = @mysqli_query($conn, "SELECT NULL AS delivery_id, NULL AS product_id, brand_name, generic_name, batch_no, expiry_date, NULL AS delivery_date, (available_units_pharmacy + available_units_storage) AS current_stock, CASE WHEN (available_units_pharmacy + available_units_storage) <= 3 THEN 'Critical' ELSE 'Low Stock' END AS stock_status FROM view_available_products WHERE (available_units_pharmacy + available_units_storage) < 10 AND status != 'Out of Stock' ORDER BY (available_units_pharmacy + available_units_storage) ASC, (expiry_date IS NULL), expiry_date ASC");
    } else {
        $result = false;
    }
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $low_stock_items[] = $row;
        }
        mysqli_free_result($result);
    }
    $response['data']['low_stock_items'] = $low_stock_items;

    // Expiring soon items - use view_products_for_sale
    $view_batch_priority_items = [];
    if ($has_products_for_sale_view) {
        $result = @mysqli_query($conn, "SELECT brand_name, generic_name, batch_no, expiry_date, (storage_packages_balance + pharmacy_units_balance) as available_stock, DATEDIFF(expiry_date, CURDATE()) as days_until_expiry, NULL as delivery_id, NULL as product_id, NULL as delivery_date, CASE WHEN (storage_packages_balance + pharmacy_units_balance) <= 3 THEN 'Critical' ELSE 'Low Stock' END AS stock_status FROM view_products_for_sale WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND availability_status != 'Expired' ORDER BY expiry_date ASC");
    } elseif ($has_inventory_alerts_view) {
        $result = @mysqli_query($conn, "SELECT delivery_id, product_id, brand_name, generic_name, batch_no, expiry_date, delivery_date, total_stock AS available_stock, DATEDIFF(expiry_date, CURDATE()) AS days_until_expiry, stock_status FROM view_inventory_status_alerts WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND stock_status NOT IN ('Expired', 'Out of Stock') ORDER BY expiry_date ASC, brand_name ASC");
    } elseif ($has_expiring_view) {
        $result = @mysqli_query($conn, "SELECT * FROM view_expiring_soon_details ORDER BY expiry_date ASC");
    } elseif ($has_batch_priority_view) {
        $result = @mysqli_query($conn, "SELECT * FROM view_batch_priority ORDER BY expiry_date ASC");
    } else {
        $result = false;
    }
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $view_batch_priority_items[] = $row;
        }
        mysqli_free_result($result);
    }
    $response['data']['expiring_items'] = $view_batch_priority_items;

    // Available products list - use view_products_for_sale
    $available_products_items = [];
    if ($has_products_for_sale_view) {
        $result = @mysqli_query($conn, "SELECT brand_name, generic_name, batch_no, expiry_date, (storage_packages_balance + pharmacy_units_balance) as available_stock, NULL as delivery_id, NULL as product_id, NULL as delivery_date, 'Available' as stock_status FROM view_products_for_sale WHERE availability_status = 'Available' ORDER BY brand_name ASC, expiry_date ASC");
    } elseif ($has_inventory_alerts_view) {
        $result = @mysqli_query($conn, "SELECT delivery_id, product_id, brand_name, generic_name, batch_no, expiry_date, delivery_date, total_stock AS available_stock, stock_status FROM view_inventory_status_alerts WHERE total_stock > 0 AND stock_status NOT IN ('Out of Stock', 'Expired') ORDER BY brand_name ASC, expiry_date ASC");
    } elseif ($has_available_products_view) {
        $result = @mysqli_query($conn, "SELECT brand_name, generic_name, batch_no, expiry_date, (available_units_pharmacy + available_units_storage) as available_stock, NULL as delivery_id, NULL as product_id, NULL as delivery_date, CASE WHEN status = 'Available' THEN 'Available' ELSE status END AS stock_status FROM view_available_products WHERE (available_units_pharmacy + available_units_storage) > 0 AND status != 'Out of Stock' ORDER BY brand_name ASC, expiry_date ASC");
    } elseif ($has_stock_report_view) {
        $result = @mysqli_query($conn, "SELECT NULL AS delivery_id, NULL AS product_id, brand_name, NULL AS generic_name, NULL AS batch_no, latest_expiry AS expiry_date, NULL AS delivery_date, total_units AS available_stock, stock_status FROM view_stock_report WHERE stock_status != 'Out of Stock' ORDER BY brand_name ASC, latest_expiry ASC");
            } else {
                $result = false; // No data available
    }
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $available_products_items[] = $row;
        }
        mysqli_free_result($result);
    }
    $response['data']['available_products_items'] = $available_products_items;

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    error_log('Dashboard API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching dashboard data',
        'debug' => $e->getMessage()
    ]);
} catch (Error $e) {
    http_response_code(500);
    error_log('Dashboard API Fatal Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'A fatal error occurred while fetching dashboard data',
        'debug' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>

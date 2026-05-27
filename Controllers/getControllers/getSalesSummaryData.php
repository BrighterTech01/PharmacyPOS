<?php
// API endpoint to get sales summary data
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

    // Get query parameters
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'available';
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $limit;
    $from = isset($_GET['from']) ? trim($_GET['from']) : '';
    $to = isset($_GET['to']) ? trim($_GET['to']) : '';

    // Build search conditions
    $conditionsAvailable = [];
    $conditionsMovement = [];
    $conditionsCashier = [];

    if ($q !== '') {
        $qEsc = mysqli_real_escape_string($conn, $q);
            $conditionsAvailable[] = "(brand_name LIKE '%$qEsc%' OR generic_name LIKE '%$qEsc%' OR batch_no LIKE '%$qEsc%' OR barcode LIKE '%$qEsc%')";
            $conditionsMovement[] = "(brand_name LIKE '%$qEsc%' OR generic_name LIKE '%$qEsc%' OR batch_no LIKE '%$qEsc%')";
    }
    if ($from !== '') {
        $fromEsc = mysqli_real_escape_string($conn, $from);
        $conditionsCashier[] = "DATE(sale_date) >= '$fromEsc'";
    }
    if ($to !== '') {
        $toEsc = mysqli_real_escape_string($conn, $to);
        $conditionsCashier[] = "DATE(sale_date) <= '$toEsc'";
    }

    function buildWhere($conds) { 
        return !empty($conds) ? (' WHERE ' . implode(' AND ', $conds)) : ''; 
    }

    // KPIs
    $kpi_sales_today = 0.0;
    $kpi_tx_today = 0;
    $kpi_low_stock = 0;
    $kpi_revenue_month = 0.0;

    // Use view_dashboard_sales_summary for daily totals
    $res = mysqli_query($conn, "SELECT total_transactions, total_sales_amount FROM view_dashboard_sales_summary WHERE sale_date = CURDATE() LIMIT 1");
    if ($res) {
        $r = mysqli_fetch_assoc($res);
        $kpi_sales_today = (float)($r['total_sales_amount'] ?? 0);
        $kpi_tx_today = (int)($r['total_transactions'] ?? 0);
        mysqli_free_result($res);
    }

    // Low stock from view_available_products - calculate available stock from units
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM view_available_products WHERE (available_units_pharmacy + available_units_storage) < 10 AND status != 'Out of Stock'");
    if ($res) { 
        $r = mysqli_fetch_assoc($res); 
        $kpi_low_stock = (int)($r['c'] ?? 0); 
        mysqli_free_result($res); 
    }

    // Monthly revenue from view_dashboard_sales_summary
    $res = mysqli_query($conn, "SELECT COALESCE(SUM(total_sales_amount),0) AS v FROM view_dashboard_sales_summary WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())");
    if ($res) { 
        $r = mysqli_fetch_assoc($res); 
        $kpi_revenue_month = (float)($r['v'] ?? 0); 
        mysqli_free_result($res); 
    }

    $response['data']['kpis'] = [
        'total_sales_today' => $kpi_sales_today,
        'total_transactions_today' => $kpi_tx_today,
        'low_stock_items' => $kpi_low_stock,
        'total_revenue_month' => $kpi_revenue_month
    ];

    // Check which views exist
    $has_available_products_view = false;
    $has_stock_card_view = false;
    
    $views_check = mysqli_query($conn, "SHOW FULL TABLES WHERE TABLE_TYPE = 'VIEW'");
    if ($views_check) {
        while ($vr = mysqli_fetch_array($views_check)) {
            $view_name = strtolower($vr[0]);
            if ($view_name === 'view_available_products') $has_available_products_view = true;
            if ($view_name === 'view_stock_card') $has_stock_card_view = true;
        }
        mysqli_free_result($views_check);
    }

    // Handle different tabs
    if ($tab === 'available') {
        $wa = buildWhere($conditionsAvailable);
        
        if ($has_available_products_view) {
            $sqlCount = "SELECT COUNT(*) AS c FROM view_available_products" . $wa;
            $sql = "SELECT product_id, brand_name, generic_name, strength, NULL as category_name, NULL as manufacturer_name, batch_id, batch_no, expiry_date, selling_price_unit as selling_price, (available_units_pharmacy + available_units_storage) as available_stock, barcode FROM view_available_products" . $wa . " ORDER BY brand_name ASC LIMIT $limit OFFSET $offset";
        } else {
            // Fallback query
            $sqlCount = "SELECT COUNT(*) AS c FROM products p LEFT JOIN categories c ON p.category_id = c.category_id LEFT JOIN manufacturers m ON p.manufacturer_id = m.manufacturer_id LEFT JOIN drug_batches db ON p.product_id = db.product_id" . $wa;
            // Fallback query using new schema - pricing from deliveries, stock from inventory
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
                        b.barcode_value 
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
                    ) d ON db.batch_id = d.batch_id" . 
                    $wa . " 
                    ORDER BY p.brand_name ASC LIMIT $limit OFFSET $offset";
        }
        
        $res = mysqli_query($conn, $sqlCount); 
        if ($res) { 
            $r = mysqli_fetch_assoc($res); 
            $total = (int)($r['c'] ?? 0); 
            mysqli_free_result($res); 
        } else {
            $total = 0;
        }

        $res = mysqli_query($conn, $sql);
        $rows = [];
        if ($res) { 
            while ($row = mysqli_fetch_assoc($res)) { 
                $rows[] = $row; 
            } 
            mysqli_free_result($res); 
        }

        $response['data']['available'] = [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];

    } elseif ($tab === 'movement') {
        $wm = buildWhere($conditionsMovement);
        
        if ($has_stock_card_view) {
            $sqlCount = "SELECT COUNT(*) AS c FROM view_stock_card" . $wm;
            $sql = "SELECT product_id, brand_name, generic_name, batch_id, batch_no, cost_price, selling_price, total_in, total_out, total_adjustment, current_stock FROM view_stock_card" . $wm . " ORDER BY batch_id DESC LIMIT $limit OFFSET $offset";
        } else {
            // Fallback query - simple stock data
            $sqlCount = "SELECT COUNT(*) AS c FROM products p" . $wm;
            // Fallback query using new schema
            $sql = "SELECT 
                        p.product_id, 
                        p.brand_name, 
                        p.generic_name, 
                        NULL as batch_id, 
                        'N/A' as batch_no, 
                        COALESCE(d.cost_per_package, 0) as cost_price, 
                        COALESCE(d.selling_price_unit, 0) as selling_price, 
                        0 as total_in, 
                        0 as total_out, 
                        0 as total_adjustment, 
                        0 as current_stock 
                    FROM products p
                    LEFT JOIN (
                        SELECT db1.product_id, 
                               MAX(d1.created_at) as latest_delivery,
                               MAX(d1.cost_per_package) as cost_per_package,
                               MAX(d1.selling_price_unit) as selling_price_unit
                        FROM deliveries d1
                        LEFT JOIN drug_batches db1 ON d1.batch_id = db1.batch_id
                        GROUP BY db1.product_id
                    ) d ON p.product_id = d.product_id" . 
                    $wm . " 
                    ORDER BY p.brand_name DESC LIMIT $limit OFFSET $offset";
        }
        
        $res = mysqli_query($conn, $sqlCount); 
        if ($res) { 
            $r = mysqli_fetch_assoc($res); 
            $total = (int)($r['c'] ?? 0); 
            mysqli_free_result($res); 
        } else {
            $total = 0;
        }

        $res = mysqli_query($conn, $sql);
        $rows = [];
        if ($res) { 
            while ($row = mysqli_fetch_assoc($res)) { 
                $rows[] = $row; 
            } 
            mysqli_free_result($res); 
        }

        $response['data']['movement'] = [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];

    } else { // cashier
        $wc = buildWhere($conditionsCashier);
        
        // Use view_sales_analytics to group by cashier and date
        $sqlCount = "SELECT COUNT(DISTINCT CONCAT(cashier_name, '_', DATE(sale_date))) AS c FROM view_sales_analytics" . $wc;
        $sql = "SELECT 
                    cashier_name, 
                    DATE(sale_date) as sale_date,
                    COUNT(DISTINCT sale_id) as total_transactions, 
                    SUM(total_amount) as total_sales, 
                    SUM(COALESCE(quantity_packages, 0) + COALESCE(quantity_units, 0)) as total_items_sold
                FROM view_sales_analytics" . $wc . " 
                GROUP BY cashier_name, DATE(sale_date) 
                ORDER BY DATE(sale_date) DESC, cashier_name ASC 
                LIMIT $limit OFFSET $offset";
        
        $res = mysqli_query($conn, $sqlCount); 
        if ($res) { 
            $r = mysqli_fetch_assoc($res); 
            $total = (int)($r['c'] ?? 0); 
            mysqli_free_result($res); 
        } else {
            $total = 0;
        }

        $res = mysqli_query($conn, $sql);
        $rows = [];
        if ($res) { 
            while ($row = mysqli_fetch_assoc($res)) { 
                $rows[] = $row; 
            } 
            mysqli_free_result($res); 
        }

        $response['data']['cashier'] = [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
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

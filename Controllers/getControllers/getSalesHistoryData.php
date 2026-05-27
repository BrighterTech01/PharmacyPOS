<?php
// API endpoint to get sales history data
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
    $response = ['success' => true, 'sales' => []];

    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $from = isset($_GET['from']) ? trim($_GET['from']) : '';
    $to = isset($_GET['to']) ? trim($_GET['to']) : '';

    // Build search conditions
    $conditions = [];
    if ($search !== '') {
        $searchEsc = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(sale_id LIKE '%$searchEsc%' OR receipt_number LIKE '%$searchEsc%' OR cashier_name LIKE '%$searchEsc%')";
    }
    if ($from !== '') {
        $fromEsc = mysqli_real_escape_string($conn, $from);
        $conditions[] = "DATE(sale_date) >= '$fromEsc'";
    }
    if ($to !== '') {
        $toEsc = mysqli_real_escape_string($conn, $to);
        $conditions[] = "DATE(sale_date) <= '$toEsc'";
    }

    $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

    // Use view_sales_analytics for sales history
    $sql = "SELECT 
                sale_id,
                sale_date,
                'Walk-in Customer' as customer_name,
                total_amount,
                payment_method,
                'Completed' as status,
                cashier_name,
                COUNT(DISTINCT detail_id) as total_items,
                GROUP_CONCAT(
                    CONCAT(
                        '{\"product_name\":\"', COALESCE(brand_name, generic_name, 'Unknown'), '\",',
                        '\"quantity\":', COALESCE(quantity_packages, 0) + COALESCE(quantity_units, 0), ',',
                        '\"price\":', COALESCE(unit_price, 0), ',',
                        '\"qty\":', COALESCE(quantity_packages, 0) + COALESCE(quantity_units, 0)
                    ) SEPARATOR '|'
                ) as items_json
            FROM view_sales_analytics" . $whereClause . "
            GROUP BY sale_id, sale_date, total_amount, cashier_name, payment_method
            ORDER BY sale_date DESC";
    
    // Fallback if view doesn't exist
    $has_view = false;
    $views_check = mysqli_query($conn, "SHOW FULL TABLES WHERE TABLE_TYPE = 'VIEW'");
    if ($views_check) {
        while ($vr = mysqli_fetch_array($views_check)) {
            if (strtolower($vr[0]) === 'view_sales_analytics') { 
                $has_view = true; 
                break; 
            }
        }
        mysqli_free_result($views_check);
    }
    
    if (!$has_view) {
        // Fallback to sales table with joins
        $sql = "SELECT 
                    s.sale_id,
                    s.sale_date,
                    'Walk-in Customer' as customer_name,
                    s.total_amount,
                    'Cash' as payment_method,
                    'Completed' as status,
                    u.username as cashier_name,
                    COUNT(sd.sale_detail_id) as total_items,
                    GROUP_CONCAT(
                        CONCAT(
                            '{\"product_name\":\"', COALESCE(p.brand_name, p.generic_name, 'Unknown'), '\",',
                            '\"quantity\":', COALESCE(sd.quantity, 0), ',',
                            '\"price\":', COALESCE(sd.price, 0), ',',
                            '\"qty\":', COALESCE(sd.quantity, 0)
                        ) SEPARATOR '|'
                    ) as items_json
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.user_id
                LEFT JOIN sales_details sd ON s.sale_id = sd.sale_id
                LEFT JOIN products p ON sd.product_id = p.product_id" . $whereClause . "
                GROUP BY s.sale_id, s.sale_date, s.total_amount, u.username
                ORDER BY s.sale_date DESC";
    }

    $result = mysqli_query($conn, $sql);
    if ($result) {
        $sales = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Parse items JSON
            $items = [];
            if (!empty($row['items_json'])) {
                $items_array = explode('|', $row['items_json']);
                foreach ($items_array as $item_json) {
                    $item = json_decode($item_json, true);
                    if ($item && isset($item['product_name'])) {
                        $items[] = [
                            'product_name' => $item['product_name'],
                            'name' => $item['product_name'], // alias
                            'quantity' => (int)($item['quantity'] ?? 0),
                            'qty' => (int)($item['qty'] ?? $item['quantity'] ?? 0), // alias
                            'price' => (float)($item['price'] ?? 0)
                        ];
                    }
                }
            }
            
            // If no items were parsed, create a fallback item
            if (empty($items)) {
                $items = [
                    [
                        'product_name' => 'Sale Item',
                        'name' => 'Sale Item',
                        'quantity' => (int)$row['total_items'],
                        'qty' => (int)$row['total_items'],
                        'price' => (float)$row['total_amount']
                    ]
                ];
            }

            $sales[] = [
                'sale_id' => $row['sale_id'],
                'sale_date' => $row['sale_date'],
                'customer_name' => $row['customer_name'],
                'total_amount' => (float)$row['total_amount'],
                'payment' => $row['payment_method'],
                'status' => $row['status'],
                'cashier_name' => $row['cashier_name'],
                'total_items' => (int)$row['total_items'],
                'items' => $items
            ];
        }
        mysqli_free_result($result);
        $response['sales'] = $sales;
    } else {
        $response['sales'] = [];
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

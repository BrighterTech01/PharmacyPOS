<?php
// API endpoint to get categories
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
    $response = ['success' => true, 'categories' => []];

    // Fetch all categories from database
    $sql = "SELECT category_id, category_name, status FROM Categories ORDER BY category_name ASC";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $categories = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = [
                'category_id' => (int)$row['category_id'],
                'category_name' => $row['category_name'],
                'status' => $row['status'] ?? 'Active'
            ];
        }
        mysqli_free_result($result);
        $response['categories'] = $categories;
    } else {
        $response['categories'] = [];
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


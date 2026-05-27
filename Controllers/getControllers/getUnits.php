<?php
// API endpoint to get unit types
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
    $response = ['success' => true, 'units' => []];

    // Fetch all unit types from database
    $sql = "SELECT unit_type_id, unit_name, status FROM unit_types ORDER BY unit_name ASC";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $units = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $units[] = [
                'unit_id' => (int)$row['unit_type_id'],
                'unit_name' => $row['unit_name'],
                'status' => $row['status'] ?? 'Active'
            ];
        }
        mysqli_free_result($result);
        $response['units'] = $units;
    } else {
        $response['units'] = [];
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


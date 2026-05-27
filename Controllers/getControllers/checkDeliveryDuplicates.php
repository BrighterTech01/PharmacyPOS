<?php
// API endpoint to check for duplicate batch_no and reference_no
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
    $batch_no = isset($_GET['batch_no']) ? trim($_GET['batch_no']) : '';
    $reference_no = isset($_GET['reference_no']) ? trim($_GET['reference_no']) : '';
    $delivery_id = isset($_GET['delivery_id']) ? (int)$_GET['delivery_id'] : 0; // For edit mode - exclude current delivery
    
    $response = [
        'success' => true,
        'batch_exists' => false,
        'reference_exists' => false,
        'batch_message' => '',
        'reference_message' => ''
    ];
    
    // Check for duplicate batch_no
    if ($batch_no !== '') {
        $batchEsc = mysqli_real_escape_string($conn, $batch_no);
        $query = "SELECT delivery_id FROM deliveries WHERE batch_no = '$batchEsc'";
        if ($delivery_id > 0) {
            $query .= " AND delivery_id <> $delivery_id";
        }
        $query .= " LIMIT 1";
        
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $response['batch_exists'] = true;
            $response['batch_message'] = 'Batch number already exists. Please use a different one.';
        }
        if ($result) mysqli_free_result($result);
    }
    
    // Check for duplicate reference_no
    if ($reference_no !== '') {
        $refEsc = mysqli_real_escape_string($conn, $reference_no);
        $query = "SELECT delivery_id FROM deliveries WHERE reference_no = '$refEsc'";
        if ($delivery_id > 0) {
            $query .= " AND delivery_id <> $delivery_id";
        }
        $query .= " LIMIT 1";
        
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $response['reference_exists'] = true;
            $response['reference_message'] = 'Reference number already exists. Please use a different one.';
        }
        if ($result) mysqli_free_result($result);
    }
    
    ob_clean();
    echo json_encode($response);
    exit;
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}


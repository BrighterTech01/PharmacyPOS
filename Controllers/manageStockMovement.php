<?php
include_once '../dbConnection/connection.php';

// Redirect direct GET access to the page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location:../Admin/stockcard_management/stock_MovementLog.php');
    exit();
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Get current user ID
$user_id = 0;
if (!empty($_SESSION['user']['user_id'])) { $user_id = (int)$_SESSION['user']['user_id']; }
elseif (!empty($_SESSION['user_id'])) { $user_id = (int)$_SESSION['user_id']; }

// Transfer stock from storage to pharmacy
if (isset($_POST['transfer_stock'])) {
    $batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
    $quantity_packages = isset($_POST['quantity_packages']) ? (int)$_POST['quantity_packages'] : 0;

    if ($batch_id <= 0 || $quantity_packages <= 0) {
        header('location:../Admin/stockcard_management/stock_MovementLog.php?error=missing_fields');
        exit();
    }

    // Start transaction
    mysqli_autocommit($conn, false);
    $error = false;
    $errorMessage = '';

    // Fetch batch details to get product_id, quantity_units per package
    $batchSql = "SELECT db.product_id, db.quantity_units AS units_per_package, p.base_unit_type_id AS unit_type_id
                 FROM drug_batches db
                 LEFT JOIN products p ON db.product_id = p.product_id
                 WHERE db.batch_id = $batch_id LIMIT 1";
    $batchRes = mysqli_query($conn, $batchSql);
    
    if (!$batchRes || mysqli_num_rows($batchRes) === 0) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        header('location:../Admin/stockcard_management/stock_MovementLog.php?error=invalid_batch');
        exit();
    }

    $batchData = mysqli_fetch_assoc($batchRes);
    $product_id = (int)$batchData['product_id'];
    $units_per_package = (int)$batchData['units_per_package'];
    $unit_type_id = (int)($batchData['unit_type_id'] ?? 0);
    $quantity_units = $quantity_packages * $units_per_package;

    // Check if batch exists in storage_inventory
    $storageCheck = mysqli_query($conn, "SELECT storage_id FROM storage_inventory WHERE batch_id = $batch_id LIMIT 1");
    if (!$storageCheck || mysqli_num_rows($storageCheck) === 0) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        header('location:../Admin/stockcard_management/stock_MovementLog.php?error=not_in_storage');
        exit();
    }

    // For storage_inventory, we just update the timestamp (it tracks presence, not quantity)
    // The actual package tracking would need to be in a separate table if needed
    // For now, we'll just ensure the record exists and update timestamp
    mysqli_query($conn, "UPDATE storage_inventory SET updated_at = NOW() WHERE batch_id = $batch_id");

    // Update pharmacy_inventory (add or create)
    $pharmacyCheck = mysqli_query($conn, "SELECT pharmacy_id, quantity_units FROM pharmacy_inventory WHERE batch_id = $batch_id LIMIT 1");
    if ($pharmacyCheck && mysqli_num_rows($pharmacyCheck) > 0) {
        $updatePharmacy = "UPDATE pharmacy_inventory SET quantity_units = quantity_units + $quantity_units, updated_at = NOW() WHERE batch_id = $batch_id";
    } else {
        $updatePharmacy = "INSERT INTO pharmacy_inventory (batch_id, quantity_units, updated_at) VALUES ($batch_id, $quantity_units, NOW())";
    }
    
    if (!mysqli_query($conn, $updatePharmacy)) {
        $error = true;
        $errorMessage = 'Failed to update pharmacy inventory: ' . mysqli_error($conn);
    }

    // Get transfer_id (we'll use a simple increment or timestamp-based ID)
    // For now, we'll use movement_id after insert
    $transfer_id = 0;

    // Insert movement log
    if (!$error) {
        $movSql = "INSERT INTO stock_movement_log 
                   (product_id, batch_id, movement_type, from_inventory, to_inventory, quantity_packages, quantity_units, unit_type_id, reference_type, reference_id, movement_date, user_id) 
                   VALUES ($product_id, $batch_id, 'transfer', 'storage', 'pharmacy', $quantity_packages, $quantity_units, $unit_type_id, 'transfer', $transfer_id, NOW(), $user_id)";
        
        if (!mysqli_query($conn, $movSql)) {
            $error = true;
            $errorMessage = 'Failed to log movement: ' . mysqli_error($conn);
        } else {
            $transfer_id = mysqli_insert_id($conn);
            // Update the movement log with the transfer_id as reference_id
            mysqli_query($conn, "UPDATE stock_movement_log SET reference_id = $transfer_id WHERE movement_id = $transfer_id");
        }
    }

    if ($error) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        header('location:../Admin/stockcard_management/stock_MovementLog.php?error=transfer_failed&msg=' . urlencode($errorMessage));
        exit();
    } else {
        mysqli_commit($conn);
        mysqli_autocommit($conn, true);
        header('location:../Admin/stockcard_management/stock_MovementLog.php?success=transferred');
        exit();
    }
}

// Default redirect
header('location:../Admin/stockcard_management/stock_MovementLog.php');
exit();
?>


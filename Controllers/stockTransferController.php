<?php
include_once '../dbConnection/connection.php';

// Redirect direct GET access to the page  
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location:../Admin/stockcard_management/stockTransfer.php');
    exit();
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Get current user ID
$user_id = 0;
$usernameForLookup = null;
if (!empty($_SESSION['user']['user_id'])) {
    $user_id = (int)$_SESSION['user']['user_id'];
} elseif (!empty($_SESSION['user']['id'])) {
    $user_id = (int)$_SESSION['user']['id'];
}

if ($user_id <= 0 && !empty($_SESSION['user']['username'])) {
    $usernameForLookup = $_SESSION['user']['username'];
}

if ($user_id <= 0 && !empty($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
}

if ($user_id <= 0 && !empty($_SESSION['id'])) {
    $user_id = (int)$_SESSION['id'];
}

if ($user_id <= 0 && !empty($_SESSION['username'])) {
    $usernameForLookup = $_SESSION['username'];
}

// If we still don't have a numeric user id but have a username, try to resolve it
if ($user_id <= 0 && $usernameForLookup) {
    $usernameEsc = mysqli_real_escape_string($conn, $usernameForLookup);
    $lookupSql = "SELECT user_id FROM users WHERE username = '$usernameEsc' LIMIT 1";
    $lookupRes = mysqli_query($conn, $lookupSql);
    if ($lookupRes && mysqli_num_rows($lookupRes) > 0) {
        $lookupRow = mysqli_fetch_assoc($lookupRes);
        $user_id = (int)($lookupRow['user_id'] ?? 0);
    }
    if ($lookupRes instanceof mysqli_result) {
        mysqli_free_result($lookupRes);
    }
}

// Transfer stock from storage to pharmacy
if (isset($_POST['transfer_stock'])) {
    if ($user_id <= 0) {
        header('location:../Admin/stockcard_management/stockTransfer.php?error=missing_user');
        exit();
    }
    $batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
    $quantity_packages = isset($_POST['quantity_packages']) ? (int)$_POST['quantity_packages'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($batch_id <= 0 || $quantity_packages <= 0) {
        header('location:../Admin/stockcard_management/stockTransfer.php?error=missing_fields');
        exit();
    }

    // Start transaction
    mysqli_autocommit($conn, false);
    $error = false;
    $errorMessage = '';

    // Fetch delivery details to get product_id, quantity_units per package, and available packages
    // Note: batch_id parameter is actually delivery_id now
    $delivery_id = $batch_id; // Rename for clarity
    $batchSql = "SELECT d.product_id, 
                        d.quantity_packages AS batch_total_packages,
                        d.quantity_units AS units_per_package, 
                        p.base_unit_type_id AS unit_type_id
                 FROM deliveries d
                 LEFT JOIN products p ON d.product_id = p.product_id
                 WHERE d.delivery_id = $delivery_id LIMIT 1";
    $batchRes = mysqli_query($conn, $batchSql);
    
    if (!$batchRes || mysqli_num_rows($batchRes) === 0) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        header('location:../Admin/stockcard_management/stockTransfer.php?error=invalid_batch');
        exit();
    }

    $batchData = mysqli_fetch_assoc($batchRes);
    $product_id = (int)$batchData['product_id'];
    $batch_total_packages = (int)$batchData['batch_total_packages'];
    $units_per_package = (int)$batchData['units_per_package'];
    $unit_type_id = (int)($batchData['unit_type_id'] ?? 0);
    $quantity_units = $quantity_packages * $units_per_package;

    // Check if delivery exists in storage_inventory and get current balance
    $storageCheck = mysqli_query($conn, "SELECT storage_id, quantity_packages_balance, quantity_units_balance FROM storage_inventory WHERE delivery_id = $delivery_id LIMIT 1");
    if (!$storageCheck || mysqli_num_rows($storageCheck) === 0) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        header('location:../Admin/stockcard_management/stockTransfer.php?error=not_in_storage');
        exit();
    }
    
    $storageData = mysqli_fetch_assoc($storageCheck);
    $available_packages = (int)($storageData['quantity_packages_balance'] ?? 0);
    
    // Check if requested quantity is available
    if ($quantity_packages > $available_packages) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        header('location:../Admin/stockcard_management/stockTransfer.php?error=insufficient_stock');
        exit();
    }

    // Update storage_inventory: Add to OUT (balance will be auto-calculated by DB as IN - OUT)
    $storageUpdate = "UPDATE storage_inventory SET 
        quantity_packages_out = quantity_packages_out + $quantity_packages,
        quantity_units_out = quantity_units_out + $quantity_units,
        updated_at = NOW() 
        WHERE delivery_id = $delivery_id";
    
    if (!mysqli_query($conn, $storageUpdate)) {
        $error = true;
        $errorMessage = 'Failed to update storage inventory: ' . mysqli_error($conn);
    }

    // Update pharmacy_inventory: Add to IN (balance will be auto-calculated by DB as IN - OUT)
    // Note: pharmacy_inventory uses delivery_id to link to deliveries table
    // Check if delivery_id column exists, if not, try to add it
    $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM pharmacy_inventory LIKE 'delivery_id'");
    if (!$checkColumn || mysqli_num_rows($checkColumn) == 0) {
        // Column doesn't exist, try to add it
        @mysqli_query($conn, "ALTER TABLE pharmacy_inventory ADD COLUMN delivery_id INT NULL AFTER pharmacy_id");
    }
    
    // Now try to update/insert pharmacy_inventory
    $pharmacyCheck = @mysqli_query($conn, "SELECT pharmacy_id, quantity_units_in FROM pharmacy_inventory WHERE delivery_id = $delivery_id LIMIT 1");
    if ($pharmacyCheck && mysqli_num_rows($pharmacyCheck) > 0) {
        $pharmacyData = mysqli_fetch_assoc($pharmacyCheck);
        $current_units_in = (int)($pharmacyData['quantity_units_in'] ?? 0);
        $new_units_in = $current_units_in + $quantity_units;
        
        $updatePharmacy = "UPDATE pharmacy_inventory SET 
            quantity_units_in = $new_units_in,
            updated_at = NOW() 
            WHERE delivery_id = $delivery_id";
    } else {
        // Create new entry with IN quantities (balance will be auto-calculated by DB)
        $updatePharmacy = "INSERT INTO pharmacy_inventory 
            (delivery_id, quantity_units_in, updated_at) 
            VALUES ($delivery_id, $quantity_units, NOW())";
    }
    
    if (!@mysqli_query($conn, $updatePharmacy)) {
        $error = true;
        $errorMessage = 'Failed to update pharmacy inventory: ' . mysqli_error($conn);
        // If delivery_id column doesn't exist, provide helpful error message
        if (strpos(mysqli_error($conn), "Unknown column 'delivery_id'") !== false) {
            $errorMessage = 'pharmacy_inventory table is missing delivery_id column. Please add it: ALTER TABLE pharmacy_inventory ADD COLUMN delivery_id INT NULL AFTER pharmacy_id;';
        }
    }

    // Create stock_transfers record
    if (!$error) {
        $notesEsc = mysqli_real_escape_string($conn, $notes);
        $insertTransfer = "INSERT INTO stock_transfers 
                          (delivery_id, quantity_packages, quantity_units, unit_type_id, from_location, to_location, transfer_date, user_id, notes) 
                          VALUES ($delivery_id, $quantity_packages, $quantity_units, $unit_type_id, 'storage', 'pharmacy', NOW(), $user_id, " . ($notesEsc !== '' ? "'$notesEsc'" : "NULL") . ")";
        
        if (!mysqli_query($conn, $insertTransfer)) {
            $error = true;
            $errorMessage = 'Failed to create transfer record: ' . mysqli_error($conn);
        } else {
            $transfer_id = mysqli_insert_id($conn);
            
            // Insert movement log with transfer_id as reference_id
            $movSql = "INSERT INTO stock_movement_log 
                       (product_id, movement_type, from_inventory, to_inventory, quantity_packages, quantity_units, unit_type_id, reference_type, reference_id, movement_date, user_id) 
                       VALUES ($product_id, 'transfer', 'storage', 'pharmacy', $quantity_packages, $quantity_units, $unit_type_id, 'transfer', $transfer_id, NOW(), $user_id)";
            
            if (!mysqli_query($conn, $movSql)) {
                $error = true;
                $errorMessage = 'Failed to log movement: ' . mysqli_error($conn);
            }
        }
    }

    if ($error) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        header('location:../Admin/stockcard_management/stockTransfer.php?error=transfer_failed&msg=' . urlencode($errorMessage));
        exit();
    } else {
        mysqli_commit($conn);
        mysqli_autocommit($conn, true);
        header('location:../Admin/stockcard_management/stockTransfer.php?success=transferred');
        exit();
    }
}

// Default redirect
header('location:../Admin/stockcard_management/stockTransfer.php');
exit();
?>


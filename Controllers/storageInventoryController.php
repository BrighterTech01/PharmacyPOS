<?php
include_once '../dbConnection/connection.php';

// Redirect direct GET access to the page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location:../Admin/inventory_management/storageInventory.php');
    exit();
}

function redirect_with($params) {
    $base = '../Admin/inventory_management/storageInventory.php';
    $query = http_build_query($params);
    header('location:' . $base . ($query ? ('?' . $query) : ''));
    exit();
}

// Check admin permission
function checkAdminPermission() {
    if (session_status() === PHP_SESSION_NONE) { 
        session_start(); 
    }
    
    // Check if current user is the pharmacy master account (always has full access)
    $currentUsername = '';
    if (!empty($_SESSION['user']['username'])) { $currentUsername = $_SESSION['user']['username']; }
    elseif (!empty($_SESSION['username'])) { $currentUsername = $_SESSION['username']; }
    $isPharmacyAccount = (strtolower($currentUsername) === 'pharmacy');
    
    $role = '';
    if (!empty($_SESSION['user']['role'])) { 
        $role = strtolower($_SESSION['user']['role']); 
    } elseif (!empty($_SESSION['role'])) { 
        $role = strtolower($_SESSION['role']); 
    }
    
    // Pharmacy account always has admin access
    return $isPharmacyAccount || ($role === 'admin');
}

// Update Storage Inventory
if (isset($_POST['update_storage_inventory'])) {
    if (!checkAdminPermission()) {
        redirect_with(['error' => 'forbidden']);
    }
    
    $storage_id = isset($_POST['storage_id']) ? (int)$_POST['storage_id'] : 0;
    $quantity_packages_in = isset($_POST['quantity_packages_in']) ? (int)$_POST['quantity_packages_in'] : 0;
    $quantity_packages_out = isset($_POST['quantity_packages_out']) ? (int)$_POST['quantity_packages_out'] : 0;
    $quantity_units_in = isset($_POST['quantity_units_in']) ? (int)$_POST['quantity_units_in'] : 0;
    $quantity_units_out = isset($_POST['quantity_units_out']) ? (int)$_POST['quantity_units_out'] : 0;
    
    if ($storage_id <= 0) {
        redirect_with(['error' => 'invalid_id']);
    }
    
    if ($quantity_packages_in < 0 || $quantity_packages_out < 0 || 
        $quantity_units_in < 0 || $quantity_units_out < 0) {
        redirect_with(['error' => 'invalid_quantity']);
    }
    
    // Calculate balances
    $quantity_packages_balance = max(0, $quantity_packages_in - $quantity_packages_out);
    $quantity_units_balance = max(0, $quantity_units_in - $quantity_units_out);
    
    // Ensure record exists
    $idEsc = (int)$storage_id;
    $existsSql = "SELECT storage_id FROM storage_inventory WHERE storage_id = $idEsc";
    $existsRes = mysqli_query($conn, $existsSql);
    if (!$existsRes || mysqli_num_rows($existsRes) === 0) {
        redirect_with(['error' => 'invalid_id']);
    }
    
    // Update inventory
    $updateSql = "UPDATE storage_inventory SET 
                    quantity_packages_in = $quantity_packages_in,
                    quantity_packages_out = $quantity_packages_out,
                    quantity_packages_balance = $quantity_packages_balance,
                    quantity_units_in = $quantity_units_in,
                    quantity_units_out = $quantity_units_out,
                    quantity_units_balance = $quantity_units_balance,
                    updated_at = NOW()
                  WHERE storage_id = $idEsc";
    
    if (mysqli_query($conn, $updateSql)) {
        // Log the manual update in stock_movement_log
        $batchSql = "SELECT batch_id, (SELECT product_id FROM drug_batches WHERE batch_id = si.batch_id) as product_id,
                     (SELECT quantity_units FROM drug_batches WHERE batch_id = si.batch_id) as units_per_package
                     FROM storage_inventory si WHERE storage_id = $idEsc";
        $batchRes = mysqli_query($conn, $batchSql);
        if ($batchRes && mysqli_num_rows($batchRes) > 0) {
            $batchData = mysqli_fetch_assoc($batchRes);
            $batch_id = (int)$batchData['batch_id'];
            $product_id = (int)$batchData['product_id'];
            $units_per_package = max(1, (int)$batchData['units_per_package']);
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : 1);
            
            // Get unit_type_id from product
            $unitTypeSql = "SELECT base_unit_type_id FROM products WHERE product_id = $product_id LIMIT 1";
            $unitTypeRes = mysqli_query($conn, $unitTypeSql);
            $unit_type_id = 1; // Default
            if ($unitTypeRes && mysqli_num_rows($unitTypeRes) > 0) {
                $unitData = mysqli_fetch_assoc($unitTypeRes);
                $unit_type_id = (int)$unitData['base_unit_type_id'];
            }
            
            // Calculate total units from packages and direct units
            $total_units = ($quantity_packages_balance * $units_per_package) + $quantity_units_balance;
            
            // Insert movement log
            $movementSql = "INSERT INTO stock_movement_log 
                            (product_id, batch_id, movement_type, from_inventory, to_inventory, 
                             quantity_packages, quantity_units, unit_type_id, reference_type, 
                             reference_id, movement_date, user_id, notes) 
                            VALUES (
                                $product_id,
                                $batch_id,
                                'adjustment',
                                'storage',
                                'storage',
                                $quantity_packages_balance,
                                $total_units,
                                $unit_type_id,
                                'manual_update',
                                $storage_id,
                                NOW(),
                                $user_id,
                                'Manual inventory adjustment by admin'
                            )";
            mysqli_query($conn, $movementSql);
        }
        
        redirect_with(['success' => 'updated']);
    } else {
        redirect_with(['error' => 'update_failed']);
    }
}

// Delete Storage Inventory
if (isset($_POST['delete_storage_inventory'])) {
    if (!checkAdminPermission()) {
        redirect_with(['error' => 'forbidden']);
    }
    
    $storage_id = isset($_POST['storage_id']) ? (int)$_POST['storage_id'] : 0;
    
    if ($storage_id <= 0) {
        redirect_with(['error' => 'invalid_id']);
    }
    
    $idEsc = (int)$storage_id;
    
    // Note: Allow admin cascade deletion even if balances exist (system-managed cleanup)
    
    // Get batch and product info for logging
    $infoSql = "SELECT si.batch_id, (SELECT product_id FROM drug_batches WHERE batch_id = si.batch_id) as product_id 
                FROM storage_inventory si WHERE storage_id = $idEsc";
    $infoRes = mysqli_query($conn, $infoSql);
    $batch_id = null;
    $product_id = null;
    if ($infoRes && mysqli_num_rows($infoRes) > 0) {
        $infoData = mysqli_fetch_assoc($infoRes);
        $batch_id = (int)$infoData['batch_id'];
        $product_id = (int)$infoData['product_id'];
    }
    
    // Perform cascading deletion for this batch across related tables
    if (!$batch_id) {
        redirect_with(['error' => 'invalid_id']);
    }
    $batchIdEsc = (int)$batch_id;

    // Resolve unit type for logging
    $unit_type_id = 1; // default fallback
    if ($product_id) {
        $unitSql = "SELECT base_unit_type_id FROM products WHERE product_id = $product_id LIMIT 1";
        $unitRes = mysqli_query($conn, $unitSql);
        if ($unitRes && mysqli_num_rows($unitRes) > 0) {
            $unitRow = mysqli_fetch_assoc($unitRes);
            if (!empty($unitRow['base_unit_type_id'])) {
                $unit_type_id = (int)$unitRow['base_unit_type_id'];
            }
        }
    }

    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : 1);

    mysqli_begin_transaction($conn);
    try {
        // 1) Delete movement logs for this batch (affects stock_MovementLog and stockTransfer views)
        mysqli_query($conn, "DELETE FROM stock_movement_log WHERE batch_id = $batchIdEsc");

        // 2) Delete deliveries for this batch
        mysqli_query($conn, "DELETE FROM deliveries WHERE batch_id = $batchIdEsc");

        // 3) Delete pharmacy inventory for this batch
        mysqli_query($conn, "DELETE FROM pharmacy_inventory WHERE batch_id = $batchIdEsc");

        // 4) Delete storage inventory for this batch (including the current record)
        mysqli_query($conn, "DELETE FROM storage_inventory WHERE batch_id = $batchIdEsc");

        // 5) Delete the batch itself
        mysqli_query($conn, "DELETE FROM drug_batches WHERE batch_id = $batchIdEsc");

        // 6) Log deletion of batch in stock movement log
        if ($product_id) {
            $note = mysqli_real_escape_string($conn, 'Batch deleted and removed from storage/pharmacy');
            $movementSql = "INSERT INTO stock_movement_log \
                            (product_id, batch_id, movement_type, from_inventory, to_inventory, \
                             quantity_packages, quantity_units, unit_type_id, reference_type, \
                             reference_id, movement_date, user_id, notes) \
                            VALUES (\n+                                $product_id,\n+                                NULL,\n+                                'deletion',\n+                                'system',\n+                                'removed',\n+                                0,\n+                                0,\n+                                $unit_type_id,\n+                                'batch_delete',\n+                                $batchIdEsc,\n+                                NOW(),\n+                                $user_id,\n+                                '$note'\n+                            )";
            mysqli_query($conn, $movementSql);
        }

        if (mysqli_error($conn)) {
            throw new Exception(mysqli_error($conn));
        }

        mysqli_commit($conn);
        redirect_with(['success' => 'deleted']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        redirect_with(['error' => 'delete_failed']);
    }
}

// Create is not allowed (system-managed)
if (isset($_POST['create_storage_inventory'])) {
    redirect_with(['error' => 'system_managed']);
}

// Default redirect
redirect_with([]);
?>

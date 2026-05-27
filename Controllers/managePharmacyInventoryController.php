<?php
include_once '../dbConnection/connection.php';

// Redirect direct GET access to the page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location:../Admin/inventory_management/pharmacyInventory.php');
    exit();
}

function redirect_with($params) {
    $base = '../Admin/inventory_management/pharmacyInventory.php';
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

// Update Pharmacy Inventory
if (isset($_POST['update_pharmacy_inventory'])) {
    if (!checkAdminPermission()) {
        redirect_with(['error' => 'forbidden']);
    }
    
    $pharmacy_id = isset($_POST['pharmacy_id']) ? (int)$_POST['pharmacy_id'] : 0;
    $quantity_units_in = isset($_POST['quantity_units_in']) ? (int)$_POST['quantity_units_in'] : 0;
    $quantity_units_out = isset($_POST['quantity_units_out']) ? (int)$_POST['quantity_units_out'] : 0;
    
    if ($pharmacy_id <= 0) {
        redirect_with(['error' => 'invalid_id']);
    }
    
    if ($quantity_units_in < 0 || $quantity_units_out < 0) {
        redirect_with(['error' => 'invalid_quantity']);
    }
    
    // Calculate balance
    $quantity_units_balance = max(0, $quantity_units_in - $quantity_units_out);
    
    // Ensure record exists
    $idEsc = (int)$pharmacy_id;
    $existsSql = "SELECT pharmacy_id FROM pharmacy_inventory WHERE pharmacy_id = $idEsc";
    $existsRes = mysqli_query($conn, $existsSql);
    if (!$existsRes || mysqli_num_rows($existsRes) === 0) {
        redirect_with(['error' => 'invalid_id']);
    }
    
    // Update inventory
    $updateSql = "UPDATE pharmacy_inventory SET 
                    quantity_units_in = $quantity_units_in,
                    quantity_units_out = $quantity_units_out,
                    quantity_units_balance = $quantity_units_balance,
                    updated_at = NOW()
                  WHERE pharmacy_id = $idEsc";
    
    if (mysqli_query($conn, $updateSql)) {
        // Log the manual update in stock_movement_log
        $batchSql = "SELECT batch_id, (SELECT product_id FROM drug_batches WHERE batch_id = pi.batch_id) as product_id 
                     FROM pharmacy_inventory pi WHERE pharmacy_id = $idEsc";
        $batchRes = mysqli_query($conn, $batchSql);
        if ($batchRes && mysqli_num_rows($batchRes) > 0) {
            $batchData = mysqli_fetch_assoc($batchRes);
            $batch_id = (int)$batchData['batch_id'];
            $product_id = (int)$batchData['product_id'];
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : 1);
            
            // Get unit_type_id from product
            $unitTypeSql = "SELECT base_unit_type_id FROM products WHERE product_id = $product_id LIMIT 1";
            $unitTypeRes = mysqli_query($conn, $unitTypeSql);
            $unit_type_id = 1; // Default
            if ($unitTypeRes && mysqli_num_rows($unitTypeRes) > 0) {
                $unitData = mysqli_fetch_assoc($unitTypeRes);
                $unit_type_id = (int)$unitData['base_unit_type_id'];
            }
            
            // Insert movement log
            $movementSql = "INSERT INTO stock_movement_log 
                            (product_id, batch_id, movement_type, from_inventory, to_inventory, 
                             quantity_packages, quantity_units, unit_type_id, reference_type, 
                             reference_id, movement_date, user_id, notes) 
                            VALUES (
                                $product_id,
                                $batch_id,
                                'adjustment',
                                'pharmacy',
                                'pharmacy',
                                0,
                                $quantity_units_balance,
                                $unit_type_id,
                                'manual_update',
                                $pharmacy_id,
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

// Delete Pharmacy Inventory
if (isset($_POST['delete_pharmacy_inventory'])) {
    if (!checkAdminPermission()) {
        redirect_with(['error' => 'forbidden']);
    }
    
    $pharmacy_id = isset($_POST['pharmacy_id']) ? (int)$_POST['pharmacy_id'] : 0;
    
    if ($pharmacy_id <= 0) {
        redirect_with(['error' => 'invalid_id']);
    }
    
    $idEsc = (int)$pharmacy_id;
    
    // Check if inventory has balance (prevent deletion if stock exists)
    $balanceSql = "SELECT quantity_units_balance FROM pharmacy_inventory WHERE pharmacy_id = $idEsc";
    $balanceRes = mysqli_query($conn, $balanceSql);
    if ($balanceRes && mysqli_num_rows($balanceRes) > 0) {
        $balanceData = mysqli_fetch_assoc($balanceRes);
        $balance = (int)$balanceData['quantity_units_balance'];
        if ($balance > 0) {
            redirect_with(['error' => 'has_stock', 'balance' => $balance]);
        }
    }
    
    // Get batch and product info before deletion
    $infoSql = "SELECT pi.batch_id, (SELECT product_id FROM drug_batches WHERE batch_id = pi.batch_id) as product_id 
                FROM pharmacy_inventory pi WHERE pharmacy_id = $idEsc";
    $infoRes = mysqli_query($conn, $infoSql);
    $batch_id = null;
    $product_id = null;
    if ($infoRes && mysqli_num_rows($infoRes) > 0) {
        $infoData = mysqli_fetch_assoc($infoRes);
        $batch_id = (int)$infoData['batch_id'];
        $product_id = (int)$infoData['product_id'];
    }
    
    if (!$batch_id) {
        redirect_with(['error' => 'invalid_id']);
    }
    
    $batchIdEsc = (int)$batch_id;

    // Resolve product unit type for logging
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
    
    // Start transaction for cascading deletion
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Delete all stock_movement_log entries for this batch
        mysqli_query($conn, "DELETE FROM stock_movement_log WHERE batch_id = $batchIdEsc");
        
        // 2. Delete all deliveries for this batch
        mysqli_query($conn, "DELETE FROM deliveries WHERE batch_id = $batchIdEsc");
        
        // 3. Delete all storage_inventory records for this batch
        mysqli_query($conn, "DELETE FROM storage_inventory WHERE batch_id = $batchIdEsc");
        
        // 4. Delete all pharmacy_inventory records for this batch (including the current one)
        mysqli_query($conn, "DELETE FROM pharmacy_inventory WHERE batch_id = $batchIdEsc");
        
        // 5. Delete the batch itself
        mysqli_query($conn, "DELETE FROM drug_batches WHERE batch_id = $batchIdEsc");

        // 6. Log deletion in stock movement log (batch_id set to NULL after deletion)
        if ($product_id) {
            $note = mysqli_real_escape_string($conn, 'Batch deleted and removed from all inventories');
            $movementSql = "INSERT INTO stock_movement_log \
                            (product_id, batch_id, movement_type, from_inventory, to_inventory, \
                             quantity_packages, quantity_units, unit_type_id, reference_type, \
                             reference_id, movement_date, user_id, notes) \
                            VALUES (\n+                                $product_id,\n+                                NULL,\n+                                'deletion',\n+                                'system',\n+                                'removed',\n+                                0,\n+                                0,\n+                                $unit_type_id,\n+                                'batch_delete',\n+                                $batchIdEsc,\n+                                NOW(),\n+                                $user_id,\n+                                '$note'\n+                            )";
            mysqli_query($conn, $movementSql);
        }
        
        // Check for errors
        if (mysqli_error($conn)) {
            throw new Exception(mysqli_error($conn));
        }
        
        // Commit transaction
        mysqli_commit($conn);
        redirect_with(['success' => 'deleted']);
        
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        redirect_with(['error' => 'delete_failed']);
    }
}

// Create is not allowed (system-managed)
if (isset($_POST['create_pharmacy_inventory'])) {
    redirect_with(['error' => 'system_managed']);
}

// Default redirect
redirect_with([]);
?>

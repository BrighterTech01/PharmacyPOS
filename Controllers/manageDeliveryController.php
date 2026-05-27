<?php
include_once '../dbConnection/connection.php';

// Redirect direct GET access to the page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location:../Admin/product_management/deliveries.php');
    exit();
}

// Create delivery
if (isset($_POST['create_delivery'])) {
    // Resolve "Received By" (actor_user_id) from session/DB.
    // Do NOT trust client-provided user_id because it can be missing or a sentinel (-1).
    $resolveActorUserId = function(int $fallbackUserId = 0) use ($conn): int {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        $actor = 0;
        if (!empty($_SESSION['user']['user_id'])) { $actor = (int)$_SESSION['user']['user_id']; }
        elseif (!empty($_SESSION['user_id'])) { $actor = (int)$_SESSION['user_id']; }

        // If we have a username, resolve it to a real DB user_id
        if ($actor <= 0) {
            $uname = '';
            if (!empty($_SESSION['user']['username'])) { $uname = (string)$_SESSION['user']['username']; }
            elseif (!empty($_SESSION['username'])) { $uname = (string)$_SESSION['username']; }

            if ($uname !== '') {
                $unameEsc = mysqli_real_escape_string($conn, $uname);
                $rs = @mysqli_query($conn, "SELECT user_id FROM users WHERE username = '$unameEsc' LIMIT 1");
                if ($rs && ($row = mysqli_fetch_assoc($rs))) {
                    $actor = (int)($row['user_id'] ?? 0);
                }
                if ($rs instanceof mysqli_result) { @mysqli_free_result($rs); }
            }
        }

        if ($actor <= 0 && $fallbackUserId > 0) {
            $actor = $fallbackUserId;
        }

        return $actor > 0 ? $actor : 0;
    };

    // Check if this is a multi-item delivery
    if (isset($_POST['delivery_items']) && !empty($_POST['delivery_items'])) {
        $deliveryData = json_decode($_POST['delivery_items'], true);
        
        if (!$deliveryData || !isset($deliveryData['items']) || !is_array($deliveryData['items']) || count($deliveryData['items']) === 0) {
            header('location:../Admin/product_management/deliveries.php?error=missing_fields');
            exit();
        }

        $user_id = isset($deliveryData['user_id']) ? (int)$deliveryData['user_id'] : 0; // optional
        $delivery_date = isset($deliveryData['delivery_date']) ? trim($deliveryData['delivery_date']) : '';
        $status = 'Active';
        $items = $deliveryData['items'];

        // Determine user performing the action (Received By)
        $actor_user_id = $resolveActorUserId($user_id);
        if ($actor_user_id <= 0) {
            header('location:../Admin/product_management/deliveries.php?error=missing_fields');
            exit();
        }
        
        // Get delivery_no from form data, or auto-generate if not provided
        $delivery_no = null;
        if (isset($deliveryData['delivery_no']) && !empty(trim($deliveryData['delivery_no']))) {
            $delivery_no = (int)trim($deliveryData['delivery_no']);
        } else {
            // Auto-generate delivery_no: get the maximum delivery_no and add 1
            $maxDeliveryNoQuery = "SELECT COALESCE(MAX(delivery_no), 0) AS max_delivery_no FROM deliveries";
            $maxResult = mysqli_query($conn, $maxDeliveryNoQuery);
            if ($maxResult && mysqli_num_rows($maxResult) > 0) {
                $maxRow = mysqli_fetch_assoc($maxResult);
                $delivery_no = (int)($maxRow['max_delivery_no'] ?? 0) + 1;
            } else {
                $delivery_no = 1; // Start from 1 if no deliveries exist
            }
        }
        
        // Get reference_no from form data, or auto-generate if not provided
        $reference_no = isset($deliveryData['reference_no']) && !empty(trim($deliveryData['reference_no']))
            ? trim($deliveryData['reference_no'])
            : 'DEL-' . date('YmdHis') . '-' . $actor_user_id;

        // Validation
        if ($delivery_date === '') {
            header('location:../Admin/product_management/deliveries.php?error=missing_fields');
            exit();
        }

        // Validate that at least one item has required fields (product_id and batch_no)
        $hasValidItem = false;
        foreach ($items as $item) {
            $product_id_check = isset($item['product_id']) ? (int)$item['product_id'] : 0;
            $batch_no_check = isset($item['batch_no']) ? trim($item['batch_no']) : '';
            if ($product_id_check > 0 && $batch_no_check !== '') {
                $hasValidItem = true;
                break;
            }
        }
        
        if (!$hasValidItem) {
            header('location:../Admin/product_management/deliveries.php?error=missing_fields');
            exit();
        }

        // Escape strings
        $dateEsc = mysqli_real_escape_string($conn, $delivery_date);
        $statusEsc = mysqli_real_escape_string($conn, $status);
        $refEsc = mysqli_real_escape_string($conn, $reference_no);
        
        // actor_user_id already resolved above

        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $seenBatchNumbers = []; // Track batch numbers within this delivery

        // Process each item
        foreach ($items as $item) {
            $product_id = isset($item['product_id']) ? (int)$item['product_id'] : 0;
            $batch_no = isset($item['batch_no']) ? trim($item['batch_no']) : '';
            $expiry_date = isset($item['expiry_date']) && !empty($item['expiry_date']) ? trim($item['expiry_date']) : null;
            $manufacturing_date = isset($item['manufacturing_date']) && !empty($item['manufacturing_date']) ? trim($item['manufacturing_date']) : null;
            $quantity_packages = isset($item['quantity_packages']) ? (int)$item['quantity_packages'] : 0;
            $quantity_units = isset($item['quantity_units']) ? (int)$item['quantity_units'] : 0;
            $cost_per_package = isset($item['cost_per_package']) ? (float)$item['cost_per_package'] : 0;
            $cost_per_unit = isset($item['cost_per_unit']) ? (float)$item['cost_per_unit'] : 0;
            $selling_price_package = isset($item['selling_price_package']) ? (float)$item['selling_price_package'] : 0;
            $selling_price_unit = isset($item['selling_price_unit']) ? (float)$item['selling_price_unit'] : 0;

            // Validate item
            if ($product_id <= 0 || $batch_no === '' || $quantity_packages <= 0 || $cost_per_package <= 0 || 
                $quantity_units <= 0 || $selling_price_unit <= 0) {
                $errorCount++;
                continue;
            }

            // Batch number duplicate check removed - same batch numbers are now allowed

            // Calculate total_units and total_cost
            $total_units = $quantity_packages * $quantity_units;
            $total_cost = $quantity_packages * $cost_per_package;

            // Escape strings - handle empty dates by using a default date or checking if column allows NULL
            // Escape batch_no
            $batchEsc = mysqli_real_escape_string($conn, $batch_no);
            
            // If expiry_date is required, use a far future date as default
            if (empty($expiry_date) || $expiry_date === null) {
                // Check if we can use NULL, otherwise use a default date (e.g., 10 years from now)
                $expiryEsc = "'2099-12-31'"; // Default far future date if NULL not allowed
            } else {
                $expiryEsc = "'" . mysqli_real_escape_string($conn, $expiry_date) . "'";
            }
            
            if (empty($manufacturing_date) || $manufacturing_date === null) {
                $mfgEsc = "NULL";
            } else {
                $mfgEsc = "'" . mysqli_real_escape_string($conn, $manufacturing_date) . "'";
            }

            // Get product unit type for movement log
            $productQuery = "SELECT base_unit_type_id FROM products WHERE product_id = '$product_id' LIMIT 1";
            $productResult = mysqli_query($conn, $productQuery);
            $unit_type_id = 0;
            if ($productResult && mysqli_num_rows($productResult) > 0) {
                $productData = mysqli_fetch_assoc($productResult);
                $unit_type_id = (int)($productData['base_unit_type_id'] ?? 0);
            }

            // Create storage_inventory entry first
            $insertStorage = "INSERT INTO storage_inventory 
                (delivery_id, quantity_packages_in, quantity_units_in, 
                 quantity_packages_out, quantity_units_out,
                 status, updated_at) 
                VALUES (NULL, $quantity_packages, $total_units, 
                        0, 0,
                        'Active', NOW())";
            $storageResult = mysqli_query($conn, $insertStorage);
            
            if (!$storageResult) {
                $errorCount++;
                $errors[] = "Failed to create storage inventory for batch '$batch_no'";
                continue;
            }
            
            $storage_id = mysqli_insert_id($conn);

            // Insert delivery
            $query = "INSERT INTO deliveries (
                user_id, product_id, batch_no, expiry_date, manufacturing_date,
                delivery_date, quantity_packages, quantity_units, total_units,
                cost_per_package, cost_per_unit, selling_price_package, selling_price_unit,
                total_cost, reference_no, delivery_no, status, created_at, updated_at
            ) VALUES (
                 '$actor_user_id', '$product_id', '$batchEsc', $expiryEsc, $mfgEsc,
                '$dateEsc', '$quantity_packages', '$quantity_units', '$total_units',
                '$cost_per_package', '$cost_per_unit', '$selling_price_package', '$selling_price_unit',
                '$total_cost', '$refEsc', $delivery_no, '$statusEsc', NOW(), NOW()
            )";
            
            $result = mysqli_query($conn, $query);

            if ($result) {
                $delivery_id = mysqli_insert_id($conn);
                
                // Update storage_inventory with delivery_id
                $updateStorageDelivery = "UPDATE storage_inventory SET delivery_id = $delivery_id WHERE storage_id = $storage_id";
                mysqli_query($conn, $updateStorageDelivery);

                // Insert movement log
                $movSql = "INSERT INTO stock_movement_log (product_id, movement_type, from_inventory, to_inventory, quantity_packages, quantity_units, unit_type_id, reference_type, reference_id, movement_date, user_id) VALUES (
                    '$product_id', 'delivery', 'supplier', 'storage', '$quantity_packages', '$total_units', '$unit_type_id', 'delivery', '$delivery_id', NOW(), '$actor_user_id'
                )";
                @mysqli_query($conn, $movSql);

                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Failed to create delivery for batch '$batch_no': " . mysqli_error($conn);
            }
        }

        if ($successCount > 0) {
            $msg = "Successfully created $successCount delivery item(s)";
            if ($errorCount > 0) {
                $msg .= " with $errorCount error(s)";
            }
            header('location:../Admin/product_management/deliveries.php?success=created&msg=' . urlencode($msg));
        } else {
            $errorMsg = "Failed to create any delivery items. " . implode('; ', $errors);
            header('location:../Admin/product_management/deliveries.php?error=create_failed&msg=' . urlencode($errorMsg));
        }
        exit();
    } else {
        // Legacy single-item delivery (for backward compatibility)
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $batch_no = isset($_POST['batch_no']) ? trim($_POST['batch_no']) : '';
        $expiry_date = isset($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;
        $manufacturing_date = isset($_POST['manufacturing_date']) ? trim($_POST['manufacturing_date']) : null;
        $delivery_date = isset($_POST['delivery_date']) ? trim($_POST['delivery_date']) : '';
        $quantity_packages = isset($_POST['quantity_packages']) ? (int)$_POST['quantity_packages'] : 0;
        $quantity_units = isset($_POST['quantity_units']) ? (int)$_POST['quantity_units'] : 0;
        $cost_per_package = isset($_POST['cost_per_package']) ? (float)$_POST['cost_per_package'] : 0;
        $cost_per_unit = isset($_POST['cost_per_unit']) ? (float)$_POST['cost_per_unit'] : 0;
        $selling_price_package = isset($_POST['selling_price_package']) ? (float)$_POST['selling_price_package'] : 0;
        $selling_price_unit = isset($_POST['selling_price_unit']) ? (float)$_POST['selling_price_unit'] : 0;
        $reference_no = isset($_POST['reference_no']) ? trim($_POST['reference_no']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'Active';

        // Determine user performing the action (Received By)
        $actor_user_id = $resolveActorUserId($user_id);

        // Validation
        if ($actor_user_id <= 0 || $delivery_date === '' || $product_id <= 0 || $batch_no === '' || 
            $cost_per_package <= 0 || $selling_price_package <= 0 || $selling_price_unit <= 0) {
            header('location:../Admin/product_management/deliveries.php?error=missing_fields');
            exit();
        }

        // Calculate total_units and total_cost
        $total_units = $quantity_packages * $quantity_units;
        $total_cost = $quantity_packages * $cost_per_package;

        // Escape strings
        $dateEsc = mysqli_real_escape_string($conn, $delivery_date);
        $refEsc = mysqli_real_escape_string($conn, $reference_no);
        $batchEsc = mysqli_real_escape_string($conn, $batch_no);
        
        // Handle empty expiry_date - use default date if NULL not allowed
        if (empty($expiry_date) || $expiry_date === null) {
            $expiryEsc = "'2099-12-31'"; // Default far future date if NULL not allowed
        } else {
            $expiryEsc = "'" . mysqli_real_escape_string($conn, $expiry_date) . "'";
        }
        
        if (empty($manufacturing_date) || $manufacturing_date === null) {
            $mfgEsc = "NULL";
        } else {
            $mfgEsc = "'" . mysqli_real_escape_string($conn, $manufacturing_date) . "'";
        }
        
        $statusEsc = mysqli_real_escape_string($conn, $status);

        // Batch number duplicate check removed - same batch numbers are now allowed

        // Get product unit type for movement log
        $productQuery = "SELECT base_unit_type_id FROM products WHERE product_id = '$product_id' LIMIT 1";
        $productResult = mysqli_query($conn, $productQuery);
        $unit_type_id = 0;
        if ($productResult && mysqli_num_rows($productResult) > 0) {
            $productData = mysqli_fetch_assoc($productResult);
            $unit_type_id = (int)($productData['base_unit_type_id'] ?? 0);
        }

        // Create storage_inventory entry first to get storage_id
        $insertStorage = "INSERT INTO storage_inventory 
            (delivery_id, quantity_packages_in, quantity_units_in, 
             quantity_packages_out, quantity_units_out,
             status, updated_at) 
            VALUES (NULL, $quantity_packages, $total_units, 
                    0, 0,
                    'Active', NOW())";
        $storageResult = mysqli_query($conn, $insertStorage);
        
        if (!$storageResult) {
            $errorMsg = mysqli_error($conn);
            header('location:../Admin/product_management/deliveries.php?error=create_failed&msg=' . urlencode($errorMsg ?: 'Failed to create storage inventory'));
            exit();
        }
        
        $storage_id = mysqli_insert_id($conn);

        // Insert delivery (storage_id column removed from schema)
        $query = "INSERT INTO deliveries (
            user_id, product_id, batch_no, expiry_date, manufacturing_date,
            delivery_date, quantity_packages, quantity_units, total_units,
            cost_per_package, cost_per_unit, selling_price_package, selling_price_unit,
            total_cost, reference_no, status, created_at, updated_at
        ) VALUES (
            '$actor_user_id', '$product_id', '$batchEsc', $expiryEsc, $mfgEsc,
            '$dateEsc', '$quantity_packages', '$quantity_units', '$total_units',
            '$cost_per_package', '$cost_per_unit', '$selling_price_package', '$selling_price_unit',
            '$total_cost', " . ($refEsc !== '' ? "'$refEsc'" : "NULL") . ", '$statusEsc', NOW(), NOW()
        )";
        
        $result = mysqli_query($conn, $query);

        if ($result) {
            $delivery_id = mysqli_insert_id($conn);
            
            // Update storage_inventory with delivery_id now that we have it
            $updateStorageDelivery = "UPDATE storage_inventory SET delivery_id = $delivery_id WHERE storage_id = $storage_id";
            mysqli_query($conn, $updateStorageDelivery);

            // actor_user_id already resolved above

            // Insert movement log
            $movSql = "INSERT INTO stock_movement_log (product_id, movement_type, from_inventory, to_inventory, quantity_packages, quantity_units, unit_type_id, reference_type, reference_id, movement_date, user_id) VALUES (
                '$product_id', 'delivery', 'supplier', 'storage', '$quantity_packages', '$total_units', '$unit_type_id', 'delivery', '$delivery_id', NOW(), '$actor_user_id'
            )";
            @mysqli_query($conn, $movSql);

            header('location:../Admin/product_management/deliveries.php?success=created');
            exit();
        } else {
            $errorMsg = mysqli_error($conn);
            header('location:../Admin/product_management/deliveries.php?error=create_failed&msg=' . urlencode($errorMsg ?: 'Failed to create delivery'));
            exit();
        }
    }
}

// Update delivery
if (isset($_POST['update_delivery'])) {
    $delivery_id = isset($_POST['delivery_id']) ? (int)$_POST['delivery_id'] : 0;
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $batch_no = isset($_POST['batch_no']) ? trim($_POST['batch_no']) : '';
    $expiry_date = isset($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;
    $manufacturing_date = isset($_POST['manufacturing_date']) ? trim($_POST['manufacturing_date']) : null;
    $delivery_date = isset($_POST['delivery_date']) ? trim($_POST['delivery_date']) : '';
    $quantity_packages = isset($_POST['quantity_packages']) ? (int)$_POST['quantity_packages'] : 0;
    $quantity_units = isset($_POST['quantity_units']) ? (int)$_POST['quantity_units'] : 0;
    $cost_per_package = isset($_POST['cost_per_package']) ? (float)$_POST['cost_per_package'] : 0;
    $cost_per_unit = isset($_POST['cost_per_unit']) ? (float)$_POST['cost_per_unit'] : 0;
    $selling_price_package = isset($_POST['selling_price_package']) ? (float)$_POST['selling_price_package'] : 0;
    $selling_price_unit = isset($_POST['selling_price_unit']) ? (float)$_POST['selling_price_unit'] : 0;
    $reference_no = isset($_POST['reference_no']) ? trim($_POST['reference_no']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'Active';

    if ($delivery_id <= 0) {
        header('location:../Admin/product_management/deliveries.php?error=invalid_id');
        exit();
    }

    // Calculate total_units and total_cost
    $total_units = $quantity_packages * $quantity_units;
    $total_cost = $quantity_packages * $cost_per_package;

    // Escape strings
    $dateEsc = mysqli_real_escape_string($conn, $delivery_date);
    $refEsc = mysqli_real_escape_string($conn, $reference_no);
    $batchEsc = mysqli_real_escape_string($conn, $batch_no);
    $expiryEsc = $expiry_date ? "'" . mysqli_real_escape_string($conn, $expiry_date) . "'" : "NULL";
    $mfgEsc = $manufacturing_date ? "'" . mysqli_real_escape_string($conn, $manufacturing_date) . "'" : "NULL";
    $statusEsc = mysqli_real_escape_string($conn, $status);

    // Batch number duplicate check removed - same batch numbers are now allowed

    // Update delivery
    $query = "UPDATE deliveries SET 
        user_id = '$user_id', 
        product_id = '$product_id',
        batch_no = '$batchEsc',
        expiry_date = $expiryEsc,
        manufacturing_date = $mfgEsc,
        delivery_date = '$dateEsc', 
        quantity_packages = '$quantity_packages', 
        quantity_units = '$quantity_units',
        total_units = '$total_units',
        cost_per_package = '$cost_per_package',
        cost_per_unit = '$cost_per_unit',
        selling_price_package = '$selling_price_package', 
        selling_price_unit = '$selling_price_unit',
        total_cost = '$total_cost',
        reference_no = " . ($refEsc !== '' ? "'$refEsc'" : "NULL") . ",
        status = '$statusEsc',
        updated_at = NOW()
        WHERE delivery_id = '$delivery_id'";
    
    $result = mysqli_query($conn, $query);

    if ($result) {
        header('location:../Admin/product_management/deliveries.php?success=updated');
        exit();
    } else {
        $errorMsg = mysqli_error($conn);
        header('location:../Admin/product_management/deliveries.php?error=update_failed&msg=' . urlencode($errorMsg ?: 'Failed to update delivery'));
        exit();
    }
}

// Delete delivery
if (isset($_POST['delete_delivery'])) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $role = '';
    $currentUsername = '';
    if (!empty($_SESSION['user']['role'])) { $role = strtolower($_SESSION['user']['role']); }
    elseif (!empty($_SESSION['role'])) { $role = strtolower($_SESSION['role']); }
    if (!empty($_SESSION['user']['username'])) { $currentUsername = $_SESSION['user']['username']; }
    elseif (!empty($_SESSION['username'])) { $currentUsername = $_SESSION['username']; }
    
    // Check if current user is the pharmacy master account (always has full access)
    $isPharmacyAccount = (strtolower($currentUsername) === 'pharmacy');
    
    if (!$isPharmacyAccount && $role !== 'admin') {
        header('location:../Admin/product_management/deliveries.php?error=forbidden');
        exit();
    }
    $delivery_id = isset($_POST['delivery_id']) ? (int)$_POST['delivery_id'] : 0;
    if ($delivery_id <= 0) {
        header('location:../Admin/product_management/deliveries.php?error=invalid_id');
        exit();
    }

    $query = "DELETE FROM deliveries WHERE delivery_id = '$delivery_id'";
    $result = mysqli_query($conn, $query);

    if ($result) {
        header('location:../Admin/product_management/deliveries.php?success=deleted');
        exit();
    } else {
        header('location:../Admin/product_management/deliveries.php?error=delete_failed');
        exit();
    }
}

// Fallback
header('location:../Admin/product_management/deliveries.php');
exit();
?>

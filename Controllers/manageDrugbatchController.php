<?php
    include_once '../dbConnection/connection.php';

    // Redirect direct GET access to page
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('location:../Admin/inventory_management/batches.php');
        exit();
    }

    // Create batch
    if (isset($_POST['create_batch'])) {
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $batch_no = trim($_POST['batch_no'] ?? '');
        $manufacturing_date = trim($_POST['manufacturing_date'] ?? '');
        $expiry_date = trim($_POST['expiry_date'] ?? '');
        $received_date = trim($_POST['received_date'] ?? '');
        $quantity_packages = isset($_POST['quantity_packages']) ? (int)$_POST['quantity_packages'] : 0;
        $quantity_units = isset($_POST['quantity_units']) ? (int)$_POST['quantity_units'] : 0;

        if (
            $product_id <= 0 || $batch_no === '' || $manufacturing_date === '' || $expiry_date === '' || $received_date === '' ||
            $quantity_packages < 0 || $quantity_units < 0
        ) {
            header('location:../Admin/product_management/batches.php?error=missing_fields');
            exit();
        }

        $batchEsc = mysqli_real_escape_string($conn, $batch_no);
        $mfgEsc = mysqli_real_escape_string($conn, $manufacturing_date);
        $expEsc = mysqli_real_escape_string($conn, $expiry_date);
        $rcvEsc = mysqli_real_escape_string($conn, $received_date);

        // Duplicate check on batch_no (case/space insensitive)
        $dupRes = mysqli_query($conn, "SELECT batch_id FROM drug_batches WHERE LOWER(TRIM(batch_no)) = LOWER(TRIM('$batchEsc')) LIMIT 1");
        if ($dupRes && mysqli_num_rows($dupRes) > 0) {
            header('location:../Admin/product_management/batches.php?error=duplicate_batch');
            exit();
        }

        $query = "INSERT INTO drug_batches (product_id, batch_no, manufacturing_date, expiry_date, received_date, quantity_packages, quantity_units) 
                  VALUES ('$product_id', '$batchEsc', '$mfgEsc', '$expEsc', '$rcvEsc', '$quantity_packages', '$quantity_units')";
        $result = mysqli_query($conn, $query);

        if ($result) {
            header('location:../Admin/product_management/batches.php?success=created');
            exit();
        } else {
            $errorMsg = mysqli_error($conn);
            header('location:../Admin/product_management/batches.php?error=create_failed&msg=' . urlencode($errorMsg ?: 'Failed to create batch'));
            exit();
        }
    }

    // Update batch
    if (isset($_POST['update_batch'])) {
        $batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $batch_no = trim($_POST['batch_no'] ?? '');
        $manufacturing_date = trim($_POST['manufacturing_date'] ?? '');
        $expiry_date = trim($_POST['expiry_date'] ?? '');
        $received_date = trim($_POST['received_date'] ?? '');
        $quantity_packages = isset($_POST['quantity_packages']) ? (int)$_POST['quantity_packages'] : 0;
        $quantity_units = isset($_POST['quantity_units']) ? (int)$_POST['quantity_units'] : 0;

        if ($batch_id <= 0) {
            header('location:../Admin/product_management/batches.php?error=invalid_id');
            exit();
        }

        $batchEsc = mysqli_real_escape_string($conn, $batch_no);
        $mfgEsc = mysqli_real_escape_string($conn, $manufacturing_date);
        $expEsc = mysqli_real_escape_string($conn, $expiry_date);
        $rcvEsc = mysqli_real_escape_string($conn, $received_date);

        if ($batchEsc !== '') {
            $dupRes = mysqli_query($conn, "SELECT batch_id FROM drug_batches WHERE LOWER(TRIM(batch_no)) = LOWER(TRIM('$batchEsc')) AND batch_id != '$batch_id' LIMIT 1");
            if ($dupRes && mysqli_num_rows($dupRes) > 0) {
                header('location:../Admin/product_management/batches.php?error=duplicate_batch');
                exit();
            }
        }

        // Get old batch quantities before update
        $oldBatchRes = mysqli_query($conn, "SELECT quantity_packages, quantity_units FROM drug_batches WHERE batch_id = '$batch_id' LIMIT 1");
        if (!$oldBatchRes || mysqli_num_rows($oldBatchRes) === 0) {
            header('location:../Admin/product_management/batches.php?error=invalid_id');
            exit();
        }
        $oldBatch = mysqli_fetch_assoc($oldBatchRes);
        $old_packages = (int)($oldBatch['quantity_packages'] ?? 0);
        $old_units = (int)($oldBatch['quantity_units'] ?? 0);

        // Calculate ratio of change (only if old values > 0 to avoid division by zero)
        $packages_ratio = ($old_packages > 0) ? ($quantity_packages / $old_packages) : 1.0;
        $units_ratio = ($old_units > 0) ? ($quantity_units / $old_units) : 1.0;

        // Update batch
        $query = "UPDATE drug_batches SET 
                    product_id = '$product_id',
                    batch_no = '$batchEsc',
                    manufacturing_date = '$mfgEsc',
                    expiry_date = '$expEsc',
                    received_date = '$rcvEsc',
                    quantity_packages = '$quantity_packages',
                    quantity_units = '$quantity_units'
                  WHERE batch_id = '$batch_id'";
        $result = mysqli_query($conn, $query);

        if ($result) {
            // Update deliveries proportionally
            if ($old_packages > 0 || $old_units > 0) {
                // Update deliveries that reference this batch
                $deliveriesRes = mysqli_query($conn, "SELECT delivery_id, quantity_packages, quantity_units FROM deliveries WHERE batch_id = '$batch_id'");
                if ($deliveriesRes && mysqli_num_rows($deliveriesRes) > 0) {
                    while ($delivery = mysqli_fetch_assoc($deliveriesRes)) {
                        $delivery_id = (int)$delivery['delivery_id'];
                        $new_delivery_packages = max(0, (int)round((float)$delivery['quantity_packages'] * $packages_ratio));
                        // deliveries.quantity_units is units per package → set directly to the new per-package units
                        $new_delivery_units = max(0, (int)$quantity_units);
                        
                        // Note: total_units is a generated column, so it will be automatically recalculated by the database
                        // In deliveries, quantity_units represents units per package
                        $updateDelivery = "UPDATE deliveries SET 
                            quantity_packages = $new_delivery_packages,
                            quantity_units = $new_delivery_units
                            WHERE delivery_id = $delivery_id";
                        mysqli_query($conn, $updateDelivery);
                    }
                }
            }

            // Update storage_inventory proportionally
            $storageRes = mysqli_query($conn, "SELECT storage_id, quantity_packages_in, quantity_packages_out, quantity_units_in, quantity_units_out 
                                               FROM storage_inventory WHERE batch_id = '$batch_id' LIMIT 1");
            if ($storageRes && mysqli_num_rows($storageRes) > 0) {
                $storage = mysqli_fetch_assoc($storageRes);
                $storage_id = (int)$storage['storage_id'];
                
                $new_packages_in = max(0, (int)round((float)$storage['quantity_packages_in'] * $packages_ratio));
                $new_packages_out = max(0, (int)round((float)$storage['quantity_packages_out'] * $packages_ratio));
                // Recompute units IN/OUT from packages using the new units-per-package
                $new_units_in = max(0, (int)($new_packages_in * $quantity_units));
                $new_units_out = max(0, (int)($new_packages_out * $quantity_units));
                
                $updateStorage = "UPDATE storage_inventory SET 
                    quantity_packages_in = $new_packages_in,
                    quantity_packages_out = $new_packages_out,
                    quantity_units_in = $new_units_in,
                    quantity_units_out = $new_units_out,
                    updated_at = NOW()
                    WHERE storage_id = $storage_id";
                mysqli_query($conn, $updateStorage);
            }

            // Update pharmacy_inventory proportionally
            $pharmacyRes = mysqli_query($conn, "SELECT pharmacy_id, quantity_units_in, quantity_units_out 
                                                FROM pharmacy_inventory WHERE batch_id = '$batch_id' LIMIT 1");
            if ($pharmacyRes && mysqli_num_rows($pharmacyRes) > 0) {
                $pharmacy = mysqli_fetch_assoc($pharmacyRes);
                $pharmacy_id = (int)$pharmacy['pharmacy_id'];
                
                $new_units_in = max(0, (int)round((float)$pharmacy['quantity_units_in'] * $units_ratio));
                $new_units_out = max(0, (int)round((float)$pharmacy['quantity_units_out'] * $units_ratio));
                $updatePharmacy = "UPDATE pharmacy_inventory SET 
                    quantity_units_in = $new_units_in,
                    quantity_units_out = $new_units_out,
                    updated_at = NOW()
                    WHERE pharmacy_id = $pharmacy_id";
                mysqli_query($conn, $updatePharmacy);
            }

            header('location:../Admin/product_management/batches.php?success=updated');
            exit();
        } else {
            $errorMsg = mysqli_error($conn);
            header('location:../Admin/product_management/batches.php?error=update_failed&msg=' . urlencode($errorMsg ?: 'Failed to update batch'));
            exit();
        }
    }

    // Delete batch (block if linked to deliveries)
    if (isset($_POST['delete_batch'])) {
        $batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
        if ($batch_id <= 0) {
            header('location:../Admin/product_management/batches.php?error=invalid_id');
            exit();
        }

        $batchIdEsc = (int)$batch_id;
        // Check if this batch is linked to any deliveries
        $deliveriesRes = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM deliveries WHERE batch_id = $batchIdEsc");
        $deliveriesCnt = 0; 
        if ($deliveriesRes) { 
            $deliveriesCnt = (int)mysqli_fetch_assoc($deliveriesRes)['cnt']; 
        }

        if ($deliveriesCnt > 0) {
            header('location:../Admin/product_management/batches.php?error=batch_in_use&deliveries=' . $deliveriesCnt);
            exit();
        }

        // Gather product information for logging before deletion
        $product_id = null;
        $productRes = mysqli_query($conn, "SELECT product_id FROM drug_batches WHERE batch_id = $batchIdEsc LIMIT 1");
        if ($productRes && mysqli_num_rows($productRes) > 0) {
            $productRow = mysqli_fetch_assoc($productRes);
            if (!empty($productRow['product_id'])) {
                $product_id = (int)$productRow['product_id'];
            }
        }

        $unit_type_id = 1; // default unit
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

        // No deliveries link this batch → allow delete
        // Use transaction to cascade delete related records
        // Note: sales_details and sales transactions are preserved (not deleted)
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Delete from stock_movement_log
            mysqli_query($conn, "DELETE FROM stock_movement_log WHERE batch_id = $batchIdEsc");
            
            // 2. Delete from pharmacy_inventory
            mysqli_query($conn, "DELETE FROM pharmacy_inventory WHERE batch_id = $batchIdEsc");
            
            // 3. Delete from storage_inventory
            mysqli_query($conn, "DELETE FROM storage_inventory WHERE batch_id = $batchIdEsc");
            
            // 4. Delete from barcodes (if table exists)
            $barcodesCheck = mysqli_query($conn, "SHOW TABLES LIKE 'barcodes'");
            if ($barcodesCheck && mysqli_num_rows($barcodesCheck) > 0) {
                mysqli_query($conn, "DELETE FROM barcodes WHERE batch_id = $batchIdEsc");
            }
            
            // 5. Finally delete the batch itself
            // Note: sales_details are NOT deleted to preserve sales transaction history
            $result = mysqli_query($conn, "DELETE FROM drug_batches WHERE batch_id = $batchIdEsc");

            // 6. Log deletion event in stock movement log
            if ($product_id) {
                $note = mysqli_real_escape_string($conn, 'Batch deleted from system');
                $movementSql = "INSERT INTO stock_movement_log \
                                (product_id, batch_id, movement_type, from_inventory, to_inventory, \
                                 quantity_packages, quantity_units, unit_type_id, reference_type, \
                                 reference_id, movement_date, user_id, notes) \
                                VALUES (\n+                                    $product_id,\n+                                    NULL,\n+                                    'deletion',\n+                                    'system',\n+                                    'removed',\n+                                    0,\n+                                    0,\n+                                    $unit_type_id,\n+                                    'batch_delete',\n+                                    $batchIdEsc,\n+                                    NOW(),\n+                                    $user_id,\n+                                    '$note'\n+                                )";
                mysqli_query($conn, $movementSql);
            }
            
            if (mysqli_error($conn)) {
                throw new Exception(mysqli_error($conn));
            }
            
            if ($result) {
                mysqli_commit($conn);
                header('location:../Admin/product_management/batches.php?success=deleted');
                exit();
            } else {
                throw new Exception('Failed to delete batch');
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            header('location:../Admin/product_management/batches.php?error=delete_failed&msg=' . urlencode($e->getMessage()));
            exit();
        }
    }

    // Fallback
    header('location:../Admin/product_management/batches.php');
    exit();
?>

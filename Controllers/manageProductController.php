<?php
include_once '../dbConnection/connection.php';

// Redirect direct GET access to the page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location:../Admin/product_management/products.php');
    exit();
}

// Create product
if (isset($_POST['create_product'])) {
    $brand_name = trim($_POST['brand_name']);
    $generic_name = trim($_POST['generic_name']);
    $strength = trim($_POST['strength']);
    $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';
    $category_id = (int)$_POST['category_id'];
    $base_unit_type_id = (int)$_POST['base_unit_type_id'];
    $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $package_type_id = isset($_POST['package_type_id']) ? (int)$_POST['package_type_id'] : null;
    $product_threshold = isset($_POST['product_threshold']) && $_POST['product_threshold'] !== '' ? (int)$_POST['product_threshold'] : 0;
    $expire_threshold = isset($_POST['expire_threshold']) && $_POST['expire_threshold'] !== '' ? (int)$_POST['expire_threshold'] : 0;
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    // Validate required fields
    if (empty($brand_name) || empty($generic_name) || $category_id <= 0 || $base_unit_type_id <= 0 || $supplier_id <= 0) {
        header('location:../Admin/product_management/products.php?error=missing_fields');
        exit();
    }

    // Check if barcode already exists (if barcode is provided)
    if ($barcode !== '') {
        $barcodeCheck = mysqli_prepare($conn, "SELECT product_id, brand_name FROM products WHERE barcode = ?");
        mysqli_stmt_bind_param($barcodeCheck, "s", $barcode);
        mysqli_stmt_execute($barcodeCheck);
        $barcodeResult = mysqli_stmt_get_result($barcodeCheck);
        
        if (mysqli_num_rows($barcodeResult) > 0) {
            $existingProduct = mysqli_fetch_assoc($barcodeResult);
            $existingBrand = htmlspecialchars($existingProduct['brand_name']);
            header('location:../Admin/product_management/products.php?error=duplicate_barcode&barcode=' . urlencode($barcode) . '&product=' . urlencode($existingBrand));
            mysqli_stmt_close($barcodeCheck);
            exit();
        }
        mysqli_stmt_close($barcodeCheck);
    }

    // Check if category exists
    $categoryCheck = mysqli_prepare($conn, "SELECT category_id FROM Categories WHERE category_id = ?");
    mysqli_stmt_bind_param($categoryCheck, "i", $category_id);
    mysqli_stmt_execute($categoryCheck);
    $categoryResult = mysqli_stmt_get_result($categoryCheck);
    
    if (mysqli_num_rows($categoryResult) == 0) {
        header('location:../Admin/product_management/products.php?error=invalid_category');
        exit();
    }

    // Check if unit type exists
    $unitCheck = mysqli_prepare($conn, "SELECT unit_type_id FROM unit_types WHERE unit_type_id = ?");
    mysqli_stmt_bind_param($unitCheck, "i", $base_unit_type_id);
    mysqli_stmt_execute($unitCheck);
    $unitResult = mysqli_stmt_get_result($unitCheck);
    
    if (mysqli_num_rows($unitResult) == 0) {
        header('location:../Admin/product_management/products.php?error=invalid_unit');
        exit();
    }

    // Check if supplier exists
    $supplierCheck = mysqli_prepare($conn, "SELECT supplier_id FROM suppliers WHERE supplier_id = ?");
    mysqli_stmt_bind_param($supplierCheck, "i", $supplier_id);
    mysqli_stmt_execute($supplierCheck);
    $supplierResult = mysqli_stmt_get_result($supplierCheck);
    if (mysqli_num_rows($supplierResult) == 0) {
        header('location:../Admin/product_management/products.php?error=invalid_supplier');
        exit();
    }

    // Insert new product using prepared statement
    // Validate package type if provided
    if (!empty($package_type_id)) {
        $pkgCheck = mysqli_prepare($conn, "SELECT package_type_id FROM package_types WHERE package_type_id = ?");
        mysqli_stmt_bind_param($pkgCheck, "i", $package_type_id);
        mysqli_stmt_execute($pkgCheck);
        $pkgResult = mysqli_stmt_get_result($pkgCheck);
        if (mysqli_num_rows($pkgResult) == 0) {
            header('location:../Admin/product_management/products.php?error=invalid_package_type');
            exit();
        }
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO products (brand_name, generic_name, strength, barcode, base_unit_type_id, category_id, supplier_id, package_type_id, product_threshold, expire_threshold, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssssiiiiiii", $brand_name, $generic_name, $strength, $barcode, $base_unit_type_id, $category_id, $supplier_id, $package_type_id, $product_threshold, $expire_threshold, $is_active);

    try {
        $executeResult = mysqli_stmt_execute($stmt);
        
        if (!$executeResult) {
            $error = mysqli_error($conn);
            // Check if it's a duplicate barcode error
            if (strpos($error, "Duplicate entry") !== false && strpos($error, "barcode") !== false) {
                // Extract barcode from error or use the submitted barcode
                $barcodeValue = $barcode !== '' ? $barcode : 'provided';
                header('location:../Admin/product_management/products.php?error=duplicate_barcode&barcode=' . urlencode($barcodeValue));
                mysqli_stmt_close($stmt);
                exit();
            }
            // Generic create failed error
            header('location:../Admin/product_management/products.php?error=create_failed&msg=' . urlencode($error));
            mysqli_stmt_close($stmt);
            exit();
        }
        
        // Get the inserted product_id
        $product_id = mysqli_insert_id($conn);
        
        // If product barcode provided, cascade update all related batch barcodes (when barcodes table exists)
        if ($barcode !== '') {
            $hasBarcodesTable = mysqli_query($conn, "SHOW TABLES LIKE 'barcodes'");
            if ($hasBarcodesTable && mysqli_num_rows($hasBarcodesTable) > 0) {
                $updB = mysqli_prepare($conn, "UPDATE barcodes SET barcode_value = ? WHERE product_id = ?");
                if ($updB) {
                    mysqli_stmt_bind_param($updB, "si", $barcode, $product_id);
                    mysqli_stmt_execute($updB);
                    mysqli_stmt_close($updB);
                }
            }
            if ($hasBarcodesTable) { mysqli_free_result($hasBarcodesTable); }
        }
        // Auto-update status for category, unit_type, and package_type
        // Update category status
        $catCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE category_id = $category_id";
        $catCountRes = mysqli_query($conn, $catCountSql);
        $catProdCount = ($catCountRes) ? (int)mysqli_fetch_assoc($catCountRes)['cnt'] : 0;
        $catStatus = ($catProdCount > 0) ? 'Active' : 'Inactive';
        mysqli_query($conn, "UPDATE Categories SET status = '$catStatus' WHERE category_id = $category_id");
        
        // Update unit_type status
        $unitCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE base_unit_type_id = $base_unit_type_id";
        $unitCountRes = mysqli_query($conn, $unitCountSql);
        $unitProdCount = ($unitCountRes) ? (int)mysqli_fetch_assoc($unitCountRes)['cnt'] : 0;
        $unitStatus = ($unitProdCount > 0) ? 'Active' : 'Inactive';
        mysqli_query($conn, "UPDATE unit_types SET status = '$unitStatus' WHERE unit_type_id = $base_unit_type_id");
        
        // Update package_type status if provided
        if (!empty($package_type_id)) {
            $pkgCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE package_type_id = $package_type_id";
            $pkgCountRes = mysqli_query($conn, $pkgCountSql);
            $pkgProdCount = ($pkgCountRes) ? (int)mysqli_fetch_assoc($pkgCountRes)['cnt'] : 0;
            $pkgStatus = ($pkgProdCount > 0) ? 'Active' : 'Inactive';
            mysqli_query($conn, "UPDATE package_types SET status = '$pkgStatus' WHERE package_type_id = $package_type_id");
        }
        
        header('location:../Admin/product_management/products.php?success=created');
        mysqli_stmt_close($stmt);
        exit();
    } catch (mysqli_sql_exception $e) {
        // Catch mysqli_sql_exception for duplicate barcode
        $errorMessage = $e->getMessage();
        if (strpos($errorMessage, "Duplicate entry") !== false && strpos($errorMessage, "barcode") !== false) {
            $barcodeValue = $barcode !== '' ? $barcode : 'provided';
            header('location:../Admin/product_management/products.php?error=duplicate_barcode&barcode=' . urlencode($barcodeValue));
            if (isset($stmt)) mysqli_stmt_close($stmt);
            exit();
        }
        // Generic error
        header('location:../Admin/product_management/products.php?error=create_failed&msg=' . urlencode($errorMessage));
        if (isset($stmt)) mysqli_stmt_close($stmt);
        exit();
    } catch (Exception $e) {
        // Catch any other exceptions
        header('location:../Admin/product_management/products.php?error=create_failed&msg=' . urlencode($e->getMessage()));
        if (isset($stmt)) mysqli_stmt_close($stmt);
        exit();
    }
}
                    
// Update product
if (isset($_POST['update_product'])) {
    $product_id = (int)$_POST['product_id'];
    $brand_name = trim($_POST['brand_name']);
    $generic_name = trim($_POST['generic_name']);
    $strength = trim($_POST['strength']);
    $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';
    $category_id = (int)$_POST['category_id'];
    $base_unit_type_id = (int)$_POST['base_unit_type_id'];
    $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $package_type_id = isset($_POST['package_type_id']) ? (int)$_POST['package_type_id'] : null;
    $product_threshold = isset($_POST['product_threshold']) && $_POST['product_threshold'] !== '' ? (int)$_POST['product_threshold'] : 0;
    $expire_threshold = isset($_POST['expire_threshold']) && $_POST['expire_threshold'] !== '' ? (int)$_POST['expire_threshold'] : 0;
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    // Validate required fields
    if ($product_id <= 0 || empty($brand_name) || empty($generic_name) || $category_id <= 0 || $base_unit_type_id <= 0 || $supplier_id <= 0) {
        header('location:../Admin/product_management/products.php?error=missing_fields');
        exit();
    }

    // Check if product exists
    $productCheck = mysqli_prepare($conn, "SELECT product_id FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($productCheck, "i", $product_id);
    mysqli_stmt_execute($productCheck);
    $productResult = mysqli_stmt_get_result($productCheck);
    
    if (mysqli_num_rows($productResult) == 0) {
        header('location:../Admin/product_management/products.php?error=invalid_id');
        exit();
    }

    // Check if category exists
    $categoryCheck = mysqli_prepare($conn, "SELECT category_id FROM Categories WHERE category_id = ?");
    mysqli_stmt_bind_param($categoryCheck, "i", $category_id);
    mysqli_stmt_execute($categoryCheck);
    $categoryResult = mysqli_stmt_get_result($categoryCheck);
    
    if (mysqli_num_rows($categoryResult) == 0) {
        header('location:../Admin/product_management/products.php?error=invalid_category');
        exit();
    }

    // Check if unit type exists
    $unitCheck = mysqli_prepare($conn, "SELECT unit_type_id FROM unit_types WHERE unit_type_id = ?");
    mysqli_stmt_bind_param($unitCheck, "i", $base_unit_type_id);
    mysqli_stmt_execute($unitCheck);
    $unitResult = mysqli_stmt_get_result($unitCheck);
    
    if (mysqli_num_rows($unitResult) == 0) {
        header('location:../Admin/product_management/products.php?error=invalid_unit');
        exit();
    }

    // Check if supplier exists
    $supplierCheck = mysqli_prepare($conn, "SELECT supplier_id FROM suppliers WHERE supplier_id = ?");
    mysqli_stmt_bind_param($supplierCheck, "i", $supplier_id);
    mysqli_stmt_execute($supplierCheck);
    $supplierResult = mysqli_stmt_get_result($supplierCheck);
    if (mysqli_num_rows($supplierResult) == 0) {
        header('location:../Admin/product_management/products.php?error=invalid_supplier');
        exit();
    }

    // Check if barcode already exists for another product (if barcode is provided)
    if ($barcode !== '') {
        $barcodeCheck = mysqli_prepare($conn, "SELECT product_id, brand_name FROM products WHERE barcode = ? AND product_id != ?");
        mysqli_stmt_bind_param($barcodeCheck, "si", $barcode, $product_id);
        mysqli_stmt_execute($barcodeCheck);
        $barcodeResult = mysqli_stmt_get_result($barcodeCheck);
        
        if (mysqli_num_rows($barcodeResult) > 0) {
            $existingProduct = mysqli_fetch_assoc($barcodeResult);
            $existingBrand = htmlspecialchars($existingProduct['brand_name']);
            header('location:../Admin/product_management/products.php?error=duplicate_barcode&barcode=' . urlencode($barcode) . '&product=' . urlencode($existingBrand));
            mysqli_stmt_close($barcodeCheck);
            exit();
        }
        mysqli_stmt_close($barcodeCheck);
    }

    // Update product using prepared statement
    // Validate package type if provided
    if (!empty($package_type_id)) {
        $pkgCheck = mysqli_prepare($conn, "SELECT package_type_id FROM package_types WHERE package_type_id = ?");
        mysqli_stmt_bind_param($pkgCheck, "i", $package_type_id);
        mysqli_stmt_execute($pkgCheck);
        $pkgResult = mysqli_stmt_get_result($pkgCheck);
        if (mysqli_num_rows($pkgResult) == 0) {
            header('location:../Admin/product_management/products.php?error=invalid_package_type');
            exit();
        }
    }

    // Get old values before update to update status for old references
    $oldProductQuery = "SELECT category_id, base_unit_type_id, package_type_id FROM products WHERE product_id = $product_id LIMIT 1";
    $oldProductRes = mysqli_query($conn, $oldProductQuery);
    $oldCategoryId = 0;
    $oldUnitTypeId = 0;
    $oldPackageTypeId = null;
    if ($oldProductRes && mysqli_num_rows($oldProductRes) > 0) {
        $oldProduct = mysqli_fetch_assoc($oldProductRes);
        $oldCategoryId = (int)$oldProduct['category_id'];
        $oldUnitTypeId = (int)$oldProduct['base_unit_type_id'];
        $oldPackageTypeId = !empty($oldProduct['package_type_id']) ? (int)$oldProduct['package_type_id'] : null;
    }

    $stmt = mysqli_prepare($conn, "UPDATE products SET brand_name = ?, generic_name = ?, strength = ?, barcode = ?, base_unit_type_id = ?, category_id = ?, supplier_id = ?, package_type_id = ?, product_threshold = ?, expire_threshold = ?, is_active = ? WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, "ssssiiiiiiii", $brand_name, $generic_name, $strength, $barcode, $base_unit_type_id, $category_id, $supplier_id, $package_type_id, $product_threshold, $expire_threshold, $is_active, $product_id);

    try {
        $executeResult = mysqli_stmt_execute($stmt);
        
        if (!$executeResult) {
            $error = mysqli_error($conn);
            // Check if it's a duplicate barcode error
            if (strpos($error, "Duplicate entry") !== false && strpos($error, "barcode") !== false) {
                $barcodeValue = $barcode !== '' ? $barcode : 'provided';
                header('location:../Admin/product_management/products.php?error=duplicate_barcode&barcode=' . urlencode($barcodeValue));
                mysqli_stmt_close($stmt);
                exit();
            }
            // Generic update failed error
            header('location:../Admin/product_management/products.php?error=update_failed&msg=' . urlencode($error));
            mysqli_stmt_close($stmt);
            exit();
        }
        
        // Auto-update status for NEW category, unit_type, and package_type
        // Update new category status
        $catCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE category_id = $category_id";
        $catCountRes = mysqli_query($conn, $catCountSql);
        $catProdCount = ($catCountRes) ? (int)mysqli_fetch_assoc($catCountRes)['cnt'] : 0;
        $catStatus = ($catProdCount > 0) ? 'Active' : 'Inactive';
        mysqli_query($conn, "UPDATE Categories SET status = '$catStatus' WHERE category_id = $category_id");
        
        // Update new unit_type status
        $unitCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE base_unit_type_id = $base_unit_type_id";
        $unitCountRes = mysqli_query($conn, $unitCountSql);
        $unitProdCount = ($unitCountRes) ? (int)mysqli_fetch_assoc($unitCountRes)['cnt'] : 0;
        $unitStatus = ($unitProdCount > 0) ? 'Active' : 'Inactive';
        mysqli_query($conn, "UPDATE unit_types SET status = '$unitStatus' WHERE unit_type_id = $base_unit_type_id");
        
        // Update new package_type status if provided
        if (!empty($package_type_id)) {
            $pkgCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE package_type_id = $package_type_id";
            $pkgCountRes = mysqli_query($conn, $pkgCountSql);
            $pkgProdCount = ($pkgCountRes) ? (int)mysqli_fetch_assoc($pkgCountRes)['cnt'] : 0;
            $pkgStatus = ($pkgProdCount > 0) ? 'Active' : 'Inactive';
            mysqli_query($conn, "UPDATE package_types SET status = '$pkgStatus' WHERE package_type_id = $package_type_id");
        }
        
        // Update status for OLD category if it changed
        if ($oldCategoryId > 0 && $oldCategoryId !== $category_id) {
            $oldCatCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE category_id = $oldCategoryId";
            $oldCatCountRes = mysqli_query($conn, $oldCatCountSql);
            $oldCatProdCount = ($oldCatCountRes) ? (int)mysqli_fetch_assoc($oldCatCountRes)['cnt'] : 0;
            $oldCatStatus = ($oldCatProdCount > 0) ? 'Active' : 'Inactive';
            mysqli_query($conn, "UPDATE Categories SET status = '$oldCatStatus' WHERE category_id = $oldCategoryId");
        }
        
        // Update status for OLD unit_type if it changed
        if ($oldUnitTypeId > 0 && $oldUnitTypeId !== $base_unit_type_id) {
            $oldUnitCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE base_unit_type_id = $oldUnitTypeId";
            $oldUnitCountRes = mysqli_query($conn, $oldUnitCountSql);
            $oldUnitProdCount = ($oldUnitCountRes) ? (int)mysqli_fetch_assoc($oldUnitCountRes)['cnt'] : 0;
            $oldUnitStatus = ($oldUnitProdCount > 0) ? 'Active' : 'Inactive';
            mysqli_query($conn, "UPDATE unit_types SET status = '$oldUnitStatus' WHERE unit_type_id = $oldUnitTypeId");
        }
        
        // Update status for OLD package_type if it changed
        if ($oldPackageTypeId !== null) {
            $currentPkgId = !empty($package_type_id) ? (int)$package_type_id : null;
            if ($oldPackageTypeId !== $currentPkgId) {
                $oldPkgCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE package_type_id = $oldPackageTypeId";
                $oldPkgCountRes = mysqli_query($conn, $oldPkgCountSql);
                $oldPkgProdCount = ($oldPkgCountRes) ? (int)mysqli_fetch_assoc($oldPkgCountRes)['cnt'] : 0;
                $oldPkgStatus = ($oldPkgProdCount > 0) ? 'Active' : 'Inactive';
                mysqli_query($conn, "UPDATE package_types SET status = '$oldPkgStatus' WHERE package_type_id = $oldPackageTypeId");
            }
        }
        
        header('location:../Admin/product_management/products.php?success=updated');
        mysqli_stmt_close($stmt);
        exit();
    } catch (mysqli_sql_exception $e) {
        // Catch mysqli_sql_exception for duplicate barcode
        $errorMessage = $e->getMessage();
        if (strpos($errorMessage, "Duplicate entry") !== false && strpos($errorMessage, "barcode") !== false) {
            $barcodeValue = $barcode !== '' ? $barcode : 'provided';
            header('location:../Admin/product_management/products.php?error=duplicate_barcode&barcode=' . urlencode($barcodeValue));
            mysqli_stmt_close($stmt);
            exit();
        }
        // Generic error
        header('location:../Admin/product_management/products.php?error=update_failed&msg=' . urlencode($errorMessage));
        mysqli_stmt_close($stmt);
        exit();
    } catch (Exception $e) {
        // Catch any other exceptions
        header('location:../Admin/product_management/products.php?error=update_failed&msg=' . urlencode($e->getMessage()));
        mysqli_stmt_close($stmt);
        exit();
    }
}

// Delete product
if (isset($_POST['delete_product'])) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $role = '';
    $currentUsername = '';
    if (!empty($_SESSION['user']['role'])) { $role = strtolower($_SESSION['user']['role']); }
    elseif (!empty($_SESSION['role'])) { $role = strtolower($_SESSION['role']); }
    if (!empty($_SESSION['user']['username'])) { $currentUsername = $_SESSION['user']['username']; }
    elseif (!empty($_SESSION['username'])) { $currentUsername = $_SESSION['username']; }
    
    // Check if current user is the pharmacy master account (always has full access)
    $isPharmacyAccount = (strtolower($currentUsername) === 'pharmacy');
    
    // Only admins may delete products (pharmacy account always has full access)
    if (!$isPharmacyAccount && $role !== 'admin') {
        header('location:../Admin/product_management/products.php?error=forbidden');
        exit();
    }
    
    $product_id = (int)$_POST['product_id'];
    if ($product_id <= 0) {
        header('location:../Admin/product_management/products.php?error=invalid_id');
        exit();
    }

    // Check if product exists
    $productCheck = mysqli_prepare($conn, "SELECT product_id FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($productCheck, "i", $product_id);
    mysqli_stmt_execute($productCheck);
    $productResult = mysqli_stmt_get_result($productCheck);
    
    if (mysqli_num_rows($productResult) == 0) {
        header('location:../Admin/product_management/products.php?error=product_not_found');
        exit();
    }

    // Get category, unit_type, and package_type IDs before deletion for status updates
    $prodInfoQuery = "SELECT category_id, base_unit_type_id, package_type_id FROM products WHERE product_id = $product_id LIMIT 1";
    $prodInfoRes = mysqli_query($conn, $prodInfoQuery);
    $delCategoryId = 0;
    $delUnitTypeId = 0;
    $delPackageTypeId = null;
    if ($prodInfoRes && mysqli_num_rows($prodInfoRes) > 0) {
        $prodInfo = mysqli_fetch_assoc($prodInfoRes);
        $delCategoryId = (int)$prodInfo['category_id'];
        $delUnitTypeId = (int)$prodInfo['base_unit_type_id'];
        $delPackageTypeId = !empty($prodInfo['package_type_id']) ? (int)$prodInfo['package_type_id'] : null;
    }

    // Check dependencies to avoid FK errors using prepared statements
    // Check deliveries table
    $deliveriesCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM deliveries WHERE product_id = ?");
    mysqli_stmt_bind_param($deliveriesCheck, "i", $product_id);
    mysqli_stmt_execute($deliveriesCheck);
    $deliveriesResult = mysqli_stmt_get_result($deliveriesCheck);
    $deliveriesCnt = mysqli_fetch_assoc($deliveriesResult)['cnt'];
    mysqli_stmt_close($deliveriesCheck);

    // Check barcodes table (if it exists)
    $barcodesCnt = 0;
    $barcodesTableExists = @mysqli_query($conn, "SHOW TABLES LIKE 'barcodes'");
    if ($barcodesTableExists && mysqli_num_rows($barcodesTableExists) > 0) {
        $barcodesCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM barcodes WHERE product_id = ?");
        mysqli_stmt_bind_param($barcodesCheck, "i", $product_id);
        mysqli_stmt_execute($barcodesCheck);
        $barcodesResult = mysqli_stmt_get_result($barcodesCheck);
        $barcodesCnt = mysqli_fetch_assoc($barcodesResult)['cnt'];
        mysqli_stmt_close($barcodesCheck);
    }
    if ($barcodesTableExists) { mysqli_free_result($barcodesTableExists); }

    // Check if product has any inventory (storage_inventory or pharmacy_inventory)
    // storage_inventory has both packages and units balance
    $storageInventoryCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM storage_inventory si INNER JOIN deliveries d ON si.delivery_id = d.delivery_id WHERE d.product_id = ? AND (si.quantity_packages_balance > 0 OR si.quantity_units_balance > 0)");
    mysqli_stmt_bind_param($storageInventoryCheck, "i", $product_id);
    mysqli_stmt_execute($storageInventoryCheck);
    $storageInventoryResult = mysqli_stmt_get_result($storageInventoryCheck);
    $storageInventoryCnt = mysqli_fetch_assoc($storageInventoryResult)['cnt'];
    mysqli_stmt_close($storageInventoryCheck);

    // pharmacy_inventory only has units balance (no packages)
    $pharmacyInventoryCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM pharmacy_inventory pi INNER JOIN deliveries d ON pi.delivery_id = d.delivery_id WHERE d.product_id = ? AND pi.quantity_units_balance > 0");
    mysqli_stmt_bind_param($pharmacyInventoryCheck, "i", $product_id);
    mysqli_stmt_execute($pharmacyInventoryCheck);
    $pharmacyInventoryResult = mysqli_stmt_get_result($pharmacyInventoryCheck);
    $pharmacyInventoryCnt = mysqli_fetch_assoc($pharmacyInventoryResult)['cnt'];
    mysqli_stmt_close($pharmacyInventoryCheck);

    $inventoryCnt = $storageInventoryCnt + $pharmacyInventoryCnt;

    // Check stock_movement_log table
    $movementLogCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM stock_movement_log WHERE product_id = ?");
    mysqli_stmt_bind_param($movementLogCheck, "i", $product_id);
    mysqli_stmt_execute($movementLogCheck);
    $movementLogResult = mysqli_stmt_get_result($movementLogCheck);
    $movementLogCnt = mysqli_fetch_assoc($movementLogResult)['cnt'];
    mysqli_stmt_close($movementLogCheck);

    // Check sales_details via deliveries
    $salesCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM sales_details sd INNER JOIN deliveries d ON sd.delivery_id = d.delivery_id WHERE d.product_id = ?");
    mysqli_stmt_bind_param($salesCheck, "i", $product_id);
    mysqli_stmt_execute($salesCheck);
    $salesResult = mysqli_stmt_get_result($salesCheck);
    $salesCnt = mysqli_fetch_assoc($salesResult)['cnt'];
    mysqli_stmt_close($salesCheck);

    if ($deliveriesCnt > 0 || $barcodesCnt > 0 || $inventoryCnt > 0 || $movementLogCnt > 0 || $salesCnt > 0) {
        $errorDetails = [];
        if ($deliveriesCnt > 0) $errorDetails[] = "deliveries: $deliveriesCnt";
        if ($barcodesCnt > 0) $errorDetails[] = "barcodes: $barcodesCnt";
        if ($inventoryCnt > 0) $errorDetails[] = "inventory: $inventoryCnt";
        if ($movementLogCnt > 0) $errorDetails[] = "movement_logs: $movementLogCnt";
        if ($salesCnt > 0) $errorDetails[] = "sales: $salesCnt";
        $details = implode(', ', $errorDetails);
        header('location:../Admin/product_management/products.php?error=product_in_use&details=' . urlencode($details));
        exit();
    }

    // Delete product using prepared statement with error handling
    $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $product_id);

    try {
        $executeResult = mysqli_stmt_execute($stmt);
        
        if (!$executeResult) {
            $error = mysqli_error($conn);
            $errorMsg = '';
            
            // Check for specific foreign key constraint errors
            if (strpos($error, 'foreign key constraint') !== false) {
                if (strpos($error, 'stock_movement_log') !== false) {
                    $errorMsg = 'Cannot delete product: It has stock movement records. Please delete or update the movement logs first.';
                } elseif (strpos($error, 'sales_details') !== false || strpos($error, 'sales') !== false) {
                    $errorMsg = 'Cannot delete product: It has sales records. Products with sales history cannot be deleted.';
                } elseif (strpos($error, 'deliveries') !== false) {
                    $errorMsg = 'Cannot delete product: It has delivery records. Please delete or update the deliveries first.';
                } else {
                    $errorMsg = 'Cannot delete product: It is referenced by other records. ' . htmlspecialchars($error);
                }
            } else {
                $errorMsg = 'Failed to delete product: ' . htmlspecialchars($error);
            }
            
            header('location:../Admin/product_management/products.php?error=delete_failed&msg=' . urlencode($errorMsg));
            mysqli_stmt_close($stmt);
            exit();
        }
        
        // Success - continue with status updates
        // Auto-update status for category, unit_type, and package_type after deletion
        // Update category status
        if ($delCategoryId > 0) {
            $catCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE category_id = $delCategoryId";
            $catCountRes = mysqli_query($conn, $catCountSql);
            $catProdCount = ($catCountRes) ? (int)mysqli_fetch_assoc($catCountRes)['cnt'] : 0;
            $catStatus = ($catProdCount > 0) ? 'Active' : 'Inactive';
            mysqli_query($conn, "UPDATE Categories SET status = '$catStatus' WHERE category_id = $delCategoryId");
        }
        
        // Update unit_type status
        if ($delUnitTypeId > 0) {
            $unitCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE base_unit_type_id = $delUnitTypeId";
            $unitCountRes = mysqli_query($conn, $unitCountSql);
            $unitProdCount = ($unitCountRes) ? (int)mysqli_fetch_assoc($unitCountRes)['cnt'] : 0;
            $unitStatus = ($unitProdCount > 0) ? 'Active' : 'Inactive';
            mysqli_query($conn, "UPDATE unit_types SET status = '$unitStatus' WHERE unit_type_id = $delUnitTypeId");
        }
        
        // Update package_type status if it had one
        if ($delPackageTypeId !== null && $delPackageTypeId > 0) {
            $pkgCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE package_type_id = $delPackageTypeId";
            $pkgCountRes = mysqli_query($conn, $pkgCountSql);
            $pkgProdCount = ($pkgCountRes) ? (int)mysqli_fetch_assoc($pkgCountRes)['cnt'] : 0;
            $pkgStatus = ($pkgProdCount > 0) ? 'Active' : 'Inactive';
            mysqli_query($conn, "UPDATE package_types SET status = '$pkgStatus' WHERE package_type_id = $delPackageTypeId");
        }
        
        header('location:../Admin/product_management/products.php?success=deleted');
        mysqli_stmt_close($stmt);
        exit();
        
    } catch (mysqli_sql_exception $e) {
        // Catch foreign key constraint errors and other SQL exceptions
        $error = $e->getMessage();
        $errorMsg = '';
        
        // Check for specific foreign key constraint errors
        if (strpos($error, 'foreign key constraint') !== false) {
            if (strpos($error, 'stock_movement_log') !== false) {
                $errorMsg = 'Cannot delete product: It has stock movement records. Please delete or update the movement logs first.';
            } elseif (strpos($error, 'sales_details') !== false || strpos($error, 'sales') !== false) {
                $errorMsg = 'Cannot delete product: It has sales records. Products with sales history cannot be deleted.';
            } elseif (strpos($error, 'deliveries') !== false) {
                $errorMsg = 'Cannot delete product: It has delivery records. Please delete or update the deliveries first.';
            } else {
                $errorMsg = 'Cannot delete product: It is referenced by other records. ' . htmlspecialchars($error);
            }
        } else {
            $errorMsg = 'Failed to delete product: ' . htmlspecialchars($error);
        }
        
        header('location:../Admin/product_management/products.php?error=delete_failed&msg=' . urlencode($errorMsg));
        if (isset($stmt)) mysqli_stmt_close($stmt);
        exit();
    } catch (Exception $e) {
        // Catch any other exceptions
        header('location:../Admin/product_management/products.php?error=delete_failed&msg=' . urlencode('An unexpected error occurred: ' . htmlspecialchars($e->getMessage())));
        if (isset($stmt)) mysqli_stmt_close($stmt);
        exit();
    }
}

// Fallback
header('location:../Admin/product_management/products.php');
exit();
?>
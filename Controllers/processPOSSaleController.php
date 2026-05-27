<?php
// Start output buffering to prevent any HTML output from breaking JSON
ob_start();

// Suppress display of errors, but log them
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'A fatal error occurred',
            'debug' => $error['message'] . ' in ' . basename($error['file']) . ' on line ' . $error['line']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
});

header("Content-Type: application/json");
include_once '../dbConnection/connection.php';
if (!function_exists('generateXinyeThermalReceiptHTML')) {
    define('PRINT_RECEIPT_FUNCTIONS_ONLY', true);
    require_once __DIR__ . '/printReceipt.php';
}

// Check if connection was successful
if (!isset($conn) || $conn === null || !$conn) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Resolve "user_id" from session/DB (do NOT trust client-provided user_id)
$resolveUserId = function(int $fallbackUserId = 0) use ($conn): int {
    $actor = 0;
    if (!empty($_SESSION['user']['user_id'])) { 
        $actor = (int)$_SESSION['user']['user_id']; 
    } elseif (!empty($_SESSION['user_id'])) { 
        $actor = (int)$_SESSION['user_id']; 
    }

    // If we have a username, resolve it to a real DB user_id
    if ($actor <= 0) {
        $uname = '';
        if (!empty($_SESSION['user']['username'])) { 
            $uname = (string)$_SESSION['user']['username']; 
        } elseif (!empty($_SESSION['username'])) { 
            $uname = (string)$_SESSION['username']; 
        }

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

$method = $_SERVER['REQUEST_METHOD'];

if ($method === "POST") {
    try {
    $input = json_decode(file_get_contents("php://input"), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON input: ' . json_last_error_msg()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    
    // Resolve user_id from session (client-provided user_id is only used as fallback)
    $clientUserId = isset($input['user_id']) ? intval($input['user_id']) : 0;
    $user_id = $resolveUserId($clientUserId);
    $customer_name = isset($input['customer_name']) ? trim($input['customer_name']) : 'Walk-in Customer';
    $cash_received = isset($input['cash_received']) ? floatval($input['cash_received']) : 0;
    $items = isset($input['items']) ? $input['items'] : [];
    $allocation = isset($input['allocation']) ? strtoupper(trim($input['allocation'])) : 'FEFO';
    $discount_type = isset($input['discount_type']) ? trim($input['discount_type']) : 'None';
    $discount_amount = isset($input['discount_amount']) ? floatval($input['discount_amount']) : 0;
    
    // Validate discount_type - map 'Regular' to 'None' since table enum only has None, PWD, Senior
    if ($discount_type === 'Regular') {
        $discount_type = 'None';
    }
    if (!in_array($discount_type, ['None', 'PWD', 'Senior'])) {
        $discount_type = 'None';
        $discount_amount = 0;
    }

    if ($allocation !== 'FEFO' && $allocation !== 'FIFO') {
        $allocation = 'FEFO';
    }

    // Validation
    if ($user_id <= 0) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'User authentication required. Please log in again.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    if ($cash_received <= 0 || empty($items)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'All fields are required: cash_received and items array'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Start transaction
    mysqli_autocommit($conn, false);

    $error = false;
    $errorMessage = '';
    $total_amount = 0;
    $sale_items = [];

        // Process each item from the cart
        // We derive the correct batches using FEFO/FIFO rules for reliability
    foreach ($items as $item) {
        if ($error) { break; }

            $product_id = intval($item['product_id'] ?? 0);
            $sale_type = isset($item['sale_type']) ? strtolower(trim($item['sale_type'])) : 'unit';
            $requested_packages = intval($item['quantity_packages'] ?? 0);
            $requested_units = intval($item['quantity_units'] ?? 0);
            $unit_price = floatval($item['unit_price'] ?? $item['price'] ?? 0);

            if ($product_id <= 0 || $unit_price <= 0) {
            $error = true;
                $errorMessage = 'Invalid item data: missing product_id or price';
                error_log("Invalid item payload: " . json_encode($item));
            break;
        }
        
            if ($sale_type === 'package' && $requested_packages <= 0) {
                $error = true;
                $errorMessage = 'Package sale requires a package quantity greater than zero';
                break;
            }
            if ($sale_type === 'unit' && $requested_units <= 0) {
                $error = true;
                $errorMessage = 'Unit sale requires a unit quantity greater than zero';
                break;
            }

            // Get base unit type for logging/sales details
            $productQuery = "SELECT base_unit_type_id FROM products WHERE product_id = $product_id LIMIT 1";
            $productResult = mysqli_query($conn, $productQuery);
            if (!$productResult || mysqli_num_rows($productResult) == 0) {
                $error = true;
                $errorMessage = 'Product not found: ' . $product_id;
                break;
            }
            $productData = mysqli_fetch_assoc($productResult);
            $unit_type_id = intval($productData['base_unit_type_id'] ?? 0);
            if ($productResult instanceof mysqli_result) {
                mysqli_free_result($productResult);
            }

            if ($sale_type === 'package') {
                $remaining_packages = $requested_packages;

                // Build ORDER BY clause based on allocation method (FIFO or FEFO)
                if ($allocation === 'FIFO') {
                    // FIFO: First In First Out - order by delivery_date first, then expiry_date
                    $orderBy = "
                        CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
                        d.delivery_date ASC,
                        CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
                        d.expiry_date ASC,
                        d.delivery_id ASC";
                } else {
                    // FEFO: First Expiry First Out - order by expiry_date first, then delivery_date (default)
                    $orderBy = "
                        CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
                        d.expiry_date ASC,
                        CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
                        d.delivery_date ASC,
                        d.delivery_id ASC";
                }

                $batchQuery = "SELECT 
                        d.delivery_id AS batch_id,
                        d.batch_no,
                        d.expiry_date,
                        d.delivery_date AS received_date,
                        CASE 
                            WHEN d.quantity_units > 0 THEN d.quantity_units
                            ELSE 1
                        END AS units_per_package,
                        si.quantity_packages_balance
                    FROM storage_inventory si
                    INNER JOIN deliveries d ON si.delivery_id = d.delivery_id
                    LEFT JOIN products p ON d.product_id = p.product_id
                    WHERE d.product_id = $product_id
                        AND si.quantity_packages_balance > 0
                        AND (d.expiry_date >= CURDATE() OR d.expiry_date IS NULL)
                        AND d.status = 'Active'
                    ORDER BY $orderBy";
        
        $batchResult = mysqli_query($conn, $batchQuery);
                if (!$batchResult) {
            $error = true;
                    $errorMessage = 'Error fetching storage inventory: ' . mysqli_error($conn);
                    error_log("Storage allocation query failed: " . mysqli_error($conn) . " | Product ID: $product_id");
                    break;
                }

                if (mysqli_num_rows($batchResult) == 0) {
                    $error = true;
                    $errorMessage = 'Product with ID ' . $product_id . ' has no available packages in storage inventory';
                    if ($batchResult instanceof mysqli_result) { mysqli_free_result($batchResult); }
                    break;
                }

                while ($remaining_packages > 0 && ($batch = mysqli_fetch_assoc($batchResult))) {
                    $batch_id = intval($batch['batch_id'] ?? 0);
                    $available_packages = intval($batch['quantity_packages_balance'] ?? 0);
                    if ($batch_id <= 0 || $available_packages <= 0) { continue; }

                    $units_per_package = max(1, intval($batch['units_per_package'] ?? 1));
                    $packages_to_take = min($remaining_packages, $available_packages);

                    if ($packages_to_take <= 0) { continue; }

                    $line_units = $packages_to_take * $units_per_package;
                    $line_subtotal = $packages_to_take * $unit_price;

                    $sale_items[] = [
                        'batch_id' => $batch_id,
                        'batch_no' => $batch['batch_no'] ?? null,
                        'product_id' => $product_id,
                        'sale_type' => 'package',
                        'quantity_packages' => $packages_to_take,
                        'quantity_units' => $line_units,
                        'unit_price' => $unit_price,
                        'subtotal' => $line_subtotal,
                        'unit_type_id' => $unit_type_id,
                        'units_per_package' => $units_per_package,
                        'expiry_date' => $batch['expiry_date'] ?? null,
                        'delivery_date' => $batch['received_date'] ?? null
                    ];

                    $total_amount += $line_subtotal;
                    $remaining_packages -= $packages_to_take;
                }

                if ($batchResult instanceof mysqli_result) {
                    mysqli_free_result($batchResult);
                }

                if ($remaining_packages > 0 && !$error) {
                    $error = true;
                    $fulfilled = $requested_packages - $remaining_packages;
                    $errorMessage = 'Insufficient storage stock for product ID ' . $product_id . '. Requested ' . $requested_packages . ' packages, fulfilled ' . $fulfilled . ' packages.';
                    error_log("Insufficient storage packages - Product ID: $product_id, Requested: $requested_packages, Fulfilled: $fulfilled");
                    break;
                }

        } else {
                $remaining_units = $requested_units;

                // Build ORDER BY clause based on allocation method (FIFO or FEFO)
                if ($allocation === 'FIFO') {
                    // FIFO: First In First Out - order by delivery_date first, then expiry_date
                    $orderBy = "
                        CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
                        d.delivery_date ASC,
                        CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
                        d.expiry_date ASC,
                        d.delivery_id ASC";
                } else {
                    // FEFO: First Expiry First Out - order by expiry_date first, then delivery_date (default)
                    $orderBy = "
                        CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
                        d.expiry_date ASC,
                        CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
                        d.delivery_date ASC,
                        d.delivery_id ASC";
                }

                $batchQuery = "SELECT 
                        d.delivery_id AS batch_id,
                        d.batch_no,
                        d.expiry_date,
                        d.delivery_date AS received_date,
                        pi.quantity_units_balance
                    FROM pharmacy_inventory pi
                    INNER JOIN deliveries d ON pi.delivery_id = d.delivery_id
                    WHERE d.product_id = $product_id
                        AND pi.quantity_units_balance > 0
                        AND (d.expiry_date >= CURDATE() OR d.expiry_date IS NULL)
                        AND d.status = 'Active'
                    ORDER BY $orderBy";

                $batchResult = mysqli_query($conn, $batchQuery);
                if (!$batchResult) {
                    $error = true;
                    $errorMessage = 'Error fetching pharmacy inventory: ' . mysqli_error($conn);
                    error_log("Pharmacy allocation query failed: " . mysqli_error($conn) . " | Product ID: $product_id");
                    break;
                }

                if (mysqli_num_rows($batchResult) == 0) {
                    $error = true;
                    $errorMessage = 'Product with ID ' . $product_id . ' has no available units in pharmacy inventory';
                    if ($batchResult instanceof mysqli_result) { mysqli_free_result($batchResult); }
                    break;
                }

                while ($remaining_units > 0 && ($batch = mysqli_fetch_assoc($batchResult))) {
                    $batch_id = intval($batch['batch_id'] ?? 0);
                    $available_units = intval($batch['quantity_units_balance'] ?? 0);
                    if ($batch_id <= 0 || $available_units <= 0) { continue; }

                    $units_to_take = min($remaining_units, $available_units);
                    if ($units_to_take <= 0) { continue; }

                    $line_subtotal = $units_to_take * $unit_price;

                $sale_items[] = [
                    'batch_id' => $batch_id,
                    'batch_no' => $batch['batch_no'] ?? null,
                    'product_id' => $product_id,
                        'sale_type' => 'unit',
                        'quantity_packages' => 0,
                        'quantity_units' => $units_to_take,
                        'unit_price' => $unit_price,
                        'subtotal' => $line_subtotal,
                        'unit_type_id' => $unit_type_id,
                        'expiry_date' => $batch['expiry_date'] ?? null,
                        'delivery_date' => $batch['received_date'] ?? null
                ];
                
                $total_amount += $line_subtotal;
                    $remaining_units -= $units_to_take;
            }

                if ($batchResult instanceof mysqli_result) {
            mysqli_free_result($batchResult);
                }
            
                if ($remaining_units > 0 && !$error) {
                $error = true;
                    $fulfilled = $requested_units - $remaining_units;
                    $errorMessage = 'Insufficient pharmacy stock for product ID ' . $product_id . '. Requested ' . $requested_units . ' units, fulfilled ' . $fulfilled . ' units.';
                    error_log("Insufficient pharmacy units - Product ID: $product_id, Requested: $requested_units, Fulfilled: $fulfilled");
                    break;
                }
            }
        }

        // Initialize change_amount and final_total
        $change_amount = 0;
        $final_total = 0;
        
        if (!$error) {
            // Apply discount to total amount
            $final_total = $total_amount - $discount_amount;
            if ($final_total < 0) {
                $final_total = 0;
            }
            
            if ($cash_received < $final_total) {
                $error = true;
                $errorMessage = 'Cash received (₱' . number_format($cash_received, 2, '.', '') . ') is insufficient for total amount (₱' . number_format($final_total, 2, '.', '') . ')';
            } else {
                $change_amount = $cash_received - $final_total;
                if ($change_amount < 0) {
                    $change_amount = 0;
                }
            }
        }

        // Check if we have any sale items to process
        if (!$error && empty($sale_items)) {
            $error = true;
            $errorMessage = 'No sale items were created. Check if products have available stock in the correct inventory (pharmacy for units, storage for packages).';
            error_log("No sale items created - Items count: " . count($items));
        }

        // Initialize sale_id to prevent undefined variable errors
        $sale_id = 0;
        $receipt_number = '';

    if (!$error) {
            // Generate receipt number (format: REC-YYYYMMDD-XXXXX)
            $receipt_number = 'REC-' . date('Ymd') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // Insert sale header - using updated schema with final_amount, customer_name, cash_recieving, change_amount
            // Note: cash_recieving column has a typo in the database (recieving instead of receiving)
            // Ensure all values are set and valid
            $payment_method = 'Cash';
            $is_manual = 0;
            
            // Ensure customer_name is not empty
            if (empty($customer_name)) {
                $customer_name = 'Walk-in Customer';
            }
            
            // Ensure change_amount is set (should already be calculated above)
            if (!isset($change_amount) || $change_amount < 0) {
                $change_amount = 0;
            }
            
            // Ensure final_total is set
            if (!isset($final_total) || $final_total < 0) {
                $final_total = $total_amount - $discount_amount;
                if ($final_total < 0) {
                    $final_total = 0;
                }
            }
            
            // Note: final_amount is a generated column, so we don't insert it - MySQL calculates it automatically
            $insertSalesSql = "INSERT INTO sales (receipt_number, user_id, total_amount, payment_method, is_manual_sale, discount_type, discount_amount, customer_name, cash_recieving, change_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insertSalesSql);
            if (!$stmt) {
                $error = true;
                $errorMessage = 'Error preparing sales INSERT: ' . mysqli_error($conn);
                error_log("Sales INSERT Prepare Error: " . mysqli_error($conn) . " | SQL: " . $insertSalesSql);
            } else {
                // Parameter types: s=string, i=integer, d=double
                // receipt_number (s), user_id (i), total_amount (d), payment_method (s), is_manual (i), discount_type (s), discount_amount (d), customer_name (s), cash_recieving (d), change_amount (d)
                // Note: final_amount is a generated column, so we don't bind it
                // Type string: s-i-d-s-i-s-d-s-d-d = "sidsisdddd" (10 characters for 10 parameters)
                $bindResult = mysqli_stmt_bind_param($stmt, "sidsisdddd", $receipt_number, $user_id, $total_amount, $payment_method, $is_manual, $discount_type, $discount_amount, $customer_name, $cash_received, $change_amount);
                
                if (!$bindResult) {
                    $error = true;
                    $errorMessage = 'Error binding parameters: ' . mysqli_stmt_error($stmt);
                    error_log("Sales INSERT Bind Error: " . mysqli_stmt_error($stmt));
                    mysqli_stmt_close($stmt);
                } else {
                    if (!mysqli_stmt_execute($stmt)) {
                        $error = true;
                        $errorMessage = 'Error executing sales INSERT: ' . mysqli_stmt_error($stmt);
                        error_log("Sales INSERT Execute Error: " . mysqli_stmt_error($stmt) . " | Receipt: " . $receipt_number);
                    } else {
                        $sale_id = mysqli_insert_id($conn);
                        if (!$sale_id || $sale_id <= 0) {
                            $error = true;
                            $errorMessage = 'Failed to get sale_id after INSERT';
                            error_log("Sales INSERT: No sale_id returned. Last insert ID: " . mysqli_insert_id($conn));
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }

        if (!$error && $sale_id > 0 && !empty($sale_items)) {
        // Process each sale item
        foreach ($sale_items as $item) {
            if ($error) { break; }
                
            $item_product_id = intval($item['product_id']);
            $item_batch_id = intval($item['batch_id']);
                $sale_type = $item['sale_type'];
                $quantity_packages = intval($item['quantity_packages']);
                $quantity_units = intval($item['quantity_units']);
                $unit_price = floatval($item['unit_price']);
            $item_subtotal = floatval($item['subtotal']);
            $unit_type_id = intval($item['unit_type_id'] ?? 0); // For stock movement log
                
                // Use the product's base_unit_type_id for sale_unit_type_id
                // This ensures the foreign key constraint is satisfied
                // For package sales, we still use the base unit type since packages contain units of that type
                if ($unit_type_id <= 0) {
                    // Fallback: query the product's base_unit_type_id if not provided
                    $productFallbackQuery = "SELECT base_unit_type_id FROM products WHERE product_id = $item_product_id LIMIT 1";
                    $productFallbackResult = mysqli_query($conn, $productFallbackQuery);
                    if ($productFallbackResult && mysqli_num_rows($productFallbackResult) > 0) {
                        $productFallbackData = mysqli_fetch_assoc($productFallbackResult);
                        $unit_type_id = intval($productFallbackData['base_unit_type_id'] ?? 0);
                        mysqli_free_result($productFallbackResult);
                    }
                }
                $sale_unit_type_id = $unit_type_id; // Use the actual unit_type_id from the product
                
                // Validate that sale_unit_type_id exists in unit_types table
                if ($sale_unit_type_id <= 0) {
                    $error = true;
                    $errorMessage = 'Invalid unit_type_id for product ID ' . $item_product_id . '. Product must have a valid base_unit_type_id.';
                    error_log("Invalid unit_type_id - Product ID: $item_product_id, unit_type_id: $sale_unit_type_id");
                    break;
                }
                
                // Verify the unit_type_id exists in unit_types table
                $unitTypeCheck = mysqli_query($conn, "SELECT unit_type_id FROM unit_types WHERE unit_type_id = $sale_unit_type_id LIMIT 1");
                if (!$unitTypeCheck || mysqli_num_rows($unitTypeCheck) == 0) {
                    $error = true;
                    $errorMessage = 'Unit type ID ' . $sale_unit_type_id . ' does not exist in unit_types table for product ID ' . $item_product_id;
                    error_log("Unit type not found - unit_type_id: $sale_unit_type_id, Product ID: $item_product_id");
                    if ($unitTypeCheck instanceof mysqli_result) {
                        mysqli_free_result($unitTypeCheck);
                    }
                    break;
                }
                if ($unitTypeCheck instanceof mysqli_result) {
                    mysqli_free_result($unitTypeCheck);
                }
                
                // Calculate subtotal based on sale type
                // For package sales: subtotal = quantity_packages * unit_price
                // For unit sales: subtotal = quantity_units * unit_price
                if ($sale_type === 'package') {
                    $calculated_subtotal = floatval($quantity_packages) * floatval($unit_price);
                } else {
                    $calculated_subtotal = floatval($quantity_units) * floatval($unit_price);
                }
                
                // Insert into sales_details with calculated subtotal
                // Note: sales_details table now uses delivery_id instead of batch_id
                $detailSql = "INSERT INTO sales_details (sale_id, delivery_id, sale_unit_type_id, quantity_packages, quantity_units, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt_detail = mysqli_prepare($conn, $detailSql);
                if (!$stmt_detail) {
                    $error = true;
                    $errorMessage = 'Error preparing sales_details INSERT: ' . mysqli_error($conn);
                    error_log("Sales Details INSERT Prepare Error: " . mysqli_error($conn));
                    break;
                }
                
                // Insert with delivery_id (stored in $item_batch_id variable)
                mysqli_stmt_bind_param($stmt_detail, "iiiiidd", $sale_id, $item_batch_id, $sale_unit_type_id, $quantity_packages, $quantity_units, $unit_price, $calculated_subtotal);
                
                if (!mysqli_stmt_execute($stmt_detail)) {
                    $error = true;
                    $errorMessage = 'Error executing sales_details INSERT: ' . mysqli_stmt_error($stmt_detail);
                    error_log("Sales Details INSERT Execute Error: " . mysqli_stmt_error($stmt_detail));
                    mysqli_stmt_close($stmt_detail);
                    break;
                }
                mysqli_stmt_close($stmt_detail);

                // 🧮 INVENTORY DEDUCTION LOGIC
                if ($sale_type === 'package') {
                    // 🟦 PACKAGE SALE → Deduct from STORAGE_INVENTORY
                    $storageCheck = mysqli_query($conn, "SELECT storage_id, quantity_packages_out, quantity_packages_balance, quantity_units_out, quantity_units_balance FROM storage_inventory WHERE delivery_id = $item_batch_id LIMIT 1");
                    if (!$storageCheck || mysqli_num_rows($storageCheck) == 0) {
                $error = true;
                        $errorMessage = 'Storage inventory record not found for batch_id: ' . $item_batch_id;
                        error_log("Storage inventory not found - Batch: $item_batch_id");
                break;
            }
                    
                    $storageData = mysqli_fetch_assoc($storageCheck);
                    $current_packages_out = intval($storageData['quantity_packages_out'] ?? 0);
                    $current_packages_balance = intval($storageData['quantity_packages_balance'] ?? 0);
                    $current_units_out = intval($storageData['quantity_units_out'] ?? 0);
                    $units_per_package = max(1, intval($item['units_per_package'] ?? 1));
                    $units_to_deduct = $quantity_units > 0 ? $quantity_units : ($quantity_packages * $units_per_package);
                    
                    // Validate stock availability
                    if ($current_packages_balance < $quantity_packages) {
                        $error = true;
                        $errorMessage = 'Insufficient storage package balance for batch ' . $item_batch_id . '. Available: ' . $current_packages_balance . ', Needed: ' . $quantity_packages;
                        error_log("Storage package balance insufficient - Batch: $item_batch_id, Available: $current_packages_balance, Needed: $quantity_packages");
                        break;
                    }
                    
                    // Update storage inventory
                    $new_packages_out = $current_packages_out + $quantity_packages;
                    $new_units_out = $current_units_out + $units_to_deduct;
                    $updateStorage = "UPDATE storage_inventory SET 
                        quantity_packages_out = $new_packages_out,
                        quantity_units_out = $new_units_out,
                        updated_at = NOW() 
                        WHERE delivery_id = $item_batch_id";
                    
                    if (!mysqli_query($conn, $updateStorage)) {
                        $error = true;
                        $errorMessage = 'Error updating storage inventory: ' . mysqli_error($conn);
                        error_log("Storage inventory update error: " . mysqli_error($conn));
                        break;
                    }
                    
                    // Log stock movement
                    $movement_packages = $quantity_packages;
                    $movement_units = $units_to_deduct; // Total units in packages
                    $from_inventory = 'storage';
                    
                } else {
                    // 🟩 UNIT SALE → Deduct from PHARMACY_INVENTORY
                    $pharmacyCheck = mysqli_query($conn, "SELECT pharmacy_id, quantity_units_out, quantity_units_balance FROM pharmacy_inventory WHERE delivery_id = $item_batch_id LIMIT 1");
                    if (!$pharmacyCheck || mysqli_num_rows($pharmacyCheck) == 0) {
                        $error = true;
                        $errorMessage = 'Pharmacy inventory record not found for batch_id: ' . $item_batch_id . '. Please restock from storage.';
                        error_log("Pharmacy inventory not found - Batch: $item_batch_id");
                        break;
                    }
                    
                    $pharmacyData = mysqli_fetch_assoc($pharmacyCheck);
                    $current_units_out = intval($pharmacyData['quantity_units_out'] ?? 0);
                    $current_units_balance = intval($pharmacyData['quantity_units_balance'] ?? 0);
                    
                    // Validate stock availability
                    if ($current_units_balance < $quantity_units) {
                        $error = true;
                        $errorMessage = 'Insufficient pharmacy inventory balance for batch ' . $item_batch_id . '. Available: ' . $current_units_balance . ', Needed: ' . $quantity_units . '. Please restock from storage.';
                        error_log("Pharmacy inventory insufficient - Batch: $item_batch_id, Available: $current_units_balance, Needed: $quantity_units");
                        break;
                    }
                    
                    // Update pharmacy inventory
                    $new_units_out = $current_units_out + $quantity_units;
                    $updatePharmacy = "UPDATE pharmacy_inventory SET 
                        quantity_units_out = $new_units_out,
                        updated_at = NOW() 
                        WHERE delivery_id = $item_batch_id";
                    
                    if (!mysqli_query($conn, $updatePharmacy)) {
                        $error = true;
                        $errorMessage = 'Error updating pharmacy inventory: ' . mysqli_error($conn);
                        error_log("Pharmacy inventory update error: " . mysqli_error($conn));
                        break;
                    }
                    
                    // Log stock movement
                    $movement_packages = 0;
                    $movement_units = $quantity_units;
                    $from_inventory = 'pharmacy';
                }
                
                // Insert into Stock_Movement_Log
                // Note: batch_id column may still exist but references removed drug_batches table
                // We omit batch_id from the INSERT to avoid foreign key constraint issues
                $movementSql = "INSERT INTO stock_movement_log 
                    (product_id, movement_type, from_inventory, to_inventory, quantity_packages, quantity_units, unit_type_id, reference_type, reference_id, movement_date, user_id) 
                    VALUES (
                        $item_product_id,
                        'sale',
                        '$from_inventory',
                        'customer',
                        $movement_packages,
                        $movement_units,
                        $unit_type_id,
                        'sale',
                        $sale_id,
                        NOW(),
                        $user_id
                    )";
                if (!@mysqli_query($conn, $movementSql)) {
                    // If the query fails, try with batch_id set to NULL (in case column exists)
                    $errorMsg = mysqli_error($conn);
                    if ($errorMsg && (strpos($errorMsg, 'batch_id') !== false || strpos($errorMsg, 'Column count') !== false)) {
                        $movementSql = "INSERT INTO stock_movement_log 
                            (product_id, batch_id, movement_type, from_inventory, to_inventory, quantity_packages, quantity_units, unit_type_id, reference_type, reference_id, movement_date, user_id) 
                            VALUES (
                                $item_product_id,
                                NULL,
                                'sale',
                                '$from_inventory',
                                'customer',
                                $movement_packages,
                                $movement_units,
                                $unit_type_id,
                                'sale',
                                $sale_id,
                                NOW(),
                                $user_id
                            )";
                        if (!@mysqli_query($conn, $movementSql)) {
                            $error = true;
                            $errorMessage = 'Error logging stock movement: ' . mysqli_error($conn);
                            error_log("Stock movement log error: " . mysqli_error($conn));
                            break;
                        }
                    } else {
                        $error = true;
                        $errorMessage = 'Error logging stock movement: ' . $errorMsg;
                        error_log("Stock movement log error: " . $errorMsg);
                        break;
                    }
                }
        }
    }

    if ($error) {
        // Rollback transaction on error
        mysqli_rollback($conn);
            // Clean any output buffer before sending error
            ob_end_clean();
        http_response_code(500);
            
            // Ensure error message is set
            if (empty($errorMessage)) {
                $errorMessage = 'An unknown error occurred during transaction processing';
            }
            
            // Log the error for debugging
            error_log("processPOSSaleController Transaction Error: " . $errorMessage);
            
        echo json_encode([
            'success' => false,
            'error' => $errorMessage
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
            // Only commit if we have a valid sale_id and no errors occurred during item processing
            if ($sale_id > 0 && !$error) {
        // Commit transaction
                if (!mysqli_commit($conn)) {
                    $error = true;
                    $errorMessage = 'Failed to commit transaction: ' . mysqli_error($conn);
                    mysqli_rollback($conn);
                    ob_end_clean();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => $errorMessage
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
        // Generate receipt HTML and save to session for printing
                    // Wrap in output buffering to catch any accidental output
                    @ob_start();
                    try {
                        if (function_exists('generateReceipt')) {
        generateReceipt($conn, $sale_id, $customer_name, $cash_received, $change_amount);
                        }
                    } catch (Exception $e) {
                        // Log receipt generation errors but don't fail the sale
                        error_log("Receipt generation error: " . $e->getMessage());
                    }
                    // Discard any output from receipt generation
                    @ob_end_clean();
                    
                    // Ensure output buffer is clean before sending JSON
                    while (ob_get_level() > 0) {
                        @ob_end_clean();
                    }
        
        // Return success with sale details - including all fields from updated sales table
        echo json_encode([
            'success' => true,
            'message' => 'Sale completed successfully',
            'sale' => [
                'sale_id' => $sale_id,
                'receipt_number' => $receipt_number ?? '',
                'user_id' => $user_id,
                'total_amount' => $total_amount,
                'final_amount' => $final_total,
                'payment_method' => 'Cash',
                'is_manual_sale' => 0,
                'subtotal' => $total_amount,
                'discount_type' => $discount_type,
                'discount_amount' => $discount_amount,
                'tax' => 0,
                'cash_received' => $cash_received,
                'change_amount' => $change_amount ?? 0,
                'customer_name' => $customer_name
            ]
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            } else {
                // No sale_id or error occurred - handle it
                if ($sale_id <= 0) {
                    $errorMessage = 'Sale ID is missing. Transaction may not have been created.';
                } elseif ($error) {
                    // Error occurred during item processing - rollback
                    mysqli_rollback($conn);
                }
                
                ob_end_clean();
                http_response_code(500);
                
                if (empty($errorMessage)) {
                    $errorMessage = 'An unknown error occurred during transaction processing';
                }
                
                echo json_encode([
                    'success' => false,
                    'error' => $errorMessage,
                    'sale_id' => $sale_id > 0 ? $sale_id : null
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        
        // Restore autocommit (connection will be closed automatically by PHP)
        if (isset($conn) && $conn !== null) {
            try {
                @mysqli_autocommit($conn, true);
            } catch (Exception $e) {
                // Connection might already be closed, ignore
            }
        }
        
    } catch (Throwable $e) {
        // Catch any unhandled errors (including fatal errors)
        ob_end_clean();
        http_response_code(500);
        $errorMsg = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        
        // Log the full error
        error_log("processPOSSaleController Fatal Error: " . $errorMsg . " in " . $errorFile . " on line " . $errorLine);
        
        // Return JSON error (safe error message for production)
        echo json_encode([
            'success' => false,
            'error' => 'An unexpected error occurred. Please try again.',
            'debug' => $errorMsg // Remove this in production
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Cleanup: Rollback transaction if active
        // Don't close connection here - let PHP handle it automatically
        if (isset($conn) && $conn !== null) {
            try {
                @mysqli_rollback($conn);
                @mysqli_autocommit($conn, true);
            } catch (Exception $e) {
                // Connection might already be closed, ignore
            }
        }
    }
    
} else {
    ob_end_clean();
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Flush any remaining buffered output so the JSON response is sent
if (ob_get_level() > 0) {
    @ob_end_flush();
}

/**
 * Generate receipt HTML and save to session
 */
function generateReceipt($conn, $sale_id, $customer_name, $cash_received = 0, $change_amount = 0) {
    // Suppress any warnings/notices that might break JSON output
    $oldErrorReporting = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
    
    try {
        // Get sale details with product information - including customer_name from sales table
    $query = "
        SELECT 
            s.sale_id,
            s.receipt_number,
            s.total_amount,
            s.final_amount,
            s.payment_method,
            s.customer_name,
            s.cash_recieving,
            s.change_amount,
            s.discount_type,
            s.discount_amount,
            s.created_at,
            u.username as cashier_name,
            p.brand_name,
            p.generic_name,
            sd.quantity_packages,
            sd.quantity_units,
            sd.unit_price,
            sd.subtotal
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.user_id
        LEFT JOIN sales_details sd ON s.sale_id = sd.sale_id
        LEFT JOIN deliveries d ON sd.delivery_id = d.delivery_id
        LEFT JOIN products p ON d.product_id = p.product_id
        WHERE s.sale_id = ?
        ORDER BY sd.detail_id ASC
    ";
    
    $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            throw new Exception('Failed to prepare receipt query: ' . mysqli_error($conn));
        }
        
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to execute receipt query: ' . mysqli_stmt_error($stmt));
        }
        
    $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            throw new Exception('Failed to get result: ' . mysqli_error($conn));
        }
    
    $sale_data = [];
    $items = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        if (empty($sale_data)) {
            // Use customer_name from database if available, otherwise use parameter
            $db_customer_name = $row['customer_name'] ?? $customer_name;
            if (empty($db_customer_name)) {
                $db_customer_name = 'Walk-in Customer';
            }
            
            $sale_data = [
                'sale_id' => $row['sale_id'],
                'receipt_number' => $row['receipt_number'] ?? '',
                'total_amount' => $row['total_amount'] ?? 0,
                'final_amount' => $row['final_amount'] ?? $row['total_amount'] ?? 0,
                'payment_method' => $row['payment_method'] ?? 'Cash',
                'customer_name' => $db_customer_name,
                'cash_received' => isset($row['cash_recieving']) ? floatval($row['cash_recieving']) : $cash_received,
                'change_amount' => isset($row['change_amount']) ? floatval($row['change_amount']) : $change_amount,
                'discount_type' => $row['discount_type'] ?? 'None',
                'discount_amount' => isset($row['discount_amount']) ? floatval($row['discount_amount']) : 0,
                'created_at' => $row['created_at'],
                'cashier_name' => $row['cashier_name'] ?? 'Cashier'
            ];
            
            // Update customer_name variable for use in receipt generation
            $customer_name = $db_customer_name;
        }
            
            // Calculate total quantity from packages and units
            $quantity = intval($row['quantity_packages'] ?? 0) + intval($row['quantity_units'] ?? 0);
        
        $items[] = [
            'brand_name' => $row['brand_name'],
            'generic_name' => $row['generic_name'],
                'quantity' => $quantity,
                'price' => $row['unit_price'],
            'subtotal' => $row['subtotal']
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    // Generate receipt HTML
        $calculatedSubtotal = array_reduce($items, function ($carry, $item) {
            return $carry + floatval($item['subtotal'] ?? 0);
        }, 0.0);

        $receipt_html = generateXinyeThermalReceiptHTML(
            $sale_data,
            $items,
            [
                'customer_name'  => $sale_data['customer_name'] ?? $customer_name ?? 'Walk-in Customer',
                'subtotal'       => $calculatedSubtotal,
                'tax_amount'     => 0,
                'total_amount'   => $sale_data['final_amount'] ?? $sale_data['total_amount'] ?? 0,
                'cash_received'  => $sale_data['cash_received'] ?? $cash_received ?? null,
                'change_amount'  => $sale_data['change_amount'] ?? $change_amount ?? null,
                'discount_type'  => $sale_data['discount_type'] ?? 'None',
                'discount_amount' => $sale_data['discount_amount'] ?? 0,
                'payment_method' => $sale_data['payment_method'] ?? 'Cash',
            ]
        );
        
        // Save to session for printing (only if session not already started and headers not sent)
        if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['last_receipt_html'] = $receipt_html;
    $_SESSION['last_sale_id'] = $sale_id;
        }
    } catch (Exception $e) {
        // Log error but don't throw - receipt generation shouldn't fail the sale
        error_log("generateReceipt error: " . $e->getMessage());
    } finally {
        // Restore error reporting
        error_reporting($oldErrorReporting);
    }
}
?>
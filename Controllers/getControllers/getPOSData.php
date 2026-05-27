<?php
// API endpoint to get POS products data
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
    $response = ['success' => true, 'products' => []];

    // Check for available views (priority: products_for_sale > fifofefo > ready_for_sale > available_products)
    $has_products_for_sale_view = false;
    $has_fifofefo_view = false;
    $has_ready_for_sale_view = false;
    $has_available_products_view = false;
    
    try {
        $views_check = mysqli_query($conn, "SHOW FULL TABLES WHERE TABLE_TYPE = 'VIEW'");
        if ($views_check) {
            while ($vr = mysqli_fetch_array($views_check)) {
                $viewName = strtolower($vr[0]);
                if ($viewName === 'view_products_for_sale') { 
                    $has_products_for_sale_view = true; 
                }
                if ($viewName === 'view_fifofefo_available_products') { 
                    $has_fifofefo_view = true; 
                }
                if ($viewName === 'view_ready_for_sale') { 
                    $has_ready_for_sale_view = true; 
                }
                if ($viewName === 'view_available_products') { 
                    $has_available_products_view = true; 
                }
            }
            mysqli_free_result($views_check);
        }
    } catch (Exception $e) {
        // If view check fails, continue with fallback options
        error_log("View check error: " . $e->getMessage());
    }

    if ($has_products_for_sale_view) {
        // Use view_products_for_sale (highest priority - most complete view)
        // Aggregate by product_id (sum stock across deliveries, use FEFO/FIFO priority)
        // Get prices from the earliest expiring batch (FEFO)
        $sql = "SELECT 
                    vpfs.product_id,
                    MAX(vpfs.delivery_id) as delivery_id, -- Reference delivery for backend selection
                    MAX(vpfs.brand_name) as brand_name,
                    MAX(vpfs.generic_name) as generic_name,
                    MAX(vpfs.strength) as strength,
                    MAX(vpfs.barcode) as barcode,
                    MAX(vpfs.category_name) as category_name,
                    MAX(vpfs.batch_no) as batch_no,
                    MIN(vpfs.expiry_date) as earliest_expiry_date, -- FEFO: earliest expiry first
                    MIN(vpfs.manufacturing_date) as earliest_manufacturing_date,
                    MIN(vpfs.delivery_date) as earliest_delivery_date, -- FIFO: earliest delivery first
                    MAX(vpfs.fifo_fefo_priority) as fifo_fefo_priority,
                    MAX(p.product_threshold) as product_threshold,
                    MAX(p.expire_threshold) as expire_threshold,
                    MAX(p.expire_threshold) as expire_threshold,
                    -- Get prices from earliest expiring batch (FEFO) - use subquery to find first delivery
                    (SELECT vpfs2.cost_per_package FROM view_products_for_sale vpfs2 
                     WHERE vpfs2.product_id = vpfs.product_id
                       AND (vpfs2.availability_status = 'Available' OR vpfs2.availability_status IS NULL)
                       AND (vpfs2.expiry_date IS NULL OR vpfs2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vpfs2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vpfs2.expiry_date ASC,
                       CASE WHEN vpfs2.delivery_date IS NULL THEN 1 ELSE 0 END,
                       vpfs2.delivery_date ASC
                     LIMIT 1) as cost_per_package,
                    (SELECT vpfs2.cost_per_unit FROM view_products_for_sale vpfs2 
                     WHERE vpfs2.product_id = vpfs.product_id
                       AND (vpfs2.availability_status = 'Available' OR vpfs2.availability_status IS NULL)
                       AND (vpfs2.expiry_date IS NULL OR vpfs2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vpfs2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vpfs2.expiry_date ASC,
                       CASE WHEN vpfs2.delivery_date IS NULL THEN 1 ELSE 0 END,
                       vpfs2.delivery_date ASC
                     LIMIT 1) as cost_per_unit,
                    (SELECT vpfs2.selling_price_package FROM view_products_for_sale vpfs2 
                     WHERE vpfs2.product_id = vpfs.product_id
                       AND (vpfs2.availability_status = 'Available' OR vpfs2.availability_status IS NULL)
                       AND (vpfs2.expiry_date IS NULL OR vpfs2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vpfs2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vpfs2.expiry_date ASC,
                       CASE WHEN vpfs2.delivery_date IS NULL THEN 1 ELSE 0 END,
                       vpfs2.delivery_date ASC
                     LIMIT 1) as selling_price_package,
                    (SELECT vpfs2.selling_price_unit FROM view_products_for_sale vpfs2 
                     WHERE vpfs2.product_id = vpfs.product_id
                       AND (vpfs2.availability_status = 'Available' OR vpfs2.availability_status IS NULL)
                       AND (vpfs2.expiry_date IS NULL OR vpfs2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vpfs2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vpfs2.expiry_date ASC,
                       CASE WHEN vpfs2.delivery_date IS NULL THEN 1 ELSE 0 END,
                       vpfs2.delivery_date ASC
                     LIMIT 1) as selling_price_unit,
                    SUM(CASE WHEN (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE()) THEN COALESCE(vpfs.storage_packages_balance, 0) ELSE 0 END) as storage_packages_balance,
                    SUM(CASE WHEN (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE()) THEN COALESCE(vpfs.pharmacy_units_balance, 0) ELSE 0 END) as pharmacy_units_balance,
                    (
                      SUM(CASE WHEN (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE()) THEN COALESCE(vpfs.pharmacy_units_balance, 0) ELSE 0 END) +
                      SUM(CASE WHEN (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE()) THEN COALESCE(vpfs.storage_packages_balance, 0) ELSE 0 END)
                    ) as total_units_available,
                    MAX(vpfs.availability_status) as availability_status,
                    COUNT(DISTINCT CASE 
                        WHEN (
                            (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE())
                            AND (
                                COALESCE(vpfs.storage_packages_balance, 0) > 0
                                OR COALESCE(vpfs.pharmacy_units_balance, 0) > 0
                            )
                        ) THEN vpfs.delivery_id 
                        ELSE NULL 
                    END) as batch_count
                FROM view_products_for_sale vpfs
                LEFT JOIN products p ON vpfs.product_id = p.product_id
                GROUP BY vpfs.product_id
                HAVING (
                    SUM(CASE WHEN (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE()) THEN COALESCE(vpfs.storage_packages_balance, 0) ELSE 0 END) > 0
                    OR
                    SUM(CASE WHEN (vpfs.expiry_date IS NULL OR vpfs.expiry_date >= CURDATE()) THEN COALESCE(vpfs.pharmacy_units_balance, 0) ELSE 0 END) > 0
                )
                ORDER BY 
                    CASE WHEN earliest_expiry_date IS NULL THEN 1 ELSE 0 END,
                    earliest_expiry_date ASC,
                    CASE WHEN earliest_delivery_date IS NULL THEN 1 ELSE 0 END,
                    earliest_delivery_date ASC,
                    MAX(vpfs.brand_name) ASC";
    } elseif ($has_fifofefo_view) {
        // Use view_fifofefo_available_products (FIFO/FEFO optimized)
        // Aggregate by product_id (sum stock across batches, use price from earliest expiring batch)
        // Join with products table to get category_id, strength, and unit_type_id
        $sql = "SELECT 
                    vfap.product_id,
                    MAX(vfap.brand_name) as brand_name,
                    MAX(vfap.generic_name) as generic_name,
                    MAX(p.category_id) as category_id,
                    MAX(p.strength) as strength,
                    MAX(p.base_unit_type_id) as unit_id,
                    MAX(c.category_name) as category_name,
                    MAX(ut.unit_name) as unit_name,
                    SUM(vfap.total_units_available) as total_units_available,
                    SUM(vfap.pharmacy_units_balance) as pharmacy_units_balance,
                    SUM(vfap.storage_units_balance) as storage_units_balance,
                    SUM(vfap.storage_packages_balance) as storage_packages_balance,
                    -- Get prices from earliest expiring batch (FEFO)
                    (SELECT vfap2.selling_price_unit FROM view_fifofefo_available_products vfap2 
                     WHERE vfap2.product_id = vfap.product_id
                       AND vfap2.total_units_available > 0
                       AND vfap2.stock_status != 'Expired'
                       AND (vfap2.expiry_date IS NULL OR vfap2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vfap2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vfap2.expiry_date ASC,
                       CASE WHEN vfap2.delivery_date IS NULL THEN 1 ELSE 0 END,
                       vfap2.delivery_date ASC
                     LIMIT 1) as selling_price_unit,
                    (SELECT vfap2.selling_price_package FROM view_fifofefo_available_products vfap2 
                     WHERE vfap2.product_id = vfap.product_id
                       AND vfap2.total_units_available > 0
                       AND vfap2.stock_status != 'Expired'
                       AND (vfap2.expiry_date IS NULL OR vfap2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vfap2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vfap2.expiry_date ASC,
                       CASE WHEN vfap2.delivery_date IS NULL THEN 1 ELSE 0 END,
                       vfap2.delivery_date ASC
                     LIMIT 1) as selling_price_package,
                    (SELECT vfap2.cost_per_unit FROM view_fifofefo_available_products vfap2 
                     WHERE vfap2.product_id = vfap.product_id
                       AND vfap2.total_units_available > 0
                       AND vfap2.stock_status != 'Expired'
                       AND (vfap2.expiry_date IS NULL OR vfap2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vfap2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vfap2.expiry_date ASC,
                       CASE WHEN vfap2.delivery_date IS NULL THEN 1 ELSE 0 END,
                       vfap2.delivery_date ASC
                     LIMIT 1) as cost_per_unit,
                    (SELECT vfap2.cost_per_package FROM view_fifofefo_available_products vfap2 
                     WHERE vfap2.product_id = vfap.product_id
                       AND vfap2.total_units_available > 0
                       AND vfap2.stock_status != 'Expired'
                       AND (vfap2.expiry_date IS NULL OR vfap2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vfap2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vfap2.expiry_date ASC,
                       CASE WHEN vfap2.delivery_date IS NULL THEN 1 ELSE 0 END,
                       vfap2.delivery_date ASC
                     LIMIT 1) as cost_per_package,
                    MIN(vfap.expiry_date) as earliest_expiry_date,
                    MIN(vfap.delivery_date) as earliest_delivery_date,
                    COUNT(DISTINCT CASE 
                        WHEN (
                            (vfap.expiry_date IS NULL OR vfap.expiry_date >= CURDATE())
                            AND (
                                COALESCE(vfap.storage_packages_balance, 0) > 0
                                OR COALESCE(vfap.pharmacy_units_balance, 0) > 0
                                OR COALESCE(vfap.total_units_available, 0) > 0
                            )
                        ) THEN vfap.batch_id 
                        ELSE NULL 
                    END) as batch_count,
                    MIN(vfap.stock_status) as stock_status,
                    MAX(p.barcode) as barcode_value,
                    MAX(p.product_threshold) as product_threshold,
                    MAX(p.expire_threshold) as expire_threshold
                FROM view_fifofefo_available_products vfap
                LEFT JOIN products p ON vfap.product_id = p.product_id
                LEFT JOIN Categories c ON p.category_id = c.category_id
                LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
                WHERE (vfap.storage_packages_balance > 0 OR vfap.pharmacy_units_balance > 0 OR vfap.total_units_available > 0)
                  AND (vfap.expiry_date IS NULL OR vfap.expiry_date >= CURDATE())
                  AND (p.is_active = 1 OR p.is_active IS NULL)
                GROUP BY vfap.product_id
                ORDER BY 
                    CASE WHEN earliest_expiry_date IS NULL THEN 1 ELSE 0 END,
                    earliest_expiry_date ASC,
                    CASE WHEN earliest_delivery_date IS NULL THEN 1 ELSE 0 END,
                    earliest_delivery_date ASC,
                    brand_name ASC";
    } elseif ($has_ready_for_sale_view) {
        // Use view_ready_for_sale (fallback)
        // Aggregate by product_id (sum stock across batches, use price from earliest expiring batch)
        // Join with products table to get category_id, strength, and unit_type_id
                $sql = "SELECT 
                    vrs.product_id,
                    SUM(vrs.total_units_available) as total_units_available,
                    SUM(vrs.pharmacy_units_available) as pharmacy_units_available,
                    SUM(vrs.storage_packages_available) as storage_packages_available,
                    -- Get prices from earliest expiring batch (FEFO)
                    (SELECT vrs2.selling_price_unit FROM view_ready_for_sale vrs2 
                     WHERE vrs2.product_id = vrs.product_id
                       AND vrs2.total_units_available > 0
                       AND vrs2.stock_status = 'Available'
                       AND (vrs2.expiry_date IS NULL OR vrs2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vrs2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vrs2.expiry_date ASC
                     LIMIT 1) as selling_price_unit,
                    (SELECT vrs2.selling_price_package FROM view_ready_for_sale vrs2 
                     WHERE vrs2.product_id = vrs.product_id
                       AND vrs2.total_units_available > 0
                       AND vrs2.stock_status = 'Available'
                       AND (vrs2.expiry_date IS NULL OR vrs2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vrs2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vrs2.expiry_date ASC
                     LIMIT 1) as selling_price_package,
                    MIN(vrs.expiry_date) as earliest_expiry_date,
                    MIN(d.delivery_date) as earliest_delivery_date,
                    COUNT(DISTINCT vrs.batch_id) as batch_count,
                    MAX(p.barcode) as barcode_value,
                    MAX(vrs.brand_name) as brand_name,
                    MAX(vrs.generic_name) as generic_name,
                    MAX(p.category_id) as category_id,
                    MAX(p.strength) as strength,
                    MAX(p.base_unit_type_id) as unit_id,
                    MAX(c.category_name) as category_name,
                    MAX(ut.unit_name) as unit_name,
                    MAX(p.product_threshold) as product_threshold
                FROM view_ready_for_sale vrs
                LEFT JOIN products p ON vrs.product_id = p.product_id
                LEFT JOIN Categories c ON p.category_id = c.category_id
                LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
                LEFT JOIN deliveries d ON vrs.product_id = d.product_id AND d.batch_no IS NOT NULL
                WHERE (vrs.storage_packages_available > 0 OR vrs.pharmacy_units_available > 0 OR vrs.total_units_available > 0)
                  AND (vrs.expiry_date IS NULL OR vrs.expiry_date >= CURDATE())
                  AND (p.is_active = 1 OR p.is_active IS NULL)
                GROUP BY vrs.product_id
                ORDER BY 
                    CASE WHEN earliest_expiry_date IS NULL THEN 1 ELSE 0 END,
                    earliest_expiry_date ASC,
                    CASE WHEN earliest_delivery_date IS NULL THEN 1 ELSE 0 END,
                    earliest_delivery_date ASC,
                    vrs.brand_name ASC";
    } elseif ($has_available_products_view) {
        // Use view_available_products - aggregate by product_id to combine all batches
        // Show all available products with their details
        // Get prices from earliest expiring batch (FEFO)
        $sql = "SELECT 
                    vap.product_id,
                    MAX(vap.delivery_id) as delivery_id, -- Reference delivery, actual selection at sale time
                    MAX(vap.brand_name) as brand_name,
                    MAX(vap.generic_name) as generic_name,
                    MAX(vap.strength) as strength,
                    MAX(vap.barcode) as barcode,
                    MAX(vap.unit_type) as unit_type,
                    MAX(vap.package_type) as package_type,
                    MAX(vap.batch_no) as batch_no,
                    MIN(vap.expiry_date) as earliest_expiry_date,
                    MIN(vap.received_date) as earliest_received_date,
                    MIN(vap.manufacturing_date) as earliest_manufacturing_date,
                    SUM(COALESCE(vap.available_packages, 0)) as available_packages,
                    SUM(COALESCE(vap.available_units_storage, 0)) as available_units_storage,
                    SUM(COALESCE(vap.available_units_pharmacy, 0)) as available_units_pharmacy,
                    (SUM(COALESCE(vap.available_units_pharmacy, 0)) + SUM(COALESCE(vap.available_units_storage, 0)) + 
                     (SUM(COALESCE(vap.available_packages, 0)) * COALESCE(MAX(d.quantity_units / NULLIF(d.quantity_packages, 0)), 1))) as total_units_available,
                    -- Get prices from earliest expiring batch (FEFO)
                    (SELECT vap2.selling_price_package FROM view_available_products vap2 
                     WHERE vap2.product_id = vap.product_id
                       AND (vap2.status != 'Expired' OR vap2.status IS NULL)
                       AND (vap2.expiry_date IS NULL OR vap2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vap2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vap2.expiry_date ASC,
                       CASE WHEN vap2.received_date IS NULL THEN 1 ELSE 0 END,
                       vap2.received_date ASC
                     LIMIT 1) as selling_price_package,
                    (SELECT vap2.selling_price_unit FROM view_available_products vap2 
                     WHERE vap2.product_id = vap.product_id
                       AND (vap2.status != 'Expired' OR vap2.status IS NULL)
                       AND (vap2.expiry_date IS NULL OR vap2.expiry_date >= CURDATE())
                     ORDER BY 
                       CASE WHEN vap2.expiry_date IS NULL THEN 1 ELSE 0 END,
                       vap2.expiry_date ASC,
                       CASE WHEN vap2.received_date IS NULL THEN 1 ELSE 0 END,
                       vap2.received_date ASC
                     LIMIT 1) as selling_price_unit,
                    MAX(vap.status) as stock_status,
                    MAX(vap.stock_condition) as stock_condition,
                    MAX(p.category_id) as category_id,
                    MAX(c.category_name) as category_name,
                    MAX(p.base_unit_type_id) as unit_id,
                    MAX(ut.unit_name) as unit_name,
                    COUNT(DISTINCT vap.delivery_id) as batch_count,
                    MAX(p.product_threshold) as product_threshold,
                    MAX(p.expire_threshold) as expire_threshold
                FROM view_available_products vap
                LEFT JOIN products p ON vap.product_id = p.product_id
                LEFT JOIN Categories c ON p.category_id = c.category_id
                LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
                LEFT JOIN deliveries d ON vap.delivery_id = d.delivery_id
                WHERE 1=1
                  AND (vap.expiry_date IS NULL OR vap.expiry_date >= CURDATE())
                  AND (p.is_active = 1 OR p.is_active IS NULL)
                GROUP BY vap.product_id
                HAVING (available_packages > 0 OR available_units_storage > 0 OR available_units_pharmacy > 0 OR total_units_available > 0)
                ORDER BY 
                    CASE WHEN earliest_expiry_date IS NULL THEN 1 ELSE 0 END,
                    earliest_expiry_date ASC,
                    CASE WHEN earliest_received_date IS NULL THEN 1 ELSE 0 END,
                    earliest_received_date ASC,
                    brand_name ASC"; // FEFO (expiry first) then FIFO (delivery first)
    } else {
        // Final fallback: aggregate stock from storage and pharmacy tables per product
        // Use FEFO/FIFO ordering for pricing and delivery references
        $sql = "SELECT pd.*
                FROM (
                    SELECT 
                        p.product_id,
                    p.brand_name,
                    p.generic_name,
                        p.product_threshold,
                        p.expire_threshold,
                    p.strength,
                        p.category_id,
                        p.base_unit_type_id AS unit_id,
                        p.barcode,
                    c.category_name,
                        ut.unit_name,
                        COALESCE((
                            SELECT SUM(pi.quantity_units_balance)
                            FROM pharmacy_inventory pi
                            INNER JOIN deliveries d ON pi.delivery_id = d.delivery_id
                            WHERE d.product_id = p.product_id
                              AND d.status = 'Active'
                              AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                        ), 0) AS pharmacy_units_balance,
                        COALESCE((
                            SELECT SUM(si.quantity_units_balance)
                            FROM storage_inventory si
                            INNER JOIN deliveries d ON si.delivery_id = d.delivery_id
                            WHERE d.product_id = p.product_id
                              AND d.status = 'Active'
                              AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                        ), 0) AS storage_units_balance,
                        COALESCE((
                            SELECT SUM(si.quantity_packages_balance)
                            FROM storage_inventory si
                            INNER JOIN deliveries d ON si.delivery_id = d.delivery_id
                            WHERE d.product_id = p.product_id
                              AND d.status = 'Active'
                              AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                        ), 0) AS storage_packages_balance,
                        COALESCE((
                            SELECT MIN(d.expiry_date)
                            FROM deliveries d
                            WHERE d.product_id = p.product_id
                              AND d.status = 'Active'
                              AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                              AND (
                                  (SELECT SUM(pi.quantity_units_balance) FROM pharmacy_inventory pi WHERE pi.delivery_id = d.delivery_id) > 0
                                  OR
                                  (SELECT SUM(si.quantity_packages_balance) FROM storage_inventory si WHERE si.delivery_id = d.delivery_id) > 0
                              )
                        ), NULL) AS earliest_expiry_date,
                        COALESCE((
                            SELECT MIN(d.delivery_date)
                            FROM deliveries d
                            WHERE d.product_id = p.product_id
                              AND d.status = 'Active'
                              AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                              AND (
                                  (SELECT SUM(pi.quantity_units_balance) FROM pharmacy_inventory pi WHERE pi.delivery_id = d.delivery_id) > 0
                                  OR
                                  (SELECT SUM(si.quantity_packages_balance) FROM storage_inventory si WHERE si.delivery_id = d.delivery_id) > 0
                              )
                        ), NULL) AS earliest_delivery_date,
                        COALESCE((
                            SELECT d.delivery_id
                            FROM deliveries d
                            WHERE d.product_id = p.product_id
                              AND d.status = 'Active'
                              AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                              AND (
                                  (SELECT SUM(pi.quantity_units_balance) FROM pharmacy_inventory pi WHERE pi.delivery_id = d.delivery_id) > 0
                                  OR
                                  (SELECT SUM(si.quantity_packages_balance) FROM storage_inventory si WHERE si.delivery_id = d.delivery_id) > 0
                              )
                            ORDER BY 
                                CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
                    d.expiry_date,
                                CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
                                d.delivery_date,
                                d.delivery_id
                            LIMIT 1
                        ), NULL) AS delivery_id,
                        COALESCE((
                            SELECT d.selling_price_unit
                            FROM deliveries d
                            WHERE d.product_id = p.product_id
                              AND d.status = 'Active'
                              AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                              AND (
                                  (SELECT SUM(pi.quantity_units_balance) FROM pharmacy_inventory pi WHERE pi.delivery_id = d.delivery_id) > 0
                                  OR
                                  (SELECT SUM(si.quantity_packages_balance) FROM storage_inventory si WHERE si.delivery_id = d.delivery_id) > 0
                       )
                     ORDER BY 
                                CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
                                d.expiry_date,
                                CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
                                d.delivery_date,
                                d.delivery_id
                            LIMIT 1
                        ), 0) AS selling_price_unit,
                        COALESCE((
                            SELECT d.selling_price_package
                            FROM deliveries d
                            WHERE d.product_id = p.product_id
                              AND d.status = 'Active'
                              AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                              AND (
                                  (SELECT SUM(pi.quantity_units_balance) FROM pharmacy_inventory pi WHERE pi.delivery_id = d.delivery_id) > 0
                                  OR
                                  (SELECT SUM(si.quantity_packages_balance) FROM storage_inventory si WHERE si.delivery_id = d.delivery_id) > 0
                       )
                     ORDER BY 
                                CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
                                d.expiry_date,
                                CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
                                d.delivery_date,
                                d.delivery_id
                            LIMIT 1
                        ), 0) AS selling_price_package,
                        COALESCE((
                            SELECT CASE 
                                WHEN d.quantity_packages > 0 THEN d.quantity_units / d.quantity_packages
                                ELSE NULL
                            END
                            FROM deliveries d
                            WHERE d.product_id = p.product_id
                              AND d.status = 'Active'
                              AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                              AND (
                                  (SELECT SUM(pi.quantity_units_balance) FROM pharmacy_inventory pi WHERE pi.delivery_id = d.delivery_id) > 0
                                  OR
                                  (SELECT SUM(si.quantity_packages_balance) FROM storage_inventory si WHERE si.delivery_id = d.delivery_id) > 0
                       )
                     ORDER BY 
                                CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END,
                                d.expiry_date,
                                CASE WHEN d.delivery_date IS NULL THEN 1 ELSE 0 END,
                                d.delivery_date,
                                d.delivery_id
                            LIMIT 1
                        ), NULL) AS units_per_package_estimate,
                        COALESCE((
                            SELECT COUNT(DISTINCT d.delivery_id)
                            FROM deliveries d
                            WHERE d.product_id = p.product_id
                              AND d.status = 'Active'
                              AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                              AND (
                                  (SELECT SUM(pi.quantity_units_balance) FROM pharmacy_inventory pi WHERE pi.delivery_id = d.delivery_id) > 0
                                  OR
                                  (SELECT SUM(si.quantity_packages_balance) FROM storage_inventory si WHERE si.delivery_id = d.delivery_id) > 0
                              )
                        ), 0) AS batch_count
                FROM products p
                LEFT JOIN Categories c ON p.category_id = c.category_id
                    LEFT JOIN unit_types ut ON p.base_unit_type_id = ut.unit_type_id
                    WHERE p.is_active = 1 OR p.is_active IS NULL
                ) AS pd
                WHERE (pd.pharmacy_units_balance > 0 
                       OR pd.storage_units_balance > 0 
                       OR pd.storage_packages_balance > 0)
                ORDER BY 
                    CASE WHEN pd.earliest_expiry_date IS NULL THEN 1 ELSE 0 END,
                    pd.earliest_expiry_date,
                    CASE WHEN pd.earliest_delivery_date IS NULL THEN 1 ELSE 0 END,
                    pd.earliest_delivery_date,
                    pd.brand_name ASC";
    }

    // Try to execute the SQL query with error handling
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        // Log the SQL error for debugging
        $error = mysqli_error($conn);
        $errorMessage = "SQL Error: " . $error;
        
        // If using products_for_sale view and it fails, try fallback
        if ($has_products_for_sale_view && strpos($error, 'view_products_for_sale') !== false) {
            // View might not exist, fallback to next option
            $has_products_for_sale_view = false;
            // Re-execute with fallback logic (will be handled in next request)
            throw new Exception("View not accessible. Please use fallback options. Original error: " . $error);
        }
        // If using fifofefo view and it fails, try fallback
        if ($has_fifofefo_view && strpos($error, 'view_fifofefo_available_products') !== false) {
            // View might not exist, fallback to next option
            $has_fifofefo_view = false;
            // Re-execute with fallback logic (will be handled in next request)
            throw new Exception("View not accessible. Please use fallback options. Original error: " . $error);
        }
        
        throw new Exception($errorMessage . " | SQL Preview: " . substr($sql, 0, 300));
    }
    
    if ($result && mysqli_num_rows($result) >= 0) {
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Calculate stock status based on packages and units
            $storagePackages = (float)($row['storage_packages_balance'] ?? $row['available_packages'] ?? $row['storage_packages_available'] ?? 0);
            $storageUnits = (float)($row['storage_units_balance'] ?? $row['available_units_storage'] ?? $row['storage_units_balance_raw'] ?? 0);
            $pharmacyUnits = (float)($row['pharmacy_units_balance'] ?? $row['available_units_pharmacy'] ?? $row['pharmacy_units_available'] ?? 0);
            $unitsPerPackageEstimate = (float)($row['units_per_package_estimate'] ?? 0);
            if ($storageUnits <= 0 && $storagePackages > 0 && $unitsPerPackageEstimate > 0) {
                $storageUnits = $storagePackages * $unitsPerPackageEstimate;
            }
            $expiryDate = $row['earliest_expiry_date'] ?? $row['expiry_date'] ?? null;
            $productThreshold = isset($row['product_threshold']) ? (float)$row['product_threshold'] : 0;
            $totalUnitsAvailable = (float)($row['total_units_available'] ?? $row['total_stock'] ?? 0);
            if ($totalUnitsAvailable <= 0) {
                $totalUnitsAvailable = $pharmacyUnits + $storageUnits;
            }
 
            // Get prices
            $sellingPriceUnit = (float)($row['selling_price_unit'] ?? 0);
            $sellingPricePackage = (float)($row['selling_price_package'] ?? 0);
            
            // Determine default price based on available stock
            // If packages available, prefer package price; if units available, prefer unit price
            $defaultPrice = 0;
            if (($storagePackages > 0 || $storageUnits > 0) && $sellingPricePackage > 0) {
                $defaultPrice = $sellingPricePackage;
            } elseif ($pharmacyUnits > 0 && $sellingPriceUnit > 0) {
                $defaultPrice = $sellingPriceUnit;
            } elseif ($sellingPricePackage > 0) {
                $defaultPrice = $sellingPricePackage;
            } elseif ($sellingPriceUnit > 0) {
                $defaultPrice = $sellingPriceUnit;
            }
            
            // Determine stock status
            $stockStatus = 'Available';
            if ($expiryDate && $expiryDate !== '0000-00-00') {
                $expiryTimestamp = strtotime($expiryDate);
                $todayTimestamp = strtotime('today');
                if ($expiryTimestamp < $todayTimestamp) {
                    $stockStatus = 'Expired';
                }
            }
            
            // If not expired, check stock availability
            if ($stockStatus !== 'Expired') {
                if ($storagePackages <= 0 && $pharmacyUnits <= 0 && $storageUnits <= 0) {
                    $stockStatus = 'Out of Stock';
                } else {
                    $stockStatus = 'Available';
                }
            }

            // Flag low stock when threshold is defined and total units fall at or below threshold
            if ($stockStatus === 'Available' && $productThreshold > 0) {
                if ($totalUnitsAvailable > 0 && $totalUnitsAvailable <= $productThreshold) {
                    $stockStatus = 'Low Stock';
                }
            }
 
            // Handle different view structures
            if ($has_products_for_sale_view) {
                // Using view_products_for_sale (most complete view, aggregated by product_id)
                $products[] = [
                    'product_id' => (int)$row['product_id'],
                    'delivery_id' => isset($row['delivery_id']) ? (int)$row['delivery_id'] : null, // Reference delivery
                    'brand_name' => $row['brand_name'] ?? '',
                    'generic_name' => $row['generic_name'] ?? '',
                    'strength' => $row['strength'] ?? null,
                    'barcode' => $row['barcode'] ?? null,
                    'category_name' => $row['category_name'] ?? null,
                    'batch_no' => $row['batch_no'] ?? null,
                    'category_id' => null, // Not in view
                    'unit_id' => null, // Not in view
                    'unit_name' => null, // Not in view
                    'manufacturer_name' => null, // Not in view
                    'batch_id' => null, // Backend will determine using FEFO/FIFO
                    'expiry_date' => $row['earliest_expiry_date'] ?? null,
                    'manufacturing_date' => $row['earliest_manufacturing_date'] ?? null,
                    'delivery_date' => $row['earliest_delivery_date'] ?? null,
                    'received_date' => $row['earliest_delivery_date'] ?? null, // Alias
                    'fifo_fefo_priority' => $row['fifo_fefo_priority'] ?? null,
                    'price' => $defaultPrice,
                    'total_stock' => (float)($row['total_units_available'] ?? 0),
                    'pharmacy_units_balance' => (float)($row['pharmacy_units_balance'] ?? 0),
                    'storage_units_balance' => $storageUnits,
                    'storage_packages_balance' => (float)($row['storage_packages_balance'] ?? 0),
                    'selling_price_unit' => (float)($row['selling_price_unit'] ?? 0),
                    'selling_price_package' => (float)($row['selling_price_package'] ?? 0),
                    'cost_per_unit' => (float)($row['cost_per_unit'] ?? 0),
                    'cost_per_package' => (float)($row['cost_per_package'] ?? 0),
                    'batch_count' => (int)($row['batch_count'] ?? 0),
                    'stock_status' => $stockStatus,
                    'product_threshold' => $productThreshold,
                    'expire_threshold' => isset($row['expire_threshold']) ? (int)$row['expire_threshold'] : null,
                    'is_low_stock' => ($stockStatus === 'Low Stock'),
                    'availability_status' => $stockStatus,
                    'barcode_value' => $row['barcode'] ?? null,
                    // Map to expected field names for frontend
                    'available_packages' => (float)($row['storage_packages_balance'] ?? 0),
                    'available_units_pharmacy' => (float)($row['pharmacy_units_balance'] ?? 0),
                    'available_units_storage' => $storageUnits,
                    'total_units_available' => (float)($row['total_units_available'] ?? 0)
                ];
            } elseif ($has_fifofefo_view) {
                // Using view_fifofefo_available_products (FIFO/FEFO optimized, aggregated by product_id)
                $products[] = [
                    'product_id' => (int)$row['product_id'],
                    'brand_name' => $row['brand_name'],
                    'generic_name' => $row['generic_name'],
                    'strength' => $row['strength'] ?? null,
                    'category_id' => isset($row['category_id']) ? (int)$row['category_id'] : null,
                    'category_name' => $row['category_name'] ?? null,
                    'unit_id' => isset($row['unit_id']) ? (int)$row['unit_id'] : null,
                    'unit_name' => $row['unit_name'] ?? null,
                    'manufacturer_name' => null, // Not in view
                    'batch_id' => null, // Aggregated - multiple batches per product
                    'batch_no' => null, // Aggregated
                    'expiry_date' => $row['earliest_expiry_date'] ?? null,
                    'delivery_date' => $row['earliest_delivery_date'] ?? null,
                    'price' => $defaultPrice,
                    'total_stock' => (float)($row['total_units_available'] ?? 0),
                    'pharmacy_units_balance' => (float)($row['pharmacy_units_balance'] ?? 0),
                    'storage_units_balance' => $storageUnits,
                    'storage_packages_balance' => (float)($row['storage_packages_balance'] ?? 0),
                    'selling_price_unit' => (float)($row['selling_price_unit'] ?? 0),
                    'selling_price_package' => (float)($row['selling_price_package'] ?? 0),
                    'cost_per_unit' => (float)($row['cost_per_unit'] ?? 0),
                    'cost_per_package' => (float)($row['cost_per_package'] ?? 0),
                    'batch_count' => (int)($row['batch_count'] ?? 0),
                    'stock_status' => $stockStatus,
                    'product_threshold' => $productThreshold,
                    'expire_threshold' => isset($row['expire_threshold']) ? (int)$row['expire_threshold'] : null,
                    'is_low_stock' => ($stockStatus === 'Low Stock'),
                    'barcode_value' => $row['barcode_value'] ?? null
                ];
            } elseif ($has_ready_for_sale_view) {
                // Using view_ready_for_sale (aggregated by product_id)
                $products[] = [
                    'product_id' => (int)$row['product_id'],
                    'brand_name' => $row['brand_name'],
                    'generic_name' => $row['generic_name'],
                    'strength' => $row['strength'] ?? null,
                    'category_id' => isset($row['category_id']) ? (int)$row['category_id'] : null,
                    'category_name' => $row['category_name'] ?? null,
                    'unit_id' => isset($row['unit_id']) ? (int)$row['unit_id'] : null,
                    'unit_name' => $row['unit_name'] ?? null,
                    'manufacturer_name' => null, // Not in view_ready_for_sale
                    'batch_id' => null, // Aggregated - multiple batches per product
                    'batch_no' => null, // Aggregated
                    'expiry_date' => $row['earliest_expiry_date'] ?? null,
                    'price' => $defaultPrice,
                    'total_stock' => (float)($row['total_units_available'] ?? 0),
                    'pharmacy_units_available' => (float)($row['pharmacy_units_available'] ?? 0),
                    'storage_packages_available' => (float)($row['storage_packages_available'] ?? 0),
                    'storage_units_balance' => $storageUnits,
                    'selling_price_unit' => (float)($row['selling_price_unit'] ?? 0),
                    'selling_price_package' => (float)($row['selling_price_package'] ?? 0),
                    'batch_count' => (int)($row['batch_count'] ?? 0),
                    'stock_status' => $stockStatus,
                    'product_threshold' => $productThreshold,
                    'expire_threshold' => isset($row['expire_threshold']) ? (int)$row['expire_threshold'] : null,
                    'is_low_stock' => ($stockStatus === 'Low Stock'),
                    'barcode_value' => $row['barcode_value'] ?? null
                ];
            } elseif ($has_available_products_view) {
                // Using view_available_products structure (aggregated by product_id)
                $products[] = [
                    'product_id' => (int)$row['product_id'],
                    'batch_id' => null, // Backend will determine using FEFO/FIFO
                    'delivery_id' => isset($row['delivery_id']) ? (int)$row['delivery_id'] : null, // Reference delivery
                    'brand_name' => $row['brand_name'],
                    'generic_name' => $row['generic_name'],
                    'strength' => $row['strength'] ?? null,
                    'barcode' => $row['barcode'] ?? null,
                    'category_id' => isset($row['category_id']) ? (int)$row['category_id'] : null,
                    'category_name' => $row['category_name'] ?? null,
                    'unit_id' => isset($row['unit_id']) ? (int)$row['unit_id'] : null,
                    'unit_name' => $row['unit_name'] ?? null,
                    'unit_type' => $row['unit_type'] ?? null,
                    'package_type' => $row['package_type'] ?? null,
                    'batch_no' => $row['batch_no'] ?? null, // From first batch
                    'expiry_date' => $row['earliest_expiry_date'] ?? null,
                    'received_date' => $row['earliest_received_date'] ?? null,
                    'manufacturing_date' => $row['earliest_manufacturing_date'] ?? null,
                    'available_packages' => (float)($row['available_packages'] ?? 0), // Sum of all batches
                    'available_units_storage' => (float)($row['available_units_storage'] ?? 0), // Sum of all batches
                    'available_units_pharmacy' => (float)($row['available_units_pharmacy'] ?? 0), // Sum of all batches
                    'available_units_storage' => (float)($row['available_units_storage'] ?? $storageUnits),
                    'price' => $defaultPrice, // Default price based on available stock
                    'total_stock' => (float)($row['total_units_available'] ?? 0), // Total across all batches
                    'selling_price_unit' => (float)($row['selling_price_unit'] ?? 0),
                    'selling_price_package' => (float)($row['selling_price_package'] ?? 0),
                    'batch_count' => (int)($row['batch_count'] ?? 0), // Number of batches
                    'stock_status' => $stockStatus,
                    'stock_condition' => $row['stock_condition'] ?? null,
                    'product_threshold' => $productThreshold,
                    'expire_threshold' => isset($row['expire_threshold']) ? (int)$row['expire_threshold'] : null,
                    'is_low_stock' => ($stockStatus === 'Low Stock' || ($row['stock_condition'] ?? '') === 'Low Stock'),
                    'barcode_value' => $row['barcode'] ?? null,
                    'storage_units_balance' => $storageUnits
                ];
            } else {
                // Final fallback structure using computed stock values
                $products[] = [
                    'product_id' => (int)$row['product_id'],
                    'brand_name' => $row['brand_name'],
                    'generic_name' => $row['generic_name'],
                    'strength' => $row['strength'],
                    'category_id' => isset($row['category_id']) ? (int)$row['category_id'] : null,
                    'category_name' => $row['category_name'] ?? null,
                    'unit_id' => isset($row['unit_id']) ? (int)$row['unit_id'] : null,
                    'unit_name' => $row['unit_name'] ?? null,
                    'manufacturer_name' => $row['manufacturer_name'] ?? null,
                    'batch_id' => null, // Backend will determine using FEFO/FIFO
                    'delivery_id' => isset($row['delivery_id']) ? (int)$row['delivery_id'] : null,
                    'batch_no' => null,
                    'expiry_date' => $expiryDate,
                    'price' => $defaultPrice,
                    'total_stock' => $totalUnitsAvailable,
                    'total_units_available' => $totalUnitsAvailable,
                    'selling_price_unit' => $sellingPriceUnit,
                    'selling_price_package' => $sellingPricePackage,
                    'storage_packages_balance' => $storagePackages,
                    'storage_units_balance' => $storageUnits,
                    'pharmacy_units_balance' => $pharmacyUnits,
                    'available_packages' => $storagePackages,
                    'available_units_pharmacy' => $pharmacyUnits,
                    'available_units_storage' => $storageUnits,
                    'units_per_package_estimate' => $unitsPerPackageEstimate,
                    'stock_status' => $stockStatus,
                    'product_threshold' => $productThreshold,
                    'expire_threshold' => isset($row['expire_threshold']) ? (int)$row['expire_threshold'] : null,
                    'is_low_stock' => ($stockStatus === 'Low Stock'),
                    'barcode_value' => $row['barcode'] ?? $row['barcode_value'] ?? null
                ];
            }
        }
        mysqli_free_result($result);
        // Recalculate batch metadata (total vs active vs expired) and adjust expiry/delivery dates
        $productIds = array_values(array_filter(array_unique(array_map(static function($prod) {
            return isset($prod['product_id']) ? (int)$prod['product_id'] : null;
        }, $products)), fn($id) => $id !== null && $id > 0));

        $batchStats = [];
        $expireThresholdMap = [];
        if (!empty($productIds)) {
            $idList = implode(',', $productIds);
            $stockConditionSql = "
                    (COALESCE(si.storage_packages_balance, 0) > 0
                     OR COALESCE(si.storage_units_balance, 0) > 0
                     OR COALESCE(pi.pharmacy_units_balance, 0) > 0)";
            $batchSql = "
                SELECT 
                    d.product_id,
                    COUNT(DISTINCT d.delivery_id) AS total_batches,
                    SUM(
                        CASE 
                            WHEN d.status = 'Active' 
                                 AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                                 AND $stockConditionSql
                            THEN 1 ELSE 0 
                        END
                    ) AS active_batches,
                    MIN(
                        CASE 
                            WHEN d.status = 'Active' 
                                 AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                                 AND $stockConditionSql
                            THEN d.expiry_date 
                            ELSE NULL 
                        END
                    ) AS earliest_active_expiry,
                    MIN(
                        CASE 
                            WHEN d.status = 'Active' 
                                 AND (d.expiry_date IS NULL OR d.expiry_date >= CURDATE())
                                 AND $stockConditionSql
                            THEN d.delivery_date 
                            ELSE NULL 
                        END
                    ) AS earliest_active_delivery
                FROM deliveries d
                LEFT JOIN (
                    SELECT 
                        delivery_id,
                        SUM(quantity_packages_balance) AS storage_packages_balance,
                        SUM(quantity_units_balance) AS storage_units_balance
                    FROM storage_inventory
                    GROUP BY delivery_id
                ) si ON si.delivery_id = d.delivery_id
                LEFT JOIN (
                    SELECT 
                        delivery_id,
                        SUM(quantity_units_balance) AS pharmacy_units_balance
                    FROM pharmacy_inventory
                    GROUP BY delivery_id
                ) pi ON pi.delivery_id = d.delivery_id
                WHERE d.product_id IN ($idList)
                GROUP BY d.product_id";
            $batchResult = mysqli_query($conn, $batchSql);
            if ($batchResult) {
                while ($row = mysqli_fetch_assoc($batchResult)) {
                    $pid = (int)$row['product_id'];
                    $total = (int)($row['total_batches'] ?? 0);
                    $active = (int)($row['active_batches'] ?? 0);
                    $batchStats[$pid] = [
                        'total_batches' => $total,
                        'active_batches' => $active,
                        'expired_batches' => max(0, $total - $active),
                        'earliest_active_expiry' => $row['earliest_active_expiry'] ?? null,
                        'earliest_active_delivery' => $row['earliest_active_delivery'] ?? null,
                    ];
                }
                mysqli_free_result($batchResult);
            }

            $expireSql = "SELECT product_id, expire_threshold FROM products WHERE product_id IN ($idList)";
            $expireResult = mysqli_query($conn, $expireSql);
            if ($expireResult) {
                while ($row = mysqli_fetch_assoc($expireResult)) {
                    $pid = (int)($row['product_id'] ?? 0);
                    if ($pid > 0) {
                        $expireThresholdMap[$pid] = isset($row['expire_threshold']) ? (int)$row['expire_threshold'] : null;
                    }
                }
                mysqli_free_result($expireResult);
            }
        }

        foreach ($products as &$prod) {
            $pid = (int)($prod['product_id'] ?? 0);
            if ($pid > 0 && isset($batchStats[$pid])) {
                $stats = $batchStats[$pid];
                $prod['total_batches'] = $stats['total_batches'];
                $prod['active_batches'] = $stats['active_batches'];
                $prod['expired_batches'] = $stats['expired_batches'];
                $prod['batch_count'] = $stats['active_batches'];

                if (!empty($stats['earliest_active_expiry'])) {
                    $prod['expiry_date'] = $stats['earliest_active_expiry'];
                }
                if (!empty($stats['earliest_active_delivery'])) {
                    $prod['delivery_date'] = $stats['earliest_active_delivery'];
                    if (empty($prod['received_date'])) {
                        $prod['received_date'] = $stats['earliest_active_delivery'];
                    }
                }

                if ($stats['active_batches'] > 0 && ($prod['stock_status'] ?? '') === 'Expired') {
                    $prod['stock_status'] = 'Available';
                }
                if ($stats['active_batches'] <= 0 && ($prod['stock_status'] ?? '') === 'Available') {
                    $prod['stock_status'] = 'Expired';
                }
            } else {
                $total = isset($prod['total_batches']) ? (int)$prod['total_batches'] : (int)($prod['batch_count'] ?? 0);
                $active = (int)($prod['batch_count'] ?? $total);
                $prod['total_batches'] = $total;
                $prod['active_batches'] = $active;
                $prod['expired_batches'] = max(0, $total - $active);
            }

            if ($pid > 0 && isset($expireThresholdMap[$pid])) {
                $prod['expire_threshold'] = $expireThresholdMap[$pid];
            } elseif (!isset($prod['expire_threshold'])) {
                $prod['expire_threshold'] = null;
            }
        }
        unset($prod);

        $defaultExpireThreshold = 30;
        $todayTimestamp = strtotime('today');
        foreach ($products as &$prod) {
            $expireThreshold = isset($prod['expire_threshold']) ? (int)$prod['expire_threshold'] : 0;
            if ($expireThreshold <= 0) {
                $expireThreshold = $defaultExpireThreshold;
            }
            $prod['expire_threshold'] = $expireThreshold;
            $prod['is_expiring_soon'] = false;
            $prod['days_until_expiry'] = null;

            $expiryDate = $prod['expiry_date'] ?? null;
            if (!empty($expiryDate) && $expiryDate !== '0000-00-00' && $expiryDate !== '1970-01-01') {
                $expiryTs = strtotime($expiryDate);
                if ($expiryTs !== false && $todayTimestamp !== false) {
                    $daysRemaining = (int)floor(($expiryTs - $todayTimestamp) / 86400);
                    $prod['days_until_expiry'] = $daysRemaining;
                    $isExpired = $daysRemaining < 0;
                    $hasActiveStock = (($prod['active_batches'] ?? 0) > 0) && (($prod['total_stock'] ?? $prod['total_units_available'] ?? 0) > 0);
                    if (!$isExpired && $hasActiveStock && $daysRemaining <= $expireThreshold) {
                        $prod['is_expiring_soon'] = true;
                    }
                }
            }

            if ($prod['is_expiring_soon']) {
                if (empty($prod['stock_condition']) || strtolower($prod['stock_condition']) !== 'low stock') {
                    $prod['stock_condition'] = 'Expiring Soon';
                }
                $currentStatus = strtolower($prod['stock_status'] ?? '');
                if (!in_array($currentStatus, ['expired', 'out of stock', 'low stock'])) {
                    $prod['stock_status'] = 'Expiring Soon';
                }
            }
        }
        unset($prod);

        $response['products'] = $products;
        $response['debug_info'] = [
            'products_count' => count($products),
            'view_detected' => [
                'products_for_sale' => $has_products_for_sale_view ?? false,
                'fifofefo' => $has_fifofefo_view ?? false,
                'ready_for_sale' => $has_ready_for_sale_view ?? false,
                'available_products' => $has_available_products_view ?? false
            ],
            'batch_override' => [
                'counted_products' => count($batchStats)
            ]
        ];
    } else {
        $response['products'] = [];
        $error_msg = mysqli_error($conn);
        $response['error'] = $error_msg ?: 'No results returned';
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    // Make sure error is properly encoded
    $errorMessage = $e->getMessage();
    // Log to error log for debugging
    error_log("getPOSData.php Error: " . $errorMessage);
    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'debug' => (isset($sql) ? 'SQL exists' : 'No SQL generated')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}?>

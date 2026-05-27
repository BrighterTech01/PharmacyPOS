<?php
include_once '../dbConnection/connection.php';

// GET → return to Unit Types page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location:../Admin/category_management/unitType.php');
    exit();
}

function redirect_with_params($params) {
    $base = '../Admin/category_management/unitType.php';
    $q = http_build_query($params);
    header('location:' . $base . ($q ? ('?' . $q) : ''));
    exit();
}

// Create unit type
if (isset($_POST['create_unit_type'])) {
    $unit_name = isset($_POST['unit_name']) ? trim($_POST['unit_name']) : '';
    if ($unit_name === '') {
        redirect_with_params(['error' => 'missing_fields']);
    }

    $nameEsc = mysqli_real_escape_string($conn, $unit_name);

    // Unique name (case-insensitive)
    $dupSql = "SELECT unit_type_id FROM unit_types WHERE LOWER(unit_name) = LOWER('$nameEsc') LIMIT 1";
    $dupRes = mysqli_query($conn, $dupSql);
    if ($dupRes && mysqli_num_rows($dupRes) > 0) {
        redirect_with_params(['error' => 'duplicate']);
    }

    // New unit types start as 'Inactive' (no products yet)
    $insSql = "INSERT INTO unit_types (unit_name, status) VALUES ('$nameEsc', 'Inactive')";
    if (mysqli_query($conn, $insSql)) {
        redirect_with_params(['success' => 'created']);
    }
    redirect_with_params(['error' => 'create_failed']);
}

// Update unit type
if (isset($_POST['update_unit_type'])) {
    $unit_type_id = isset($_POST['unit_type_id']) ? (int)$_POST['unit_type_id'] : 0;
    $unit_name = isset($_POST['unit_name']) ? trim($_POST['unit_name']) : '';
    if ($unit_type_id <= 0 || $unit_name === '') {
        redirect_with_params(['error' => 'missing_fields']);
    }

    // Exists
    $id = (int)$unit_type_id;
    $existSql = "SELECT unit_type_id FROM unit_types WHERE unit_type_id = $id";
    $existRes = mysqli_query($conn, $existSql);
    if (!$existRes || mysqli_num_rows($existRes) === 0) {
        redirect_with_params(['error' => 'invalid_id']);
    }

    $nameEsc = mysqli_real_escape_string($conn, $unit_name);
    $dupSql = "SELECT unit_type_id FROM unit_types WHERE LOWER(unit_name) = LOWER('$nameEsc') AND unit_type_id <> $id LIMIT 1";
    $dupRes = mysqli_query($conn, $dupSql);
    if ($dupRes && mysqli_num_rows($dupRes) > 0) {
        redirect_with_params(['error' => 'duplicate']);
    }

    // Auto-update status based on product count
    $prodCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE base_unit_type_id = $id";
    $prodCountRes = mysqli_query($conn, $prodCountSql);
    $productCount = 0;
    if ($prodCountRes) {
        $productCount = (int)mysqli_fetch_assoc($prodCountRes)['cnt'];
    }
    $newStatus = ($productCount > 0) ? 'Active' : 'Inactive';

    $updSql = "UPDATE unit_types SET unit_name = '$nameEsc', status = '$newStatus' WHERE unit_type_id = $id";
    if (mysqli_query($conn, $updSql)) {
        redirect_with_params(['success' => 'updated']);
    }
    redirect_with_params(['error' => 'update_failed']);
}

// Delete unit type
if (isset($_POST['delete_unit_type'])) {
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
        redirect_with_params(['error' => 'forbidden']);
    }

    $unit_type_id = isset($_POST['unit_type_id']) ? (int)$_POST['unit_type_id'] : 0;
    if ($unit_type_id <= 0) {
        redirect_with_params(['error' => 'invalid_id']);
    }

    $id = (int)$unit_type_id;

    // Prevent deletion if products reference this unit type
    $useSql = "SELECT COUNT(*) AS cnt FROM products WHERE base_unit_type_id = $id";
    $useRes = mysqli_query($conn, $useSql);
    $cnt = ($useRes) ? (int)mysqli_fetch_assoc($useRes)['cnt'] : 0;
    if ($cnt > 0) {
        redirect_with_params(['error' => 'in_use', 'count' => $cnt]);
    }

    $delSql = "DELETE FROM unit_types WHERE unit_type_id = $id";
    if (mysqli_query($conn, $delSql)) {
        redirect_with_params(['success' => 'deleted']);
    }
    redirect_with_params(['error' => 'delete_failed']);
}

// Fallback
redirect_with_params([]);
?>


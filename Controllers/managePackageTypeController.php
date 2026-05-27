<?php
include_once '../dbConnection/connection.php';

// Redirect direct GET access back to the package types page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location:../Admin/category_management/packageType.php');
    exit();
}

function redirect_with($params) {
    $base = '../Admin/category_management/packageType.php';
    $query = http_build_query($params);
    header('location:' . $base . ($query ? ('?' . $query) : ''));
    exit();
}

// Create
if (isset($_POST['create_package_type'])) {
    $package_name = isset($_POST['package_name']) ? trim($_POST['package_name']) : '';

    if ($package_name === '') {
        redirect_with(['error' => 'missing_fields']);
    }

    $nameEsc = mysqli_real_escape_string($conn, $package_name);

    // Enforce uniqueness by name (case-insensitive)
    $checkSql = "SELECT package_type_id FROM package_types WHERE LOWER(package_name) = LOWER('$nameEsc') LIMIT 1";
    $checkRes = mysqli_query($conn, $checkSql);
    if ($checkRes && mysqli_num_rows($checkRes) > 0) {
        redirect_with(['error' => 'duplicate']);
    }

    // New package types start as 'Inactive' (no products yet)
    $insertSql = "INSERT INTO package_types (package_name, status) VALUES ('$nameEsc', 'Inactive')";
    if (mysqli_query($conn, $insertSql)) {
        redirect_with(['success' => 'created']);
    } else {
        redirect_with(['error' => 'create_failed']);
    }
}

// Update
if (isset($_POST['update_package_type'])) {
    $package_type_id = isset($_POST['package_type_id']) ? (int)$_POST['package_type_id'] : 0;
    $package_name = isset($_POST['package_name']) ? trim($_POST['package_name']) : '';

    if ($package_type_id <= 0 || $package_name === '') {
        redirect_with(['error' => 'missing_fields']);
    }

    // Ensure exists
    $idEsc = (int)$package_type_id;
    $existsSql = "SELECT package_type_id FROM package_types WHERE package_type_id = $idEsc";
    $existsRes = mysqli_query($conn, $existsSql);
    if (!$existsRes || mysqli_num_rows($existsRes) === 0) {
        redirect_with(['error' => 'invalid_id']);
    }

    $nameEsc = mysqli_real_escape_string($conn, $package_name);
    // Unique name excluding self
    $dupSql = "SELECT package_type_id FROM package_types WHERE LOWER(package_name) = LOWER('$nameEsc') AND package_type_id <> $idEsc LIMIT 1";
    $dupRes = mysqli_query($conn, $dupSql);
    if ($dupRes && mysqli_num_rows($dupRes) > 0) {
        redirect_with(['error' => 'duplicate']);
    }

    // Auto-update status based on product count
    $prodCountSql = "SELECT COUNT(*) AS cnt FROM products WHERE package_type_id = $idEsc";
    $prodCountRes = mysqli_query($conn, $prodCountSql);
    $productCount = 0;
    if ($prodCountRes) {
        $productCount = (int)mysqli_fetch_assoc($prodCountRes)['cnt'];
    }
    $newStatus = ($productCount > 0) ? 'Active' : 'Inactive';

    $updateSql = "UPDATE package_types SET package_name = '$nameEsc', status = '$newStatus' WHERE package_type_id = $idEsc";
    if (mysqli_query($conn, $updateSql)) {
        redirect_with(['success' => 'updated']);
    } else {
        redirect_with(['error' => 'update_failed']);
    }
}

// Delete
if (isset($_POST['delete_package_type'])) {
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
        redirect_with(['error' => 'forbidden']);
    }

    $package_type_id = isset($_POST['package_type_id']) ? (int)$_POST['package_type_id'] : 0;
    if ($package_type_id <= 0) {
        redirect_with(['error' => 'invalid_id']);
    }

    $idEsc = (int)$package_type_id;

    // Optional: prevent deletion if used by products
    $useSql = "SELECT COUNT(*) AS cnt FROM products WHERE package_type_id = $idEsc";
    $useRes = mysqli_query($conn, $useSql);
    $cnt = ($useRes) ? (int)mysqli_fetch_assoc($useRes)['cnt'] : 0;
    if ($cnt > 0) {
        redirect_with(['error' => 'in_use', 'count' => $cnt]);
    }

    $delSql = "DELETE FROM package_types WHERE package_type_id = $idEsc";
    if (mysqli_query($conn, $delSql)) {
        redirect_with(['success' => 'deleted']);
    } else {
        redirect_with(['error' => 'delete_failed']);
    }
}

// Fallback
redirect_with([]);
?>


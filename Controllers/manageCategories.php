<?php
include_once '../dbConnection/connection.php';

// Redirect direct GET access back to page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location:../Admin/product_management/categories.php');
    exit();
}

// Create category
if (isset($_POST['create_category'])) {
    $category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
    if ($category_name === '') {
        header('location:../Admin/category_management/categories.php?error=missing_fields');
        exit();
    }

    $nameEsc = mysqli_real_escape_string($conn, $category_name);
    // New categories start as 'Inactive' (no products yet)
    $query = "INSERT INTO Categories (category_name, status) VALUES ('$nameEsc', 'Inactive')";
    $result = mysqli_query($conn, $query);

    if ($result) {
        header('location:../Admin/category_management/categories.php?success=created');
        exit();
    } else {
        $errorMsg = mysqli_error($conn);
        header('location:../Admin/category_management/categories.php?error=create_failed&msg=' . urlencode($errorMsg ?: 'Failed to create category'));
        exit();
    }
}

// Update category
if (isset($_POST['update_category'])) {
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
    
    if ($category_id <= 0 || $category_name === '') {
        header('location:../Admin/category_management/categories.php?error=missing_fields');
        exit();
    }

    $nameEsc = mysqli_real_escape_string($conn, $category_name);
    
    // Auto-update status based on product count
    $prodCountQuery = "SELECT COUNT(*) AS cnt FROM products WHERE category_id = $category_id";
    $prodCountRes = mysqli_query($conn, $prodCountQuery);
    $productCount = 0;
    if ($prodCountRes) {
        $productCount = (int)mysqli_fetch_assoc($prodCountRes)['cnt'];
    }
    $newStatus = ($productCount > 0) ? 'Active' : 'Inactive';
    
    $query = "UPDATE Categories SET category_name = '$nameEsc', status = '$newStatus' WHERE category_id = $category_id";
    $result = mysqli_query($conn, $query);

    if ($result) {
        header('location:../Admin/category_management/categories.php?success=updated');
        exit();
    } else {
        $errorMsg = mysqli_error($conn);
        header('location:../Admin/category_management/categories.php?error=update_failed&msg=' . urlencode($errorMsg ?: 'Failed to update category'));
        exit();
    }
}

// Delete category (prevent if in use by products)
if (isset($_POST['delete_category'])) {
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
        header('location:../Admin/category_management/categories.php?error=forbidden');
        exit();
    }
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    if ($category_id <= 0) {
        header('location:../Admin/category_management/categories.php?error=invalid_id');
        exit();
    }

    $usageQuery = "SELECT COUNT(*) AS cnt FROM products WHERE category_id = $category_id";
    $usageRes = mysqli_query($conn, $usageQuery);
    $cnt = 0;
    if ($usageRes) {
        $cnt = (int)mysqli_fetch_assoc($usageRes)['cnt'];
    }

    if ($cnt > 0) {
        header('location:../Admin/category_management/categories.php?error=category_in_use&count=' . $cnt);
        exit();
    }

    $query = "DELETE FROM Categories WHERE category_id = $category_id";
    $result = mysqli_query($conn, $query);

    if ($result) {
        header('location:../Admin/category_management/categories.php?success=deleted');
        exit();
    } else {
        $errorMsg = mysqli_error($conn);
        header('location:../Admin/category_management/categories.php?error=delete_failed&msg=' . urlencode($errorMsg ?: 'Failed to delete category'));
        exit();
    }
}

// Fallback
header('location:../Admin/category_management/categories.php');
exit();
?>

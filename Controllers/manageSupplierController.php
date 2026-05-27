<?php
    include_once '../dbConnection/connection.php';

    // Redirect any direct GET access to avoid rendering a document (prevents Quirks Mode)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('location:../Admin/product_management/suppliers.php');
        exit();
    }

    // Create supplier
    if (isset($_POST['create_supplier'])) {
        $supplier_name = trim($_POST['supplier_name']);
        $contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';

        if ($supplier_name === '') {
            header('location:../Admin/product_management/suppliers.php?error=missing_name');
            exit();
        }

        $nameEsc = mysqli_real_escape_string($conn, $supplier_name);
        $contactEsc = mysqli_real_escape_string($conn, $contact_person);
        $phoneEsc = mysqli_real_escape_string($conn, $phone);
        $emailEsc = mysqli_real_escape_string($conn, $email);
        $addressEsc = mysqli_real_escape_string($conn, $address);

        // Duplicate check (case/space insensitive)
        $dupRes = mysqli_query($conn, "SELECT supplier_id FROM suppliers WHERE LOWER(TRIM(supplier_name)) = LOWER(TRIM('$nameEsc')) LIMIT 1");
        if ($dupRes && mysqli_num_rows($dupRes) > 0) {
            header('location:../Admin/supplier_management/suppliers.php?error=duplicate_name');
            exit();
        }

        $query = "INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) 
                  VALUES ('$nameEsc', '$contactEsc', '$phoneEsc', '$emailEsc', '$addressEsc')";
        $result = mysqli_query($conn, $query);

        if ($result) {
            header('location:../Admin/product_management/suppliers.php?success=created');
            exit();
        } else {
            $errorMsg = mysqli_error($conn);
            header('location:../Admin/product_management/suppliers.php?error=create_failed&msg=' . urlencode($errorMsg ?: 'Failed to create supplier'));
            exit();
        }
    }

    // Update supplier
    if (isset($_POST['update_supplier'])) {
        $supplier_id = (int)$_POST['supplier_id'];
        $supplier_name = trim($_POST['supplier_name']);
        $contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';

        $query = "UPDATE suppliers SET 
                    supplier_name = '$supplier_name',
                    contact_person = '$contact_person',
                    phone = '$phone',
                    email = '$email',
                    address = '$address'
                  WHERE supplier_id = '$supplier_id'";
        $result = mysqli_query($conn, $query);

        if ($result) {
            header('location:../Admin/product_management/suppliers.php?success=updated');
            exit();
        } else {
            $errorMsg = mysqli_error($conn);
            header('location:../Admin/product_management/suppliers.php?error=update_failed&msg=' . urlencode($errorMsg ?: 'Failed to update supplier'));
            exit();
        }
    }

    // Delete supplier
    if (isset($_POST['delete_supplier'])) {
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
            header('location:../Admin/supplier_management/suppliers.php?error=forbidden');
            exit();
        }
        $supplier_id = (int)$_POST['supplier_id'];

        // Prevent deletion if referenced by deliveries
        $referencingCount = 0;
        $checkQuery = "SELECT COUNT(*) AS cnt 
                       FROM deliveries d 
                       INNER JOIN products p ON d.product_id = p.product_id 
                       WHERE p.supplier_id = ?";
        if ($stmt = mysqli_prepare($conn, $checkQuery)) {
            mysqli_stmt_bind_param($stmt, 'i', $supplier_id);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if ($result) {
                    $row = mysqli_fetch_assoc($result);
                    $referencingCount = isset($row['cnt']) ? (int)$row['cnt'] : 0;
                }
            }
            mysqli_stmt_close($stmt);
        }

        if ($referencingCount > 0) {
            header('location:../Admin/product_management/suppliers.php?error=supplier_in_use&count=' . $referencingCount);
            exit();
        }

        $query = "DELETE FROM suppliers WHERE supplier_id = '$supplier_id'";
        $result = mysqli_query($conn, $query);

        if ($result) {
            header('location:../Admin/product_management/suppliers.php?success=deleted');
            exit();
        } else {
            $errorMsg = mysqli_error($conn);
            header('location:../Admin/product_management/suppliers.php?error=delete_failed&msg=' . urlencode($errorMsg ?: 'Failed to delete supplier'));
            exit();
        }
    }

    // Fallback: on any other request, redirect back to the suppliers page
    header('location:../Admin/product_management/suppliers.php');
    exit();
?>

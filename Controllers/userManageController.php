<?php
session_start();
require_once '../dbConnection/connection.php';
require_once '../includes/master_admin.php';

// Ensure the master admin exists
ensureMasterAdminAccount($conn);

// Check if user is admin
$isAdmin = false;
$currentUsername = '';
if (!empty($_SESSION['user']['username'])) { $currentUsername = $_SESSION['user']['username']; }
elseif (!empty($_SESSION['username'])) { $currentUsername = $_SESSION['username']; }

// Pharmacy account always has admin access
$isPharmacyAccount = (strtolower($currentUsername) === 'pharmacy');

if (!empty($_SESSION['user']['role'])) { 
    $isAdmin = strtolower($_SESSION['user']['role']) === 'admin'; 
} elseif (!empty($_SESSION['role'])) { 
    $isAdmin = strtolower($_SESSION['role']) === 'admin'; 
}

// Pharmacy account always has full access
$isAdmin = $isPharmacyAccount || $isAdmin;

if (!$isAdmin) {
    header('Location: ../Admin/user_management.php?error=unauthorized');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ADD USER
    if (isset($_POST['add_user'])) {
        $firstname = mysqli_real_escape_string($conn, trim($_POST['firstname'] ?? ''));
        $lastname = mysqli_real_escape_string($conn, trim($_POST['lastname'] ?? ''));
        $contactInput = trim($_POST['contact'] ?? '');
        $contact = NULL;
        $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
        $role = mysqli_real_escape_string($conn, trim($_POST['role'] ?? ''));
        
        // Validation
        $errors = [];
        
        // Validate contact field
        if ($contactInput !== '') {
            // Validate contact is a valid integer
            // MySQL BIGINT (signed): -9,223,372,036,854,775,808 to 9,223,372,036,854,775,807
            // Phone numbers can be up to 15 digits (ITU-T E.164 standard)
            if (is_numeric($contactInput)) {
                // Remove any non-numeric characters (spaces, dashes, etc.)
                $contactInput = preg_replace('/[^0-9]/', '', $contactInput);
                
                if (strlen($contactInput) > 0 && strlen($contactInput) <= 15) {
                    $contact = intval($contactInput);
                } else {
                    $errors[] = 'Contact number must be between 1 and 15 digits.';
                }
            } else {
                $errors[] = 'Contact must be a valid number.';
            }
        }
        if (empty($firstname)) $errors[] = 'First name is required';
        if (empty($lastname)) $errors[] = 'Last name is required';
        if (empty($username)) $errors[] = 'Username is required';
        if (empty($password)) $errors[] = 'Password is required';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
        if (empty($role)) $errors[] = 'Role is required';
        if ($role != 'Admin' && $role != 'Cashier') $errors[] = 'Invalid role';
        
        if (empty($errors)) {
            // Check if username exists using prepared statement
            $checkStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ?");
            if (!$checkStmt) {
                header('Location: ../Admin/user_management.php?error=add_failed&msg=' . urlencode('Database error: ' . mysqli_error($conn)));
                exit();
            }
            mysqli_stmt_bind_param($checkStmt, "s", $username);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            
            if (mysqli_num_rows($checkResult) > 0) {
                mysqli_stmt_close($checkStmt);
                header('Location: ../Admin/user_management.php?error=username_exists&username=' . urlencode($username));
                exit();
            }
            mysqli_stmt_close($checkStmt);
            
            // Hash password (password_hash returns safe string)
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user using prepared statement - handle NULL contact properly
            if ($contact === NULL) {
                $stmt = mysqli_prepare($conn, "INSERT INTO users (firstname, lastname, contact, username, password, address, role, status) VALUES (?, ?, NULL, ?, ?, ?, ?, 'Inactive')");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssssss", $firstname, $lastname, $username, $hashedPassword, $address, $role);
                }
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO users (firstname, lastname, contact, username, password, address, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Inactive')");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssissss", $firstname, $lastname, $contact, $username, $hashedPassword, $address, $role);
                }
            }
            
            if ($stmt && mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header('Location: ../Admin/user_management.php?success=user_added');
                exit();
            } else {
                $errorMsg = mysqli_error($conn);
                if (isset($stmt)) mysqli_stmt_close($stmt);
                header('Location: ../Admin/user_management.php?error=add_failed&msg=' . urlencode($errorMsg ?: 'Failed to add user'));
                exit();
            }
        } else {
            header('Location: ../Admin/user_management.php?error=validation&msg=' . urlencode(implode(', ', $errors)));
            exit();
        }
    }
    
    // UPDATE USER
    if (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id > 0) {
            $firstname = mysqli_real_escape_string($conn, trim($_POST['firstname'] ?? ''));
            $lastname = mysqli_real_escape_string($conn, trim($_POST['lastname'] ?? ''));
            $contactInput = trim($_POST['contact'] ?? '');
            $contact = NULL;
            $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
            $password = $_POST['password'] ?? '';
            $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
            $role = mysqli_real_escape_string($conn, trim($_POST['role'] ?? ''));
            $status = mysqli_real_escape_string($conn, trim($_POST['status'] ?? ''));
            
            // Validation
            $errors = [];
            
            // Validate contact field
            if ($contactInput !== '') {
                // Validate contact is a valid integer
                // MySQL BIGINT (signed): -9,223,372,036,854,775,808 to 9,223,372,036,854,775,807
                // Phone numbers can be up to 15 digits (ITU-T E.164 standard)
                if (is_numeric($contactInput)) {
                    // Remove any non-numeric characters (spaces, dashes, etc.)
                    $contactInput = preg_replace('/[^0-9]/', '', $contactInput);
                    
                    if (strlen($contactInput) > 0 && strlen($contactInput) <= 15) {
                        // Check if value is within BIGINT range (supports up to 19 digits)
                        // For safety, we'll use a reasonable limit
                        if (strlen($contactInput) <= 15) {
                            $contact = intval($contactInput);
                        } else {
                            $errors[] = 'Contact number is too long. Maximum 15 digits allowed.';
                        }
                    } else {
                        $errors[] = 'Contact number must be between 1 and 15 digits.';
                    }
                } else {
                    $errors[] = 'Contact must be a valid number.';
                }
            }
            if (empty($firstname)) $errors[] = 'First name is required';
            if (empty($lastname)) $errors[] = 'Last name is required';
            if (empty($username)) $errors[] = 'Username is required';
            if (!empty($password) && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
            if (empty($role)) $errors[] = 'Role is required';
            if ($role != 'Admin' && $role != 'Cashier') $errors[] = 'Invalid role';
            if (empty($status)) $errors[] = 'Status is required';
            if ($status != 'Active' && $status != 'Inactive') $errors[] = 'Invalid status';
            
            if (empty($errors)) {
                // Check if username exists (excluding current user) using prepared statement
                $checkStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? AND user_id != ?");
                if (!$checkStmt) {
                    header('Location: ../Admin/user_management.php?error=update_failed&msg=' . urlencode('Database error: ' . mysqli_error($conn)));
                    exit();
                }
                mysqli_stmt_bind_param($checkStmt, "si", $username, $user_id);
                mysqli_stmt_execute($checkStmt);
                $checkResult = mysqli_stmt_get_result($checkStmt);

                if (mysqli_num_rows($checkResult) > 0) {
                    mysqli_stmt_close($checkStmt);
                    header('Location: ../Admin/user_management.php?error=username_exists&username=' . urlencode($username));
                    exit();
                }
                mysqli_stmt_close($checkStmt);
                
                // Build update query using prepared statement
                $updateFields = [];
                $bindTypes = "";
                $bindValues = [];
                
                $updateFields[] = "firstname = ?";
                $bindTypes .= "s";
                $bindValues[] = $firstname;
                
                $updateFields[] = "lastname = ?";
                $bindTypes .= "s";
                $bindValues[] = $lastname;
                
                $updateFields[] = "username = ?";
                $bindTypes .= "s";
                $bindValues[] = $username;
                
                $updateFields[] = "address = ?";
                $bindTypes .= "s";
                $bindValues[] = $address;
                
                $updateFields[] = "role = ?";
                $bindTypes .= "s";
                $bindValues[] = $role;
                
                $updateFields[] = "status = ?";
                $bindTypes .= "s";
                $bindValues[] = $status;
                
                // Handle contact - NULL if empty, otherwise use integer value
                if ($contact === NULL) {
                    $updateFields[] = "contact = NULL";
                } else {
                    $updateFields[] = "contact = ?";
                    $bindTypes .= "i";
                    $bindValues[] = $contact;
                }
                
                // Update password only if provided
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateFields[] = "password = ?";
                    $bindTypes .= "s";
                    $bindValues[] = $hashedPassword;
                }
                
                $query = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE user_id = ?";
                $bindTypes .= "i";
                $bindValues[] = $user_id;
                
                $stmt = mysqli_prepare($conn, $query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        header('Location: ../Admin/user_management.php?success=user_updated');
                        exit();
                    } else {
                        $errorMsg = mysqli_stmt_error($stmt);
                        mysqli_stmt_close($stmt);
                        header('Location: ../Admin/user_management.php?error=update_failed&msg=' . urlencode($errorMsg ?: 'Failed to update user'));
                        exit();
                    }
                } else {
                    header('Location: ../Admin/user_management.php?error=update_failed&msg=' . urlencode(mysqli_error($conn) ?: 'Failed to prepare update query'));
                    exit();
                }
            } else {
                header('Location: ../Admin/user_management.php?error=validation&msg=' . urlencode(implode(', ', $errors)));
                exit();
            }
        } else {
            header('Location: ../Admin/user_management.php?error=invalid_id');
            exit();
        }
    }
    
    // DELETE USER
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id > 0) {
            // Check if user exists using prepared statement
            $checkStmt = mysqli_prepare($conn, "SELECT user_id, firstname, lastname, username FROM users WHERE user_id = ?");
            if (!$checkStmt) {
                header('Location: ../Admin/user_management.php?error=delete_failed&msg=' . urlencode('Database error: ' . mysqli_error($conn)));
                exit();
            }
            mysqli_stmt_bind_param($checkStmt, "i", $user_id);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            
            if (mysqli_num_rows($checkResult) > 0) {
                $user = mysqli_fetch_assoc($checkResult);
                mysqli_stmt_close($checkStmt);

                // Prevent deletion of the master admin account
                if (strcasecmp($user['username'] ?? '', MASTER_ADMIN_USERNAME) === 0) {
                    header('Location: ../Admin/user_management.php?error=cannot_delete_master');
                    exit();
                }

                // Check if current user is master admin (has power to hard-delete users with sales)
                $isMasterAdminDeleting = $isPharmacyAccount;

                // If the user has sales/transactions, handle based on who is deleting
                $hasSales = false;
                $salesCheckStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS cnt FROM sales WHERE user_id = ?");
                if ($salesCheckStmt) {
                    mysqli_stmt_bind_param($salesCheckStmt, "i", $user_id);
                    mysqli_stmt_execute($salesCheckStmt);
                    $salesRes = mysqli_stmt_get_result($salesCheckStmt);
                    $salesRow = $salesRes ? mysqli_fetch_assoc($salesRes) : null;
                    $hasSales = ((int)($salesRow['cnt'] ?? 0)) > 0;
                    mysqli_stmt_close($salesCheckStmt);
                }

                $displayName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
                if (empty($displayName)) {
                    $displayName = $user['username'] ?? 'User';
                }

                // If user has sales and current user is NOT master admin, soft-delete only
                if ($hasSales && !$isMasterAdminDeleting) {
                    $deactivateStmt = mysqli_prepare($conn, "UPDATE users SET status = 'Inactive' WHERE user_id = ?");
                    if ($deactivateStmt) {
                        mysqli_stmt_bind_param($deactivateStmt, "i", $user_id);
                        mysqli_stmt_execute($deactivateStmt);
                        mysqli_stmt_close($deactivateStmt);
                    }
                    header('Location: ../Admin/user_management.php?success=user_deactivated&name=' . urlencode($displayName));
                    exit();
                }

                // If user has sales and current user IS master admin, transfer sales to master admin before deletion
                if ($hasSales && $isMasterAdminDeleting) {
                    // Get master admin's user_id from database
                    $masterAdminUsername = MASTER_ADMIN_USERNAME; // Assign constant to variable for bind_param
                    $masterAdminStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? LIMIT 1");
                    if ($masterAdminStmt) {
                        mysqli_stmt_bind_param($masterAdminStmt, "s", $masterAdminUsername);
                        mysqli_stmt_execute($masterAdminStmt);
                        $masterAdminResult = mysqli_stmt_get_result($masterAdminStmt);
                        $masterAdminRow = $masterAdminResult ? mysqli_fetch_assoc($masterAdminResult) : null;
                        $masterAdminUserId = (int)($masterAdminRow['user_id'] ?? 0);
                        mysqli_stmt_close($masterAdminStmt);

                        if ($masterAdminUserId > 0) {
                            // Transfer all sales records to master admin
                            $transferStmt = mysqli_prepare($conn, "UPDATE sales SET user_id = ? WHERE user_id = ?");
                            if ($transferStmt) {
                                mysqli_stmt_bind_param($transferStmt, "ii", $masterAdminUserId, $user_id);
                                mysqli_stmt_execute($transferStmt);
                                mysqli_stmt_close($transferStmt);
                            }

                            // Also transfer stock_movement_log records if they reference this user
                            $transferLogStmt = mysqli_prepare($conn, "UPDATE stock_movement_log SET user_id = ? WHERE user_id = ?");
                            if ($transferLogStmt) {
                                mysqli_stmt_bind_param($transferLogStmt, "ii", $masterAdminUserId, $user_id);
                                mysqli_stmt_execute($transferLogStmt);
                                mysqli_stmt_close($transferLogStmt);
                            }
                        }
                    }
                }
                
                // Delete user using prepared statement
                $deleteStmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
                if (!$deleteStmt) {
                    header('Location: ../Admin/user_management.php?error=delete_failed&msg=' . urlencode('Database error: ' . mysqli_error($conn)));
                    exit();
                }
                mysqli_stmt_bind_param($deleteStmt, "i", $user_id);
                
                if (mysqli_stmt_execute($deleteStmt)) {
                    mysqli_stmt_close($deleteStmt);
                    header('Location: ../Admin/user_management.php?success=user_deleted&name=' . urlencode($displayName));
                    exit();
                } else {
                    $errorMsg = mysqli_stmt_error($deleteStmt);
                    mysqli_stmt_close($deleteStmt);
                    // If delete fails due to FK constraints, handle based on who is deleting
                    $lowerErr = strtolower((string)$errorMsg);
                    if (strpos($lowerErr, 'foreign key') !== false || strpos($lowerErr, 'constraint') !== false) {
                        // If master admin is deleting, try to transfer remaining references
                        if ($isMasterAdminDeleting) {
                            // Get master admin's user_id
                            $masterAdminUsername = MASTER_ADMIN_USERNAME; // Assign constant to variable for bind_param
                            $masterAdminStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? LIMIT 1");
                            if ($masterAdminStmt) {
                                mysqli_stmt_bind_param($masterAdminStmt, "s", $masterAdminUsername);
                                mysqli_stmt_execute($masterAdminStmt);
                                $masterAdminResult = mysqli_stmt_get_result($masterAdminStmt);
                                $masterAdminRow = $masterAdminResult ? mysqli_fetch_assoc($masterAdminResult) : null;
                                $masterAdminUserId = (int)($masterAdminRow['user_id'] ?? 0);
                                mysqli_stmt_close($masterAdminStmt);

                                if ($masterAdminUserId > 0) {
                                    // Try to transfer any remaining references (sales, stock_movement_log, etc.)
                                    @mysqli_query($conn, "UPDATE sales SET user_id = $masterAdminUserId WHERE user_id = $user_id");
                                    @mysqli_query($conn, "UPDATE stock_movement_log SET user_id = $masterAdminUserId WHERE user_id = $user_id");
                                    
                                    // Retry deletion
                                    $retryDeleteStmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
                                    if ($retryDeleteStmt) {
                                        mysqli_stmt_bind_param($retryDeleteStmt, "i", $user_id);
                                        if (mysqli_stmt_execute($retryDeleteStmt)) {
                                            mysqli_stmt_close($retryDeleteStmt);
                                            header('Location: ../Admin/user_management.php?success=user_deleted&name=' . urlencode($displayName));
                                            exit();
                                        }
                                        mysqli_stmt_close($retryDeleteStmt);
                                    }
                                }
                            }
                        }
                        // If not master admin or retry failed, fall back to soft-delete
                        $deactivateStmt = mysqli_prepare($conn, "UPDATE users SET status = 'Inactive' WHERE user_id = ?");
                        if ($deactivateStmt) {
                            mysqli_stmt_bind_param($deactivateStmt, "i", $user_id);
                            mysqli_stmt_execute($deactivateStmt);
                            mysqli_stmt_close($deactivateStmt);
                        }
                        header('Location: ../Admin/user_management.php?success=user_deactivated&name=' . urlencode($displayName));
                        exit();
                    }
                    header('Location: ../Admin/user_management.php?error=delete_failed&msg=' . urlencode($errorMsg ?: 'Failed to delete user'));
                    exit();
                }
            } else {
                if (isset($checkStmt)) mysqli_stmt_close($checkStmt);
                header('Location: ../Admin/user_management.php?error=user_not_found');
                exit();
            }
        } else {
            header('Location: ../Admin/user_management.php?error=invalid_id');
            exit();
        }
    }
}

// If no action matched, redirect back
header('Location: ../Admin/user_management.php');
exit();
?>


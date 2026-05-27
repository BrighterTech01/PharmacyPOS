<?php
/**
 * Master Admin Bootstrap
 *
 * Ensures there is always a default admin account in the `users` table:
 *   username: pharmacy
 *   password: admin123
 *   role:     Admin
 *
 * Notes:
 * - Idempotent: safe to call many times.
 * - We enforce role/status for the master account, but we DO NOT overwrite the
 *   password if it was changed (unless it's empty). This keeps the account usable
 *   as a default while still allowing an admin to change it later.
 */

if (!defined('MASTER_ADMIN_USERNAME')) {
    define('MASTER_ADMIN_USERNAME', 'pharmacy');
}
if (!defined('MASTER_ADMIN_PASSWORD')) {
    define('MASTER_ADMIN_PASSWORD', 'admin123');
}

function ensureMasterAdminAccount($conn) {
    if (!$conn) {
        return;
    }

    $masterUsername   = MASTER_ADMIN_USERNAME;
    $masterPassword   = MASTER_ADMIN_PASSWORD;
    $defaultFirstname = 'Pharmacy';
    $defaultLastname  = 'Administrator';
    $defaultAddress   = 'System Generated';

    $stmt = mysqli_prepare($conn, "SELECT user_id, password, role, status, firstname, lastname FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param($stmt, "s", $masterUsername);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existingUser = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if ($existingUser) {
        $updates = [];
        $params  = '';
        $values  = [];

        // Always enforce role/status for the master account
        if (strtolower($existingUser['role'] ?? '') !== 'admin') {
            $updates[] = "role = 'Admin'";
        }
        if (strtolower($existingUser['status'] ?? '') !== 'active') {
            $updates[] = "status = 'Active'";
        }

        // Fill basic profile if missing
        if (empty($existingUser['firstname'])) {
            $updates[] = "firstname = ?";
            $params   .= 's';
            $values[]  = $defaultFirstname;
        }
        if (empty($existingUser['lastname'])) {
            $updates[] = "lastname = ?";
            $params   .= 's';
            $values[]  = $defaultLastname;
        }

        // Only set password if missing/empty (do NOT overwrite an existing password)
        if (empty($existingUser['password'])) {
            $updates[] = "password = ?";
            $params   .= 's';
            $values[]  = password_hash($masterPassword, PASSWORD_DEFAULT);
        }

        if (!empty($updates)) {
            $setClause = implode(', ', $updates);
            $updateSql = "UPDATE users SET {$setClause} WHERE user_id = ?";
            $params   .= 'i';
            $values[]  = (int)$existingUser['user_id'];

            $updateStmt = mysqli_prepare($conn, $updateSql);
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, $params, ...$values);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }
        }
        return;
    }

    // Create the master account if it doesn't exist
    $hashedPassword = password_hash($masterPassword, PASSWORD_DEFAULT);
    $insertSql = "INSERT INTO users (firstname, lastname, contact, username, password, address, role, status)
                  VALUES (?, ?, NULL, ?, ?, ?, 'Admin', 'Active')";
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if (!$insertStmt) {
        return;
    }

    mysqli_stmt_bind_param(
        $insertStmt,
        "sssss",
        $defaultFirstname,
        $defaultLastname,
        $masterUsername,
        $hashedPassword,
        $defaultAddress
    );
    mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);
}



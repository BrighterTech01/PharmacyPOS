<?php
header("Content-Type: application/json");
session_start();
include_once '../dbConnection/connection.php';
require_once '../includes/master_admin.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);

if ($method === "POST") {
    $username = isset($input['username']) ? trim($input['username']) : '';
    $password = isset($input['password']) ? $input['password'] : '';

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Please fill in all fields'
        ]);
        exit;
    }

    // Ensure the default master admin exists in DB before any login attempts.
    // This makes the pharmacy/admin123 account a real DB user (better for audit logs).
    ensureMasterAdminAccount($conn);

    // Secure lookup using prepared statements
    $stmt = mysqli_prepare($conn, "SELECT user_id, username, password, role, status FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error (prepare failed)']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            // Block login for deactivated/inactive accounts
            $statusLower = strtolower((string)($user['status'] ?? ''));
            if ($statusLower !== '' && $statusLower !== 'active') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Account is inactive/deactivated. Please contact the administrator.'
                ]);
                if ($stmt) { mysqli_stmt_close($stmt); }
                exit;
            }

            // Regenerate session id for security
            if (function_exists('session_regenerate_id')) {
                session_regenerate_id(true);
            }

            // Update status to Active using prepared statement
            $update = mysqli_prepare($conn, "UPDATE users SET status = 'Active' WHERE user_id = ?");
            if ($update) {
                mysqli_stmt_bind_param($update, 'i', $user['user_id']);
                mysqli_stmt_execute($update);
                mysqli_stmt_close($update);
            }

            // Set session (store both flat and nested keys; the codebase checks both)
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user'] = [
                'user_id' => (int)$user['user_id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            $_SESSION['last_activity'] = time();

            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'user_id' => (int)$user['user_id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Incorrect password']);
        }
    } else {
        // Backward-compatible code-level fallback ONLY for the master admin username/password.
        // This prevents lockout if the DB record cannot be created for some reason.
        if ($username === MASTER_ADMIN_USERNAME && $password === MASTER_ADMIN_PASSWORD) {
            if (function_exists('session_regenerate_id')) {
                session_regenerate_id(true);
            }
            $_SESSION['user_id'] = -1; // sentinel for built-in account
            $_SESSION['username'] = MASTER_ADMIN_USERNAME;
            $_SESSION['role'] = 'Admin';
            $_SESSION['user'] = [
                'user_id' => -1,
                'username' => MASTER_ADMIN_USERNAME,
                'role' => 'Admin',
            ];
            $_SESSION['last_activity'] = time();

            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'user_id' => -1,
                    'username' => MASTER_ADMIN_USERNAME,
                    'role' => 'Admin'
                ]
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
    }

    if ($stmt) {
        mysqli_stmt_close($stmt);
    }
} else if ($method === "GET") {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'user' => [
                'user_id' => (int)($_SESSION['user']['user_id'] ?? $_SESSION['user_id']),
                'username' => (string)($_SESSION['user']['username'] ?? $_SESSION['username']),
                'role' => (string)($_SESSION['user']['role'] ?? $_SESSION['role'])
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'logged_in' => false]);
    }
} else if ($method === "DELETE") {
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        // Only attempt DB status update for real DB users
        if ($user_id > 0) {
            $update = mysqli_prepare($conn, "UPDATE users SET status = 'Inactive' WHERE user_id = ?");
            if ($update) {
                mysqli_stmt_bind_param($update, 'i', $user_id);
                mysqli_stmt_execute($update);
                mysqli_stmt_close($update);
            }
        }
    }

    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>

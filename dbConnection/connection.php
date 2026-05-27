<?php
/**
 * Database Connection Handler
 * Handles connection to MySQL database with comprehensive error handling
 */

// Database configuration
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'name' => getenv('DB_NAME') ?: 'pharmacy',
];

$local_config_file = __DIR__ . '/config.local.php';
if (is_file($local_config_file)) {
    $local_config = require $local_config_file;
    if (is_array($local_config)) {
        $db_config = array_merge($db_config, $local_config);
    }
}

$db_host = $db_config['host'];
$db_username = $db_config['username'];
$db_password = $db_config['password'];
$db_name = $db_config['name'];

// Initialize connection variable
$conn = null;

// Set connection options before connecting
mysqli_report(MYSQLI_REPORT_OFF); // Suppress warnings, we'll handle errors manually

// Attempt database connection
$conn = @mysqli_connect($db_host, $db_username, $db_password, $db_name);

// Check connection and handle errors
if (!$conn) {
    // Get detailed error information
    $error_code = mysqli_connect_errno();
    $error_message = mysqli_connect_error();
    
    // Log error details
    error_log("Database Connection Failed - Error Code: $error_code, Message: $error_message");
    error_log("Connection Details - Host: $db_host, Database: $db_name, Username: $db_username");
    
    // Determine error type and provide appropriate message
    $user_message = "Database connection failed. ";
    
    switch ($error_code) {
        case 1045:
            $user_message .= "Access denied. Please check your database credentials.";
            break;
        case 1049:
            $user_message .= "Database '$db_name' does not exist. Please create the database first.";
            break;
        case 2002:
        case 2003:
            $user_message .= "Cannot connect to database server. Please check if MySQL is running.";
            break;
        case 2006:
            $user_message .= "MySQL server has gone away. Please try again.";
            break;
        default:
            $user_message .= "Error Code: $error_code - $error_message";
            break;
    }
    
    // In production, show user-friendly message; in development, show detailed error
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        // Production: Show generic error page
        http_response_code(503); // Service Unavailable
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Service Unavailable</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                .error-container { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #e74c3c; }
                p { color: #666; line-height: 1.6; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1>⚠️ Service Temporarily Unavailable</h1>
                <p>The database service is currently unavailable. Please try again later.</p>
                <p>If the problem persists, please contact the system administrator.</p>
            </div>
        </body>
        </html>
        ");
    } else {
        // Development: Show detailed error
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Database Connection Error</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
                .error-container { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 5px; max-width: 800px; margin: 0 auto; }
                h1 { color: #856404; margin-top: 0; }
                .error-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px; }
                .error-details strong { color: #721c24; }
                code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1>⚠️ Database Connection Error</h1>
                <p><strong>Error:</strong> $user_message</p>
                <div class='error-details'>
                    <p><strong>Error Code:</strong> <code>$error_code</code></p>
                    <p><strong>Error Message:</strong> <code>$error_message</code></p>
                    <p><strong>Host:</strong> <code>$db_host</code></p>
                    <p><strong>Database:</strong> <code>$db_name</code></p>
                    <p><strong>Username:</strong> <code>$db_username</code></p>
                </div>
                <p style='margin-top: 15px; color: #666; font-size: 14px;'>
                    <strong>Possible Solutions:</strong><br>
                    1. Check if MySQL server is running<br>
                    2. Verify database credentials in connection.php<br>
                    3. Ensure the database '$db_name' exists<br>
                    4. Check MySQL user permissions
                </p>
            </div>
        </body>
        </html>
        ");
    }
}

// Set charset to UTF-8 to prevent encoding issues
if (!mysqli_set_charset($conn, "utf8mb4")) {
    error_log("Error setting charset to utf8mb4: " . mysqli_error($conn));
    // Fallback to utf8 if utf8mb4 is not available
    mysqli_set_charset($conn, "utf8");
}

// Enable error reporting for mysqli (only in development)
if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
} else {
    // In production, suppress errors but log them
    mysqli_report(MYSQLI_REPORT_OFF);
}

// Connection successful - log if in development mode
if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') {
    error_log("Database connection established successfully to '$db_name' on '$db_host'");
}

<?php
require_once 'config.php';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = null;
$db_error = null;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, $options);
} catch (Exception $e) {
    $db_error = $e->getMessage();
    
    // Only force exit in non-CLI web contexts
    if (php_sapi_name() !== 'cli' && isset($_SERVER['REQUEST_METHOD'])) {
        if (DEBUG_MODE) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Database connection failed: ' . $db_error]);
            exit;
        } else {
            // In production, don't expose database errors
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Internal server error']);
            exit;
        }
    }
    // In CLI mode, allow script to continue for diagnostics
    error_log("Database connection error: " . $db_error);
}

// Initialize required tables if connection was successful
if ($pdo !== null) {
    try {
        // Ensure login_attempts table exists (required for rate limiting)
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL,
            attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_action_time (ip_address, action, attempt_time)
        )");
    } catch (PDOException $e) {
        // Table creation failed - log it but don't fail the connection
        error_log("Warning: Could not create login_attempts table: " . $e->getMessage());
    }
}
?>

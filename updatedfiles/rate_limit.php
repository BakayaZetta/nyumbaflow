<?php
/**
 * Rate Limiting Utility
 * Protects against brute-force login attacks.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_config.php';

// Ensure the login_attempts table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        action VARCHAR(50) NOT NULL,
        attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_action_time (ip_address, action, attempt_time)
    )");
    
    // Check if attempt_time column exists (in case table was created by an older version)
    $check = $pdo->query("SHOW COLUMNS FROM login_attempts LIKE 'attempt_time'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE login_attempts ADD COLUMN attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP");
        $pdo->exec("CREATE INDEX idx_attempt_time ON login_attempts(attempt_time)");
    }
    
    // Check if action column exists
    $check = $pdo->query("SHOW COLUMNS FROM login_attempts LIKE 'action'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE login_attempts ADD COLUMN action VARCHAR(50) NOT NULL DEFAULT 'login'");
    }
} catch (PDOException $e) {
    // Ignore error if table exists or permission denied
}

/**
 * Get the client IP address securely.
 */
function get_client_ip() {
    return $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Check if the current IP has exceeded the rate limit.
 *
 * @param string $action The action to rate-limit (e.g., 'landlord_login')
 * @param int $max_attempts Maximum allowed attempts (default: 5)
 * @param int $lockout_minutes Lockout duration in minutes (default: 15)
 * @return bool True if allowed, False if rate limited.
 */
function check_rate_limit($action = 'login', $max_attempts = 5, $lockout_minutes = 15) {
    global $pdo;
    $ip_address = get_client_ip();
    
    // Clean old attempts (older than lockout window)
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$lockout_minutes]);

    // Count recent attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND action = ?");
    $stmt->execute([$ip_address, $action]);
    $attempts = $stmt->fetchColumn();

    if ($attempts >= $max_attempts) {
        return false; // Rate limit exceeded
    }
    
    return true; // Allowed
}

/**
 * Record a failed attempt for rate limiting.
 */
function record_failed_attempt($action = 'login') {
    global $pdo;
    $ip_address = get_client_ip();
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, action) VALUES (?, ?)");
    $stmt->execute([$ip_address, $action]);
}

/**
 * Clear successful attempts.
 */
function clear_attempts($action = 'login') {
    global $pdo;
    $ip_address = get_client_ip();
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND action = ?");
    $stmt->execute([$ip_address, $action]);
}
?>

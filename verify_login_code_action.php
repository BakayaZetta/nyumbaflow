<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';

// Check if user has temp session
if (!isset($_SESSION['temp_admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

$code = trim($_POST['code'] ?? '');

if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid verification code']);
    exit;
}

try {
    // Find valid code
    $stmt = $pdo->prepare(
        "SELECT id, landlord_id, expires_at, verified_at FROM login_verification_codes 
         WHERE landlord_id = ? AND code = ? AND verified_at IS NULL"
    );
    $stmt->execute([$_SESSION['temp_admin_id'], $code]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verification) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid verification code']);
        exit;
    }
    
    // Check expiration
    if (strtotime($verification['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Verification code has expired']);
        exit;
    }
    
    // Mark code as verified
    $stmt = $pdo->prepare("UPDATE login_verification_codes SET verified_at = NOW() WHERE id = ?");
    $stmt->execute([$verification['id']]);
    
    // Get user details
    $stmt = $pdo->prepare("SELECT id, username, email FROM landlords WHERE id = ?");
    $stmt->execute([$_SESSION['temp_admin_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Clear temp session variables
    unset($_SESSION['temp_admin_id']);
    unset($_SESSION['temp_admin_email']);
    unset($_SESSION['temp_admin_name']);
    unset($_SESSION['account_pending']);
    
    // Regenerate session ID
    session_regenerate_id(true);
    
    // Set permanent session variables
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_email'] = $user['email'];
    $_SESSION['admin_name'] = $user['username'];
    $_SESSION['account_pending'] = true; // Flag for limited access
    
    // Log audit
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $pdo->prepare("INSERT INTO audit_log (action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?)")
        ->execute(['login_verified', 'landlord', $user['id'], "Login code verified (pending approval)", $ip_address]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Verification successful! Logging you in...',
        'redirect' => 'index.php'
    ]);
    
} catch (PDOException $e) {
    error_log("Login verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>

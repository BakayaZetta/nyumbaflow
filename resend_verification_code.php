<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';
require_once 'EmailService.php';

// Check if user has temp session
if (!isset($_SESSION['temp_admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

try {
    // Delete old codes (only keep 1 active code per user)
    $pdo->prepare("DELETE FROM login_verification_codes WHERE landlord_id = ? AND verified_at IS NULL")
        ->execute([$_SESSION['temp_admin_id']]);
    
    // Get user details
    $stmt = $pdo->prepare("SELECT email, phone_number FROM landlords WHERE id = ?");
    $stmt->execute([$_SESSION['temp_admin_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Generate new code
    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store verification code
    $stmt = $pdo->prepare("INSERT INTO login_verification_codes (landlord_id, code, method, expires_at) VALUES (?, ?, 'both', ?)");
    $stmt->execute([$_SESSION['temp_admin_id'], $verification_code, $expires_at]);
    
    // Send via email
    $emailService = new EmailService();
    $emailService->send($user['email'], 
        'New Login Verification Code - Nyumbaflow', 
        "Your new 6-digit verification code is: <strong>$verification_code</strong><br>This code expires in 10 minutes.");
    
    // Send via SMS if phone exists
    if (!empty($user['phone_number'])) {
        require_once 'SmsService.php';
        $smsService = new SmsService();
        $smsService->sendSms($user['phone_number'], "Your new Nyumbaflow verification code is: $verification_code. Code expires in 10 minutes.");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'New verification code sent to your email and SMS'
    ]);
    
} catch (Exception $e) {
    error_log("Resend verification code error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to resend code']);
}
?>

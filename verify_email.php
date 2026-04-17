<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';
require_once 'EmailService.php';

function wantsJsonResponse() {
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $xrw = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    return strpos($accept, 'application/json') !== false || $xrw === 'xmlhttprequest';
}

function respondVerification($ok, $message) {
    if (wantsJsonResponse()) {
        header('Content-Type: application/json; charset=utf-8');
        if ($ok) {
            echo json_encode(['success' => true, 'message' => $message, 'redirect' => 'auth.php']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $message]);
        }
        exit;
    }

    $query = $ok ? 'verification=success&message=' : 'verification=error&message=';
    header('Location: auth.php?' . $query . urlencode($message));
    exit;
}

$token = $_GET['token'] ?? '';

if (empty($token)) {
    respondVerification(false, 'Invalid verification link');
}

try {
    // Find verification token
    $stmt = $pdo->prepare("SELECT landlord_id, expires_at, verified_at FROM email_verifications WHERE token = ?");
    $stmt->execute([$token]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verification) {
        respondVerification(false, 'Invalid or expired verification link');
    }
    
    if ($verification['verified_at'] !== null) {
        respondVerification(false, 'This email has already been verified');
    }
    
    // Check expiration
    if (strtotime($verification['expires_at']) < time()) {
        respondVerification(false, 'Verification link has expired. Please sign up again.');
    }
    
    // Mark as verified
    $stmt = $pdo->prepare("UPDATE email_verifications SET verified_at = NOW() WHERE token = ?");
    $stmt->execute([$token]);
    
    // Update user status to pending_approval
    $stmt = $pdo->prepare("UPDATE landlords SET status = 'pending_approval' WHERE id = ?");
    $stmt->execute([$verification['landlord_id']]);
    
    // Get user details
    $stmt = $pdo->prepare("SELECT id, username, email, phone_number FROM landlords WHERE id = ?");
    $stmt->execute([$verification['landlord_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create approval request record
    $stmt = $pdo->prepare("INSERT INTO account_approvals (landlord_id, status) VALUES (?, 'pending')");
    $stmt->execute([$user['id']]);
    
    // Get active approvers
    $stmt = $pdo->prepare("SELECT approver_email FROM approver_settings WHERE is_active = TRUE");
    $stmt->execute();
    $approvers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Send approval notifications to all approvers
    $emailService = new EmailService();
    foreach ($approvers as $approver_email) {
        $emailService->sendApprovalNotification($approver_email, $user['username'], $user['email'], $user['phone_number']);
    }
    
    // Log audit
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $pdo->prepare("INSERT INTO audit_log (action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?)")
        ->execute(['email_verified', 'landlord', $user['id'], "Email verified, awaiting approval", $ip_address]);
    
    respondVerification(true, 'Email verified successfully! Your account is now pending approval. You will be notified once reviewed.');
    
} catch (PDOException $e) {
    error_log("Email verification error: " . $e->getMessage());
    if (wantsJsonResponse()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    } else {
        header('Location: auth.php?verification=error&message=' . urlencode('Internal server error')); 
    }
}
?>

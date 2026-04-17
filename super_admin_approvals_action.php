<?php
require_once 'db_config.php';
require_once 'EmailService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Check if superadmin is logged in
if (!isset($_SESSION['superadmin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$landlord_id = (int)($_POST['landlord_id'] ?? 0);

if (!$action || !$landlord_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    if ($action === 'approve') {
        handleApprove($landlord_id);
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        if (empty($reason)) {
            http_response_code(400);
            echo json_encode(['error' => 'Rejection reason is required']);
            exit;
        }
        handleReject($landlord_id, $reason);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Approval action error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleApprove($landlord_id) {
    global $pdo;
    
    // Get user details
    $stmt = $pdo->prepare("SELECT id, username, email, phone_number FROM landlords WHERE id = ?");
    $stmt->execute([$landlord_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    
    // Update user status to active
    $stmt = $pdo->prepare("UPDATE landlords SET status = 'active' WHERE id = ?");
    $stmt->execute([$landlord_id]);
    
    // Update approval record
    $stmt = $pdo->prepare("UPDATE account_approvals SET status = 'approved', approver_id = ?, approved_at = NOW() WHERE landlord_id = ?");
    $stmt->execute([$_SESSION['superadmin_id'], $landlord_id]);
    
    // Get approver name
    $stmt = $pdo->prepare("SELECT username FROM landlords WHERE id = ?");
    $stmt->execute([$_SESSION['superadmin_id']]);
    $approver = $stmt->fetch(PDO::FETCH_COLUMN);
    
    // Send approval email to user
    $emailService = new EmailService();
    $emailService->sendApprovalConfirmation($user['email'], $user['username'], date('M d, Y H:i'));
    
    // Send approval SMS if phone exists
    if (!empty($user['phone_number'])) {
        require_once 'SmsService.php';
        $smsService = new SmsService();
        $smsService->sendSms($user['phone_number'], 
            "Great news! Your Nyumbaflow account has been approved. You can now log in to your dashboard. Visit: " . 
            ($_SERVER['HTTP_HOST'] ?? 'nyumbaflow.com'));
    }
    
    // Log audit
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $pdo->prepare("INSERT INTO audit_log (action, entity_type, entity_id, admin_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute(['account_approved', 'landlord', $landlord_id, $_SESSION['superadmin_id'], 
            "Account approved by $approver", $ip_address]);
    
    echo json_encode([
        'success' => true,
        'message' => "Account for {$user['username']} has been approved. Confirmation email sent."
    ]);
}

function handleReject($landlord_id, $reason) {
    global $pdo;
    
    // Get user details
    $stmt = $pdo->prepare("SELECT id, username, email, phone_number FROM landlords WHERE id = ?");
    $stmt->execute([$landlord_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    
    // Update user status to rejected
    $stmt = $pdo->prepare("UPDATE landlords SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$landlord_id]);
    
    // Update approval record with rejection reason
    $stmt = $pdo->prepare("UPDATE account_approvals SET status = 'rejected', approver_id = ?, rejection_reason = ?, rejected_at = NOW() WHERE landlord_id = ?");
    $stmt->execute([$_SESSION['superadmin_id'], $reason, $landlord_id]);
    
    // Get approver name
    $stmt = $pdo->prepare("SELECT username FROM landlords WHERE id = ?");
    $stmt->execute([$_SESSION['superadmin_id']]);
    $approver = $stmt->fetch(PDO::FETCH_COLUMN);
    
    // Send rejection email to user
    $emailService = new EmailService();
    $emailService->sendRejectionNotification($user['email'], $user['username'], $reason, date('M d, Y H:i'));
    
    // Send rejection SMS if phone exists
    if (!empty($user['phone_number'])) {
        require_once 'SmsService.php';
        $smsService = new SmsService();
        $smsService->sendSms($user['phone_number'], 
            "Your Nyumbaflow account application has been reviewed. Please check your email for details on how to proceed.");
    }
    
    // Log audit
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $pdo->prepare("INSERT INTO audit_log (action, entity_type, entity_id, admin_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute(['account_rejected', 'landlord', $landlord_id, $_SESSION['superadmin_id'], 
            "Account rejected by $approver. Reason: $reason", $ip_address]);
    
    echo json_encode([
        'success' => true,
        'message' => "Account for {$user['username']} has been rejected. Notification email sent."
    ]);
}
?>

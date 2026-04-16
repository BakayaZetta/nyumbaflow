<?php
session_start();
require_once 'db_config.php';
require_once 'SmsService.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $landlord_id = $_SESSION['admin_id'];
    $new_rate = floatval($_POST['water_rate']);
    $should_notify = isset($_POST['notify_tenants']);
    
    try {
        $pdo->beginTransaction();
        
        // 1. Update rates for all units belonging to this landlord's properties
        $stmt = $pdo->prepare("
            UPDATE units u
            JOIN properties p ON u.property_id = p.id
            SET u.water_rate = ?
            WHERE p.landlord_id = ?
        ");
        $stmt->execute([$new_rate, $landlord_id]);
        
        // 2. Notify tenants if requested
        if ($should_notify) {
            $sms = new SmsService();
            
            $tStmt = $pdo->prepare("
                SELECT t.phone_number, t.name, p.name as property_name
                FROM tenants t
                JOIN properties p ON t.property_id = p.id
                WHERE p.landlord_id = ? AND t.status = 'active'
            ");
            $tStmt->execute([$landlord_id]);
            $tenants = $tStmt->fetchAll();
            
            foreach ($tenants as $t) {
                $msg = "Hello " . $t['name'] . ", please note that the water rate for " . $t['property_name'] . " has been updated to KES " . number_format($new_rate) . " per unit. Thank you.";
                $sms->sendSms($t['phone_number'], $msg);
            }
        }
        
        $pdo->commit();
        $_SESSION['message'] = "Rates updated successfully!" . ($should_notify ? " Tenants have been notified." : "");
        $_SESSION['message_type'] = "success";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error updating rates: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: settings.php");
    exit();
}
?>

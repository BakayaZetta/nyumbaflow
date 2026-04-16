<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = $_POST['tenant_id'];
    $bill_type = $_POST['bill_type'];
    $amount = floatval($_POST['amount']);
    $month = date('F');
    $year = date('Y');
    $due_date = date('Y-m-d', strtotime('next month 5th'));
    
    try {
        // Find the unit_id for this tenant
        $stmt = $pdo->prepare("SELECT unit_id FROM tenants WHERE id = ?");
        $stmt->execute([$tenant_id]);
        $unit_id = $stmt->fetchColumn();
        
        if (!$unit_id) {
            die("Invalid tenant or unit.");
        }
        
        // Insert custom bill
        $ins = $pdo->prepare("
            INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, due_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')
        ");
        $ins->execute([
            $tenant_id,
            $unit_id,
            $bill_type,
            $amount,
            $amount,
            $month,
            $year,
            $due_date
        ]);
        
        $_SESSION['message'] = "Custom bill created successfully!";
        $_SESSION['message_type'] = "success";
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Error creating bill: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: billing.php");
    exit();
}
?>

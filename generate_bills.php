<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.php");
    exit();
}

$property_id = $_GET['property_id'] ?? null;
if (!$property_id) {
    die("Property ID required.");
}

// IDOR Check: Ensure property belongs to the landlord
$stmt = $pdo->prepare("SELECT id FROM properties WHERE id = ? AND landlord_id = ?");
$stmt->execute([$property_id, $_SESSION['admin_id']]);
if (!$stmt->fetch()) {
    die("Unauthorized property access.");
}

// Simple generation logic for the current month
$month = date('F');
$year = date('Y');
$due_date = date('Y-m-d', strtotime('next month 5th')); // Arbitrary due date: 5th of next month

try {
    $pdo->beginTransaction();

    // 1. Fetch all units for this property with rates
    $stmt = $pdo->prepare("SELECT id, rent_amount, water_rate, wifi_fee, garbage_fee, late_fee_enabled, late_fee_rate FROM units WHERE property_id = ?");
    $stmt->execute([$property_id]);
    $units = $stmt->fetchAll();

    foreach ($units as $unit) {
        // 2. Fetch active tenant and their enrollment status
        $tStmt = $pdo->prepare("SELECT id, name, balance_credit, has_wifi, has_garbage FROM tenants WHERE unit_id = ? AND status = 'active' LIMIT 1");
        $tStmt->execute([$unit['id']]);
        $tenant = $tStmt->fetch();

        if ($tenant) {
            $tenant_id = $tenant['id'];
            $credit = $tenant['balance_credit'];
            
            // a. Rent Bill
            $checkRent = $pdo->prepare("SELECT id FROM bills WHERE unit_id = ? AND month = ? AND year = ? AND bill_type = 'rent'");
            $checkRent->execute([$unit['id'], $month, $year]);
            
            if (!$checkRent->fetch()) {
                $rent = $unit['rent_amount'];
                $rent_balance = $rent;
                
                if ($credit > 0) {
                    $reduction = min($credit, $rent_balance);
                    $rent_balance -= $reduction;
                    $credit -= $reduction;
                }

                $ins = $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, due_date, status) VALUES (?, ?, 'rent', ?, ?, ?, ?, ?, ?)");
                $ins->execute([$tenant_id, $unit['id'], $rent, $rent_balance, $month, $year, $due_date, $rent_balance <= 0 ? 'paid' : 'unpaid']);
            }

            // b. WiFi Bill (if enrolled)
            if ($tenant['has_wifi'] && $unit['wifi_fee'] > 0) {
                $checkWifi = $pdo->prepare("SELECT id FROM bills WHERE unit_id = ? AND month = ? AND year = ? AND bill_type = 'wifi'");
                $checkWifi->execute([$unit['id'], $month, $year]);
                if (!$checkWifi->fetch()) {
                    $wifi = $unit['wifi_fee'];
                    $wifi_balance = $wifi;
                    if ($credit > 0) {
                        $reduction = min($credit, $wifi_balance);
                        $wifi_balance -= $reduction;
                        $credit -= $reduction;
                    }
                    $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, due_date, status) VALUES (?, ?, 'wifi', ?, ?, ?, ?, ?, ?)")
                        ->execute([$tenant_id, $unit['id'], $wifi, $wifi_balance, $month, $year, $due_date, $wifi_balance <= 0 ? 'paid' : 'unpaid']);
                }
            }

            // c. Garbage Bill (if enrolled) — independent of WiFi
            if ($tenant['has_garbage'] && $unit['garbage_fee'] > 0) {
                $checkGarbage = $pdo->prepare("SELECT id FROM bills WHERE unit_id = ? AND month = ? AND year = ? AND bill_type = 'garbage'");
                $checkGarbage->execute([$unit['id'], $month, $year]);
                if (!$checkGarbage->fetch()) {
                    $garbage = $unit['garbage_fee'];
                    $garbage_balance = $garbage;
                    if ($credit > 0) {
                        $reduction = min($credit, $garbage_balance);
                        $garbage_balance -= $reduction;
                        $credit -= $reduction;
                    }
                    $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, due_date, status) VALUES (?, ?, 'garbage', ?, ?, ?, ?, ?, ?)")
                        ->execute([$tenant_id, $unit['id'], $garbage, $garbage_balance, $month, $year, $due_date, $garbage_balance <= 0 ? 'paid' : 'unpaid']);
                }
            }

            // d. Water Bill (Variable)
            $rStmt = $pdo->prepare("SELECT reading_curr, reading_prev FROM bills WHERE unit_id = ? AND bill_type = 'water' AND status = 'unpaid' AND month = ? AND year = ? ORDER BY id DESC LIMIT 1");
            $rStmt->execute([$unit['id'], $month, $year]);
            $reading = $rStmt->fetch();
            
            if ($reading && $reading['reading_curr'] > $reading['reading_prev']) {
                $units_used = $reading['reading_curr'] - $reading['reading_prev'];
                $water_total = $units_used * $unit['water_rate'];
                $water_balance = $water_total;
                
                if ($credit > 0) {
                    $reduction = min($credit, $water_balance);
                    $water_balance -= $reduction;
                    $credit -= $reduction;
                }
                
                // Update the existing (placeholder) water bill with the calculated amount
                $pdo->prepare("UPDATE bills SET amount = ?, balance = ?, status = ? WHERE unit_id = ? AND bill_type = 'water' AND month = ? AND year = ?")
                    ->execute([$water_total, $water_balance, $water_balance <= 0 ? 'paid' : 'unpaid', $unit['id'], $month, $year]);
            }
            
            // 3. Update remaining credit back to tenant
            $pdo->prepare("UPDATE tenants SET balance_credit = ? WHERE id = ?")->execute([$credit, $tenant_id]);
        }
    }

    $pdo->commit();
    $_SESSION['message'] = "Bills for $month $year generated successfully!";
    $_SESSION['message_type'] = "success";

    // Trigger Bulk SMS for rent reminders
    require_once 'SmsService.php';
    $sms = new SmsService();
    $sms->sendMonthlyBills($pdo, $property_id, $month, $year);

    header("Location: index.php?property_id=$property_id");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error generating bills: " . $e->getMessage());
}
?>

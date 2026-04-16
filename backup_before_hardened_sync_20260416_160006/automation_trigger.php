<?php
// automation_trigger.php
// This script is included in index.php to handle autogeneration of bills and SMS on the 1st of every month.

require_once 'db_config.php';
require_once 'SmsService.php';

$month = date('F');
$year = date('Y');
$day = date('j');

// We only run automation on the 1st of the month
if ($day == 1) {
    $landlord_id = $_SESSION['admin_id'];
    $sms = new SmsService();

    // Fetch all landlord's properties
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE landlord_id = ?");
    $stmt->execute([$landlord_id]);
    $props = $stmt->fetchAll();

    foreach ($props as $p) {
        $p_id = $p['id'];

        // 1. Check if bills need to be generated
        if ($p['last_bill_gen_month'] !== $month || $p['last_bill_gen_year'] != $year) {
            // SILENT BILL GENERATION (Logic from generate_bills.php)
            try {
                $pdo->beginTransaction();
                $due_date = date('Y-m-d', strtotime('5th ' . $month . ' ' . $year));

                $uStmt = $pdo->prepare("SELECT id, rent_amount, water_rate, wifi_fee, garbage_fee FROM units WHERE property_id = ?");
                $uStmt->execute([$p_id]);
                $units = $uStmt->fetchAll();

                foreach ($units as $unit) {
                    $tStmt = $pdo->prepare("SELECT id, balance_credit, has_wifi, has_garbage FROM tenants WHERE unit_id = ? AND status = 'active' LIMIT 1");
                    $tStmt->execute([$unit['id']]);
                    $tenant = $tStmt->fetch();

                    if ($tenant) {
                        $t_id = $tenant['id'];
                        $credit = $tenant['balance_credit'];

                        // Rent
                        $check = $pdo->prepare("SELECT id FROM bills WHERE unit_id = ? AND month = ? AND year = ? AND bill_type = 'rent'");
                        $check->execute([$unit['id'], $month, $year]);
                        if (!$check->fetch()) {
                            $amt = $unit['rent_amount'];
                            $bal = max(0, $amt - $credit);
                            $credit = max(0, $credit - $amt);
                            $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, due_date, status) VALUES (?, ?, 'rent', ?, ?, ?, ?, ?, ?)")
                                ->execute([$t_id, $unit['id'], $amt, $bal, $month, $year, $due_date, $bal <= 0 ? 'paid' : 'unpaid']);
                        }

                        // WiFi
                        if ($tenant['has_wifi'] && $unit['wifi_fee'] > 0) {
                            $check = $pdo->prepare("SELECT id FROM bills WHERE unit_id = ? AND month = ? AND year = ? AND bill_type = 'wifi'");
                            $check->execute([$unit['id'], $month, $year]);
                            if (!$check->fetch()) {
                                $amt = $unit['wifi_fee'];
                                $bal = max(0, $amt - $credit);
                                $credit = max(0, $credit - $amt);
                                $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, due_date, status) VALUES (?, ?, 'wifi', ?, ?, ?, ?, ?, ?)")
                                    ->execute([$t_id, $unit['id'], $amt, $bal, $month, $year, $due_date, $bal <= 0 ? 'paid' : 'unpaid']);
                            }
                        }

                        // Garbage
                        if ($tenant['has_garbage'] && $unit['garbage_fee'] > 0) {
                            $check = $pdo->prepare("SELECT id FROM bills WHERE unit_id = ? AND month = ? AND year = ? AND bill_type = 'garbage'");
                            $check->execute([$unit['id'], $month, $year]);
                            if (!$check->fetch()) {
                                $amt = $unit['garbage_fee'];
                                $bal = max(0, $amt - $credit);
                                $credit = max(0, $credit - $amt);
                                $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, due_date, status) VALUES (?, ?, 'garbage', ?, ?, ?, ?, ?, ?)")
                                    ->execute([$t_id, $unit['id'], $amt, $bal, $month, $year, $due_date, $bal <= 0 ? 'paid' : 'unpaid']);
                            }
                        }

                        // Water Placeholder (Initial entry for reading)
                        $check = $pdo->prepare("SELECT id FROM bills WHERE unit_id = ? AND month = ? AND year = ? AND bill_type = 'water'");
                        $check->execute([$unit['id'], $month, $year]);
                        if (!$check->fetch()) {
                            // Fetch previous reading for placeholder
                            $prevStmt = $pdo->prepare("SELECT reading_curr FROM bills WHERE unit_id = ? AND bill_type = 'water' ORDER BY id DESC LIMIT 1");
                            $prevStmt->execute([$unit['id']]);
                            $prev = $prevStmt->fetchColumn() ?: 0;
                            
                            $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, reading_curr, reading_prev, due_date, status) VALUES (?, ?, 'water', 0, 0, ?, ?, ?, ?, ?, 'unpaid')")
                                ->execute([$t_id, $unit['id'], $month, $year, $prev, $prev, $due_date]);
                        }

                        // Update credit
                        $pdo->prepare("UPDATE tenants SET balance_credit = ? WHERE id = ?")->execute([$credit, $t_id]);
                    }
                }

                // Update property record
                $pdo->prepare("UPDATE properties SET last_bill_gen_month = ?, last_bill_gen_year = ? WHERE id = ?")
                    ->execute([$month, $year, $p_id]);
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Automation Bill Gen Error for Prop $p_id: " . $e->getMessage());
            }
        }

        // 2. Check if billing SMS needs to be sent
        if ($p['last_billing_sms_month'] !== $month || $p['last_billing_sms_year'] != $year) {
            // Trigger SMS dispatch at 8:00 AM (or whenever first login happens)
            if (date('H') >= 8) {
                if ($sms->sendMonthlyBills($pdo, $p_id, $month, $year)) {
                    $pdo->prepare("UPDATE properties SET last_billing_sms_month = ?, last_billing_sms_year = ? WHERE id = ?")
                        ->execute([$month, $year, $p_id]);
                }
            }
        }
    }
}
?>

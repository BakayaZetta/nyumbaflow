<?php
session_start();
require_once 'db_config.php';

// Check session timeout (10 minutes)
if (isset($_SESSION['admin_id']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > 600) { // 10 minutes
        session_unset();
        session_destroy();
        header("Location: auth.html");
        exit();
    }
}
$_SESSION['last_activity'] = time();

// Check if landlord is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.html");
    exit();
}

$landlord_id = $_SESSION['admin_id'];
require_once 'SmsService.php';
$sms = new SmsService();

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle Water Readings Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_readings'])) {
    $readings = $_POST['readings']; // Array of unit_id => current_reading
    $month = date('F');
    $year = date('Y');
    
    try {
        $pdo->beginTransaction();
        foreach ($readings as $unit_id => $curr) {
            if ($curr === '') continue;
            
            // Get previous reading (last month's current)
            $prevStmt = $pdo->prepare("SELECT reading_curr FROM bills WHERE unit_id = ? AND bill_type = 'water' ORDER BY id DESC LIMIT 1");
            $prevStmt->execute([$unit_id]);
            $prev = $prevStmt->fetchColumn() ?: 0;
            
            // Check if water bill for this month exists
            $check = $pdo->prepare("SELECT id FROM bills WHERE unit_id = ? AND bill_type = 'water' AND month = ? AND year = ?");
            $check->execute([$unit_id, $month, $year]);
            $bill = $check->fetch();
            
            if ($bill) {
                // Fetch water rate for calculation
                $rateStmt = $pdo->prepare("SELECT water_rate FROM units WHERE id = ?");
                $rateStmt->execute([$unit_id]);
                $rate = $rateStmt->fetchColumn() ?: 0;
                
                $units_used = max(0, $curr - $prev);
                $amount = $units_used * $rate;
                
                $upd = $pdo->prepare("UPDATE bills SET reading_curr = ?, reading_prev = ?, amount = ?, balance = ? WHERE id = ?");
                $upd->execute([$curr, $prev, $amount, $amount, $bill['id']]);
            } else {
                // Get tenant
                $tStmt = $pdo->prepare("SELECT id FROM tenants WHERE unit_id = ? AND status = 'active'");
                $tStmt->execute([$unit_id]);
                $tid = $tStmt->fetchColumn();
                
                if ($tid) {
                    // Fetch water rate for calculation
                    $rateStmt = $pdo->prepare("SELECT water_rate FROM units WHERE id = ?");
                    $rateStmt->execute([$unit_id]);
                    $rate = $rateStmt->fetchColumn() ?: 0;
                    
                    $units_used = max(0, $curr - $prev);
                    $amount = $units_used * $rate;
                    
                    $ins = $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, reading_curr, reading_prev, due_date, status) VALUES (?, ?, 'water', ?, ?, ?, ?, ?, ?, ?, 'unpaid')");
                    $ins->execute([
                        $tid, 
                        $unit_id, 
                        $amount, 
                        $amount, 
                        $month, 
                        $year, 
                        $curr, 
                        $prev, 
                        date('Y-m-d', strtotime('5th next month'))
                    ]);
                }
            }
        }
        $pdo->commit();
        $_SESSION['message'] = "Readings saved successfully! You can now generate major bills.";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error saving readings: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    header("Location: billing.php?property_id=" . $_POST['property_id'] . "&tab=readings");
    exit();
}

// Handle SMS Invoice Send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_invoice'])) {
    $tenant_id = $_POST['tenant_id'];
    $month = date('F');
    $year = date('Y');

    // Fetch all unpaid/partial bills for this tenant for specific month
    $stmt = $pdo->prepare("
        SELECT b.*, t.name, t.phone_number, p.name as prop_name, u.unit_number
        FROM bills b
        JOIN tenants t ON b.tenant_id = t.id
        JOIN units u ON b.unit_id = u.id
        JOIN properties p ON u.property_id = p.id
        WHERE b.tenant_id = ? AND b.month = ? AND b.year = ?
    ");
    $stmt->execute([$tenant_id, $month, $year]);
    $tenant_bills = $stmt->fetchAll();

    if ($tenant_bills) {
        $data = [
            'property' => $tenant_bills[0]['prop_name'],
            'month' => "$month $year",
            'rent' => 0, 'water_units' => 0, 'water_cost' => 0, 'wifi' => 0, 'garbage' => 0, 'credit' => 0, 'total' => 0
        ];

        foreach ($tenant_bills as $tb) {
            if ($tb['bill_type'] == 'rent') $data['rent'] = $tb['amount'];
            if ($tb['bill_type'] == 'water') {
                $data['water_units'] = $tb['reading_curr'] - $tb['reading_prev'];
                $data['water_cost'] = $tb['amount'];
            }
            if ($tb['bill_type'] == 'wifi') $data['wifi'] = $tb['amount'];
            if ($tb['bill_type'] == 'garbage') $data['garbage'] = $tb['amount'];
            $data['total'] += $tb['balance'];
        }

        // Fetch remaining credit
        $cStmt = $pdo->prepare("SELECT balance_credit FROM tenants WHERE id = ?");
        $cStmt->execute([$tenant_id]);
        $data['credit'] = $cStmt->fetchColumn();

        if ($sms->sendMonthlyBreakdown($tenant_bills[0]['phone_number'], $tenant_bills[0]['name'], $data)) {
            $_SESSION['message'] = "Invoice sent to " . $tenant_bills[0]['name'];
            $_SESSION['message_type'] = "success";
        }
    }
    header("Location: billing.php?property_id=" . $_POST['property_id']);
    exit();
}

// Handle Manual Bill Adjustment (distributed across tenant's bills for a month)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_adjustment'])) {
    $tenant_id = intval($_POST['adjust_tenant_id']);
    $adj_month = $_POST['adjust_month'];
    $adj_year = $_POST['adjust_year'];
    $adjustment_type = $_POST['adjustment_type'];
    $amount = floatval($_POST['adjustment_amount']);
    $payment_method = $_POST['payment_method'] ?? null;
    $notes = $_POST['adjustment_notes'] ?? '';
    $property_id_redirect = $_POST['redirect_property_id'] ?? '';

    try {
        $pdo->beginTransaction();

        if ($adjustment_type === 'payment') {
            // === DISTRIBUTED PAYMENT LOGIC ===
            // Fetch ALL unpaid/partial bills for this tenant in this month, ordered by type priority
            $billsStmt = $pdo->prepare("
                SELECT id, bill_type, amount, balance 
                FROM bills 
                WHERE tenant_id = ? AND month = ? AND year = ? AND status != 'paid'
                ORDER BY FIELD(bill_type, 'rent', 'water', 'wifi', 'garbage', 'penalty', 'other')
            ");
            $billsStmt->execute([$tenant_id, $adj_month, $adj_year]);
            $pending_bills = $billsStmt->fetchAll();

            $remaining_payment = $amount;

            // Record the overall payment (link to first bill for reference)
            if (!empty($pending_bills)) {
                $payStmt = $pdo->prepare("INSERT INTO payments (bill_id, amount, payment_method, transaction_reference, payment_date) VALUES (?, ?, ?, ?, NOW())");
                $payStmt->execute([$pending_bills[0]['id'], $amount, $payment_method, $notes]);
            }

            // Distribute payment across bills sequentially
            foreach ($pending_bills as $bill) {
                if ($remaining_payment <= 0) break;

                if ($remaining_payment >= $bill['balance']) {
                    // Payment covers this bill fully
                    $remaining_payment -= $bill['balance'];
                    $pdo->prepare("UPDATE bills SET balance = 0, status = 'paid' WHERE id = ?")->execute([$bill['id']]);
                } else {
                    // Payment only partially covers this bill
                    $new_bal = $bill['balance'] - $remaining_payment;
                    $remaining_payment = 0;
                    $pdo->prepare("UPDATE bills SET balance = ?, status = 'partial' WHERE id = ?")->execute([$new_bal, $bill['id']]);
                }
            }

            // If there's still money left over after clearing all bills → tenant credit
            if ($remaining_payment > 0) {
                $pdo->prepare("UPDATE tenants SET balance_credit = balance_credit + ? WHERE id = ?")->execute([$remaining_payment, $tenant_id]);
            }

            // Send SMS Confirmation
            $tStmt = $pdo->prepare("SELECT name, phone_number, property_id FROM tenants WHERE id = ?");
            $tStmt->execute([$tenant_id]);
            $tenant = $tStmt->fetch();

            $pStmt = $pdo->prepare("SELECT name FROM properties WHERE id = ?");
            $pStmt->execute([$tenant['property_id']]);
            $propName = $pStmt->fetchColumn();

            // Total remaining balance across ALL months for this tenant
            $balStmt = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) FROM bills WHERE tenant_id = ? AND status != 'paid'");
            $balStmt->execute([$tenant_id]);
            $total_remaining = $balStmt->fetchColumn();

            $credStmt = $pdo->prepare("SELECT balance_credit FROM tenants WHERE id = ?");
            $credStmt->execute([$tenant_id]);
            $credit = $credStmt->fetchColumn() ?: 0;

            $net_balance = $total_remaining - $credit;

            $sms->sendPaymentConfirmation($tenant['phone_number'], $tenant['name'], $amount, $net_balance, $propName);

        } elseif ($adjustment_type === 'penalty') {
            // Add penalty as a new bill entry
            $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, due_date, status) 
                SELECT ?, unit_id, 'penalty', ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'unpaid' 
                FROM tenants WHERE id = ?")
                ->execute([$tenant_id, $amount, $amount, $adj_month, $adj_year, $tenant_id]);

        } elseif ($adjustment_type === 'discount' || $adjustment_type === 'credit') {
            // Distribute discount/credit across unpaid bills
            $billsStmt = $pdo->prepare("SELECT id, balance FROM bills WHERE tenant_id = ? AND month = ? AND year = ? AND status != 'paid' ORDER BY balance ASC");
            $billsStmt->execute([$tenant_id, $adj_month, $adj_year]);
            $pending = $billsStmt->fetchAll();
            $remaining = $amount;
            foreach ($pending as $bill) {
                if ($remaining <= 0) break;
                if ($remaining >= $bill['balance']) {
                    $remaining -= $bill['balance'];
                    $pdo->prepare("UPDATE bills SET balance = 0, status = 'paid' WHERE id = ?")->execute([$bill['id']]);
                } else {
                    $new_bal = $bill['balance'] - $remaining;
                    $remaining = 0;
                    $pdo->prepare("UPDATE bills SET balance = ?, status = 'partial' WHERE id = ?")->execute([$new_bal, $bill['id']]);
                }
            }
        }

        $pdo->commit();
        $_SESSION['message'] = "Adjustment applied successfully!";
        $_SESSION['message_type'] = "success";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }

    header("Location: billing.php?property_id=" . $property_id_redirect);
    exit();
}

// Fetch Properties for filtering
$stmt = $pdo->prepare("SELECT * FROM properties WHERE landlord_id = ?");
$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll();

$current_property_id = $_GET['property_id'] ?? ($properties[0]['id'] ?? null);

// Fetch Bills for the selected property
$bills = [];
$property_tenants = [];
if ($current_property_id) {
    // Bills — grouped per tenant per month (summed)
    $stmt = $pdo->prepare("
        SELECT 
            b.tenant_id, b.unit_id, b.month, b.year,
            SUM(b.amount) as total_amount, 
            SUM(b.balance) as total_balance,
            GROUP_CONCAT(DISTINCT b.bill_type ORDER BY FIELD(b.bill_type, 'rent','water','wifi','garbage','penalty','other')) as bill_types,
            t.name as tenant_name, u.unit_number
        FROM bills b 
        JOIN units u ON b.unit_id = u.id 
        JOIN tenants t ON b.tenant_id = t.id 
        WHERE u.property_id = ? 
        GROUP BY b.tenant_id, b.month, b.year
        ORDER BY b.year DESC, b.month DESC, u.unit_number ASC
    ");
    $stmt->execute([$current_property_id]);
    $bills = $stmt->fetchAll();

    // Tenants for the modal
    $stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE property_id = ? AND status = 'active'");
    $stmt->execute([$current_property_id]);
    $property_tenants = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detailed Billing - HomeSync</title>
    <link rel="shortcut icon" href="icons/home.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --success: #2ec4b6;
            --warning: #ff9f1c;
            --danger: #e71d36;
            --light: #f8f9fa;
            --dark: #0f172a;
            --gray: #64748b;
            --shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f1f5f9; color: var(--dark); display: flex; min-height: 100vh; }

        .app-container { display: flex; width: 100%; }
        .main { flex: 1; padding: 30px; overflow-y: auto; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: var(--dark); }

        .filter-bar { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; align-items: center; }
        .prop-select { padding: 10px 15px; border-radius: 10px; border: 1px solid #ddd; background: white; font-weight: 500; }

        .billing-card { background: white; border-radius: 20px; padding: 25px; box-shadow: var(--shadow); }
        
        .math-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
            padding: 20px;
            background: var(--light);
            border-radius: 15px;
        }
        .math-item h4 { font-size: 12px; color: var(--gray); text-transform: uppercase; letter-spacing: 1px; }
        .math-item p { font-size: 20px; font-weight: 700; margin-top: 5px; }

        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 15px; color: var(--gray); font-size: 14px; font-weight: 600; border-bottom: 1px solid #f1f5f9; }
        .table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        .status-badge { padding: 5px 12px; border-radius: 50px; font-size: 12px; font-weight: 600; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-unpaid { background: #fee2e2; color: #991b1b; }
        .status-partial { background: #fef3c7; color: #92400e; }

        .btn { padding: 10px 15px; border-radius: 8px; cursor: pointer; transition: var(--transition); border: none; font-weight: 600; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 450px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main">
            <div class="page-header">
                <h1>Detailed Billing</h1>
                <div style="display: flex; gap: 10px;">
                    <a href="?property_id=<?php echo $current_property_id; ?>&tab=overview" class="btn <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'overview') ? 'btn-primary' : 'btn-outline'; ?>">Overview</a>
                    <a href="?property_id=<?php echo $current_property_id; ?>&tab=readings" class="btn <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'readings') ? 'btn-primary' : 'btn-outline'; ?>">Utility Readings</a>
                    <button class="btn btn-primary" onclick="openCustomBillModal()"><i class="fas fa-plus"></i> Create Custom Bill</button>
                </div>
            </div>

            <?php if ($message): ?>
                <div style="padding: 15px; border-radius: 12px; margin-bottom: 25px; background: <?php echo $message_type == 'success' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $message_type == 'success' ? '#166534' : '#991b1b'; ?>;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="filter-bar">
                <label>Property:</label>
                <select class="prop-select" onchange="location.href='?property_id='+this.value">
                    <?php foreach ($properties as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $current_property_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" onclick="location.href='generate_bills.php?property_id=<?php echo $current_property_id; ?>'"><i class="fas fa-sync"></i> Generate Rent Bills</button>
            </div>

            <?php
            $total_billed = 0;
            $total_paid = 0;
            $total_pending = 0;
            foreach ($bills as $b) {
                $total_billed += $b['total_amount'];
                $total_pending += $b['total_balance'];
                $total_paid += ($b['total_amount'] - $b['total_balance']);
            }
            ?>

            <?php if (!isset($_GET['tab']) || $_GET['tab'] == 'overview'): ?>
            <div class="billing-card">
                <div class="math-summary">
                    <div class="math-item">
                        <h4>Total Billed</h4>
                        <p>KES <?php echo number_format($total_billed); ?></p>
                    </div>
                    <div class="math-item">
                        <h4>Total Received</h4>
                        <p class="status-paid" style="background:transparent;">KES <?php echo number_format($total_paid); ?></p>
                    </div>
                    <div class="math-item">
                        <h4>Total Outstanding</h4>
                        <p class="status-unpaid" style="background:transparent;">KES <?php echo number_format($total_pending); ?></p>
                    </div>
                    <div class="math-item">
                        <h4>Collection Rate</h4>
                        <p><?php echo $total_billed > 0 ? round(($total_paid / $total_billed) * 100, 1) : 0; ?>%</p>
                    </div>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Tenant / House</th>
                            <th>Bills</th>
                            <th>Month</th>
                            <th>Total Billed</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bills) > 0): ?>
                            <?php foreach ($bills as $b): ?>
                                <?php
                                $status = $b['total_balance'] <= 0 ? 'paid' : ($b['total_balance'] < $b['total_amount'] ? 'partial' : 'unpaid');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($b['tenant_name']); ?></strong><br>
                                        <small style="color: var(--gray);"><?php echo htmlspecialchars($b['unit_number']); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $types = explode(',', $b['bill_types']);
                                        foreach ($types as $type): ?>
                                            <span style="text-transform: capitalize; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; background: #f1f5f9; margin-right: 3px; display: inline-block; margin-bottom: 2px;">
                                                <?php echo htmlspecialchars(trim($type)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo $b['month'] . ' ' . $b['year']; ?></td>
                                    <td>KES <?php echo number_format($b['total_amount']); ?></td>
                                    <td style="font-weight: 700; color: <?php echo $b['total_balance'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>">
                                        KES <?php echo number_format($b['total_balance']); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $status; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td style="display: flex; gap: 5px;">
                                        <?php if ($status !== 'paid'): ?>
                                        <button class="btn btn-primary btn-sm" onclick="openPayModal(<?php echo $b['tenant_id']; ?>, '<?php echo addslashes($b['tenant_name']); ?>', <?php echo $b['total_balance']; ?>, '<?php echo $b['month']; ?>', '<?php echo $b['year']; ?>', '<?php echo str_replace(',', ', ', $b['bill_types']); ?>')" title="Record Payment">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </button>
                                        <?php else: ?>
                                        <span style="font-size: 11px; color: var(--success); font-weight: 600;"><i class="fas fa-check-circle"></i> Cleared</span>
                                        <?php endif; ?>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="tenant_id" value="<?php echo $b['tenant_id']; ?>">
                                            <input type="hidden" name="property_id" value="<?php echo $current_property_id; ?>">
                                            <button type="submit" name="send_invoice" class="btn btn-outline btn-sm" title="Send SMS Invoice"><i class="fas fa-paper-plane"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--gray);">No bills found for this property.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <!-- Readings Tab -->
            <div class="billing-card">
                <h3>Enter Water Readings - <?php echo date('F Y'); ?></h3>
                <p style="font-size: 14px; color: var(--gray); margin-bottom: 25px;">Enter the current meter reading for each unit. The system will calculate usage automatically.</p>
                
                <form method="POST">
                    <input type="hidden" name="property_id" value="<?php echo $current_property_id; ?>">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Unit Number</th>
                                <th>Tenant</th>
                                <th>Previous Reading</th>
                                <th>Current Reading</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $uStmt = $pdo->prepare("
                                SELECT u.id, u.unit_number, t.name as tenant_name, 
                                (SELECT reading_curr FROM bills WHERE unit_id = u.id AND bill_type = 'water' ORDER BY id DESC LIMIT 1) as last_reading
                                FROM units u 
                                LEFT JOIN tenants t ON u.id = t.unit_id AND t.status = 'active'
                                WHERE u.property_id = ?
                            ");
                            $uStmt->execute([$current_property_id]);
                            $units = $uStmt->fetchAll();
                            foreach ($units as $u):
                            ?>
                            <tr>
                                <td><strong><?php echo $u['unit_number']; ?></strong></td>
                                <td><?php echo $u['tenant_name'] ?: '<span style="color:#ccc;">Vacant</span>'; ?></td>
                                <td><?php echo $u['last_reading'] ?: 0; ?></td>
                                <td>
                                    <input type="number" step="0.01" name="readings[<?php echo $u['id']; ?>]" class="form-control" style="width: 150px;" placeholder="Enter new...">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 25px; text-align: right;">
                        <button type="submit" name="save_readings" class="btn btn-primary">Save Readings & Calculate Bills</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="customBillModal">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 20px; width: 450px; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
            <h3>Create Custom Bill</h3>
            <form action="save_bill.php" method="POST" style="margin-top: 20px;">
                <div class="form-group">
                    <label>Select Tenant</label>
                    <select name="tenant_id" class="form-control" required>
                        <?php foreach ($property_tenants as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bill Type</label>
                    <select name="bill_type" class="form-control">
                        <option value="water">Water</option>
                        <option value="wifi">WiFi</option>
                        <option value="garbage">Garbage</option>
                        <option value="penalty">Penalty</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (KES)</label>
                    <input type="number" name="amount" class="form-control" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="button" class="btn btn-outline" style="flex: 1; background:#f8f9fa; border:1px solid #ddd;" onclick="closeModal('customBillModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Create Bill</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment / Adjust Modal -->
    <div class="modal" id="adjustModal">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 20px; width: 500px; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
            <h3>Record Payment / Adjustment</h3>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="adjust_tenant_id" id="adjustTenantId">
                <input type="hidden" name="adjust_month" id="adjustMonth">
                <input type="hidden" name="adjust_year" id="adjustYear">
                <input type="hidden" name="redirect_property_id" value="<?php echo $current_property_id; ?>">
                <div class="form-group">
                    <label>Tenant: <span id="adjustTenantName" style="font-weight:700; color: var(--dark);"></span></label>
                </div>
                <div class="form-group">
                    <label>Period: <span id="adjustPeriod" style="font-weight:600; color: var(--primary);"></span></label>
                </div>
                <div class="form-group">
                    <label>Bills: <span id="adjustBillTypes" style="font-size:12px; color: var(--gray);"></span></label>
                </div>
                <div class="form-group">
                    <label>Total Balance Due: <span id="currentBalance" style="font-weight:700; color: var(--danger);"></span></label>
                </div>
                <div class="form-group">
                    <label>Adjustment Type</label>
                    <select name="adjustment_type" class="form-control" required>
                        <option value="payment">Payment Received</option>
                        <option value="credit">Add Credit</option>
                        <option value="penalty">Add Penalty</option>
                        <option value="discount">Apply Discount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (KES)</label>
                    <input type="number" step="0.01" name="adjustment_amount" id="adjustAmount" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes / Reference (Optional)</label>
                    <textarea name="adjustment_notes" class="form-control" rows="2" placeholder="e.g. M-Pesa ref: QWE123..."></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="button" class="btn btn-outline" style="flex: 1; background:#f8f9fa; border:1px solid #ddd;" onclick="closeAdjustModal()">Cancel</button>
                    <button type="submit" name="apply_adjustment" class="btn btn-primary" style="flex: 1;">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function openCustomBillModal() { 
            document.getElementById('customBillModal').style.display = 'flex'; 
        }

        function openPayModal(tenantId, tenantName, balance, month, year, billTypes) {
            document.getElementById('adjustTenantId').value = tenantId;
            document.getElementById('adjustMonth').value = month;
            document.getElementById('adjustYear').value = year;
            document.getElementById('adjustTenantName').textContent = tenantName;
            document.getElementById('adjustPeriod').textContent = month + ' ' + year;
            document.getElementById('adjustBillTypes').textContent = billTypes;
            document.getElementById('currentBalance').textContent = 'KES ' + Number(balance).toLocaleString();
            document.getElementById('adjustAmount').value = balance;
            document.getElementById('adjustModal').style.display = 'flex';
        }

        function closeAdjustModal() {
            document.getElementById('adjustModal').style.display = 'none';
        }

        // Close modals on background click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>

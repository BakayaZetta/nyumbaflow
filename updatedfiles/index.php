<?php
require_once 'db_config.php';
require_once 'session_check.php';
requireLogin();
require_once 'sanitize.php';
require_once 'automation_trigger.php';

require_once 'SmsService.php';

$landlord_id = $_SESSION['admin_id'];
$sms = new SmsService(); // Credentials would be loaded from DB/Config in production

// Initialize messages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Fetch Properties for the selector
$stmt = $pdo->prepare("SELECT * FROM properties WHERE landlord_id = ?");
$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll();

$current_property_id = (int)($_GET['property_id'] ?? ($properties[0]['id'] ?? 0));

// Auto-generate bills for the current month if they don't exist
$month = date('F');
$year = date('Y');
foreach ($properties as $prop) {
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bills b JOIN units u ON b.unit_id = u.id WHERE u.property_id = ? AND b.month = ? AND b.year = ? AND b.bill_type = 'rent'");
    $checkStmt->execute([$prop['id'], $month, $year]);
    if ($checkStmt->fetchColumn() == 0) {
        // Trigger generation if today is 1st or later and bills are missing
    }
}

// Redirect to onboarding if no properties exist
if (!$current_property_id) {
    header("Location: onboarding.html");
    exit();
}

// Fetch Summary Stats for the selected property
$stmt = $pdo->prepare("
    SELECT 
        SUM(balance) as total_pending,
        (SELECT COUNT(*) FROM units WHERE property_id = ?) as total_units,
        (SELECT COUNT(*) FROM tenants WHERE property_id = ? AND status = 'active') as total_tenants
    FROM bills b
    JOIN units u ON b.unit_id = u.id
    WHERE u.property_id = ? AND b.status != 'paid'
");
$stmt->execute([$current_property_id, $current_property_id, $current_property_id]);
$stats = $stmt->fetch();

// Fetch Today's Visitor Count
$v_today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE property_id = ? AND visit_date = ?");
$stmt->execute([$current_property_id, $v_today]);
$visitors_today = $stmt->fetchColumn();

// Fetch Recent Visitors (last 5)
$stmt = $pdo->prepare("
    SELECT v.*, u.unit_number 
    FROM visitors v 
    LEFT JOIN units u ON v.unit_id = u.id
    WHERE v.property_id = ? 
    ORDER BY v.visit_date DESC, v.time_in DESC 
    LIMIT 5
");
$stmt->execute([$current_property_id]);
$recent_visitors = $stmt->fetchAll();


// Fetch Active Bills for the grid/table
$stmt = $pdo->prepare("
    SELECT 
        u.unit_number, 
        t.name as tenant_name, 
        t.phone_number,
        b.id as bill_id,
        b.amount, 
        b.balance, 
        b.status, 
        b.month,
        b.bill_type
    FROM bills b
    JOIN units u ON b.unit_id = u.id
    JOIN tenants t ON t.unit_id = u.id AND t.status = 'active'
    WHERE u.property_id = ? AND b.balance > 0 AND b.month = ? AND b.year = ?
    ORDER BY u.unit_number ASC
");
// For simplicity, showing current month/year
$stmt->execute([$current_property_id, date('F'), date('Y')]);
$units_bills = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Nyumbaflow</title>
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

        /* Main Content */
        .main { flex: 1; padding: 30px; overflow-y: auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .prop-selector { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .prop-pill { padding: 10px 20px; border-radius: 50px; background: white; color: var(--gray); font-weight: 500; cursor: pointer; transition: var(--transition); text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .prop-pill.active { background: var(--primary); color: white; }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-info h3 { font-size: 14px; color: var(--gray); font-weight: 500; }
        .stat-info p { font-size: 24px; font-weight: 700; margin-top: 5px; }

        /* Billing Table */
        .section-card { background: white; border-radius: 20px; padding: 30px; box-shadow: var(--shadow); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 15px; color: var(--gray); font-size: 14px; font-weight: 600; border-bottom: 1px solid #f1f5f9; }
        .table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .status-badge { padding: 5px 12px; border-radius: 50px; font-size: 12px; font-weight: 600; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-unpaid { background: #fee2e2; color: #991b1b; }
        .status-partial { background: #fef3c7; color: #92400e; }

        .btn-pay { width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--primary); color: var(--primary); background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .btn-pay:hover { background: var(--primary); color: white; }

        /* Modal */
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 400px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; }
        
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="top-bar">
            <h1>Dashboard</h1>
            <div class="user-info" style="display: flex; gap: 15px; align-items: center;">
                <div style="text-align: right;">
                    <p style="font-weight: 600;"><?php echo esc($_SESSION['admin_name']); ?></p>
                    <p style="font-size: 12px; color: var(--gray);">Landlord</p>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    <?php echo esc(substr($_SESSION['admin_name'], 0, 1)); ?>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo esc($message_type); ?>"><?php echo esc($message); ?></div>
        <?php endif; ?>

        <div class="prop-selector">
            <?php foreach ($properties as $p): ?>
                <a href="?property_id=<?php echo $p['id']; ?>" class="prop-pill <?php echo $p['id'] == $current_property_id ? 'active' : ''; ?>">
                    <?php echo esc($p['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Monthly Utility Notice -->
        <?php if (date('j') <= 5): ?>
        <div style="background: var(--warning); color: white; padding: 15px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong><i class="fas fa-exclamation-triangle"></i> Utility Reading Phase</strong>
                <p style="font-size: 14px; opacity: 0.9;">It's the beginning of the month. Please enter water readings before generating major bills.</p>
            </div>
            <a href="billing.php?property_id=<?php echo $current_property_id; ?>&tab=readings" class="btn" style="background: white; color: var(--warning);">Enter Readings</a>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1); color: var(--primary);"><i class="fas fa-home"></i></div>
                <div class="stat-info">
                    <h3>Total Units</h3>
                    <p><?php echo $stats['total_units'] ?? 0; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(46, 196, 182, 0.1); color: var(--success);"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3>Occupied Units</h3>
                    <p><?php echo $stats['total_tenants'] ?? 0; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(231, 29, 54, 0.1); color: var(--danger);"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3>Outstanding Dues</h3>
                    <p>KES <?php echo number_format($stats['total_pending'] ?? 0); ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1); color: #0ea5e9;"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3>Today's Visitors</h3>
                    <p><?php echo $visitors_today; ?></p>
                </div>
            </div>

        </div>

        <!-- Quick Actions -->
        <div style="margin-bottom: 30px;">
            <h2 style="font-size: 18px; margin-bottom: 20px;">Quick Actions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="tenants.php" class="btn prop-pill active" style="justify-content: center;"><i class="fas fa-user-plus"></i> Add New Tenant</a>
                <a href="notifications.php" class="btn prop-pill" style="background: white; border: 1px solid #ddd; justify-content: center;"><i class="fas fa-bullhorn"></i> Send Bulk Notice</a>
                <a href="settings.php" class="btn prop-pill" style="background: white; border: 1px solid #ddd; justify-content: center;"><i class="fas fa-tint"></i> Update Rates</a>
                <button class="btn prop-pill" style="background: white; border: 1px solid #ddd; justify-content: center;" onclick="location.href='generate_bills.php?property_id=<?php echo $current_property_id; ?>'"><i class="fas fa-sync"></i> Generate Bills</button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; margin-top: 30px; align-items: start;">
            <!-- Billing Overview -->
            <div class="section-card">
                <div class="card-header">
                    <h2>Billing Overview</h2>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn prop-pill active" onclick="location.href='generate_bills.php?property_id=<?php echo $current_property_id; ?>'"><i class="fas fa-plus"></i> Generate Bills</button>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>House</th>
                                <th>Tenant</th>
                                <th>Bill Type</th>
                                <th>Total</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units_bills as $item): ?>
                                <tr>
                                    <td><strong><?php echo esc($item['unit_number']); ?></strong></td>
                                    <td><?php echo $item['tenant_name'] ? esc($item['tenant_name']) : '<span style="color: #cbd5e1;">Vacant</span>'; ?></td>
                                    <td><?php echo esc($item['bill_type'] ?? '-'); ?></td>
                                    <td>KES <?php echo number_format($item['amount'] ?? 0); ?></td>
                                    <td style="color: <?php echo ($item['balance'] > 0) ? 'var(--danger)' : 'var(--success)'; ?>">
                                        KES <?php echo number_format($item['balance'] ?? 0); ?>
                                    </td>
                                    <td>
                                        <?php if ($item['status']): ?>
                                            <span class="status-badge status-<?php echo $item['status']; ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Visitors -->
            <div class="section-card">
                <div class="card-header">
                    <h2>Recent Visitors</h2>
                    <a href="visitors.php" style="font-size: 13px; color: var(--primary); text-decoration: none;">View All</a>
                </div>
                <div class="visitor-list">
                    <?php if ($recent_visitors): ?>
                        <?php foreach ($recent_visitors as $rv): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                <div>
                                    <p style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($rv['name']); ?></p>
                                    <p style="font-size: 12px; color: var(--gray);">House: <?php echo htmlspecialchars($rv['unit_number'] ?? 'N/A'); ?> • <?php echo date('g:i A', strtotime($rv['time_in'])); ?></p>
                                </div>
                                <?php if ($rv['time_out']): ?>
                                    <span style="font-size: 10px; padding: 3px 8px; background: #f1f5f9; border-radius: 4px; color: var(--gray);">Out</span>
                                <?php else: ?>
                                    <span style="font-size: 10px; padding: 3px 8px; background: #dcfce7; border-radius: 4px; color: #166534;">In</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--gray); font-size: 14px; padding: 20px;">No recent visitors.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <script>
        // No modals anymore
    </script>
</body>
</html>
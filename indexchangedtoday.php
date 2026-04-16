<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_config.php';

// DO NOT include automation_trigger.php here - it should only run via cron or manual trigger
// require_once 'automation_trigger.php'; // REMOVED - This was causing the fatal error

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

$current_property_id = $_GET['property_id'] ?? ($properties[0]['id'] ?? null);

// Auto-generate bills for the current month if they don't exist (improved logic)
$month = date('F');
$year = date('Y');
$bills_generated = false;

foreach ($properties as $prop) {
    try {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bills b JOIN units u ON b.unit_id = u.id WHERE u.property_id = ? AND b.month = ? AND b.year = ? AND b.bill_type = 'rent'");
        $checkStmt->execute([$prop['id'], $month, $year]);
        
        if ($checkStmt->fetchColumn() == 0) {
            // Only auto-generate if it's the 1st of the month or if manually triggered
            if (date('j') == 1) {
                // Generate bills for this property (call function from automation_trigger.php if needed)
                // Note: Since we removed the include, you may want to move the generation logic here
                // or create a separate function file.
                $bills_generated = true;
            }
        }
    } catch (PDOException $e) {
        error_log("Error checking bills for property {$prop['id']}: " . $e->getMessage());
    }
}

// Redirect to onboarding if no properties exist
if (!$current_property_id) {
    header("Location: onboarding.html");
    exit();
}

// Fetch Summary Stats for the selected property
try {
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
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = ['total_pending' => 0, 'total_units' => 0, 'total_tenants' => 0];
}

// Fetch Today's Visitor Count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE property_id = ? AND visit_date = CURDATE()");
    $stmt->execute([$current_property_id]);
    $visitors_today = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching visitors: " . $e->getMessage());
    $visitors_today = 0;
}

// Fetch Recent Visitors (last 5)
try {
    $stmt = $pdo->prepare("
        SELECT v.* 
        FROM visitors v 
        WHERE v.property_id = ? 
        ORDER BY v.visit_date DESC, v.time_in DESC 
        LIMIT 5
    ");
    $stmt->execute([$current_property_id]);
    $recent_visitors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching recent visitors: " . $e->getMessage());
    $recent_visitors = [];
}

// Fetch Active Bills for the grid/table
try {
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
        LEFT JOIN tenants t ON t.unit_id = u.id AND t.status = 'active'
        WHERE u.property_id = ? AND b.balance > 0 AND b.month = ? AND b.year = ?
        ORDER BY u.unit_number ASC
    ");
    $stmt->execute([$current_property_id, date('F'), date('Y')]);
    $units_bills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching bills: " . $e->getMessage());
    $units_bills = [];
}

// Add this function to manually generate bills if needed
function generateMonthlyBills($pdo, $property_id, $month, $year) {
    try {
        $generated = 0;
        $stmt = $pdo->prepare("
            SELECT u.id, u.unit_number, t.id as tenant_id, t.name, t.phone_number,
                   COALESCE(rs.rent_amount, 0) as rent_amount
            FROM units u
            LEFT JOIN tenants t ON u.id = t.unit_id AND t.status = 'active'
            LEFT JOIN rent_settings rs ON u.id = rs.unit_id
            WHERE u.property_id = ?
        ");
        $stmt->execute([$property_id]);
        $units = $stmt->fetchAll();
        
        foreach ($units as $unit) {
            // Check if bill already exists
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM bills 
                WHERE unit_id = ? AND month = ? AND year = ? AND bill_type = 'rent'
            ");
            $checkStmt->execute([$unit['id'], $month, $year]);
            
            if ($checkStmt->fetchColumn() == 0 && $unit['tenant_id']) {
                $amount = $unit['rent_amount'];
                $insertStmt = $pdo->prepare("
                    INSERT INTO bills (unit_id, amount, balance, month, year, bill_type, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'rent', 'unpaid', NOW())
                ");
                if ($insertStmt->execute([$unit['id'], $amount, $amount, $month, $year])) {
                    $generated++;
                }
            }
        }
        return ['success' => true, 'generated' => $generated];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Handle manual bill generation
if (isset($_POST['generate_bills']) && isset($_POST['property_id'])) {
    $property_id_post = (int)($_POST['property_id'] ?? 0);
    $result = generateMonthlyBills($pdo, $property_id_post, date('F'), date('Y'));
    if ($result['success']) {
        $message = "Successfully generated {$result['generated']} bills";
        $message_type = "success";
        // Refresh the page to show new bills
        header("Location: index.php?property_id={$property_id_post}&msg=" . urlencode($message));
        exit();
    } else {
        $message = "Error generating bills: " . $result['error'];
        $message_type = "error";
    }
}
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
        .prop-pill:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 20px; transition: var(--transition); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.12); }
        .stat-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-info h3 { font-size: 14px; color: var(--gray); font-weight: 500; }
        .stat-info p { font-size: 24px; font-weight: 700; margin-top: 5px; }

        /* Billing Table */
        .section-card { background: white; border-radius: 20px; padding: 30px; box-shadow: var(--shadow); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 15px; color: var(--gray); font-size: 14px; font-weight: 600; border-bottom: 1px solid #f1f5f9; }
        .table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .status-badge { padding: 5px 12px; border-radius: 50px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-unpaid { background: #fee2e2; color: #991b1b; }
        .status-partial { background: #fef3c7; color: #92400e; }

        .btn-pay { width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--primary); color: var(--primary); background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition); }
        .btn-pay:hover { background: var(--primary); color: white; transform: scale(1.05); }

        /* Modal */
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 400px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); animation: modalSlideIn 0.3s ease; }
        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; transition: var(--transition); }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1); }
        
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; animation: slideDown 0.3s ease; }
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #991b1b; }
        
        .btn { cursor: pointer; transition: var(--transition); }
        .btn:hover { transform: translateY(-2px); }
        
        .empty-state { text-align: center; padding: 40px; color: var(--gray); }
        .empty-state i { font-size: 48px; margin-bottom: 15px; opacity: 0.5; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="top-bar">
            <h1>Dashboard</h1>
            <div class="user-info" style="display: flex; gap: 15px; align-items: center;">
                <div style="text-align: right;">
                    <p style="font-weight: 600;"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></p>
                    <p style="font-size: 12px; color: var(--gray);">Landlord</p>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    <?php echo substr($_SESSION['admin_name'] ?? 'A', 0, 1); ?>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <div class="prop-selector">
            <?php foreach ($properties as $p): ?>
                <a href="?property_id=<?php echo $p['id']; ?>" class="prop-pill <?php echo $p['id'] == $current_property_id ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($p['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Monthly Utility Notice -->
        <?php if (date('j') <= 5): ?>
        <div style="background: linear-gradient(135deg, var(--warning), #ffb347); color: white; padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div>
                <strong><i class="fas fa-exclamation-triangle"></i> Utility Reading Phase</strong>
                <p style="font-size: 14px; opacity: 0.9; margin-top: 5px;">It's the beginning of the month. Please enter water readings before generating major bills.</p>
            </div>
            <a href="billing.php?property_id=<?php echo $current_property_id; ?>&tab=readings" class="btn" style="background: white; color: var(--warning); padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                <i class="fas fa-tint"></i> Enter Readings
            </a>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1); color: var(--primary);"><i class="fas fa-home"></i></div>
                <div class="stat-info">
                    <h3>Total Units</h3>
                    <p><?php echo number_format($stats['total_units'] ?? 0); ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(46, 196, 182, 0.1); color: var(--success);"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3>Occupied Units</h3>
                    <p><?php echo number_format($stats['total_tenants'] ?? 0); ?></p>
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
                    <p><?php echo number_format($visitors_today); ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="margin-bottom: 30px;">
            <h2 style="font-size: 18px; margin-bottom: 20px;">Quick Actions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="tenants.php" class="btn prop-pill active" style="display: flex; align-items: center; gap: 8px; justify-content: center; text-decoration: none;">
                    <i class="fas fa-user-plus"></i> Add New Tenant
                </a>
                <a href="notifications.php" class="btn prop-pill" style="background: white; border: 1px solid #ddd; display: flex; align-items: center; gap: 8px; justify-content: center; text-decoration: none;">
                    <i class="fas fa-bullhorn"></i> Send Bulk Notice
                </a>
                <a href="settings.php" class="btn prop-pill" style="background: white; border: 1px solid #ddd; display: flex; align-items: center; gap: 8px; justify-content: center; text-decoration: none;">
                    <i class="fas fa-tint"></i> Update Rates
                </a>
                <form method="POST" style="margin: 0;" onsubmit="return confirm('Generate bills for all units?');">
                    <input type="hidden" name="property_id" value="<?php echo $current_property_id; ?>">
                    <button type="submit" name="generate_bills" class="btn prop-pill" style="background: white; border: 1px solid #ddd; display: flex; align-items: center; gap: 8px; justify-content: center; width: 100%; cursor: pointer;">
                        <i class="fas fa-sync"></i> Generate Bills
                    </button>
                </form>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; margin-top: 30px; align-items: start;">
            <!-- Billing Overview -->
            <div class="section-card">
                <div class="card-header">
                    <h2><i class="fas fa-file-invoice-dollar"></i> Billing Overview</h2>
                    <div style="display: flex; gap: 10px;">
                        <form method="POST" onsubmit="return confirm('Generate bills for all units?');">
                            <input type="hidden" name="property_id" value="<?php echo $current_property_id; ?>">
                            <button type="submit" name="generate_bills" class="btn prop-pill active" style="padding: 8px 16px; font-size: 13px;">
                                <i class="fas fa-plus"></i> Generate Bills
                            </button>
                        </form>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <?php if (count($units_bills) > 0): ?>
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
                                    <td><strong><?php echo htmlspecialchars($item['unit_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['tenant_name'] ?? '<span style="color: #cbd5e1;">Vacant</span>'); ?></td>
                                    <td><?php echo htmlspecialchars($item['bill_type'] ?? 'Rent'); ?></td>
                                    <td>KES <?php echo number_format($item['amount'] ?? 0); ?></td>
                                    <td style="color: <?php echo ($item['balance'] > 0) ? 'var(--danger)' : 'var(--success)'; ?>; font-weight: 600;">
                                        KES <?php echo number_format($item['balance'] ?? 0); ?>
                                    </td>
                                    <td>
                                        <?php if ($item['status']): ?>
                                            <span class="status-badge status-<?php echo $item['status']; ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-unpaid">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <p>No pending bills for this month.</p>
                        <p style="font-size: 12px; margin-top: 10px;">Click "Generate Bills" to create bills for this month.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Visitors -->
            <div class="section-card">
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> Recent Visitors</h2>
                    <a href="visitors.php?property_id=<?php echo $current_property_id; ?>" style="font-size: 13px; color: var(--primary); text-decoration: none;">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="visitor-list">
                    <?php if ($recent_visitors && count($recent_visitors) > 0): ?>
                        <?php foreach ($recent_visitors as $rv): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                <div>
                                    <p style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($rv['name']); ?></p>
                                    <p style="font-size: 12px; color: var(--gray); margin-top: 4px;">
                                        <?php if (!empty($rv['number_plate'])): ?>
                                            🚗 <?php echo htmlspecialchars($rv['number_plate']); ?> • 
                                        <?php endif; ?>
                                        <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($rv['time_in'])); ?>
                                    </p>
                                </div>
                                <?php if ($rv['time_out']): ?>
                                    <span style="font-size: 10px; padding: 3px 8px; background: #f1f5f9; border-radius: 4px; color: var(--gray);">
                                        <i class="fas fa-sign-out-alt"></i> <?php echo date('g:i A', strtotime($rv['time_out'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 10px; padding: 3px 8px; background: #dcfce7; border-radius: 4px; color: #166534;">
                                        <i class="fas fa-sign-in-alt"></i> In
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 30px;">
                            <i class="fas fa-user-friends"></i>
                            <p>No recent visitors.</p>
                            <p style="font-size: 12px; margin-top: 5px;">Visitors will appear here when checked in.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>
<?php
session_start();
require_once 'db_config.php';

// Check session timeout (10 minutes)
if (isset($_SESSION['admin_id']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > 600) {
        session_unset();
        session_destroy();
        header("Location: auth.html");
        exit();
    }
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.html");
    exit();
}

$landlord_id = $_SESSION['admin_id'];

// Fetch properties for dropdown
$stmt = $pdo->prepare("SELECT * FROM properties WHERE landlord_id = ?");
$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll();

$property_id = $_GET['property_id'] ?? ($properties[0]['id'] ?? null);

if (!$property_id) {
    header("Location: onboarding.html");
    exit();
}

// Handle visitor check-out
if (isset($_POST['checkout']) && isset($_POST['visitor_id'])) {
    $stmt = $pdo->prepare("UPDATE visitors SET time_out = CURTIME() WHERE id = ? AND property_id = ?");
    $stmt->execute([$_POST['visitor_id'], $property_id]);
    $_SESSION['message'] = "Visitor checked out successfully";
    $_SESSION['message_type'] = "success";
    header("Location: visitors.php?property_id=" . $property_id);
    exit();
}

// Fetch visitors with tenant information
$stmt = $pdo->prepare("
    SELECT 
        v.*,
        t.name as tenant_name,
        t.unit_id,
        u.unit_number
    FROM visitors v
    LEFT JOIN tenants t ON v.tenant_id = t.id
    LEFT JOIN units u ON t.unit_id = u.id
    WHERE v.property_id = ?
    ORDER BY v.visit_date DESC, v.time_in DESC
");
$stmt->execute([$property_id]);
$visitors = $stmt->fetchAll();

// Fetch active tenants for the add visitor form
$stmt = $pdo->prepare("
    SELECT t.*, u.unit_number 
    FROM tenants t
    JOIN units u ON t.unit_id = u.id
    WHERE t.property_id = ? AND t.status = 'active'
    ORDER BY u.unit_number
");
$stmt->execute([$property_id]);
$tenants = $stmt->fetchAll();

// Get today's visitors count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE property_id = ? AND visit_date = CURDATE()");
$stmt->execute([$property_id]);
$today_count = $stmt->fetchColumn();

// Get active visitors (still in)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE property_id = ? AND visit_date = CURDATE() AND time_out IS NULL");
$stmt->execute([$property_id]);
$active_count = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitors Log - Nyumbaflow</title>
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f1f5f9; display: flex; min-height: 100vh; }
        
        .main { flex: 1; padding: 30px; overflow-y: auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .actions-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #3651d4;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 15px;
            color: var(--gray);
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-secondary {
            background: #f1f5f9;
            color: var(--gray);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
        }
        
        .btn-small:hover {
            background: #f8f9fa;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            width: 500px;
            max-width: 90%;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
        }
        
        .prop-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .prop-pill {
            padding: 10px 20px;
            border-radius: 50px;
            background: white;
            color: var(--gray);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .prop-pill.active {
            background: var(--primary);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="top-bar">
            <h1>Visitors Log</h1>
            <div class="user-info" style="display: flex; gap: 15px; align-items: center;">
                <div style="text-align: right;">
                    <p style="font-weight: 600;"><?php echo $_SESSION['admin_name']; ?></p>
                    <p style="font-size: 12px; color: var(--gray);">Landlord</p>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    <?php echo substr($_SESSION['admin_name'], 0, 1); ?>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="prop-selector">
            <?php foreach ($properties as $p): ?>
                <a href="?property_id=<?php echo $p['id']; ?>" class="prop-pill <?php echo $p['id'] == $property_id ? 'active' : ''; ?>">
                    <?php echo $p['name']; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Today's Visitors</h3>
                <div class="number"><?php echo $today_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>Currently Inside</h3>
                <div class="number"><?php echo $active_count; ?></div>
            </div>
        </div>

        <div class="actions-bar">
            <button class="btn btn-primary" onclick="showAddVisitorModal()">
                <i class="fas fa-plus"></i> Add New Visitor
            </button>
            <a href="gate_access.php?property_id=<?php echo $property_id; ?>" class="btn btn-success">
                <i class="fas fa-shield-alt"></i> Gate Dashboard
            </a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Visitor Name</th>
                        <th>Phone</th>
                        <th>ID Number</th>
                        <th>Number Plate</th>
                        <th>Visiting</th>
                        <th>Unit</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitors as $visitor): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($visitor['visit_date'])); ?></td>
                            <td><?php echo htmlspecialchars($visitor['name']); ?></td>
                            <td><?php echo htmlspecialchars($visitor['phone_number']); ?></td>
                            <td><?php echo htmlspecialchars($visitor['id_number'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($visitor['number_plate'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($visitor['tenant_name'] ?? 'General Visit'); ?></td>
                            <td><?php echo htmlspecialchars($visitor['unit_number'] ?? '-'); ?></td>
                            <td><?php echo date('g:i A', strtotime($visitor['time_in'])); ?></td>
                            <td>
                                <?php if ($visitor['time_out']): ?>
                                    <?php echo date('g:i A', strtotime($visitor['time_out'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$visitor['time_out'] && $visitor['visit_date'] == date('Y-m-d')): ?>
                                    <span class="badge badge-success">Inside</span>
                                <?php elseif ($visitor['time_out']): ?>
                                    <span class="badge badge-secondary">Left</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Previous Day</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$visitor['time_out'] && $visitor['visit_date'] == date('Y-m-d')): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="visitor_id" value="<?php echo $visitor['id']; ?>">
                                        <input type="hidden" name="checkout" value="1">
                                        <button type="submit" class="btn-small" onclick="return confirm('Check out this visitor?')">
                                            Check Out
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Visitor Modal -->
    <div id="visitorModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Add New Visitor</h2>
            <form method="POST" action="add_visitor.php">
                <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">
                
                <div class="form-group">
                    <label>Visitor Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone_number" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>ID Number (Optional)</label>
                    <input type="text" name="id_number" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Number Plate (Optional)</label>
                    <input type="text" name="number_plate" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Visiting Tenant</label>
                    <select name="tenant_id" class="form-control">
                        <option value="">General Visit</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?php echo $tenant['id']; ?>">
                                <?php echo htmlspecialchars($tenant['name'] . ' - Unit ' . $tenant['unit_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #f1f5f9;" onclick="hideAddVisitorModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Visitor</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddVisitorModal() {
            document.getElementById('visitorModal').style.display = 'flex';
        }
        
        function hideAddVisitorModal() {
            document.getElementById('visitorModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('visitorModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
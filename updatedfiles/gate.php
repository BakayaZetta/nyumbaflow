<?php
require_once 'db_config.php';
session_start();
require_once 'sanitize.php';
require_once 'SmsService.php';

$sms = new SmsService(); // Credentials should be configured in SmsService.php or env

if (!isset($_SESSION['personnel_id']) || $_SESSION['personnel_role'] !== 'gate') {
    header("Location: personnel_login.php");
    exit();
}

$property_id = $_SESSION['personnel_property_id'];

$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$property_id]);
$property = $stmt->fetch();

if (!$property) {
    die("<h1>Error</h1><p>Assigned property not found.</p>");
}

// Handle Visitor Log Submission (Entry)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_visitor'])) {
    $name = $_POST['v_name'];
    $phone = $_POST['v_phone'];
    $id_number = $_POST['v_id_number'] ?? '';
    $plate = $_POST['v_plate'] ?? '';
    $unit_id = $_POST['unit_id'];
    $id_image = "";

    if (isset($_FILES['id_image']) && $_FILES['id_image']['error'] === 0) {
        $ext = pathinfo($_FILES['id_image']['name'], PATHINFO_EXTENSION);
        $id_image = 'unit_' . $unit_id . '_id_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['id_image']['tmp_name'], 'uploads/visitors/' . $id_image);
    }
    
    // Get tenant and unit info
    $stmt = $pdo->prepare("
        SELECT t.id as tenant_id, t.name as tenant_name, t.phone_number as tenant_phone, u.unit_number 
        FROM units u 
        LEFT JOIN tenants t ON t.unit_id = u.id AND t.status = 'active'
        WHERE u.id = ?
    ");
    $stmt->execute([$unit_id]);
    $data = $stmt->fetch();
    
    $v_type = $_POST['v_type'] ?? 'guest';
    $v_purpose = $_POST['v_purpose'] ?? '';
    
    $v_date = date('Y-m-d');
    $v_time = date('H:i:s');
    
    $stmt = $pdo->prepare("INSERT INTO visitors (property_id, tenant_id, unit_id, name, id_number, phone_number, number_plate, id_image, visitor_type, purpose, visit_date, time_in) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$property['id'], $data['tenant_id'] ?? null, $unit_id, $name, $id_number, $phone, $plate, $id_image, $v_type, $v_purpose, $v_date, $v_time]);


    
    // Send SMS Notifications
    if ($data) {
        $propName = $property['name'];
        $unitNum = $data['unit_number'];
        $tenantName = $data['tenant_name'] ?? 'Vacant Unit';
        $vMsg = "HI $name you have visited $propName to $unitNum, visiting $tenantName.";
        $sms->sendSms($phone, $vMsg);
        
        if ($data['tenant_phone']) {
            $tMsg = "Hello $tenantName, you have a visitor $name at your house.";
            $sms->sendSms($data['tenant_phone'], $tMsg);
        }
    }
    
    $success = "Visitor logged successfully!";
}

// Handle Visitor Logout (Exit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout_visitor'])) {
    $visitor_id = $_POST['visitor_id'];
    $v_time_out = date('H:i:s');
    $stmt = $pdo->prepare("UPDATE visitors SET time_out = ? WHERE id = ?");
    $stmt->execute([$v_time_out, $visitor_id]);
    $success = "Visitor logged out successfully!";
}

// Fetch checked-in visitors (no time_out yet)
$v_today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT v.*, u.unit_number 
    FROM visitors v 
    LEFT JOIN units u ON v.unit_id = u.id
    WHERE v.property_id = ? AND v.time_out IS NULL AND v.visit_date = ?
    ORDER BY v.time_in DESC
");

$stmt->execute([$property['id'], $v_today]);
$active_visitors = $stmt->fetchAll();

// Fetch all units for the property
$stmt = $pdo->prepare("SELECT u.unit_number, u.id as unit_id, t.name as tenant_name FROM units u LEFT JOIN tenants t ON t.unit_id = u.id AND t.status = 'active' WHERE u.property_id = ? ORDER BY u.unit_number");
$stmt->execute([$property['id']]);
$units = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Portal - <?php echo esc($property['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #4361ee; --secondary: #1e293b; --bg: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; color: #1e293b; }
        .header { background: var(--secondary); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .container { max-width: 600px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        h1 { font-size: 24px; margin-bottom: 20px; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #64748b; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; box-sizing: border-box; transition: 0.3s; }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1); }
        .btn { width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn:hover { background: #3a56d4; transform: translateY(-2px); }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .visitor-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #fff; border-radius: 15px; margin-bottom: 12px; border: 1px solid #e2e8f0; transition: 0.3s; }
        .visitor-item:hover { transform: scale(1.02); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .btn-logout { background: #fee2e2; color: #ef4444; padding: 8px 16px; border-radius: 10px; font-size: 12px; border: none; font-weight: 600; cursor: pointer; }
        .btn-logout:hover { background: #fecaca; }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-info { background: #e0e7ff; color: #4361ee; }
    </style>
</head>
<body>
    <div class="header">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="background:var(--primary); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-shield-alt" style="color:white;"></i>
            </div>
            <div>
                <h2 style="margin:0; font-size:16px;">Nyumbaflow Security</h2>
                <p style="margin:0; font-size:12px; opacity:0.7;"><?php echo esc($property['name']); ?></p>
            </div>
        </div>
        <div style="text-align:right;">
            <span style="font-size:12px; opacity:0.7;">Logging in as: <strong>Gate Officer</strong></span><br>
            <form action="logout.php" method="POST" style="display:inline;">
<?php echo get_csrf_token_field(); ?>
                <button type="submit" style="background:none; border:none; color:#fca5a5; font-size:12px; text-decoration:none; font-weight:600; cursor:pointer; padding:0;">Sign Out</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-user-plus"></i> New Visitor Entry</h1>
            <form method="POST" enctype="multipart/form-data">
<?php echo get_csrf_token_field(); ?>
                <div class="form-group">
                    <label class="required">Full Name</label>
                    <input type="text" name="v_name" class="form-control" required placeholder="John Doe">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label class="required">ID Number</label>
                        <input type="text" name="v_id_number" class="form-control" required placeholder="12345678">
                    </div>
                    <div class="form-group">
                        <label class="required">Phone Number</label>
                        <input type="tel" name="v_phone" class="form-control" required placeholder="0712345678">
                    </div>
                </div>
                <div class="form-group">
                    <label>ID Photo (Upload/Capture)</label>
                    <input type="file" name="id_image" class="form-control" accept="image/*" capture="environment">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Vehicle Plate (Optional)</label>
                        <input type="text" name="v_plate" class="form-control" placeholder="KAA 001A">
                    </div>
                    <div class="form-group">
                        <label class="required">Visitor Type</label>
                        <select name="v_type" id="v_type" class="form-control" onchange="toggleContractorField()">
                            <option value="guest">Guest</option>
                            <option value="contractor">Contractor</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="contractor_div" style="display:none;">
                    <label>Contractor/Company Name & Purpose</label>
                    <input type="text" name="v_purpose" class="form-control" placeholder="e.g. Acme Plumbing - Leak fix">
                </div>
                <div class="form-group">
                    <label class="required">Visiting Unit</label>
                    <select name="unit_id" class="form-control" required>
                        <option value="">-- Select House --</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?php echo esc($u['unit_id']); ?>">
                                <?php echo esc($u['unit_number']); ?> 
                                <?php echo $u['tenant_name'] ? '(' . esc($u['tenant_name']) . ')' : '(Vacant)'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="log_visitor" class="btn">Confirm Entry</button>
            </form>
        </div>

        <div style="margin-top:40px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="font-size:18px; margin:0;"><i class="fas fa-clock"></i> Active Visitors</h2>
                <div class="search-box" style="position:relative;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:12px; color:#94a3b8; font-size:12px;"></i>
                    <input type="text" id="visitorSearch" class="form-control" placeholder="Search..." style="padding-left:30px; padding-top:8px; padding-bottom:8px; font-size:12px;">
                </div>
            </div>
            
            <div id="activeList">
                <?php foreach ($active_visitors as $v): ?>
                    <div class="visitor-item" data-search="<?php echo esc(strtolower($v['name'] . ' ' . $v['unit_number'])); ?>">
                        <div>
                            <h4 style="margin:0; font-size:15px;"><?php echo esc($v['name']); ?></h4>
                            <div style="display:flex; gap:10px; margin-top:5px;">
                                <span class="badge badge-info">Unit <?php echo esc($v['unit_number']); ?></span>
                                <span style="font-size:12px; color:#94a3b8;"><i class="far fa-clock"></i> <?php echo esc(date('H:i', strtotime($v['time_in']))); ?></span>
                            </div>
                        </div>
                        <form method="POST">
                    </div>
                <?php endforeach; ?>
                <?php if (empty($active_visitors)): ?>
                    <p style="text-align: center; color: #94a3b8; font-size: 13px; padding: 20px; background:white; border-radius:15px; border:1px dashed #cbd5e1;">No visitors currently in the property.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleContractorField() {
            const type = document.getElementById('v_type').value;
            const div = document.getElementById('contractor_div');
            div.style.display = (type === 'contractor') ? 'block' : 'none';
        }

        document.getElementById('visitorSearch').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.visitor-item').forEach(item => {
                const searchData = item.getAttribute('data-search');
                item.style.display = searchData.includes(term) ? 'flex' : 'none';
            });
        });
    </script>
</body>
</html>

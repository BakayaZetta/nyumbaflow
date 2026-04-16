<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['personnel_id']) || $_SESSION['personnel_role'] !== 'caretaker') {
    header("Location: personnel_login.php");
    exit();
}

$caretaker_id = $_SESSION['personnel_id'];
$property_id = $_SESSION['personnel_property_id'];
$caretaker_name = $_SESSION['personnel_name'];

$message = "";
$message_type = "";

// Fetch Property Details
$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$property_id]);
$property = $stmt->fetch();


// Fetch Units
$stmt = $pdo->prepare("SELECT * FROM units WHERE property_id = ? ORDER BY unit_number ASC");
$stmt->execute([$property_id]);
$units = $stmt->fetchAll();

// Handle Meter Reading Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reading'])) {
    $unit_id = $_POST['unit_id'];
    $reading = floatval($_POST['reading']);
    $month = date('F');
    $year = date('Y');

    try {
        $pdo->beginTransaction();

        // 1. Get previous reading
        $stmt = $pdo->prepare("SELECT reading_curr FROM bills WHERE unit_id = ? AND bill_type = 'water' ORDER BY year DESC, FIELD(month, 'December','November','October','September','August','July','June','May','April','March','February','January') LIMIT 1");
        $stmt->execute([$unit_id]);
        $prev_reading = $stmt->fetchColumn() ?: 0;

        // 2. Check if a water bill already exists for this month
        $stmt = $pdo->prepare("SELECT id FROM bills WHERE unit_id = ? AND month = ? AND year = ? AND bill_type = 'water'");
        $stmt->execute([$unit_id, $month, $year]);
        $existing_bill_id = $stmt->fetchColumn();

        $units_consumed = max(0, $reading - $prev_reading);
        // Get water rate from the unit, not the property
        $rateStmt = $pdo->prepare("SELECT water_rate FROM units WHERE id = ?");
        $rateStmt->execute([$unit_id]);
        $rate = $rateStmt->fetchColumn() ?: 100; // Default rate
        $amount = $units_consumed * $rate;

        if ($existing_bill_id) {
            $upd = $pdo->prepare("UPDATE bills SET reading_prev = ?, reading_curr = ?, amount = ?, balance = ? WHERE id = ?");
            $upd->execute([$prev_reading, $reading, $amount, $amount, $existing_bill_id]);
        } else {
            // Get tenant for this unit
            $tStmt = $pdo->prepare("SELECT id FROM tenants WHERE unit_id = ? AND status = 'active'");
            $tStmt->execute([$unit_id]);
            $tenant_id = $tStmt->fetchColumn();

            if ($tenant_id) {
                $ins = $pdo->prepare("INSERT INTO bills (unit_id, tenant_id, bill_type, amount, balance, month, year, reading_prev, reading_curr) VALUES (?, ?, 'water', ?, ?, ?, ?, ?, ?)");
                $ins->execute([$unit_id, $tenant_id, $amount, $amount, $month, $year, $prev_reading, $reading]);
            } else {
                throw new Exception("No active tenant found for this unit.");
            }
        }

        $pdo->commit();
        $message = "Reading for Unit " . $_POST['unit_number'] . " recorded successfully!";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Caretaker Portal - <?php echo htmlspecialchars($property['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #4361ee; --secondary: #1e293b; --bg: #f8fafc; }
        body { background: var(--bg); color: #1e293b; font-family: 'Inter', sans-serif; margin: 0; }
        .header { background: var(--secondary); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .unit-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; margin-top: 20px; }
        .unit-card { background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 25px; transition: 0.3s; }
        .unit-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; margin-top: 15px; transition: 0.3s; }
        .btn:hover { background: #3a56d4; }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <div class="header">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="background:var(--primary); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-tint" style="color:white;"></i>
            </div>
            <div>
                <h2 style="margin:0; font-size:16px;">Nyumbaflow Caretaker</h2>
                <p style="margin:0; font-size:12px; opacity:0.7;"><?php echo htmlspecialchars($property['name']); ?></p>
            </div>
        </div>
        <div style="text-align:right;">
            <span style="font-size:12px; opacity:0.7;">Logged in as: <strong><?php echo htmlspecialchars($caretaker_name); ?></strong></span><br>
            <form action="logout.php" method="POST" style="display:inline;">
                <button type="submit" style="background:none; border:none; color:#fca5a5; font-size:12px; text-decoration:none; font-weight:600; cursor:pointer; padding:0;">Sign Out</button>
            </form>
        </div>
    </div>

    <div class="container">
        <header style="margin-bottom: 40px;">
            <h1 style="font-size: 28px; margin-bottom: 8px;">Water Meter Readings</h1>
            <p style="color: #64748b;">Record current usage for all units. Automated billing will be calculated.</p>
        </header>


        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="unit-grid">
            <?php foreach ($units as $unit): ?>
                <div class="unit-card">
                    <h3 style="margin:0 0 10px 0;">Unit <?php echo htmlspecialchars($unit['unit_number']); ?></h3>
                    <form method="POST">
                        <input type="hidden" name="unit_id" value="<?php echo $unit['id']; ?>">
                        <input type="hidden" name="unit_number" value="<?php echo $unit['unit_number']; ?>">
                        <div class="form-group">
                            <label>Current Meter Reading (Value)</label>
                            <input type="number" step="0.01" name="reading" class="form-control" required placeholder="Enter current reading">
                        </div>
                        <button type="submit" name="submit_reading" class="btn">Update Reading</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>

<?php
session_start();
require_once 'db_config.php';
require_once 'SmsService.php';

// Check if landlord is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.html");
    exit();
}

$landlord_id = $_SESSION['admin_id'];
$sms = new SmsService();

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle Bulk Notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_bulk'])) {
    $property_id = $_POST['property_id'];
    $notif_text = $_POST['notif_text'];

    try {
        // Fetch all active tenants for this property
        $stmt = $pdo->prepare("SELECT phone_number FROM tenants WHERE property_id = ? AND status = 'active'");
        $stmt->execute([$property_id]);
        $phones = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($phones) > 0) {
            $sms->sendBulkNotice($phones, $notif_text);
            $_SESSION['message'] = "Bulk notification sent to " . count($phones) . " tenants!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "No active tenants found for this property.";
            $_SESSION['message_type'] = "error";
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    header("Location: notifications.php");
    exit();
}

// Handle Individual SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_individual_sms'])) {
    $phone = $_POST['send_individual_sms'];
    $message = $_POST['individual_message'];

    try {
        // Get property name for shortcode
        $stmt = $pdo->prepare("
            SELECT p.name as property_name
            FROM tenants t
            JOIN properties p ON t.property_id = p.id
            WHERE t.phone_number = ? AND p.landlord_id = ?
        ");
        $stmt->execute([$phone, $landlord_id]);
        $property = $stmt->fetch();

        if ($property) {
            $sms->sendDirectMessage($phone, $message, $property['property_name']);
            $_SESSION['message'] = "Direct message sent successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Tenant not found or access denied.";
            $_SESSION['message_type'] = "error";
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error sending message: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    header("Location: notifications.php");
    exit();
}

// Fetch Properties for selection
$stmt = $pdo->prepare("SELECT * FROM properties WHERE landlord_id = ?");
$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll();

// Fetch Recent Tenants for individual SMS
$stmt = $pdo->prepare("SELECT t.*, p.name as property_name FROM tenants t JOIN properties p ON t.property_id = p.id WHERE p.landlord_id = ? ORDER BY t.name ASC");
$stmt->execute([$landlord_id]);
$tenants = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Center - HomeSync</title>
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
        
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: var(--dark); }

        .notif-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .notif-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .notif-card h3 { font-size: 18px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 10px; font-size: 14px; font-weight: 600; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; }
        textarea.form-control { height: 120px; resize: none; }

        .btn { padding: 12px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: var(--transition); border: none; width: 100%; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #3a0ca3; }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .hint { font-size: 12px; color: var(--gray); margin-top: 5px; display: block; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main">
            <div class="page-header">
                <h1>Notification Center</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="notif-grid">
                <!-- Bulk Message -->
                <div class="notif-card">
                    <h3><i class="fas fa-bullhorn" style="color: var(--primary);"></i> Bulk Announcement</h3>
                    <p style="font-size: 14px; color: var(--gray); margin-bottom: 20px;">Send a message to all tenants in a selected property.</p>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Property</label>
                            <select name="property_id" class="form-control" required>
                                <?php foreach ($properties as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Message Text</label>
                            <textarea name="notif_text" class="form-control" placeholder="e.g. Dear Tenants, scheduled maintenance will occur this Saturday..." required></textarea>
                            <small class="hint">Avoid using special characters for better SMS delivery.</small>
                        </div>
                        <button type="submit" name="send_bulk" class="btn btn-primary">Send Bulk SMS</button>
                    </form>
                </div>

                <!-- Individual Message -->
                <div class="notif-card">
                    <h3><i class="fas fa-user" style="color: var(--success);"></i> Direct Message</h3>
                    <p style="font-size: 14px; color: var(--gray); margin-bottom: 20px;">Send a custom message to a specific tenant.</p>
                    
                    <form id="individualForm">
                        <div class="form-group">
                            <label>Select Tenant</label>
                            <select id="tenantSelect" class="form-control" required>
                                <option value="">-- Select Tenant --</option>
                                <?php foreach ($tenants as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t['phone_number']); ?>">
                                        <?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['property_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Message Text</label>
                            <textarea id="directText" class="form-control" placeholder="e.g. Hi Jane, please call the office regarding your recent request..." required></textarea>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="sendIndividualNotif()">Send Direct SMS</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Removed old function as we now use form submission
    </script>
</body>
</html>

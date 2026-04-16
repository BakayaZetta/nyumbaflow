<?php
require_once 'db_config.php';
require_once 'session_check.php';
requireLogin();
require_once 'sanitize.php';

// Check if user is logged in
$landlord_id = $_SESSION['admin_id'];
$success_message = '';
$error_message = '';

function settingsTableExists($pdo, $table_name) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table_name]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// Handle apartment/property deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_property'])) {
    $property_id = intval($_POST['property_id'] ?? 0);

    try {
        $checkStmt = $pdo->prepare("SELECT id, name FROM properties WHERE id = ? AND landlord_id = ? LIMIT 1");
        $checkStmt->execute([$property_id, $landlord_id]);
        $property = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$property) {
            $error_message = "Property not found or you do not have permission to delete it.";
        } else {
            $pdo->beginTransaction();

            // Remove dependent data in a controlled order.
            $pdo->prepare("DELETE p FROM payments p JOIN bills b ON p.bill_id = b.id JOIN units u ON b.unit_id = u.id WHERE u.property_id = ?")
                ->execute([$property_id]);
            $pdo->prepare("DELETE b FROM bills b JOIN units u ON b.unit_id = u.id WHERE u.property_id = ?")
                ->execute([$property_id]);
            $pdo->prepare("DELETE FROM visitors WHERE property_id = ?")->execute([$property_id]);

            if (settingsTableExists($pdo, 'tenant_links')) {
                $pdo->prepare("DELETE tl FROM tenant_links tl JOIN tenants t ON tl.tenant_id = t.id WHERE t.property_id = ?")
                    ->execute([$property_id]);
            }
            if (settingsTableExists($pdo, 'security_links')) {
                $pdo->prepare("DELETE FROM security_links WHERE property_id = ?")->execute([$property_id]);
            }
            if (settingsTableExists($pdo, 'gate_personnel')) {
                $pdo->prepare("DELETE FROM gate_personnel WHERE property_id = ?")->execute([$property_id]);
            }
            if (settingsTableExists($pdo, 'caretakers')) {
                $pdo->prepare("DELETE FROM caretakers WHERE property_id = ?")->execute([$property_id]);
            }

            $pdo->prepare("DELETE FROM tenants WHERE property_id = ?")->execute([$property_id]);
            $pdo->prepare("DELETE FROM units WHERE property_id = ?")->execute([$property_id]);
            $pdo->prepare("DELETE FROM properties WHERE id = ? AND landlord_id = ?")->execute([$property_id, $landlord_id]);

            $pdo->commit();
            $success_message = "Apartment deleted successfully with its mapped records.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Error deleting apartment: " . $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT * FROM properties WHERE landlord_id = ?");
$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HomeSync</title>
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

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .settings-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .settings-card:hover { transform: translateY(-5px); }
        
        .settings-card h3 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; color: var(--primary); }
        .settings-card p { font-size: 14px; color: var(--gray); line-height: 1.5; }

        .btn {
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #3a0ca3; }
        
        .btn-outline { background: transparent; color: var(--primary); border: 1px solid var(--primary); }
        .btn-outline:hover { background: var(--primary); color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }

        .property-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: var(--light);
            border-radius: 10px;
            font-size: 14px;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main">
            <div class="page-header">
                <h1>Settings & Management</h1>
            </div>

            <?php if ($success_message): ?>
                <div style="padding: 12px 14px; border-radius: 10px; background: #dcfce7; color: #166534; margin-bottom: 20px;">
                    <?php echo esc($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div style="padding: 12px 14px; border-radius: 10px; background: #fee2e2; color: #991b1b; margin-bottom: 20px;">
                    <?php echo esc($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Property Management -->
                <div class="settings-card">
                    <h3><i class="fas fa-building"></i> My Properties</h3>
                    <p>Manage your apartment complexes, floor counts, and basic information.</p>
                    <div class="property-list">
                        <?php if (count($properties) > 0): ?>
                            <?php foreach ($properties as $p): ?>
                                <div class="property-item">
                                    <div>
                                        <span><strong><?php echo esc($p['name']); ?></strong></span><br>
                                        <small><?php echo esc($p['location']); ?></small>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Delete apartment <?php echo htmlspecialchars(addslashes($p['name']), ENT_QUOTES); ?> and all mapped data? This cannot be undone.');">
                                        <?php echo get_csrf_token_field(); ?>
                                        <input type="hidden" name="property_id" value="<?php echo (int)$p['id']; ?>">
                                        <button type="submit" name="delete_property" class="btn btn-danger" style="padding: 8px 12px; font-size: 12px;">Delete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="font-style: italic;">No properties found.</p>
                        <?php endif; ?>
                    </div>
                    <a href="onboarding.html" class="btn btn-outline" style="margin-top: 10px;"><i class="fas fa-plus"></i> Add New Property</a>
                </div>

                <!-- Rate Management -->
                <div class="settings-card">
                    <h3><i class="fas fa-tint"></i> Utility Rates</h3>
                    <p>Update water, wifi, and garbage rates. Changes can be automatically broadcast to tenants.</p>
                    <form action="update_rates.php" method="POST">
<?php echo get_csrf_token_field(); ?>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <label style="font-size: 12px; font-weight: 600;">Default Water Rate (KES)</label>
                            <input type="number" name="water_rate" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;" placeholder="200">
                            
                            <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                                <input type="checkbox" name="notify_tenants" id="notify">
                                <label for="notify" style="font-size: 12px;">Notify all tenants via SMS</label>
                            </div>
                            <button type="submit" class="btn btn-primary" style="margin-top: 5px;">Update & Notify</button>
                        </div>
                    </form>
                </div>

                <!-- Security Management -->
                <div class="settings-card">
                    <h3><i class="fas fa-user-shield"></i> Personnel Management</h3>
                    <p>Provision permanent accounts for Gate Personnel and Caretakers. Manage their access to specific properties.</p>
                    <div style="margin-top: auto;">
                        <a href="access_control.php" class="btn btn-primary" style="width: 100%;">Manage Personnel Access</a>
                    </div>
                </div>

                <!-- Contractor Management -->
                <div class="settings-card">
                    <h3><i class="fas fa-tools"></i> Service Providers</h3>
                    <p>Keep track of plumbers, electricians, and other contractors for your properties.</p>
                    <div style="margin-top: auto;">
                        <a href="contractors.php" class="btn btn-primary" style="width: 100%;">View Contractors</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
require_once 'db_config.php';
require_once 'session_check.php';
require_once 'sanitize.php';

// Check if user is logged in
requireLogin();

// Ensure optional tenant columns exist outside transactions.
try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS id_picture VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS initial_water_reading DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS secondary_phone_number VARCHAR(15) NULL");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS wifi_fee DECIMAL(10,2) DEFAULT 0");
} catch (PDOException $e) {
    // Continue; these columns may already exist or be managed elsewhere.
}

if (isset($_SESSION['tenant_success'])) {
    $success = $_SESSION['tenant_success'];
    unset($_SESSION['tenant_success']);
}
if (isset($_SESSION['tenant_error'])) {
    $error = $_SESSION['tenant_error'];
    unset($_SESSION['tenant_error']);
}

function tableExists($pdo, $table_name) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table_name]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function autoCreateBillsForTenant($pdo, $property_id, $tenant_id, $unit_id, $has_wifi, $has_garbage) {
    $month = date('F');
    $year = date('Y');

    // Only auto-bill if this property has already started billing for the current month.
    $cycleStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM bills b
        JOIN units u ON b.unit_id = u.id
        WHERE u.property_id = ? AND b.month = ? AND b.year = ?
    ");
    $cycleStmt->execute([$property_id, $month, $year]);
    if ((int)$cycleStmt->fetchColumn() === 0) {
        return;
    }

    $unitStmt = $pdo->prepare("SELECT rent_amount, water_rate, wifi_fee, garbage_fee FROM units WHERE id = ? AND property_id = ? LIMIT 1");
    $unitStmt->execute([$unit_id, $property_id]);
    $unit = $unitStmt->fetch(PDO::FETCH_ASSOC);
    if (!$unit) {
        return;
    }

    $tenantStmt = $pdo->prepare("SELECT balance_credit, wifi_fee FROM tenants WHERE id = ? AND property_id = ? LIMIT 1");
    $tenantStmt->execute([$tenant_id, $property_id]);
    $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);
    if (!$tenant) {
        return;
    }

    $credit = (float)$tenant['balance_credit'];
    $due_date = date('Y-m-d', strtotime('next month 5th'));

    $createFixedBill = function ($bill_type, $amount) use ($pdo, $tenant_id, $unit_id, $month, $year, $due_date, &$credit) {
        $amount = (float)$amount;
        if ($amount <= 0) {
            return;
        }

        $existsStmt = $pdo->prepare("SELECT id FROM bills WHERE tenant_id = ? AND bill_type = ? AND month = ? AND year = ? LIMIT 1");
        $existsStmt->execute([$tenant_id, $bill_type, $month, $year]);
        if ($existsStmt->fetch()) {
            return;
        }

        $balance = $amount;
        if ($credit > 0) {
            $reduction = min($credit, $balance);
            $balance -= $reduction;
            $credit -= $reduction;
        }

        $status = $balance <= 0 ? 'paid' : 'unpaid';
        $insertStmt = $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([$tenant_id, $unit_id, $bill_type, $amount, $balance, $month, $year, $due_date, $status]);
    };

    $createFixedBill('rent', $unit['rent_amount']);
    if ((int)$has_wifi === 1) {
        $wifi_amt = (float)($tenant['wifi_fee'] > 0 ? $tenant['wifi_fee'] : $unit['wifi_fee']);
        $createFixedBill('wifi', $wifi_amt);
    }
    if ((int)$has_garbage === 1) {
        $createFixedBill('garbage', $unit['garbage_fee']);
    }

    // Water placeholder for the month so the tenant appears in monthly billing actions.
    $waterExistsStmt = $pdo->prepare("SELECT id FROM bills WHERE tenant_id = ? AND bill_type = 'water' AND month = ? AND year = ? LIMIT 1");
    $waterExistsStmt->execute([$tenant_id, $month, $year]);
    if (!$waterExistsStmt->fetch()) {
        // Try to get the latest reading for this specific tenant first
        $prevStmt = $pdo->prepare("SELECT reading_curr FROM bills WHERE tenant_id = ? AND bill_type = 'water' ORDER BY id DESC LIMIT 1");
        $prevStmt->execute([$tenant_id]);
        $prev = $prevStmt->fetchColumn();
        
        if ($prev === false) {
            // No previous bills? Use the initial reading from the tenant record
            $tReadStmt = $pdo->prepare("SELECT initial_water_reading FROM tenants WHERE id = ?");
            $tReadStmt->execute([$tenant_id]);
            $prev = $tReadStmt->fetchColumn();
        }
        $prev = $prev !== false ? (float)$prev : 0;

        $waterInsertStmt = $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, reading_curr, reading_prev, due_date, status) VALUES (?, ?, 'water', 0, 0, ?, ?, ?, ?, ?, 'unpaid')");
        $waterInsertStmt->execute([$tenant_id, $unit_id, $month, $year, $prev, $prev, $due_date]);
    }

    $creditUpdateStmt = $pdo->prepare("UPDATE tenants SET balance_credit = ? WHERE id = ?");
    $creditUpdateStmt->execute([$credit, $tenant_id]);
}

// Fetch all tenants with their property and unit details
$tenants = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, p.name as property_name, u.unit_number 
        FROM tenants t 
        JOIN properties p ON t.property_id = p.id 
        JOIN units u ON t.unit_id = u.id 
        WHERE p.landlord_id = ? 
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$_SESSION['admin_id']]);
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching tenants: " . $e->getMessage();
}

// Fetch properties and units for the modal
$stmt = $pdo->prepare("SELECT id, name FROM properties WHERE landlord_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$available_properties = $stmt->fetchAll();

// Fetch only vacant units (units without active tenants) for the modal dropdown
$stmt = $pdo->prepare("
    SELECT u.id, u.unit_number, u.property_id, u.rent_amount 
    FROM units u 
    JOIN properties p ON u.property_id = p.id 
    WHERE p.landlord_id = ?
    AND u.id NOT IN (
        SELECT unit_id FROM tenants WHERE status = 'active'
    )
    ORDER BY u.unit_number ASC
");
$stmt->execute([$_SESSION['admin_id']]);
$available_units = $stmt->fetchAll();

// Handle form submission for editing tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_tenant'])) {
    $tenant_id = $_POST['edit_tenant_id'];
    $name = $_POST['edit_name'];
    $id_number = $_POST['edit_id_number'];
    $phone_number = $_POST['edit_phone_number'];
    $secondary_phone_number = $_POST['edit_secondary_phone_number'] ?? null;
    $has_wifi = isset($_POST['edit_has_wifi']) ? 1 : 0;
    $wifi_fee = floatval($_POST['edit_wifi_fee'] ?? 0);
    $has_garbage = isset($_POST['edit_has_garbage']) ? 1 : 0;
    $initial_water_reading = $_POST['edit_initial_water_reading'] ?? 0;

    try {
        // First verify this tenant belongs to the current landlord
        $stmt = $pdo->prepare("
            SELECT t.id FROM tenants t
            JOIN properties p ON t.property_id = p.id
            WHERE t.id = ? AND p.landlord_id = ?
        ");
        $stmt->execute([$tenant_id, $_SESSION['admin_id']]);
        if (!$stmt->fetch()) {
            $error = "Access denied: Tenant not found or doesn't belong to you.";
        } else {
            // Check if ID number is already used by another tenant
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id_number = ? AND id != ?");
            $stmt->execute([$id_number, $tenant_id]);
            if ($stmt->fetch()) {
                $error = "A tenant with this ID number already exists.";
            } else {
                // Update tenant
                $stmt = $pdo->prepare("UPDATE tenants SET name = ?, id_number = ?, phone_number = ?, secondary_phone_number = ?, has_wifi = ?, wifi_fee = ?, has_garbage = ?, initial_water_reading = ? WHERE id = ?");
                $stmt->execute([$name, $id_number, $phone_number, $secondary_phone_number, $has_wifi, $wifi_fee, $has_garbage, $initial_water_reading, $tenant_id]);

                $success = "Tenant updated successfully!";

                // Refresh the tenants list
                $stmt = $pdo->prepare("
                    SELECT t.*, p.name as property_name, u.unit_number
                    FROM tenants t
                    JOIN properties p ON t.property_id = p.id
                    JOIN units u ON t.unit_id = u.id
                    WHERE p.landlord_id = ?
                    ORDER BY t.created_at DESC
                ");
                $stmt->execute([$_SESSION['admin_id']]);
                $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        $error = "Error updating tenant: " . $e->getMessage();
    }
}

// Handle tenant move-out (vacate unit) - COMPLETE REMOVAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tenant'])) {
    $tenant_id = $_POST['delete_tenant_id'];

    try {
        $pdo->beginTransaction();

        // First verify this tenant belongs to the current landlord
        $stmt = $pdo->prepare("
            SELECT t.id, t.status, t.unit_id FROM tenants t
            JOIN properties p ON t.property_id = p.id
            WHERE t.id = ? AND p.landlord_id = ?
        ");
        $stmt->execute([$tenant_id, $_SESSION['admin_id']]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tenant) {
            $pdo->rollBack();
            $error = "Access denied: Tenant not found or doesn't belong to you.";
        } else {
            // Check if tenant has any unpaid bills
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE tenant_id = ? AND balance > 0");
            $stmt->execute([$tenant_id]);
            $unpaid_bills = $stmt->fetchColumn();

            if ($unpaid_bills > 0) {
                $pdo->rollBack();
                $error = "Cannot move out tenant with unpaid bills. Please settle all outstanding balances first.";
            } else {
                // Delete all bills associated with this tenant first (to avoid FK constraint with payments)
                $stmt = $pdo->prepare("DELETE FROM bills WHERE tenant_id = ?");
                $stmt->execute([$tenant_id]);

                // Delete any payments associated with this tenant's bills
                $stmt = $pdo->prepare("DELETE FROM payments WHERE bill_id IN (SELECT id FROM bills WHERE tenant_id = ?)");
                $stmt->execute([$tenant_id]);

                // Delete visitor records linked to this tenant (due to FK constraint)
                $stmt = $pdo->prepare("DELETE FROM visitors WHERE tenant_id = ?");
                $stmt->execute([$tenant_id]);

                // Revoke any tenant portal access link if table exists.
                if (tableExists($pdo, 'tenant_links')) {
                    $stmt = $pdo->prepare("DELETE FROM tenant_links WHERE tenant_id = ?");
                    $stmt->execute([$tenant_id]);
                }

                // Delete the tenant completely from the system
                $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
                $stmt->execute([$tenant_id]);

                $pdo->commit();
                $success = "Tenant removed from the system completely. The unit is now vacant and ready for a new tenant.";

                // Refresh the tenants list
                $stmt = $pdo->prepare("
                    SELECT t.*, p.name as property_name, u.unit_number
                    FROM tenants t
                    JOIN properties p ON t.property_id = p.id
                    JOIN units u ON t.unit_id = u.id
                    WHERE p.landlord_id = ?
                    ORDER BY t.created_at DESC
                ");
                $stmt->execute([$_SESSION['admin_id']]);
                $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error moving out tenant: " . $e->getMessage();
    }
}

// Handle form submission for adding new tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tenant'])) {
    $name = $_POST['name'];
    $id_number = $_POST['id_number'];
    $property_id = $_POST['property_id'];
    $unit_id = $_POST['unit_id'];
    $phone_number = $_POST['phone_number'];
    $secondary_phone_number = $_POST['secondary_phone_number'] ?? null;
    $move_in_date = $_POST['move_in_date'];
    $has_wifi = isset($_POST['has_wifi']) ? 1 : 0;
    $wifi_fee = floatval($_POST['wifi_fee'] ?? 0);
    $has_garbage = isset($_POST['has_garbage']) ? 1 : 0;
    $custom_rent_amount = floatval($_POST['custom_rent_amount'] ?? 0);
    
    // Handle file upload (ID picture) with security validation
    $id_picture = null;
    if (isset($_FILES['id_picture']) && $_FILES['id_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/id_pictures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true); 
        }
        
        // Security validations
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['id_picture']['type'];
        $file_size = $_FILES['id_picture']['size'];
        $file_extension = strtolower(pathinfo($_FILES['id_picture']['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $error = "Invalid file type. Only JPG, PNG, and GIF images are allowed.";
        }
        // Validate extension
        elseif (!in_array($file_extension, $allowed_extensions)) {
            $error = "Invalid file extension. Only jpg, jpeg, png, and gif are allowed.";
        }
        // Validate file size
        elseif ($file_size > $max_file_size) {
            $error = "File too large. Maximum size is 5MB.";
        }
        // All validations passed
        else {
            // Generate secure filename
            $id_picture = $upload_dir . uniqid('id_', true) . '.' . $file_extension;
            
            if (!move_uploaded_file($_FILES['id_picture']['tmp_name'], $id_picture)) {
                $error = "Failed to upload file. Please try again.";
                $id_picture = null;
            }
        }
    }
    
    // Alter table to add id_picture and initial readings columns if they don't exist (MUST be outside transaction)
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS id_picture VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS initial_water_reading DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS secondary_phone_number VARCHAR(15) NULL");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS wifi_fee DECIMAL(10,2) DEFAULT 0");

    if (!isset($error)) {
        try {
            // Ensure selected unit belongs to this landlord and selected property
            $stmt = $pdo->prepare("
                SELECT u.id
                FROM units u
                JOIN properties p ON u.property_id = p.id
                WHERE u.id = ? AND u.property_id = ? AND p.landlord_id = ?
                LIMIT 1
            ");
            $stmt->execute([$unit_id, $property_id, $_SESSION['admin_id']]);
            if (!$stmt->fetch()) {
                throw new Exception("Invalid unit selection for the selected property.");
            }

            if ($custom_rent_amount <= 0) {
                throw new Exception("Please enter a valid rent amount greater than zero.");
            }

            // Block assigning a new tenant to an occupied unit
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE unit_id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$unit_id]);
            if ($stmt->fetch()) {
                throw new Exception("This unit is currently occupied. Move out the current tenant first.");
            }

            // Check if an ACTIVE tenant with this ID already exists under this landlord
            $stmt = $pdo->prepare("
                SELECT t.id FROM tenants t 
                JOIN properties p ON t.property_id = p.id 
                WHERE t.id_number = ? AND t.status = 'active' AND p.landlord_id = ?
            ");
            $stmt->execute([$id_number, $_SESSION['admin_id']]);
            if ($stmt->fetch()) {
                throw new Exception("A tenant with this ID number already exists.");
            }

            // Persist unit-specific rent chosen during tenant onboarding
            $stmt = $pdo->prepare("UPDATE units SET rent_amount = ? WHERE id = ?");
            $stmt->execute([$custom_rent_amount, $unit_id]);

            // Get initial readings from form
            $initial_water_reading = $_POST['initial_water_reading'] ?? 0;

            // Insert new tenant
            $stmt = $pdo->prepare("INSERT INTO tenants (property_id, unit_id, name, id_number, phone_number, secondary_phone_number, move_in_date, id_picture, has_wifi, wifi_fee, has_garbage, initial_water_reading) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$property_id, $unit_id, $name, $id_number, $phone_number, $secondary_phone_number, $move_in_date, $id_picture, $has_wifi, $wifi_fee, $has_garbage, $initial_water_reading]);
            $tenant_id = $pdo->lastInsertId();

            // Auto-bill this new tenant for the current billing cycle when applicable.
            autoCreateBillsForTenant($pdo, $property_id, $tenant_id, $unit_id, $has_wifi, $has_garbage);

            $success = "Tenant added successfully!";
            
            // Refresh the tenants list
            $stmt = $pdo->prepare("
                SELECT t.*, p.name as property_name, u.unit_number 
                FROM tenants t 
                JOIN properties p ON t.property_id = p.id 
                JOIN units u ON t.unit_id = u.id 
                WHERE p.landlord_id = ? 
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([$_SESSION['admin_id']]);
            $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Redirect to clear POST data and ensure modal closes
            $_SESSION['tenant_success'] = $success;
            header("Location: tenants.php");
            exit();
        } catch (Exception $e) {
            $error = "Error adding tenant: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Management - Homesync</title>
    <link rel="shortcut icon" href="icons/home.png" type="image/x-icon">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a0ca3;
            --secondary: #4cc9f0;
            --success: #2ec4b6;
            --warning: #ff9f1c;
            --danger: #e71d36;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .page-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .bg-primary-light {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .bg-success-light {
            background: rgba(46, 196, 182, 0.1);
            color: var(--success);
        }
        
        .bg-warning-light {
            background: rgba(255, 159, 28, 0.1);
            color: var(--warning);
        }
        
        .card-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .card-footer {
            font-size: 14px;
            color: var(--gray);
        }
        
        .positive {
            color: var(--success);
        }
        
        .negative {
            color: var(--danger);
        }
        
        /* Main Tenant Card */
        .tenant-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: var(--card-shadow);
            margin-bottom: 32px;
        }
        
        .card-header-lg {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .card-title-lg {
            font-size: 20px;
            font-weight: 700;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--light-gray);
        }
        
        .tenant-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tenant-table th {
            background: var(--light);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
        }
        
        .tenant-table td {
            padding: 16px;
            border-top: 1px solid var(--light-gray);
        }
        
        .tenant-table tr:hover {
            background: rgba(67, 97, 238, 0.03);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-occupied {
            background: rgba(46, 196, 182, 0.1);
            color: var(--success);
        }
        
        .status-vacant {
            background: rgba(255, 159, 28, 0.1);
            color: var(--warning);
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }
        
        .btn-view {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .btn-view:hover {
            background: rgba(67, 97, 238, 0.2);
        }
        
        .btn-edit {
            background: rgba(255, 159, 28, 0.1);
            color: var(--warning);
        }
        
        .btn-edit:hover {
            background: rgba(255, 159, 28, 0.2);
        }
        
        .btn-delete {
            background: rgba(231, 29, 54, 0.1);
            color: var(--danger);
        }
        
        .btn-delete:hover {
            background: rgba(231, 29, 54, 0.2);
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
            display: none;
        }
        
        .modal {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 700;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .modal-footer {
            padding: 24px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        /* Notification Styles */
        .notification {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .notification-success {
            background: rgba(46, 196, 182, 0.1);
            color: var(--success);
            border: 1px solid rgba(46, 196, 182, 0.2);
        }
        
        .notification-error {
            background: rgba(231, 29, 54, 0.1);
            color: var(--danger);
            border: 1px solid rgba(231, 29, 54, 0.2);
        }
        
        .notification-close {
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
        }
        
        /* Responsive Styles */
        @media (max-width: 1024px) {
            .app-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 16px;
            }
            
            .sidebar-header {
                padding: 0 0 16px;
            }
            
            .nav-items {
                display: flex;
                overflow-x: auto;
                gap: 8px;
                margin-bottom: 16px;
            }
            
            .nav-item {
                border-left: none;
                border-bottom: 3px solid transparent;
                padding: 12px 16px;
                white-space: nowrap;
            }
            
            .nav-item:hover, .nav-item.active {
                border-left-color: transparent;
                border-bottom-color: var(--primary);
            }
            
            .sidebar-footer {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
        }
        
        /* Utility Classes */
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .mb-0 {
            margin-bottom: 0;
        }
        
        .mt-24 {
            margin-top: 24px;
        }
        
        .d-none {
            display: none;
        }
        
        .d-flex {
            display: flex;
        }
        
        .align-center {
            align-items: center;
        }
        
        .justify-between {
            justify-content: space-between;
        }
        
        /* ID Picture Styles */
        .id-picture {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--light-gray);
        }
        
        .id-picture-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Tenant Management</h1>
                <div class="page-actions">
                    <button class="btn btn-primary" id="addTenantBtn">
                        <i class="fas fa-plus"></i> Add Tenant
                    </button>
                </div>
            </div>
            
            <!-- Notifications -->
            <?php if (isset($success)): ?>
                <div class="notification notification-success">
                    <span><?php echo $success; ?></span>
                    <button class="notification-close">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="notification notification-error">
                    <span><?php echo $error; ?></span>
                    <button class="notification-close">&times;</button>
                </div>
            <?php endif; ?>
            
            <!-- Dashboard Cards -->
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Total Tenants</h3>
                        <div class="card-icon bg-primary-light">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="card-value"><?php echo count($tenants); ?></div>
                    <div class="card-footer">
                        <span class="positive"><i class="fas fa-arrow-up"></i> 5.2%</span> from last month
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Occupied Houses</h3>
                        <div class="card-icon bg-success-light">
                            <i class="fas fa-home"></i>
                        </div>
                    </div>
                    <div class="card-value"><?php echo count($tenants); ?></div>
                    <div class="card-footer">
                        <?php echo 20 - count($tenants); ?> houses vacant
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">New This Month</h3>
                        <div class="card-icon bg-warning-light">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    <div class="card-value">3</div>
                    <div class="card-footer">
                        <span class="positive"><i class="fas fa-arrow-up"></i> 15%</span> from last month
                    </div>
                </div>
            </div>
            
            <!-- Tenant List Card -->
            <div class="tenant-card">
                <div class="card-header-lg">
                    <h2 class="card-title-lg">All Tenants</h2>
                    <div class="search-box">
                        <input type="text" class="form-control" placeholder="Search tenants..." style="width: 250px;">
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="tenant-table">
                        <thead>
                            <tr>
                                <th>ID Picture</th>
                                <th>Name</th>
                                <th>ID Number</th>
                                <th>House Number</th>
                                <th>Phone Number</th>
                                <th>Services</th>
                                <th>Move-in Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tenants) > 0): ?>
                                <?php foreach ($tenants as $tenant): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($tenant['id_picture'])): ?>
                                                <img src="serve_id_picture.php?tenant_id=<?php echo $tenant['id']; ?>" alt="ID Picture" class="id-picture">
                                            <?php else: ?>
                                                <div class="id-picture-placeholder">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($tenant['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($tenant['id_number']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($tenant['unit_number']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($tenant['property_name']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($tenant['phone_number']); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <i class="fas fa-wifi" style="color: <?php echo $tenant['has_wifi'] ? 'var(--primary)' : '#ccc'; ?>;" title="WiFi"></i>
                                                <i class="fas fa-trash" style="color: <?php echo $tenant['has_garbage'] ? 'var(--primary)' : '#ccc'; ?>;" title="Garbage"></i>
                                            </div>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($tenant['move_in_date'])); ?></td>
                                        <td>
                                            <?php
                                            $is_active_tenant = (($tenant['status'] ?? 'active') === 'active');
                                            ?>
                                            <span class="status-badge <?php echo $is_active_tenant ? 'status-occupied' : 'status-vacant'; ?>">
                                                <?php echo $is_active_tenant ? 'Occupied' : 'Vacated'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="action-btn btn-edit" onclick="openEditModal(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['name']); ?>', '<?php echo htmlspecialchars($tenant['id_number']); ?>', '<?php echo htmlspecialchars($tenant['phone_number']); ?>', '<?php echo htmlspecialchars($tenant['secondary_phone_number'] ?? ''); ?>', '<?php echo htmlspecialchars($tenant['unit_number']); ?>', '<?php echo htmlspecialchars($tenant['property_name']); ?>', '<?php echo $tenant['has_wifi']; ?>', '<?php echo $tenant['wifi_fee']; ?>', '<?php echo $tenant['has_garbage']; ?>', '<?php echo $tenant['initial_water_reading']; ?>')"><i class="fas fa-edit"></i></button>
                                            <?php if ($is_active_tenant): ?>
                                            <button class="action-btn btn-delete" onclick="confirmDelete(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['name']); ?>')"><i class="fas fa-sign-out-alt"></i></button>
                                            <?php else: ?>
                                            <button class="action-btn btn-delete" style="opacity: 0.4; cursor: not-allowed;" disabled><i class="fas fa-sign-out-alt"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No tenants found. Add your first tenant using the button above.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-24">
                    <button class="btn btn-outline">Load More</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Tenant Modal -->
    <div class="modal-overlay" id="tenantModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Add New Tenant</h2>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?php echo get_csrf_token_field(); ?>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g. John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ID Number</label>
                        <input type="text" class="form-control" name="id_number" required placeholder="e.g. 12345678" autocomplete="off">
                    </div>
                    
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Property</label>
                            <select class="form-control" name="property_id" id="propertySelect" required>
                                <option value="">Select Property</option>
                                <?php foreach ($available_properties as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Unit / House</label>
                            <select class="form-control" name="unit_id" id="unitSelect" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($available_units as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" data-property="<?php echo $u['property_id']; ?>" data-rent="<?php echo htmlspecialchars($u['rent_amount']); ?>">
                                        <?php echo htmlspecialchars($u['unit_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Monthly Rent (KES)</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="custom_rent_amount" id="customRentAmount" required placeholder="Select a unit to preload rent">
                    </div>
                    
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" required placeholder="e.g. 0712345678">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Secondary Phone (Optional)</label>
                            <input type="tel" class="form-control" name="secondary_phone_number" placeholder="e.g. 0787654321">
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px; margin-bottom: 20px; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; margin-bottom: 0;">
                            <input type="checkbox" name="has_wifi" id="hasWifiBill" checked style="width: 18px; height: 18px;"> Enable WiFi
                        </label>
                        <div id="wifiFeeContainer" style="display: flex; align-items: center; gap: 10px;">
                            <label style="font-size: 14px; white-space: nowrap;">WiFi Fee (KES):</label>
                            <input type="number" step="0.01" name="wifi_fee" class="form-control" style="width: 120px; padding: 8px;" placeholder="0.00" value="0">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                            <input type="checkbox" name="has_garbage" checked style="width: 18px; height: 18px;"> Enable Garbage billing
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Move-in Date</label>
                        <input type="date" class="form-control" name="move_in_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ID Picture (Optional)</label>
                        <input type="file" class="form-control" name="id_picture" accept="image/*">
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Initial Water Reading</label>
                            <input type="number" step="0.01" class="form-control" name="initial_water_reading" placeholder="e.g. 123.45" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancelTenant">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="add_tenant">Add Tenant</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Tenant Modal -->
    <div class="modal-overlay" id="editTenantModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Edit Tenant</h2>
                <button class="modal-close" id="closeEditModal">&times;</button>
            </div>
            <form method="POST">
                <?php echo get_csrf_token_field(); ?>
                <input type="hidden" name="edit_tenant_id" id="editTenantId">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="edit_name" id="editName" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ID Number</label>
                        <input type="text" class="form-control" name="edit_id_number" id="editIdNumber" required>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="edit_phone_number" id="editPhoneNumber" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Secondary Phone</label>
                            <input type="tel" class="form-control" name="edit_secondary_phone_number" id="editSecondaryPhoneNumber">
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px; margin-bottom: 20px; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; margin-bottom: 0;">
                            <input type="checkbox" name="edit_has_wifi" id="editHasWifi" style="width: 18px; height: 18px;"> Enable WiFi
                        </label>
                        <div id="editWifiFeeContainer" style="display: flex; align-items: center; gap: 10px;">
                            <label style="font-size: 14px; white-space: nowrap;">WiFi Fee (KES):</label>
                            <input type="number" step="0.01" name="edit_wifi_fee" id="editWifiFee" class="form-control" style="width: 120px; padding: 8px;">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                            <input type="checkbox" name="edit_has_garbage" id="editHasGarbage" style="width: 18px; height: 18px;"> Enable Garbage billing
                        </label>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Initial Water Reading</label>
                            <input type="number" step="0.01" class="form-control" name="edit_initial_water_reading" id="editInitialWaterReading">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancelEditTenant">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="edit_tenant">Update Tenant</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Tenant Removal</h2>
                <button class="modal-close" id="closeDeleteModal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: var(--danger); margin-bottom: 16px;"></i>
                    <h3 style="color: var(--danger); margin-bottom: 16px;">Remove this tenant completely?</h3>
                </div>
                <div style="background: var(--light); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin-bottom: 12px;"><strong>Tenant:</strong> <span id="deleteTenantName"></span></p>
                    <p style="color: var(--danger); font-weight: 600; margin-bottom: 12px;">Warning: This action cannot be undone.</p>
                    <p style="font-size: 14px; color: var(--gray);">This action will:</p>
                    <ul style="font-size: 14px; color: var(--gray); margin-top: 8px;">
                        <li>Completely remove the tenant from the system</li>
                        <li>Delete all their billing records</li>
                        <li>Revoke tenant portal access</li>
                        <li>Make the unit available for a new tenant</li>
                    </ul>
                </div>
                <p style="font-size: 14px; color: var(--gray); text-align: center;">
                    If this tenant has outstanding bills, removal will be blocked. Please settle all balances first.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelDelete">Cancel</button>
                <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">Yes, Remove Completely</button>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const addTenantBtn = document.getElementById('addTenantBtn');
        const tenantModal = document.getElementById('tenantModal');
        const editTenantModal = document.getElementById('editTenantModal');
        const deleteModal = document.getElementById('deleteModal');
        const closeModal = document.getElementById('closeModal');
        const closeEditModal = document.getElementById('closeEditModal');
        const closeDeleteModal = document.getElementById('closeDeleteModal');
        const cancelTenant = document.getElementById('cancelTenant');
        const cancelEditTenant = document.getElementById('cancelEditTenant');
        const cancelDelete = document.getElementById('cancelDelete');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

        addTenantBtn.addEventListener('click', () => {
            tenantModal.style.display = 'flex';
        });

        closeModal.addEventListener('click', () => {
            tenantModal.style.display = 'none';
        });

        cancelTenant.addEventListener('click', () => {
            tenantModal.style.display = 'none';
        });

        closeEditModal.addEventListener('click', () => {
            editTenantModal.style.display = 'none';
        });

        cancelEditTenant.addEventListener('click', () => {
            editTenantModal.style.display = 'none';
        });

        closeDeleteModal.addEventListener('click', () => {
            deleteModal.style.display = 'none';
        });

        cancelDelete.addEventListener('click', () => {
            deleteModal.style.display = 'none';
        });

        // Edit tenant modal functionality
        function openEditModal(tenantId, name, idNumber, phoneNumber, secondaryPhone, unitNumber, propertyName, hasWifi, wifiFee, hasGarbage, initialWaterReading) {
            document.getElementById('editTenantId').value = tenantId;
            document.getElementById('editName').value = name;
            document.getElementById('editIdNumber').value = idNumber;
            document.getElementById('editPhoneNumber').value = phoneNumber;
            document.getElementById('editSecondaryPhoneNumber').value = secondaryPhone || '';
            document.getElementById('editHasWifi').checked = hasWifi == '1';
            document.getElementById('editWifiFee').value = wifiFee || 0;
            document.getElementById('editHasGarbage').checked = hasGarbage == '1';
            document.getElementById('editInitialWaterReading').value = initialWaterReading || 0;
            
            // Toggle containers
            document.getElementById('editWifiFeeContainer').style.visibility = hasWifi == '1' ? 'visible' : 'hidden';
            
            editTenantModal.style.display = 'flex';
        }

        // Delete confirmation modal functionality
        function confirmDelete(tenantId, tenantName) {
            document.getElementById('deleteTenantName').textContent = tenantName;
            deleteModal.style.display = 'flex';

            // Handle delete confirmation
            confirmDeleteBtn.onclick = function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';

                const tenantIdInput = document.createElement('input');
                tenantIdInput.type = 'hidden';
                tenantIdInput.name = 'delete_tenant_id';
                tenantIdInput.value = tenantId;
                form.appendChild(tenantIdInput);

                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'delete_tenant';
                submitInput.value = '1';
                form.appendChild(submitInput);

                document.body.appendChild(form);
                form.submit();
            };
        }

        // Dynamic unit filtering
        const propertySelect = document.getElementById('propertySelect');
        const unitSelect = document.getElementById('unitSelect');
        const customRentAmount = document.getElementById('customRentAmount');
        const unitOptions = Array.from(unitSelect.options).slice(1); // Exclude first "Select Unit"

        function syncSelectedUnitRent() {
            const selectedOption = unitSelect.options[unitSelect.selectedIndex];
            if (selectedOption && selectedOption.dataset && selectedOption.dataset.rent !== undefined) {
                const rentValue = parseFloat(selectedOption.dataset.rent);
                if (!Number.isNaN(rentValue)) {
                    customRentAmount.value = rentValue.toFixed(2);
                    return;
                }
            }
            customRentAmount.value = '';
        }

        propertySelect.addEventListener('change', function() {
            const propertyId = this.value;
            unitSelect.innerHTML = '<option value="">Select Unit</option>';
            customRentAmount.value = '';

            unitOptions.forEach(opt => {
                if (opt.dataset.property === propertyId) {
                    unitSelect.appendChild(opt);
                }
            });
        });

        unitSelect.addEventListener('change', syncSelectedUnitRent);

        // WiFi fee toggling
        const hasWifiBill = document.getElementById('hasWifiBill');
        const wifiFeeContainer = document.getElementById('wifiFeeContainer');
        const editHasWifi = document.getElementById('editHasWifi');
        const editWifiFeeContainer = document.getElementById('editWifiFeeContainer');

        hasWifiBill.addEventListener('change', function() {
            wifiFeeContainer.style.visibility = this.checked ? 'visible' : 'hidden';
        });

        editHasWifi.addEventListener('change', function() {
            editWifiFeeContainer.style.visibility = this.checked ? 'visible' : 'hidden';
        });

        // Close modal when clicking outside
        tenantModal.addEventListener('click', (e) => {
            if (e.target === tenantModal) {
                tenantModal.style.display = 'none';
            }
        });

        editTenantModal.addEventListener('click', (e) => {
            if (e.target === editTenantModal) {
                editTenantModal.style.display = 'none';
            }
        });

        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
        });

        // Close notifications
        document.querySelectorAll('.notification-close').forEach(button => {
            button.addEventListener('click', (e) => {
                e.target.closest('.notification').style.display = 'none';
            });
        });
    </script>
</body>
</html>

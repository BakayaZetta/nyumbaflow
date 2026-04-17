<?php
require_once 'db_config.php';
require_once 'sanitize.php';
require_once 'csrf_token.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: super_login.php");
    exit;
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_approver') {
            $approver_name = trim($_POST['approver_name'] ?? '');
            $approver_email = trim($_POST['approver_email'] ?? '');

            if ($approver_name === '' || $approver_email === '' || !filter_var($approver_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please provide a valid approver name and email.');
            }

            $stmt = $pdo->prepare("INSERT INTO approver_settings (approver_name, approver_email, is_active) VALUES (?, ?, 1)");
            $stmt->execute([$approver_name, $approver_email]);
            $message = 'Approver added successfully.';
        }

        if ($action === 'toggle_approver') {
            $id = (int)($_POST['id'] ?? 0);
            $is_active = (int)($_POST['is_active'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Invalid approver ID.');
            }

            $stmt = $pdo->prepare("UPDATE approver_settings SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $id]);
            $message = 'Approver status updated.';
        }

        if ($action === 'delete_approver') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Invalid approver ID.');
            }

            $stmt = $pdo->prepare("DELETE FROM approver_settings WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Approver removed.';
        }

        if ($action === 'update_rules') {
            $email_hours = (int)($_POST['email_verification_expiry_hours'] ?? 24);
            $code_minutes = (int)($_POST['login_code_expiry_minutes'] ?? 10);

            if ($email_hours < 1 || $email_hours > 168) {
                throw new Exception('Email verification expiry must be between 1 and 168 hours.');
            }
            if ($code_minutes < 1 || $code_minutes > 60) {
                throw new Exception('Login verification code expiry must be between 1 and 60 minutes.');
            }

            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute(['email_verification_expiry_hours', (string)$email_hours, 'Email verification link expiry in hours']);
            $stmt->execute(['login_code_expiry_minutes', (string)$code_minutes, 'Login 6-digit code expiry in minutes']);

            $message = 'Workflow settings updated.';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

$approvers = $pdo->query("SELECT id, approver_name, approver_email, is_active, created_at, updated_at FROM approver_settings ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$email_hours = (int)($settings['email_verification_expiry_hours'] ?? 24);
$code_minutes = (int)($settings['login_code_expiry_minutes'] ?? 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Settings - Nyumbaflow Super Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0ea5e9;
            --danger: #ef4444;
            --success: #10b981;
            --dark: #0f172a;
            --slate: #1e293b;
        }

        body {
            background: #f8fafc;
            color: var(--dark);
            font-family: 'Inter', sans-serif;
            display: flex;
            min-height: 100vh;
            margin: 0;
        }

        .sidebar {
            width: 250px;
            background: var(--dark);
            color: white;
            padding: 30px 20px;
            flex-shrink: 0;
        }

        .main {
            flex: 1;
            padding: 40px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .card h2 {
            margin: 0 0 16px;
            font-size: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .row {
            margin-bottom: 14px;
        }

        label {
            display: block;
            font-size: 13px;
            margin-bottom: 6px;
            color: #64748b;
            font-weight: 600;
        }

        input[type="text"],
        input[type="email"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-sizing: border-box;
            font-family: inherit;
        }

        input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgb(14 165 233 / 0.15);
        }

        .btn {
            padding: 9px 12px;
            border-radius: 8px;
            border: none;
            font-size: 12px;
            cursor: pointer;
            color: white;
            font-weight: 600;
            background: var(--primary);
        }

        .btn:hover {
            opacity: 0.92;
        }

        .btn-muted {
            background: #475569;
        }

        .btn-danger {
            background: var(--danger);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #f1f5f9;
            color: #64748b;
            font-size: 13px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .status {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-on {
            background: #dcfce7;
            color: #166534;
        }

        .status-off {
            background: #fee2e2;
            color: #991b1b;
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

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .inline {
            display: inline-flex;
            gap: 6px;
            align-items: center;
            margin-right: 6px;
        }

        .top-links {
            margin-top: 10px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .top-links a {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .top-links .link-dashboard {
            background: #f1f5f9;
            color: #334155;
        }

        .top-links .link-approvals {
            background: #e0f2fe;
            color: #075985;
        }

        @media (max-width: 1100px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .main {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color: var(--primary); margin-bottom: 40px;">HomeSync SA</h2>
        <nav>
            <a href="super_dashboard.php" style="display:block; color:#cbd5e1; text-decoration:none; padding:10px; border-radius:8px; margin-bottom:8px;">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a href="super_admin_approvals.php" style="display:block; color:#cbd5e1; text-decoration:none; padding:10px; border-radius:8px; margin-bottom:8px;">
                <i class="fas fa-user-check"></i> Approvals
            </a>
            <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-cogs"></i> Settings
            </div>
            <p style="margin-top: 50px; opacity: 0.5; font-size: 12px;">Logged in as: <?php echo htmlspecialchars($_SESSION['superadmin_name'] ?? 'Superadmin'); ?></p>
            <a href="logout.php" style="color: #94a3b8; text-decoration: none; font-size: 13px; display: block; margin-top: 10px;">Logout</a>
        </nav>
    </div>

    <div class="main">
        <header style="margin-bottom: 30px;">
            <h1>Approval Workflow Settings</h1>
            <div class="top-links">
                <a class="link-dashboard" href="super_dashboard.php">Back to Dashboard</a>
                <a class="link-approvals" href="super_admin_approvals.php">Open Approvals</a>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="alert <?php echo $message_type === 'error' ? 'alert-error' : 'alert-success'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="grid">
            <section class="card">
                <h2>Add Approver</h2>
                <form method="post">
                    <?php echo get_csrf_token_field(); ?>
                    <input type="hidden" name="action" value="add_approver">
                    <div class="row">
                        <label for="approver_name">Approver Name</label>
                        <input id="approver_name" type="text" name="approver_name" required>
                    </div>
                    <div class="row">
                        <label for="approver_email">Approver Email</label>
                        <input id="approver_email" type="email" name="approver_email" required>
                    </div>
                    <button type="submit" class="btn">Add Approver</button>
                </form>
            </section>

            <section class="card">
                <h2>Rules</h2>
                <form method="post">
                    <?php echo get_csrf_token_field(); ?>
                    <input type="hidden" name="action" value="update_rules">
                    <div class="row">
                        <label for="email_hours">Email Verification Expiry (hours)</label>
                        <input id="email_hours" type="number" min="1" max="168" name="email_verification_expiry_hours" value="<?php echo (int)$email_hours; ?>" required>
                    </div>
                    <div class="row">
                        <label for="code_minutes">Login Code Expiry (minutes)</label>
                        <input id="code_minutes" type="number" min="1" max="60" name="login_code_expiry_minutes" value="<?php echo (int)$code_minutes; ?>" required>
                    </div>
                    <button type="submit" class="btn">Save Rules</button>
                </form>
            </section>
        </div>

        <section class="card">
            <h2>Configured Approvers</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approvers as $approver): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($approver['approver_name']); ?></td>
                            <td><?php echo htmlspecialchars($approver['approver_email']); ?></td>
                            <td>
                                <?php if ((int)$approver['is_active'] === 1): ?>
                                    <span class="status status-on">Active</span>
                                <?php else: ?>
                                    <span class="status status-off">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($approver['updated_at']))); ?></td>
                            <td>
                                <form method="post" class="inline">
                                    <?php echo get_csrf_token_field(); ?>
                                    <input type="hidden" name="action" value="toggle_approver">
                                    <input type="hidden" name="id" value="<?php echo (int)$approver['id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo (int)$approver['is_active'] === 1 ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-muted"><?php echo (int)$approver['is_active'] === 1 ? 'Disable' : 'Enable'; ?></button>
                                </form>
                                <form method="post" class="inline" onsubmit="return confirm('Delete this approver?');">
                                    <?php echo get_csrf_token_field(); ?>
                                    <input type="hidden" name="action" value="delete_approver">
                                    <input type="hidden" name="id" value="<?php echo (int)$approver['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
</body>
</html>

<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_config.php';
require_once 'rate_limit.php';
require_once 'csrf_token.php';

$error = "";
$success = "";

// Bootstrap: Create superadmin if none exists
$checkSA = $pdo->prepare("SELECT COUNT(*) FROM landlords WHERE role = 'superadmin'");
$checkSA->execute();
$hasSuperadmin = $checkSA->fetchColumn() > 0;

if (!$hasSuperadmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bootstrap'])) {
    // Rate limit bootstrap attempts: 3 per hour
    if (!check_rate_limit('bootstrap', 3, 60)) {
        $error = "Too many bootstrap attempts. Please try again later.";
    } else {
        $user = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        $secret = $_POST['bootstrap_secret'] ?? '';

        // Validate bootstrap secret
        if (!defined('BOOTSTRAP_SECRET') || BOOTSTRAP_SECRET === 'CHANGE_ME_ON_DEPLOY') {
            $error = "Bootstrap secret has not been configured. Set BOOTSTRAP_SECRET in config.php before first use.";
        } elseif (!hash_equals(BOOTSTRAP_SECRET, $secret)) {
            record_failed_attempt('bootstrap');
            $error = "Invalid bootstrap secret.";
        }
        // Validate username: 3-50 alphanumeric/underscore
        elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $user)) {
            $error = "Username must be 3-50 characters (letters, numbers, underscores only).";
        }
        // Validate email format
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        }
        // Validate password strength
        elseif (strlen($pass) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $pass) || !preg_match('/[a-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
            $error = "Password must contain at least one uppercase letter, one lowercase letter, and one digit.";
        } else {
            // All validations passed — create superadmin
            try {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $ins = $pdo->prepare("INSERT INTO landlords (username, email, password, role) VALUES (?, ?, ?, 'superadmin')");
                $ins->execute([$user, $email, $hashed]);
                $success = "Superadmin account created successfully! Please login now.";
                $hasSuperadmin = true; // flip flag so login form shows
                error_log("[BOOTSTRAP] Superadmin created by IP " . get_client_ip() . " at " . date('Y-m-d H:i:s'));
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Username or email already exists.";
                } else {
                    error_log("[BOOTSTRAP ERROR] " . $e->getMessage());
                    $error = "An internal error occurred. Please try again.";
                }
            }
        }
    }
}

// Normal login handler
if ($hasSuperadmin && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bootstrap'])) {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (!check_rate_limit('super_login', 5, 15)) {
        $error = "Too many failed login attempts. Please try again after 15 minutes.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM landlords WHERE email = ? AND role = 'superadmin'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($pass, $admin['password'])) {
            clear_attempts('super_login');
            session_regenerate_id(true);
            $_SESSION['superadmin_id'] = $admin['id'];
            $_SESSION['superadmin_name'] = $admin['username'];
            header("Location: super_dashboard.php");
            exit();
        } else {
            record_failed_attempt('super_login');
            $error = "Invalid superadmin credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Superadmin Login - HomeSync</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .login-card { background: #1e293b; padding: 40px; border-radius: 20px; width: 380px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        h1 { font-size: 24px; margin-bottom: 20px; text-align: center; color: #38bdf8; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-size: 13px; color: #94a3b8; }
        input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: #0ea5e9; border: none; border-radius: 8px; color: white; font-weight: 700; cursor: pointer; margin-top: 10px; transition: background 0.2s; }
        .btn:hover { background: #0284c7; }
        .error { color: #f43f5e; font-size: 13px; text-align: center; margin-bottom: 15px; }
        .success { color: #10b981; font-size: 13px; text-align: center; margin-bottom: 15px; }
        .hint { font-size: 11px; color: #64748b; margin-top: 4px; }
        .shield-icon { text-align: center; font-size: 40px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="shield-icon">🛡️</div>
        <h1>Nyumbaflow</h1><h1>SuperAdmin</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!$hasSuperadmin): ?>
            <p style="font-size: 12px; color: #94a3b8; margin-bottom: 15px;">No superadmin found. Bootstrap the first account:</p>
            <form method="POST" autocomplete="off">
                <?php echo get_csrf_token_field(); ?>
                <input type="hidden" name="bootstrap" value="1">
                <div class="form-group">
                    <label>Bootstrap Secret</label>
                    <input type="password" name="bootstrap_secret" required placeholder="Enter deploy secret">
                    <div class="hint">Set in config.php → BOOTSTRAP_SECRET</div>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+" placeholder="e.g. admin">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="admin@example.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="8" placeholder="Min 8 chars, mixed case + digit">
                    <div class="hint">Must include uppercase, lowercase, and a number</div>
                </div>
                <button type="submit" class="btn">🔐 Create SuperAdmin</button>
            </form>
        <?php else: ?>
            <form method="POST">
                <?php echo get_csrf_token_field(); ?>
                <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                <button type="submit" class="btn">Login to Dashboard</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'session_check.php';
require_once 'db_config.php';
session_start();
require_once 'db_config.php';

$error = "";

// Bootstrap: Create superadmin if none exists
$checkSA = $pdo->query("SELECT COUNT(*) FROM landlords WHERE role = 'superadmin'");
if ($checkSA->fetchColumn() == 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bootstrap'])) {
        $user = trim($_POST['username']);
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = trim($_POST['email']);
        
        $ins = $pdo->prepare("INSERT INTO landlords (username, email, password, role) VALUES (?, ?, ?, 'superadmin')");
        $ins->execute([$user, $email, $pass]);
        $error = "Superadmin created! Please login now.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bootstrap'])) {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM landlords WHERE email = ? AND role = 'superadmin'");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($pass, $admin['password'])) {
        $_SESSION['superadmin_id'] = $admin['id'];
        $_SESSION['superadmin_name'] = $admin['username'];
        header("Location: super_dashboard.php");
        exit();
    } else {
        $error = "Invalid superadmin credentials.";
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
        .login-card { background: #1e293b; padding: 40px; border-radius: 20px; width: 350px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        h1 { font-size: 24px; margin-bottom: 20px; text-align: center; color: #38bdf8; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-size: 13px; color: #94a3b8; }
        input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: #0ea5e9; border: none; border-radius: 8px; color: white; font-weight: 700; cursor: pointer; margin-top: 10px; }
        .error { color: #f43f5e; font-size: 13px; text-align: center; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>HomeSync</h1><h1>SuperAdmin</h1>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php 
        $checkSA = $pdo->query("SELECT COUNT(*) FROM landlords WHERE role = 'superadmin'");
        if ($checkSA->fetchColumn() == 0): 
        ?>
            <p style="font-size: 12px; color: #94a3b8; margin-bottom: 15px;">No superadmin found. Bootstrap the first account:</p>
            <form method="POST">
                <input type="hidden" name="bootstrap" value="1">
                <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                <button type="submit" class="btn">Create SuperAdmin</button>
            </form>
        <?php else: ?>
            <form method="POST">
                <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                <button type="submit" class="btn">Login to Dashboard</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

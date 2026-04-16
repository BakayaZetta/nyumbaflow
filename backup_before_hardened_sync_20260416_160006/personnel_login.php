<?php
session_start();
require_once 'db_config.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role']; // 'gate' or 'caretaker'

    if ($role === 'gate') {
        $stmt = $pdo->prepare("SELECT * FROM gate_personnel WHERE username = ?");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM caretakers WHERE username = ?");
    }
    
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['personnel_id'] = $user['id'];
        $_SESSION['personnel_name'] = $user['full_name'];
        $_SESSION['personnel_role'] = $role;
        $_SESSION['personnel_property_id'] = $user['property_id'];
        
        if ($role === 'gate') {
            header("Location: gate.php");
        } else {
            header("Location: caretaker_portal.php");
        }
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Personnel Login - Nyumbaflow</title>
    <link rel="shortcut icon" href="../icons/home.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .login-card { background: #1e293b; padding: 40px; border-radius: 20px; width: 350px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        h1 { font-size: 24px; margin-bottom: 20px; text-align: center; color: #38bdf8; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-size: 13px; color: #94a3b8; }
        input, select { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: #0ea5e9; border: none; border-radius: 8px; color: white; font-weight: 700; cursor: pointer; margin-top: 10px; }
        .error { color: #f43f5e; font-size: 13px; text-align: center; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Nyumbaflow</h1>
        <p style="text-align:center; color:#94a3b8; margin-bottom:20px;">Personnel Portal</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Login As</label>
                <select name="role" required>
                    <option value="caretaker">Caretaker</option>
                    <option value="gate">Gate Personnel</option>
                </select>
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login to Portal</button>
        </form>
        <p style="text-align:center; margin-top:20px; font-size:12px; color:#64748b;">
            Landlord? <a href="auth.html" style="color:#38bdf8; text-decoration:none;">Login here</a>
        </p>
    </div>
</body>
</html>

<?php
session_start();
require_once '../db_config.php';

$error = '';

// If already logged in, redirect to gate dashboard
if (isset($_SESSION['security_id'])) {
    header("Location: index2.php");
    exit();
}

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM gate_personnel WHERE username = ?");
            $stmt->execute([$username]);
            $personnel = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($personnel && password_verify($password, $personnel['password'])) {
                // Login successful
                $_SESSION['security_id'] = $personnel['id'];
                $_SESSION['security_name'] = $personnel['full_name'];
                $_SESSION['property_id'] = $personnel['property_id'];
                $_SESSION['last_activity'] = time();
                
                header("Location: index2.php");
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            error_log("Gate login error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Personnel Login - HomeSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #0461d3;
            --accent: #1867ff;
            --error: #e74c3c;
            --bg-primary: #f0f7ff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 420px;
            width: 100%;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .login-icon {
            font-size: 48px;
            color: var(--accent);
            margin-bottom: 16px;
        }
        
        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: #01172e;
            margin-bottom: 8px;
        }
        
        .login-subtitle {
            font-size: 14px;
            color: #6c87a8;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #3a506b;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d4e5ff;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(24, 103, 255, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-login:hover {
            background: var(--primary-blue);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(24, 103, 255, 0.3);
        }
        
        .error-message {
            background: #fee;
            color: var(--error);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #fcc;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #6c87a8;
        }
        
        .login-footer a {
            color: var(--accent);
            text-decoration: none;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title">Gate Personnel Login</h1>
            <p class="login-subtitle">HomeSync Security Portal</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control" 
                       placeholder="Enter your username" required autofocus 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> HomeSync. All rights reserved.</p>
            <p>Contact admin if you forgot your credentials</p>
        </div>
    </div>
</body>
</html>

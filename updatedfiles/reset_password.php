<?php
require_once 'db_config.php';
require_once 'csrf_token.php';

$token = $_GET['token'] ?? '';
$valid_token = false;

if (!empty($token)) {
    // Verify token exists and is not expired
    $stmt = $pdo->prepare("SELECT id FROM landlords WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $valid_token = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - HomeSync</title>
    <link rel="shortcut icon" href="icons/home.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 450px;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h2 { color: var(--primary); font-size: 2rem; }
        .logo span { color: var(--secondary); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .error-box {
            background: #fee2e2;
            color: #b91c1c;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .success-box {
            background: #dcfce7;
            color: #166534;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h2>Nyumba<span>flow</span></h2>
        </div>

        <?php if (!$valid_token): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-triangle fa-3x" style="margin-bottom: 15px;"></i>
                <h3>Invalid Reset Link</h3>
                <p style="margin-top: 10px;">This password reset link is invalid or has expired. Please request a new one from the login page.</p>
                <a href="auth.php" class="btn" style="display: block; margin-top: 20px; text-decoration: none;">Back to Login</a>
            </div>
        <?php else: ?>
            <div id="reset-content">
                <h3 style="margin-bottom: 20px; text-align: center;">Set New Password</h3>
                <p style="color: var(--gray); font-size: 0.9rem; margin-bottom: 25px; text-align: center;">Please enter a strong new password for your account.</p>
                
                <form id="reset-password-form">
                    <?php echo get_csrf_token_field(); ?>
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" class="form-control" required minlength="8" placeholder="At least 8 characters">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" placeholder="Repeat password">
                    </div>
                    
                    <button type="submit" class="btn">Update Password</button>
                </form>
            </div>
            
            <div id="success-screen" style="display: none;">
                <div class="success-box">
                    <i class="fas fa-check-circle fa-4x" style="margin-bottom: 15px;"></i>
                    <h3>Success!</h3>
                    <p style="margin-top: 10px;">Your password has been updated securely.</p>
                </div>
                <a href="auth.php" class="btn" style="display: block; text-decoration: none; text-align: center;">Login Now</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('reset-password-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                alert("Passwords do not match!");
                return;
            }
            
            const formData = new FormData(this);
            fetch('auth_action.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('reset-content').style.display = 'none';
                    document.getElementById('success-screen').style.display = 'block';
                } else {
                    alert(data.error || 'Failed to reset password');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('A server error occurred');
            });
        });
    </script>
</body>
</html>

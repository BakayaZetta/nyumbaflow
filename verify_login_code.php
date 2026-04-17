<?php
require_once 'config.php';
require_once 'csrf_token.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user has temp session
if (!isset($_SESSION['temp_admin_id'])) {
    header('Location: auth.php');
    exit;
}

require_once 'db_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login Code - Nyumbaflow</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
        }
        .pending-notice {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
            color: #856404;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        .code-input {
            display: flex;
            gap: 5px;
            justify-content: space-between;
        }
        .code-input input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 5px;
            transition: border-color 0.3s;
        }
        .code-input input:focus {
            outline: none;
            border-color: #667eea;
        }
        .code-input input.error {
            border-color: #dc3545;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #5568d3;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .message {
            margin-top: 15px;
            padding: 12px;
            border-radius: 5px;
            text-align: center;
            display: none;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        .resend-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }
        .resend-link a {
            color: #667eea;
            text-decoration: none;
            cursor: pointer;
        }
        .resend-link a:hover {
            text-decoration: underline;
        }
        .timer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Verify Your Login</h1>
        
        <div class="pending-notice">
            <strong>Note:</strong> Your account is pending approval. You can access the application with limited functionality until your account is approved.
        </div>
        
        <p style="text-align: center; color: #666; margin-bottom: 20px; font-size: 14px;">
            Enter the 6-digit code sent to your email and SMS
        </p>
        
        <form id="verificationForm">
            <div class="form-group">
                <label>Verification Code</label>
                <div class="code-input">
                    <input type="text" name="digit1" maxlength="1" inputmode="numeric" required>
                    <input type="text" name="digit2" maxlength="1" inputmode="numeric" required>
                    <input type="text" name="digit3" maxlength="1" inputmode="numeric" required>
                    <input type="text" name="digit4" maxlength="1" inputmode="numeric" required>
                    <input type="text" name="digit5" maxlength="1" inputmode="numeric" required>
                    <input type="text" name="digit6" maxlength="1" inputmode="numeric" required>
                </div>
            </div>
            
            <button type="submit">Verify and Login</button>
        </form>
        
        <div id="message" class="message"></div>
        
        <div class="resend-link">
            <p>Didn't receive the code? <a href="#" onclick="resendCode(); return false;">Resend</a></p>
        </div>
        
        <div class="timer" id="timer"></div>
    </div>

    <script>
        const csrfToken = '<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';

        // Auto-advance to next input
        const codeInputs = document.querySelectorAll('.code-input input');
        codeInputs.forEach((input, index) => {
            input.addEventListener('keyup', (e) => {
                if (e.key >= '0' && e.key <= '9') {
                    if (index < codeInputs.length - 1) {
                        codeInputs[index + 1].focus();
                    }
                } else if (e.key === 'Backspace') {
                    input.value = '';
                    if (index > 0) {
                        codeInputs[index - 1].focus();
                    }
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                if (/^\d{6}$/.test(paste)) {
                    paste.split('').forEach((char, i) => {
                        codeInputs[i].value = char;
                    });
                    codeInputs[5].focus();
                }
            });
        });

        document.getElementById('verificationForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const code = Array.from(codeInputs).map(i => i.value).join('');
            
            if (code.length !== 6) {
                showMessage('Please enter all 6 digits', 'error');
                return;
            }
            
            try {
                const response = await fetch('verify_login_code_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'code=' + encodeURIComponent(code) + '&csrf_token=' + encodeURIComponent(csrfToken)
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    showMessage(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect || 'index.php';
                    }, 1500);
                } else {
                    showMessage(data.error, 'error');
                    codeInputs.forEach(i => {
                        i.value = '';
                        i.classList.add('error');
                    });
                    setTimeout(() => {
                        codeInputs.forEach(i => i.classList.remove('error'));
                        codeInputs[0].focus();
                    }, 1000);
                }
            } catch (error) {
                showMessage('An error occurred. Please try again.', 'error');
            }
        });

        function showMessage(text, type) {
            const msg = document.getElementById('message');
            msg.textContent = text;
            msg.className = 'message ' + type;
        }

        function resendCode() {
            fetch('resend_verification_code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(csrfToken)
            })
                .then(r => r.json())
                .then(d => showMessage(d.message, d.success ? 'success' : 'error'))
                .catch(e => showMessage('Error resending code', 'error'));
        }

        // Start timer
        let timeRemaining = 600; // 10 minutes
        function updateTimer() {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            document.getElementById('timer').textContent = `Code expires in: ${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeRemaining > 0) {
                timeRemaining--;
                setTimeout(updateTimer, 1000);
            } else {
                showMessage('Code has expired. Please log in again.', 'error');
                setTimeout(() => window.location.href = 'auth.php', 3000);
            }
        }
        updateTimer();

        // Focus first input
        codeInputs[0].focus();
    </script>
</body>
</html>

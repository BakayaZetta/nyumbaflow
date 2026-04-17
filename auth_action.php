<?php
// Load config first to set session cookie parameters
require_once 'config.php';

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress notices and warnings during form processing
error_reporting(E_ERROR | E_PARSE);

require_once 'db_config.php';
require_once 'rate_limit.php';
require_once 'csrf_token.php';
require_once 'EmailService.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Validate CSRF token for all POST actions
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid request. Please try again.']);
        exit;
    }
    
    switch ($action) {
        case 'signup':
            handleSignup();
            break;
        case 'login':
            handleLogin();
            break;
        case 'forgot':
            handleForgotPassword();
            break;
        case 'reset':
            handleResetPassword();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleSignup() {
    global $pdo;
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($phone_number)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required (name, email, phone, password)']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        return;
    }
    
    if ($password !== $confirm_password) {
        http_response_code(400);
        echo json_encode(['error' => 'Passwords do not match']);
        return;
    }
    
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 8 characters long']);
        return;
    }
    
    // Check if email already exists
    try {
        $stmt = $pdo->prepare("SELECT id FROM landlords WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already registered']);
            return;
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        return;
    }
    
    try {
        $pdo->beginTransaction();

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user with pending_verification status
        $stmt = $pdo->prepare("INSERT INTO landlords (username, email, password, phone_number, status) VALUES (?, ?, ?, ?, 'pending_verification')");
        $stmt->execute([$name, $email, $hashed_password, $phone_number]);
        
        $user_id = $pdo->lastInsertId();
        
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Store verification token
        $stmt = $pdo->prepare("INSERT INTO email_verifications (landlord_id, email, token, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $email, $token, $expires_at]);
        
        // Send verification email
        $emailService = new EmailService();
        $email_sent = $emailService->sendEmailVerification($email, $token);

        if (!$email_sent) {
            $host = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
            $is_local = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;

            if ($is_local) {
                $pdo->commit();
                $verification_link = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? '/') . "/verify_email.php?token=" . urlencode($token);

                echo json_encode([
                    'success' => true,
                    'message' => 'Account created, but email delivery failed in local environment. Use the verification link below to continue testing.',
                    'verification_link' => $verification_link,
                    'redirect' => 'auth.php'
                ]);
                return;
            }

            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'We could not send verification email right now. Please try again in a few minutes.']);
            return;
        }

        $pdo->commit();
        
        // Log audit
        logAudit('user_signup', 'landlord', $user_id, null, "User signed up: $email");
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Please check your email to verify your account.',
            'redirect' => 'auth.php'
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
}



function handleLogin() {
    global $pdo;
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }

    // Check rate limit
    if (!check_rate_limit('landlord_login', 5, 15)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many failed login attempts. Please try again after 15 minutes.']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, password, phone_number, status FROM landlords WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $pdo->errorInfo()[2]);
        }
        
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            // User not found
            record_failed_attempt('landlord_login');
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
            return;
        }
        
        if (!password_verify($password, $admin['password'])) {
            // Password doesn't match
            record_failed_attempt('landlord_login');
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
            return;
        }

        // Check account status
        if ($admin['status'] === 'pending_verification') {
            http_response_code(403);
            echo json_encode(['error' => 'Your account is pending email verification. Please check your email for the verification link.']);
            return;
        }

        if ($admin['status'] === 'rejected') {
            http_response_code(403);
            echo json_encode(['error' => 'Your account has been rejected. Please contact support.']);
            return;
        }

        if ($admin['status'] === 'pending_approval') {
            // Account pending approval - allow login but send verification code
            $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store verification code
            $stmt = $pdo->prepare("INSERT INTO login_verification_codes (landlord_id, code, method, expires_at) VALUES (?, ?, 'both', ?)");
            $stmt->execute([$admin['id'], $verification_code, $expires_at]);
            
            // Send verification code via email
            $emailService = new EmailService();
            $emailService->send($admin['email'], 
                'Login Verification Code - Nyumbaflow', 
                "Your 6-digit verification code is: <strong>$verification_code</strong><br>This code expires in 10 minutes.<br><br>Please note: Your account is pending approval and you have limited access until approved.");
            
            // Send verification code via SMS if phone exists
            if (!empty($admin['phone_number'])) {
                require_once 'SmsService.php';
                $smsService = new SmsService();
                $smsService->sendSms($admin['phone_number'], "Your Nyumbaflow verification code is: $verification_code. Code expires in 10 minutes. Account pending approval.");
            }
            
            // Store user ID in session temporarily for verification
            $_SESSION['temp_admin_id'] = $admin['id'];
            $_SESSION['temp_admin_email'] = $admin['email'];
            $_SESSION['temp_admin_name'] = $admin['username'];
            $_SESSION['account_pending'] = true;
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'A 6-digit verification code has been sent to your email and SMS.',
                'requires_verification' => true,
                'pending_approval' => true,
                'redirect' => 'verify_login_code.php'
            ]);
            return;
        }

        if ($admin['status'] === 'banned') {
            http_response_code(403);
            echo json_encode(['error' => 'Your account has been banned. Please contact support.']);
            return;
        }
        
        // Clear failed attempts
        clear_attempts('landlord_login');
        
        // Regenerate session ID to prevent session fixation
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        
        // Set session variables for active account
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_name'] = $admin['username'];
        
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => 'index.php']);
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
}

function handleForgotPassword() {
    global $pdo;
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid email is required']);
        return;
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM landlords WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Generate secure 64-char token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update DB
        $updateStmt = $pdo->prepare("UPDATE landlords SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $updateStmt->execute([$token, $expires, $user['id']]);
        
        // Send Email
        $mail = new EmailService();
        $mail->sendPasswordReset($email, $token);
    }
    
    // Always show success to prevent email enumeration
    echo json_encode(['success' => true, 'message' => 'If this email is registered, you will receive password reset instructions']);
}

function handleResetPassword() {
    global $pdo;
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($token) || strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid token or password too short']);
        return;
    }
    
    // Verify token
    $stmt = $pdo->prepare("SELECT id FROM landlords WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired reset link. Please request a new one.']);
        return;
    }
    
    // Update password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare("UPDATE landlords SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
    $updateStmt->execute([$hashed_password, $user['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Your password has been reset successfully. You can now login.']);
}

function logAudit($action, $entity_type, $entity_id, $admin_id, $details) {
    global $pdo;
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare("INSERT INTO audit_log (action, entity_type, entity_id, admin_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$action, $entity_type, $entity_id, $admin_id, $details, $ip_address]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}
?>
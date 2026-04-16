<?php
session_start();
require_once 'db_config.php';
require_once 'rate_limit.php';
require_once 'EmailService.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
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
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required']);
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
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert admin
    try {
        $stmt = $pdo->prepare("INSERT INTO landlords (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $hashed_password]);
        
        $_SESSION['admin_id'] = $pdo->lastInsertId();
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_name'] = $name;
        
        echo json_encode(['success' => true, 'message' => 'Registration successful', 'redirect' => 'onboarding.html']);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
}

function handleLogin() {
    global $pdo;
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validation
    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }

    if (!check_rate_limit('landlord_login', 5, 15)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many failed login attempts. Please try again after 15 minutes.']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, password, status FROM landlords WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin || !password_verify($password, $admin['password'])) {
            record_failed_attempt('landlord_login');
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
            return;
        }

        if ($admin['status'] === 'banned') {
            http_response_code(403);
            echo json_encode(['error' => 'Your account has been banned. Please contact support.']);
            return;
        }
        
        clear_attempts('landlord_login');
        
        // Prevent Session Fixation
        session_regenerate_id(true);
        
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_name'] = $admin['username'];
        
        echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => 'index.php']);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
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
?>
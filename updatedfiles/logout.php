<?php
require_once __DIR__ . '/security_headers.php';

// Session Security Configurations
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

$redirect = "auth.html";
if (isset($_SESSION['personnel_role'])) {
    if ($_SESSION['personnel_role'] === 'gate' || $_SESSION['personnel_role'] === 'caretaker') {
        $redirect = "personnel_login.php";
    }
}

// Clear all session variables
$_SESSION = array();

// Also delete the session cookie securely.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to identified login page
header("Location: auth.php");
exit();
?>

<?php
session_start();

$redirect = "auth.html";
if (isset($_SESSION['personnel_role'])) {
    if ($_SESSION['personnel_role'] === 'gate' || $_SESSION['personnel_role'] === 'caretaker') {
        $redirect = "personnel_login.php";
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to identified login page
header("Location: $redirect");
exit();
?>

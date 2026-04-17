<?php
require_once 'config.php';
require_once 'db_config.php';

function checkSessionTimeout() {
    // Check if session has started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }

    // Check for session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        // Session has expired, destroy it
        session_unset();
        session_destroy();
        return false;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

function requireLogin() {
    if (!checkSessionTimeout()) {
        header("Location: auth.php");
        exit();
    }

    // Ensure pending accounts cannot access operational pages.
    enforceAccountAccessPolicy();
    
    // Regenerate session ID periodically to prevent fixation attacks
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function enforceAccountAccessPolicy() {
    global $pdo;

    $admin_id = (int)($_SESSION['admin_id'] ?? 0);
    if ($admin_id <= 0) {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT status FROM landlords WHERE id = ? LIMIT 1");
        $stmt->execute([$admin_id]);
        $account_status = $stmt->fetchColumn();

        $_SESSION['account_status'] = $account_status ?: 'unknown';

        if ($account_status === 'pending_approval') {
            $_SESSION['account_pending'] = true;
            $current_script = basename($_SERVER['PHP_SELF'] ?? '');
            $allowed_pages = ['pending_account.php', 'logout.php'];

            if (!in_array($current_script, $allowed_pages, true)) {
                header("Location: pending_account.php");
                exit();
            }
            return;
        }

        if ($account_status === 'active') {
            unset($_SESSION['account_pending']);
        }
    } catch (Exception $e) {
        error_log("Session account policy error: " . $e->getMessage());
    }
}

// Auto-check session on include
checkSessionTimeout();
?>

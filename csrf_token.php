<?php
/**
 * CSRF Token Utility Functions
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a CSRF token if one doesn't exist.
 */
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Verify a CSRF token.
 */
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Get the current CSRF token.
 */
if (!function_exists('get_csrf_token')) {
    function get_csrf_token() {
        return generate_csrf_token();
    }
}

/**
 * Get a hidden input field for the CSRF token.
 */
if (!function_exists('get_csrf_token_field')) {
    function get_csrf_token_field() {
        $token = get_csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}
?>

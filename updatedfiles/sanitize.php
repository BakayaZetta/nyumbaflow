<?php
/**
 * Global Sanitization Helpers
 * Centralized functions for XSS prevention and input validation
 */

/**
 * Escape output for HTML context (XSS Protection)
 */
if (!function_exists('esc')) {
    function esc($str) {
        if ($str === null) return '';
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Sanitize strings for database or logic
 */
if (!function_exists('sanitize_string')) {
    function sanitize_string($str, $maxLen = null) {
        $str = trim((string)$str);
        if ($maxLen !== null) {
            $str = mb_substr($str, 0, $maxLen, 'UTF-8');
        }
        return $str;
    }
}

/**
 * Sanitize integer inputs
 */
if (!function_exists('sanitize_int')) {
    function sanitize_int($val) {
        return (int)$val;
    }
}

/**
 * Sanitize float/decimal inputs
 */
if (!function_exists('sanitize_float')) {
    function sanitize_float($val) {
        return (float)$val;
    }
}
?>

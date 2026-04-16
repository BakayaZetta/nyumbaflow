<?php
/**
 * Security Headers for HomeSync
 * This file sets essential security headers to protect against XSS, clickjacking, 
 * MIME attacks, and HTTPS downgrade.
 */

// 1. Content Security Policy (CSP)
// Restricts sources for scripts, styles, and other resources to trusted origins.
$csp_rules = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
    "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
    "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com",
    "img-src 'self' data: https:",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'"
];
header("Content-Security-Policy: " . implode("; ", $csp_rules));

// 2. X-Frame-Options
// Prevent clickjacking by disallowing the page from being rendered in an iframe.
header("X-Frame-Options: SAMEORIGIN");

// 3. X-Content-Type-Options
// Prevent browsers from interpreting files as a different MIME type (MIME sniffing).
header("X-Content-Type-Options: nosniff");

// 4. Strict-Transport-Security (HSTS)
// Enforce HTTPS for 1 year (only effective if the site is accessed via HTTPS).
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// 5. Referrer-Policy
// Control how much referrer information is passed when navigating away from the site.
header("Referrer-Policy: strict-origin-when-cross-origin");

// 6. Permissions-Policy
// Disable unused browser features for enhanced privacy/security.
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()");

// 7. X-XSS-Protection
// Enable legacy XSS filtering in older browsers.
header("X-XSS-Protection: 1; mode=block");
?>

<?php
// Enforce strict session cookie parameters
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
}

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/scsrf.php';
// Nyumbaflow Configuration File
// This file contains all configuration settings for the application

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'homesync');
define('DB_USER', 'root');
define('DB_PASS', ''); // Set your database password here

// SMS Configuration (Celcom Africa)
define('SMS_API_KEY', ''); // Set your Celcom Africa API key
define('SMS_PARTNER_ID', ''); // Set your Celcom Africa Partner ID
define('SMS_SHORTCODE', 'Nyumbaflow');

// Session Configuration
define('SESSION_TIMEOUT', 600); // 10 minutes in seconds

// Application Settings
define('APP_NAME', 'Nyumbaflow');
define('APP_VERSION', '1.0.0');
// Default Rates (can be overridden per property)
define('DEFAULT_WATER_RATE', 200.00);
define('DEFAULT_WIFI_FEE', 1500.00);
define('DEFAULT_GARBAGE_FEE', 500.00);
define('DEFAULT_LATE_FEE_RATE', 100.00);

// Email Configuration (SMTP)
define('SMTP_HOST', 's');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_username');
define('SMTP_PASS', 'your_password');
define('MAIL_FROM_ADDRESS', '');
define('MAIL_FROM_NAME', 'Nyumbaflow Support');

// Debug Mode
define('DEBUG_MODE', false);

// Timezone
date_default_timezone_set('Africa/Nairobi');
?>

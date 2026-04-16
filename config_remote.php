<?php
// HomeSync Configuration File
// This file contains all configuration settings for the application

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'nusrwymj_homesync');
define('DB_USER', 'nusrwymj_nusrwymj');
define('DB_PASS', 'H=9^9;E8SQc^l_6H'); // Set your database password here

// SMS Configuration (Celcom Africa)
define('SMS_API_KEY', ''); // Set your Celcom Africa API key
define('SMS_PARTNER_ID', ''); // Set your Celcom Africa Partner ID
define('SMS_SHORTCODE', 'HOMESYNC');

// Session Configuration
define('SESSION_TIMEOUT', 600); // 10 minutes in seconds

// Application Settings
define('APP_NAME', 'HomeSync');
define('APP_VERSION', '1.0.0');

// File Upload Settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Security Settings
define('PASSWORD_MIN_LENGTH', 8);
define('TOKEN_LENGTH', 32); // For security tokens

// Default Rates (can be overridden per property)
define('DEFAULT_WATER_RATE', 200.00);
define('DEFAULT_WIFI_FEE', 1500.00);
define('DEFAULT_GARBAGE_FEE', 500.00);
define('DEFAULT_LATE_FEE_RATE', 100.00);

// Email Configuration (for future use)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@nyumbaflow.com');

// Debug Mode
define('DEBUG_MODE', false);

// Timezone
date_default_timezone_set('Africa/Nairobi');
?>

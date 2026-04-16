<?php
// HomeSync Configuration File - LOCAL XAMPP VERSION

// Database Configuration for XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'homesync_local');  // We'll create this database
define('DB_USER', 'root');             // XAMPP default username
define('DB_PASS', '');                 // XAMPP default password (empty)

// SMS Configuration (Celcom Africa)
define('SMS_API_KEY', '');
define('SMS_PARTNER_ID', '');
define('SMS_SHORTCODE', 'HOMESYNC');

// Session Configuration
define('SESSION_TIMEOUT', 600);

// Application Settings
define('APP_NAME', 'HomeSync');
define('APP_VERSION', '1.0.0');

// File Upload Settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Security Settings
define('PASSWORD_MIN_LENGTH', 8);
define('TOKEN_LENGTH', 32);

// Default Rates
define('DEFAULT_WATER_RATE', 200.00);
define('DEFAULT_WIFI_FEE', 1500.00);
define('DEFAULT_GARBAGE_FEE', 500.00);
define('DEFAULT_LATE_FEE_RATE', 100.00);

// Email Configuration
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@nyumbaflow.com');

// Debug Mode - SET TO TRUE FOR LOCAL DEVELOPMENT
define('DEBUG_MODE', true);

// Timezone
date_default_timezone_set('Africa/Nairobi');
?>
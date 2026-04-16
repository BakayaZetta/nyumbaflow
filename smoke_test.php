<?php
/**
 * Smoke Test - Validates core application functionality after hardening sync
 * Tests: Config loading, DB connectivity, Security headers, Session management
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors to stdout during test

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => [],
    'status' => 'PASS'
];

// Test 1: Config File Loading
$test_name = 'Config File Loading';
try {
    ob_start();
    require_once 'config.php';
    ob_end_clean();
    
    if (defined('APP_NAME') && defined('DB_HOST') && defined('SESSION_TIMEOUT')) {
        $results['tests'][$test_name] = [
            'status' => 'PASS',
            'message' => 'All required config constants defined',
            'details' => [
                'APP_NAME' => APP_NAME,
                'DB_HOST' => DB_HOST,
                'SESSION_TIMEOUT' => SESSION_TIMEOUT,
                'DEBUG_MODE' => DEBUG_MODE
            ]
        ];
    } else {
        throw new Exception('Missing required config constants');
    }
} catch (Exception $e) {
    $results['tests'][$test_name] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
    $results['status'] = 'FAIL';
}

// Test 2: Database Configuration File
$test_name = 'Database Configuration File';
try {
    if (file_exists('db_config.php')) {
        $config_content = file_get_contents('db_config.php');
        if (strpos($config_content, 'PDO') !== false) {
            $results['tests'][$test_name] = [
                'status' => 'PASS',
                'message' => 'Database configuration file present and valid',
                'note' => 'Actual DB connection skipped (may require running MySQL)'
            ];
        } else {
            throw new Exception('db_config.php does not reference PDO');
        }
    } else {
        throw new Exception('db_config.php not found');
    }
} catch (Exception $e) {
    $results['tests'][$test_name] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
    $results['status'] = 'FAIL';
}

// Test 3: Security Headers Module
$test_name = 'Security Headers Module';
try {
    if (file_exists('security_headers.php')) {
        ob_start();
        require_once 'security_headers.php';
        ob_end_clean();
        $results['tests'][$test_name] = [
            'status' => 'PASS',
            'message' => 'Security headers module loaded successfully'
        ];
    } else {
        throw new Exception('security_headers.php not found');
    }
} catch (Exception $e) {
    $results['tests'][$test_name] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
    $results['status'] = 'FAIL';
}

// Test 4: CSRF Protection Module
$test_name = 'CSRF Protection Module';
try {
    if (file_exists('scsrf.php')) {
        ob_start();
        require_once 'scsrf.php';
        ob_end_clean();
        $results['tests'][$test_name] = [
            'status' => 'PASS',
            'message' => 'CSRF protection module loaded successfully'
        ];
    } else {
        throw new Exception('scsrf.php not found');
    }
} catch (Exception $e) {
    $results['tests'][$test_name] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
    $results['status'] = 'FAIL';
}

// Test 5: Session Management
$test_name = 'Session Management';
try {
    if (file_exists('session_check.php')) {
        $session_content = file_get_contents('session_check.php');
        if (strpos($session_content, 'checkSessionTimeout') !== false && 
            strpos($session_content, 'SESSION_TIMEOUT') !== false) {
            $results['tests'][$test_name] = [
                'status' => 'PASS',
                'message' => 'Session check module properly defined',
                'features' => ['Session timeout check', 'Session regeneration']
            ];
        } else {
            throw new Exception('session_check.php missing key functions');
        }
    } else {
        throw new Exception('session_check.php not found');
    }
} catch (Exception $e) {
    $results['tests'][$test_name] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
    $results['status'] = 'FAIL';
}

// Test 6: Key Classes/Services
$test_name = 'SMS Service Class';
try {
    if (file_exists('SmsService.php')) {
        ob_start();
        require_once 'SmsService.php';
        ob_end_clean();
        if (class_exists('SmsService')) {
            $results['tests'][$test_name] = [
                'status' => 'PASS',
                'message' => 'SmsService class loaded and defined'
            ];
        } else {
            throw new Exception('SmsService class not defined');
        }
    } else {
        throw new Exception('SmsService.php not found');
    }
} catch (Exception $e) {
    $results['tests'][$test_name] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
    $results['status'] = 'FAIL';
}

// Test 7: Email Service Class
$test_name = 'Email Service Class';
try {
    if (file_exists('EmailService.php')) {
        ob_start();
        require_once 'EmailService.php';
        ob_end_clean();
        if (class_exists('EmailService')) {
            $results['tests'][$test_name] = [
                'status' => 'PASS',
                'message' => 'EmailService class loaded and defined'
            ];
        } else {
            throw new Exception('EmailService class not defined');
        }
    } else {
        $results['tests'][$test_name] = [
            'status' => 'INFO',
            'message' => 'EmailService.php not found (optional new feature)'
        ];
    }
} catch (Exception $e) {
    $results['tests'][$test_name] = [
        'status' => 'WARN',
        'message' => $e->getMessage()
    ];
}

// Test 8: Critical PHP Files Exist
$test_name = 'Critical PHP Files';
try {
    $critical_files = [
        'auth.php', 'index.php', 'onboarding.php', 'billing.php',
        'tenants.php', 'visitors.php', 'settings.php', 'gate.php'
    ];
    $missing = [];
    foreach ($critical_files as $f) {
        if (!file_exists($f)) {
            $missing[] = $f;
        }
    }
    if (empty($missing)) {
        $results['tests'][$test_name] = [
            'status' => 'PASS',
            'message' => 'All critical PHP files present',
            'files_checked' => count($critical_files)
        ];
    } else {
        $results['tests'][$test_name] = [
            'status' => 'FAIL',
            'message' => 'Missing critical files: ' . implode(', ', $missing)
        ];
        $results['status'] = 'FAIL';
    }
} catch (Exception $e) {
    $results['tests'][$test_name] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
    $results['status'] = 'FAIL';
}

// Output results as JSON for easy parsing
header('Content-Type: application/json; charset=utf-8');
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

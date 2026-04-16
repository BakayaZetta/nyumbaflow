<?php
/**
 * Database Connection Diagnostic
 * Checks MySQL availability and connection issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$diagnostic = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check 1: PHP PDO Extension
$check_name = 'PHP PDO Extension';
try {
    if (extension_loaded('pdo')) {
        if (extension_loaded('pdo_mysql')) {
            $diagnostic['checks'][$check_name] = [
                'status' => 'PASS',
                'message' => 'PDO and PDO_MySQL extensions loaded'
            ];
        } else {
            $diagnostic['checks'][$check_name] = [
                'status' => 'FAIL',
                'message' => 'PDO loaded but pdo_mysql extension missing'
            ];
        }
    } else {
        $diagnostic['checks'][$check_name] = [
            'status' => 'FAIL',
            'message' => 'PDO extension not loaded'
        ];
    }
} catch (Exception $e) {
    $diagnostic['checks'][$check_name] = [
        'status' => 'ERROR',
        'message' => $e->getMessage()
    ];
}

// Check 2: Config Constants
$check_name = 'Configuration Constants';
try {
    require_once 'config.php';
    
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
        $diagnostic['checks'][$check_name] = [
            'status' => 'PASS',
            'message' => 'All database config constants defined',
            'values' => [
                'DB_HOST' => DB_HOST,
                'DB_NAME' => DB_NAME,
                'DB_USER' => DB_USER,
                'DB_PASS' => '***HIDDEN***'
            ]
        ];
    } else {
        $diagnostic['checks'][$check_name] = [
            'status' => 'FAIL',
            'message' => 'Missing required config constants'
        ];
    }
} catch (Exception $e) {
    $diagnostic['checks'][$check_name] = [
        'status' => 'ERROR',
        'message' => $e->getMessage()
    ];
}

// Check 3: MySQL Connection Attempt
$check_name = 'MySQL Connection Attempt';
try {
    $attempt = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3
        ]
    );
    
    $version = $attempt->query("SELECT VERSION()")->fetchColumn();
    $diagnostic['checks'][$check_name] = [
        'status' => 'PASS',
        'message' => 'MySQL server is running and accessible',
        'mysql_version' => $version
    ];
    
    // Check 4: Available Databases
    $check_name = 'Available Databases';
    $result = $attempt->query("SHOW DATABASES");
    $databases = $result->fetchAll(PDO::FETCH_COLUMN);
    
    $app_databases = array_filter($databases, function($db) {
        return in_array($db, ['homesync', 'homesync_local', 'nyumbaflow']);
    });
    
    if (empty($app_databases)) {
        $diagnostic['checks'][$check_name] = [
            'status' => 'WARN',
            'message' => 'Application database not found',
            'all_databases' => $databases,
            'expected_names' => ['homesync', 'homesync_local', 'nyumbaflow'],
            'action' => 'Run init_db.php or create database manually'
        ];
    } else {
        $diagnostic['checks'][$check_name] = [
            'status' => 'PASS',
            'message' => 'Application database(s) found',
            'found' => $app_databases
        ];
        
        // Check 5: Database Selection
        $check_name = 'Database Selection';
        try {
            $db_attempt = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $diagnostic['checks'][$check_name] = [
                'status' => 'PASS',
                'message' => 'Connected to ' . DB_NAME . ' database',
                'database_name' => DB_NAME
            ];
            
            // Check 6: Key Table Existence
            $check_name = 'Database Tables';
            $result = $db_attempt->query("SHOW TABLES");
            $tables = $result->fetchAll(PDO::FETCH_COLUMN);
            $critical_tables = ['landlords', 'properties', 'units', 'tenants', 'bills', 'visitors'];
            $missing = array_diff($critical_tables, $tables);
            
            if (empty($missing)) {
                $diagnostic['checks'][$check_name] = [
                    'status' => 'PASS',
                    'message' => 'All critical tables exist',
                    'table_count' => count($tables),
                    'tables' => $tables
                ];
            } else {
                $diagnostic['checks'][$check_name] = [
                    'status' => 'WARN',
                    'message' => 'Some critical tables missing',
                    'missing_tables' => $missing,
                    'existing_tables' => $tables,
                    'action' => 'Run database migrations or init_db.php'
                ];
            }
        } catch (Exception $e) {
            $diagnostic['checks'][$check_name] = [
                'status' => 'FAIL',
                'message' => 'Cannot connect to ' . DB_NAME . ' database',
                'error' => $e->getMessage(),
                'suggestion' => 'Database may not exist. Try creating it first.'
            ];
        }
    }
    
} catch (PDOException $e) {
    $diagnostic['checks'][$check_name] = [
        'status' => 'FAIL',
        'message' => 'Cannot connect to MySQL server',
        'error' => $e->getMessage(),
        'likely_causes' => [
            'MySQL service not running',
            'Wrong host/credentials',
            'Port not open (default 3306)',
            'Connection timeout'
        ],
        'suggestion' => 'Check if XAMPP MySQL is running via Control Panel'
    ];
}

// Check 7: db_config.php behavior
$check_name = 'Database Init Script (db_config.php)';
try {
    if (file_exists('db_config.php')) {
        $content = file_get_contents('db_config.php');
        if (strpos($content, 'exit') !== false) {
            $diagnostic['checks'][$check_name] = [
                'status' => 'WARN',
                'message' => 'db_config.php calls exit() on error (prevents debugging)',
                'recommendation' => 'Consider making it non-fatal for development'
            ];
        } else {
            $diagnostic['checks'][$check_name] = [
                'status' => 'PASS',
                'message' => 'db_config.php appears safe for error handling'
            ];
        }
    }
} catch (Exception $e) {
    $diagnostic['checks'][$check_name] = [
        'status' => 'ERROR',
        'message' => $e->getMessage()
    ];
}

// Summary
$failed = count(array_filter($diagnostic['checks'], fn($c) => $c['status'] === 'FAIL'));
$warnings = count(array_filter($diagnostic['checks'], fn($c) => $c['status'] === 'WARN'));

$diagnostic['summary'] = [
    'total_checks' => count($diagnostic['checks']),
    'passed' => count(array_filter($diagnostic['checks'], fn($c) => $c['status'] === 'PASS')),
    'warnings' => $warnings,
    'failed' => $failed,
    'overall_status' => $failed > 0 ? 'CRITICAL' : ($warnings > 0 ? 'WARNING' : 'HEALTHY')
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

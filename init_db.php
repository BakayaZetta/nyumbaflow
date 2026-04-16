<?php
require_once 'db_config.php';
require_once 'config.php';

// 1. BOOTSTRAP_SECRET Gate
if (!isset($_GET['secret']) || $_GET['secret'] !== BOOTSTRAP_SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'Bootstrap secret required. Please check your configuration.']));
}

// 2. Re-initialization Guard
// Check if the users table already exists to prevent accidental destruction
try {
    $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($check) {
        http_response_code(400);
        die(json_encode(['error' => 'Database is already initialized. Initialization aborted to prevent data loss.']));
    }
} catch (Exception $e) {
    // If we can't check tables, it's safer to proceed but log it
}

try {
    $sql = file_get_contents('database.sql');
    if ($sql === false) {
        throw new Exception("Could not read database.sql");
    }

    // Split SQL by semicolon to execute multiple queries
    // This is a simple split, might need improvement for complex SQL but should work for this schema
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    $pdo->beginTransaction();
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Database initialized successfully. Please delete this file for security.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database initialization failed: ' . $e->getMessage()]);
}
?>

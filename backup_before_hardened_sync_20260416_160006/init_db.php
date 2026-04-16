<?php
require_once 'db_config.php';

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

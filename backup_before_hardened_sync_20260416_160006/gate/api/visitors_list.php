<?php
// api/visitors_list.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../../config.php';
require __DIR__ . '/../../db_config.php';

try {
    $stmt = $pdo->query("
        SELECT v.*, t.full_name AS tenant_name
        FROM visitors v
        LEFT JOIN tenants t ON v.tenant_id = t.id
        ORDER BY v.id DESC
        LIMIT 50
    ");
    $rows = $stmt->fetchAll();

    // Make signature URLs relative so the browser can load them
    foreach ($rows as &$r) {
        if (!empty($r['signature'])) {
            $r['signature_url'] = '/' . ltrim($r['signature'], '/');
        } else {
            $r['signature_url'] = null;
        }
    }

    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load visitors: ' . $e->getMessage()]);
}

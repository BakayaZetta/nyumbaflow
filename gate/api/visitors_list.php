session_start();

// Authentication Check
if (!isset($_SESSION['personnel_id']) || $_SESSION['personnel_role'] !== 'gate') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access. Please login.']);
    exit;
}

$property_id = $_SESSION['personnel_property_id'];

try {
    $stmt = $pdo->prepare("
        SELECT v.*, t.full_name AS tenant_name
        FROM visitors v
        LEFT JOIN tenants t ON v.tenant_id = t.id
        WHERE v.property_id = ?
        ORDER BY v.id DESC
        LIMIT 50
    ");
    $stmt->execute([$property_id]);
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

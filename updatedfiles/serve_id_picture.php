<?php
require_once 'session_check.php';
require_once 'db_config.php';

// Check if landlord/admin is logged in
requireLogin();
$landlord_id = $_SESSION['admin_id'];

if (!isset($_GET['tenant_id'])) {
    http_response_code(400);
    die("Tenant ID required.");
}

$tenant_id = intval($_GET['tenant_id']);

try {
    // Verify ownership: tenant must belong to a property owned by the logged-in landlord
    $stmt = $pdo->prepare("
        SELECT t.id_picture 
        FROM tenants t
        JOIN properties p ON t.property_id = p.id
        WHERE t.id = ? AND p.landlord_id = ?
    ");
    $stmt->execute([$tenant_id, $landlord_id]);
    $id_picture_path = $stmt->fetchColumn();

    if (!$id_picture_path || !file_exists($id_picture_path)) {
        http_response_code(404);
        die("ID picture not found or access denied.");
    }

    // Determine MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $id_picture_path);
    finfo_close($finfo);

    // Serve the file
    header("Content-Type: " . $mime_type);
    header("Content-Length: " . filesize($id_picture_path));
    
    // Disable caching for sensitive data
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    
    readfile($id_picture_path);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die("Error serving image.");
}

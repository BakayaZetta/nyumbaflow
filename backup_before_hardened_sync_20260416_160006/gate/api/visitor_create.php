<?php
// api/visitor_create.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../../config.php';
require __DIR__ . '/../../db_config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit;
}

$full_name   = trim($data['full_name'] ?? '');
$citizen_id  = trim($data['citizen_id'] ?? null);
$phone       = trim($data['phone_number'] ?? null);
$plate       = trim($data['number_plate'] ?? null);
$unit_id     = intval($data['unit_id'] ?? 0); // Changed from house_number to unit_id
$time_in     = $data['time_in'] ?? null;
$time_out    = $data['time_out'] ?? null;
$signature   = $data['signature'] ?? null;
$visitor_type = trim($data['visitor_type'] ?? 'guest'); // contractor, delivery, guest, etc.

// Basic validation
$errors = [];
if ($full_name === '') $errors[] = 'Full name is required.';
if ($unit_id <= 0) $errors[] = 'Unit/House is required.';

if (!empty($phone) && !preg_match('/^[\d\+\-\s]{6,20}$/', $phone)) {
    $errors[] = 'Phone number format seems invalid.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// Normalize datetime-local (from HTML) -> MySQL DATETIME (if provided)
function normalize_datetime($dt) {
    if (!$dt) return null;
    // dt might be like "2025-08-29T14:12"
    $normalized = str_replace('T', ' ', $dt);
    // if missing seconds, add :00
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
        $normalized .= ':00';
    }
    return $normalized;
}
$time_in  = normalize_datetime($time_in);
$time_out = normalize_datetime($time_out);

// Add visitor_type column if it doesn't exist (for contractors)
try {
    $pdo->exec("ALTER TABLE visitors ADD COLUMN IF NOT EXISTS visitor_type VARCHAR(50) DEFAULT 'guest'");
} catch (PDOException $e) {
    // Column may already exist
}

// Get property_id and tenant_id from the unit
$property_id = null;
$tenant_id = null;
try {
    $stmt = $pdo->prepare("SELECT u.property_id, t.id as tenant_id 
                           FROM units u 
                           LEFT JOIN tenants t ON t.unit_id = u.id AND t.status = 'active' 
                           WHERE u.id = ?");
    $stmt->execute([$unit_id]);
    $row = $stmt->fetch();
    if ($row) {
        $property_id = $row['property_id'];
        $tenant_id = $row['tenant_id']; // May be NULL if unit is vacant
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Unit not found.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Handle signature: accept base64 PNG in $signature
$signature_path = null;
if ($signature && preg_match('/^data:image\/png;base64,/', $signature)) {
    $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $signature);
    $base64 = str_replace(' ', '+', $base64);
    $dataDecoded = base64_decode($base64);
    if ($dataDecoded === false) {
        // ignore signature or fail
        http_response_code(400);
        echo json_encode(['error' => 'Signature data invalid.']);
        exit;
    }
    $uploadDir = __DIR__ . '/../uploads/signatures/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $fn = 'sig_' . time() . '_' . bin2hex(random_bytes(5)) . '.png';
    $fullPath = $uploadDir . $fn;
    file_put_contents($fullPath, $dataDecoded);
    // store relative path so the frontend can access via /uploads/...
    $signature_path = 'uploads/signatures/' . $fn;
}

try {
    $sql = "INSERT INTO visitors
        (property_id, tenant_id, name, id_number, phone_number, number_plate, visit_date, time_in, time_out, visitor_type)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $property_id,
        $tenant_id ?: null,
        $full_name,
        $citizen_id ?: null,
        $phone ?: null,
        $plate ?: null,
        $time_in ?: date('H:i:s'),
        $time_out ?: null,
        $visitor_type
    ]);
    $lastId = $pdo->lastInsertId();
    
    // If this is a contractor, also add/update them in the contractors table
    if ($visitor_type === 'contractor' && $phone) {
        // Check if contractor already exists by phone
        $checkStmt = $pdo->prepare("SELECT id FROM contractors WHERE phone_number = ? AND landlord_id = (SELECT landlord_id FROM properties WHERE id = ?)");
        $checkStmt->execute([$phone, $property_id]);
        $existingContractor = $checkStmt->fetch();
        
        if (!$existingContractor) {
            // Insert new contractor (assuming there's a way to get landlord_id from property)
            $landlordStmt = $pdo->prepare("SELECT landlord_id FROM properties WHERE id = ?");
            $landlordStmt->execute([$property_id]);
            $landlordId = $landlordStmt->fetchColumn();
            
            if ($landlordId) {
                // Try to determine category from purpose if available, otherwise default to 'Other'
                $category = 'Other';
                if (!empty($data['purpose'])) {
                    $purpose = strtolower($data['purpose']);
                    if (strpos($purpose, 'plumb') !== false) $category = 'Plumber';
                    elseif (strpos($purpose, 'electr') !== false) $category = 'Electrician';
                    elseif (strpos($purpose, 'carpent') !== false) $category = 'Carpenter';
                    elseif (strpos($purpose, 'security') !== false) $category = 'Security';
                    elseif (strpos($purpose, 'clean') !== false) $category = 'Cleaner';
                }
                
                $contractorStmt = $pdo->prepare("INSERT INTO contractors (landlord_id, name, category, phone_number) VALUES (?, ?, ?, ?)");
                $contractorStmt->execute([$landlordId, $full_name, $category, $phone]);
            }
        }
    }
    
    echo json_encode(['success' => true, 'id' => (int)$lastId]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Insert failed: ' . $e->getMessage()]);
    exit;
}

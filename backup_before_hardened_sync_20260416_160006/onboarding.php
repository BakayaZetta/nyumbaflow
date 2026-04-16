<?php
require_once 'session_check.php';
require_once 'db_config.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $landlord_id = $_SESSION['admin_id'];
    $properties_data = json_decode($_POST['properties_json'], true);
    $default_rent = floatval($_POST['default_rent'] ?? 0);
    $water_rate = floatval($_POST['water_rate'] ?? 0);
    $wifi_fee = floatval($_POST['wifi_fee'] ?? 0);
    $garbage_fee = floatval($_POST['garbage_fee'] ?? 0);
    $late_fees_enabled = intval($_POST['late_fees'] ?? 0);
    $penalty_amount = floatval($_POST['penalty_amount'] ?? 0);
$celcom_id = $_POST['celcom_id'] ?? 'NYUMBAFLOW';

    if (!$properties_data || empty($properties_data)) {
        die("No property data received.");
    }

    try {
        $pdo->beginTransaction();

        foreach ($properties_data as $prop) {
            // 1. Insert Property
            $stmt = $pdo->prepare("INSERT INTO properties (landlord_id, name, location) VALUES (?, ?, ?)");
            $stmt->execute([$landlord_id, $prop['name'], $prop['location']]);
            $property_id = $pdo->lastInsertId();

            // 2. Insert Units
            if (isset($prop['units']) && is_array($prop['units'])) {
                $unitStmt = $pdo->prepare("INSERT INTO units (property_id, unit_number, rent_amount, water_rate, wifi_fee, garbage_fee, late_fee_enabled, late_fee_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($prop['units'] as $unitNum) {
                    $unitStmt->execute([
                        $property_id, 
                        $unitNum, 
                        $default_rent, 
                        $water_rate,
                        $wifi_fee,
                        $garbage_fee,
                        $late_fees_enabled, 
                        $penalty_amount
                    ]);
                }
            }

            // 3. Generate initial Security Link for this property
            $token = bin2hex(random_bytes(16));
            $secStmt = $pdo->prepare("INSERT INTO security_links (property_id, access_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $secStmt->execute([$property_id, $token]);
        }

        $pdo->commit();
        
        // Success! Redirect to dashboard
        $_SESSION['message'] = "Properties setup successfully! You can now start adding tenants.";
        $_SESSION['message_type'] = "success";
        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Error during setup: " . $e->getMessage());
    }
} else {
    header("Location: onboarding.html");
    exit();
}
?>

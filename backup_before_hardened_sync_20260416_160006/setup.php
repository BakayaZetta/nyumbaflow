<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get the posted data
        $apartmentCount = intval($_POST['apartment_count']);
        $waterRate = floatval($_POST['water_rate']);
        
        // Create properties table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS properties (
                id INT AUTO_INCREMENT PRIMARY KEY,
                landlord_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                floors INT NOT NULL,
                rooms_per_floor INT NOT NULL,
                rent_amount DECIMAL(10, 2) NOT NULL,
                garbage_fee DECIMAL(10, 2) DEFAULT 0,
                water_rate DECIMAL(10, 2) NOT NULL,
                wifi_fee DECIMAL(10, 2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (landlord_id) REFERENCES landlords(id) ON DELETE CASCADE
            )
        ");
        
        // Create apartment-specific tables for each property
        for ($i = 1; $i <= $apartmentCount; $i++) {
            $propertyName = "Apartment $i";
            $floors = intval($_POST["apartment_{$i}_floors"]);
            $roomsPerFloor = intval($_POST["apartment_{$i}_rooms_per_floor"]);
            $rentAmount = floatval($_POST["apartment_{$i}_rent"]);
            $garbageFee = isset($_POST["apartment_{$i}_garbage"]) ? floatval($_POST["apartment_{$i}_garbage"]) : 0;
            $wifiFee = isset($_POST["apartment_{$i}_wifi"]) ? floatval($_POST["apartment_{$i}_wifi"]) : 0;
            
            // Insert property record
            $stmt = $pdo->prepare("
                INSERT INTO properties (landlord_id, name, floors, rooms_per_floor, rent_amount, garbage_fee, water_rate, wifi_fee)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['admin_id'],
                $propertyName,
                $floors,
                $roomsPerFloor,
                $rentAmount,
                $garbageFee,
                $waterRate,
                $wifiFee
            ]);
            
            $propertyId = $pdo->lastInsertId();
            
            // Create apartment-specific tenants table
            $tableName = "tenants_" . $propertyId;
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS $tableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_number VARCHAR(20) NOT NULL UNIQUE,
                    house_number VARCHAR(10) NOT NULL,
                    phone_number VARCHAR(15) NOT NULL,
                    rented_month VARCHAR(20) NOT NULL,
                    rented_year INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Create apartment-specific bills table
            $billsTableName = "tenant_bills_" . $propertyId;
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS $billsTableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_number VARCHAR(20) NOT NULL,
                    house_number VARCHAR(10) NOT NULL,
                    bill_type ENUM('rent', 'wifi', 'water', 'garbage') NOT NULL,
                    amount DECIMAL(10, 2) NOT NULL,
                    due_date DATE NOT NULL,
                    status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
                    payment_date DATE NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (id_number) REFERENCES $tableName(id_number)
                )
            ");
            
            // Create apartment-specific visitors table
            $visitorsTableName = "visitors_" . $propertyId;
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS $visitorsTableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    phone_number VARCHAR(15) NOT NULL,
                    visit_date DATE NOT NULL,
                    visit_time TIME NOT NULL,
                    time_out TIME NULL,
                    id_number VARCHAR(20) NOT NULL,
                    house_number VARCHAR(10) NOT NULL,
                    numberplate VARCHAR(20) NULL,
                    FOREIGN KEY (id_number) REFERENCES $tableName(id_number)
                )
            ");
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Properties setup successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Setup error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error during setup']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
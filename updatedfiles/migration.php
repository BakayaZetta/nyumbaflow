<?php
require_once 'db_config.php';

echo "Starting migration...\n";

try {
    // 1. Update landlords table
    echo "Updating landlords table...\n";
    $pdo->exec("ALTER TABLE landlords ADD COLUMN IF NOT EXISTS role ENUM('admin', 'superadmin') DEFAULT 'admin'");
    $pdo->exec("ALTER TABLE landlords ADD COLUMN IF NOT EXISTS status ENUM('active', 'banned') DEFAULT 'active'");

    // 2. Update tenants table
    echo "Updating tenants table...\n";
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS balance_credit DECIMAL(10, 2) DEFAULT 0");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS has_wifi BOOLEAN DEFAULT FALSE");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS has_garbage BOOLEAN DEFAULT FALSE");

    // 3. Ensure security_links has expires_at
    echo "Ensuring security_links has expires_at...\n";
    // Check if column exists first since MySQL doesn't support ADD COLUMN IF NOT EXISTS for all versions easily
    $stmt = $pdo->query("SHOW COLUMNS FROM security_links LIKE 'expires_at'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE security_links ADD COLUMN expires_at TIMESTAMP NULL AFTER created_at");
        echo "Added expires_at to security_links.\n";
    }

    // 4. Create tenant_links table
    echo "Creating tenant_links table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        access_token VARCHAR(64) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
    )");

    // 5. Add reading columns to bills if missing (used in billing.php)
    echo "Updating bills table for water readings...\n";
    $pdo->exec("ALTER TABLE bills ADD COLUMN IF NOT EXISTS reading_curr DECIMAL(10, 2) DEFAULT 0");
    $pdo->exec("ALTER TABLE bills ADD COLUMN IF NOT EXISTS reading_prev DECIMAL(10, 2) DEFAULT 0");

    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>

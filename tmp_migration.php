<?php
require_once 'db_config.php';

function addColumnIfMissing($pdo, $table, $column, $definition) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "Added column $column to $table.\n";
        } else {
            echo "Column $column already exists in $table.\n";
        }
    } catch (Exception $e) {
        echo "Error adding $column: " . $e->getMessage() . "\n";
    }
}

addColumnIfMissing($pdo, 'visitors', 'id_image', "VARCHAR(255) NULL AFTER number_plate");
addColumnIfMissing($pdo, 'visitors', 'unit_id', "INT NULL AFTER tenant_id");

// Verify if unit_id exists before adding foreign key
try {
    $pdo->exec("ALTER TABLE visitors ADD FOREIGN KEY (unit_id) REFERENCES units(id)");
    echo "Added foreign key for unit_id.\n";
} catch (Exception $e) {
    echo "Foreign key note: " . $e->getMessage() . "\n";
}

echo "Migration finished.";
?>

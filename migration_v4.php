<?php
require_once 'db_config.php';

try {
	$stmt = $pdo->query("SHOW COLUMNS FROM visitors LIKE 'id_image'");

	if (!$stmt->fetch()) {
		$pdo->exec("ALTER TABLE visitors ADD COLUMN id_image VARCHAR(255) NULL AFTER number_plate");
		echo "Added id_image column to visitors.";
	} else {
		echo "id_image column already exists in visitors.";
	}
} catch (Exception $e) {
	echo "Migration failed: " . $e->getMessage();
}
?>

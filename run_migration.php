<?php
require_once 'db_config.php';

try {
    $sql = file_get_contents('migration_approval_workflow.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "✓ Migration completed successfully.\n";
    echo "✓ Tables created: email_verifications, login_verification_codes, account_approvals, approver_settings, system_settings, audit_log\n";
    echo "✓ Default approvers configured: George and Sam\n";
    
} catch (PDOException $e) {
    echo "✗ Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

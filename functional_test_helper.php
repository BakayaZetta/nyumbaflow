<?php
require_once 'db_config.php';

$op = $argv[1] ?? '';
$email = $argv[2] ?? '';

function out($data) {
    echo json_encode($data, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

try {
    if ($op === 'user') {
        global $pdo, $email;
        $stmt = $pdo->prepare("SELECT id, username, email, phone_number, status, role, created_at FROM landlords WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        out(['ok' => true, 'user' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    if ($op === 'token') {
        global $pdo, $email;
        $stmt = $pdo->prepare("SELECT ev.token, ev.expires_at, ev.verified_at FROM email_verifications ev JOIN landlords l ON l.id = ev.landlord_id WHERE l.email = ? ORDER BY ev.id DESC LIMIT 1");
        $stmt->execute([$email]);
        out(['ok' => true, 'verification' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    if ($op === 'login_code') {
        global $pdo, $email;
        $stmt = $pdo->prepare("SELECT lc.code, lc.expires_at, lc.verified_at FROM login_verification_codes lc JOIN landlords l ON l.id = lc.landlord_id WHERE l.email = ? ORDER BY lc.id DESC LIMIT 1");
        $stmt->execute([$email]);
        out(['ok' => true, 'login_code' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    if ($op === 'approval') {
        global $pdo, $email;
        $stmt = $pdo->prepare("SELECT aa.status, aa.rejection_reason, aa.requested_at, aa.approved_at, aa.rejected_at FROM account_approvals aa JOIN landlords l ON l.id = aa.landlord_id WHERE l.email = ? ORDER BY aa.id DESC LIMIT 1");
        $stmt->execute([$email]);
        out(['ok' => true, 'approval' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    if ($op === 'cleanup') {
        global $pdo, $email;
        $stmt = $pdo->prepare("SELECT id FROM landlords WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM landlords WHERE id = ?")->execute([$id]);
        }
        out(['ok' => true, 'deleted_landlord_id' => $id]);
    }

    if ($op === 'latest_ft_user') {
        global $pdo;
        $stmt = $pdo->query("SELECT email FROM landlords WHERE email LIKE 'ft.user.%@nyumbaflow.com' ORDER BY id DESC LIMIT 1");
        out(['ok' => true, 'email' => $stmt->fetchColumn()]);
    }

    if ($op === 'latest_ft_reject') {
        global $pdo;
        $stmt = $pdo->query("SELECT email FROM landlords WHERE email LIKE 'ft.reject.%@nyumbaflow.com' ORDER BY id DESC LIMIT 1");
        out(['ok' => true, 'email' => $stmt->fetchColumn()]);
    }

    out(['ok' => false, 'error' => 'Unknown operation']);
} catch (Exception $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
}

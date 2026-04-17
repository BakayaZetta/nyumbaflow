<?php
require_once 'db_config.php';
require_once 'session_check.php';
requireLogin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_name = $_SESSION['admin_name'] ?? 'User';
$admin_email = $_SESSION['admin_email'] ?? '';

$stmt = $pdo->prepare("SELECT status FROM landlords WHERE id = ? LIMIT 1");
$stmt->execute([$admin_id]);
$status = (string)($stmt->fetchColumn() ?: 'pending_approval');

if ($status === 'active') {
    header("Location: index.php");
    exit();
}

$requested_at = null;
$approval_status = null;
$rejection_reason = null;
$stmt = $pdo->prepare("SELECT status, requested_at, rejection_reason FROM account_approvals WHERE landlord_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$admin_id]);
$approval = $stmt->fetch(PDO::FETCH_ASSOC);
if ($approval) {
    $approval_status = $approval['status'] ?? 'pending';
    $requested_at = $approval['requested_at'] ?? null;
    $rejection_reason = $approval['rejection_reason'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Pending Approval - Nyumbaflow</title>
    <link rel="shortcut icon" href="icons/home.png" type="image/x-icon">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(120deg, #edf2f7 0%, #d9e2ec 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1f2937;
        }
        .card {
            width: min(680px, 92vw);
            background: #ffffff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.15);
            border-top: 6px solid #f59e0b;
        }
        h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }
        p {
            line-height: 1.55;
            margin: 8px 0;
        }
        .meta {
            margin-top: 18px;
            padding: 14px;
            background: #f8fafc;
            border-radius: 10px;
        }
        .warning {
            margin-top: 18px;
            padding: 12px;
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 6px;
            color: #92400e;
        }
        .danger {
            margin-top: 14px;
            padding: 12px;
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            border-radius: 6px;
            color: #991b1b;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .btn-muted {
            background: #e5e7eb;
            color: #111827;
        }
    </style>
</head>
<body>
    <section class="card">
        <h1>Account Awaiting Approval</h1>
        <p>Hello <?php echo htmlspecialchars($admin_name); ?>, your email has been verified successfully.</p>
        <p>Your account is currently under admin review. You can log in, but operational actions are disabled until approval is completed.</p>

        <div class="meta">
            <p><strong>Email:</strong> <?php echo htmlspecialchars($admin_email); ?></p>
            <p><strong>Account Status:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?></p>
            <?php if ($requested_at): ?>
                <p><strong>Approval Requested:</strong> <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($requested_at))); ?></p>
            <?php endif; ?>
            <?php if ($approval_status): ?>
                <p><strong>Approval Record:</strong> <?php echo htmlspecialchars(ucfirst($approval_status)); ?></p>
            <?php endif; ?>
        </div>

        <div class="warning">
            You will receive an email (and SMS when configured) immediately after approval.
        </div>

        <?php if ($status === 'rejected' && !empty($rejection_reason)): ?>
            <div class="danger">
                <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($rejection_reason); ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="pending_account.php" class="btn btn-primary">Refresh Status</a>
            <a href="logout.php" class="btn btn-muted">Logout</a>
        </div>
    </section>
</body>
</html>

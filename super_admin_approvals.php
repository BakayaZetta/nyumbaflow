<?php
require_once 'db_config.php';
require_once 'sanitize.php';
require_once 'csrf_token.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: super_login.php");
    exit;
}

// Get pending approvals
try {
    $stmt = $pdo->prepare("
        SELECT 
            aa.id,
            aa.landlord_id,
            aa.status,
            aa.requested_at,
            aa.approved_at,
            aa.rejected_at,
            aa.rejection_reason,
            l.username,
            l.email,
            l.phone_number,
            l.created_at as signup_date
        FROM account_approvals aa
        JOIN landlords l ON aa.landlord_id = l.id
        ORDER BY aa.requested_at DESC
    ");
    $stmt->execute();
    $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch approvals: " . $e->getMessage());
    $approvals = [];
}

// Count pending approvals
$pending_count = count(array_filter($approvals, fn($a) => $a['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Approvals - Nyumbaflow Super Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0ea5e9;
            --danger: #ef4444;
            --success: #10b981;
            --dark: #0f172a;
            --slate: #1e293b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #f8fafc;
            color: var(--dark);
            font-family: 'Inter', sans-serif;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: var(--dark);
            color: white;
            padding: 30px 20px;
            flex-shrink: 0;
        }

        .main {
            flex: 1;
            padding: 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 {
            font-size: 28px;
        }

        .badge {
            display: inline-block;
            background-color: var(--danger);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
            font-weight: bold;
        }

        .top-links {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .top-links a {
            display: inline-block;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .link-dashboard {
            background: #f1f5f9;
            color: #334155;
        }

        .link-settings {
            background: #e0f2fe;
            color: #075985;
        }

        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #cbd5e1;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            font-weight: 600;
            color: #334155;
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .filter-btn:hover {
            border-color: var(--primary);
        }

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #f1f5f9;
            font-size: 13px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 8px 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .btn-approve {
            background-color: var(--success);
            color: white;
        }

        .btn-approve:hover {
            opacity: 0.92;
        }

        .btn-reject {
            background-color: var(--danger);
            color: white;
        }

        .btn-reject:hover {
            opacity: 0.92;
        }

        .btn-view {
            background-color: #64748b;
            color: white;
        }

        .btn-view:hover {
            opacity: 0.92;
        }

        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 32px;
            margin-bottom: 16px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal h2 {
            margin-bottom: 20px;
            color: #0f172a;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 4px;
            color: white;
            font-weight: 600;
            z-index: 2000;
            animation: slideIn 0.3s ease-out;
        }

        .toast.success {
            background-color: var(--success);
        }

        .toast.error {
            background-color: var(--danger);
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .sidebar a {
            display: block;
            color: #cbd5e1;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .main {
                padding: 20px;
            }

            th,
            td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color: var(--primary); margin-bottom: 40px;">HomeSync SA</h2>
        <nav>
            <a href="super_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px; margin-bottom: 8px;">
                <i class="fas fa-user-check"></i> Approvals
            </div>
            <a href="super_admin_settings.php"><i class="fas fa-cogs"></i> Settings</a>
            <p style="margin-top: 50px; opacity: 0.5; font-size: 12px;">Logged in as: <?php echo htmlspecialchars($_SESSION['superadmin_name'] ?? 'Superadmin'); ?></p>
            <a href="logout.php" style="color: #94a3b8; font-size: 13px; margin-top: 10px;">Logout</a>
        </nav>
    </div>

    <div class="main">
        <div class="header">
            <div>
                <h1>Account Approvals</h1>
                <?php if ($pending_count > 0): ?>
                    <span class="badge"><?php echo $pending_count; ?> Pending</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="top-links">
            <a class="link-dashboard" href="super_dashboard.php">Back to Dashboard</a>
            <a class="link-settings" href="super_admin_settings.php">Approval Settings</a>
        </div>

        <div class="filters">
            <button class="filter-btn active" onclick="filterApprovals('all', this)">All</button>
            <button class="filter-btn" onclick="filterApprovals('pending', this)">Pending</button>
            <button class="filter-btn" onclick="filterApprovals('approved', this)">Approved</button>
            <button class="filter-btn" onclick="filterApprovals('rejected', this)">Rejected</button>
        </div>

        <div class="table-container">
            <?php if (count($approvals) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvals as $approval): ?>
                            <tr class="approval-row" data-status="<?php echo $approval['status']; ?>">
                                <td><?php echo htmlspecialchars($approval['username']); ?></td>
                                <td><?php echo htmlspecialchars($approval['email']); ?></td>
                                <td><?php echo htmlspecialchars($approval['phone_number'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($approval['requested_at'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $approval['status']; ?>">
                                        <?php echo ucfirst($approval['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if ($approval['status'] === 'pending'): ?>
                                            <button class="btn btn-approve" onclick="approveRequest(<?php echo $approval['landlord_id']; ?>, '<?php echo htmlspecialchars($approval['username']); ?>')">Approve</button>
                                            <button class="btn btn-reject" onclick="openRejectModal(<?php echo $approval['landlord_id']; ?>, '<?php echo htmlspecialchars($approval['username']); ?>')">Reject</button>
                                        <?php elseif ($approval['status'] === 'approved'): ?>
                                            <button class="btn btn-view" onclick="viewApprovedDetails(<?php echo $approval['landlord_id']; ?>, '<?php echo date('M d, Y H:i', strtotime($approval['approved_at'])); ?>')">View</button>
                                        <?php elseif ($approval['status'] === 'rejected'): ?>
                                            <button class="btn btn-view" onclick="viewRejectionDetails(<?php echo $approval['landlord_id']; ?>, '<?php echo htmlspecialchars($approval['rejection_reason']); ?>', '<?php echo date('M d, Y H:i', strtotime($approval['rejected_at'])); ?>')">View</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">[ ]</div>
                    <h2>No Approvals Yet</h2>
                    <p>All new user applications will appear here. Users will need to verify their email address first.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <h2>Reject Account Request</h2>
            <div class="form-group">
                <label>User: <span id="rejectUserName"></span></label>
            </div>
            <div class="form-group">
                <label for="rejectionReason">Reason for Rejection *</label>
                <textarea id="rejectionReason" required placeholder="Please provide a reason for rejecting this account request..."></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn" onclick="closeRejectModal()" style="background-color: #999; color: white;">Cancel</button>
                <button class="btn" onclick="submitReject()" style="background-color: #dc3545; color: white;">Reject</button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
        let selectedLandlordId = null;

        function filterApprovals(status, buttonEl) {
            const rows = document.querySelectorAll('.approval-row');
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            if (buttonEl) {
                buttonEl.classList.add('active');
            }
        }

        function openRejectModal(landlordId, userName) {
            selectedLandlordId = landlordId;
            document.getElementById('rejectUserName').textContent = userName;
            document.getElementById('rejectionReason').value = '';
            document.getElementById('rejectModal').classList.add('active');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            selectedLandlordId = null;
        }

        async function approveRequest(landlordId, userName) {
            if (!confirm(`Approve account for ${userName}?`)) return;

            try {
                const response = await fetch('super_admin_approvals_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=approve&landlord_id=${landlordId}&csrf_token=${encodeURIComponent(csrfToken)}`
                });

                const data = await response.json();
                showToast(data.message, response.ok ? 'success' : 'error');
                
                if (response.ok) {
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                showToast('Error approving request', 'error');
            }
        }

        async function submitReject() {
            const reason = document.getElementById('rejectionReason').value.trim();

            if (!reason) {
                showToast('Please provide a reason for rejection', 'error');
                return;
            }

            try {
                const response = await fetch('super_admin_approvals_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=reject&landlord_id=${selectedLandlordId}&reason=${encodeURIComponent(reason)}&csrf_token=${encodeURIComponent(csrfToken)}`
                });

                const data = await response.json();
                showToast(data.message, response.ok ? 'success' : 'error');
                
                if (response.ok) {
                    closeRejectModal();
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                showToast('Error rejecting request', 'error');
            }
        }

        function viewApprovedDetails(landlordId, approvalDate) {
            alert(`Account Approved\nDate: ${approvalDate}`);
        }

        function viewRejectionDetails(landlordId, reason, rejectionDate) {
            alert(`Account Rejected\nDate: ${rejectionDate}\nReason: ${reason}`);
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideIn 0.3s ease-out reverse';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>

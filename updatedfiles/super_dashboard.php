<?php
require_once 'db_config.php';
session_start();
require_once 'sanitize.php';

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: super_login.php");
    exit();
}

$message = "";
$message_type = "";

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $lid = $_POST['landlord_id'];
    $act = $_POST['action'];

    try {
        if ($act === 'ban') {
            $pdo->prepare("UPDATE landlords SET status = 'banned' WHERE id = ?")->execute([$lid]);
            $message = "Landlord banned successfully.";
        } elseif ($act === 'unban') {
            $pdo->prepare("UPDATE landlords SET status = 'active' WHERE id = ?")->execute([$lid]);
            $message = "Landlord unbanned successfully.";
        } elseif ($act === 'delete') {
            $pdo->prepare("DELETE FROM landlords WHERE id = ?")->execute([$lid]);
            $message = "Landlord account and all associated data deleted.";
        }
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Global Stats
$totalLandlords = $pdo->query("SELECT COUNT(*) FROM landlords WHERE role = 'admin'")->fetchColumn();
$totalProperties = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$totalTenants = $pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
$totalRevenue = $pdo->query("SELECT SUM(amount - balance) FROM bills WHERE status IN ('paid', 'partial')")->fetchColumn() ?: 0;

// Landlord List
$landlords = $pdo->query("
    SELECT l.*, 
    (SELECT COUNT(*) FROM properties WHERE landlord_id = l.id) as props_count,
    (SELECT COUNT(*) FROM tenants t JOIN properties p ON t.property_id = p.id WHERE p.landlord_id = l.id) as tenants_count
    FROM landlords l 
    WHERE l.role = 'admin'
    ORDER BY l.created_at DESC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SuperAdmin Dashboard - HomeSync</title>
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
        body { background: #f8fafc; color: var(--dark); font-family: 'Inter', sans-serif; display: flex; min-height: 100vh; margin: 0; }
        .sidebar { width: 250px; background: var(--dark); color: white; padding: 30px 20px; }
        .main { flex: 1; padding: 40px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .stat-card h3 { font-size: 13px; color: #64748b; margin-bottom: 5px; }
        .stat-card p { font-size: 24px; font-weight: 700; }
        
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 12px; border-bottom: 2px solid #f1f5f9; color: #64748b; }
        .table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        .badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 600; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-banned { background: #fee2e2; color: #991b1b; }
        
        .btn { padding: 6px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer; color: white; }
        .btn-ban { background: #f59e0b; }
        .btn-unban { background: var(--success); }
        .btn-delete { background: var(--danger); margin-left: 5px; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color: var(--primary); margin-bottom: 40px;">HomeSync SA</h2>
        <nav>
            <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px;">
                <i class="fas fa-chart-line"></i> Dashboard
            </div>
            <p style="margin-top: 50px; opacity: 0.5; font-size: 12px;">Logged in as: <?php echo esc($_SESSION['superadmin_name']); ?></p>
            <a href="logout.php" style="color: #94a3b8; text-decoration: none; font-size: 13px; display: block; margin-top: 10px;">Logout</a>
        </nav>
    </div>
    <div class="main">
        <header style="margin-bottom: 30px;">
            <h1>Administration Overview</h1>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo esc($message_type); ?>"><?php echo esc($message); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><h3>Landlords</h3><p><?php echo $totalLandlords; ?></p></div>
            <div class="stat-card"><h3>Properties</h3><p><?php echo $totalProperties; ?></p></div>
            <div class="stat-card"><h3>Tenants</h3><p><?php echo $totalTenants; ?></p></div>
            <div class="stat-card"><h3>App Collection</h3><p>KES <?php echo number_format($totalRevenue); ?></p></div>
        </div>

        <div class="card">
            <h2>Landlord Accounts</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Properties</th>
                        <th>Tenants</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($landlords as $l): ?>
                        <tr>
                            <td>#<?php echo $l['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($l['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($l['email']); ?></td>
                            <td><?php echo $l['props_count']; ?></td>
                            <td><?php echo $l['tenants_count']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $l['status']; ?>">
                                    <?php echo ucfirst($l['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($l['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Change status?')">
<?php echo get_csrf_token_field(); ?>
                                    <input type="hidden" name="landlord_id" value="<?php echo $l['id']; ?>">
                                    <?php if ($l['status'] == 'active'): ?>
                                        <button type="submit" name="action" value="ban" class="btn btn-ban">Ban</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="unban" class="btn btn-unban">Unban</button>
                                    <?php endif; ?>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete account? This cannot be undone.')">
<?php echo get_csrf_token_field(); ?>
                                    <input type="hidden" name="landlord_id" value="<?php echo $l['id']; ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

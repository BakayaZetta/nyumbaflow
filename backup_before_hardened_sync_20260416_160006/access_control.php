<?php
session_start();
require_once 'session_check.php';
require_once 'db_config.php';

requireLogin();

$landlord_id = $_SESSION['admin_id'];

// Fetch properties for authorization
$stmt = $pdo->prepare("SELECT id, name FROM properties WHERE landlord_id = ?");
$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll();
$property_ids = array_column($properties, 'id');

if (empty($property_ids)) {
    header("Location: onboarding.html");
    exit();
}

$success_message = "";
$error_message = "";

// Handle account management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? ''; // 'gate' or 'caretaker'
    $prop_id = intval($_POST['property_id'] ?? 0);

    if (!in_array($type, ['gate', 'caretaker'], true)) {
        $error_message = "Invalid personnel type selected.";
    } elseif (in_array($prop_id, $property_ids)) {
        $table = ($type === 'gate') ? 'gate_personnel' : 'caretakers';
        
        if ($action === 'create') {
            $username = trim($_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $full_name = trim($_POST['full_name']);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO $table (property_id, username, password, full_name) VALUES (?, ?, ?, ?)");
                $stmt->execute([$prop_id, $username, $password, $full_name]);
                $success_message = ucfirst($type) . " account created successfully!";
            } catch (PDOException $e) {
                $error_message = ($e->getCode() == 23000) ? "Username already exists." : "Error: " . $e->getMessage();
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            $delStmt = $pdo->prepare("DELETE FROM $table WHERE id = ? AND property_id = ?");
            $delStmt->execute([$id, $prop_id]);
            if ($delStmt->rowCount() > 0) {
                $success_message = ucfirst($type) . " account deleted.";
            } else {
                $error_message = "Account not found or already deleted.";
            }
        }
    } else {
        $error_message = "Invalid property selected.";
    }
}

// Fetch existing accounts
$inClause = implode(',', $property_ids);
$gate_staff = $pdo->query("SELECT g.*, p.name as property_name FROM gate_personnel g JOIN properties p ON g.property_id = p.id WHERE g.property_id IN ($inClause)")->fetchAll();
$caretakers = $pdo->query("SELECT c.*, p.name as property_name FROM caretakers c JOIN properties p ON c.property_id = p.id WHERE c.property_id IN ($inClause)")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Control - HomeSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #4361ee; --dark: #0f172a; --gray: #64748b; --light: #f1f5f9; --shadow: 0 10px 30px rgba(0,0,0,0.05); }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--light); display: flex; min-height: 100vh; }
        .main { flex: 1; padding: 40px; }
        .card { background: white; border-radius: 20px; padding: 30px; box-shadow: var(--shadow); margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 15px; }
        .btn { padding: 12px 25px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-primary { background: var(--primary); color: white; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 15px; border-bottom: 2px solid var(--light); color: var(--gray); font-size: 14px; }
        .table td { padding: 15px; border-bottom: 1px solid var(--light); font-size: 14px; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <header style="margin-bottom: 30px;">
            <h1 style="font-size: 28px;">Personnel Access Control</h1>
            <p style="color: var(--gray);">provision permanent accounts for your property staff.</p>
        </header>

        <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-error"><?php echo $error_message; ?></div><?php endif; ?>

        <div class="grid">
            <!-- Add Personnel -->
            <div class="card">
                <h3><i class="fas fa-user-plus"></i> Create New Account</h3>
                <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="create">
                    <label style="font-size: 12px; font-weight: 600;">Staff Type</label>
                    <select name="type" class="form-control" required>
                        <option value="gate">Gate Personnel</option>
                        <option value="caretaker">Caretaker</option>
                    </select>
                    
                    <label style="font-size: 12px; font-weight: 600;">Assign to Property</label>
                    <select name="property_id" class="form-control" required>
                        <?php foreach ($properties as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input type="text" name="full_name" placeholder="Full Name" class="form-control" required>
                    <input type="text" name="username" placeholder="Username" class="form-control" required>
                    <input type="password" name="password" placeholder="Password" class="form-control" required>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
                </form>
            </div>

            <!-- Existing Accounts Info -->
            <div class="card">
                <h3><i class="fas fa-info-circle"></i> Account Roles</h3>
                <ul style="margin-top: 20px; color: var(--gray); line-height: 2; font-size: 14px;">
                    <li><strong>Gate Personnel:</strong> Can log in to the Gate Portal to manage visitor entries and exits.</li>
                    <li><strong>Caretakers:</strong> Can log in to the Caretaker Portal to record water meter readings.</li>
                    <li>Accounts are permanent and property-specific.</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="section-header">
                <h2>Active Personnel</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Property</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gate_staff as $g): ?>
                        <tr>
                            <td><span class="badge badge-gate">Gate Personnel</span></td>
                            <td><?php echo htmlspecialchars($g['full_name']); ?></td>
                            <td><code><?php echo htmlspecialchars($g['username']); ?></code></td>
                            <td><?php echo htmlspecialchars($g['property_name']); ?></td>
                            <td>
                                <div style="display:flex; gap:10px;">
                                    <button class="btn btn-sm btn-info" onclick="copyLink('gate', '<?php echo $g['username']; ?>')">Copy Link</button>
                                    <form method="POST" onsubmit="return confirm('Delete this account?');" style="margin:0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="gate">
                                        <input type="hidden" name="property_id" value="<?php echo (int)$g['property_id']; ?>">
                                        <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($caretakers as $c): ?>
                        <tr>
                            <td><span class="badge badge-caretaker">Caretaker</span></td>
                            <td><?php echo htmlspecialchars($c['full_name']); ?></td>
                            <td><code><?php echo htmlspecialchars($c['username']); ?></code></td>
                            <td><?php echo htmlspecialchars($c['property_name']); ?></td>
                            <td>
                                <div style="display:flex; gap:10px;">
                                    <button class="btn btn-sm btn-info" onclick="copyLink('caretaker', '<?php echo $c['username']; ?>')">Copy Link</button>
                                    <form method="POST" onsubmit="return confirm('Delete this account?');" style="margin:0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="caretaker">
                                        <input type="hidden" name="property_id" value="<?php echo (int)$c['property_id']; ?>">
                                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function copyLink(role, username) {
            const baseUrl = window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/');
            const link = `${baseUrl}/personnel_login.php`;
            navigator.clipboard.writeText(link).then(() => {
                alert('Portal Login Link Copied: ' + link + '\nStaff can use their username and password to log in.');
            });
        }
    </script>
</body>
</html>


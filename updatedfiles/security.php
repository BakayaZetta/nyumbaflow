<?php
require_once 'db_config.php';
session_start();
require_once 'sanitize.php';

// Check if landlord is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.php");
    exit();
}

$landlord_id = $_SESSION['admin_id'];

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle security personnel creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_security'])) {
    $name = $_POST['security_name'];
    $email = $_POST['security_email'];

    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM security_personnel WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['message'] = "A security personnel with this email already exists.";
            $_SESSION['message_type'] = "error";
        } else {
            // Generate access token
            $access_token = bin2hex(random_bytes(32));

            // Insert security personnel
            $v_now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO security_personnel (landlord_id, name, email, access_token, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$landlord_id, $name, $email, $access_token, $v_now]);

            // Send magic link email (simulated)
            $magic_link = "http://localhost/homesync/gate/index2.php?token=" . $access_token;

            // In a real app, you'd send this via email
            $_SESSION['message'] = "Security personnel created! Magic link: " . $magic_link;
            $_SESSION['message_type'] = "success";
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error creating security personnel: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    header("Location: security.php");
    exit();
}

// Handle security personnel deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_security'])) {
    $security_id = $_POST['security_id'];

    try {
        // Verify ownership
        $stmt = $pdo->prepare("SELECT id FROM security_personnel WHERE id = ? AND landlord_id = ?");
        $stmt->execute([$security_id, $landlord_id]);
        if (!$stmt->fetch()) {
            $_SESSION['message'] = "Access denied: Security personnel not found.";
            $_SESSION['message_type'] = "error";
        } else {
            $stmt = $pdo->prepare("DELETE FROM security_personnel WHERE id = ?");
            $stmt->execute([$security_id]);

            $_SESSION['message'] = "Security personnel deleted successfully!";
            $_SESSION['message_type'] = "success";
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error deleting security personnel: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    header("Location: security.php");
    exit();
}

// Fetch security personnel
$security_personnel = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM security_personnel WHERE landlord_id = ? ORDER BY created_at DESC");
    $stmt->execute([$landlord_id]);
    $security_personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching security personnel: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Management - HomeSync</title>
    <link rel="shortcut icon" href="icons/home.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --success: #2ec4b6;
            --warning: #ff9f1c;
            --danger: #e71d36;
            --light: #f8f9fa;
            --dark: #0f172a;
            --gray: #64748b;
            --shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f1f5f9; color: var(--dark); display: flex; min-height: 100vh; }

        .app-container { display: flex; width: 100%; }
        .main { flex: 1; padding: 30px; overflow-y: auto; }

        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: var(--dark); }

        .security-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .security-card h3 { font-size: 18px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 10px; font-size: 14px; font-weight: 600; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; }

        .btn { padding: 12px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: var(--transition); border: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #3a0ca3; }
        .btn-danger { background: var(--danger); color: white; }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th { text-align: left; padding: 15px; color: var(--gray); font-size: 14px; font-weight: 600; border-bottom: 1px solid #f1f5f9; }
        .table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }

        .action-btn { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; background: rgba(231, 29, 54, 0.1); color: var(--danger); }
        .action-btn:hover { background: rgba(231, 29, 54, 0.2); }

        .magic-link { font-family: monospace; background: var(--light); padding: 8px; border-radius: 4px; font-size: 12px; word-break: break-all; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main">
            <div class="page-header">
                <h1>Security Management</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo esc($message_type); ?>"><?php echo esc($message); ?></div>
            <?php endif; ?>

            <!-- Create Security Personnel -->
            <div class="security-card">
                <h3><i class="fas fa-shield-alt" style="color: var(--primary);"></i> Create Security Personnel</h3>
                <p style="font-size: 14px; color: var(--gray); margin-bottom: 20px;">
                    Create security personnel accounts to allow access to visitor logging. A magic link will be generated for secure access.
                </p>

                <form method="POST">
<?php echo get_csrf_token_field(); ?>
                    <div class="form-group">
                        <label>Security Personnel Name</label>
                        <input type="text" name="security_name" class="form-control" required placeholder="e.g. John Security">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="security_email" class="form-control" required placeholder="e.g. john@security.com">
                    </div>
                    <button type="submit" name="create_security" class="btn btn-primary">Create Security Personnel</button>
                </form>
            </div>

            <!-- Security Personnel List -->
            <div class="security-card">
                <h3><i class="fas fa-users" style="color: var(--success);"></i> Security Personnel</h3>

                <?php if (count($security_personnel) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Magic Link</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($security_personnel as $person): ?>
                                <tr>
                                    <td><strong><?php echo esc($person['name']); ?></strong></td>
                                    <td><?php echo esc($person['email']); ?></td>
                                    <td>
                                        <div class="magic-link">
                                            http://localhost/homesync/gate/index2.php?token=<?php echo esc($person['access_token']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($person['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
<?php echo get_csrf_token_field(); ?>
                                            <input type="hidden" name="security_id" value="<?php echo (int)$person['id']; ?>">
                                            <button type="submit" name="delete_security" class="action-btn" onclick="return confirm('Are you sure you want to delete this security personnel?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: var(--gray); padding: 40px;">No security personnel created yet. Add your first security personnel above.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

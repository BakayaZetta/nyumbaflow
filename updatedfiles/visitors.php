<?php
require_once 'db_config.php';
require_once 'session_check.php';
requireLogin();
require_once 'sanitize.php';

// Fetch visitors only for the current landlord's properties
$visitors = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.*, u.unit_number as tenant_house, t.name as tenant_name
        FROM visitors v
        LEFT JOIN units u ON v.unit_id = u.id
        LEFT JOIN tenants t ON v.tenant_id = t.id AND t.status = 'active'
        LEFT JOIN properties p ON v.property_id = p.id
        WHERE p.landlord_id = ?
        ORDER BY v.visit_date DESC, v.time_in DESC
    ");
    $stmt->execute([$_SESSION['admin_id']]);
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error fetching visitors: " . $e->getMessage();
}

// Handle filtering if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter'])) {
    $filter_date = $_POST['filter_date'];
    $filter_house = $_POST['filter_house'];

    $query = "
        SELECT v.*, u.unit_number as tenant_house, t.name as tenant_name
        FROM visitors v
        LEFT JOIN units u ON v.unit_id = u.id
        LEFT JOIN tenants t ON v.tenant_id = t.id AND t.status = 'active'
        LEFT JOIN properties p ON v.property_id = p.id
        WHERE p.landlord_id = ?
    ";

    $params = [$_SESSION['admin_id']];

    if (!empty($filter_date)) {
        $query .= " AND v.visit_date = ?";
        $params[] = $filter_date;
    }

    if (!empty($filter_house)) {
        $query .= " AND u.unit_number = ?";
        $params[] = $filter_house;
    }

    $query .= " ORDER BY v.visit_date DESC, v.time_in DESC";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error filtering visitors: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Visitor - Nyumbaflow</title>
    <link rel="shortcut icon" href="icons/home.png" type="image/x-icon">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a0ca3;
            --secondary: #4cc9f0;
            --success: #2ec4b6;
            --warning: #ff9f1c;
            --danger: #e71d36;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .page-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .bg-primary-light {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .bg-success-light {
            background: rgba(46, 196, 182, 0.1);
            color: var(--success);
        }
        
        .bg-warning-light {
            background: rgba(255, 159, 28, 0.1);
            color: var(--warning);
        }
        
        .bg-info-light {
            background: rgba(76, 201, 240, 0.1);
            color: var(--secondary);
        }
        
        .card-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .card-footer {
            font-size: 14px;
            color: var(--gray);
        }
        
        .positive {
            color: var(--success);
        }
        
        .negative {
            color: var(--danger);
        }
        
        /* Main Visitors Card */
        .visitors-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: var(--card-shadow);
            margin-bottom: 32px;
        }
        
        .card-header-lg {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .card-title-lg {
            font-size: 20px;
            font-weight: 700;
        }
        
        /* Filter Styles */
        .filter-container {
            background: var(--light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .filter-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--dark);
        }
        
        .filter-form {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: var(--gray);
        }
        
        .filter-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--light-gray);
        }
        
        .visitors-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .visitors-table th {
            background: var(--light);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
            position: sticky;
            top: 0;
        }
        
        .visitors-table td {
            padding: 16px;
            border-top: 1px solid var(--light-gray);
        }
        
        .visitors-table tr:hover {
            background: rgba(67, 97, 238, 0.03);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: rgba(46, 196, 182, 0.1);
            color: var(--success);
        }
        
        .status-completed {
            background: rgba(255, 159, 28, 0.1);
            color: var(--warning);
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }
        
        .btn-view {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .btn-view:hover {
            background: rgba(67, 97, 238, 0.2);
        }
        
        /* Notification Styles */
        .notification {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .notification-success {
            background: rgba(46, 196, 182, 0.1);
            color: var(--success);
            border: 1px solid rgba(46, 196, 182, 0.2);
        }
        
        .notification-error {
            background: rgba(231, 29, 54, 0.1);
            color: var(--danger);
            border: 1px solid rgba(231, 29, 54, 0.2);
        }
        
        .notification-close {
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
        }
        
        /* Responsive Styles */
        @media (max-width: 1024px) {
            .app-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 16px;
            }
            
            .sidebar-header {
                padding: 0 0 16px;
            }
            
            .nav-items {
                display: flex;
                overflow-x: auto;
                gap: 8px;
                margin-bottom: 16px;
            }
            
            .nav-item {
                border-left: none;
                border-bottom: 3px solid transparent;
                padding: 12px 16px;
                white-space: nowrap;
            }
            
            .nav-item:hover, .nav-item.active {
                border-left-color: transparent;
                border-bottom-color: var(--primary);
            }
            
            .sidebar-footer {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
        
        /* Utility Classes */
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .mb-0 {
            margin-bottom: 0;
        }
        
        .mt-24 {
            margin-top: 24px;
        }
        
        .d-none {
            display: none;
        }
        
        .d-flex {
            display: flex;
        }
        
        .align-center {
            align-items: center;
        }
        
        .justify-between {
            justify-content: space-between;
        }
        
        /* Visitor Details Styles */
        .visitor-details {
            font-size: 14px;
            color: var(--gray);
        }
        
        .visitor-details div {
            margin-bottom: 4px;
        }
        
        .visitor-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 16px;
        }
        
        /* Time Styles */
        .time-in {
            color: var(--success);
            font-weight: 600;
        }
        
        .time-out {
            color: var(--warning);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Visitor Management</h1>
                <div class="page-actions">
                    <button class="btn btn-primary" onclick="window.location.href='access_control.php'">
                        <i class="fas fa-users-cog"></i> Personnel Management
                    </button>
                </div>

            </div>
            
            <!-- Notifications -->
            <?php if (isset($error)): ?>
                <div class="notification notification-error">
                    <span><?php echo esc($error); ?></span>
                    <button class="notification-close">&times;</button>
                </div>
            <?php endif; ?>
            
            <!-- Dashboard Cards -->
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Total Visitors</h3>
                        <div class="card-icon bg-primary-light">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="card-value"><?php echo count($visitors); ?></div>
                    <div class="card-footer">
                        <span class="positive"><i class="fas fa-arrow-up"></i> 12.5%</span> from last week
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Active Visitors</h3>
                        <div class="card-icon bg-success-light">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="card-value">
                        <?php
                        $active_visitors = 0;
                        foreach ($visitors as $visitor) {
                            if (empty($visitor['time_out'])) {
                                $active_visitors++;
                            }
                        }
                        echo $active_visitors;
                        ?>
                    </div>
                    <div class="card-footer">
                        Currently in the property
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Today's Visitors</h3>
                        <div class="card-icon bg-info-light">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="card-value">
                        <?php
                        $today_visitors = 0;
                        $today = date('Y-m-d');
                        foreach ($visitors as $visitor) {
                            if ($visitor['visit_date'] == $today) {
                                $today_visitors++;
                            }
                        }
                        echo $today_visitors;
                        ?>
                    </div>
                    <div class="card-footer">
                        <?php echo date('F j, Y'); ?>
                    </div>
                </div>
            </div>
            
            <!-- Visitors Card -->
            <div class="visitors-card">
                <div class="card-header-lg">
                    <h2 class="card-title-lg">All Visitors</h2>
                    <div class="search-box">
                        <input type="text" class="form-control" placeholder="Search visitors..." style="width: 250px;">
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-container">
                    <h3 class="filter-title">Filter Visitors</h3>
                    <form method="POST" class="filter-form">
                        <div class="filter-group">
                            <label class="filter-label">Date</label>
                            <input type="date" class="filter-input" name="filter_date" value="<?php echo esc($_POST['filter_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">House Number</label>
                            <input type="text" class="filter-input" name="filter_house" placeholder="Enter house number" value="<?php echo esc($_POST['filter_house'] ?? ''); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" name="filter" class="btn btn-primary">Apply Filters</button>
                            <a href="visitors.php" class="btn btn-outline">Clear Filters</a>
                        </div>
                    </form>
                </div>
                
                <div class="table-container">
                    <table class="visitors-table">
                        <thead>
                            <tr>
                                <th>Visitor Details</th>
                                <th>Visit Information</th>
                                <th>Host Information</th>
                                <th>Vehicle & ID</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($visitors) > 0): ?>
                                <?php foreach ($visitors as $visitor): ?>
                                    <tr>
                                        <td>
                                            <div class="visitor-details">
                                                <div class="visitor-name">
                                                    <?php echo esc($visitor['name']); ?>
                                                    <?php if ($visitor['visitor_type'] === 'contractor'): ?>
                                                        <span class="badge" style="font-size: 10px; background: #fee2e2; color: #ef4444; padding: 2px 6px; border-radius: 4px; margin-left: 5px;">Contractor</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div><?php echo esc($visitor['phone_number']); ?></div>
                                                <div>ID: <?php echo esc($visitor['id_number']); ?></div>
                                                <?php if (!empty($visitor['purpose'])): ?>
                                                    <div style="font-style: italic; font-size: 12px; color: var(--primary);"><i class="fas fa-info-circle"></i> <?php echo esc($visitor['purpose']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="visitor-details">
                                                <div><strong>Date:</strong> <?php echo esc(date('M j, Y', strtotime($visitor['visit_date']))); ?></div>
                                                <div><span class="time-in">In:</span> <?php echo esc(date('g:i A', strtotime($visitor['time_in']))); ?></div>
                                                <div>
                                                    <?php if (!empty($visitor['time_out'])): ?>
                                                        <span class="time-out">Out:</span> <?php echo esc(date('g:i A', strtotime($visitor['time_out']))); ?>
                                                    <?php else: ?>
                                                        <span class="time-out">Still in property</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="visitor-details">
                                                <div><strong>House:</strong> <?php echo esc($visitor['tenant_house'] ?? 'N/A'); ?></div>
                                                <div><strong>Tenant:</strong> <?php echo esc($visitor['tenant_name'] ?? 'N/A'); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="visitor-details">
                                                <?php if (!empty($visitor['number_plate'])): ?>
                                                    <div><strong>Plate:</strong> <?php echo esc($visitor['number_plate']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($visitor['id_image'])): ?>
                                                    <div><a href="uploads/visitors/<?php echo esc($visitor['id_image']); ?>" target="_blank" style="color:var(--primary); font-size:12px;"><i class="fas fa-id-card"></i> View ID Image</a></div>
                                                <?php else: ?>
                                                    <div style="font-size:11px; color:var(--gray);">No ID Image captured</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?php if (empty($visitor['time_out'])): ?>
                                                <span class="status-badge status-active">Active</span>
                                            <?php else: ?>
                                                <span class="status-badge status-completed">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No visitors found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-24">
                    <button class="btn btn-outline">Load More</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Close notifications
        document.querySelectorAll('.notification-close').forEach(button => {
            button.addEventListener('click', (e) => {
                e.target.closest('.notification').style.display = 'none';
            });
        });
    </script>
</body>
</html>
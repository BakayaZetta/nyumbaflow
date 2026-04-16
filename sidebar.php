<?php
$current_page = basename($_SERVER['PHP_SELF']);
require_once 'sanitize.php';
?>
<style>
    .sidebar {
        width: 280px;
        background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
        color: white;
        padding: 24px 0;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        z-index: 100;
        min-height: 100vh;
        position: sticky;
        top: 0;
    }
    
    .sidebar-header {
        padding: 0 24px 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 24px;
    }
    
    .sidebar-header h1 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: #4361ee;
    }
    
    .sidebar-header p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
    }
    
    .nav-items {
        display: flex;
        flex-direction: column;
    }
    
    .nav-item {
        display: flex;
        align-items: center;
        padding: 14px 24px;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }
    
    .nav-item:hover, .nav-item.active {
        background: rgba(255, 255, 255, 0.05);
        color: white;
        border-left-color: #4361ee;
    }
    
    .nav-item i {
        margin-right: 12px;
        font-size: 18px;
        width: 24px;
        text-align: center;
    }
    
    .sidebar-footer {
        margin-top: auto;
        padding: 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #4361ee;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
    }

    .user-info h4 {
        font-size: 14px;
        font-weight: 600;
        color: #4361ee;
    }

    .user-info p {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
    }

    .logout-btn {
        width: 100%;
        padding: 12px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
        margin-top: 16px;
    }

    .logout-btn:hover {
        background: rgba(231, 29, 54, 0.2);
        border-color: #e71d36;
        color: #ff6b7d;
    }

    .footer {
        padding: 20px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        text-align: center;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
        line-height: 1.5;
    }

    .footer p {
        margin: 0;
    }

    .footer a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
    }

    .footer a:hover {
        color: white;
    }

    @media (max-width: 768px) {
        .footer {
            padding: 15px 20px;
            font-size: 11px;
        }
    }
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <h1><i class="fas fa-home"></i> Nyumbaflow</h1>
        <p>Property Management</p>
    </div>
    
    <div class="nav-items">
        <a href="index.php" class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-desktop"></i>
            <span>Dashboard</span>
        </a>
        <a href="billing.php" class="nav-item <?php echo $current_page == 'billing.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Billing</span>
        </a>
        <a href="tenants.php" class="nav-item <?php echo $current_page == 'tenants.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-friends"></i>
            <span>Tenants</span>
        </a>
        <a href="contractors.php" class="nav-item <?php echo $current_page == 'contractors.php' ? 'active' : ''; ?>">
            <i class="fas fa-users-cog"></i>
            <span>Contractors</span>
        </a>
        <a href="notifications.php" class="nav-item <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
        </a>
        <a href="visitors.php" class="nav-item <?php echo $current_page == 'visitors.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            <span>Visitors</span>
        </a>
        <a href="settings.php" class="nav-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar"><?php echo esc(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?></div>
            <div class="user-info">
                <h4><?php echo esc($_SESSION['admin_name'] ?? 'Admin'); ?></h4>
                <p>Landlord</p>
            </div>
        </div>
        <form method="POST" action="logout.php" style="margin-top: 16px;">
            <?php echo get_csrf_token_field(); ?>
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Nyumbaflow. All rights reserved.</p>
        <p>Powered by <a href="mailto:jacetechnologies@gmail.com">Jacetechnologies@gmail.com</a></p>
        <p>Contact: <a href="tel:+254725531336">+254 725 531336</a></p>
    </div>
</div>

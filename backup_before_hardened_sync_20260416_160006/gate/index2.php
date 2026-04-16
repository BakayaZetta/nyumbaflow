<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HomeSync — Enhanced Gate Log</title>
  <link rel="shortcut icon" href="../icons/home.png" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* ---------- Enhanced Color Palette & Theme ---------- */
    :root {
      --primary-blue: #0461d3;
      --primary-dark: #0f2b4f;
      --primary-light: #67a7ff;
      --accent: #1867ff;
      --text-primary: #01172e;
      --text-secondary: #3a506b;
      --text-muted: #6c87a8;
      --bg-primary: #f0f7ff;
      --bg-secondary: #e4f0ff;
      --bg-card: #ffffff;
      --border-light: #d4e5ff;
      --success: #2ecc71;
      --error: #e74c3c;
      --warning: #f39c12;
      --shadow-sm: 0 2px 8px rgba(4, 97, 211, 0.2);
      --shadow-md: 0 4px 16px rgba(4, 97, 211, 0.32);
      --shadow-lg: 0 8px 24px rgba(4, 97, 211, 0.4);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
    }

    /* ---------- Base Styles ---------- */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
      background: var(--bg-primary);
      color: var(--text-primary);
      line-height: 1.6;
      padding: 0;
      margin: 0;
      min-height: 100vh;
    }

    .app-container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 24px;
      display: grid;
      grid-template-columns: 1fr 400px;
      gap: 24px;
      align-items: start;
    }

    @media (max-width: 968px) {
      .app-container {
        grid-template-columns: 1fr;
        padding: 16px;
      }
    }

    /* ---------- Card Components ---------- */
    .card {
      background: var(--bg-card);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-md);
      padding: 24px;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .card:hover {
      box-shadow: var(--shadow-lg);
    }

    .card-header {
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--border-light);
    }

    .card-title {
      font-size: 22px;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 4px;
    }

    .card-subtitle {
      font-size: 14px;
      color: var(--text-muted);
    }

    /* ---------- Form Elements ---------- */
    .form-group {
      margin-bottom: 18px;
    }

    .form-label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 6px;
      color: var(--text-secondary);
    }

    .required-marker {
      color: var(--error);
    }

    .form-control {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      font-size: 15px;
      transition: all 0.2s ease;
      background: white;
      color: var(--text-primary);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(24, 103, 255, 0.1);
    }

    .form-row {
      display: flex;
      gap: 16px;
      margin-bottom: 18px;
    }

    .form-row .form-group {
      flex: 1;
      margin-bottom: 0;
    }

    @media (max-width: 640px) {
      .form-row {
        flex-direction: column;
        gap: 18px;
      }
    }

    /* ---------- Buttons ---------- */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 12px 20px;
      border-radius: var(--radius-md);
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      border: none;
      gap: 8px;
    }

    .btn-primary {
      background: var(--accent);
      color: white;
      box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
      background: var(--primary-blue);
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
    }

    .btn-secondary {
      background: var(--bg-secondary);
      color: var(--primary-blue);
      border: 1px solid var(--border-light);
    }

    .btn-secondary:hover {
      background: #d9e9ff;
    }

    .btn-sm {
      padding: 8px 14px;
      font-size: 13px;
    }

    .btn-group {
      display: flex;
      gap: 12px;
    }

    /* ---------- Signature Canvas ---------- */
    .signature-section {
      margin: 24px 0;
    }

    .signature-container {
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      overflow: hidden;
      margin-bottom: 12px;
    }

    #sigCanvas {
      width: 100%;
      height: 160px;
      background: #f9fbfd;
      cursor: crosshair;
      display: block;
    }

    .signature-actions {
      display: flex;
      gap: 12px;
    }

    /* ---------- Visitor List ---------- */
    .visitors-list {
      max-height: 60vh;
      overflow-y: auto;
      padding-right: 8px;
    }

    .visitors-list::-webkit-scrollbar {
      width: 6px;
    }

    .visitors-list::-webkit-scrollbar-track {
      background: var(--bg-secondary);
      border-radius: 3px;
    }

    .visitors-list::-webkit-scrollbar-thumb {
      background: var(--primary-light);
      border-radius: 3px;
    }

    .visitor-card {
      padding: 16px;
      margin-bottom: 12px;
      background: var(--bg-secondary);
      border-radius: var(--radius-md);
      transition: all 0.2s ease;
      position: relative;
    }

    .visitor-card:hover {
      background: #d9e9ff;
      transform: translateY(-2px);
      box-shadow: var(--shadow-sm);
    }

    .visitor-avatar {
      width: 48px;
      height: 48px;
      border-radius: var(--radius-md);
      background: var(--accent);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: white;
      font-size: 16px;
      flex-shrink: 0;
      margin-right: 16px;
    }

    .visitor-details {
      flex: 1;
    }

    .visitor-name {
      font-weight: 600;
      margin-bottom: 4px;
      color: var(--text-primary);
    }

    .visitor-meta {
      font-size: 13px;
      color: var(--text-secondary);
      margin-bottom: 2px;
    }

    .visitor-time {
      font-size: 12px;
      color: var(--text-muted);
    }

    .visitor-actions {
      position: absolute;
      top: 16px;
      right: 16px;
      display: flex;
      gap: 8px;
    }

    .visitor-status {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      margin-top: 8px;
    }

    .status-in {
      background-color: rgba(46, 204, 113, 0.2);
      color: var(--success);
    }

    .status-out {
      background-color: rgba(231, 76, 60, 0.2);
      color: var(--error);
    }

    /* ---------- Search Bar ---------- */
    .search-container {
      position: relative;
      margin-bottom: 16px;
    }

    .search-input {
      padding-left: 40px;
    }

    .search-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
    }

    /* ---------- Toast Notifications ---------- */
    .toast-container {
      position: fixed;
      top: 24px;
      right: 24px;
      z-index: 1000;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .toast {
      padding: 16px 20px;
      border-radius: var(--radius-md);
      color: white;
      font-size: 14px;
      box-shadow: var(--shadow-lg);
      animation: toastIn 0.3s ease;
      max-width: 320px;
      display: flex;
      flex-direction: column;
    }

    .toast-success {
      background: var(--success);
    }

    .toast-error {
      background: var(--error);
    }

    .toast-title {
      font-weight: 600;
      margin-bottom: 4px;
    }

    .toast-message {
      opacity: 0.9;
    }

    @keyframes toastIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes toastOut {
      from {
        opacity: 1;
        transform: translateY(0);
      }
      to {
        opacity: 0;
        transform: translateY(-20px);
      }
    }

    /* ---------- Apple-style Alert ---------- */
    .alert-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2000;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
    }

    .alert-overlay.active {
      opacity: 1;
      pointer-events: all;
    }

    .alert-container {
      background: white;
      border-radius: 14px;
      overflow: hidden;
      width: 270px;
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
      transform: scale(0.9);
      transition: transform 0.3s ease;
    }

    .alert-overlay.active .alert-container {
      transform: scale(1);
    }

    .alert-title {
      padding: 20px 16px 5px;
      font-size: 17px;
      font-weight: 600;
      text-align: center;
    }

    .alert-message {
      padding: 5px 16px 20px;
      font-size: 13px;
      text-align: center;
      color: var(--text-secondary);
    }

    .alert-actions {
      border-top: 0.5px solid #e0e0e0;
      display: flex;
    }

    .alert-button {
      flex: 1;
      padding: 16px;
      text-align: center;
      font-size: 17px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease;
    }

    .alert-button:active {
      background: #f0f0f0;
    }

    .alert-cancel {
      color: var(--primary-blue);
      border-right: 0.5px solid #e0e0e0;
    }

    .alert-confirm {
      color: var(--error);
    }

    /* ---------- Utility Classes ---------- */
    .text-muted {
      color: var(--text-muted);
      font-size: 13px;
    }

    .mt-2 {
      margin-top: 16px;
    }

    .mb-2 {
      margin-bottom: 16px;
    }

    .d-flex {
      display: flex;
    }

    .justify-between {
      justify-content: space-between;
    }

    .align-center {
      align-items: center;
    }

    .visitor-item {
      display: flex;
      margin-bottom: 16px;
    }

    .loading {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255,255,255,.3);
      border-radius: 50%;
      border-top-color: #fff;
      animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .house-validation {
      font-size: 12px;
      margin-top: 4px;
      display: none;
    }

    .validation-valid {
      color: var(--success);
    }

    .validation-invalid {
      color: var(--error);
    }
  </style>
</head>
<body>
  <?php
  // Database connection and processing - using main config
  require_once '../config.php';
  require_once '../db_config.php';

  // Check if security personnel is logged in
  if (!isset($_SESSION['security_id'])) {
      header("Location: login.php");
      exit();
  }

  $security_id = $_SESSION['security_id'];
  
  // Handle form submission
  $message = '';
  $message_type = '';
  
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
      if (isset($_POST['submit_visitor'])) {
          // Process visitor form submission
          $name = $_POST['name'];
          $id_number = $_POST['id_number'] ?? '';
          $phone_number = $_POST['phone_number'];
          $numberplate = $_POST['numberplate'] ?? '';
          $unit_id = $_POST['unit_id']; // Changed from house_number to unit_id
          
          // Get current date and time
          $visit_date = date('Y-m-d');
          $visit_time = date('H:i:s');
          
          // Get property_id and tenant_id from the unit
          $tenant_id = null;
          $property_id = null;
          
          if (!empty($unit_id)) {
              // Get property_id from unit and check for active tenant
              $check_sql = "SELECT u.property_id, t.id as tenant_id FROM units u 
                           LEFT JOIN tenants t ON t.unit_id = u.id AND t.status = 'active' 
                           WHERE u.id = ?";
              $check_stmt = $conn->prepare($check_sql);
              $check_stmt->bind_param("i", $unit_id);
              $check_stmt->execute();
              $check_result = $check_stmt->get_result();
              
              if ($row = $check_result->fetch_assoc()) {
                  $property_id = $row['property_id'];
                  $tenant_id = $row['tenant_id']; // May be NULL if unit is vacant
              }
              $check_stmt->close();
          }
          
          // Insert into visitors table using relational schema
          $sql = "INSERT INTO visitors (property_id, tenant_id, name, id_number, phone_number, number_plate, visit_date, time_in) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("iissssss", $property_id, $tenant_id, $name, $id_number, $phone_number, $numberplate, $visit_date, $visit_time);
          
          if ($stmt->execute()) {
              $message = "Visitor logged successfully!";
              $message_type = "success";
          } else {
              $message = "Error: " . $conn->error;
              $message_type = "error";
          }
          
          $stmt->close();
      } elseif (isset($_POST['timeout_visitor'])) {
          // Process timeout request
          $visitor_id = $_POST['visitor_id'];
          $time_out = date('H:i:s');
          
          $sql = "UPDATE visitors SET time_out = ? WHERE id = ?";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("si", $time_out, $visitor_id);
          
          if ($stmt->execute()) {
              $message = "Visitor timed out successfully!";
              $message_type = "success";
          } else {
              $message = "Error: " . $conn->error;
              $message_type = "error";
          }
          
          $stmt->close();
      }
  }
  
  // Get search term if any
  $search_term = '';
  if (isset($_GET['search'])) {
      $search_term = $conn->real_escape_string($_GET['search']);
  }
  
  // Fetch visitors from database with unit info
  $sql = "SELECT v.*, u.unit_number 
          FROM visitors v 
          LEFT JOIN tenants t ON v.tenant_id = t.id
          LEFT JOIN units u ON t.unit_id = u.id
          WHERE 1=1";
  if (!empty($search_term)) {
      $sql .= " AND (v.name LIKE '%$search_term%' OR u.unit_number LIKE '%$search_term%' OR v.phone_number LIKE '%$search_term%' OR v.id_number LIKE '%$search_term%')";
  }
  $sql .= " ORDER BY v.visit_date DESC, v.time_in DESC";
  
  $result = $conn->query($sql);
  $visitors = [];
  if ($result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
          $visitors[] = $row;
      }
  }
  ?>
  
  <div class="app-container">
    <!-- Left: Visitor Form -->
    <section class="card">
      <div class="card-header">
        <h2 class="card-title">Gate Visitor Entry</h2>
        <p class="card-subtitle">Record all visitor details clearly and concisely</p>
      </div>

      <?php if (!empty($message)): ?>
        <div class="toast toast-<?php echo $message_type; ?>" id="statusToast" style="position: relative; margin-bottom: 20px; animation: toastIn 0.3s ease;">
          <div class="toast-title"><?php echo ucfirst($message_type); ?></div>
          <div class="toast-message"><?php echo $message; ?></div>
        </div>
        
        <script>
          // Auto-hide toast after 5 seconds
          setTimeout(() => {
            const toast = document.getElementById('statusToast');
            if (toast) {
              toast.style.animation = 'toastOut 0.3s ease';
              setTimeout(() => toast.remove(), 300);
            }
          }, 5000);
        </script>
      <?php endif; ?>

      <form method="POST" autocomplete="off" id="visitorForm">
        <div class="form-row">
          <div class="form-group">
            <label for="name" class="form-label">Full Name <span class="required-marker">*</span></label>
            <input type="text" id="name" name="name" class="form-control" placeholder="e.g. George Njamula" required>
          </div>
          <div class="form-group">
            <label for="id_number" class="form-label">ID Number</label>
            <input type="text" id="id_number" name="id_number" class="form-control" placeholder="ID / Passport no.">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="phone_number" class="form-label">Phone Number <span class="required-marker">*</span></label>
            <input type="tel" id="phone_number" name="phone_number" class="form-control" placeholder="+2547..." required>
          </div>
          <div class="form-group">
            <label for="numberplate" class="form-label">Car Registration (Optional)</label>
            <input type="text" id="numberplate" name="numberplate" class="form-control" placeholder="KDS 003A">
          </div>
        </div>

        <div class="form-group">
          <label for="unit_id" class="form-label">House Number Visited <span class="required-marker">*</span></label>
          <select id="unit_id" name="unit_id" class="form-control" required>
            <option value="">Select House/Unit</option>
            <?php
            // Fetch all units with tenant info for the dropdown
            $units_sql = "SELECT u.id, u.unit_number, t.name as tenant_name 
                         FROM units u 
                         LEFT JOIN tenants t ON t.unit_id = u.id AND t.status = 'active' 
                         ORDER BY u.unit_number";
            $units_result = $conn->query($units_sql);
            while ($unit = $units_result->fetch_assoc()) {
                $display = $unit['unit_number'];
                if ($unit['tenant_name']) {
                    $display .= ' - ' . htmlspecialchars($unit['tenant_name']);
                } else {
                    $display .= ' (Vacant)';
                }
                echo '<option value="' . $unit['id'] . '">' . htmlspecialchars($display) . '</option>';
            }
            ?>
          </select>
        </div>

        <div class="d-flex justify-between align-center mt-2">
          <p class="text-muted">Fields marked with <span class="required-marker">*</span> are required</p>
          <button type="submit" name="submit_visitor" class="btn btn-primary" id="submitBtn">
            <span>Log Visitor</span>
          </button>
        </div>
      </form>
    </section>

    <!-- Right: Recent Visitors -->
    <aside class="card">
      <div class="d-flex justify-between align-center card-header">
        <div>
          <h2 class="card-title">Recent Visitors</h2>
        </div>
        <a href="?" class="btn btn-secondary btn-sm">Refresh</a>
      </div>

      <form method="GET" class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" class="form-control search-input" placeholder="Search visitors...">
      </form>

      <div class="visitors-list" id="visitorList">
        <?php if (empty($visitors)): ?>
          <div class="text-muted" style="text-align: center; padding: 20px;">No visitors found</div>
        <?php else: ?>
          <?php foreach ($visitors as $visitor): ?>
            <?php
            $initials = '';
            $name_parts = explode(' ', $visitor['name']);
            foreach ($name_parts as $part) {
                if (!empty($part)) {
                    $initials .= strtoupper($part[0]);
                }
                if (strlen($initials) >= 2) break;
            }
            
            $isCurrentlyIn = empty($visitor['time_out']);
            $timeIn = date('M j, Y g:i A', strtotime($visitor['visit_date'] . ' ' . $visitor['visit_time']));
            $timeOut = $isCurrentlyIn ? null : date('M j, Y g:i A', strtotime($visitor['visit_date'] . ' ' . $visitor['time_out']));
            ?>
            <div class="visitor-card">
              <div class="visitor-item">
                <div class="visitor-avatar"><?php echo $initials; ?></div>
                <div class="visitor-details">
                  <div class="visitor-name"><?php echo htmlspecialchars($visitor['name']); ?></div>
                  <div class="visitor-meta"><?php echo htmlspecialchars($visitor['unit_number'] ?? 'N/A'); ?></div>
                  <?php if (!empty($visitor['id_number'])): ?>
                    <div class="visitor-meta">ID: <?php echo htmlspecialchars($visitor['id_number']); ?></div>
                  <?php endif; ?>
                  <div class="visitor-meta">Phone: <?php echo htmlspecialchars($visitor['phone_number']); ?></div>
                  <?php if (!empty($visitor['numberplate'])): ?>
                    <div class="visitor-meta">Vehicle: <?php echo htmlspecialchars($visitor['numberplate']); ?></div>
                  <?php endif; ?>
                  <div class="visitor-time">In: <?php echo $timeIn; ?></div>
                  <?php if ($timeOut): ?>
                    <div class="visitor-time">Out: <?php echo $timeOut; ?></div>
                  <?php endif; ?>
                  <div class="visitor-status <?php echo $isCurrentlyIn ? 'status-in' : 'status-out'; ?>">
                    <?php echo $isCurrentlyIn ? 'Currently In' : 'Checked Out'; ?>
                  </div>
                </div>
              </div>
              <?php if ($isCurrentlyIn): ?>
                <div class="visitor-actions">
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="visitor_id" value="<?php echo $visitor['id']; ?>">
                    <button type="submit" name="timeout_visitor" class="btn btn-secondary btn-sm">Timeout</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </aside>
  </div>

  <script>
    // JavaScript for enhanced functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Search functionality
      const searchInput = document.querySelector('input[name="search"]');
      searchInput.addEventListener('input', function() {
        // Submit the form when typing (with a slight delay)
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
          this.form.submit();
        }, 500);
      });
      
      // Auto-focus on search input if there's a search term
      <?php if (!empty($search_term)): ?>
        searchInput.focus();
      <?php endif; ?>
      
      // Unit selection is now a dropdown, no validation needed
    });
  </script>
</body>
</html>
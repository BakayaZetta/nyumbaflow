-- Migration: Email Verification and Account Approval Workflow
-- Date: 2026-04-16

-- 1. Alter landlords table to add new status and fields
ALTER TABLE landlords MODIFY status ENUM('pending_verification', 'pending_approval', 'rejected', 'active', 'banned', 'disabled') DEFAULT 'pending_verification';

-- Ensure phone_number exists
ALTER TABLE landlords ADD COLUMN IF NOT EXISTS phone_number VARCHAR(15) NULL;

-- 2. Create email_verifications table
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES landlords(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_landlord_id (landlord_id)
);

-- 3. Create login_verification_codes table for 6-digit codes
CREATE TABLE IF NOT EXISTS login_verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    method ENUM('email', 'sms', 'both') DEFAULT 'both',
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES landlords(id) ON DELETE CASCADE,
    INDEX idx_landlord_expires (landlord_id, expires_at)
);

-- 4. Create account_approvals table
CREATE TABLE IF NOT EXISTS account_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approver_id INT NULL,
    approval_reason VARCHAR(500) NULL,
    rejection_reason VARCHAR(500) NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,
    FOREIGN KEY (landlord_id) REFERENCES landlords(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES landlords(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_landlord_id (landlord_id)
);

-- 5. Create approver_settings table for configurable approvers
CREATE TABLE IF NOT EXISTS approver_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    approver_name VARCHAR(100) NOT NULL,
    approver_email VARCHAR(100) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active)
);

-- 6. Create system_settings table for general configuration
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- 7. Insert default approvers (George and Sam)
INSERT INTO approver_settings (approver_name, approver_email, is_active)
VALUES 
    ('George', 'george@nyumbaflow.com', TRUE),
    ('Sam', 'sam@nyumbaflow.com', TRUE)
ON DUPLICATE KEY UPDATE is_active = TRUE;

-- 8. Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description)
VALUES 
    ('email_verification_expiry_hours', '24', 'Email verification link expiry in hours'),
    ('login_code_expiry_minutes', '10', 'Login 6-digit code expiry in minutes'),
    ('account_approval_required', 'true', 'Whether account approval is required'),
    ('send_approval_notifications', 'true', 'Send notifications to approvers')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 9. Create audit_log table for tracking approvals/rejections
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT,
    admin_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES landlords(id) ON DELETE SET NULL,
    INDEX idx_action_entity (action, entity_type),
    INDEX idx_created_at (created_at)
);

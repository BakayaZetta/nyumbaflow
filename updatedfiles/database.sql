/* 
   HomeSync - Relational Database Schema 
   Optimized for Multi-Property Landlords and Dynamic Unit Management
*/

CREATE DATABASE IF NOT EXISTS homesync;
USE homesync;

-- 1. Landlords / Admins Table
CREATE TABLE IF NOT EXISTS landlords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone_number VARCHAR(15) NULL,
    role ENUM('admin', 'superadmin') DEFAULT 'admin',
    status ENUM('active', 'banned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Properties (Apartments/Complexes)
CREATE TABLE IF NOT EXISTS properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES landlords(id) ON DELETE CASCADE
);

-- 3. Units (Houses/Rooms)
CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    unit_number VARCHAR(20) NOT NULL, -- e.g., A1, 101, B-5
    rent_amount DECIMAL(10, 2) NOT NULL,
    water_rate DECIMAL(10, 2) DEFAULT 0,
    wifi_fee DECIMAL(10, 2) DEFAULT 0,
    garbage_fee DECIMAL(10, 2) DEFAULT 0,
    late_fee_enabled BOOLEAN DEFAULT FALSE,
    late_fee_rate DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    UNIQUE KEY unique_unit (property_id, unit_number)
);

-- 4. Tenants
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    unit_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    id_number VARCHAR(20) NOT NULL UNIQUE,
    phone_number VARCHAR(15) NOT NULL,
    move_in_date DATE NOT NULL,
    status ENUM('active', 'vacated') DEFAULT 'active',
    balance_credit DECIMAL(10, 2) DEFAULT 0,
    has_wifi BOOLEAN DEFAULT FALSE,
    has_garbage BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

-- 5. Bills
CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    unit_id INT NOT NULL,
    bill_type ENUM('rent', 'wifi', 'water', 'garbage', 'penalty') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    balance DECIMAL(10, 2) NOT NULL,
    month VARCHAR(20) NOT NULL,
    year INT NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('paid', 'partial', 'unpaid') DEFAULT 'unpaid',
    reading_curr DECIMAL(10, 2) DEFAULT 0,
    reading_prev DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

-- 6. Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('mpesa', 'bank', 'cash') NOT NULL,
    transaction_reference VARCHAR(100) NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
);

-- 7. Contractors / Service Providers
CREATE TABLE IF NOT EXISTS contractors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL, -- e.g., Plumber, Electrician
    phone_number VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES landlords(id) ON DELETE CASCADE
);

-- 8. Security Access (Gate Portal)
CREATE TABLE IF NOT EXISTS security_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    access_token VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- 9. Tenant Access Links
CREATE TABLE IF NOT EXISTS tenant_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    access_token VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- 10. Gate Personnel Authentication
CREATE TABLE IF NOT EXISTS gate_personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    UNIQUE KEY unique_username (property_id, username)
);

-- 11. Caretaker Authentication
CREATE TABLE IF NOT EXISTS caretakers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    UNIQUE KEY unique_caretaker_username (property_id, username)
);

-- 10. Visitor Logs
CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    tenant_id INT NULL,
    name VARCHAR(100) NOT NULL,
    id_number VARCHAR(20) NULL,
    phone_number VARCHAR(15) NOT NULL,
    number_plate VARCHAR(20) NULL,
    visit_date DATE NOT NULL,
    time_in TIME NOT NULL,
    time_out TIME NULL,
    FOREIGN KEY (property_id) REFERENCES properties(id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Create some indexes for performance
CREATE INDEX idx_tenant_phone ON tenants(phone_number);
CREATE INDEX idx_bill_status ON bills(status);
CREATE INDEX idx_unit_property ON units(property_id);

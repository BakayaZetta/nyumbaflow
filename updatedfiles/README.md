# HomeSync - Property Management System

## 📋 Table of Contents
- [Project Overview](#project-overview)
- [Key Features](#key-features)
- [Technology Stack](#technology-stack)
- [Prerequisites](#prerequisites)
- [Local Installation & Setup](#local-installation--setup)
- [Usage Guide](#usage-guide)
- [Hosting Guide](#hosting-guide)
- [Database Schema](#database-schema)
- [API Documentation](#api-documentation)
- [Contributing](#contributing)
- [License](#license)
- [Support](#support)

## 🎯 Project Overview

HomeSync is a comprehensive property management system designed specifically for landlords and property managers in Kenya. The system streamlines property operations including tenant management, automated billing, visitor logging, SMS notifications, and financial reporting. Built with scalability and user experience in mind, HomeSync transforms traditional property management into a digital, efficient process.

### 🎯 Mission
To empower property owners with technology that simplifies property management, reduces operational costs, and enhances tenant satisfaction through automated processes and real-time insights.

### 🎯 Target Audience
- Residential property owners
- Property management companies
- Real estate investors
- Estate managers
- Security personnel

## ✨ Key Features

### 🏠 Core Property Management
- **Multi-Property Support**: Manage multiple properties under one account
- **Unit Management**: Track individual units/houses within properties
- **Tenant Lifecycle**: Complete tenant onboarding to offboarding process
- **Contract Management**: Digital contract storage and management

### 💰 Automated Billing System
- **Rent Billing**: Automated monthly rent generation
- **Utility Billing**: Water, WiFi, and garbage collection tracking
- **Custom Bills**: Create one-time or recurring custom charges
- **Payment Tracking**: Record payments and track outstanding balances
- **Automatic Calculations**: Smart bill calculations with tenant credits

### 👥 Visitor Management
- **Gate Logging**: Real-time visitor entry/exit tracking
- **Security Integration**: Secure access for security personnel
- **Visitor History**: Complete visitor logs with timestamps
- **Mobile-Responsive**: Optimized for security personnel mobile devices

### 📱 Communication & Notifications
- **SMS Integration**: Automated SMS notifications for bills and reminders
- **Bulk Messaging**: Send notices to all tenants or specific groups
- **Payment Confirmations**: Instant SMS receipts for payments
- **Custom Messages**: Personalized communication capabilities

### 📊 Reporting & Analytics
- **Financial Reports**: Income, expenses, and profitability analysis
- **Occupancy Tracking**: Real-time occupancy rates and trends
- **Payment Analytics**: Collection rates and outstanding dues
- **Tenant Insights**: Payment history and behavior patterns

### 🔐 Security & Access Control
- **Role-Based Access**: Separate interfaces for landlords and security personnel
- **Secure Authentication**: Magic link authentication for security staff
- **Session Management**: Automatic session timeout and security checks
- **Data Encryption**: Secure storage of sensitive information

## 🛠 Technology Stack

### Backend
- **Language**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Architecture**: MVC-inspired structure
- **Session Management**: PHP Sessions with custom security

### Frontend
- **HTML5**: Semantic markup and accessibility
- **CSS3**: Custom responsive design with CSS Grid and Flexbox
- **JavaScript**: Vanilla JS with modern ES6+ features
- **Icons**: Font Awesome 6.4.0
- **Fonts**: Inter font family from Google Fonts

### External Services
- **SMS Service**: Custom SmsService class (configurable for Africa's Talking or similar)
- **Email**: PHPMailer (for future email notifications)
- **Charts**: Chart.js (for analytics dashboards)

### Development Tools
- **Version Control**: Git
- **Local Server**: XAMPP/WAMP/MAMP
- **Code Editor**: VS Code recommended
- **Database Management**: phpMyAdmin or MySQL Workbench

## 📋 Prerequisites

### System Requirements
- **Operating System**: Windows 10+, macOS 10.15+, Ubuntu 18.04+
- **RAM**: Minimum 4GB, Recommended 8GB
- **Storage**: 500MB free space
- **Web Browser**: Chrome 90+, Firefox 88+, Safari 14+

### Software Dependencies
- **Web Server**: Apache 2.4+ with mod_rewrite enabled
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7 or higher
- **Composer**: For PHP dependency management (optional)

### PHP Extensions Required
```
php-mysqli
php-pdo
php-session
php-json
php-curl (for SMS API)
php-mbstring
php-xml
```

### Network Requirements
- **Internet Connection**: Required for SMS functionality
- **Firewall**: Open ports 80 (HTTP) and 443 (HTTPS) for web access
- **SMS API**: Valid API credentials for SMS service provider

## 🚀 Local Installation & Setup

### Step 1: Environment Setup

#### Option A: XAMPP Installation (Recommended for Beginners)
1. Download XAMPP from [apachefriends.org](https://www.apachefriends.org/)
2. Install XAMPP with default settings
3. Start Apache and MySQL services from XAMPP Control Panel

#### Option B: Manual Setup
1. Install Apache web server
2. Install PHP 7.4+
3. Install MySQL 5.7+
4. Configure virtual host (optional)

### Step 2: Project Download
```bash
# Clone the repository
git clone https://github.com/yourusername/homesync.git

# Or download ZIP and extract to your web directory
# For XAMPP: htdocs/homesync/
# For manual: /var/www/html/homesync/
```

### Step 3: Database Setup
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create new database: `homesync`
3. Import the database schema:
   - Go to Import tab
   - Select `database.sql` from project root
   - Click "Go" to import

### Step 4: Configuration
1. **Database Configuration** (`db_config.php`):
```php
<?php
$host = 'localhost';
$dbname = 'homesync';
$username = 'root'; // Default XAMPP username
$password = '';     // Default XAMPP password (empty)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
```

2. **SMS Configuration** (`SmsService.php`):
```php
class SmsService {
    private $apiKey = 'YOUR_AFRICAS_TALKING_API_KEY';
    private $username = 'YOUR_AFRICAS_TALKING_USERNAME';
    private $senderId = 'HOMESYNC';
    // ... rest of configuration
}
```

### Step 5: File Permissions
```bash
# Set proper permissions for uploads directory
chmod 755 uploads/
chmod 755 uploads/id_pictures/
```

### Step 6: Initial Setup
1. Open browser and navigate to: `http://localhost/homesync/setup.php`
2. Follow the setup wizard to create admin account
3. Configure SMS settings and property details

### Step 7: Verification
- Access the application: `http://localhost/homesync/`
- Login with created admin credentials
- Test basic functionality (add property, tenant, etc.)

## 📖 Usage Guide

### First-Time Setup
1. **Admin Registration**: Use `setup.php` to create landlord account
2. **Property Setup**: Add your first property and units
3. **Rate Configuration**: Set rent and utility rates in settings
4. **SMS Setup**: Configure SMS API credentials

### Daily Operations

#### For Landlords
1. **Dashboard Overview**: Monitor payments, occupancy, and outstanding dues
2. **Tenant Management**: Add new tenants, update information
3. **Billing**: Generate monthly bills, record payments
4. **Visitor Monitoring**: View visitor logs and security activity
5. **Reports**: Generate financial and occupancy reports

#### For Security Personnel
1. **Access System**: Use magic link sent by landlord
2. **Visitor Logging**: Record visitor details at gate
3. **Real-time Updates**: View current visitors and recent activity
4. **Mobile Optimized**: Use on smartphones for field operations

### Advanced Features

#### Automated Billing
- Set up recurring bills for rent and utilities
- Configure automatic SMS reminders
- Track payment history and overdue accounts

#### Custom Notifications
- Create bulk SMS campaigns
- Send targeted messages to specific tenants
- Schedule automated reminders

#### Financial Management
- Track income and expenses
- Generate profit/loss reports
- Monitor collection rates

## 🌐 Hosting Guide

### Local Hosting Options

#### 1. XAMPP/WAMP for Development
- Install XAMPP on local machine
- Place project in htdocs folder
- Access via `http://localhost/homesync`

#### 2. Docker Containerization
```dockerfile
# Dockerfile for HomeSync
FROM php:7.4-apache
COPY . /var/www/html/
RUN docker-php-ext-install mysqli pdo pdo_mysql
EXPOSE 80
```

### Remote Hosting Platforms

#### Research Results: Best Hosting Platforms for HomeSync

After extensive research, here are the recommended hosting platforms for HomeSync:

##### 🥇 **DigitalOcean** (Recommended)
**Why it's best:**
- Excellent PHP/MySQL performance
- Affordable pricing ($5/month)
- Easy scaling
- Good Kenyan connectivity
- 99.9% uptime SLA

**Setup Instructions:**
1. Create DigitalOcean account
2. Choose Ubuntu 20.04 droplet ($5/month)
3. Connect via SSH:
```bash
ssh root@your_droplet_ip
```

4. Install LAMP stack:
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y
sudo systemctl start apache2
sudo systemctl enable apache2

# Install MySQL
sudo apt install mysql-server -y
sudo mysql_secure_installation

# Install PHP
sudo apt install php libapache2-mod-php php-mysql php-cli php-json php-curl -y

# Enable mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

5. Upload project files:
```bash
# Using SCP from local machine
scp -r /path/to/homesync root@your_droplet_ip:/var/www/html/
```

6. Configure database:
```bash
mysql -u root -p
CREATE DATABASE homesync;
exit;
# Import database
mysql -u root -p homesync < /var/www/html/database.sql
```

7. Configure virtual host:
```bash
sudo nano /etc/apache2/sites-available/homesync.conf
```

Add to the file:
```apache
<VirtualHost *:80>
    ServerAdmin admin@homesync.com
    DocumentRoot /var/www/html/homesync
    ServerName your_domain.com

    <Directory /var/www/html/homesync>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/homesync_error.log
    CustomLog ${APACHE_LOG_DIR}/homesync_access.log combined
</VirtualHost>
```

8. Enable site and restart Apache:
```bash
sudo a2ensite homesync.conf
sudo systemctl restart apache2
```

9. Set proper permissions:
```bash
sudo chown -R www-data:www-data /var/www/html/homesync
sudo chmod -R 755 /var/www/html/homesync
```

10. Configure SSL (Let's Encrypt):
```bash
sudo apt install certbot python3-certbot-apache -y
sudo certbot --apache -d your_domain.com
```

##### 🥈 **Hostinger** (Budget-Friendly)
**Pricing:** $2.99/month
**Why good:** Kenyan data centers, cPanel, easy setup

**Setup Steps:**
1. Purchase shared hosting plan
2. Access cPanel
3. Upload files via File Manager
4. Create MySQL database in cPanel
5. Import database.sql
6. Configure db_config.php
7. Access via provided domain

##### 🥉 **AWS Lightsail** (Scalable)
**Pricing:** $3.50/month
**Why good:** AWS reliability, easy scaling

**Setup Steps:**
1. Create AWS account
2. Launch Lightsail instance (PHP stack)
3. Connect via browser-based SSH
4. Upload files via SCP or git
5. Configure database and permissions

### Domain & SSL Configuration

#### Domain Registration
- **Recommended Registrars:** Namecheap, GoDaddy, or local Kenyan registrars
- **Domain Suggestions:** homesync.co.ke, yourpropertyname.com

#### SSL Certificate Setup
```bash
# Using Certbot for free SSL
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```

### Performance Optimization

#### Apache Configuration
```apache
# /etc/apache2/apache2.conf - Add these settings
<IfModule mpm_prefork_module>
    StartServers 2
    MinSpareServers 2
    MaxSpareServers 5
    MaxRequestWorkers 50
    MaxConnectionsPerChild 1000
</IfModule>
```

#### MySQL Optimization
```sql
-- Add to my.cnf
[mysqld]
innodb_buffer_pool_size = 128M
innodb_log_file_size = 32M
query_cache_size = 64M
max_connections = 100
```

#### PHP Optimization
```ini
; php.ini settings
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
```

### Backup Strategy

#### Automated Backups
```bash
# Create backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u username -p password homesync > /backups/homesync_$DATE.sql
tar -czf /backups/homesync_files_$DATE.tar.gz /var/www/html/homesync
```

#### Cron Job for Daily Backups
```bash
crontab -e
# Add: 0 2 * * * /path/to/backup_script.sh
```

### Monitoring & Maintenance

#### Uptime Monitoring
- Use services like UptimeRobot or Pingdom
- Set up alerts for downtime

#### Log Monitoring
```bash
# Monitor error logs
tail -f /var/log/apache2/error.log
tail -f /var/log/apache2/homesync_error.log
```

## 📊 Database Schema

### Core Tables

#### users (Landlords)
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### properties
```sql
CREATE TABLE properties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    landlord_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    location TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES users(id)
);
```

#### units
```sql
CREATE TABLE units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    unit_number VARCHAR(50) NOT NULL,
    rent_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id)
);
```

#### tenants
```sql
CREATE TABLE tenants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unit_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20),
    id_number VARCHAR(20),
    email VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    balance_credit DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id)
);
```

#### bills
```sql
CREATE TABLE bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    unit_id INT NOT NULL,
    bill_type ENUM('rent', 'water', 'wifi', 'garbage', 'other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) NOT NULL,
    month VARCHAR(20),
    year YEAR,
    reading_prev DECIMAL(10,2),
    reading_curr DECIMAL(10,2),
    due_date DATE,
    status ENUM('paid', 'unpaid', 'partial') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
);
```

#### payments
```sql
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_reference VARCHAR(100),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id)
);
```

#### visitors
```sql
CREATE TABLE visitors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20),
    id_number VARCHAR(20),
    numberplate VARCHAR(20),
    house_number VARCHAR(50),
    visit_date DATE NOT NULL,
    visit_time TIME NOT NULL,
    time_out TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### security_personnel
```sql
CREATE TABLE security_personnel (
    id INT PRIMARY KEY AUTO_INCREMENT,
    landlord_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    access_token VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES users(id)
);
```

## 🔌 API Documentation

### SMS Service API

#### Send SMS
```php
$sms = new SmsService();
$result = $sms->sendSMS($phoneNumber, $message);
```

#### Send Payment Confirmation
```php
$result = $sms->sendPaymentConfirmation($phone, $name, $amount, $balance, $property);
```

#### Send Monthly Breakdown
```php
$data = [
    'property' => 'Property Name',
    'month' => 'December 2023',
    'rent' => 15000,
    'water_units' => 25,
    'water_cost' => 1250,
    'wifi' => 2000,
    'garbage' => 500,
    'credit' => 0,
    'total' => 17750
];
$result = $sms->sendMonthlyBreakdown($phone, $name, $data);
```

### File Upload API

#### ID Document Upload
- **Endpoint**: `upload_id.php`
- **Method**: POST
- **Parameters**: `id_file` (file), `tenant_id` (int)
- **Response**: JSON success/error message

## 🤝 Contributing

### Development Setup
1. Fork the repository
2. Create feature branch: `git checkout -b feature/new-feature`
3. Make changes and test thoroughly
4. Commit changes: `git commit -am 'Add new feature'`
5. Push to branch: `git push origin feature/new-feature`
6. Create Pull Request

### Code Standards
- Follow PSR-12 PHP coding standards
- Use meaningful variable and function names
- Add comments for complex logic
- Test all new features before committing

### Testing Checklist
- [ ] Functionality works as expected
- [ ] No PHP errors or warnings
- [ ] Database queries are optimized
- [ ] Responsive design verified
- [ ] Cross-browser compatibility checked
- [ ] Security vulnerabilities addressed

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Getting Help
- **Documentation**: Check this README first
- **Issues**: Report bugs on GitHub Issues
- **Discussions**: Use GitHub Discussions for questions
- **Email**: jacetechnologies@gmail.com

### Common Issues

#### Database Connection Issues
```php
// Check db_config.php settings
// Verify MySQL service is running
// Check user permissions
```

#### SMS Not Sending
```php
// Verify API credentials in SmsService.php
// Check internet connection
// Confirm API balance/credits
```

#### File Upload Issues
```php
// Check uploads/ directory permissions
// Verify PHP upload settings in php.ini
// Confirm file size limits
```

---

**HomeSync** - Transforming Property Management in Kenya 🇰🇪

*Built with by Jacetechnologies*

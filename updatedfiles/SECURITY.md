# SECURITY CONFIGURATION DOCUMENTATION

## Configuration Files Protection

### Main .htaccess (Root)
- Denies direct access to `config.php`, `db_config.php`, `session_check.php`
- Blocks access to backup files (.bak, .backup, .old, .tmp, .swp)
- Prevents directory listing
- Protects .htaccess itself

### uploads/id_pictures/.htaccess
- Disables PHP execution in upload directory
- Only allows image files (.jpg, .jpeg, .png, .gif)
- Prevents execution of uploaded malicious files

## Database Credentials

### Current Setup
All database credentials are centralized in `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'homesync');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Production Recommendation
For production environments, use environment variables:

1. Update `config.php` to use getenv():
```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'homesync');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
```

2. Set environment variables in Apache vhost:
```apache
SetEnv DB_HOST "localhost"
SetEnv DB_NAME "homesync_prod"
SetEnv DB_USER "homesync_user"
SetEnv DB_PASS "secure_password_here"
```

Or use a `.env` file with a library like `vlucas/phpdotenv`.

## Session Management

### Implemented Security Features
1. **Session Timeout** - 10 minutes (600 seconds) defined in `SESSION_TIMEOUT`
2. **Session Regeneration** - ID regenerated every 5 minutes to prevent fixation
3. **Logout Functionality** - `logout.php` destroys session properly
4. **Auto-check** - Session validated on every page load via `session_check.php`

### Session Security in Files
- `session_check.php` - Validates timeout and regenerates session ID
- `logout.php` - Properly destroys session
- All protected pages use `requireLogin()` function

## File Upload Security

### Validation Implemented in `tenants.php`
1. **File Type Validation** - Only image MIME types allowed (image/jpeg, image/png, image/gif)
2. **Extension Validation** - Only .jpg, .jpeg, .png, .gif extensions allowed
3. **Size Limit** - Maximum 5MB per file
4. **Secure Filenames** - Generated using `uniqid('id_', true)` to prevent path traversal
5. **Directory Permissions** - Set to 0755 (not 0777) to prevent execution
6. **PHP Execution Blocked** - .htaccess in uploads directory prevents PHP execution

### Upload Directory Structure
```
uploads/
└── id_pictures/
    ├── .htaccess (blocks PHP execution)
    └── [uploaded images]
```

## No Sensitive Files in Web Root

### Verified Clean
- ✅ No .env files
- ✅ No .bak backup files
- ✅ No .log files exposed
- ✅ No .git directory (if using version control, add to .htaccess)

### Recommended .htaccess Addition (if using Git)
```apache
<DirectoryMatch "^\.|\/\.">
    Order allow,deny
    Deny from all
</DirectoryMatch>
```

## Security Checklist

- [x] Config files protected from direct web access
- [x] Database credentials centralized in config.php
- [x] Session timeout implemented (10 minutes)
- [x] Logout functionality exists
- [x] Session regeneration prevents fixation
- [x] File upload validation (type, size, extension)
- [x] Upload directory secured (no PHP execution)
- [x] No backup/log files in web root
- [x] Directory listing disabled
- [x] All SQL queries use prepared statements
- [x] Bootstrap protection (secret + rate limit + password strength)
- [x] Universal output escaping (XSS prevention)
- [x] Strict input validation and parameter casting
- [x] Centralized sanitization helper (sanitize.php)

## Additional Recommendations

### For Production
1. Enable HTTPS and set session cookies to secure-only
2. Add CSP (Content Security Policy) headers
3. Implement rate limiting for login attempts
4. Add CSRF tokens to forms
5. Enable error logging to files (not browser display)
6. Use environment variables for all secrets
7. Regular security audits and updates
## Bootstrap Protection (Feature 10)

### Threat: Account Takeover on Fresh Deploy
On a fresh deploy with no superadmin in the database, `super_login.php` shows a bootstrap form.
Without protection, anyone who discovers the URL could create the superadmin and own the system.

### Protections Implemented
1. **Bootstrap Secret** — A `BOOTSTRAP_SECRET` constant in `config.php` must be set to a unique value before deploying. The bootstrap form requires this secret to submit.
2. **Default Sentinel Check** — If the secret is still `CHANGE_ME_ON_DEPLOY`, bootstrap is blocked entirely.
3. **CSRF Protection** — Both bootstrap and login forms include CSRF tokens via `csrf_token.php`.
4. **Rate Limiting** — Bootstrap attempts are rate-limited to 3 per hour per IP.
5. **Password Strength** — Superadmin password must be ≥8 characters with uppercase, lowercase, and digit.
6. **Input Validation** — Username (3-50 alphanumeric), email (valid format), and password are all validated server-side.
7. **Audit Logging** — Successful bootstrap events are logged with IP and timestamp via `error_log()`.

### Deploy Checklist
1. Open `config.php` and change `BOOTSTRAP_SECRET` to a unique, secret value
2. Navigate to `super_login.php` and enter the secret + credentials to bootstrap
3. After superadmin is created, the bootstrap form disappears permanently (as long as a superadmin exists)

## Input Validation & Output Escaping (Feature 11)

### Threat: Cross-Site Scripting (XSS) & Business Logic Bypass
Malicious users can submit scripts or manipulate parameters to steal sessions, execute unauthorized code, or bypass property-level isolation.

### Protections Implemented
1. **Centralized Sanitization** — A new `sanitize.php` provides:
   - `esc($data)`: Shorthand for `htmlspecialchars($data, ENT_QUOTES, 'UTF-8')`.
   - `sanitize_string($data)`: Cleans input strings.
   - `sanitize_int($data)`: Casts and validates integers.
2. **Universal Output Escaping** — Every dynamic output in the following files now uses `esc()`:
   - Dashboard & Billing: `index.php`, `super_dashboard.php`, `billing.php`
   - Registry: `tenants.php`, `visitors.php`, `contractors.php`
   - Access & Portals: `access_control.php`, `caretaker_portal.php`, `gate.php`, `gate/index2.php`
   - Auth: `personnel_login.php`, `gate/login.php`
   - Structure: `sidebar.php`, `notifications.php`, `security.php`, `settings.php`
3. **Strict Parameter Casting** — GET/POST parameters used as IDs (e.g., `property_id`, `unit_id`, `visitor_id`) are cast to `int` and validated against authorized data.
4. **Validation Logic** — Critical flows like `onboarding_action.php` and `gate/index2.php` implement strict required-field checks and format validation.
<?php
// data/config.php
// Enhanced configuration with security settings

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Set your MySQL password here
define('DB_NAME', 'aims_ver1');

// Security configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds
define('CSRF_TOKEN_EXPIRE', 3600); // 1 hour
define('MAX_FILE_SIZE', 10485760); // 10MB in bytes
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'txt']);

// Application configuration
define('SITE_NAME', 'GLC Academic Information Management System');
define('SITE_URL', 'http://localhost/AIMS_ver1');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('LOG_PATH', __DIR__ . '/../logs/');

// Create necessary directories
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

// Set PHP security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('display_errors', 0); // Set to 1 for development
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('Asia/Manila');
?>
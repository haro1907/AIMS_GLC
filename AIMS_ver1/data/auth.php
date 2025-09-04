<?php
// data/auth.php
// Enhanced authentication system with security features

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

class Auth {
    private static $currentUser = null;
    
    public static function login($username, $password) {
        // Check for too many failed attempts
        if (self::isLockedOut($username)) {
            return ['success' => false, 'message' => 'Account temporarily locked due to too many failed attempts.'];
        }
        
        // Get user from database
        $user = fetchOne("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);
        
        if (!$user) {
            self::recordFailedAttempt($username);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        // Check password - handle both hashed and plain text for development
        $passwordValid = false;
        if (strlen($user['password']) > 50) {
            // Likely a hashed password
            $passwordValid = password_verify($password, $user['password']);
        } else {
            // Plain text password for development
            $passwordValid = ($password === $user['password']);
        }
        
        if (!$passwordValid) {
            self::recordFailedAttempt($username);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        // Clear failed attempts on successful login
        self::clearFailedAttempts($username);
        
        // Generate session token
        $sessionToken = bin2hex(random_bytes(32));
        $sessionExpires = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        
        // Update user's session info
        executeUpdate(
            "UPDATE users SET session_token = ?, session_expires = ?, last_login = NOW() WHERE id = ?",
            [$sessionToken, $sessionExpires, $user['id']]
        );
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_name'] = self::getRoleName($user['role_id']);
        $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['last_activity'] = time();
        
        // Log successful login
        ActivityLogger::log($user['id'], 'LOGIN', 'users', $user['id']);
        
        return ['success' => true, 'redirect' => self::getRedirectUrl($user['role_id'])];
    }
    
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            // Clear session token in database
            executeUpdate("UPDATE users SET session_token = NULL, session_expires = NULL WHERE id = ?", [$_SESSION['user_id']]);
            
            // Log logout
            ActivityLogger::log($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id']);
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        // Start new session
        session_start();
        session_regenerate_id(true);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: /AIMS_ver1/index.php?msg=" . urlencode("Please log in to access this page."));
            exit;
        }
        
        // Check session timeout
        if (self::isSessionExpired()) {
            self::logout();
            header("Location: /AIMS_ver1/index.php?msg=" . urlencode("Session expired. Please log in again."));
            exit;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
    }
    
    public static function requireRole($allowedRoles = []) {
        self::requireLogin();
        
        $currentRole = $_SESSION['role_name'] ?? '';
        if (!in_array($currentRole, $allowedRoles)) {
            http_response_code(403);
            include __DIR__ . '/../shared/access_denied.php';
            exit;
        }
    }
    
    public static function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        // Verify session token in database
        $user = fetchOne(
            "SELECT id FROM users WHERE id = ? AND session_token = ? AND session_expires > NOW()",
            [$_SESSION['user_id'], $_SESSION['session_token']]
        );
        
        return $user !== false;
    }
    
    public static function isSessionExpired() {
        if (!isset($_SESSION['last_activity'])) {
            return true;
        }
        
        return (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT;
    }
    
    public static function getCurrentUser() {
        if (self::$currentUser === null && self::isLoggedIn()) {
            self::$currentUser = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
        }
        return self::$currentUser;
    }
    
    public static function hasRole($role) {
        return isset($_SESSION['role_name']) && $_SESSION['role_name'] === $role;
    }
    
    public static function hasPermission($permission) {
        $role = $_SESSION['role_name'] ?? '';
        
        $permissions = [
            'Super Admin' => ['*'], // All permissions
            'Registrar' => ['manage_grades', 'upload_files', 'view_students'],
            'SAO' => ['manage_announcements', 'manage_inventory', 'approve_borrows'],
            'Student' => ['view_own_data', 'borrow_items']
        ];
        
        if (!isset($permissions[$role])) {
            return false;
        }
        
        return in_array('*', $permissions[$role]) || in_array($permission, $permissions[$role]);
    }
    
    private static function getRoleName($roleId) {
        $role = fetchOne("SELECT role FROM roles WHERE id = ?", [$roleId]);
        return $role ? $role['role'] : '';
    }
    
    public static function getRedirectUrl($roleId) {
        $redirects = [
            1 => '/AIMS_ver1/admin/dashboard.php',
            2 => '/AIMS_ver1/registrar/dashboard.php',
            3 => '/AIMS_ver1/sao/dashboard.php',
            4 => '/AIMS_ver1/student/dashboard.php'
        ];
        
        return $redirects[$roleId] ?? '/AIMS_ver1/student/dashboard.php';
    }
    
    private static function isLockedOut($username) {
        $lockFile = LOG_PATH . 'failed_attempts_' . md5($username) . '.json';
        
        if (!file_exists($lockFile)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($lockFile), true);
        
        if ($data['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $lockoutEnd = $data['last_attempt'] + LOGIN_LOCKOUT_TIME;
            return time() < $lockoutEnd;
        }
        
        return false;
    }
    
    private static function recordFailedAttempt($username) {
        $lockFile = LOG_PATH . 'failed_attempts_' . md5($username) . '.json';
        
        $data = ['attempts' => 0, 'last_attempt' => 0];
        if (file_exists($lockFile)) {
            $data = json_decode(file_get_contents($lockFile), true);
        }
        
        $data['attempts']++;
        $data['last_attempt'] = time();
        
        file_put_contents($lockFile, json_encode($data));
    }
    
    private static function clearFailedAttempts($username) {
        $lockFile = LOG_PATH . 'failed_attempts_' . md5($username) . '.json';
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
}

class ActivityLogger {
    public static function log($userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        executeUpdate(
            "INSERT INTO activity_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $action,
                $tableName,
                $recordId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $ipAddress,
                $userAgent
            ]
        );
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-logout on session timeout
if (isset($_SESSION['user_id']) && Auth::isSessionExpired()) {
    Auth::logout();
}
?>
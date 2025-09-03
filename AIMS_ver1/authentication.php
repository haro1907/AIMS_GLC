<?php
// authentication.php - Enhanced authentication handler
require_once __DIR__ . '/data/config.php';
require_once __DIR__ . '/data/db.php';
require_once __DIR__ . '/data/auth.php';
require_once __DIR__ . '/data/security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    $redirectUrl = Auth::getRedirectUrl($user['role_id']);
    header("Location: $redirectUrl");
    exit;
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /AIMS_ver1/index.php");
    exit;
}

// Rate limiting check
if (!Security::checkRateLimit('login', 5, 900)) {
    header("Location: /AIMS_ver1/index.php?msg=" . urlencode("Too many login attempts. Please try again in 15 minutes."));
    exit;
}

// Verify CSRF token
$csrfToken = getPost('csrf_token');
if (!Security::verifyCSRFToken($csrfToken)) {
    Security::logSecurityEvent('csrf_token_mismatch', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    header("Location: /AIMS_ver1/index.php?msg=" . urlencode("Invalid security token. Please refresh the page and try again."));
    exit;
}

// Get and validate input
$username = getPost('username');
$password = $_POST['password'] ?? ''; // Don't sanitize password

// Validate input
$errors = Security::validateInput([
    'username' => $username,
    'password' => $password
], [
    'username' => 'required|min:3|max:50',
    'password' => 'required|min:6'
]);

if (!empty($errors)) {
    $errorMessage = "Please check your input and try again.";
    header("Location: /AIMS_ver1/index.php?msg=" . urlencode($errorMessage));
    exit;
}

// Attempt login
$result = Auth::login($username, $password);

if ($result['success']) {
    // Successful login - redirect to appropriate dashboard
    header("Location: " . $result['redirect']);
    exit;
} else {
    // Failed login - log attempt and redirect back
    Security::logSecurityEvent('failed_login', [
        'username' => $username,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    header("Location: /AIMS_ver1/index.php?msg=" . urlencode($result['message']));
    exit;
}
?>
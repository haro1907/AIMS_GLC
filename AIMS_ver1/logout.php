<?php
// logout.php - Enhanced logout handler
require_once __DIR__ . '/data/auth.php';

// Log the logout action before destroying session
if (Auth::isLoggedIn()) {
    ActivityLogger::log($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id']);
    
    // Clear session token in database
    executeUpdate("UPDATE users SET session_token = NULL, session_expires = NULL WHERE id = ?", [$_SESSION['user_id']]);
}

// Destroy session
Auth::logout();

// Redirect to login page with message
header("Location: /AIMS_ver1/index.php?msg=" . urlencode("You have been logged out successfully."));
exit;
?>
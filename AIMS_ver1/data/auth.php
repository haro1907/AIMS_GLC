<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/db.php";

function require_login() {
    if (!isset($_SESSION["user_id"])) {
        header("Location: /AIMS_ver1/index.php?msg=Please+log+in");
        exit;
    }
}

function require_role($allowed_roles = []) {
    require_login();
    $role = $_SESSION["role_name"] ?? "";
    if (!in_array($role, $allowed_roles)) {
        echo "<h3 style='font-family:sans-serif'>Access denied for role: " . htmlspecialchars($role) . "</h3>";
        echo "<p><a href='/AIMS_ver1/logout.php'>Logout</a></p>";
        exit;
    }
}
?>

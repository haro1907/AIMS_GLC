<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/data/db.php";

$username = trim($_POST["username"] ?? "");
$password = trim($_POST["password"] ?? "");

if ($username === "" || $password === "") {
    header("Location: index.php?msg=Missing+username+or+password");
    exit;
}

$stmt = mysqli_prepare($con, "SELECT u.id, u.username, u.password, u.email, r.role 
                              FROM users u 
                              JOIN roles r ON r.id = u.role_id 
                              WHERE u.username = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);

if (!$user || $user["password"] !== $password) {
    header("Location: index.php?msg=Invalid+credentials");
    exit;
}

$_SESSION["user_id"]   = (int)$user["id"];
$_SESSION["username"]  = $user["username"];
$_SESSION["role_name"] = $user["role"];
$_SESSION["email"]     = $user["email"] ?? "";

switch ($_SESSION["role_name"]) {
    case "Super Admin":
        header("Location: /AIMS_ver1/admin/dashboard.php"); break;
    case "Registrar":
        header("Location: /AIMS_ver1/registrar/dashboard.php"); break;
    case "SAO":
        header("Location: /AIMS_ver1/sao/dashboard.php"); break;
    case "Student":
        header("Location: /AIMS_ver1/student/dashboard.php"); break;
    default:
        session_destroy();
        header("Location: /AIMS_ver1/index.php?msg=Invalid+role");
}
exit;

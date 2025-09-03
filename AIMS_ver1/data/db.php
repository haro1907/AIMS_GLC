<?php
$host = "localhost";
$user = "root";
$pass = ""; // Set your MySQL root password here if you have one
$db   = "aims_ver1";

$con = mysqli_connect($host, $user, $pass, $db);
if (!$con) {
    die("Database connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($con, "utf8mb4");
?>

<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GLC AIMS</title>
  <style>
    body { font-family: Arial, sans-serif; margin:0; background:#f7f7fb; }
    header { background:#1f3a93; color:#fff; padding:14px 20px; }
    nav { background:#fff; border-bottom:1px solid #e5e7eb; padding:10px 20px; display:flex; gap:10px; flex-wrap:wrap; }
    .wrap { max-width:1000px; margin: 22px auto; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px; }
    a.btn { text-decoration:none; padding:8px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; }
    a.btn:hover { background:#f1f5f9; }
    .muted { color:#6b7280; }
    .right { margin-left:auto; }
    input, select, textarea { padding:8px; border:1px solid #d1d5db; border-radius:8px; width:100%; box-sizing:border-box; }
    label { display:block; margin-top:10px; font-weight:bold; }
    button { padding:8px 12px; border-radius:8px; border:0; background:#1f3a93; color:#fff; cursor:pointer; }
    button:hover { opacity:0.95; }
    table { border-collapse:collapse; width:100%; }
    th, td { border:1px solid #e5e7eb; padding:8px; text-align:left; }
    th { background:#f8fafc; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
  </style>
</head>
<body>
<header>
  <div style="display:flex; align-items:center; gap:10px;">
    <strong>Student Portal</strong>
    <span class="muted">| role: <?php echo htmlspecialchars($_SESSION["role_name"] ?? "guest"); ?></span>
    <span class="right"></span>
    <?php if (!empty($_SESSION["user_id"])): ?>
      <a class="btn" href="/AIMS_ver1/logout.php">Logout</a>
    <?php endif; ?>
  </div>
</header>
<nav>
  <?php
    $role = $_SESSION["role_name"] ?? "";
    if ($role === "Super Admin") {
        echo "<a class='btn' href='/AIMS_ver1/admin/dashboard.php'>Admin Dashboard</a>";
    } else if ($role === "Registrar") {
        echo "<a class='btn' href='/AIMS_ver1/registrar/dashboard.php'>Registrar Dashboard</a>";
        echo "<a class='btn' href='/AIMS_ver1/registrar/upload_grades.php'>Upload Grades</a>";
        echo "<a class='btn' href='/AIMS_ver1/registrar/upload_files.php'>Upload Files</a>";
    } else if ($role === "SAO") {
        echo "<a class='btn' href='/AIMS_ver1/sao/dashboard.php'>SAO Dashboard</a>";
        echo "<a class='btn' href='/AIMS_ver1/sao/announcements.php'>Announcements</a>";
    } else if ($role === "Student") {
        echo "<a class='btn' href='/AIMS_ver1/student/dashboard.php'>Student Dashboard</a>";
        echo "<a class='btn' href='/AIMS_ver1/student/grades.php'>My Grades</a>";
        echo "<a class='btn' href='/AIMS_ver1/student/files.php'>My Files</a>";
        echo "<a class='btn' href='/AIMS_ver1/student/announcements.php'>Announcements</a>";
    } else {
        echo "<a class='btn' href='/AIMS_ver1/index.php'>Login</a>";
    }
  ?>
</nav>
<div class="wrap">

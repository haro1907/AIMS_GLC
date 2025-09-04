<?php
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(["Super Admin"]);
include __DIR__ . "/../shared/header.php";
?>
<h2>Super Admin Dashboard</h2>
<p>Use phpMyAdmin to manage users and roles for now (DB: <strong>student_portal</strong>).</p>
<ul>
  <li>roles: 1=Super Admin, 2=Registrar, 3=SAO, 4=Student</li>
  <li>users: set role_id accordingly</li>
</ul>
<?php include __DIR__ . "/../shared/footer.php"; ?>

<?php
require_once __DIR__ . "/../data/auth.php";
require_role(["Registrar"]);
include __DIR__ . "/../shared/header.php";
?>
<h2>Registrar Dashboard</h2>
<p>Use the links in the navigation bar to upload grades or student files.</p>
<?php include __DIR__ . "/../shared/footer.php"; ?>

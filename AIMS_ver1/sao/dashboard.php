<?php
require_once __DIR__ . "/../data/auth.php";
require_role(["SAO"]);
include __DIR__ . "/../shared/header.php";
?>
<h2>SAO Dashboard</h2>
<p>Use the Announcements page to create and view announcements.</p>
<?php include __DIR__ . "/../shared/footer.php"; ?>

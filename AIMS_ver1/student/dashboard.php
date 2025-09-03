<?php
require_once __DIR__ . "/../data/auth.php";
require_role(["Student"]);
include __DIR__ . "/../shared/header.php";
?>
<h2>Student Dashboard</h2>
<p>Welcome! Use the links above to see your grades, files, and announcements.</p>
<?php include __DIR__ . "/../shared/footer.php"; ?>

<?php
require_once __DIR__ . "/../data/auth.php";
require_role(["Student"]);
include __DIR__ . "/../shared/header.php";
require_once __DIR__ . "/../data/db.php";

$uid = (int)$_SESSION["user_id"];
$res = mysqli_query($con, "SELECT subject, grade, semester, school_year FROM grades WHERE user_id = $uid ORDER BY id DESC");
?>
<h2>My Grades</h2>
<table>
  <tr><th>Subject</th><th>Grade</th><th>Semester</th><th>School Year</th></tr>
  <?php while ($row = mysqli_fetch_assoc($res)): ?>
    <tr>
      <td><?php echo htmlspecialchars($row["subject"]); ?></td>
      <td><?php echo htmlspecialchars($row["grade"]); ?></td>
      <td><?php echo htmlspecialchars($row["semester"]); ?></td>
      <td><?php echo htmlspecialchars($row["school_year"]); ?></td>
    </tr>
  <?php endwhile; ?>
</table>
<?php include __DIR__ . "/../shared/footer.php"; ?>

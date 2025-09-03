<?php
require_once __DIR__ . "/../data/auth.php";
require_role(["Student"]);
include __DIR__ . "/../shared/header.php";
require_once __DIR__ . "/../data/db.php";

$uid = (int)$_SESSION["user_id"];
$res = mysqli_query($con, "SELECT file_name, file_path, uploaded_at FROM student_files WHERE user_id = $uid ORDER BY id DESC");
?>
<h2>My Files</h2>
<table>
  <tr><th>File</th><th>Uploaded</th></tr>
  <?php while ($row = mysqli_fetch_assoc($res)): ?>
    <tr>
      <td><a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($row["file_name"]); ?></a></td>
      <td><?php echo htmlspecialchars($row["uploaded_at"]); ?></td>
    </tr>
  <?php endwhile; ?>
</table>
<?php include __DIR__ . "/../shared/footer.php"; ?>

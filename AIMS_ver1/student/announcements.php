<?php
require_once __DIR__ . "/../data/auth.php";
require_role(["Student"]);
include __DIR__ . "/../shared/header.php";
require_once __DIR__ . "/../data/db.php";

$res = mysqli_query($con, "SELECT a.title, a.content, a.posted_at, u.username AS author
                           FROM announcements a 
                           LEFT JOIN users u ON u.id = a.posted_by
                           ORDER BY a.posted_at DESC");
?>
<h2>Announcements</h2>
<table>
  <tr><th>Title</th><th>Content</th><th>Author</th><th>Posted</th></tr>
  <?php while ($row = mysqli_fetch_assoc($res)): ?>
    <tr>
      <td><?php echo htmlspecialchars($row["title"]); ?></td>
      <td><?php echo nl2br(htmlspecialchars($row["content"])); ?></td>
      <td><?php echo htmlspecialchars($row["author"] ?? ""); ?></td>
      <td><?php echo htmlspecialchars($row["posted_at"]); ?></td>
    </tr>
  <?php endwhile; ?>
</table>
<?php include __DIR__ . "/../shared/footer.php"; ?>

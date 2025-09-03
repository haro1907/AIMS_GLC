<?php
require_once __DIR__ . "/../data/auth.php";
require_role(["SAO"]);
include __DIR__ . "/../shared/header.php";
require_once __DIR__ . "/../data/db.php";

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $content = trim($_POST["content"] ?? "");
    $uid = (int)($_SESSION["user_id"] ?? 0);
    if ($title === "" || $content === "") {
        $msg = "Title and Content are required.";
    } else {
        $stmt = mysqli_prepare($con, "INSERT INTO announcements (title, content, posted_by) VALUES (?,?,?)");
        mysqli_stmt_bind_param($stmt, "ssi", $title, $content, $uid);
        if (mysqli_stmt_execute($stmt)) {
            $msg = "Announcement posted.";
        } else {
            $msg = "DB error: " . htmlspecialchars(mysqli_error($con));
        }
    }
}

$res = mysqli_query($con, "SELECT a.id, a.title, a.content, a.posted_at, u.username AS author
                           FROM announcements a 
                           LEFT JOIN users u ON u.id = a.posted_by
                           ORDER BY a.posted_at DESC");
?>
<h2>Announcements</h2>
<?php if ($msg): ?><p><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
<form method="post">
  <label>Title</label>
  <input name="title" required>
  <label>Content</label>
  <textarea name="content" rows="5" required></textarea>
  <button type="submit">Post Announcement</button>
</form>
<hr>
<h3>Recent</h3>
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

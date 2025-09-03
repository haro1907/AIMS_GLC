<?php
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(["Registrar"]);
include __DIR__ . "/../shared/header.php";
require_once __DIR__ . "/../data/db.php";

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username   = trim($_POST["username"] ?? "");
    $subject    = trim($_POST["subject"] ?? "");
    $grade      = trim($_POST["grade"] ?? "");
    $semester   = trim($_POST["semester"] ?? "");
    $schoolyear = trim($_POST["school_year"] ?? "");

    if ($username === "" || $subject === "" || $grade === "") {
        $msg = "Please fill in username, subject, and grade.";
    } else {
        $stmt = mysqli_prepare($con, "SELECT id FROM users WHERE username=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $u = mysqli_fetch_assoc($res);
        if (!$u) {
            $msg = "Username not found.";
        } else {
            $uid = (int)$u["id"];
            $stmt2 = mysqli_prepare($con, "INSERT INTO grades (user_id, subject, grade, semester, school_year) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt2, "isdss", $uid, $subject, $grade, $semester, $schoolyear);
            if (mysqli_stmt_execute($stmt2)) {
                $msg = "Grade saved.";
            } else {
                $msg = "DB error: " . htmlspecialchars(mysqli_error($con));
            }
        }
    }
}
?>
<h2>Upload Grades</h2>
<?php if ($msg): ?><p><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
<form method="post">
  <div class="grid">
    <div>
      <label>Student Username</label>
      <input name="username" required>
    </div>
    <div>
      <label>Subject</label>
      <input name="subject" required>
    </div>
  </div>
  <div class="grid">
    <div>
      <label>Grade</label>
      <input type="number" step="0.01" name="grade" required>
    </div>
    <div>
      <label>Semester</label>
      <input name="semester" placeholder="e.g. 1st Semester">
    </div>
  </div>
  <label>School Year</label>
  <input name="school_year" placeholder="e.g. 2025-2026">
  <button type="submit">Save Grade</button>
</form>
<?php include __DIR__ . "/../shared/footer.php"; ?>

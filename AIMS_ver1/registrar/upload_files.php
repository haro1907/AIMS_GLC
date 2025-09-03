<?php
require_once __DIR__ . "/../data/auth.php";
require_role(["Registrar"]);
include __DIR__ . "/../shared/header.php";
require_once __DIR__ . "/../data/db.php";

$uploadDir = __DIR__ . "/../uploads";
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    if ($username === "" || empty($_FILES["file"]["name"])) {
        $msg = "Please provide username and choose a file.";
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
            $orig = basename($_FILES["file"]["name"]);
            $safe = preg_replace("/[^A-Za-z0-9._-]/", "_", $orig);
            $target = $uploadDir . "/" . time() . "_" . $safe;
            if (move_uploaded_file($_FILES["file"]["tmp_name"], $target)) {
                $rel = "/AIMS_ver1/uploads/" . basename($target);
                $stmt2 = mysqli_prepare($con, "INSERT INTO student_files (user_id, file_name, file_path) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt2, "iss", $uid, $safe, $rel);
                if (mysqli_stmt_execute($stmt2)) {
                    $msg = "File uploaded.";
                } else {
                    $msg = "DB error: " . htmlspecialchars(mysqli_error($con));
                }
            } else {
                $msg = "Upload failed.";
            }
        }
    }
}
?>
<h2>Upload Student Files / Credentials</h2>
<?php if ($msg): ?><p><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
  <label>Student Username</label>
  <input name="username" required>
  <label>Choose File (PDF/Image)</label>
  <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.gif" required>
  <button type="submit">Upload</button>
</form>
<?php include __DIR__ . "/../shared/footer.php"; ?>

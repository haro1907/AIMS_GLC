<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<?php include __DIR__ . "/shared/header.php"; ?>
<h2>Login</h2>
<?php if (!empty($_GET["msg"])): ?>
  <p style="color:#b91c1c;"><?php echo htmlspecialchars($_GET["msg"]); ?></p>
<?php endif; ?>
<form method="post" action="authentication.php">
  <label>Username</label>
  <input type="text" name="username" required>
  <label>Password</label>
  <input type="password" name="password" required>
  <button type="submit">Login</button>
</form>
<?php include __DIR__ . "/shared/footer.php"; ?>

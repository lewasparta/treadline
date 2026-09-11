<?php
require_once __DIR__ . '/config/config.php';

if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON u.role_id=r.id WHERE u.username = ? OR u.email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'ACTIVE' && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['last_activity'] = time();
        if (!empty($_POST['remember'])) {
            $token = bin2hex(random_bytes(32));
            db()->prepare("UPDATE users SET remember_token=? WHERE id=?")->execute([$token, $user['id']]);
            setcookie('tl_remember', $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
        }
        db()->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
        log_action('LOGIN', 'user', $user['id']);
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · TREADLINE</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-brand">
      <div class="mark">T</div>
      <div class="name">TREADLINE</div>
      <div class="tag">SMART FLEET TYRE TRACKING</div>
    </div>

    <?php if (!empty($_GET['timeout'])): ?>
      <div style="background:#fdf1de;color:#a85c00;padding:10px;border-radius:8px;font-size:13px;margin-bottom:14px;">Session expired. Please log in again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div style="background:#fde8e8;color:#c0272f;padding:10px;border-radius:8px;font-size:13px;margin-bottom:14px;"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <label class="form-label">Username or Email</label>
      <input class="form-control" name="username" required autofocus style="margin-bottom:14px;">
      <label class="form-label">Password</label>
      <input class="form-control" type="password" name="password" required style="margin-bottom:10px;">
      <div class="flex items-center" style="justify-content:space-between;margin-bottom:18px;">
        <label style="font-size:12.5px;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="remember"> Remember me</label>
        <a href="forgot_password.php" style="font-size:12.5px;">Forgot password?</a>
      </div>
      <button class="btn btn-accent" type="submit" style="width:100%;justify-content:center;padding:11px;">Sign In</button>
    </form>
    <p style="text-align:center;font-size:11.5px;color:#8a95a8;margin-top:20px;">
    <br>
      First time? Run <a href="setup.php">setup.php</a> to install the database.
    </p>
  </div>
</div>
</body>
</html>

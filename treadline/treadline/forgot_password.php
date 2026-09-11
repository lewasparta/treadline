<?php
require_once __DIR__ . '/config/config.php';
$msg = null; $mode = 'request';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $stmt = db()->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$_POST['email'], $_POST['email']]);
    $u = $stmt->fetch();
    if ($u) {
        $tok = bin2hex(random_bytes(24));
        db()->prepare("UPDATE users SET reset_token=?, reset_expires=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=?")
            ->execute([$tok, $u['id']]);
        $msg = "Reset link generated. In production this would be emailed — for demo purposes, here it is: " .
               "<a href='forgot_password.php?token=$tok'>Reset your password</a>";
    } else {
        $msg = "If that account exists, a reset link has been generated.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $stmt = db()->prepare("SELECT id FROM users WHERE reset_token=? AND reset_expires > NOW()");
    $stmt->execute([$_POST['token']]);
    $u = $stmt->fetch();
    if ($u) {
        $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        db()->prepare("UPDATE users SET password_hash=?, reset_token=NULL WHERE id=?")->execute([$hash, $u['id']]);
        $msg = "Password updated. <a href='index.php'>Login now</a>";
    } else {
        $msg = "This reset link is invalid or has expired.";
    }
} elseif ($token) {
    $mode = 'reset';
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password · TREADLINE</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;600;400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css"></head><body>
<div class="login-wrap"><div class="login-card">
<div class="login-brand"><div class="mark">T</div><div class="name">TREADLINE</div><div class="tag">PASSWORD RESET</div></div>
<?php if ($msg): ?><div style="background:#e8f0fe;padding:12px;border-radius:8px;font-size:13px;margin-bottom:14px;"><?= $msg ?></div><?php endif; ?>
<?php if ($mode === 'reset' && !str_contains($msg ?? '', 'updated')): ?>
  <form method="post">
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label class="form-label">New Password</label>
    <input class="form-control" type="password" name="new_password" required style="margin-bottom:14px;">
    <button class="btn btn-accent" style="width:100%;justify-content:center;padding:11px;">Update Password</button>
  </form>
<?php elseif (!$msg): ?>
  <form method="post">
    <label class="form-label">Enter your username or email</label>
    <input class="form-control" name="email" required style="margin-bottom:14px;">
    <button class="btn btn-accent" style="width:100%;justify-content:center;padding:11px;">Send Reset Link</button>
  </form>
<?php endif; ?>
<p style="text-align:center;font-size:12px;margin-top:16px;"><a href="index.php">Back to login</a></p>
</div></div></body></html>

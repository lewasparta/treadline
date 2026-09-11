<?php
/**
 * TREADLINE - One-time setup script.
 * Visit setup.php in your browser once after configuring config/db.php
 * (or set TREADLINE_DB_HOST / TREADLINE_DB_NAME / TREADLINE_DB_USER / TREADLINE_DB_PASS env vars).
 * It creates the database + tables from sql/schema.sql and sets a real
 * bcrypt hash for the default admin account. Delete or lock this file afterwards.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$host = getenv('TREADLINE_DB_HOST') ?: ($_POST['host'] ?? 'localhost');
$user = getenv('TREADLINE_DB_USER') ?: ($_POST['user'] ?? 'root');
$pass = getenv('TREADLINE_DB_PASS') ?: ($_POST['pass'] ?? '');
$name = getenv('TREADLINE_DB_NAME') ?: ($_POST['name'] ?? 'treadline');
$done = false;
$error = null;
$adminPass = $_POST['admin_password'] ?? 'admin123';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
        // Replace DB name placeholder if custom name given
        $sql = str_replace('treadline', $name, $sql);
        // Execute statement by statement (simple splitter on ;\n)
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
            if ($stmt === '' || str_starts_with($stmt, '--')) continue;
            try { $pdo->exec($stmt . ';'); } catch (Exception $ignore) { /* skip comments/blank */ }
        }
        $pdo->exec("USE `$name`");
        $hash = password_hash($adminPass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash=? WHERE username='admin'")->execute([$hash]);

        // Write a local config override
        $cfg = "<?php\nputenv('TREADLINE_DB_HOST=" . $host . "');\nputenv('TREADLINE_DB_NAME=" . $name . "');\nputenv('TREADLINE_DB_USER=" . $user . "');\nputenv('TREADLINE_DB_PASS=" . $pass . "');\n";
        file_put_contents(__DIR__ . '/config/env.php', $cfg);

        $done = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Treadline Setup</title>
<style>body{font-family:Arial,sans-serif;background:#101a2c;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#fff;color:#1c2537;padding:36px;border-radius:14px;max-width:460px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.4)}
h1{font-size:20px;margin-top:0} label{font-size:13px;font-weight:700;display:block;margin:12px 0 4px}
input{width:100%;padding:9px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box}
button{margin-top:18px;width:100%;padding:11px;background:#e08a2c;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer}
.ok{background:#e6f4ea;color:#217a3f;padding:12px;border-radius:8px;font-size:13px}
.err{background:#fde8e8;color:#c0272f;padding:12px;border-radius:8px;font-size:13px}
</style></head><body>
<div class="box">
<h1>🚀 TREADLINE Setup</h1>
<?php if ($done): ?>
  <div class="ok">Database <b><?=htmlspecialchars($name)?></b> created and seeded successfully.<br><br>
  Login with username <b>admin</b> and password <b><?=htmlspecialchars($adminPass)?></b>.<br><br>
  <a href="index.php">Go to Login →</a></div>
<?php else: ?>
  <?php if ($error): ?><div class="err">Error: <?=htmlspecialchars($error)?></div><?php endif; ?>
  <form method="post">
    <label>MySQL Host</label><input name="host" value="<?=htmlspecialchars($host)?>">
    <label>MySQL User</label><input name="user" value="<?=htmlspecialchars($user)?>">
    <label>MySQL Password</label><input type="password" name="pass" value="<?=htmlspecialchars($pass)?>">
    <label>Database Name</label><input name="name" value="<?=htmlspecialchars($name)?>">
    <label>Admin Password (for login)</label><input name="admin_password" value="admin123">
    <button type="submit">Install Treadline</button>
  </form>
<?php endif; ?>
</div>
</body></html>

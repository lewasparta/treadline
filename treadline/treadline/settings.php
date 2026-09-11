<?php
require_once __DIR__ . '/config/config.php';
require_role(['ADMIN']);
$pageTitle = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $k => $v) {
        if ($k === 'csrf') continue;
        $stmt = db()->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
        $stmt->execute([$v, $k]);
    }
    log_action('UPDATE', 'settings', null, 'Settings updated');
    header('Location: settings.php?saved=1'); exit;
}
$settings = db()->query("SELECT * FROM settings ORDER BY id")->fetchAll();
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Settings</h1><div class="sub">Configure thresholds — never hardcode a single PSI or tread value for every tyre.</div></div></div>
  <?php if (!empty($_GET['saved'])): ?><div class="tl-card" style="background:#e6f4ea;color:#217a3f;margin-bottom:16px;">Settings saved successfully.</div><?php endif; ?>
  <form method="post" class="tl-card">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="form-row">
      <?php foreach ($settings as $s): ?>
        <div>
          <label class="form-label"><?= e(str_replace('_',' ',ucfirst($s['setting_key']))) ?></label>
          <input class="form-control" name="<?= e($s['setting_key']) ?>" value="<?= e($s['setting_value']) ?>">
          <div class="text-muted" style="font-size:11px;margin-top:3px;"><?= e($s['description']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-accent" style="margin-top:16px;">Save Settings</button>
  </form>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

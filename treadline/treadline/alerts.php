<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Alerts';

if (!empty($_GET['resolve'])) {
    db()->prepare("UPDATE alerts SET is_resolved=1, is_read=1 WHERE id=?")->execute([(int)$_GET['resolve']]);
    header('Location: alerts.php'); exit;
}
if (!empty($_GET['mark_all_read'])) {
    db()->exec("UPDATE alerts SET is_read=1");
    header('Location: alerts.php'); exit;
}

$where = ['a.is_resolved = 0']; $params = [];
if (!empty($_GET['priority'])) { $where[] = 'a.priority = ?'; $params[] = $_GET['priority']; }
$stmt = db()->prepare("SELECT a.*, v.plate_number FROM alerts a LEFT JOIN vehicles v ON v.id=a.vehicle_id
  WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(a.priority,'CRITICAL','HIGH','MEDIUM','LOW'), a.created_at DESC LIMIT 300");
$stmt->execute($params);
$alerts = $stmt->fetchAll();
db()->exec("UPDATE alerts SET is_read=1 WHERE is_resolved=0");

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Alerts</h1><div class="sub"><?= count($alerts) ?> active alert(s)</div></div>
    <a class="btn btn-outline" href="?mark_all_read=1">Mark all read</a></div>

  <div class="tabs no-print">
    <a href="alerts.php" class="<?= empty($_GET['priority'])?'active':'' ?>">All</a>
    <?php foreach (['CRITICAL','HIGH','MEDIUM','LOW'] as $p): ?>
      <a href="alerts.php?priority=<?= $p ?>" class="<?= ($_GET['priority']??'')===$p?'active':'' ?>"><?= $p ?></a>
    <?php endforeach; ?>
  </div>

  <div class="tl-card">
    <?php foreach ($alerts as $a): ?>
      <div class="alert-item p-<?= e($a['priority']) ?>">
        <div class="dot"></div>
        <div style="flex:1;">
          <div class="msg"><?= $a['priority']==='CRITICAL'?'🔴':($a['priority']==='HIGH'?'🟠':'🟡') ?> <?= e($a['message']) ?></div>
          <div class="meta"><?= e($a['plate_number'] ?? '') ?> · <?= fmt_date($a['created_at'],'d M Y H:i') ?> <?php if($a['action_text']): ?>· <b><?= e($a['action_text']) ?></b><?php endif; ?></div>
        </div>
        <a href="?resolve=<?= $a['id'] ?>" class="btn btn-outline btn-sm">Resolve</a>
      </div>
    <?php endforeach; if (!$alerts): ?><p class="text-muted" style="text-align:center;padding:30px;">No active alerts. Fleet looking good! 🎉</p><?php endif; ?>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

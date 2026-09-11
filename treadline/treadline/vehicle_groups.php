<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Vehicle Groups';
$groups = db()->query("SELECT COALESCE(NULLIF(department,''),'Unassigned') grp, COUNT(*) c FROM vehicles WHERE status<>'ARCHIVED' GROUP BY grp ORDER BY c DESC")->fetchAll();
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Vehicle Groups</h1><div class="sub">Vehicles grouped by department/operating group</div></div></div>
  <div class="form-row" style="grid-template-columns:repeat(4,1fr);">
    <?php foreach ($groups as $g): ?>
      <div class="tl-card">
        <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:700;">Group</div>
        <h3 style="margin:4px 0 10px;"><?= e($g['grp']) ?></h3>
        <div style="font-size:24px;font-weight:800;"><?= (int)$g['c'] ?></div>
        <div class="text-muted" style="font-size:12px;">vehicles</div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

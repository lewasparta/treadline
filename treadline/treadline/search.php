<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Search';
$q = trim($_GET['q'] ?? '');
$vehicles = []; $tyres = [];
if ($q !== '') {
    $like = "%$q%";
    $stmt = db()->prepare("SELECT * FROM vehicles WHERE plate_number LIKE ? OR fleet_number LIKE ? OR vin LIKE ? LIMIT 20");
    $stmt->execute([$like,$like,$like]); $vehicles = $stmt->fetchAll();

    $stmt = db()->prepare("SELECT t.*, v.plate_number FROM tyres t LEFT JOIN vehicles v ON v.id=t.current_vehicle_id
      WHERE t.serial_number LIKE ? OR t.branding_code LIKE ? OR t.brand LIKE ? OR t.model LIKE ? OR t.size LIKE ? LIMIT 20");
    $stmt->execute([$like,$like,$like,$like,$like]); $tyres = $stmt->fetchAll();
}
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Search Results</h1><div class="sub">"<?= e($q) ?>"</div></div></div>

  <div class="tl-card" style="margin-bottom:16px;">
    <div class="tl-card-head"><h3>Vehicles (<?= count($vehicles) ?>)</h3></div>
    <?php foreach ($vehicles as $v): ?>
      <a href="vehicle_profile.php?id=<?= $v['id'] ?>" style="display:block;padding:10px 0;border-bottom:1px solid #eee;">
        <b><?= e($v['plate_number']) ?></b> — <?= e($v['make'].' '.$v['model']) ?> <span class="badge <?= badge_class($v['status']) ?>"><?= $v['status'] ?></span>
      </a>
    <?php endforeach; if($q && !$vehicles): ?><p class="text-muted">No matching vehicles.</p><?php endif; ?>
  </div>

  <div class="tl-card">
    <div class="tl-card-head"><h3>Tyres (<?= count($tyres) ?>)</h3></div>
    <?php foreach ($tyres as $t): ?>
      <div style="display:block;padding:10px 0;border-bottom:1px solid #eee;">
        <b class="mono"><?= e($t['serial_number']) ?></b> — <?= e($t['brand'].' '.$t['size']) ?>
        <?php if($t['plate_number']): ?> on <a href="vehicle_profile.php?id=<?= $t['current_vehicle_id'] ?>"><?= e($t['plate_number']) ?></a> (<?= e($t['current_position']) ?>)<?php endif; ?>
        <span class="badge <?= badge_class($t['status']) ?>"><?= $t['status'] ?></span>
      </div>
    <?php endforeach; if($q && !$tyres): ?><p class="text-muted">No matching tyres.</p><?php endif; ?>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

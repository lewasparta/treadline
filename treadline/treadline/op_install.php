<?php
require_once __DIR__ . '/config/config.php';
require_role(['ADMIN','FLEET_MANAGER','TYRE_INSPECTOR','SUPERVISOR']);
$pageTitle = 'Install Tyre';
$vehicles = db()->query("SELECT id, plate_number FROM vehicles WHERE status='ACTIVE' ORDER BY plate_number")->fetchAll();
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Install Tyre</h1><div class="sub">Select a vehicle, then drag a stock tyre onto an empty position on its tyre map</div></div></div>
  <div class="tl-card" style="max-width:480px;">
    <label class="form-label">Select Vehicle</label>
    <select class="form-control" onchange="if(this.value) location.href='vehicle_profile.php?id='+this.value" style="margin-bottom:10px;">
      <option value="">Choose a vehicle…</option>
      <?php foreach ($vehicles as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['plate_number']) ?></option><?php endforeach; ?>
    </select>
    <p class="text-muted" style="font-size:12.5px;">You'll land on the vehicle's tyre map — drag any tyre from "Available Stock" on the right into an empty slot to fit it.</p>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Reports Center';

$vehicles = db()->query("SELECT id, plate_number FROM vehicles WHERE status<>'ARCHIVED' ORDER BY plate_number")->fetchAll();
$branches = db()->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

$reports = [
  ['Vehicle Tyre Report','Per-vehicle tyre positions, tread, PSI and condition.','vehicle'],
  ['Individual Tyre Report','Full lifecycle of a single tyre by serial number.','tyre'],
  ['Fleet Tyre Report','All tyres across the fleet, exportable.','fleet_tyres'],
  ['Inspection Report','Detailed printable inspection record.','inspection'],
  ['Critical Tyre Report','All tyres at or below the critical threshold.','critical'],
  ['Replacement Report','Tyres flagged for replacement.','replacement'],
  ['PSI Report','Pressure status across the fleet.','psi'],
  ['Tread Wear Report','Tread readings and wear trend by tyre.','wear'],
  ['Tyre Movement Report','Install / rotate / transfer / remove history.','movements'],
  ['Rotation Report','All rotation events.','rotation'],
  ['Repair Report','Repair history and cost.','repairs'],
  ['Retread Report','Retread history and cost.','retreads'],
  ['Inventory Report','Current stock levels.','inventory'],
  ['Supplier Report','Purchases by supplier.','suppliers'],
  ['Cost / KM Report','Cost per kilometre by brand/branch/vehicle.','cost_km'],
  ['Branch Report','Performance summary by branch.','branch'],
  ['Monthly Tyre Report','Monthly replacement & cost summary.','monthly'],
  ['Tyre Performance Report','Brand performance comparison.','performance'],
];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Reports Center</h1><div class="sub">Every report supports Print, PDF (via print) and CSV/Excel export.</div></div></div>

  <div class="form-row" style="grid-template-columns:repeat(3,1fr);">
    <?php foreach ($reports as [$title, $desc, $key]): ?>
      <div class="tl-card">
        <h3 style="margin:0 0 6px;font-size:14.5px;"><?= e($title) ?></h3>
        <p class="text-muted" style="font-size:12.5px;margin:0 0 12px;"><?= e($desc) ?></p>
        <button class="btn btn-outline btn-sm" onclick="location.href='report_view.php?type=<?= $key ?>'">Open Report →</button>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="tl-card" style="margin-top:20px;">
    <h3 style="margin-top:0;">Quick Jump</h3>
    <div class="form-row">
      <div>
        <label class="form-label">Vehicle Tyre Manifest</label>
        <select class="form-control" onchange="if(this.value) location.href='report_manifest.php?vehicle_id='+this.value">
          <option value="">Select vehicle…</option>
          <?php foreach ($vehicles as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['plate_number']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Branch Fleet Manifest</label>
        <select class="form-control" onchange="if(this.value) location.href='report_fleet_manifest.php?branch_id='+this.value">
          <option value="">Select branch…</option>
          <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

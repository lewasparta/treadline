<?php
require_once __DIR__ . '/config/config.php';
require_login();
$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
$stmt = db()->prepare("SELECT v.*, b.name AS branch_name, vt.name AS type_name FROM vehicles v
  LEFT JOIN branches b ON b.id=v.branch_id LEFT JOIN vehicle_types vt ON vt.id=v.vehicle_type_id WHERE v.id=?");
$stmt->execute([$vehicleId]);
$vehicle = $stmt->fetch();
if (!$vehicle) die('Vehicle not found.');

$tt = db()->prepare("SELECT * FROM tyres WHERE current_vehicle_id=? AND status='IN_SERVICE' ORDER BY FIELD(current_position,'FL','FR','RL','RR','RLI','RLO','RRI','RRO','A1L','A1R','A2L','A2R','SP')");
$tt->execute([$vehicleId]);
$tyres = $tt->fetchAll();

$critical = (float) get_setting('tread_critical_mm', 2.0);
$watch = (float) get_setting('tread_watch_mm', 5.0);
$critCount = 0; $watchCount = 0; $goodCount = 0;
foreach ($tyres as $t) {
    $s = tread_status((float)$t['current_tread_mm']);
    if ($s==='CRITICAL') $critCount++; elseif ($s==='WATCH') $watchCount++; else $goodCount++;
}
$manifestNo = 'TM-' . date('Ymd') . '-' . str_pad($vehicleId, 4, '0', STR_PAD_LEFT);
$u = current_user();
$pageTitle = 'Tyre Manifest';
include __DIR__ . '/includes/print_head.php';
?>
<div style="max-width:900px;margin:20px auto;padding:0 16px;">
  <div class="no-print" style="margin-bottom:16px;display:flex;gap:8px;">
    <button class="btn btn-outline" onclick="window.print()">🖨 Print / Save PDF</button>
    <a class="btn btn-outline" href="exports/manifest_csv.php?vehicle_id=<?= $vehicleId ?>">⬇ Excel/CSV</a>
    <button class="btn btn-outline" onclick="location.href='vehicle_profile.php?id=<?= $vehicleId ?>'">← Back</button>
  </div>
  <div class="tl-card">
    <div style="display:flex;justify-content:space-between;border-bottom:3px solid #101a2c;padding-bottom:14px;margin-bottom:16px;">
      <div><h1 style="margin:0;font-size:24px;"># TREADLINE TYRE MANIFEST</h1><div class="text-muted"><?= e(get_setting('company_name')) ?></div></div>
      <div style="text-align:right;font-size:12.5px;">
        Branch: <b><?= e($vehicle['branch_name']) ?></b><br>
        Date: <b><?= date('d/m/Y') ?></b><br>
        Manifest #: <b><?= $manifestNo ?></b><br>
        Prepared by: <b><?= e($u['full_name']) ?></b>
      </div>
    </div>

    <div class="form-row" style="margin-bottom:18px;">
      <div><span class="text-muted" style="font-size:11px;">REGISTRATION</span><br><b style="font-size:16px;"><?= e($vehicle['plate_number']) ?></b></div>
      <div><span class="text-muted" style="font-size:11px;">VEHICLE TYPE</span><br><b><?= e($vehicle['make'].' '.$vehicle['model'].' '.$vehicle['type_name']) ?></b></div>
      <div><span class="text-muted" style="font-size:11px;">CURRENT ODOMETER</span><br><b><?= number_format($vehicle['current_mileage']) ?> KM</b></div>
    </div>

    <table class="tl-table">
      <thead><tr><th>Position</th><th>Serial</th><th>Branding Code</th><th>Brand</th><th>Size</th><th>New Tread</th><th>Current Tread</th><th>PSI</th><th>Condition</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($tyres as $t): $st = tread_status((float)$t['current_tread_mm']); ?>
        <tr>
          <td><b><?= e($t['current_position']) ?></b></td>
          <td class="mono"><?= e($t['serial_number']) ?></td>
          <td class="mono"><?= e($t['branding_code']) ?></td>
          <td><?= e($t['brand']) ?></td>
          <td><?= e($t['size']) ?></td>
          <td><?= number_format($t['new_tread_mm'],1) ?>mm</td>
          <td><b><?= number_format($t['current_tread_mm'],1) ?>mm</b></td>
          <td><?= $t['current_psi'] ?> psi</td>
          <td><?= $st==='GOOD' ? 'Good' : ($st==='WATCH'?'Watch':'Replace') ?></td>
          <td><span class="badge <?= badge_class($st) ?>"><?= $st ?></span></td>
        </tr>
      <?php endforeach; if (!$tyres): ?><tr><td colspan="10" style="text-align:center;padding:20px;">No tyres fitted.</td></tr><?php endif; ?>
      </tbody>
    </table>

    <div class="form-row" style="margin-top:20px;">
      <div><span class="text-muted" style="font-size:11px;">TOTAL TYRES</span><br><b><?= count($tyres) ?></b></div>
      <div><span class="text-muted" style="font-size:11px;">CRITICAL</span><br><b style="color:#dc3545;"><?= $critCount ?></b></div>
      <div><span class="text-muted" style="font-size:11px;">WATCH</span><br><b style="color:#e69138;"><?= $watchCount ?></b></div>
      <div><span class="text-muted" style="font-size:11px;">GOOD</span><br><b style="color:#2f9e5b;"><?= $goodCount ?></b></div>
      <div><span class="text-muted" style="font-size:11px;">REPLACEMENT REQUIRED</span><br><b><?= $critCount ?></b></div>
    </div>

    <div class="form-row" style="margin-top:60px;">
      <div><div style="border-top:1px solid #333;padding-top:6px;">Inspector Signature</div></div>
      <div><div style="border-top:1px solid #333;padding-top:6px;">Supervisor Signature</div></div>
      <div><div style="border-top:1px solid #333;padding-top:6px;">Date</div></div>
    </div>
  </div>
</div>
</body></html>

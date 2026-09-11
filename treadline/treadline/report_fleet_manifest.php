<?php
require_once __DIR__ . '/config/config.php';
require_login();
$branchId = (int)($_GET['branch_id'] ?? 0);
$branch = db()->prepare("SELECT * FROM branches WHERE id=?"); $branch->execute([$branchId]); $branch = $branch->fetch();
if (!$branch) die('Branch not found.');

$critical = (float) get_setting('tread_critical_mm', 2.0);
$watch = (float) get_setting('tread_watch_mm', 5.0);
$interval = (int) get_setting('inspection_interval_days', 30);

$stmt = db()->prepare("SELECT v.*,
  (SELECT COUNT(*) FROM tyres t WHERE t.current_vehicle_id=v.id AND t.status='IN_SERVICE') tyre_count,
  (SELECT COUNT(*) FROM tyres t WHERE t.current_vehicle_id=v.id AND t.status='IN_SERVICE' AND t.current_tread_mm<=$critical) critical_count,
  (SELECT COUNT(*) FROM tyres t WHERE t.current_vehicle_id=v.id AND t.status='IN_SERVICE' AND t.current_tread_mm<$watch AND t.current_tread_mm>$critical) watch_count,
  (SELECT MAX(i.inspection_date) FROM inspections i WHERE i.vehicle_id=v.id) last_inspection
  FROM vehicles v WHERE v.branch_id=? AND v.status<>'ARCHIVED' ORDER BY v.plate_number");
$stmt->execute([$branchId]);
$vehicles = $stmt->fetchAll();

$pageTitle = 'Fleet Manifest';
include __DIR__ . '/includes/print_head.php';
?>
<div style="max-width:1000px;margin:20px auto;padding:0 16px;">
  <div class="no-print" style="margin-bottom:16px;display:flex;gap:8px;">
    <button class="btn btn-outline" onclick="window.print()">🖨 Print / Save PDF</button>
    <button class="btn btn-outline" onclick="location.href='reports.php'">← Reports Center</button>
  </div>
  <div class="tl-card">
    <div style="display:flex;justify-content:space-between;border-bottom:3px solid #101a2c;padding-bottom:14px;margin-bottom:16px;">
      <div><h1 style="margin:0;font-size:22px;">TREADLINE FLEET MANIFEST</h1><div class="text-muted"><?= e($branch['name']) ?> · <?= e($branch['location']) ?></div></div>
      <div style="text-align:right;font-size:12.5px;">Generated: <b><?= date('d/m/Y') ?></b><br>Vehicles: <b><?= count($vehicles) ?></b></div>
    </div>
    <table class="tl-table">
      <thead><tr><th>Registration</th><th>Type</th><th>Mileage</th><th>Tyres</th><th>Critical</th><th>Watch</th><th>Good</th><th>Last Inspection</th><th>Overall Status</th></tr></thead>
      <tbody>
      <?php foreach ($vehicles as $v):
        $good = $v['tyre_count'] - $v['critical_count'] - $v['watch_count'];
        $overall = $v['critical_count'] > 0 ? 'CRITICAL' : ($v['watch_count'] > 0 ? 'WATCH' : 'GOOD');
      ?>
        <tr>
          <td><b><?= e($v['plate_number']) ?></b></td>
          <td><?= e($v['make'].' '.$v['model']) ?></td>
          <td class="mono"><?= number_format($v['current_mileage']) ?> km</td>
          <td><?= (int)$v['tyre_count'] ?></td>
          <td><?= (int)$v['critical_count'] ?></td>
          <td><?= (int)$v['watch_count'] ?></td>
          <td><?= max(0,$good) ?></td>
          <td><?= $v['last_inspection'] ? fmt_date($v['last_inspection']) : 'Never' ?></td>
          <td><span class="badge <?= badge_class($overall) ?>"><?= $overall ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>

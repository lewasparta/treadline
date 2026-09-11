<?php
require_once __DIR__ . '/config/config.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT i.*, v.plate_number, v.make, v.model, b.name AS branch_name, u.full_name AS inspector
  FROM inspections i JOIN vehicles v ON v.id=i.vehicle_id LEFT JOIN branches b ON b.id=v.branch_id JOIN users u ON u.id=i.inspector_id
  WHERE i.id=?");
$stmt->execute([$id]);
$insp = $stmt->fetch();
if (!$insp) die('Inspection not found.');
$items = db()->prepare("SELECT ii.*, t.serial_number, t.branding_code, t.brand, t.size FROM inspection_items ii JOIN tyres t ON t.id=ii.tyre_id WHERE ii.inspection_id=? ORDER BY ii.position");
$items->execute([$id]);
$items = $items->fetchAll();
$pageTitle = 'Inspection Report';
include __DIR__ . '/includes/print_head.php';
?>
<div style="max-width:900px;margin:20px auto;padding:0 16px;">
  <div class="no-print" style="margin-bottom:16px;display:flex;gap:8px;">
    <button class="btn btn-outline" onclick="window.print()">🖨 Print / Save PDF</button>
    <button class="btn btn-outline" onclick="location.href='inspections.php'">← Back</button>
  </div>
  <div class="tl-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #101a2c;padding-bottom:14px;margin-bottom:16px;">
      <div><h1 style="margin:0;font-size:24px;">TREADLINE</h1><div class="text-muted">TYRE INSPECTION REPORT</div></div>
      <div style="text-align:right;font-size:13px;"><b><?= e($insp['plate_number']) ?></b><br><?= e($insp['branch_name']) ?><br><?= fmt_date($insp['inspection_date'],'d M Y H:i') ?></div>
    </div>
    <div class="form-row" style="margin-bottom:20px;">
      <div><span class="text-muted" style="font-size:11px;">VEHICLE</span><br><b><?= e($insp['plate_number'].' — '.$insp['make'].' '.$insp['model']) ?></b></div>
      <div><span class="text-muted" style="font-size:11px;">ODOMETER</span><br><b><?= number_format($insp['odometer']) ?> KM</b></div>
      <div><span class="text-muted" style="font-size:11px;">INSPECTOR</span><br><b><?= e($insp['inspector']) ?></b></div>
      <div><span class="text-muted" style="font-size:11px;">STATUS</span><br><span class="badge <?= badge_class($insp['status']) ?>"><?= $insp['status'] ?></span></div>
    </div>

    <table class="tl-table">
      <thead><tr><th>Pos</th><th>Serial</th><th>Brand</th><th>Inner</th><th>Center</th><th>Outer</th><th>Avg</th><th>PSI</th><th>Sidewall</th><th>Tread Cond.</th><th>Health</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><b><?= e($it['position']) ?></b></td>
          <td class="mono"><?= e($it['serial_number']) ?></td>
          <td><?= e($it['brand'].' '.$it['size']) ?></td>
          <td><?= number_format($it['tread_inner'],1) ?></td>
          <td><?= number_format($it['tread_center'],1) ?></td>
          <td><?= number_format($it['tread_outer'],1) ?></td>
          <td><b><?= number_format($it['tread_avg'],1) ?>mm</b></td>
          <td><?= $it['psi_actual'] ?> <span class="badge <?= badge_class($it['psi_status']) ?>" style="font-size:9px;"><?= $it['psi_status'] ?></span></td>
          <td><?= e($it['sidewall_condition']) ?></td>
          <td><?= e($it['tread_condition']) ?></td>
          <td><span class="badge <?= badge_class($it['health_rating']) ?>"><?= $it['health_score'] ?> · <?= $it['health_rating'] ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($insp['notes']): ?><p style="margin-top:16px;"><b>Notes:</b> <?= e($insp['notes']) ?></p><?php endif; ?>

    <div class="form-row" style="margin-top:50px;">
      <div><div style="border-top:1px solid #333;padding-top:6px;">Inspector Signature</div></div>
      <div><div style="border-top:1px solid #333;padding-top:6px;">Supervisor Signature</div></div>
      <div><div style="border-top:1px solid #333;padding-top:6px;">Date</div></div>
    </div>
  </div>
</div>
</body></html>

<?php
require_once __DIR__ . '/config/config.php';
require_login();
$type = $_GET['type'] ?? 'fleet_tyres';
$critical = (float) get_setting('tread_critical_mm', 2.0);
$watch = (float) get_setting('tread_watch_mm', 5.0);

$titles = [
  'vehicle' => 'Vehicle Tyre Report', 'tyre' => 'Individual Tyre Report', 'fleet_tyres' => 'Fleet Tyre Report',
  'critical' => 'Critical Tyre Report', 'replacement' => 'Replacement Report', 'psi' => 'PSI Report',
  'wear' => 'Tread Wear Report', 'movements' => 'Tyre Movement Report', 'rotation' => 'Rotation Report',
  'repairs' => 'Repair Report', 'retreads' => 'Retread Report', 'inventory' => 'Inventory Report',
  'suppliers' => 'Supplier Report', 'cost_km' => 'Cost / KM Report', 'branch' => 'Branch Report',
  'monthly' => 'Monthly Tyre Report', 'performance' => 'Tyre Performance Report',
];
$pageTitle = $titles[$type] ?? 'Report';
$cols = []; $rows = [];

switch ($type) {
  case 'fleet_tyres':
    $cols = ['Serial','Brand','Size','Vehicle','Position','Tread (mm)','PSI','Status'];
    $stmt = db()->query("SELECT t.serial_number, t.brand, t.size, v.plate_number, t.current_position, t.current_tread_mm, t.current_psi, t.status
      FROM tyres t LEFT JOIN vehicles v ON v.id=t.current_vehicle_id ORDER BY t.status, t.serial_number");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  case 'critical':
    $cols = ['Vehicle','Position','Serial','Brand','Tread (mm)','PSI'];
    $stmt = db()->query("SELECT v.plate_number, t.current_position, t.serial_number, t.brand, t.current_tread_mm, t.current_psi
      FROM tyres t JOIN vehicles v ON v.id=t.current_vehicle_id WHERE t.status='IN_SERVICE' AND t.current_tread_mm <= $critical ORDER BY t.current_tread_mm");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  case 'replacement':
    $cols = ['Vehicle','Position','Serial','Brand','Tread (mm)','Recommendation'];
    $stmt = db()->query("SELECT v.plate_number, t.current_position, t.serial_number, t.brand, t.current_tread_mm,
      CASE WHEN t.current_tread_mm<=$critical THEN 'Replace immediately' ELSE 'Plan replacement soon' END
      FROM tyres t JOIN vehicles v ON v.id=t.current_vehicle_id WHERE t.status='IN_SERVICE' AND t.current_tread_mm < $watch ORDER BY t.current_tread_mm");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  case 'psi':
    $cols = ['Vehicle','Position','Serial','Actual PSI','Recommended PSI','Status'];
    $stmt = db()->query("SELECT v.plate_number, t.current_position, t.serial_number, t.current_psi, t.recommended_psi, t.current_psi
      FROM tyres t JOIN vehicles v ON v.id=t.current_vehicle_id WHERE t.status='IN_SERVICE'");
    foreach ($stmt as $r) {
        $status = psi_status((float)$r['current_psi'], (float)$r['recommended_psi']);
        $rows[] = [$r['plate_number'], $r['current_position'], $r['serial_number'], $r['current_psi'], $r['recommended_psi'], $status];
    }
    break;
  case 'wear':
    $cols = ['Vehicle','Position','Serial','New Tread','Current Tread','Used','% Remaining'];
    $stmt = db()->query("SELECT v.plate_number, t.current_position, t.serial_number, t.new_tread_mm, t.current_tread_mm
      FROM tyres t JOIN vehicles v ON v.id=t.current_vehicle_id WHERE t.status='IN_SERVICE'");
    foreach ($stmt as $r) {
        $used = $r['new_tread_mm'] - $r['current_tread_mm'];
        $pct = $r['new_tread_mm'] > 0 ? round(($r['current_tread_mm']/$r['new_tread_mm'])*100,1) : 0;
        $rows[] = [$r['plate_number'], $r['current_position'], $r['serial_number'], $r['new_tread_mm'].'mm', $r['current_tread_mm'].'mm', round($used,1).'mm', $pct.'%'];
    }
    break;
  case 'movements':
    $cols = ['Date','Type','Tyre Serial','From','To','Odometer','Reason'];
    $stmt = db()->query("SELECT m.movement_date, m.movement_type, t.serial_number,
      CONCAT(COALESCE(fv.plate_number,''),' ',COALESCE(m.from_position,'')) AS from_loc,
      CONCAT(COALESCE(tv.plate_number,''),' ',COALESCE(m.to_position,'')) AS to_loc, m.odometer, m.reason
      FROM tyre_movements m JOIN tyres t ON t.id=m.tyre_id LEFT JOIN vehicles fv ON fv.id=m.from_vehicle_id
      LEFT JOIN vehicles tv ON tv.id=m.to_vehicle_id ORDER BY m.movement_date DESC LIMIT 500");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  case 'rotation':
    $cols = ['Date','Tyre Serial','Vehicle','From Position','To Position','Odometer'];
    $stmt = db()->query("SELECT m.movement_date, t.serial_number, v.plate_number, m.from_position, m.to_position, m.odometer
      FROM tyre_movements m JOIN tyres t ON t.id=m.tyre_id LEFT JOIN vehicles v ON v.id=m.to_vehicle_id
      WHERE m.movement_type='ROTATE' ORDER BY m.movement_date DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  case 'repairs':
    $cols = ['Date','Vehicle','Tyre Serial','Damage','Technician','Cost'];
    $stmt = db()->query("SELECT r.repair_date, v.plate_number, t.serial_number, r.damage, r.technician, r.cost
      FROM tyre_repairs r JOIN tyres t ON t.id=r.tyre_id LEFT JOIN vehicles v ON v.id=r.vehicle_id ORDER BY r.repair_date DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  case 'retreads':
    $cols = ['Date','Tyre Serial','Retread No.','Supplier','Cost','Tread After'];
    $stmt = db()->query("SELECT rt.retread_date, t.serial_number, rt.retread_number, s.company, rt.cost, rt.tread_after_retread
      FROM tyre_retreads rt JOIN tyres t ON t.id=rt.tyre_id LEFT JOIN suppliers s ON s.id=rt.supplier_id ORDER BY rt.retread_date DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  case 'inventory':
    $cols = ['Status','Count'];
    $stmt = db()->query("SELECT status, COUNT(*) FROM tyres GROUP BY status");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  case 'suppliers':
    $cols = ['Supplier','Contact','Phone','Tyres Supplied','Total Spend'];
    $stmt = db()->query("SELECT s.company, s.contact_person, s.phone, COUNT(t.id), COALESCE(SUM(t.purchase_price),0)
      FROM suppliers s LEFT JOIN tyres t ON t.supplier_id=s.id GROUP BY s.id");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  case 'cost_km':
    $cols = ['Vehicle','Brand','Tyre Cost','Mileage (km)','Cost/KM'];
    $stmt = db()->query("SELECT v.plate_number, t.brand, t.purchase_price, GREATEST(v.current_mileage - t.install_odometer,1) AS km
      FROM tyres t JOIN vehicles v ON v.id=t.current_vehicle_id WHERE t.status='IN_SERVICE' AND t.install_odometer IS NOT NULL");
    foreach ($stmt as $r) $rows[] = [$r['plate_number'], $r['brand'], fmt_money($r['purchase_price']), number_format($r['km']), fmt_money($r['purchase_price']/$r['km'])];
    break;
  case 'branch':
    $cols = ['Branch','Vehicles','Tyres In Service','Critical','Watch'];
    $stmt = db()->query("SELECT b.name, COUNT(DISTINCT v.id),
      SUM(CASE WHEN t.status='IN_SERVICE' THEN 1 ELSE 0 END),
      SUM(CASE WHEN t.status='IN_SERVICE' AND t.current_tread_mm<=$critical THEN 1 ELSE 0 END),
      SUM(CASE WHEN t.status='IN_SERVICE' AND t.current_tread_mm<$watch AND t.current_tread_mm>$critical THEN 1 ELSE 0 END)
      FROM branches b LEFT JOIN vehicles v ON v.branch_id=b.id LEFT JOIN tyres t ON t.current_vehicle_id=v.id
      GROUP BY b.id");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  case 'monthly':
    $cols = ['Month','Tyres Removed','Total Repair Cost','Total Retread Cost'];
    $stmt = db()->query("SELECT DATE_FORMAT(removal_date,'%b %Y') m, COUNT(*) FROM tyre_removals GROUP BY m");
    $removals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $repairMonthly = db()->query("SELECT DATE_FORMAT(repair_date,'%b %Y') m, SUM(cost) FROM tyre_repairs GROUP BY m")->fetchAll(PDO::FETCH_KEY_PAIR);
    $retreadMonthly = db()->query("SELECT DATE_FORMAT(retread_date,'%b %Y') m, SUM(cost) FROM tyre_retreads GROUP BY m")->fetchAll(PDO::FETCH_KEY_PAIR);
    $months = array_unique(array_merge(array_keys($removals), array_keys($repairMonthly), array_keys($retreadMonthly)));
    foreach ($months as $m) $rows[] = [$m, $removals[$m] ?? 0, fmt_money($repairMonthly[$m] ?? 0), fmt_money($retreadMonthly[$m] ?? 0)];
    break;
  case 'performance':
    $cols = ['Brand','Tyres','Avg Current Tread','Avg Repairs'];
    $stmt = db()->query("SELECT brand, COUNT(*), ROUND(AVG(current_tread_mm),1), ROUND(AVG(total_repairs),2) FROM tyres WHERE status='IN_SERVICE' GROUP BY brand ORDER BY 3 DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    break;
  default:
    $cols = ['Info']; $rows = [['Select a vehicle or tyre from the relevant page to view this report.']];
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head">
    <div><h1><?= e($pageTitle) ?></h1><div class="sub">Generated <?= date('d M Y H:i') ?></div></div>
    <div class="flex gap-2 no-print">
      <button class="btn btn-outline" onclick="window.print()">🖨 Print / PDF</button>
      <button class="btn btn-outline" onclick="exportCsv()">⬇ CSV</button>
      <button class="btn btn-outline" onclick="location.href='reports.php'">← Reports Center</button>
    </div>
  </div>
  <div class="tl-card">
    <table class="tl-table" id="reportTable">
      <thead><tr><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?><tr><?php foreach ($r as $c): ?><td><?= e((string)$c) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="<?= count($cols) ?>" class="text-muted" style="text-align:center;padding:30px;">No data available for this report.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php $extraScript = "<script>
function exportCsv(){
  const rows = [...document.querySelectorAll('#reportTable tr')].map(tr => [...tr.children].map(td => '\"'+td.textContent.replace(/\"/g,'\"\"')+'\"').join(','));
  const blob = new Blob([rows.join('\\n')], {type:'text/csv'});
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = '" . $type . "_report.csv'; a.click();
}
</script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

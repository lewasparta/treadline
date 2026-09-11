<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Dashboard';
$u = current_user();

$critical = (float) get_setting('tread_critical_mm', 2.0);
$watch = (float) get_setting('tread_watch_mm', 5.0);
$interval = (int) get_setting('inspection_interval_days', 30);

$totalVehicles = db()->query("SELECT COUNT(*) c FROM vehicles WHERE status <> 'ARCHIVED'")->fetch()['c'];
$tyresInService = db()->query("SELECT COUNT(*) c FROM tyres WHERE status='IN_SERVICE'")->fetch()['c'];
$criticalTyres = db()->query("SELECT COUNT(*) c FROM tyres WHERE status='IN_SERVICE' AND current_tread_mm <= $critical")->fetch()['c'];
$belowWatch = db()->query("SELECT COUNT(*) c FROM tyres WHERE status='IN_SERVICE' AND current_tread_mm < $watch")->fetch()['c'];
$inStock = db()->query("SELECT COUNT(*) c FROM tyres WHERE status='STOCK'")->fetch()['c'];
$underRepair = db()->query("SELECT COUNT(*) c FROM tyres WHERE status='UNDER_REPAIR'")->fetch()['c'];
$needInspection = db()->query("
  SELECT COUNT(*) c FROM vehicles v WHERE v.status='ACTIVE' AND
  (SELECT MAX(i.inspection_date) FROM inspections i WHERE i.vehicle_id=v.id) IS NULL
  OR (SELECT MAX(i.inspection_date) FROM inspections i WHERE i.vehicle_id=v.id) < DATE_SUB(NOW(), INTERVAL $interval DAY)
")->fetch()['c'];
$avgMileage = db()->query("SELECT ROUND(AVG(current_mileage)) a FROM vehicles WHERE status<>'ARCHIVED'")->fetch()['a'] ?? 0;

$costRow = db()->query("
  SELECT COALESCE(SUM(t.purchase_price),0) AS purchase,
         COALESCE((SELECT SUM(cost) FROM tyre_repairs),0) AS repairs,
         COALESCE((SELECT SUM(cost) FROM tyre_retreads),0) AS retreads
  FROM tyres t
")->fetch();
$totalTyreCost = $costRow['purchase'] + $costRow['repairs'] + $costRow['retreads'];

$mileageSumRow = db()->query("
  SELECT COALESCE(SUM(GREATEST(v.current_mileage - t.install_odometer,0)),0) AS total_km
  FROM tyres t JOIN vehicles v ON v.id = t.current_vehicle_id
  WHERE t.status='IN_SERVICE' AND t.install_odometer IS NOT NULL
")->fetch();
$costPerKm = $mileageSumRow['total_km'] > 0 ? $totalTyreCost / $mileageSumRow['total_km'] : 0;

// Charts data
$byCondition = db()->query("
  SELECT
    SUM(CASE WHEN current_tread_mm <= $critical THEN 1 ELSE 0 END) AS critical,
    SUM(CASE WHEN current_tread_mm > $critical AND current_tread_mm < $watch THEN 1 ELSE 0 END) AS watch,
    SUM(CASE WHEN current_tread_mm >= $watch THEN 1 ELSE 0 END) AS good
  FROM tyres WHERE status='IN_SERVICE'
")->fetch();

$byBranch = db()->query("
  SELECT b.name, COUNT(v.id) vc FROM branches b LEFT JOIN vehicles v ON v.branch_id=b.id AND v.status<>'ARCHIVED'
  GROUP BY b.id ORDER BY vc DESC LIMIT 8
")->fetchAll();

$byBrand = db()->query("SELECT brand, COUNT(*) c FROM tyres WHERE status='IN_SERVICE' GROUP BY brand ORDER BY c DESC LIMIT 6")->fetchAll();

$recentAlerts = db()->query("SELECT a.*, v.plate_number FROM alerts a LEFT JOIN vehicles v ON v.id=a.vehicle_id ORDER BY a.created_at DESC LIMIT 6")->fetchAll();

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head">
    <div><h1>Fleet Overview</h1><div class="sub">Welcome back, <?= e($u['full_name']) ?> — here's what's happening across your fleet today.</div></div>
    <div class="flex gap-2">
      <button class="btn btn-outline" onclick="location.href='reports.php'">📄 Reports</button>
      <button class="btn btn-accent" onclick="location.href='inspection_new.php'">+ New Inspection</button>
    </div>
  </div>

  <div class="stat-grid">
    <div class="stat-card accent-navy"><div class="icon">🚚</div><div class="value"><?= (int)$totalVehicles ?></div><div class="label">Total Vehicles</div></div>
    <div class="stat-card accent-green"><div class="icon">🛞</div><div class="value"><?= (int)$tyresInService ?></div><div class="label">Tyres In Service</div></div>
    <div class="stat-card accent-red"><div class="icon">⚠</div><div class="value"><?= (int)$criticalTyres ?></div><div class="label">Critical Tyres</div></div>
    <div class="stat-card accent-amber"><div class="icon">🔎</div><div class="value"><?= (int)$belowWatch ?></div><div class="label">Tyres Below <?= $watch ?>mm</div></div>
    <div class="stat-card accent-blue"><div class="icon">🗓</div><div class="value"><?= (int)$needInspection ?></div><div class="label">Require Inspection</div></div>
    <div class="stat-card accent-navy"><div class="icon">📦</div><div class="value"><?= (int)$inStock ?></div><div class="label">Tyres In Stock</div></div>
    <div class="stat-card accent-amber"><div class="icon">🔧</div><div class="value"><?= (int)$underRepair ?></div><div class="label">Under Repair</div></div>
    <div class="stat-card accent-blue"><div class="icon">📍</div><div class="value"><?= number_format($avgMileage) ?></div><div class="label">Avg Vehicle Mileage (KM)</div></div>
    <div class="stat-card accent-green"><div class="icon">💰</div><div class="value" style="font-size:19px;"><?= fmt_money($totalTyreCost) ?></div><div class="label">Total Tyre Cost</div></div>
    <div class="stat-card accent-navy"><div class="icon">📉</div><div class="value" style="font-size:19px;"><?= fmt_money($costPerKm) ?></div><div class="label">Avg Cost / KM</div></div>
  </div>

  <div class="form-row" style="grid-template-columns:2fr 1fr; align-items:start;">
    <div class="tl-card" style="margin-bottom:18px;">
      <div class="tl-card-head"><h3>Tyres by Branch</h3></div>
      <canvas id="chartBranch" height="220"></canvas>
    </div>
    <div class="tl-card" style="margin-bottom:18px;">
      <div class="tl-card-head"><h3>Tyre Condition</h3></div>
      <canvas id="chartCondition" height="220"></canvas>
    </div>
  </div>

  <div class="form-row" style="grid-template-columns:1fr 1fr; align-items:start;">
    <div class="tl-card">
      <div class="tl-card-head"><h3>Tyres by Brand</h3></div>
      <canvas id="chartBrand" height="200"></canvas>
    </div>
    <div class="tl-card">
      <div class="tl-card-head"><h3>Recent Alerts</h3><a href="alerts.php" style="font-size:12px;">View all →</a></div>
      <?php if (!$recentAlerts): ?>
        <p class="text-muted" style="font-size:13px;">No alerts right now. 🎉</p>
      <?php endif; ?>
      <?php foreach ($recentAlerts as $a): ?>
        <div class="alert-item p-<?= e($a['priority']) ?>">
          <div class="dot"></div>
          <div>
            <div class="msg"><?= e($a['message']) ?></div>
            <div class="meta"><?= e($a['plate_number'] ?? '') ?> · <?= fmt_date($a['created_at'],'d M Y H:i') ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</div>

<script>
new Chart(document.getElementById('chartBranch'), {
  type: 'bar',
  data: { labels: <?= json_encode(array_column($byBranch,'name')) ?>,
    datasets: [{ label: 'Vehicles', data: <?= json_encode(array_map('intval',array_column($byBranch,'vc'))) ?>, backgroundColor: '#e08a2c', borderRadius: 6 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
new Chart(document.getElementById('chartCondition'), {
  type: 'doughnut',
  data: { labels: ['Critical','Watch','Good'],
    datasets: [{ data: [<?= (int)$byCondition['critical'] ?>, <?= (int)$byCondition['watch'] ?>, <?= (int)$byCondition['good'] ?>],
    backgroundColor: ['#dc3545','#e69138','#2f9e5b'] }] },
  options: { plugins: { legend: { position: 'bottom' } } }
});
new Chart(document.getElementById('chartBrand'), {
  type: 'bar',
  data: { labels: <?= json_encode(array_column($byBrand,'brand')) ?>,
    datasets: [{ label: 'Tyres', data: <?= json_encode(array_map('intval',array_column($byBrand,'c'))) ?>, backgroundColor: '#101a2c', borderRadius: 6 }] },
  options: { indexAxis: 'y', plugins: { legend: { display: false } } }
});
</script>
<?php include __DIR__ . '/includes/foot.php'; ?>

<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Analytics';

$critical = (float) get_setting('tread_critical_mm', 2.0);
$watch = (float) get_setting('tread_watch_mm', 5.0);

$wearTrend = db()->query("SELECT DATE_FORMAT(i.inspection_date,'%b %Y') m, ROUND(AVG(ii.tread_avg),2) avg_tread
  FROM inspection_items ii JOIN inspections i ON i.id=ii.inspection_id GROUP BY m ORDER BY MIN(i.inspection_date)")->fetchAll();

$replacementsByMonth = db()->query("SELECT DATE_FORMAT(removal_date,'%b %Y') m, COUNT(*) c FROM tyre_removals GROUP BY m ORDER BY MIN(removal_date)")->fetchAll();

$costByBranch = db()->query("SELECT b.name, COALESCE(SUM(t.purchase_price),0) cost FROM branches b
  LEFT JOIN vehicles v ON v.branch_id=b.id LEFT JOIN tyres t ON t.current_vehicle_id=v.id
  GROUP BY b.id ORDER BY cost DESC LIMIT 8")->fetchAll();

$topBrands = db()->query("SELECT brand, ROUND(AVG(current_tread_mm),2) avg_tread, COUNT(*) c FROM tyres WHERE status='IN_SERVICE' GROUP BY brand HAVING c>0 ORDER BY avg_tread DESC")->fetchAll();

$failureReasons = db()->query("SELECT reason, COUNT(*) c FROM tyre_removals GROUP BY reason")->fetchAll();

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Analytics</h1><div class="sub">Fleet-wide tyre performance trends</div></div></div>

  <div class="form-row" style="grid-template-columns:1fr 1fr;">
    <div class="tl-card"><div class="tl-card-head"><h3>Average Tread Wear Trend</h3></div><canvas id="chartWear" height="220"></canvas></div>
    <div class="tl-card"><div class="tl-card-head"><h3>Monthly Replacements</h3></div><canvas id="chartRepl" height="220"></canvas></div>
  </div>
  <div class="form-row" style="grid-template-columns:1fr 1fr;margin-top:18px;">
    <div class="tl-card"><div class="tl-card-head"><h3>Tyre Cost by Branch</h3></div><canvas id="chartCostBranch" height="220"></canvas></div>
    <div class="tl-card"><div class="tl-card-head"><h3>Removal Reasons</h3></div><canvas id="chartFailure" height="220"></canvas></div>
  </div>
  <div class="tl-card" style="margin-top:18px;">
    <div class="tl-card-head"><h3>Brand Performance (avg current tread, IN_SERVICE)</h3></div>
    <table class="tl-table"><thead><tr><th>Brand</th><th>Tyres In Service</th><th>Avg Tread Remaining</th><th>Rating</th></tr></thead>
    <tbody><?php foreach ($topBrands as $b): $s = tread_status($b['avg_tread']); ?>
      <tr><td><?= e($b['brand']) ?></td><td><?= $b['c'] ?></td><td><?= $b['avg_tread'] ?>mm</td><td><span class="badge <?= badge_class($s) ?>"><?= $s ?></span></td></tr>
    <?php endforeach; ?></tbody></table>
  </div>
</div>
</div>
<script>
new Chart(document.getElementById('chartWear'), { type:'line',
  data:{ labels: <?= json_encode(array_column($wearTrend,'m')) ?>, datasets:[{label:'Avg Tread (mm)', data:<?= json_encode(array_map('floatval',array_column($wearTrend,'avg_tread'))) ?>, borderColor:'#e08a2c', backgroundColor:'rgba(224,138,44,.15)', fill:true, tension:.3}]},
  options:{ plugins:{legend:{display:false}} }});
new Chart(document.getElementById('chartRepl'), { type:'bar',
  data:{ labels: <?= json_encode(array_column($replacementsByMonth,'m')) ?>, datasets:[{label:'Replacements', data:<?= json_encode(array_map('intval',array_column($replacementsByMonth,'c'))) ?>, backgroundColor:'#101a2c', borderRadius:6}]},
  options:{ plugins:{legend:{display:false}} }});
new Chart(document.getElementById('chartCostBranch'), { type:'bar',
  data:{ labels: <?= json_encode(array_column($costByBranch,'name')) ?>, datasets:[{label:'Cost', data:<?= json_encode(array_map('floatval',array_column($costByBranch,'cost'))) ?>, backgroundColor:'#2f6fed', borderRadius:6}]},
  options:{ indexAxis:'y', plugins:{legend:{display:false}} }});
new Chart(document.getElementById('chartFailure'), { type:'pie',
  data:{ labels: <?= json_encode(array_column($failureReasons,'reason')) ?>, datasets:[{data:<?= json_encode(array_map('intval',array_column($failureReasons,'c'))) ?>, backgroundColor:['#dc3545','#e69138','#2f9e5b','#2f6fed','#8a94a6','#a85c00','#c0272f','#217a3f','#1a53c9']}]},
  options:{ plugins:{legend:{position:'bottom'}} }});
</script>
<?php include __DIR__ . '/includes/foot.php'; ?>

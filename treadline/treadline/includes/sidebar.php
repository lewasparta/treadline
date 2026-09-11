<?php
$__u = current_user();
$__page = basename($_SERVER['SCRIPT_NAME']);
$branches = db()->query("SELECT b.*, 
  (SELECT COUNT(*) FROM vehicles v WHERE v.branch_id=b.id) AS vcount
  FROM branches b WHERE b.status='ACTIVE' ORDER BY b.name")->fetchAll();
function nav_active($files){ global $__page; return in_array($__page, (array)$files) ? 'active' : ''; }
?>
<aside class="tl-sidebar" id="tlSidebar">
  <div class="tl-brand">
    <div class="tl-brand-mark">T</div>
    <div class="tl-brand-text">
      <div class="name">TREADLINE</div>
      <div class="tag">SMART FLEET TYRE TRACKING</div>
    </div>
  </div>
  <nav class="tl-nav">
    <a class="tl-link <?= nav_active('dashboard.php') ?>" href="dashboard.php"><span class="ic">&#9635;</span> Dashboard</a>

    <div class="tl-nav-section">Fleet</div>
    <a class="tl-link <?= nav_active('vehicles.php') ?>" href="vehicles.php"><span class="ic">&#128663;</span> Vehicles</a>
    <a class="tl-link <?= nav_active('import_vehicles.php') ?>" href="import_vehicles.php">&nbsp;&nbsp;⬆ Import Vehicles (CSV)</a>
    <a class="tl-link <?= nav_active('vehicle_groups.php') ?>" href="vehicle_groups.php"><span class="ic">&#128193;</span> Vehicle Groups</a>
    <a class="tl-link <?= nav_active('vehicle_types.php') ?>" href="vehicle_types.php"><span class="ic">&#9881;</span> Vehicle Types</a>

    <div class="tl-nav-section">Tyres</div>
    <a class="tl-link <?= nav_active('tyres.php') ?>" href="tyres.php"><span class="ic">&#9899;</span> All Tyres</a>
    <a class="tl-link <?= nav_active(['tyres_service.php']) ?>" href="tyres.php?status=IN_SERVICE">In Service</a>
    <a class="tl-link <?= nav_active(['stock.php']) ?>" href="stock.php">Tyres in Stock</a>
    <a class="tl-link <?= nav_active(['tyres_critical.php']) ?>" href="tyres.php?filter=critical">Critical Tyres</a>
    <a class="tl-link" href="tyres.php?filter=replacement">For Replacement</a>
    <a class="tl-link" href="tyres.php?status=UNDER_REPAIR">Under Repair</a>
    <a class="tl-link" href="tyres.php?type=RETREAD">Retread Tyres</a>

    <div class="tl-nav-section">Inspections</div>
    <a class="tl-link <?= nav_active('inspection_new.php') ?>" href="inspection_new.php"><span class="ic">&#128203;</span> New Inspection</a>
    <a class="tl-link <?= nav_active('inspections.php') ?>" href="inspections.php">Inspection History</a>
    <a class="tl-link" href="inspections.php?status=PENDING">Pending Approval</a>
    <a class="tl-link" href="inspections.php?status=FAILED">Failed Inspections</a>

    <div class="tl-nav-section">Tyre Operations</div>
    <a class="tl-link" href="op_install.php"><span class="ic">&#128295;</span> Install Tyre</a>
    <a class="tl-link" href="op_rotate.php">Rotate Tyres</a>
    <a class="tl-link" href="op_transfer.php">Transfer Tyre</a>
    <a class="tl-link" href="op_repair.php">Repair</a>
    <a class="tl-link" href="op_retread.php">Retread</a>
    <a class="tl-link" href="op_remove.php">Remove Tyre</a>

    <div class="tl-nav-section">Inventory</div>
    <a class="tl-link <?= nav_active('stock.php') ?>" href="stock.php"><span class="ic">&#128230;</span> Tyre Stock</a>
    <a class="tl-link <?= nav_active('import_tyres.php') ?>" href="import_tyres.php">&nbsp;&nbsp;⬆ Import Tyres (CSV)</a>
    <a class="tl-link <?= nav_active('suppliers.php') ?>" href="suppliers.php">Suppliers</a>
    <a class="tl-link" href="purchases.php">Purchases</a>
    <a class="tl-link" href="stock_movements.php">Stock Movements</a>

    <div class="tl-nav-section">Reports</div>
    <a class="tl-link <?= nav_active('reports.php') ?>" href="reports.php"><span class="ic">&#128202;</span> Reports Center</a>

    <div class="tl-nav-section">System</div>
    <a class="tl-link <?= nav_active('analytics.php') ?>" href="analytics.php"><span class="ic">&#128200;</span> Analytics</a>
    <a class="tl-link <?= nav_active('alerts.php') ?>" href="alerts.php"><span class="ic">&#128276;</span> Alerts</a>
    <?php if (($__u['role_name'] ?? '') === 'ADMIN'): ?>
    <a class="tl-link <?= nav_active('users.php') ?>" href="users.php"><span class="ic">&#128100;</span> Users</a>
    <a class="tl-link <?= nav_active('settings.php') ?>" href="settings.php"><span class="ic">&#9881;</span> Settings</a>
    <?php endif; ?>
  </nav>

  <div class="tl-nav-section" style="margin-top:0;padding-left:20px;">Branches / Groups</div>
  <div class="tl-branch-list">
    <a href="vehicles.php" class="<?= empty($_GET['branch']) ? 'active' : '' ?>">All Branches <span class="count"><?= array_sum(array_column($branches,'vcount')) ?></span></a>
    <?php foreach ($branches as $b): ?>
      <a href="vehicles.php?branch=<?= (int)$b['id'] ?>" class="<?= (isset($_GET['branch']) && (int)$_GET['branch']===(int)$b['id']) ? 'active':'' ?>">
        <?= e($b['name']) ?> <span class="count"><?= (int)$b['vcount'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</aside>

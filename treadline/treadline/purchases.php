<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Purchases';
$purchases = db()->query("SELECT t.serial_number, t.brand, t.size, t.purchase_date, t.purchase_price, s.company
  FROM tyres t LEFT JOIN suppliers s ON s.id=t.supplier_id WHERE t.purchase_price > 0 ORDER BY t.purchase_date DESC LIMIT 300")->fetchAll();
$total = array_sum(array_column($purchases,'purchase_price'));
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Purchases</h1><div class="sub">Total spend: <?= fmt_money($total) ?></div></div></div>
  <div class="tl-card">
    <table class="tl-table">
      <thead><tr><th>Date</th><th>Serial</th><th>Brand/Size</th><th>Supplier</th><th>Price</th></tr></thead>
      <tbody>
      <?php foreach ($purchases as $p): ?>
        <tr><td><?= fmt_date($p['purchase_date']) ?></td><td class="mono"><?= e($p['serial_number']) ?></td>
        <td><?= e($p['brand'].' '.$p['size']) ?></td><td><?= e($p['company'] ?? '—') ?></td><td><?= fmt_money($p['purchase_price']) ?></td></tr>
      <?php endforeach; if (!$purchases): ?><tr><td colspan="5" class="text-muted" style="text-align:center;padding:30px;">No purchase records yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

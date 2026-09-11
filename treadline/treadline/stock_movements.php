<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Stock Movements';
$moves = db()->query("SELECT m.*, t.serial_number, t.brand, fv.plate_number AS from_plate, tv.plate_number AS to_plate
  FROM tyre_movements m JOIN tyres t ON t.id=m.tyre_id
  LEFT JOIN vehicles fv ON fv.id=m.from_vehicle_id LEFT JOIN vehicles tv ON tv.id=m.to_vehicle_id
  ORDER BY m.created_at DESC LIMIT 300")->fetchAll();
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Stock Movements</h1><div class="sub">Complete audit trail of every tyre movement</div></div>
  <a class="btn btn-outline" href="report_view.php?type=movements">Full Report View →</a></div>
  <div class="tl-card">
    <table class="tl-table">
      <thead><tr><th>Date</th><th>Type</th><th>Tyre</th><th>From</th><th>To</th><th>Odometer</th><th>Reason</th><th>By</th></tr></thead>
      <tbody>
      <?php foreach ($moves as $m): ?>
        <tr>
          <td><?= fmt_date($m['movement_date']) ?></td>
          <td><span class="badge badge-info"><?= $m['movement_type'] ?></span></td>
          <td class="mono"><?= e($m['brand'].' · '.$m['serial_number']) ?></td>
          <td><?= e(trim(($m['from_plate']??'').' '.($m['from_position']??''))) ?: '—' ?></td>
          <td><?= e(trim(($m['to_plate']??'').' '.($m['to_position']??''))) ?: '—' ?></td>
          <td class="mono"><?= $m['odometer']?number_format($m['odometer']):'—' ?></td>
          <td><?= e($m['reason']) ?></td>
          <td><?php $uu=db()->prepare("SELECT full_name FROM users WHERE id=?"); $uu->execute([$m['performed_by']]); echo e($uu->fetch()['full_name'] ?? '—'); ?></td>
        </tr>
      <?php endforeach; if (!$moves): ?><tr><td colspan="8" class="text-muted" style="text-align:center;padding:30px;">No movements recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

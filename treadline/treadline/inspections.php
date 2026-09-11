<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Inspections';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    require_role(['ADMIN','SUPERVISOR']);
    $id = (int)$_POST['inspection_id'];
    $status = $_POST['action'] === 'approve' ? 'APPROVED' : 'FAILED';
    db()->prepare("UPDATE inspections SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")->execute([$status, current_user()['id'], $id]);
    log_action('REVIEW', 'inspection', $id, $status);
    header('Location: inspections.php'); exit;
}

$where = ['1=1']; $params = [];
if (!empty($_GET['status'])) { $where[] = 'i.status = ?'; $params[] = $_GET['status']; }
$stmt = db()->prepare("SELECT i.*, v.plate_number, u.full_name AS inspector FROM inspections i
  JOIN vehicles v ON v.id=i.vehicle_id JOIN users u ON u.id=i.inspector_id
  WHERE " . implode(' AND ', $where) . " ORDER BY i.inspection_date DESC LIMIT 300");
$stmt->execute($params);
$rows = $stmt->fetchAll();

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
$u = current_user();
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Inspection History</h1><div class="sub"><?= count($rows) ?> inspection(s)</div></div>
    <button class="btn btn-accent" onclick="location.href='inspection_new.php'">+ New Inspection</button></div>

  <div class="tabs no-print">
    <a href="inspections.php" class="<?= empty($_GET['status'])?'active':'' ?>">All</a>
    <a href="inspections.php?status=PENDING" class="<?= ($_GET['status']??'')==='PENDING'?'active':'' ?>">Pending Approval</a>
    <a href="inspections.php?status=FAILED" class="<?= ($_GET['status']??'')==='FAILED'?'active':'' ?>">Failed</a>
    <a href="inspections.php?status=APPROVED" class="<?= ($_GET['status']??'')==='APPROVED'?'active':'' ?>">Approved</a>
  </div>

  <div class="tl-card">
    <table class="tl-table">
      <thead><tr><th>Date</th><th>Vehicle</th><th>Odometer</th><th>Inspector</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= fmt_date($r['inspection_date'],'d M Y H:i') ?></td>
          <td><a href="vehicle_profile.php?id=<?= $r['vehicle_id'] ?>"><?= e($r['plate_number']) ?></a></td>
          <td class="mono"><?= number_format($r['odometer']) ?> km</td>
          <td><?= e($r['inspector']) ?></td>
          <td><span class="badge <?= badge_class($r['status']) ?>"><?= $r['status'] ?></span></td>
          <td class="flex gap-2">
            <a class="btn btn-outline btn-sm" href="report_inspection.php?id=<?= $r['id'] ?>">View</a>
            <?php if ($r['status']==='PENDING' && in_array($u['role_name'],['ADMIN','SUPERVISOR'])): ?>
              <form method="post" style="display:inline;"><input type="hidden" name="inspection_id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-sm" style="background:#e6f4ea;color:#217a3f;">Approve</button></form>
              <form method="post" style="display:inline;"><input type="hidden" name="inspection_id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="fail"><button class="btn btn-sm btn-danger">Fail</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-muted" style="text-align:center;padding:30px;">No inspections yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

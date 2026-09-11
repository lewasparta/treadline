<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Tyres';

$critical = (float) get_setting('tread_critical_mm', 2.0);
$watch = (float) get_setting('tread_watch_mm', 5.0);

$where = ['1=1']; $params = [];
if (!empty($_GET['status'])) { $where[] = 't.status = ?'; $params[] = $_GET['status']; }
if (!empty($_GET['type'])) { $where[] = 't.type = ?'; $params[] = $_GET['type']; }
if (($_GET['filter'] ?? '') === 'critical') { $where[] = "t.status='IN_SERVICE' AND t.current_tread_mm <= $critical"; }
if (($_GET['filter'] ?? '') === 'replacement') { $where[] = "t.status='IN_SERVICE' AND t.current_tread_mm < $watch"; }
if (!empty($_GET['q'])) { $where[] = '(t.serial_number LIKE ? OR t.branding_code LIKE ? OR t.brand LIKE ?)'; $params[]="%{$_GET['q']}%"; $params[]="%{$_GET['q']}%"; $params[]="%{$_GET['q']}%"; }
$whereSql = implode(' AND ', $where);

$stmt = db()->prepare("SELECT t.*, v.plate_number FROM tyres t LEFT JOIN vehicles v ON v.id=t.current_vehicle_id
  WHERE $whereSql ORDER BY t.updated_at DESC LIMIT 400");
$stmt->execute($params);
$tyres = $stmt->fetchAll();

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head">
    <div><h1>All Tyres</h1><div class="sub"><?= count($tyres) ?> tyre record(s)</div></div>
    <div class="flex gap-2 no-print">
      <button class="btn btn-outline" onclick="window.print()">🖨 Print</button>
      <a class="btn btn-outline" href="exports/tyres_csv.php?<?= e($_SERVER['QUERY_STRING']) ?>">⬇ CSV</a>
      <?php if (can_edit()): ?><button class="btn btn-accent" onclick="location.href='stock.php'">+ Add Tyre</button><?php endif; ?>
    </div>
  </div>

  <form class="tl-card no-print" style="margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
    <div style="flex:1;min-width:200px;"><label class="form-label">Search serial / branding / brand</label><input class="form-control" name="q" value="<?= e($_GET['q'] ?? '') ?>"></div>
    <div style="min-width:160px;"><label class="form-label">Status</label>
      <select name="status" class="form-control">
        <option value="">All statuses</option>
        <?php foreach (['STOCK','IN_SERVICE','UNDER_REPAIR','RETREAD','REMOVED','SCRAP'] as $s): ?>
          <option value="<?= $s ?>" <?= ($_GET['status']??'')===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-outline">Filter</button>
  </form>

  <div class="tl-card">
    <table class="tl-table">
      <thead><tr><th>Serial</th><th>Brand/Size</th><th>Vehicle</th><th>Position</th><th>Tread</th><th>PSI</th><th>Status</th><th>Mileage</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tyres as $t): $ts = $t['current_tread_mm'] !== null ? tread_status((float)$t['current_tread_mm']) : null; ?>
        <tr>
          <td class="mono"><?= e($t['serial_number']) ?><br><span class="text-muted" style="font-size:11px;"><?= e($t['branding_code']) ?></span></td>
          <td><?= e($t['brand']) ?> <?= e($t['size']) ?></td>
          <td><?= $t['plate_number'] ? '<a href="vehicle_profile.php?id='.$t['current_vehicle_id'].'">'.e($t['plate_number']).'</a>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= e($t['current_position'] ?? '—') ?></td>
          <td><?= $t['current_tread_mm']!==null ? number_format($t['current_tread_mm'],1).'mm' : '—' ?></td>
          <td><?= e($t['current_psi'] ?? '—') ?></td>
          <td><span class="badge <?= badge_class($ts ?? $t['status']) ?>"><?= $ts ?? $t['status'] ?></span></td>
          <td class="mono"><?php
            if ($t['status']==='IN_SERVICE' && $t['install_odometer']) {
              $vm = db()->prepare("SELECT current_mileage FROM vehicles WHERE id=?"); $vm->execute([$t['current_vehicle_id']]); $vmv = $vm->fetch();
              echo number_format(max(0,($vmv['current_mileage']??0)-$t['install_odometer'])).' km';
            } else { echo '—'; } ?></td>
          <td class="flex gap-2">
            <a class="btn btn-outline btn-sm" href="#" onclick="openTyreDetail(<?= $t['id'] ?>);return false;">View</a>
            <?php if (can_edit() && $t['status'] !== 'IN_SERVICE'): ?>
              <button class="btn btn-danger btn-sm" onclick="deleteTyre(<?= $t['id'] ?>,'<?= e(addslashes($t['serial_number'])) ?>')">Delete</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$tyres): ?><tr><td colspan="9" class="text-muted" style="text-align:center;padding:30px;">No tyres found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<div class="tl-modal-backdrop" id="tyreDetailModal"><div class="tl-modal" id="tyreDetailContent" style="max-width:560px;"></div></div>
<?php $extraScript = "<script src='assets/js/vehicle_profile.js'></script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

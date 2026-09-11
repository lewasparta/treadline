<?php
require_once __DIR__ . '/config/config.php';
require_role(['ADMIN','FLEET_MANAGER','TYRE_INSPECTOR','SUPERVISOR']);
$pageTitle = 'Transfer Tyre';
$vehicles = db()->query("SELECT id, plate_number FROM vehicles WHERE status='ACTIVE' ORDER BY plate_number")->fetchAll();
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Transfer Tyre</h1><div class="sub">Move a tyre from one vehicle to another</div></div></div>
  <div class="tl-card" style="max-width:600px;">
    <form id="transferForm">
      <label class="form-label">Tyre Serial Number *</label>
      <input class="form-control" name="serial_number" required placeholder="Scan or type serial number" style="margin-bottom:14px;">
      <div class="form-row">
        <div><label class="form-label">To Vehicle *</label>
          <select class="form-control" name="to_vehicle_id" required>
            <option value="">Select vehicle…</option>
            <?php foreach ($vehicles as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['plate_number']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">To Position *</label><input class="form-control" name="to_position" required placeholder="e.g. FL"></div>
      </div>
      <label class="form-label">Reason</label>
      <input class="form-control" name="reason" placeholder="e.g. Vehicle A decommissioned" style="margin-bottom:14px;">
      <button type="button" class="btn btn-accent" onclick="submitTransfer()">Transfer Tyre</button>
    </form>
  </div>
</div>
</div>
<?php $extraScript = "<script>
async function submitTransfer(){
  const f = document.getElementById('transferForm');
  const data = Object.fromEntries(new FormData(f).entries());
  const r = await TL.post('api/transfer_save.php', data);
  if (r.ok) { TL.toast('Tyre transferred','success'); setTimeout(()=>location.href='vehicle_profile.php?id='+data.to_vehicle_id, 700); }
}
</script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

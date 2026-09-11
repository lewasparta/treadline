<?php
require_once __DIR__ . '/config/config.php';
require_role(['ADMIN','FLEET_MANAGER','SUPERVISOR']);
$pageTitle = 'Retread Tyre';
$suppliers = db()->query("SELECT id, company FROM suppliers ORDER BY company")->fetchAll();
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Retread Tyre</h1><div class="sub">Send a removed/worn tyre for retreading and return it to stock</div></div></div>
  <div class="tl-card" style="max-width:600px;">
    <form id="retreadForm">
      <label class="form-label">Tyre Serial Number *</label>
      <input class="form-control" name="serial_number" required style="margin-bottom:14px;">
      <div class="form-row">
        <div><label class="form-label">Retread Number</label><input class="form-control" name="retread_number"></div>
        <div><label class="form-label">Supplier</label>
          <select class="form-control" name="supplier_id"><option value="">—</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['company']) ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="form-row">
        <div><label class="form-label">Cost</label><input class="form-control" name="cost" type="number" step="0.01"></div>
        <div><label class="form-label">Tread After Retread (mm) *</label><input class="form-control" name="tread_after_retread" type="number" step="0.1" required></div>
      </div>
      <button type="button" class="btn btn-accent" onclick="submitRetread()">Log Retread</button>
    </form>
  </div>
</div>
</div>
<?php $extraScript = "<script>
async function submitRetread(){
  const f = document.getElementById('retreadForm');
  const data = Object.fromEntries(new FormData(f).entries());
  const r = await TL.post('api/retread_save.php', data);
  if (r.ok) { TL.toast('Retread logged — tyre returned to stock','success'); setTimeout(()=>location.href='stock.php', 700); }
}
</script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

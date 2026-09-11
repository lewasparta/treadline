<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Tyre Stock';

$counts = db()->query("SELECT status, COUNT(*) c FROM tyres GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$stock = db()->query("SELECT t.*, s.company AS supplier_name FROM tyres t LEFT JOIN suppliers s ON s.id=t.supplier_id WHERE t.status='STOCK' ORDER BY t.date_added DESC")->fetchAll();
$suppliers = db()->query("SELECT id, company FROM suppliers ORDER BY company")->fetchAll();

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head">
    <div><h1>Tyre Inventory</h1><div class="sub">Stock, repairs, retreads and lifecycle at a glance</div></div>
    <?php if (can_edit()): ?><button class="btn btn-accent" onclick="TL.openModal('addStockModal')">+ Add New Stock Tyre</button><?php endif; ?>
  </div>

  <div class="stat-grid">
    <div class="stat-card accent-navy"><div class="icon">📦</div><div class="value"><?= (int)($counts['STOCK'] ?? 0) ?></div><div class="label">In Stock</div></div>
    <div class="stat-card accent-green"><div class="icon">🛞</div><div class="value"><?= (int)($counts['IN_SERVICE'] ?? 0) ?></div><div class="label">In Service</div></div>
    <div class="stat-card accent-amber"><div class="icon">🔧</div><div class="value"><?= (int)($counts['UNDER_REPAIR'] ?? 0) ?></div><div class="label">Under Repair</div></div>
    <div class="stat-card accent-blue"><div class="icon">♻</div><div class="value"><?= (int)($counts['RETREAD'] ?? 0) ?></div><div class="label">Retread</div></div>
    <div class="stat-card accent-red"><div class="icon">🗑</div><div class="value"><?= (int)($counts['REMOVED'] ?? 0) ?></div><div class="label">Removed</div></div>
    <div class="stat-card accent-navy"><div class="icon">⛔</div><div class="value"><?= (int)($counts['SCRAP'] ?? 0) ?></div><div class="label">Scrap</div></div>
  </div>

  <div class="tl-card">
    <div class="tl-card-head"><h3>Available Stock (<?= count($stock) ?>)</h3>
      <a href="exports/tyres_csv.php?status=STOCK" class="btn btn-outline btn-sm">⬇ Export CSV</a></div>
    <table class="tl-table">
      <thead><tr><th>Serial</th><th>Brand/Size</th><th>New Tread</th><th>Rec. PSI</th><th>Supplier</th><th>Price</th><th>Location</th><th>Added</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($stock as $s): ?>
        <tr>
          <td class="mono"><?= e($s['serial_number']) ?><br><span class="text-muted" style="font-size:11px;"><?= e($s['branding_code']) ?></span></td>
          <td><?= e($s['brand'].' '.$s['size']) ?></td>
          <td><?= number_format($s['new_tread_mm'],1) ?>mm</td>
          <td><?= $s['recommended_psi'] ?> psi</td>
          <td><?= e($s['supplier_name'] ?? '—') ?></td>
          <td><?= fmt_money($s['purchase_price']) ?></td>
          <td><?= e($s['storage_location']) ?></td>
          <td><?= fmt_date($s['date_added']) ?></td>
          <td><?php if (can_edit()): ?><button class="btn btn-danger btn-sm" onclick="deleteTyre(<?= $s['id'] ?>,'<?= e(addslashes($s['serial_number'])) ?>')">Delete</button><?php endif; ?></td>
        </tr>
      <?php endforeach; if (!$stock): ?><tr><td colspan="9" class="text-muted" style="text-align:center;padding:30px;">No stock tyres available. <a href="#" onclick="TL.openModal('addStockModal');return false;">Add one</a>.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<div class="tl-modal-backdrop" id="addStockModal">
  <div class="tl-modal">
    <div class="tl-modal-head"><h3>Add New Stock Tyre</h3><button class="tl-modal-close" onclick="TL.closeModal('addStockModal')">&times;</button></div>
    <div class="tl-modal-body">
      <form id="stockForm">
        <div class="form-row">
          <div><label class="form-label">Serial Number *</label><input class="form-control" name="serial_number" required></div>
          <div><label class="form-label">Branding Code</label><input class="form-control" name="branding_code"></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">Brand *</label><input class="form-control" name="brand" required></div>
          <div><label class="form-label">Model</label><input class="form-control" name="model"></div>
          <div><label class="form-label">Size *</label><input class="form-control" name="size" required></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">New Tread (mm)</label><input class="form-control" name="new_tread_mm" type="number" step="0.1" value="8.0"></div>
          <div><label class="form-label">Recommended PSI</label><input class="form-control" name="recommended_psi" type="number" step="0.1" value="32"></div>
          <div><label class="form-label">Purchase Price</label><input class="form-control" name="purchase_price" type="number" step="0.01"></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">Expected Mileage</label><input class="form-control" name="expected_mileage" value="60000"></div>
          <div><label class="form-label">Storage Location</label><input class="form-control" name="storage_location"></div>
        </div>
      </form>
    </div>
    <div class="tl-modal-foot"><button class="btn btn-outline" onclick="TL.closeModal('addStockModal')">Cancel</button>
      <button class="btn btn-accent" onclick="submitStock2()">Add to Stock</button></div>
  </div>
</div>
<?php $extraScript = "<script>
function submitStock2(){
  const f = document.getElementById('stockForm');
  const data = Object.fromEntries(new FormData(f).entries());
  TL.post('api/stock_add.php', data).then(r => { if (r.ok) { TL.toast('Added to stock','success'); setTimeout(()=>location.reload(),600); } });
}
async function deleteTyre(tyreId, serial){
  if (!confirm('Delete tyre \"' + serial + '\"? If it has no recorded history it will be permanently deleted; otherwise it will be marked SCRAP.')) return;
  const r = await TL.post('api/tyre_delete.php', { id: tyreId });
  if (r.ok) { TL.toast(r.message || 'Done', 'success'); setTimeout(()=>location.reload(),700); }
}
</script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

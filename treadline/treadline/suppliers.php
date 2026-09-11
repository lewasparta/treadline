<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Suppliers';
$suppliers = db()->query("SELECT s.*, COUNT(t.id) tcount, COALESCE(SUM(t.purchase_price),0) total_spend
  FROM suppliers s LEFT JOIN tyres t ON t.supplier_id=s.id GROUP BY s.id ORDER BY s.company")->fetchAll();
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Suppliers</h1><div class="sub"><?= count($suppliers) ?> supplier(s)</div></div>
    <?php if (can_edit()): ?><button class="btn btn-accent" onclick="openSupplierModal()">+ Add Supplier</button><?php endif; ?></div>
  <div class="tl-card">
    <table class="tl-table">
      <thead><tr><th>Company</th><th>Contact</th><th>Phone</th><th>Products</th><th>Tyres Supplied</th><th>Total Spend</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($suppliers as $s): ?>
        <tr>
          <td><b><?= e($s['company']) ?></b> <?php if($s['status']==='INACTIVE'): ?><span class="badge badge-muted">INACTIVE</span><?php endif; ?></td>
          <td><?= e($s['contact_person']) ?></td><td><?= e($s['phone']) ?></td>
          <td><?= e($s['products']) ?></td><td><?= (int)$s['tcount'] ?></td><td><?= fmt_money($s['total_spend']) ?></td>
          <td class="flex gap-2">
            <?php if (can_edit()): ?>
              <button class="btn btn-outline btn-sm" onclick="openSupplierModal(<?= $s['id'] ?>)">Edit</button>
              <button class="btn btn-danger btn-sm" onclick="tlConfirmPost('api/supplier_delete.php',{id:<?= $s['id'] ?>},'Delete supplier &quot;<?= e(addslashes($s['company'])) ?>&quot;?')">Delete</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<div class="tl-modal-backdrop" id="supplierModal">
  <div class="tl-modal">
    <div class="tl-modal-head"><h3 id="supplierModalTitle">Add Supplier</h3><button class="tl-modal-close" onclick="TL.closeModal('supplierModal')">&times;</button></div>
    <div class="tl-modal-body">
      <form id="supplierForm">
        <input type="hidden" id="sf_id">
        <div class="form-row"><div><label class="form-label">Company *</label><input class="form-control" id="sf_company" required></div>
        <div><label class="form-label">Contact Person</label><input class="form-control" id="sf_contact_person"></div></div>
        <div class="form-row"><div><label class="form-label">Phone</label><input class="form-control" id="sf_phone"></div>
        <div><label class="form-label">Email</label><input class="form-control" id="sf_email"></div></div>
        <div class="form-row"><div><label class="form-label">Address</label><input class="form-control" id="sf_address"></div>
        <div><label class="form-label">Products</label><input class="form-control" id="sf_products"></div></div>
        <div class="form-row" id="sf_status_wrap" style="display:none;">
          <div><label class="form-label">Status</label>
            <select class="form-control" id="sf_status"><option value="ACTIVE">ACTIVE</option><option value="INACTIVE">INACTIVE</option></select>
          </div>
        </div>
      </form>
    </div>
    <div class="tl-modal-foot"><button class="btn btn-outline" onclick="TL.closeModal('supplierModal')">Cancel</button>
      <button class="btn btn-accent" onclick="saveSupplier()">Save Supplier</button></div>
  </div>
</div>
<?php $extraScript = "<script>
function resetSupplierForm(){
  ['id','company','contact_person','phone','email','address','products'].forEach(k => document.getElementById('sf_'+k).value = '');
  document.getElementById('sf_status').value = 'ACTIVE';
}
async function openSupplierModal(id){
  resetSupplierForm();
  const editing = !!id;
  document.getElementById('supplierModalTitle').textContent = editing ? 'Edit Supplier' : 'Add Supplier';
  document.getElementById('sf_status_wrap').style.display = editing ? '' : 'none';
  if (editing) {
    const res = await fetch('api/supplier_get.php?id=' + id);
    const data = await res.json();
    if (data.supplier) {
      const s = data.supplier;
      document.getElementById('sf_id').value = s.id;
      document.getElementById('sf_company').value = s.company;
      document.getElementById('sf_contact_person').value = s.contact_person || '';
      document.getElementById('sf_phone').value = s.phone || '';
      document.getElementById('sf_email').value = s.email || '';
      document.getElementById('sf_address').value = s.address || '';
      document.getElementById('sf_products').value = s.products || '';
      document.getElementById('sf_status').value = s.status;
    }
  }
  TL.openModal('supplierModal');
}
function saveSupplier(){
  const data = {
    id: document.getElementById('sf_id').value,
    company: document.getElementById('sf_company').value,
    contact_person: document.getElementById('sf_contact_person').value,
    phone: document.getElementById('sf_phone').value,
    email: document.getElementById('sf_email').value,
    address: document.getElementById('sf_address').value,
    products: document.getElementById('sf_products').value,
    status: document.getElementById('sf_status').value,
  };
  TL.post('api/supplier_save.php', data).then(r => { if (r.ok) { TL.toast('Saved','success'); setTimeout(()=>location.reload(),600); } });
}
</script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

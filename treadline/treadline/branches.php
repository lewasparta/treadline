<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Branches / Groups';

$branches = db()->query("SELECT b.*,
  (SELECT COUNT(*) FROM vehicles v WHERE v.branch_id=b.id AND v.status<>'ARCHIVED') vcount,
  (SELECT COUNT(*) FROM tyres t JOIN vehicles v ON v.id=t.current_vehicle_id WHERE v.branch_id=b.id AND t.status='IN_SERVICE') tcount
  FROM branches b ORDER BY b.name")->fetchAll();

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
$u = current_user();
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Branches / Groups</h1><div class="sub"><?= count($branches) ?> branch(es)</div></div>
    <?php if ($u['role_name']==='ADMIN'): ?><button class="btn btn-accent" onclick="openBranchModal()">+ Add Branch</button><?php endif; ?></div>

  <div class="form-row" style="grid-template-columns:repeat(3,1fr);">
    <?php foreach ($branches as $b): ?>
      <div class="tl-card">
        <h3 style="margin:0 0 4px;"><?= e($b['name']) ?> <?php if($b['status']==='INACTIVE'): ?><span class="badge badge-muted">INACTIVE</span><?php endif; ?></h3>
        <div class="text-muted" style="font-size:12px;margin-bottom:10px;"><?= e($b['code']) ?> · <?= e($b['location']) ?></div>
        <div class="form-row" style="grid-template-columns:1fr 1fr;margin-bottom:10px;">
          <div><span class="text-muted" style="font-size:11px;">VEHICLES</span><br><b style="font-size:18px;"><?= (int)$b['vcount'] ?></b></div>
          <div><span class="text-muted" style="font-size:11px;">ACTIVE TYRES</span><br><b style="font-size:18px;"><?= (int)$b['tcount'] ?></b></div>
        </div>
        <div class="text-muted" style="font-size:12px;">Manager: <?= e($b['manager']) ?> · <?= e($b['contact']) ?></div>
        <div class="flex gap-2" style="margin-top:10px;">
          <a class="btn btn-outline btn-sm" href="vehicles.php?branch=<?= $b['id'] ?>">View Vehicles →</a>
          <?php if ($u['role_name']==='ADMIN'): ?>
            <button class="btn btn-outline btn-sm" onclick="openBranchModal(<?= $b['id'] ?>)">Edit</button>
            <button class="btn btn-danger btn-sm" onclick="tlConfirmPost('api/branch_delete.php',{id:<?= $b['id'] ?>},'Delete branch &quot;<?= e(addslashes($b['name'])) ?>&quot;? This cannot be undone.')">Delete</button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</div>

<div class="tl-modal-backdrop" id="branchModal">
  <div class="tl-modal">
    <div class="tl-modal-head"><h3 id="branchModalTitle">Add Branch</h3><button class="tl-modal-close" onclick="TL.closeModal('branchModal')">&times;</button></div>
    <div class="tl-modal-body">
      <form id="branchForm">
        <input type="hidden" id="bf_id">
        <div class="form-row">
          <div><label class="form-label">Branch Name *</label><input class="form-control" id="bf_name" required></div>
          <div><label class="form-label">Branch Code *</label><input class="form-control" id="bf_code" required></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">Location</label><input class="form-control" id="bf_location"></div>
          <div><label class="form-label">Manager</label><input class="form-control" id="bf_manager"></div>
          <div><label class="form-label">Contact</label><input class="form-control" id="bf_contact"></div>
        </div>
        <div class="form-row" id="bf_status_wrap" style="display:none;">
          <div><label class="form-label">Status</label>
            <select class="form-control" id="bf_status"><option value="ACTIVE">ACTIVE</option><option value="INACTIVE">INACTIVE</option></select>
          </div>
        </div>
      </form>
    </div>
    <div class="tl-modal-foot"><button class="btn btn-outline" onclick="TL.closeModal('branchModal')">Cancel</button>
      <button class="btn btn-accent" onclick="saveBranch()">Save Branch</button></div>
  </div>
</div>
<?php $extraScript = "<script>
function resetBranchForm(){
  ['id','name','code','location','manager','contact'].forEach(k => document.getElementById('bf_'+k).value = '');
  document.getElementById('bf_status').value = 'ACTIVE';
}
async function openBranchModal(id){
  resetBranchForm();
  const editing = !!id;
  document.getElementById('branchModalTitle').textContent = editing ? 'Edit Branch' : 'Add Branch';
  document.getElementById('bf_status_wrap').style.display = editing ? '' : 'none';
  if (editing) {
    const res = await fetch('api/branch_get.php?id=' + id);
    const data = await res.json();
    if (data.branch) {
      const b = data.branch;
      document.getElementById('bf_id').value = b.id;
      document.getElementById('bf_name').value = b.name;
      document.getElementById('bf_code').value = b.code;
      document.getElementById('bf_location').value = b.location || '';
      document.getElementById('bf_manager').value = b.manager || '';
      document.getElementById('bf_contact').value = b.contact || '';
      document.getElementById('bf_status').value = b.status;
    }
  }
  TL.openModal('branchModal');
}
function saveBranch(){
  const data = {
    id: document.getElementById('bf_id').value,
    name: document.getElementById('bf_name').value,
    code: document.getElementById('bf_code').value,
    location: document.getElementById('bf_location').value,
    manager: document.getElementById('bf_manager').value,
    contact: document.getElementById('bf_contact').value,
    status: document.getElementById('bf_status').value,
  };
  TL.post('api/branch_save.php', data).then(r => { if (r.ok) { TL.toast('Saved','success'); setTimeout(()=>location.reload(),600); } });
}
</script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

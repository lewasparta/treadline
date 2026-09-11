<?php
require_once __DIR__ . '/config/config.php';
require_role(['ADMIN']);
$pageTitle = 'Users';

$users = db()->query("SELECT u.*, r.name AS role_name, b.name AS branch_name FROM users u
  JOIN roles r ON r.id=u.role_id LEFT JOIN branches b ON b.id=u.branch_id ORDER BY u.full_name")->fetchAll();
$roles = db()->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$branches = db()->query("SELECT id,name FROM branches ORDER BY name")->fetchAll();

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Users</h1><div class="sub"><?= count($users) ?> user account(s)</div></div>
    <button class="btn btn-accent" onclick="openUserModal()">+ Add User</button></div>

  <div class="tl-card">
    <table class="tl-table">
      <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Branch</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
      <tbody>
      <?php $me = current_user(); foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['full_name']) ?></td>
          <td><?= e($u['username']) ?><br><span class="text-muted" style="font-size:11px;"><?= e($u['email']) ?></span></td>
          <td><span class="badge badge-info"><?= e($u['role_name']) ?></span></td>
          <td><?= e($u['branch_name'] ?? 'All branches') ?></td>
          <td><span class="badge <?= badge_class($u['status']) ?>"><?= $u['status'] ?></span></td>
          <td><?= $u['last_login'] ? fmt_date($u['last_login'],'d M Y H:i') : 'Never' ?></td>
          <td class="flex gap-2">
            <button class="btn btn-outline btn-sm" onclick="openUserModal(<?= $u['id'] ?>)">Edit</button>
            <button class="btn btn-outline btn-sm" onclick="openPasswordModal(<?= $u['id'] ?>,'<?= e(addslashes($u['full_name'])) ?>')">Reset Password</button>
            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
              <button class="btn btn-danger btn-sm" onclick="tlConfirmPost('api/user_delete.php',{id:<?= $u['id'] ?>},'Delete user &quot;<?= e(addslashes($u['full_name'])) ?>&quot;? This cannot be undone.')">Delete</button>
            <?php else: ?>
              <span class="text-muted" style="font-size:11px;">(you)</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- Add/Edit User Modal -->
<div class="tl-modal-backdrop" id="userModal">
  <div class="tl-modal">
    <div class="tl-modal-head"><h3 id="userModalTitle">Add User</h3><button class="tl-modal-close" onclick="TL.closeModal('userModal')">&times;</button></div>
    <div class="tl-modal-body">
      <form id="userForm">
        <input type="hidden" id="uf_id">
        <div class="form-row">
          <div><label class="form-label">Full Name *</label><input class="form-control" id="uf_full_name" required></div>
          <div><label class="form-label">Username *</label><input class="form-control" id="uf_username" required></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">Email</label><input class="form-control" id="uf_email" type="email"></div>
          <div id="uf_password_wrap"><label class="form-label">Password *</label><input class="form-control" id="uf_password" type="password"></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">Role *</label>
            <select class="form-control" id="uf_role_id" required><?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select>
          </div>
          <div><label class="form-label">Branch</label>
            <select class="form-control" id="uf_branch_id"><option value="">All branches</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <div class="form-row" id="uf_status_wrap" style="display:none;">
          <div><label class="form-label">Status</label>
            <select class="form-control" id="uf_status"><option value="ACTIVE">ACTIVE</option><option value="SUSPENDED">SUSPENDED</option></select>
          </div>
        </div>
      </form>
    </div>
    <div class="tl-modal-foot"><button class="btn btn-outline" onclick="TL.closeModal('userModal')">Cancel</button>
      <button class="btn btn-accent" onclick="saveUser()">Save User</button></div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="tl-modal-backdrop" id="passwordModal">
  <div class="tl-modal" style="max-width:420px;">
    <div class="tl-modal-head"><h3>Reset Password</h3><button class="tl-modal-close" onclick="TL.closeModal('passwordModal')">&times;</button></div>
    <div class="tl-modal-body">
      <p class="text-muted" id="pw_target_name" style="font-size:13px;"></p>
      <input type="hidden" id="pw_id">
      <label class="form-label">New Password *</label>
      <input class="form-control" id="pw_password" type="password" placeholder="Minimum 4 characters">
    </div>
    <div class="tl-modal-foot"><button class="btn btn-outline" onclick="TL.closeModal('passwordModal')">Cancel</button>
      <button class="btn btn-accent" onclick="savePassword()">Set New Password</button></div>
  </div>
</div>

<?php $extraScript = "<script>
function resetUserForm(){
  document.getElementById('uf_id').value = '';
  document.getElementById('uf_full_name').value = '';
  document.getElementById('uf_username').value = '';
  document.getElementById('uf_email').value = '';
  document.getElementById('uf_password').value = '';
  document.getElementById('uf_role_id').selectedIndex = 0;
  document.getElementById('uf_branch_id').value = '';
  document.getElementById('uf_status').value = 'ACTIVE';
}
async function openUserModal(id){
  resetUserForm();
  const editing = !!id;
  document.getElementById('userModalTitle').textContent = editing ? 'Edit User' : 'Add User';
  document.getElementById('uf_password_wrap').style.display = editing ? 'none' : '';
  document.getElementById('uf_status_wrap').style.display = editing ? '' : 'none';
  if (editing) {
    const res = await fetch('api/user_get.php?id=' + id);
    const data = await res.json();
    if (data.user) {
      const u = data.user;
      document.getElementById('uf_id').value = u.id;
      document.getElementById('uf_full_name').value = u.full_name;
      document.getElementById('uf_username').value = u.username;
      document.getElementById('uf_email').value = u.email || '';
      document.getElementById('uf_role_id').value = u.role_id;
      document.getElementById('uf_branch_id').value = u.branch_id || '';
      document.getElementById('uf_status').value = u.status;
    }
  }
  TL.openModal('userModal');
}
function saveUser(){
  const id = document.getElementById('uf_id').value;
  const base = {
    full_name: document.getElementById('uf_full_name').value,
    username: document.getElementById('uf_username').value,
    email: document.getElementById('uf_email').value,
    role_id: document.getElementById('uf_role_id').value,
    branch_id: document.getElementById('uf_branch_id').value,
  };
  if (id) {
    base.id = id;
    base.status = document.getElementById('uf_status').value;
    TL.post('api/user_update.php', base).then(r => { if (r.ok) { TL.toast('User updated','success'); setTimeout(()=>location.reload(),600); } });
  } else {
    base.password = document.getElementById('uf_password').value;
    TL.post('api/user_save.php', base).then(r => { if (r.ok) { TL.toast('User created','success'); setTimeout(()=>location.reload(),600); } });
  }
}
function openPasswordModal(id, name){
  document.getElementById('pw_id').value = id;
  document.getElementById('pw_target_name').textContent = 'Setting a new password for ' + name + '.';
  document.getElementById('pw_password').value = '';
  TL.openModal('passwordModal');
}
function savePassword(){
  const id = document.getElementById('pw_id').value;
  const password = document.getElementById('pw_password').value;
  TL.post('api/user_set_password.php', { id, password }).then(r => {
    if (r.ok) { TL.toast('Password updated','success'); TL.closeModal('passwordModal'); }
  });
}
</script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

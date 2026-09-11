<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Vehicle Types';
$types = db()->query("SELECT vt.*, (SELECT COUNT(*) FROM vehicles v WHERE v.vehicle_type_id=vt.id) vcount FROM vehicle_types vt ORDER BY vt.name")->fetchAll();
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
$u = current_user();
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Vehicle Types</h1><div class="sub">Design the exact tyre-map layout for each vehicle type — any axle count, dual wheels, or oddball configuration (tuktuks, trailers, forklifts, prime movers...)</div></div>
    <?php if ($u['role_name']==='ADMIN'): ?><button class="btn btn-accent" onclick="openTypeModal()">+ Add Type</button><?php endif; ?></div>
  <div class="tl-card">
    <table class="tl-table">
      <thead><tr><th>Name</th><th>Axle Config</th><th>Positions</th><th>Spare Default</th><th>Vehicles Using</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($types as $t): ?>
        <tr>
          <td><?= e($t['name']) ?></td>
          <td><span class="badge badge-info"><?= e($t['axle_config']) ?></span></td>
          <td class="mono" style="font-size:11.5px;"><?= e(implode(', ', json_decode($t['positions'],true) ?: [])) ?></td>
          <td><?= (int)$t['spare_default'] ?></td>
          <td><?= (int)$t['vcount'] ?></td>
          <td class="flex gap-2">
            <?php if ($u['role_name']==='ADMIN'): ?>
              <button class="btn btn-outline btn-sm" onclick="openTypeModal(<?= $t['id'] ?>)">Edit</button>
              <button class="btn btn-danger btn-sm" onclick="tlConfirmPost('api/vehicle_type_delete.php',{id:<?= $t['id'] ?>},'Delete vehicle type &quot;<?= e(addslashes($t['name'])) ?>&quot;?')">Delete</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<div class="tl-modal-backdrop" id="typeModal">
  <div class="tl-modal" style="max-width:680px;">
    <div class="tl-modal-head"><h3 id="typeModalTitle">Add Vehicle Type</h3><button class="tl-modal-close" onclick="TL.closeModal('typeModal')">&times;</button></div>
    <div class="tl-modal-body">
      <form id="typeForm">
        <input type="hidden" id="tf_id">
        <div class="form-row">
          <div><label class="form-label">Name *</label><input class="form-control" id="tf_name" required></div>
          <div><label class="form-label">Axle Config Label *</label>
            <select class="form-control" id="tf_axle_config">
              <option value="4X2">4x2</option><option value="4X4">4x4</option><option value="6X2">6x2</option>
              <option value="6X4">6x4</option><option value="8X4">8x4</option>
              <option value="TRAILER_2AXLE">Trailer 2-axle</option><option value="TRAILER_3AXLE">Trailer 3-axle</option><option value="BUS">Bus</option>
            </select>
          </div>
          <div><label class="form-label">Spare Slots (default)</label><input class="form-control" id="tf_spare_default" type="number" min="0" max="4" value="1"></div>
        </div>

        <label class="form-label" style="margin-top:6px;">Tyre Rows / Axles</label>
        <p class="text-muted" style="font-size:12px;margin:2px 0 10px;">Add one row per axle or group. "Drive" marks it as a driven axle (shown in green). Position codes are short labels shown on each tyre slot — e.g. <code>FL, FR</code> or <code>RL1-O, RL1-I, RR1-I, RR1-O</code> for a dual-wheel driven axle, or <code>P1, P2, P4, P3</code> for a trailer axle.</p>
        <div id="rowsBuilder"></div>
        <button type="button" class="btn btn-outline btn-sm" onclick="addLayoutRow()">+ Add Row</button>
      </form>
    </div>
    <div class="tl-modal-foot"><button class="btn btn-outline" onclick="TL.closeModal('typeModal')">Cancel</button>
      <button class="btn btn-accent" onclick="saveType()">Save Vehicle Type</button></div>
  </div>
</div>

<?php $extraScript = "<script>
function addLayoutRow(label, drive, positions){
  const wrap = document.getElementById('rowsBuilder');
  const div = document.createElement('div');
  div.className = 'form-row row-item';
  div.style = 'grid-template-columns:1.3fr auto 2fr auto;align-items:end;margin-bottom:10px;';
  div.innerHTML = `
    <div><label class=\"form-label\">Row Label</label><input class=\"form-control row-label\" value=\"\${label||''}\" placeholder=\"e.g. STEER or AXLE 1\"></div>
    <div style=\"padding-bottom:9px;\"><label style=\"font-size:12px;display:flex;align-items:center;gap:5px;white-space:nowrap;\"><input type=\"checkbox\" class=\"row-drive\" \${drive?'checked':''}> Drive axle</label></div>
    <div><label class=\"form-label\">Position Codes (comma-separated)</label><input class=\"form-control row-positions\" value=\"\${(positions||[]).join(', ')}\" placeholder=\"FL, FR\"></div>
    <button type=\"button\" class=\"btn btn-danger btn-sm\" style=\"margin-bottom:9px;\" onclick=\"this.closest('.row-item').remove()\">&times;</button>
  `;
  wrap.appendChild(div);
}

function resetTypeForm(){
  document.getElementById('typeForm').reset();
  document.getElementById('tf_id').value = '';
  document.getElementById('rowsBuilder').innerHTML = '';
  document.getElementById('typeModalTitle').textContent = 'Add Vehicle Type';
  addLayoutRow('STEER', false, ['FL','FR']);
  addLayoutRow('DRIVE - DRIVEN', true, ['RL','RR']);
}

async function openTypeModal(id){
  resetTypeForm();
  if (id) {
    document.getElementById('typeModalTitle').textContent = 'Edit Vehicle Type';
    document.getElementById('rowsBuilder').innerHTML = '';
    const res = await fetch('api/vehicle_type_get.php?id=' + id);
    const data = await res.json();
    if (data.vehicle_type) {
      const vt = data.vehicle_type;
      document.getElementById('tf_id').value = vt.id;
      document.getElementById('tf_name').value = vt.name;
      document.getElementById('tf_axle_config').value = vt.axle_config;
      document.getElementById('tf_spare_default').value = vt.spare_default;
      (vt.layout_rows || []).forEach(r => addLayoutRow(r.label, r.drive, r.positions));
      if (!vt.layout_rows || !vt.layout_rows.length) addLayoutRow('ROW', false, []);
    }
  }
  TL.openModal('typeModal');
}

function saveType(){
  const rows = [...document.querySelectorAll('#rowsBuilder .row-item')].map(div => ({
    label: div.querySelector('.row-label').value.trim(),
    drive: div.querySelector('.row-drive').checked,
    positions: div.querySelector('.row-positions').value.split(',').map(s => s.trim()).filter(Boolean)
  })).filter(r => r.positions.length);

  const payload = {
    id: document.getElementById('tf_id').value || undefined,
    name: document.getElementById('tf_name').value,
    axle_config: document.getElementById('tf_axle_config').value,
    spare_default: document.getElementById('tf_spare_default').value,
    layout_rows: rows
  };
  TL.post('api/vehicle_type_save.php', payload).then(r => { if (r.ok) { TL.toast('Saved','success'); setTimeout(()=>location.reload(),600); } });
}

document.addEventListener('DOMContentLoaded', resetTypeForm);
</script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

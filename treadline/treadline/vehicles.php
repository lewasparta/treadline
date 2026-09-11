<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Vehicles';

$where = ["v.status <> 'ARCHIVED'"];
$params = [];
if (!empty($_GET['branch'])) { $where[] = 'v.branch_id = ?'; $params[] = (int)$_GET['branch']; }
if (!empty($_GET['status'])) { $where[] = 'v.status = ?'; $params[] = $_GET['status']; }
if (!empty($_GET['q'])) { $where[] = '(v.plate_number LIKE ? OR v.fleet_number LIKE ?)'; $params[] = "%{$_GET['q']}%"; $params[] = "%{$_GET['q']}%"; }
$whereSql = implode(' AND ', $where);

$critical = (float) get_setting('tread_critical_mm', 2.0);
$watch = (float) get_setting('tread_watch_mm', 5.0);

$sql = "SELECT v.*, b.name AS branch_name, vt.name AS type_name,
  (SELECT COUNT(*) FROM tyres t WHERE t.current_vehicle_id=v.id AND t.status='IN_SERVICE') AS tyre_count,
  (SELECT COUNT(*) FROM tyres t WHERE t.current_vehicle_id=v.id AND t.status='IN_SERVICE' AND t.current_tread_mm <= $critical) AS critical_count,
  (SELECT COUNT(*) FROM tyres t WHERE t.current_vehicle_id=v.id AND t.status='IN_SERVICE' AND t.current_tread_mm < $watch AND t.current_tread_mm > $critical) AS watch_count
  FROM vehicles v
  LEFT JOIN branches b ON b.id = v.branch_id
  LEFT JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
  WHERE $whereSql ORDER BY v.plate_number";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

$branches = db()->query("SELECT id,name FROM branches WHERE status='ACTIVE' ORDER BY name")->fetchAll();
$vtypes = db()->query("SELECT id,name FROM vehicle_types ORDER BY name")->fetchAll();
$allVehiclesForTowing = db()->query("SELECT id, plate_number FROM vehicles WHERE status<>'ARCHIVED' ORDER BY plate_number")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head">
    <div><h1>Vehicles</h1><div class="sub"><?= count($vehicles) ?> vehicle(s)<?= !empty($_GET['branch']) ? ' in this branch' : ' across all branches' ?></div></div>
    <?php if (can_edit()): ?><button class="btn btn-accent" onclick="openVehicleModal()">+ Add Vehicle</button><?php endif; ?>
  </div>

  <form class="tl-card" style="margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
    <div style="flex:1;min-width:180px;"><label class="form-label">Search plate / fleet no.</label><input class="form-control" name="q" value="<?= e($_GET['q'] ?? '') ?>"></div>
    <div style="min-width:160px;"><label class="form-label">Status</label>
      <select name="status" class="form-control">
        <option value="">All statuses</option>
        <?php foreach (['ACTIVE','MAINTENANCE','OUT_OF_SERVICE','SOLD'] as $s): ?>
          <option value="<?= $s ?>" <?= ($_GET['status'] ?? '')===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (!empty($_GET['branch'])): ?><input type="hidden" name="branch" value="<?= (int)$_GET['branch'] ?>"><?php endif; ?>
    <button class="btn btn-outline">Filter</button>
  </form>

  <div class="tl-card">
    <table class="tl-table">
      <thead><tr><th>Plate No.</th><th>Fleet No.</th><th>Type</th><th>Branch</th><th>Driver</th><th>Mileage</th><th>Tyres</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($vehicles as $v): ?>
        <tr>
          <td><a href="vehicle_profile.php?id=<?= $v['id'] ?>" style="font-weight:800;"><?= e($v['plate_number']) ?></a></td>
          <td><?= e($v['fleet_number']) ?></td>
          <td><?= e($v['make'].' '.$v['model']) ?><br><span class="text-muted" style="font-size:11px;"><?= e($v['type_name']) ?></span></td>
          <td><?= e($v['branch_name']) ?></td>
          <td><?= e($v['driver_name']) ?></td>
          <td class="mono"><?= number_format($v['current_mileage']) ?> km</td>
          <td>
            <?= (int)$v['tyre_count'] ?> fitted
            <?php if ($v['critical_count']>0): ?><span class="badge badge-critical"><?= $v['critical_count'] ?> critical</span><?php endif; ?>
            <?php if ($v['watch_count']>0): ?><span class="badge badge-watch"><?= $v['watch_count'] ?> watch</span><?php endif; ?>
          </td>
          <td><span class="badge <?= badge_class($v['status']) ?>"><?= e($v['status']) ?></span></td>
          <td class="flex gap-2">
            <a href="vehicle_profile.php?id=<?= $v['id'] ?>" class="btn btn-outline btn-sm">Open</a>
            <?php if (can_edit()): ?>
              <button class="btn btn-outline btn-sm" onclick="openVehicleModal(<?= $v['id'] ?>)">Edit</button>
              <button class="btn btn-danger btn-sm" onclick="tlConfirmPost('api/vehicle_delete.php',{id:<?= $v['id'] ?>},'Delete vehicle &quot;<?= e(addslashes($v['plate_number'])) ?>&quot;? This cannot be undone.')">Delete</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$vehicles): ?><tr><td colspan="9" class="text-muted" style="text-align:center;padding:30px;">No vehicles found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- Add/Edit Vehicle Modal -->
<div class="tl-modal-backdrop" id="addVehicleModal">
  <div class="tl-modal">
    <div class="tl-modal-head"><h3 id="vehicleModalTitle">Add New Vehicle</h3><button class="tl-modal-close" onclick="TL.closeModal('addVehicleModal')">&times;</button></div>
    <div class="tl-modal-body">
      <form id="addVehicleForm">
        <input type="hidden" name="id" id="vf_id">
        <div class="form-row">
          <div><label class="form-label">Plate Number *</label><input class="form-control" name="plate_number" id="vf_plate_number" required></div>
          <div><label class="form-label">Fleet Number</label><input class="form-control" name="fleet_number" id="vf_fleet_number"></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">Vehicle Type *</label>
            <select class="form-control" name="vehicle_type_id" id="vf_vehicle_type_id" required>
              <?php foreach ($vtypes as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label class="form-label">Branch *</label>
            <select class="form-control" name="branch_id" id="vf_branch_id" required>
              <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div><label class="form-label">Make</label><input class="form-control" name="make" id="vf_make"></div>
          <div><label class="form-label">Model</label><input class="form-control" name="model" id="vf_model"></div>
          <div><label class="form-label">Year</label><input class="form-control" name="year" id="vf_year" type="number" min="1990" max="2035"></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">VIN/Chassis No.</label><input class="form-control" name="vin" id="vf_vin"></div>
          <div><label class="form-label">Department</label><input class="form-control" name="department" id="vf_department"></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">Driver</label><input class="form-control" name="driver_name" id="vf_driver_name" placeholder="Assign or reassign driver"></div>
          <div><label class="form-label">Current Mileage (km)</label><input class="form-control" name="current_mileage" id="vf_current_mileage" type="number" value="0"></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">Status</label>
            <select class="form-control" name="status" id="vf_status">
              <?php foreach (['ACTIVE','MAINTENANCE','OUT_OF_SERVICE','SOLD','ARCHIVED'] as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label class="form-label">Towed By (trailers only)</label>
            <select class="form-control" name="towed_by_vehicle_id" id="vf_towed_by_vehicle_id">
              <option value="">— None —</option>
              <?php foreach ($allVehiclesForTowing as $ov): ?><option value="<?= $ov['id'] ?>"><?= e($ov['plate_number']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>
    </div>
    <div class="tl-modal-foot">
      <button class="btn btn-outline" onclick="TL.closeModal('addVehicleModal')">Cancel</button>
      <button class="btn btn-accent" onclick="submitAddVehicle()">Save Vehicle</button>
    </div>
  </div>
</div>

<?php $extraScript = "<script>
function resetVehicleForm(){
  document.getElementById('addVehicleForm').reset();
  document.getElementById('vf_id').value = '';
  document.getElementById('vehicleModalTitle').textContent = 'Add New Vehicle';
}
async function openVehicleModal(id){
  resetVehicleForm();
  if (id) {
    document.getElementById('vehicleModalTitle').textContent = 'Edit Vehicle';
    const res = await fetch('api/vehicle_get.php?id=' + id);
    const data = await res.json();
    if (data.vehicle) {
      const v = data.vehicle;
      document.getElementById('vf_id').value = v.id;
      document.getElementById('vf_plate_number').value = v.plate_number || '';
      document.getElementById('vf_fleet_number').value = v.fleet_number || '';
      document.getElementById('vf_vehicle_type_id').value = v.vehicle_type_id || '';
      document.getElementById('vf_branch_id').value = v.branch_id || '';
      document.getElementById('vf_make').value = v.make || '';
      document.getElementById('vf_model').value = v.model || '';
      document.getElementById('vf_year').value = v.year || '';
      document.getElementById('vf_vin').value = v.vin || '';
      document.getElementById('vf_department').value = v.department || '';
      document.getElementById('vf_driver_name').value = v.driver_name || '';
      document.getElementById('vf_current_mileage').value = v.current_mileage || 0;
      document.getElementById('vf_status').value = v.status || 'ACTIVE';
      document.getElementById('vf_towed_by_vehicle_id').value = v.towed_by_vehicle_id || '';
    }
  }
  TL.openModal('addVehicleModal');
}
function submitAddVehicle(){
  const f = document.getElementById('addVehicleForm');
  const data = Object.fromEntries(new FormData(f).entries());
  TL.post('api/vehicle_save.php', data).then(r => {
    if (r.ok) { TL.toast(data.id ? 'Vehicle updated' : 'Vehicle added','success'); setTimeout(()=>location.href='vehicle_profile.php?id='+r.id, 600); }
  });
}
" . ($editId ? "document.addEventListener('DOMContentLoaded', function(){ openVehicleModal($editId); });" : "") . "
</script>";
include __DIR__ . '/includes/foot.php'; ?>

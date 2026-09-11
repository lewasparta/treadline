<?php
require_once __DIR__ . '/config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT v.*, b.name AS branch_name, vt.name AS type_name, vt.axle_config, vt.positions, vt.layout_rows,
  tb.plate_number AS towed_by_plate
  FROM vehicles v LEFT JOIN branches b ON b.id=v.branch_id LEFT JOIN vehicle_types vt ON vt.id=v.vehicle_type_id
  LEFT JOIN vehicles tb ON tb.id=v.towed_by_vehicle_id
  WHERE v.id = ?");
$stmt->execute([$id]);
$vehicle = $stmt->fetch();
if (!$vehicle) { http_response_code(404); die('Vehicle not found. <a href="vehicles.php">Back to vehicles</a>'); }
$pageTitle = $vehicle['plate_number'];
$layoutRows = json_decode($vehicle['layout_rows'] ?? '[]', true);
if (!$layoutRows) {
    $flat = json_decode($vehicle['positions'] ?? '[]', true) ?: ['FL','FR','RL','RR'];
    $layoutRows = [['label' => 'POSITIONS', 'drive' => false, 'positions' => $flat]];
}
$otherVehicles = db()->prepare("SELECT id, plate_number FROM vehicles WHERE id<>? AND status<>'ARCHIVED' ORDER BY plate_number");
$otherVehicles->execute([$id]);
$otherVehicles = $otherVehicles->fetchAll();

// Current tyres fitted
$stmt = db()->prepare("SELECT * FROM tyres WHERE current_vehicle_id = ? AND status='IN_SERVICE'");
$stmt->execute([$id]);
$fitted = [];
foreach ($stmt->fetchAll() as $t) { $fitted[$t['current_position']] = $t; }

// Spare count: never let it drop below the highest fitted spare index already in use
$spareCount = max(0, (int)$vehicle['spare_count']);
foreach (array_keys($fitted) as $p) {
    if (preg_match('/^SP(\d+)$/', $p, $m)) $spareCount = max($spareCount, (int)$m[1]);
}

$critical = (float) get_setting('tread_critical_mm', 2.0);
$watch = (float) get_setting('tread_watch_mm', 5.0);

function tyre_slot_html($pos, $tyre, $vehicleOdo) {
    if (!$tyre) {
        return "<div class=\"tyre-slot empty\" data-pos=\"$pos\" ondragover=\"event.preventDefault();this.classList.add('drag-over')\" ondragleave=\"this.classList.remove('drag-over')\" ondrop=\"onSlotDrop(event,'$pos')\">
                  <div class=\"pos\">$pos</div><div style=\"font-size:22px;margin:8px 0 2px;color:#c8c2ac;\">+</div><div class=\"meta\">fit tyre</div>
                </div>";
    }
    $status = tread_status((float)$tyre['current_tread_mm']);
    $mileage = $tyre['install_odometer'] ? max(0, $vehicleOdo - $tyre['install_odometer']) : 0;
    return "<div class=\"tyre-slot status-$status\" draggable=\"true\" data-pos=\"$pos\" data-tyre-id=\"{$tyre['id']}\"
              ondragstart=\"onSlotDragStart(event,'$pos',{$tyre['id']})\"
              ondragover=\"event.preventDefault();this.classList.add('drag-over')\" ondragleave=\"this.classList.remove('drag-over')\"
              ondrop=\"onSlotDrop(event,'$pos')\" onclick=\"openTyreDetail({$tyre['id']})\">
              <div class=\"pos\">$pos</div>
              <div class=\"tread\">" . number_format($tyre['current_tread_mm'],1) . "mm</div>
              <div class=\"meta\">" . e($tyre['brand']) . " " . e($tyre['size']) . "<br>" . number_format($mileage) . " km · " . e($tyre['serial_number']) . "</div>
            </div>";
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-breadcrumb"><a href="vehicles.php">Vehicles</a> / <?= e($vehicle['plate_number']) ?></div>
  <div class="tl-page-head">
    <div>
      <h1><?= e($vehicle['plate_number']) ?> <span class="badge <?= badge_class($vehicle['status']) ?>" style="margin-left:8px;"><?= e($vehicle['status']) ?></span></h1>
      <div class="sub"><?= e($vehicle['make'].' '.$vehicle['model']) ?> · <?= e($vehicle['type_name']) ?> · <?= e($vehicle['branch_name']) ?>
        <?php if ($vehicle['towed_by_plate']): ?> · towed by <a href="vehicle_profile.php?id=<?= $vehicle['towed_by_vehicle_id'] ?>"><?= e($vehicle['towed_by_plate']) ?></a><?php endif; ?>
        &nbsp;<a href="vehicles.php">(change)</a></div>
    </div>
    <div class="flex gap-2">
      <button class="btn btn-outline" onclick="location.href='report_manifest.php?vehicle_id=<?= $id ?>'">🖨 Tyre Manifest</button>
      <button class="btn btn-accent" onclick="location.href='inspection_new.php?vehicle_id=<?= $id ?>'">+ New Inspection</button>
      <?php if (can_edit()): ?>
        <button class="btn btn-outline" onclick="location.href='vehicles.php?edit=<?= $id ?>'">✎ Edit Vehicle</button>
        <button class="btn btn-danger" onclick="deleteVehicle(<?= $id ?>,'<?= e(addslashes($vehicle['plate_number'])) ?>')">Delete Vehicle</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr auto; align-items:end; margin-bottom:20px;">
    <div><label class="form-label">Current Mileage (KM)</label><input class="form-control" id="mileageInput" value="<?= number_format($vehicle['current_mileage']) ?>"></div>
    <div><label class="form-label">Reading Date</label><input class="form-control" type="date" id="mileageDate" value="<?= e($vehicle['mileage_reading_date'] ?: date('Y-m-d')) ?>"></div>
    <div><label class="form-label">Driver</label><input class="form-control" id="driverInput" value="<?= e($vehicle['driver_name']) ?>" <?= can_edit() ? '' : 'disabled' ?> placeholder="Assign a driver"></div>
    <div><label class="form-label">Towed By (trailers)</label>
      <select class="form-control" id="towedByInput" <?= can_edit() ? '' : 'disabled' ?>>
        <option value="">— None —</option>
        <?php foreach ($otherVehicles as $ov): ?>
          <option value="<?= $ov['id'] ?>" <?= (int)$vehicle['towed_by_vehicle_id']===(int)$ov['id']?'selected':'' ?>><?= e($ov['plate_number']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" onclick="updateMileage(<?= $id ?>)">Update Mileage</button>
  </div>
  <?php if (can_edit()): ?>
  <div style="margin:-14px 0 20px;text-align:right;">
    <button class="btn btn-outline btn-sm" onclick="saveDriverAndTowing(<?= $id ?>)">Save Driver / Towing</button>
  </div>
  <?php endif; ?>

  <div class="tabs no-print">
    <a href="#overview" class="tab-link active" onclick="showTab(event,'overview')">Tyre Map</a>
    <a href="#inspections" class="tab-link" onclick="showTab(event,'inspections')">Inspection History</a>
    <a href="#history" class="tab-link" onclick="showTab(event,'history')">Movement History</a>
    <a href="#alerts" class="tab-link" onclick="showTab(event,'alerts')">Alerts</a>
  </div>

  <div id="tab-overview" class="tab-pane">
    <div class="form-row" style="grid-template-columns:2.2fr 1fr; align-items:start;">
      <div class="tyremap-wrap">
        <div class="tyremap" id="tyreMap" data-vehicle-id="<?= $id ?>" data-odo="<?= (int)$vehicle['current_mileage'] ?>">
          <?php foreach ($layoutRows as $ri => $row):
            $label = $row['label'] ?? '';
            $drive = !empty($row['drive']);
            $rowPositions = $row['positions'] ?? [];
            $gap = count($rowPositions) > 2 ? '14px' : '60px';
            $nextDrive = !empty($layoutRows[$ri + 1]['drive'] ?? false);
          ?>
            <div class="axle-label<?= $drive ? ' drive' : '' ?>"><?= e($label) ?></div>
            <div class="axle-row" style="gap:<?= $gap ?>;">
              <?php foreach ($rowPositions as $pos): ?>
                <?= tyre_slot_html($pos, $fitted[$pos] ?? null, $vehicle['current_mileage']) ?>
              <?php endforeach; ?>
            </div>
            <?php if ($ri < count($layoutRows) - 1): ?>
              <div class="axle-connector<?= ($drive || $nextDrive) ? ' drive-connector' : '' ?>"></div>
            <?php endif; ?>
          <?php endforeach; ?>

          <div class="spare-row">
            <div class="spare-label">SPARE</div>
            <div id="spareSlots" style="display:flex;gap:14px;flex-wrap:wrap;">
              <?php if ($spareCount === 0): ?>
                <span class="text-muted" style="font-size:12.5px;align-self:center;">No spare configured for this vehicle</span>
              <?php endif; ?>
              <?php for ($i = 1; $i <= $spareCount; $i++): $sp = "SP$i"; ?>
                <div ondragover="event.preventDefault();this.classList.add('drag-over')" ondragleave="this.classList.remove('drag-over')" ondrop="onSlotDrop(event,'<?= $sp ?>')">
                  <?= tyre_slot_html($sp, $fitted[$sp] ?? null, $vehicle['current_mileage']) ?>
                </div>
              <?php endfor; ?>
            </div>
            <?php if (can_edit()): ?>
            <div class="flex gap-2 no-print" style="margin-left:10px;">
              <button class="btn btn-outline btn-sm" onclick="adjustSpare(<?= $id ?>,-1)" title="Remove last spare slot">&minus; Spare</button>
              <button class="btn btn-outline btn-sm" onclick="adjustSpare(<?= $id ?>,1)" title="Add another spare slot">+ Spare</button>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <p class="text-muted" style="font-size:12px;margin-top:16px;text-align:center;">Green connector = drive axle. Drag a tyre between slots to rotate, or drag from stock to fit. Click a tyre for full details.</p>
      </div>

      <div class="tl-card">
        <div class="tl-card-head"><h3>Available Stock</h3><a href="stock.php" style="font-size:12px;">Manage stock →</a></div>
        <div id="stockList"><?php include __DIR__ . '/includes/stock_list_partial.php'; ?></div>
        <button class="btn btn-outline btn-sm" style="width:100%;margin-top:6px;" onclick="TL.openModal('addStockModal')">+ Add New Stock Tyre</button>
      </div>
    </div>
  </div>

  <div id="tab-inspections" class="tab-pane" style="display:none;">
    <div class="tl-card">
      <?php
      $ins = db()->prepare("SELECT i.*, u.full_name FROM inspections i JOIN users u ON u.id=i.inspector_id WHERE i.vehicle_id=? ORDER BY i.inspection_date DESC");
      $ins->execute([$id]); $insList = $ins->fetchAll();
      ?>
      <table class="tl-table">
        <thead><tr><th>Date</th><th>Odometer</th><th>Inspector</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($insList as $row): ?>
          <tr><td><?= fmt_date($row['inspection_date'],'d M Y H:i') ?></td><td class="mono"><?= number_format($row['odometer']) ?> km</td>
          <td><?= e($row['full_name']) ?></td><td><span class="badge <?= badge_class($row['status']) ?>"><?= $row['status'] ?></span></td>
          <td><a class="btn btn-outline btn-sm" href="report_inspection.php?id=<?= $row['id'] ?>">View</a></td></tr>
        <?php endforeach; if (!$insList): ?><tr><td colspan="5" class="text-muted" style="text-align:center;padding:20px;">No inspections recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="tab-history" class="tab-pane" style="display:none;">
    <div class="tl-card">
      <?php
      $mv = db()->prepare("SELECT m.*, t.serial_number, t.brand FROM tyre_movements m JOIN tyres t ON t.id=m.tyre_id
        WHERE m.from_vehicle_id=? OR m.to_vehicle_id=? ORDER BY m.movement_date DESC, m.id DESC");
      $mv->execute([$id,$id]); $mvList = $mv->fetchAll();
      ?>
      <table class="tl-table">
        <thead><tr><th>Date</th><th>Type</th><th>Tyre</th><th>From</th><th>To</th><th>Odometer</th><th>Reason</th></tr></thead>
        <tbody>
        <?php foreach ($mvList as $m): ?>
          <tr><td><?= fmt_date($m['movement_date']) ?></td><td><span class="badge badge-info"><?= $m['movement_type'] ?></span></td>
          <td><?= e($m['brand'].' · '.$m['serial_number']) ?></td>
          <td><?= e(($m['from_vehicle_id']==$id? $m['from_position']: ($m['from_vehicle_id']?'other vehicle':'—'))) ?></td>
          <td><?= e(($m['to_vehicle_id']==$id? $m['to_position']: ($m['to_vehicle_id']?'other vehicle':'—'))) ?></td>
          <td class="mono"><?= $m['odometer']?number_format($m['odometer']):'—' ?></td><td><?= e($m['reason']) ?></td></tr>
        <?php endforeach; if (!$mvList): ?><tr><td colspan="7" class="text-muted" style="text-align:center;padding:20px;">No movements recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="tab-alerts" class="tab-pane" style="display:none;">
    <div class="tl-card">
      <?php
      $al = db()->prepare("SELECT * FROM alerts WHERE vehicle_id=? ORDER BY created_at DESC");
      $al->execute([$id]); $alList = $al->fetchAll();
      foreach ($alList as $a): ?>
        <div class="alert-item p-<?= e($a['priority']) ?>"><div class="dot"></div><div><div class="msg"><?= e($a['message']) ?></div><div class="meta"><?= fmt_date($a['created_at'],'d M Y H:i') ?></div></div></div>
      <?php endforeach; if (!$alList): ?><p class="text-muted" style="padding:10px;">No alerts for this vehicle.</p><?php endif; ?>
    </div>
  </div>
</div>
</div>

<!-- Tyre Detail Modal -->
<div class="tl-modal-backdrop" id="tyreDetailModal">
  <div class="tl-modal" id="tyreDetailContent" style="max-width:560px;"></div>
</div>

<!-- Add Stock Tyre Modal -->
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
          <div><label class="form-label">Size *</label><input class="form-control" name="size" required placeholder="e.g. R15"></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">New Tread Depth (mm)</label><input class="form-control" name="new_tread_mm" type="number" step="0.1" value="8.0"></div>
          <div><label class="form-label">Recommended PSI</label><input class="form-control" name="recommended_psi" type="number" step="0.1" value="32"></div>
        </div>
        <div class="form-row">
          <div><label class="form-label">Purchase Price</label><input class="form-control" name="purchase_price" type="number" step="0.01"></div>
          <div><label class="form-label">Expected Mileage (km)</label><input class="form-control" name="expected_mileage" value="60000"></div>
          <div><label class="form-label">Storage Location</label><input class="form-control" name="storage_location" value="<?= e($vehicle['branch_name']) ?> Store"></div>
        </div>
      </form>
    </div>
    <div class="tl-modal-foot"><button class="btn btn-outline" onclick="TL.closeModal('addStockModal')">Cancel</button>
      <button class="btn btn-accent" onclick="submitStock()">Add to Stock</button></div>
  </div>
</div>

<?php $extraScript = "<script src='assets/js/vehicle_profile.js'></script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

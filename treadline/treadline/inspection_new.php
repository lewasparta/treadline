<?php
require_once __DIR__ . '/config/config.php';
require_role(['ADMIN','FLEET_MANAGER','TYRE_INSPECTOR','SUPERVISOR']);
$pageTitle = 'New Inspection';

$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
$vehicle = null; $fitted = [];
if ($vehicleId) {
    $stmt = db()->prepare("SELECT v.*, b.name AS branch_name FROM vehicles v LEFT JOIN branches b ON b.id=v.branch_id WHERE v.id=?");
    $stmt->execute([$vehicleId]);
    $vehicle = $stmt->fetch();
    if ($vehicle) {
        $tt = db()->prepare("SELECT * FROM tyres WHERE current_vehicle_id=? AND status='IN_SERVICE' ORDER BY current_position");
        $tt->execute([$vehicleId]);
        $fitted = $tt->fetchAll();
    }
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>New Inspection</h1><div class="sub">Step-by-step tyre condition capture</div></div></div>

  <?php if (!$vehicle): ?>
    <div class="tl-card" style="max-width:520px;">
      <h3 style="margin-top:0;">Step 1 — Select Vehicle</h3>
      <form method="get" action="inspection_new.php" style="margin-top:14px;">
        <label class="form-label">Search plate number</label>
        <input class="form-control" name="plate" placeholder="e.g. KBL353M" style="margin-bottom:12px;">
        <button class="btn btn-accent" type="submit" formaction="javascript:void(0)" onclick="findVehicle()">Continue</button>
        <button type="button" class="btn btn-outline" onclick="location.href='scan.php?next=inspection'">📷 Scan Vehicle/Tyre QR</button>
      </form>
      <div id="searchResults" style="margin-top:14px;"></div>
    </div>
    <script>
    async function findVehicle(){
      const plate = document.querySelector('[name=plate]').value;
      const res = await fetch('api/vehicle_lookup.php?q='+encodeURIComponent(plate));
      const data = await res.json();
      const box = document.getElementById('searchResults');
      if (data.vehicles && data.vehicles.length) {
        box.innerHTML = data.vehicles.map(v => `<a href="inspection_new.php?vehicle_id=${v.id}" class="tl-link" style="display:block;padding:10px;border:1px solid #eee;border-radius:8px;margin-bottom:6px;">${v.plate_number} — ${v.make} ${v.model}</a>`).join('');
      } else { box.innerHTML = '<p class="text-muted">No matching vehicle. <a href="vehicles.php">Add a new vehicle</a>.</p>'; }
    }
    </script>
  <?php else: ?>
    <form id="inspectionForm">
      <input type="hidden" name="vehicle_id" value="<?= $vehicleId ?>">
      <div class="tl-card" style="margin-bottom:18px;">
        <h3 style="margin-top:0;">Step 2 — Confirm Vehicle & Odometer</h3>
        <div class="form-row">
          <div><label class="form-label">Vehicle</label><input class="form-control" value="<?= e($vehicle['plate_number'].' — '.$vehicle['make'].' '.$vehicle['model']) ?>" disabled></div>
          <div><label class="form-label">Branch</label><input class="form-control" value="<?= e($vehicle['branch_name']) ?>" disabled></div>
          <div><label class="form-label">Current Odometer (KM) *</label><input class="form-control" name="odometer" required value="<?= (int)$vehicle['current_mileage'] ?>"></div>
        </div>
      </div>

      <?php if (!$fitted): ?>
        <div class="tl-card"><p class="text-muted">This vehicle has no tyres fitted yet. <a href="vehicle_profile.php?id=<?= $vehicleId ?>">Go fit tyres first</a>.</p></div>
      <?php endif; ?>

      <?php foreach ($fitted as $i => $t): ?>
      <div class="tl-card" style="margin-bottom:18px;" data-tyre-id="<?= $t['id'] ?>">
        <h3 style="margin-top:0;">Position <?= e($t['current_position']) ?> — <?= e($t['brand'].' '.$t['size']) ?> <span class="text-muted" style="font-size:12px;font-weight:400;">(<?= e($t['serial_number']) ?>)</span></h3>

        <div class="checklist-group">
          <div class="title">TREAD DEPTH (mm)</div>
          <div class="form-row">
            <div><label class="form-label">Inner</label><input class="form-control tread-inner" type="number" step="0.1" value="<?= $t['current_tread_mm'] ?>"></div>
            <div><label class="form-label">Center</label><input class="form-control tread-center" type="number" step="0.1" value="<?= $t['current_tread_mm'] ?>"></div>
            <div><label class="form-label">Outer</label><input class="form-control tread-outer" type="number" step="0.1" value="<?= $t['current_tread_mm'] ?>"></div>
          </div>
        </div>

        <div class="checklist-group">
          <div class="title">TYRE PRESSURE</div>
          <div class="form-row">
            <div><label class="form-label">Recommended</label><input class="form-control" value="<?= $t['recommended_psi'] ?> PSI" disabled></div>
            <div><label class="form-label">Actual PSI *</label><input class="form-control psi-actual" type="number" step="0.1" value="<?= $t['current_psi'] ?>"></div>
          </div>
        </div>

        <div class="checklist-group">
          <div class="title">SIDEWALL CONDITION</div>
          <div class="chip-options sidewall-group">
            <?php foreach (['Good','Cut','Crack','Bulge','Sidewall damage'] as $o): ?>
              <label class="chip <?= $o==='Good'?'active':'' ?>" onclick="chipToggle(this)"><input type="radio" name="sw_<?= $i ?>" value="<?= $o ?>" <?= $o==='Good'?'checked':'' ?>><?= $o ?></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="checklist-group">
          <div class="title">TREAD CONDITION</div>
          <div class="chip-options tread-group">
            <?php foreach (['Good','Uneven wear','Centre wear','Shoulder wear','Inner wear','Outer wear','Cupping','Feathering','Chunking','Embedded object','Puncture'] as $o): ?>
              <label class="chip <?= $o==='Good'?'active':'' ?>" onclick="chipToggle(this)"><input type="radio" name="tc_<?= $i ?>" value="<?= $o ?>" <?= $o==='Good'?'checked':'' ?>><?= $o ?></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="checklist-group">
          <div class="title">OTHER CHECKS</div>
          <div class="chip-options" data-multi="1">
            <label class="chip active valve-ok" onclick="chipToggle(this,'multi')"><input type="checkbox" checked>Valve OK</label>
            <label class="chip active rim-ok" onclick="chipToggle(this,'multi')"><input type="checkbox" checked>Rim OK</label>
            <label class="chip active nuts-ok" onclick="chipToggle(this,'multi')"><input type="checkbox" checked>Nuts OK</label>
            <label class="chip foreign-object" onclick="chipToggle(this,'multi')"><input type="checkbox">Foreign Object Found</label>
          </div>
        </div>

        <div class="checklist-group">
          <div class="title">PHOTOS (optional)</div>
          <input type="file" class="form-control tyre-photos" accept="image/*" capture="environment" multiple>
        </div>

        <label class="form-label">Comments</label>
        <textarea class="form-control comments" rows="2" placeholder="Any additional notes about this tyre..."></textarea>
      </div>
      <?php endforeach; ?>

      <?php if ($fitted): ?>
      <div class="tl-card">
        <label class="form-label">Inspector Notes (overall)</label>
        <textarea class="form-control" id="overallNotes" rows="2"></textarea>
        <button type="button" class="btn btn-accent" style="margin-top:14px;" onclick="submitInspection()">✔ Complete Inspection</button>
      </div>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</div>
</div>
<?php $extraScript = "<script src='assets/js/inspection.js'></script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

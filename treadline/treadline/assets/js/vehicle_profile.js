/* TREADLINE - vehicle profile: tabs, drag & drop tyre map, tyre detail modal */

function showTab(e, name) {
  e.preventDefault();
  document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
  document.querySelectorAll('.tab-link').forEach(a => a.classList.remove('active'));
  document.getElementById('tab-' + name).style.display = '';
  e.target.classList.add('active');
}

async function adjustSpare(vehicleId, delta) {
  const r = await TL.post('api/spare_adjust.php', { vehicle_id: vehicleId, delta });
  if (r.ok) { setTimeout(() => location.reload(), 300); }
}

let dragPayload = null;

function onSlotDragStart(ev, fromPos, tyreId) {
  dragPayload = { type: 'slot', fromPos, tyreId };
  ev.dataTransfer.effectAllowed = 'move';
}
function onStockDragStart(ev, tyreId) {
  dragPayload = { type: 'stock', tyreId };
  ev.dataTransfer.effectAllowed = 'move';
}

async function onSlotDrop(ev, toPos) {
  ev.preventDefault();
  ev.currentTarget.classList.remove('drag-over');
  if (!dragPayload) return;
  const vehicleId = document.getElementById('tyreMap').dataset.vehicleId;
  const odo = document.getElementById('tyreMap').dataset.odo;

  if (dragPayload.type === 'stock') {
    const r = await TL.post('api/tyre_install.php', { tyre_id: dragPayload.tyreId, vehicle_id: vehicleId, position: toPos, odometer: odo });
    if (r.ok) { TL.toast('Tyre fitted to ' + toPos, 'success'); setTimeout(() => location.reload(), 500); }
  } else if (dragPayload.type === 'slot') {
    if (dragPayload.fromPos === toPos) return;
    const r = await TL.post('api/tyre_rotate.php', {
      tyre_id: dragPayload.tyreId, vehicle_id: vehicleId,
      from_position: dragPayload.fromPos, to_position: toPos, odometer: odo
    });
    if (r.ok) { TL.toast('Rotated ' + dragPayload.fromPos + ' → ' + toPos, 'success'); setTimeout(() => location.reload(), 500); }
  }
  dragPayload = null;
}

async function updateMileage(vehicleId) {
  const mileage = document.getElementById('mileageInput').value.replace(/,/g, '');
  const reading_date = document.getElementById('mileageDate').value;
  const r = await TL.post('api/mileage_update.php', { vehicle_id: vehicleId, mileage, reading_date });
  if (r.ok) { TL.toast('Mileage updated', 'success'); setTimeout(() => location.reload(), 500); }
}

async function saveDriverAndTowing(vehicleId) {
  const driver_name = document.getElementById('driverInput').value;
  const towed_by_vehicle_id = document.getElementById('towedByInput').value;
  const r = await TL.post('api/vehicle_quick_update.php', { id: vehicleId, driver_name, towed_by_vehicle_id });
  if (r.ok) { TL.toast('Saved', 'success'); setTimeout(() => location.reload(), 500); }
}

async function submitStock() {
  const f = document.getElementById('stockForm');
  const data = Object.fromEntries(new FormData(f).entries());
  const r = await TL.post('api/stock_add.php', data);
  if (r.ok) { TL.toast('Added to stock', 'success'); TL.closeModal('addStockModal'); setTimeout(() => location.reload(), 500); }
}

async function openTyreDetail(tyreId) {
  TL.openModal('tyreDetailModal');
  const el = document.getElementById('tyreDetailContent');
  el.innerHTML = '<div class="tl-modal-body">Loading…</div>';
  const res = await fetch('api/tyre_detail.php?id=' + tyreId);
  const data = await res.json();
  if (data.error) { el.innerHTML = '<div class="tl-modal-body">' + data.error + '</div>'; return; }
  const t = data.tyre;
  el.innerHTML = `
    <div class="tl-modal-head"><h3>${t.position || ''} · ${t.brand} ${t.size}</h3>
      <div class="flex gap-2">
        <button class="btn btn-outline btn-sm" onclick="openTyreEditForm(${t.id})">✎ Edit Details</button>
        <button class="tl-modal-close" onclick="TL.closeModal('tyreDetailModal')">&times;</button>
      </div>
    </div>
    <div class="tl-modal-body" id="tyreDetailBody">
      <div class="form-row">
        <div><span class="text-muted" style="font-size:11.5px;">SERIAL</span><br><b>${t.serial_number}</b></div>
        <div><span class="text-muted" style="font-size:11.5px;">BRANDING CODE</span><br><b>${t.branding_code || '—'}</b></div>
        <div><span class="text-muted" style="font-size:11.5px;">TREAD</span><br><b style="color:${data.tread_color}">${t.current_tread_mm} mm</b> <span class="badge ${data.tread_badge}">${data.tread_status}</span></div>
      </div>
      <div class="form-row">
        <div><span class="text-muted" style="font-size:11.5px;">PSI</span><br><b>${t.current_psi ?? '—'}</b> <span class="badge ${data.psi_badge}">${data.psi_status}</span></div>
        <div><span class="text-muted" style="font-size:11.5px;">TYRE MILEAGE</span><br><b>${Number(data.tyre_mileage).toLocaleString()} km</b></div>
        <div><span class="text-muted" style="font-size:11.5px;">AGE</span><br><b>${data.age_days} days</b></div>
      </div>
      <div class="form-row">
        <div><span class="text-muted" style="font-size:11.5px;">HEALTH SCORE</span><br><b style="color:${data.health_color}">${data.health_score}/100 · ${data.health_rating}</b></div>
        <div><span class="text-muted" style="font-size:11.5px;">REPAIRS</span><br><b>${t.total_repairs}</b></div>
        <div><span class="text-muted" style="font-size:11.5px;">STATUS</span><br><b>${t.status}</b></div>
      </div>
      <hr style="border:none;border-top:1px solid #eee;margin:14px 0;">
      <div class="form-row">
        <div><label class="form-label">Damage / Repair note</label><input class="form-control" id="repairNote" placeholder="e.g. Sidewall puncture"></div>
        <div><label class="form-label">Repair Cost</label><input class="form-control" id="repairCost" type="number" step="0.01"></div>
      </div>
      <button class="btn btn-outline btn-sm" onclick="doRepair(${t.id})">Log Repair</button>
      <hr style="border:none;border-top:1px solid #eee;margin:14px 0;">
      <label class="form-label">Remove tyre — reason</label>
      <select class="form-control" id="removeReason" style="margin-bottom:8px;">
        <option value="NORMAL_WEAR">Normal wear</option><option value="DAMAGE">Damage</option><option value="PUNCTURE">Puncture</option>
        <option value="SIDEWALL_FAILURE">Sidewall failure</option><option value="UNEVEN_WEAR">Uneven wear</option>
        <option value="ACCIDENT">Accident</option><option value="DEFECT">Defect</option><option value="END_OF_LIFE">End of life</option><option value="OTHER">Other</option>
      </select>
      <button class="btn btn-danger btn-sm" onclick="doRemove(${t.id})">Remove From Vehicle</button>
      <hr style="border:none;border-top:1px solid #eee;margin:14px 0;">
      <button class="btn btn-danger btn-sm" onclick="deleteTyre(${t.id}, '${t.serial_number}')">🗑 Delete Tyre Record</button>
    </div>`;
}

async function doRepair(tyreId) {
  const damage = document.getElementById('repairNote').value;
  const cost = document.getElementById('repairCost').value || 0;
  const r = await TL.post('api/tyre_repair.php', { tyre_id: tyreId, damage, cost });
  if (r.ok) { TL.toast('Repair logged', 'success'); TL.closeModal('tyreDetailModal'); setTimeout(() => location.reload(), 500); }
}

async function openTyreEditForm(tyreId) {
  const body = document.getElementById('tyreDetailBody');
  body.innerHTML = 'Loading…';
  const res = await fetch('api/tyre_get.php?id=' + tyreId);
  const data = await res.json();
  if (data.error) { body.innerHTML = data.error; return; }
  const t = data.tyre;
  body.innerHTML = `
    <form id="tyreEditForm">
      <input type="hidden" id="te_id" value="${t.id}">
      <div class="form-row">
        <div><label class="form-label">Brand *</label><input class="form-control" id="te_brand" value="${t.brand || ''}" required></div>
        <div><label class="form-label">Model</label><input class="form-control" id="te_model" value="${t.model || ''}"></div>
        <div><label class="form-label">Size *</label><input class="form-control" id="te_size" value="${t.size || ''}" required></div>
      </div>
      <div class="form-row">
        <div><label class="form-label">Branding Code</label><input class="form-control" id="te_branding_code" value="${t.branding_code || ''}"></div>
        <div><label class="form-label">Recommended PSI</label><input class="form-control" id="te_recommended_psi" type="number" step="0.1" value="${t.recommended_psi}"></div>
        <div><label class="form-label">New Tread (mm)</label><input class="form-control" id="te_new_tread_mm" type="number" step="0.1" value="${t.new_tread_mm}"></div>
      </div>
      <div class="form-row">
        <div><label class="form-label">Purchase Price</label><input class="form-control" id="te_purchase_price" type="number" step="0.01" value="${t.purchase_price}"></div>
        <div><label class="form-label">Storage Location</label><input class="form-control" id="te_storage_location" value="${t.storage_location || ''}"></div>
      </div>
      <div class="flex gap-2" style="margin-top:6px;">
        <button type="button" class="btn btn-accent btn-sm" onclick="saveTyreEdit()">Save Changes</button>
        <button type="button" class="btn btn-outline btn-sm" onclick="openTyreDetail(${t.id})">Cancel</button>
      </div>
    </form>`;
}

async function saveTyreEdit() {
  const id = document.getElementById('te_id').value;
  const data = {
    id,
    brand: document.getElementById('te_brand').value,
    model: document.getElementById('te_model').value,
    size: document.getElementById('te_size').value,
    branding_code: document.getElementById('te_branding_code').value,
    recommended_psi: document.getElementById('te_recommended_psi').value,
    new_tread_mm: document.getElementById('te_new_tread_mm').value,
    purchase_price: document.getElementById('te_purchase_price').value,
    storage_location: document.getElementById('te_storage_location').value,
  };
  const r = await TL.post('api/tyre_update.php', data);
  if (r.ok) { TL.toast('Tyre updated', 'success'); openTyreDetail(id); }
}

async function doRemove(tyreId) {
  const reason = document.getElementById('removeReason').value;
  if (!confirm('Remove this tyre from the vehicle? This will end its service on this position.')) return;
  const r = await TL.post('api/tyre_remove.php', { tyre_id: tyreId, reason });
  if (r.ok) { TL.toast('Tyre removed', 'success'); TL.closeModal('tyreDetailModal'); setTimeout(() => location.reload(), 500); }
}

async function deleteVehicle(vehicleId, plate) {
  if (!confirm('Delete vehicle "' + plate + '"? This cannot be undone.')) return;
  const r = await TL.post('api/vehicle_delete.php', { id: vehicleId });
  if (r.ok) { TL.toast(r.message || 'Done', 'success'); setTimeout(() => location.href = 'vehicles.php', 800); }
}

async function deleteTyre(tyreId, serial) {
  if (!confirm('Delete tyre "' + serial + '"? If it has no recorded history it will be permanently deleted; otherwise it will be marked SCRAP.')) return;
  const r = await TL.post('api/tyre_delete.php', { id: tyreId });
  if (r.ok) { TL.toast(r.message || 'Done', 'success'); TL.closeModal('tyreDetailModal'); setTimeout(() => location.reload(), 800); }
}

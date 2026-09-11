async function submitInspection() {
  const form = document.getElementById('inspectionForm');
  const vehicleId = form.vehicle_id.value;
  const odometer = form.odometer.value;
  const cards = document.querySelectorAll('[data-tyre-id]');
  const items = [];

  cards.forEach(card => {
    const tyreId = card.dataset.tyreId;
    const inner = parseFloat(card.querySelector('.tread-inner').value) || 0;
    const center = parseFloat(card.querySelector('.tread-center').value) || 0;
    const outer = parseFloat(card.querySelector('.tread-outer').value) || 0;
    const psi = parseFloat(card.querySelector('.psi-actual').value) || 0;
    const sidewall = card.querySelector('.sidewall-group .chip.active')?.textContent.trim() || 'Good';
    const treadCond = card.querySelector('.tread-group .chip.active')?.textContent.trim() || 'Good';
    const valve_ok = card.querySelector('.valve-ok').classList.contains('active') ? 1 : 0;
    const rim_ok = card.querySelector('.rim-ok').classList.contains('active') ? 1 : 0;
    const nuts_ok = card.querySelector('.nuts-ok').classList.contains('active') ? 1 : 0;
    const foreign_object = card.querySelector('.foreign-object').classList.contains('active') ? 1 : 0;
    const comments = card.querySelector('.comments').value;
    items.push({ tyre_id: tyreId, tread_inner: inner, tread_center: center, tread_outer: outer,
      psi_actual: psi, sidewall_condition: sidewall, tread_condition: treadCond,
      valve_ok, rim_ok, nuts_ok, foreign_object, comments });
  });

  if (!items.length) { TL.toast('No tyres to inspect on this vehicle', 'error'); return; }

  const r = await TL.post('api/inspection_save.php', {
    vehicle_id: vehicleId, odometer, notes: document.getElementById('overallNotes')?.value || '', items
  });
  if (r.ok) {
    TL.toast('Inspection completed', 'success');
    setTimeout(() => location.href = 'report_inspection.php?id=' + r.id, 700);
  }
}

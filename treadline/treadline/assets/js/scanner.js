let stream = null, scanning = false;

async function startScan() {
  const video = document.getElementById('video');
  const status = document.getElementById('scanStatus');
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    video.srcObject = stream;
    await video.play();
    scanning = true;
    status.textContent = 'Scanning… point the camera at a QR code or barcode label.';
    requestAnimationFrame(tick);
  } catch (e) {
    status.textContent = 'Camera unavailable (' + e.message + '). Use manual entry instead.';
  }
}

function stopScan() {
  scanning = false;
  if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  document.getElementById('scanStatus').textContent = 'Stopped.';
}

function tick() {
  if (!scanning) return;
  const video = document.getElementById('video');
  const canvas = document.getElementById('canvas');
  if (video.readyState === video.HAVE_ENOUGH_DATA) {
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height);
    if (code && code.data) {
      document.getElementById('scanStatus').textContent = 'Found: ' + code.data;
      stopScan();
      lookupCode(code.data);
      return;
    }
  }
  requestAnimationFrame(tick);
}

async function lookupCode(code) {
  code = (code || '').trim();
  if (!code) return;
  const box = document.getElementById('lookupResult');
  box.innerHTML = 'Looking up…';
  const res = await fetch('api/scan_lookup.php?code=' + encodeURIComponent(code));
  const data = await res.json();
  if (data.tyre) {
    const t = data.tyre;
    box.innerHTML = `<div class="tl-card" style="background:#f8fafc;">
      <b>${t.brand} ${t.size}</b> · ${t.serial_number}<br>
      <span class="text-muted">Status: ${t.status}${t.plate_number ? ' · on ' + t.plate_number + ' (' + t.current_position + ')' : ''}</span><br>
      <a class="btn btn-outline btn-sm" style="margin-top:8px;" href="${t.current_vehicle_id ? 'vehicle_profile.php?id='+t.current_vehicle_id : 'stock.php'}">Open</a>
    </div>`;
  } else if (data.vehicle) {
    const v = data.vehicle;
    box.innerHTML = `<div class="tl-card" style="background:#f8fafc;">
      <b>${v.plate_number}</b> — ${v.make} ${v.model}<br>
      <a class="btn btn-accent btn-sm" style="margin-top:8px;" href="vehicle_profile.php?id=${v.id}">Open Vehicle</a>
      <a class="btn btn-outline btn-sm" style="margin-top:8px;" href="inspection_new.php?vehicle_id=${v.id}">Start Inspection</a>
    </div>`;
  } else {
    box.innerHTML = `<div class="tl-card" style="background:#fdf1de;">
      <b>TYRE / VEHICLE NOT REGISTERED</b><br><span class="text-muted">Code: ${code}</span><br>
      <a class="btn btn-accent btn-sm" style="margin-top:8px;" href="stock.php">+ Register New Tyre</a>
      <a class="btn btn-outline btn-sm" style="margin-top:8px;" href="vehicles.php">+ Register New Vehicle</a>
    </div>`;
  }
}

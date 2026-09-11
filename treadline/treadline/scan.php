<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Scan Tyre / Vehicle';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Scan Tyre / Vehicle</h1><div class="sub">Point your camera at a QR code, barcode, or Code128/Code39 label</div></div></div>

  <div class="form-row" style="grid-template-columns:1fr 1fr;align-items:start;">
    <div class="tl-card">
      <video id="video" style="width:100%;border-radius:8px;background:#000;" playsinline></video>
      <canvas id="canvas" style="display:none;"></canvas>
      <div class="flex gap-2" style="margin-top:12px;">
        <button class="btn btn-accent" id="startBtn" onclick="startScan()">📷 Start Camera</button>
        <button class="btn btn-outline" onclick="stopScan()">Stop</button>
      </div>
      <p id="scanStatus" class="text-muted" style="margin-top:10px;font-size:12.5px;"></p>
    </div>
    <div class="tl-card">
      <h3 style="margin-top:0;">Manual Entry (backup)</h3>
      <label class="form-label">Tyre Serial Number or Vehicle Plate</label>
      <input class="form-control" id="manualCode" placeholder="e.g. SLN-0001-FL or KBL353M" style="margin-bottom:10px;">
      <button class="btn btn-outline" onclick="lookupCode(document.getElementById('manualCode').value)">Look Up</button>
      <div id="lookupResult" style="margin-top:16px;"></div>
    </div>
  </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<?php $extraScript = "<script src='assets/js/scanner.js'></script>"; ?>
<?php include __DIR__ . '/includes/foot.php'; ?>

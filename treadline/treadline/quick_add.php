<?php
require_once __DIR__ . '/config/config.php';
require_login();
$pageTitle = 'Quick Add';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Quick Add</h1><div class="sub">Jump straight to a common task</div></div></div>
  <div class="form-row" style="grid-template-columns:repeat(4,1fr);">
    <div class="tl-card" style="text-align:center;cursor:pointer;" onclick="location.href='vehicles.php'"><div style="font-size:26px;">🚚</div><b>New Vehicle</b></div>
    <div class="tl-card" style="text-align:center;cursor:pointer;" onclick="location.href='stock.php'"><div style="font-size:26px;">🛞</div><b>New Tyre / Stock</b></div>
    <div class="tl-card" style="text-align:center;cursor:pointer;" onclick="location.href='inspection_new.php'"><div style="font-size:26px;">📋</div><b>New Inspection</b></div>
    <div class="tl-card" style="text-align:center;cursor:pointer;" onclick="location.href='scan.php'"><div style="font-size:26px;">📷</div><b>Scan Tyre</b></div>
    <div class="tl-card" style="text-align:center;cursor:pointer;" onclick="location.href='op_transfer.php'"><div style="font-size:26px;">🔁</div><b>Transfer Tyre</b></div>
    <div class="tl-card" style="text-align:center;cursor:pointer;" onclick="location.href='op_retread.php'"><div style="font-size:26px;">♻</div><b>Retread Tyre</b></div>
    <div class="tl-card" style="text-align:center;cursor:pointer;" onclick="location.href='suppliers.php'"><div style="font-size:26px;">🏭</div><b>New Supplier</b></div>
    <div class="tl-card" style="text-align:center;cursor:pointer;" onclick="location.href='reports.php'"><div style="font-size:26px;">📄</div><b>Generate Report</b></div>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

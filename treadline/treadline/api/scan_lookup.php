<?php
require_once __DIR__ . '/_bootstrap.php';
$code = trim($_GET['code'] ?? '');
if ($code === '') respond_json(['error' => 'No code provided']);

$stmt = db()->prepare("SELECT t.*, v.plate_number FROM tyres t LEFT JOIN vehicles v ON v.id=t.current_vehicle_id
  WHERE t.serial_number = ? OR t.branding_code = ? LIMIT 1");
$stmt->execute([$code, $code]);
$tyre = $stmt->fetch();
if ($tyre) respond_json(['tyre' => $tyre]);

$stmt = db()->prepare("SELECT * FROM vehicles WHERE plate_number = ? OR vin = ? LIMIT 1");
$stmt->execute([$code, $code]);
$vehicle = $stmt->fetch();
if ($vehicle) respond_json(['vehicle' => $vehicle]);

respond_json(['found' => false]);

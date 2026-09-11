<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
$stmt = db()->prepare("SELECT current_position, serial_number, branding_code, brand, size, new_tread_mm, current_tread_mm, current_psi, recommended_psi, status
  FROM tyres WHERE current_vehicle_id=? AND status='IN_SERVICE' ORDER BY current_position");
$stmt->execute([$vehicleId]);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="manifest_vehicle_' . $vehicleId . '_' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Position','Serial','Branding Code','Brand','Size','New Tread (mm)','Current Tread (mm)','PSI','Recommended PSI','Status']);
foreach ($stmt as $r) fputcsv($out, $r);
fclose($out);

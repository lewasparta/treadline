<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$stmt = db()->query("SELECT v.plate_number, v.fleet_number, vt.name AS type, v.make, v.model, v.year, b.name AS branch,
  v.driver_name, v.current_mileage, v.status FROM vehicles v
  LEFT JOIN branches b ON b.id=v.branch_id LEFT JOIN vehicle_types vt ON vt.id=v.vehicle_type_id
  WHERE v.status <> 'ARCHIVED' ORDER BY v.plate_number");

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="treadline_vehicles_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Plate Number','Fleet Number','Type','Make','Model','Year','Branch','Driver','Mileage','Status']);
foreach ($stmt as $r) fputcsv($out, $r);
fclose($out);

<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$stmt = db()->query("SELECT i.inspection_date, v.plate_number, i.odometer, u.full_name AS inspector, i.status
  FROM inspections i JOIN vehicles v ON v.id=i.vehicle_id JOIN users u ON u.id=i.inspector_id ORDER BY i.inspection_date DESC");

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="treadline_inspections_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Date','Vehicle','Odometer','Inspector','Status']);
foreach ($stmt as $r) fputcsv($out, $r);
fclose($out);

<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$where = ['1=1']; $params = [];
if (!empty($_GET['status'])) { $where[] = 't.status = ?'; $params[] = $_GET['status']; }
$stmt = db()->prepare("SELECT t.serial_number, t.branding_code, t.brand, t.model, t.size, t.status, t.current_tread_mm,
  t.current_psi, t.recommended_psi, v.plate_number, t.current_position, t.purchase_price, t.date_added
  FROM tyres t LEFT JOIN vehicles v ON v.id=t.current_vehicle_id WHERE " . implode(' AND ', $where) . " ORDER BY t.serial_number");
$stmt->execute($params);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="treadline_tyres_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Serial Number','Branding Code','Brand','Model','Size','Status','Current Tread (mm)','Current PSI','Recommended PSI','Vehicle','Position','Purchase Price','Date Added']);
foreach ($stmt as $r) fputcsv($out, $r);
fclose($out);

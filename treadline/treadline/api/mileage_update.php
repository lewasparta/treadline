<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$vehicleId = (int)($input['vehicle_id'] ?? 0);
$mileage = (int)($input['mileage'] ?? 0);
$date = $input['reading_date'] ?? date('Y-m-d');

if (!$vehicleId || $mileage <= 0) respond_json(['error' => 'Valid vehicle and mileage are required'], 422);

$cur = db()->prepare("SELECT current_mileage FROM vehicles WHERE id=?");
$cur->execute([$vehicleId]);
$prev = $cur->fetch();
if (!$prev) respond_json(['error' => 'Vehicle not found'], 404);
if ($mileage < (int)$prev['current_mileage']) {
    respond_json(['error' => 'New mileage cannot be lower than the current recorded mileage (' . number_format($prev['current_mileage']) . ' km)'], 422);
}

db()->prepare("UPDATE vehicles SET current_mileage=?, mileage_reading_date=? WHERE id=?")->execute([$mileage, $date, $vehicleId]);
db()->prepare("INSERT INTO mileage_log (vehicle_id, mileage, reading_date, recorded_by) VALUES (?,?,?,?)")
    ->execute([$vehicleId, $mileage, $date, $user['id']]);

log_action('MILEAGE_UPDATE', 'vehicle', $vehicleId, "New mileage: $mileage");
respond_json(['message' => 'Mileage updated']);

<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$tyreId = (int)($input['tyre_id'] ?? 0);
$damage = trim($input['damage'] ?? '');
$cost = (float)($input['cost'] ?? 0);
if (!$tyreId) respond_json(['error' => 'Tyre required'], 422);

$tyre = db()->prepare("SELECT t.*, v.current_mileage AS vm FROM tyres t LEFT JOIN vehicles v ON v.id=t.current_vehicle_id WHERE t.id=?");
$tyre->execute([$tyreId]);
$tyre = $tyre->fetch();
if (!$tyre) respond_json(['error' => 'Tyre not found'], 404);

db()->prepare("INSERT INTO tyre_repairs (tyre_id, vehicle_id, odometer, damage, repair_type, technician, cost, created_by)
    VALUES (?,?,?,?,?,?,?,?)")
    ->execute([$tyreId, $tyre['current_vehicle_id'], $tyre['vm'] ?? null, $damage, 'General repair', $user['full_name'], $cost, $user['id']]);

db()->prepare("UPDATE tyres SET total_repairs = total_repairs + 1 WHERE id=?")->execute([$tyreId]);

if ($tyre['total_repairs'] + 1 >= 3) {
    create_alert($tyre['current_vehicle_id'], $tyreId, 'REPEATED_REPAIRS', 'HIGH',
        "Tyre {$tyre['serial_number']} has been repaired " . ($tyre['total_repairs']+1) . " times — consider replacement.");
}

log_action('REPAIR', 'tyre', $tyreId, $damage);
respond_json(['message' => 'Repair logged']);

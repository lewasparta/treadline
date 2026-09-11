<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$serial = trim($input['serial_number'] ?? '');
$toVehicle = (int)($input['to_vehicle_id'] ?? 0);
$toPos = trim($input['to_position'] ?? '');
$reason = $input['reason'] ?? 'Transfer';

$tyre = db()->prepare("SELECT t.*, v.current_mileage AS vm FROM tyres t LEFT JOIN vehicles v ON v.id=t.current_vehicle_id WHERE t.serial_number=?");
$tyre->execute([$serial]);
$tyre = $tyre->fetch();
if (!$tyre) respond_json(['error' => 'Tyre with that serial number was not found'], 404);
if (!$toVehicle || !$toPos) respond_json(['error' => 'Destination vehicle and position are required'], 422);

$destVehicle = db()->prepare("SELECT current_mileage FROM vehicles WHERE id=?"); $destVehicle->execute([$toVehicle]); $destVehicle = $destVehicle->fetch();
if (!$destVehicle) respond_json(['error' => 'Destination vehicle not found'], 404);

$occupied = db()->prepare("SELECT id FROM tyres WHERE current_vehicle_id=? AND current_position=? AND status='IN_SERVICE'");
$occupied->execute([$toVehicle, $toPos]);
if ($occupied->fetch()) respond_json(['error' => "Position $toPos on the destination vehicle is already occupied"], 422);

db()->beginTransaction();
try {
    db()->prepare("INSERT INTO tyre_movements (tyre_id, movement_type, from_vehicle_id, from_position, to_vehicle_id, to_position, odometer, movement_date, reason, performed_by)
        VALUES (?, 'TRANSFER', ?, ?, ?, ?, ?, CURDATE(), ?, ?)")
        ->execute([$tyre['id'], $tyre['current_vehicle_id'], $tyre['current_position'], $toVehicle, $toPos, $destVehicle['current_mileage'], $reason, $user['id']]);

    db()->prepare("UPDATE tyres SET current_vehicle_id=?, current_position=?, install_odometer=?, status='IN_SERVICE' WHERE id=?")
        ->execute([$toVehicle, $toPos, $destVehicle['current_mileage'], $tyre['id']]);

    db()->commit();
    log_action('TRANSFER', 'tyre', $tyre['id'], "To vehicle $toVehicle position $toPos");
    respond_json(['message' => 'Tyre transferred']);
} catch (Exception $e) {
    db()->rollBack();
    respond_json(['error' => $e->getMessage()], 500);
}

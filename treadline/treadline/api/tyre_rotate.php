<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$tyreId = (int)($input['tyre_id'] ?? 0);
$vehicleId = (int)($input['vehicle_id'] ?? 0);
$fromPos = trim($input['from_position'] ?? '');
$toPos = trim($input['to_position'] ?? '');
$odometer = (int)($input['odometer'] ?? 0);

if (!$tyreId || !$vehicleId || !$fromPos || !$toPos) respond_json(['error' => 'Missing rotation details'], 422);

$destTyre = db()->prepare("SELECT * FROM tyres WHERE current_vehicle_id=? AND current_position=? AND status='IN_SERVICE' AND id<>?");
$destTyre->execute([$vehicleId, $toPos, $tyreId]);
$destTyre = $destTyre->fetch();

db()->beginTransaction();
try {
    if ($destTyre) {
        // swap
        db()->prepare("UPDATE tyres SET current_position=? WHERE id=?")->execute([$fromPos, $destTyre['id']]);
        db()->prepare("INSERT INTO tyre_movements (tyre_id, movement_type, from_vehicle_id, from_position, to_vehicle_id, to_position, odometer, movement_date, reason, performed_by)
            VALUES (?, 'ROTATE', ?, ?, ?, ?, ?, CURDATE(), 'Swapped during rotation', ?)")
            ->execute([$destTyre['id'], $vehicleId, $toPos, $vehicleId, $fromPos, $odometer, $user['id']]);
    }
    db()->prepare("UPDATE tyres SET current_position=? WHERE id=?")->execute([$toPos, $tyreId]);
    db()->prepare("INSERT INTO tyre_movements (tyre_id, movement_type, from_vehicle_id, from_position, to_vehicle_id, to_position, odometer, movement_date, reason, performed_by)
        VALUES (?, 'ROTATE', ?, ?, ?, ?, ?, CURDATE(), 'Manual rotation', ?)")
        ->execute([$tyreId, $vehicleId, $fromPos, $vehicleId, $toPos, $odometer, $user['id']]);

    db()->commit();
    log_action('ROTATE', 'tyre', $tyreId, "$fromPos -> $toPos");
    respond_json(['message' => 'Rotation complete']);
} catch (Exception $e) {
    db()->rollBack();
    respond_json(['error' => $e->getMessage()], 500);
}

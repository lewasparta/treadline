<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$tyreId = (int)($input['tyre_id'] ?? 0);
$vehicleId = (int)($input['vehicle_id'] ?? 0);
$position = trim($input['position'] ?? '');
$odometer = (int)($input['odometer'] ?? 0);

if (!$tyreId || !$vehicleId || !$position) respond_json(['error' => 'Missing tyre, vehicle or position'], 422);

$tyre = db()->prepare("SELECT * FROM tyres WHERE id=?");
$tyre->execute([$tyreId]);
$tyre = $tyre->fetch();
if (!$tyre) respond_json(['error' => 'Tyre not found'], 404);
if (!in_array($tyre['status'], ['STOCK','RETREAD'], true)) respond_json(['error' => 'This tyre is not available in stock'], 422);

$occupied = db()->prepare("SELECT id FROM tyres WHERE current_vehicle_id=? AND current_position=? AND status='IN_SERVICE'");
$occupied->execute([$vehicleId, $position]);
if ($occupied->fetch()) respond_json(['error' => "Position $position is already occupied. Remove or rotate the existing tyre first."], 422);

db()->beginTransaction();
try {
    db()->prepare("UPDATE tyres SET status='IN_SERVICE', current_vehicle_id=?, current_position=?, current_tread_mm=new_tread_mm,
        current_psi=recommended_psi, install_odometer=? WHERE id=?")
        ->execute([$vehicleId, $position, $odometer, $tyreId]);

    db()->prepare("INSERT INTO tyre_movements (tyre_id, movement_type, to_vehicle_id, to_position, odometer, movement_date, reason, performed_by)
        VALUES (?, 'INSTALL', ?, ?, ?, CURDATE(), 'Fitted from stock', ?)")
        ->execute([$tyreId, $vehicleId, $position, $odometer, $user['id']]);

    db()->commit();
    log_action('INSTALL', 'tyre', $tyreId, "To vehicle $vehicleId position $position");
    respond_json(['message' => 'Tyre installed']);
} catch (Exception $e) {
    db()->rollBack();
    respond_json(['error' => $e->getMessage()], 500);
}

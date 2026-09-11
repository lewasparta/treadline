<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN','FLEET_MANAGER','SUPERVISOR']);

$serial = trim($input['serial_number'] ?? '');
$tyre = db()->prepare("SELECT * FROM tyres WHERE serial_number=?"); $tyre->execute([$serial]); $tyre = $tyre->fetch();
if (!$tyre) respond_json(['error' => 'Tyre with that serial number was not found'], 404);

$treadAfter = (float)($input['tread_after_retread'] ?? 0);
if ($treadAfter <= 0) respond_json(['error' => 'Tread after retread is required'], 422);

db()->beginTransaction();
try {
    db()->prepare("INSERT INTO tyre_retreads (tyre_id, retread_number, retread_date, supplier_id, cost, tread_after_retread, mileage_before, created_by)
        VALUES (?,?,CURDATE(),?,?,?,?,?)")
        ->execute([$tyre['id'], $input['retread_number'] ?? null, (int)($input['supplier_id'] ?? 0) ?: null,
            (float)($input['cost'] ?? 0), $treadAfter, null, $user['id']]);

    db()->prepare("UPDATE tyres SET status='STOCK', type='RETREAD', current_tread_mm=?, new_tread_mm=?, total_retreads=total_retreads+1,
        current_vehicle_id=NULL, current_position=NULL, install_odometer=NULL WHERE id=?")
        ->execute([$treadAfter, $treadAfter, $tyre['id']]);

    db()->commit();
    log_action('RETREAD', 'tyre', $tyre['id']);
    respond_json(['message' => 'Retread logged']);
} catch (Exception $e) {
    db()->rollBack();
    respond_json(['error' => $e->getMessage()], 500);
}

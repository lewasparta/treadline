<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$tyreId = (int)($input['tyre_id'] ?? 0);
$reason = $input['reason'] ?? 'NORMAL_WEAR';
$notes = $input['condition_notes'] ?? '';

$tyre = db()->prepare("SELECT t.*, v.current_mileage AS vehicle_mileage FROM tyres t LEFT JOIN vehicles v ON v.id=t.current_vehicle_id WHERE t.id=?");
$tyre->execute([$tyreId]);
$tyre = $tyre->fetch();
if (!$tyre) respond_json(['error' => 'Tyre not found'], 404);
if ($tyre['status'] !== 'IN_SERVICE') respond_json(['error' => 'Tyre is not currently in service'], 422);

$totalMileage = $tyre['install_odometer'] ? max(0, $tyre['vehicle_mileage'] - $tyre['install_odometer']) : 0;

db()->beginTransaction();
try {
    db()->prepare("INSERT INTO tyre_removals (tyre_id, vehicle_id, position, removal_odometer, final_tread, final_psi, reason, condition_notes, total_mileage, total_repairs, cost, removed_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$tyreId, $tyre['current_vehicle_id'], $tyre['current_position'], $tyre['vehicle_mileage'],
            $tyre['current_tread_mm'], $tyre['current_psi'], $reason, $notes, $totalMileage, $tyre['total_repairs'], $tyre['purchase_price'], $user['id']]);

    db()->prepare("INSERT INTO tyre_movements (tyre_id, movement_type, from_vehicle_id, from_position, odometer, movement_date, reason, performed_by)
        VALUES (?, 'REMOVE', ?, ?, ?, CURDATE(), ?, ?)")
        ->execute([$tyreId, $tyre['current_vehicle_id'], $tyre['current_position'], $tyre['vehicle_mileage'], $reason, $user['id']]);

    $newStatus = ($reason === 'NORMAL_WEAR' && $tyre['current_tread_mm'] > 2) ? 'STOCK' : 'SCRAP';
    db()->prepare("UPDATE tyres SET status=?, current_vehicle_id=NULL, current_position=NULL, install_odometer=NULL WHERE id=?")
        ->execute([$newStatus, $tyreId]);

    db()->commit();
    log_action('REMOVE', 'tyre', $tyreId, "Reason: $reason");
    respond_json(['message' => 'Tyre removed']);
} catch (Exception $e) {
    db()->rollBack();
    respond_json(['error' => $e->getMessage()], 500);
}

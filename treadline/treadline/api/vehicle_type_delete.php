<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN']);

$id = (int)($input['id'] ?? 0);
if (!$id) respond_json(['error' => 'Missing vehicle type id'], 422);

$vcount = db()->prepare("SELECT COUNT(*) c FROM vehicles WHERE vehicle_type_id=?"); $vcount->execute([$id]);
if ((int)$vcount->fetch()['c'] > 0) {
    respond_json(['error' => 'This vehicle type is in use by one or more vehicles. Reassign them first.'], 422);
}

db()->prepare("DELETE FROM vehicle_types WHERE id=?")->execute([$id]);
log_action('DELETE', 'vehicle_type', $id);
respond_json(['message' => 'Vehicle type deleted']);

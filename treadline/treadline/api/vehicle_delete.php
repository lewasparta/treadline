<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$id = (int)($input['id'] ?? 0);
if (!$id) respond_json(['error' => 'Missing vehicle id'], 422);

$vehicle = db()->prepare("SELECT * FROM vehicles WHERE id=?"); $vehicle->execute([$id]); $vehicle = $vehicle->fetch();
if (!$vehicle) respond_json(['error' => 'Vehicle not found'], 404);

$fitted = db()->prepare("SELECT COUNT(*) c FROM tyres WHERE current_vehicle_id=? AND status='IN_SERVICE'");
$fitted->execute([$id]);
if ((int)$fitted->fetch()['c'] > 0) {
    respond_json(['error' => 'This vehicle still has tyres fitted. Remove them from the tyre map first.'], 422);
}

try {
    db()->prepare("DELETE FROM vehicles WHERE id=?")->execute([$id]);
    log_action('DELETE', 'vehicle', $id);
    respond_json(['message' => 'Vehicle deleted']);
} catch (PDOException $e) {
    // Has inspections, movements, mileage log, alerts, etc. - archive instead of losing the trail
    db()->prepare("UPDATE vehicles SET status='ARCHIVED' WHERE id=?")->execute([$id]);
    log_action('ARCHIVE', 'vehicle', $id, 'Delete blocked by history; archived instead');
    respond_json(['message' => 'This vehicle has recorded history (inspections/movements), so it was archived instead of deleted to preserve records.']);
}

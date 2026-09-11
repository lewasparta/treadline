<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$id = (int)($input['id'] ?? 0);
if (!$id) respond_json(['error' => 'Missing tyre id'], 422);

$tyre = db()->prepare("SELECT * FROM tyres WHERE id=?"); $tyre->execute([$id]); $tyre = $tyre->fetch();
if (!$tyre) respond_json(['error' => 'Tyre not found'], 404);

if ($tyre['status'] === 'IN_SERVICE') {
    respond_json(['error' => 'This tyre is currently fitted to a vehicle. Remove it first.'], 422);
}

try {
    db()->prepare("DELETE FROM tyres WHERE id=?")->execute([$id]);
    log_action('DELETE', 'tyre', $id);
    respond_json(['message' => 'Tyre deleted']);
} catch (PDOException $e) {
    // Has movements, inspection readings, repairs or retreads on record - scrap instead of losing the trail
    db()->prepare("UPDATE tyres SET status='SCRAP', current_vehicle_id=NULL, current_position=NULL WHERE id=?")->execute([$id]);
    log_action('SCRAP', 'tyre', $id, 'Delete blocked by history; scrapped instead');
    respond_json(['message' => 'This tyre has recorded history (inspections/repairs/movements), so it was marked SCRAP instead of deleted to preserve records.']);
}

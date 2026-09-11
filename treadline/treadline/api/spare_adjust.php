<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$vehicleId = (int)($input['vehicle_id'] ?? 0);
$delta = (int)($input['delta'] ?? 0);
if (!$vehicleId || !$delta) respond_json(['error' => 'Missing vehicle or delta'], 422);

$v = db()->prepare("SELECT spare_count FROM vehicles WHERE id=?"); $v->execute([$vehicleId]); $v = $v->fetch();
if (!$v) respond_json(['error' => 'Vehicle not found'], 404);

$current = (int)$v['spare_count'];
$new = $current + $delta;
$new = max(0, min(4, $new));

if ($delta < 0 && $new < $current) {
    // Refuse to remove a slot that still has a tyre fitted
    $checkPos = 'SP' . $current;
    $occupied = db()->prepare("SELECT id FROM tyres WHERE current_vehicle_id=? AND current_position=? AND status='IN_SERVICE'");
    $occupied->execute([$vehicleId, $checkPos]);
    if ($occupied->fetch()) respond_json(['error' => "Remove the tyre fitted in $checkPos before removing that spare slot"], 422);
}

db()->prepare("UPDATE vehicles SET spare_count=? WHERE id=?")->execute([$new, $vehicleId]);
log_action('SPARE_ADJUST', 'vehicle', $vehicleId, "spare_count -> $new");
respond_json(['message' => 'Spare slots updated', 'spare_count' => $new]);

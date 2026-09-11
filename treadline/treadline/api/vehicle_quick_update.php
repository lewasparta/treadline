<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$id = (int)($input['id'] ?? 0);
if (!$id) respond_json(['error' => 'Missing vehicle id'], 422);

$existing = db()->prepare("SELECT id FROM vehicles WHERE id=?"); $existing->execute([$id]);
if (!$existing->fetch()) respond_json(['error' => 'Vehicle not found'], 404);

// Only these fields may be partially updated; anything not present in $input is left untouched.
$allowed = ['driver_name', 'branch_id', 'towed_by_vehicle_id', 'status', 'department'];
$sets = []; $params = [];
foreach ($allowed as $field) {
    if (!array_key_exists($field, $input)) continue;
    $value = $input[$field];
    if (in_array($field, ['branch_id', 'towed_by_vehicle_id'], true)) {
        $value = ($value === '' || $value === null) ? null : (int)$value;
        if ($field === 'towed_by_vehicle_id' && $value === $id) {
            respond_json(['error' => 'A vehicle cannot tow itself'], 422);
        }
    }
    $sets[] = "$field = ?";
    $params[] = $value;
}
if (!$sets) respond_json(['error' => 'Nothing to update'], 422);

$params[] = $id;
db()->prepare("UPDATE vehicles SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
log_action('QUICK_UPDATE', 'vehicle', $id, implode(',', array_keys(array_intersect_key($input, array_flip($allowed)))));
respond_json(['message' => 'Vehicle updated']);

<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN']);

$id = (int)($input['id'] ?? 0);
$name = trim($input['name'] ?? '');
$axle = $input['axle_config'] ?? '4X2';
$spareDefault = max(0, (int)($input['spare_default'] ?? 1));
$rows = $input['layout_rows'] ?? [];

if (!$name) respond_json(['error' => 'Name required'], 422);
if (!is_array($rows) || !$rows) respond_json(['error' => 'At least one axle/row with positions is required'], 422);

// Validate + flatten
$flat = [];
$cleanRows = [];
foreach ($rows as $row) {
    $label = trim($row['label'] ?? '');
    $drive = !empty($row['drive']);
    $positions = array_values(array_filter(array_map('trim', $row['positions'] ?? [])));
    if (!$positions) continue;
    foreach ($positions as $p) {
        if (in_array($p, $flat, true)) respond_json(['error' => "Position code \"$p\" is used more than once"], 422);
        $flat[] = $p;
    }
    $cleanRows[] = ['label' => $label ?: 'ROW', 'drive' => $drive, 'positions' => $positions];
}
if (!$flat) respond_json(['error' => 'At least one valid position code is required'], 422);

$positionsJson = json_encode($flat);
$layoutJson = json_encode($cleanRows);

if ($id) {
    $stmt = db()->prepare("UPDATE vehicle_types SET name=?, axle_config=?, positions=?, layout_rows=?, spare_default=? WHERE id=?");
    $stmt->execute([$name, $axle, $positionsJson, $layoutJson, $spareDefault, $id]);
    log_action('UPDATE', 'vehicle_type', $id);
    respond_json(['id' => $id, 'message' => 'Vehicle type updated']);
} else {
    $stmt = db()->prepare("INSERT INTO vehicle_types (name, axle_config, positions, layout_rows, spare_default) VALUES (?,?,?,?,?)");
    $stmt->execute([$name, $axle, $positionsJson, $layoutJson, $spareDefault]);
    $id = db()->lastInsertId();
    log_action('CREATE', 'vehicle_type', $id);
    respond_json(['id' => $id, 'message' => 'Vehicle type created']);
}

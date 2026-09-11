<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$id = (int)($input['id'] ?? 0);
$plate = trim($input['plate_number'] ?? '');
if ($plate === '') respond_json(['error' => 'Plate number is required'], 422);

$towedBy = ($input['towed_by_vehicle_id'] ?? '') !== '' ? (int)$input['towed_by_vehicle_id'] : null;
if ($towedBy && $id && $towedBy === $id) respond_json(['error' => 'A vehicle cannot tow itself'], 422);

$fields = [
    'plate_number' => $plate,
    'fleet_number' => $input['fleet_number'] ?? null,
    'vehicle_type_id' => (int)($input['vehicle_type_id'] ?? 0) ?: null,
    'make' => $input['make'] ?? null,
    'model' => $input['model'] ?? null,
    'year' => $input['year'] ?? null,
    'vin' => $input['vin'] ?? null,
    'branch_id' => (int)($input['branch_id'] ?? 0) ?: null,
    'department' => $input['department'] ?? null,
    'driver_name' => $input['driver_name'] ?? null,
    'current_mileage' => (int)($input['current_mileage'] ?? 0),
    'status' => $input['status'] ?? 'ACTIVE',
    'towed_by_vehicle_id' => $towedBy,
];

try {
    if ($id) {
        $sets = implode(',', array_map(fn($k) => "$k = :$k", array_keys($fields)));
        $stmt = db()->prepare("UPDATE vehicles SET $sets WHERE id = :id");
        $stmt->execute([...$fields, 'id' => $id]);
        log_action('UPDATE', 'vehicle', $id);
        respond_json(['id' => $id, 'message' => 'Vehicle updated']);
    } else {
        // New vehicle: seed spare slot count from its type's default (0 for forklifts/tuktuks, etc.)
        $spareDefault = 1;
        if ($fields['vehicle_type_id']) {
            $vt = db()->prepare("SELECT spare_default FROM vehicle_types WHERE id=?");
            $vt->execute([$fields['vehicle_type_id']]);
            if ($row = $vt->fetch()) $spareDefault = (int)$row['spare_default'];
        }
        $fields['spare_count'] = $spareDefault;

        $cols = implode(',', array_keys($fields));
        $ph = implode(',', array_map(fn($k) => ":$k", array_keys($fields)));
        $stmt = db()->prepare("INSERT INTO vehicles ($cols, mileage_reading_date) VALUES ($ph, CURDATE())");
        $stmt->execute($fields);
        $id = db()->lastInsertId();
        db()->prepare("INSERT INTO mileage_log (vehicle_id, mileage, reading_date, recorded_by) VALUES (?,?,CURDATE(),?)")
            ->execute([$id, $fields['current_mileage'], $user['id']]);
        log_action('CREATE', 'vehicle', $id);
        respond_json(['id' => $id, 'message' => 'Vehicle created']);
    }
} catch (PDOException $e) {
    respond_json(['error' => str_contains($e->getMessage(),'Duplicate') ? 'A vehicle with this plate number already exists.' : $e->getMessage()], 500);
}

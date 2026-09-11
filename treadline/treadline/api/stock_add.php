<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$serial = trim($input['serial_number'] ?? '');
$brand = trim($input['brand'] ?? '');
$size = trim($input['size'] ?? '');
if ($serial === '' || $brand === '' || $size === '') respond_json(['error' => 'Serial number, brand and size are required'], 422);

try {
    $stmt = db()->prepare("INSERT INTO tyres (serial_number, branding_code, brand, model, size, new_tread_mm, recommended_psi,
        purchase_price, expected_mileage, storage_location, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,'STOCK')");
    $stmt->execute([
        $serial, $input['branding_code'] ?? null, $brand, $input['model'] ?? null, $size,
        (float)($input['new_tread_mm'] ?? 8.0), (float)($input['recommended_psi'] ?? 32),
        (float)($input['purchase_price'] ?? 0), (int)($input['expected_mileage'] ?? 60000),
        $input['storage_location'] ?? null,
    ]);
    $id = db()->lastInsertId();
    db()->prepare("INSERT INTO tyre_movements (tyre_id, movement_type, to_position, movement_date, reason, performed_by) VALUES (?,'STOCK_IN',NULL,CURDATE(),'Added to stock',?)")
        ->execute([$id, $user['id']]);
    log_action('CREATE', 'tyre', $id, 'Added to stock');
    respond_json(['id' => $id, 'message' => 'Tyre added to stock']);
} catch (PDOException $e) {
    respond_json(['error' => str_contains($e->getMessage(), 'Duplicate') ? 'A tyre with this serial number already exists.' : $e->getMessage()], 500);
}

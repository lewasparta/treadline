<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$id = (int)($input['id'] ?? 0);
if (!$id) respond_json(['error' => 'Missing tyre id'], 422);

$tyre = db()->prepare("SELECT id FROM tyres WHERE id=?"); $tyre->execute([$id]);
if (!$tyre->fetch()) respond_json(['error' => 'Tyre not found'], 404);

$brand = trim($input['brand'] ?? '');
$size = trim($input['size'] ?? '');
if (!$brand || !$size) respond_json(['error' => 'Brand and size are required'], 422);

try {
    $stmt = db()->prepare("UPDATE tyres SET brand=?, model=?, size=?, branding_code=?, recommended_psi=?, new_tread_mm=?,
        purchase_price=?, storage_location=? WHERE id=?");
    $stmt->execute([
        $brand, $input['model'] ?? null, $size, $input['branding_code'] ?? null,
        (float)($input['recommended_psi'] ?? 32), (float)($input['new_tread_mm'] ?? 8.0),
        (float)($input['purchase_price'] ?? 0), $input['storage_location'] ?? null, $id,
    ]);
    log_action('UPDATE', 'tyre', $id);
    respond_json(['message' => 'Tyre updated']);
} catch (PDOException $e) {
    respond_json(['error' => $e->getMessage()], 500);
}

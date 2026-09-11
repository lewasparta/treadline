<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);

$id = (int)($input['id'] ?? 0);
if (!$id) respond_json(['error' => 'Missing supplier id'], 422);

$supplier = db()->prepare("SELECT * FROM suppliers WHERE id=?"); $supplier->execute([$id]); $supplier = $supplier->fetch();
if (!$supplier) respond_json(['error' => 'Supplier not found'], 404);

try {
    db()->prepare("DELETE FROM suppliers WHERE id=?")->execute([$id]);
    log_action('DELETE', 'supplier', $id);
    respond_json(['message' => 'Supplier deleted']);
} catch (PDOException $e) {
    db()->prepare("UPDATE suppliers SET status='INACTIVE' WHERE id=?")->execute([$id]);
    log_action('DEACTIVATE', 'supplier', $id, 'Delete blocked by purchase history; deactivated instead');
    respond_json(['message' => 'This supplier has tyre purchase history, so it was marked inactive instead of deleted.']);
}

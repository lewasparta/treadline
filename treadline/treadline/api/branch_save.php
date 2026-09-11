<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN']);
$id = (int)($input['id'] ?? 0);
$name = trim($input['name'] ?? ''); $code = trim($input['code'] ?? '');
if (!$name || !$code) respond_json(['error' => 'Name and code are required'], 422);
try {
    if ($id) {
        $stmt = db()->prepare("UPDATE branches SET name=?, code=?, location=?, manager=?, contact=?, status=? WHERE id=?");
        $stmt->execute([$name, $code, $input['location'] ?? null, $input['manager'] ?? null, $input['contact'] ?? null, $input['status'] ?? 'ACTIVE', $id]);
        log_action('UPDATE', 'branch', $id);
        respond_json(['id' => $id, 'message' => 'Branch updated']);
    } else {
        $stmt = db()->prepare("INSERT INTO branches (name, code, location, manager, contact) VALUES (?,?,?,?,?)");
        $stmt->execute([$name, $code, $input['location'] ?? null, $input['manager'] ?? null, $input['contact'] ?? null]);
        $id = db()->lastInsertId();
        log_action('CREATE', 'branch', $id);
        respond_json(['id' => $id, 'message' => 'Branch created']);
    }
} catch (PDOException $e) {
    respond_json(['error' => str_contains($e->getMessage(),'Duplicate') ? 'Branch code already exists' : $e->getMessage()], 500);
}

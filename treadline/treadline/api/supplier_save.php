<?php
require_once __DIR__ . '/_bootstrap.php';
if (is_readonly()) respond_json(['error' => 'Read-only access'], 403);
$id = (int)($input['id'] ?? 0);
$company = trim($input['company'] ?? '');
if (!$company) respond_json(['error' => 'Company name required'], 422);
if ($id) {
    $stmt = db()->prepare("UPDATE suppliers SET company=?, contact_person=?, phone=?, email=?, address=?, products=?, status=? WHERE id=?");
    $stmt->execute([$company, $input['contact_person'] ?? null, $input['phone'] ?? null, $input['email'] ?? null,
        $input['address'] ?? null, $input['products'] ?? null, $input['status'] ?? 'ACTIVE', $id]);
    log_action('UPDATE', 'supplier', $id);
    respond_json(['id' => $id, 'message' => 'Supplier updated']);
} else {
    $stmt = db()->prepare("INSERT INTO suppliers (company, contact_person, phone, email, address, products) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$company, $input['contact_person'] ?? null, $input['phone'] ?? null, $input['email'] ?? null, $input['address'] ?? null, $input['products'] ?? null]);
    $id = db()->lastInsertId();
    log_action('CREATE', 'supplier', $id);
    respond_json(['id' => $id, 'message' => 'Supplier added']);
}

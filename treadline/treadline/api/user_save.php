<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN']);

$name = trim($input['full_name'] ?? '');
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
if (!$name || !$username || strlen($password) < 4) respond_json(['error' => 'Name, username and a password (min 4 chars) are required'], 422);

try {
    $stmt = db()->prepare("INSERT INTO users (full_name, username, email, password_hash, role_id, branch_id) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$name, $username, $input['email'] ?? null, password_hash($password, PASSWORD_BCRYPT),
        (int)($input['role_id'] ?? 0), (int)($input['branch_id'] ?? 0) ?: null]);
    $id = db()->lastInsertId();
    log_action('CREATE', 'user', $id);
    respond_json(['id' => $id, 'message' => 'User created']);
} catch (PDOException $e) {
    respond_json(['error' => str_contains($e->getMessage(),'Duplicate') ? 'Username or email already exists' : $e->getMessage()], 500);
}

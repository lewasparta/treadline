<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN']);

$id = (int)($input['id'] ?? 0);
$fullName = trim($input['full_name'] ?? '');
$username = trim($input['username'] ?? '');
if (!$id || !$fullName || !$username) respond_json(['error' => 'Name and username are required'], 422);

$target = db()->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?");
$target->execute([$id]);
$target = $target->fetch();
if (!$target) respond_json(['error' => 'User not found'], 404);

$newRoleId = (int)($input['role_id'] ?? $target['role_id']);
$newStatus = $input['status'] ?? $target['status'];

// Don't allow demoting/suspending the last active admin
if ($target['role_name'] === 'ADMIN') {
    $stmt = db()->prepare("SELECT r.name FROM roles r WHERE r.id=?"); $stmt->execute([$newRoleId]); $newRoleName = $stmt->fetch()['name'] ?? '';
    $losingAdmin = ($newRoleName !== 'ADMIN') || ($newStatus !== 'ACTIVE');
    if ($losingAdmin) {
        $activeAdmins = db()->query("SELECT COUNT(*) c FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='ADMIN' AND u.status='ACTIVE'")->fetch()['c'];
        if ((int)$activeAdmins <= 1) respond_json(['error' => 'Cannot demote or suspend the last active administrator'], 422);
    }
}

try {
    $stmt = db()->prepare("UPDATE users SET full_name=?, username=?, email=?, role_id=?, branch_id=?, status=? WHERE id=?");
    $stmt->execute([
        $fullName, $username, $input['email'] ?? null, $newRoleId,
        (int)($input['branch_id'] ?? 0) ?: null, $newStatus, $id,
    ]);
    log_action('UPDATE', 'user', $id);
    respond_json(['message' => 'User updated']);
} catch (PDOException $e) {
    respond_json(['error' => str_contains($e->getMessage(),'Duplicate') ? 'Username or email already in use' : $e->getMessage()], 500);
}

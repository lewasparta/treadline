<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN']);

$id = (int)($input['id'] ?? 0);
if (!$id) respond_json(['error' => 'Missing user id'], 422);
if ($id === (int)$user['id']) respond_json(['error' => 'You cannot delete your own account'], 422);

$target = db()->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?");
$target->execute([$id]);
$target = $target->fetch();
if (!$target) respond_json(['error' => 'User not found'], 404);

if ($target['role_name'] === 'ADMIN') {
    $activeAdmins = db()->query("SELECT COUNT(*) c FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='ADMIN' AND u.status='ACTIVE'")->fetch()['c'];
    if ((int)$activeAdmins <= 1) respond_json(['error' => 'Cannot delete the last active administrator'], 422);
}

try {
    db()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    log_action('DELETE', 'user', $id);
    respond_json(['message' => 'User deleted']);
} catch (PDOException $e) {
    // Has activity history (inspections, movements, audit log, etc.) - suspend instead of losing the trail
    db()->prepare("UPDATE users SET status='SUSPENDED' WHERE id=?")->execute([$id]);
    log_action('SUSPEND', 'user', $id, 'Delete blocked by activity history; suspended instead');
    respond_json(['message' => 'This user has recorded activity, so the account was suspended instead of deleted to preserve history.']);
}

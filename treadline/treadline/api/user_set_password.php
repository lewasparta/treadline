<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN']);

$id = (int)($input['id'] ?? 0);
$password = $input['password'] ?? '';
if (!$id) respond_json(['error' => 'Missing user id'], 422);
if (strlen($password) < 4) respond_json(['error' => 'Password must be at least 4 characters'], 422);

$target = db()->prepare("SELECT id FROM users WHERE id=?"); $target->execute([$id]);
if (!$target->fetch()) respond_json(['error' => 'User not found'], 404);

db()->prepare("UPDATE users SET password_hash=?, remember_token=NULL WHERE id=?")
    ->execute([password_hash($password, PASSWORD_BCRYPT), $id]);
log_action('PASSWORD_RESET', 'user', $id, 'Password reset by admin');
respond_json(['message' => 'Password updated']);

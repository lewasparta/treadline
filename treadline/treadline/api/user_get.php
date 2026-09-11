<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN']);
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT id, full_name, username, email, role_id, branch_id, status FROM users WHERE id=?");
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) respond_json(['error' => 'User not found'], 404);
respond_json(['user' => $u]);

<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN']);

$id = (int)($input['id'] ?? 0);
if (!$id) respond_json(['error' => 'Missing branch id'], 422);

$vcount = db()->prepare("SELECT COUNT(*) c FROM vehicles WHERE branch_id=?"); $vcount->execute([$id]);
if ((int)$vcount->fetch()['c'] > 0) {
    respond_json(['error' => 'This branch has vehicles assigned to it. Reassign or delete those vehicles first.'], 422);
}
$ucount = db()->prepare("SELECT COUNT(*) c FROM users WHERE branch_id=?"); $ucount->execute([$id]);
if ((int)$ucount->fetch()['c'] > 0) {
    respond_json(['error' => 'This branch has users assigned to it. Reassign those users first.'], 422);
}

db()->prepare("DELETE FROM branches WHERE id=?")->execute([$id]);
log_action('DELETE', 'branch', $id);
respond_json(['message' => 'Branch deleted']);

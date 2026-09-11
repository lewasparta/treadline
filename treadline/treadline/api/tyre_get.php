<?php
require_once __DIR__ . '/_bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM tyres WHERE id=?");
$stmt->execute([$id]);
$t = $stmt->fetch();
if (!$t) respond_json(['error' => 'Tyre not found'], 404);
respond_json(['tyre' => $t]);

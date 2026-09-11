<?php
require_once __DIR__ . '/_bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM vehicles WHERE id=?");
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) respond_json(['error' => 'Vehicle not found'], 404);
respond_json(['vehicle' => $v]);

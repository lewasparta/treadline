<?php
require_once __DIR__ . '/_bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM branches WHERE id=?");
$stmt->execute([$id]);
$b = $stmt->fetch();
if (!$b) respond_json(['error' => 'Branch not found'], 404);
respond_json(['branch' => $b]);

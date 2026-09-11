<?php
require_once __DIR__ . '/_bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM suppliers WHERE id=?");
$stmt->execute([$id]);
$s = $stmt->fetch();
if (!$s) respond_json(['error' => 'Supplier not found'], 404);
respond_json(['supplier' => $s]);

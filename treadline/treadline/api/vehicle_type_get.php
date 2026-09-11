<?php
require_once __DIR__ . '/_bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM vehicle_types WHERE id=?");
$stmt->execute([$id]);
$vt = $stmt->fetch();
if (!$vt) respond_json(['error' => 'Vehicle type not found'], 404);
$vt['layout_rows'] = json_decode($vt['layout_rows'] ?? '[]', true) ?: [];
respond_json(['vehicle_type' => $vt]);

<?php
require_once __DIR__ . '/_bootstrap.php';
$q = trim($_GET['q'] ?? '');
if ($q === '') respond_json(['vehicles' => []]);
$stmt = db()->prepare("SELECT id, plate_number, make, model FROM vehicles WHERE plate_number LIKE ? OR fleet_number LIKE ? OR vin LIKE ? LIMIT 10");
$like = "%$q%";
$stmt->execute([$like, $like, $like]);
respond_json(['vehicles' => $stmt->fetchAll()]);

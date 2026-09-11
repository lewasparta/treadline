<?php
require_once __DIR__ . '/_bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT t.*, v.plate_number, v.current_mileage AS vehicle_mileage FROM tyres t
  LEFT JOIN vehicles v ON v.id = t.current_vehicle_id WHERE t.id = ?");
$stmt->execute([$id]);
$t = $stmt->fetch();
if (!$t) respond_json(['error' => 'Tyre not found'], 404);

$t['position'] = $t['current_position'];
$treadStatus = tread_status((float)($t['current_tread_mm'] ?? $t['new_tread_mm']));
$psiSt = $t['current_psi'] ? psi_status((float)$t['current_psi'], (float)$t['recommended_psi']) : 'NORMAL';
$mileage = $t['install_odometer'] && $t['vehicle_mileage'] ? max(0, $t['vehicle_mileage'] - $t['install_odometer']) : 0;
$ageDays = $t['purchase_date'] ? (int)((time() - strtotime($t['purchase_date'])) / 86400) : 0;

$health = calc_health_score([
    'new_tread_mm' => $t['new_tread_mm'], 'tread_avg' => $t['current_tread_mm'], 'psi_status' => $psiSt,
    'purchase_date' => $t['purchase_date'], 'expected_lifespan_days' => $t['expected_lifespan_days'],
    'tyre_mileage' => $mileage, 'expected_mileage' => $t['expected_mileage'], 'total_repairs' => $t['total_repairs'],
]);

respond_json([
    'tyre' => $t,
    'tread_status' => $treadStatus,
    'tread_badge' => badge_class($treadStatus),
    'tread_color' => status_color($treadStatus),
    'psi_status' => $psiSt,
    'psi_badge' => badge_class($psiSt),
    'tyre_mileage' => $mileage,
    'age_days' => $ageDays,
    'health_score' => $health['score'],
    'health_rating' => $health['rating'],
    'health_color' => status_color($health['rating']),
]);

<?php
require_once __DIR__ . '/_bootstrap.php';
require_role(['ADMIN','FLEET_MANAGER','TYRE_INSPECTOR','SUPERVISOR']);

$vehicleId = (int)($input['vehicle_id'] ?? 0);
$odometer = (int)($input['odometer'] ?? 0);
$items = $input['items'] ?? [];
if (!$vehicleId || !$odometer || !$items) respond_json(['error' => 'Vehicle, odometer and at least one tyre reading are required'], 422);

db()->beginTransaction();
try {
    // Update vehicle mileage if it advanced
    $v = db()->prepare("SELECT current_mileage FROM vehicles WHERE id=?"); $v->execute([$vehicleId]); $v = $v->fetch();
    if ($odometer > (int)$v['current_mileage']) {
        db()->prepare("UPDATE vehicles SET current_mileage=?, mileage_reading_date=CURDATE() WHERE id=?")->execute([$odometer, $vehicleId]);
        db()->prepare("INSERT INTO mileage_log (vehicle_id, mileage, reading_date, recorded_by) VALUES (?,?,CURDATE(),?)")->execute([$vehicleId, $odometer, $user['id']]);
    }

    $stmt = db()->prepare("INSERT INTO inspections (vehicle_id, odometer, inspector_id, status, notes) VALUES (?,?,?,?,?)");
    $stmt->execute([$vehicleId, $odometer, $user['id'], 'PENDING', $input['notes'] ?? '']);
    $inspectionId = db()->lastInsertId();

    $badSidewall = ['Cut','Crack','Bulge','Sidewall damage'];
    $badTread = ['Uneven wear','Cupping','Feathering','Chunking','Embedded object','Puncture','Centre wear','Shoulder wear','Inner wear','Outer wear'];

    foreach ($items as $it) {
        $tyreId = (int)($it['tyre_id'] ?? 0);
        if (!$tyreId) continue;
        $tyre = db()->prepare("SELECT * FROM tyres WHERE id=?"); $tyre->execute([$tyreId]); $tyre = $tyre->fetch();
        if (!$tyre) continue;

        $inner = (float)($it['tread_inner'] ?? 0); $center = (float)($it['tread_center'] ?? 0); $outer = (float)($it['tread_outer'] ?? 0);
        $vals = array_filter([$inner,$center,$outer], fn($v) => $v > 0);
        $avg = $vals ? round(array_sum($vals)/count($vals), 1) : 0;
        $min = $vals ? min($vals) : 0; $max = $vals ? max($vals) : 0;
        $psiActual = (float)($it['psi_actual'] ?? 0);
        $psiSt = psi_status($psiActual, (float)$tyre['recommended_psi']);

        $health = calc_health_score([
            'new_tread_mm' => $tyre['new_tread_mm'], 'tread_avg' => $avg, 'psi_status' => $psiSt,
            'sidewall_condition' => $it['sidewall_condition'] ?? 'Good', 'tread_condition' => $it['tread_condition'] ?? 'Good',
            'foreign_object' => $it['foreign_object'] ?? 0, 'purchase_date' => $tyre['purchase_date'],
            'expected_lifespan_days' => $tyre['expected_lifespan_days'],
            'tyre_mileage' => max(0, $odometer - (int)($tyre['install_odometer'] ?? $odometer)),
            'expected_mileage' => $tyre['expected_mileage'], 'total_repairs' => $tyre['total_repairs'],
        ]);

        $ins = db()->prepare("INSERT INTO inspection_items (inspection_id, tyre_id, position, tread_inner, tread_center, tread_outer,
            tread_avg, tread_min, tread_max, psi_actual, psi_unit, psi_status, sidewall_condition, tread_condition,
            valve_ok, rim_ok, nuts_ok, foreign_object, comments, health_score, health_rating)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $ins->execute([
            $inspectionId, $tyreId, $tyre['current_position'], $inner, $center, $outer, $avg, $min, $max,
            $psiActual, 'PSI', $psiSt, $it['sidewall_condition'] ?? 'Good', $it['tread_condition'] ?? 'Good',
            !empty($it['valve_ok']) ? 1 : 0, !empty($it['rim_ok']) ? 1 : 0, !empty($it['nuts_ok']) ? 1 : 0,
            !empty($it['foreign_object']) ? 1 : 0, $it['comments'] ?? '', $health['score'], $health['rating'],
        ]);

        // Update the live tyre record with measured readings
        db()->prepare("UPDATE tyres SET current_tread_mm=?, current_psi=? WHERE id=?")->execute([$avg, $psiActual, $tyreId]);

        // Alerts
        $critical = (float) get_setting('tread_critical_mm', 2.0);
        $watch = (float) get_setting('tread_watch_mm', 5.0);
        if ($avg <= $critical) {
            create_alert($vehicleId, $tyreId, 'TREAD_CRITICAL', 'CRITICAL',
                "{$tyre['current_position']} tyre on this vehicle is at {$avg}mm — REPLACEMENT RECOMMENDED", 'REPLACEMENT RECOMMENDED');
        } elseif ($avg < $watch) {
            create_alert($vehicleId, $tyreId, 'TREAD_WATCH', 'MEDIUM', "{$tyre['current_position']} tyre is at {$avg}mm — approaching replacement threshold");
        }
        if (in_array($psiSt, ['LOW','CRITICAL'], true)) create_alert($vehicleId, $tyreId, 'PSI_LOW', $psiSt==='CRITICAL'?'HIGH':'MEDIUM', "{$tyre['current_position']} tyre PSI is low ($psiActual PSI vs {$tyre['recommended_psi']} recommended)");
        if ($psiSt === 'HIGH') create_alert($vehicleId, $tyreId, 'PSI_HIGH', 'MEDIUM', "{$tyre['current_position']} tyre PSI is high ($psiActual PSI vs {$tyre['recommended_psi']} recommended)");
        if (in_array($it['sidewall_condition'] ?? '', $badSidewall, true)) create_alert($vehicleId, $tyreId, 'SIDEWALL_DAMAGE', 'HIGH', "{$tyre['current_position']} tyre sidewall issue: {$it['sidewall_condition']}");
        if (in_array($it['tread_condition'] ?? '', $badTread, true)) create_alert($vehicleId, $tyreId, 'TREAD_DAMAGE', 'MEDIUM', "{$tyre['current_position']} tyre tread issue: {$it['tread_condition']}");
        if (!empty($it['foreign_object'])) create_alert($vehicleId, $tyreId, 'FOREIGN_OBJECT', 'HIGH', "{$tyre['current_position']} tyre has a foreign object embedded");
    }

    db()->commit();
    log_action('INSPECTION', 'vehicle', $vehicleId, "Inspection #$inspectionId created");
    respond_json(['id' => $inspectionId, 'message' => 'Inspection saved']);
} catch (Exception $e) {
    db()->rollBack();
    respond_json(['error' => $e->getMessage()], 500);
}

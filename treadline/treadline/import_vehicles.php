<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/spreadsheet_reader.php';
require_role(['ADMIN','FLEET_MANAGER']);
$pageTitle = 'Import Vehicles';

// Expected columns (exact order/names from the legacy system export):
// plate_number, fleet_number, vehicle_type, make, model, year, vin, branch, department, driver_name, current_odometer, status
$EXPECTED = ['plate_number','fleet_number','vehicle_type','make','model','year','vin','branch','department','driver_name','current_odometer','status'];

$STATUS_MAP = [
    'active' => 'ACTIVE',
    'breakdown' => 'MAINTENANCE',
    'escalate to workshop' => 'MAINTENANCE',
    'in workshop' => 'MAINTENANCE',
    'out of service' => 'OUT_OF_SERVICE',
    'written off (accident)' => 'ARCHIVED',
    'sold' => 'SOLD',
    'archived' => 'ARCHIVED',
];

// Known company-level values that appear in the "branch" column instead of a city
$COMPANY_BRANCH_MAP = [
    'gdl - gilanis distributors ltd' => 'GDL Unassigned',
    'gsl - gilanis supermarket ltd' => 'GSL Supermarket',
];

function tl_find_or_create_branch(string $name): int {
    $name = trim($name);
    if ($name === '') $name = 'Unassigned';
    global $COMPANY_BRANCH_MAP;
    $lookup = $COMPANY_BRANCH_MAP[strtolower($name)] ?? null;

    // 1. exact company mapping
    if ($lookup) {
        $s = db()->prepare("SELECT id FROM branches WHERE name = ?"); $s->execute([$lookup]);
        if ($r = $s->fetch()) return (int)$r['id'];
    }
    // 2. exact name match
    $s = db()->prepare("SELECT id FROM branches WHERE name = ?"); $s->execute([$name]);
    if ($r = $s->fetch()) return (int)$r['id'];
    // 3. fuzzy: city name is contained in an existing branch name (e.g. NAKURU -> GDL Nakuru)
    $s = db()->prepare("SELECT id FROM branches WHERE name LIKE ?"); $s->execute(['%' . $name . '%']);
    if ($r = $s->fetch()) return (int)$r['id'];
    // 4. create new branch
    $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 8)) . '-' . substr(md5($name), 0, 4);
    $s = db()->prepare("INSERT INTO branches (name, code, location) VALUES (?,?,?)");
    $s->execute([$name, $code, $name]);
    return (int)db()->lastInsertId();
}

function tl_find_or_create_vehicle_type(string $name): int {
    $name = trim($name) ?: 'General';
    $s = db()->prepare("SELECT id FROM vehicle_types WHERE name = ?"); $s->execute([$name]);
    if ($r = $s->fetch()) return (int)$r['id'];
    $s = db()->prepare("INSERT INTO vehicle_types (name, axle_config, positions) VALUES (?, '4X2', '[\"FL\",\"FR\",\"RL\",\"RR\",\"SP1\"]')");
    $s->execute([$name]);
    return (int)db()->lastInsertId();
}

$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['tmp_name'])) {
    try {
        $rows = tl_read_spreadsheet_rows($_FILES['file']['tmp_name'], $_FILES['file']['name']);
    } catch (TL_SpreadsheetError $e) {
        $rows = null;
        $results = ['error' => $e->getMessage()];
    }

    if ($rows !== null) {
        $header = array_map(fn($h) => strtolower(trim($h)), array_shift($rows));
        $missing = array_diff($EXPECTED, $header);
        if ($missing) {
            $results = ['error' => 'The file is missing expected column(s): ' . implode(', ', $missing) . '. Expected header: ' . implode(', ', $EXPECTED)];
        } else {
            $colIndex = array_flip($header);
            $created = 0; $updated = 0; $errors = [];
            $rowNum = 1;
            foreach ($rows as $row) {
                $rowNum++;
                if (!array_filter($row, fn($v) => trim((string)$v) !== '')) continue;
                $get = fn($key) => trim((string)($row[$colIndex[$key]] ?? ''));

                $plate = $get('plate_number');
                if ($plate === '') { $errors[] = "Row $rowNum: missing plate_number, skipped"; continue; }

                $typeId = tl_find_or_create_vehicle_type($get('vehicle_type'));
                $branchId = tl_find_or_create_branch($get('branch'));
                $statusRaw = strtolower($get('status'));
                $status = $STATUS_MAP[$statusRaw] ?? 'ACTIVE';
                $mileage = (int) round((float) preg_replace('/[^0-9.]/', '', $get('current_odometer'))) ?: 0;
                $year = $get('year') !== '' ? (int)round((float)$get('year')) : null;

                $existing = db()->prepare("SELECT id, current_mileage FROM vehicles WHERE plate_number = ?");
                $existing->execute([$plate]);
                $existing = $existing->fetch();

                try {
                    if ($existing) {
                        $newMileage = max($mileage, (int)$existing['current_mileage']);
                        db()->prepare("UPDATE vehicles SET fleet_number=?, vehicle_type_id=?, make=?, model=?, year=?, vin=?, branch_id=?,
                            department=?, driver_name=?, current_mileage=?, status=? WHERE id=?")
                            ->execute([$get('fleet_number'), $typeId, $get('make'), $get('model'), $year, $get('vin'), $branchId,
                                $get('department'), $get('driver_name'), $newMileage, $status, $existing['id']]);
                        $updated++;
                    } else {
                        db()->prepare("INSERT INTO vehicles (plate_number, fleet_number, vehicle_type_id, make, model, year, vin, branch_id,
                            department, driver_name, current_mileage, mileage_reading_date, status)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,CURDATE(),?)")
                            ->execute([$plate, $get('fleet_number'), $typeId, $get('make'), $get('model'), $year, $get('vin'), $branchId,
                                $get('department'), $get('driver_name'), $mileage, $status]);
                        $created++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Row $rowNum ($plate): " . $e->getMessage();
                }
            }
            log_action('IMPORT', 'vehicle', null, "$created created, $updated updated");
            $results = ['created' => $created, 'updated' => $updated, 'errors' => $errors];
        }
    }
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Import Vehicles</h1><div class="sub">Upload a CSV or Excel (.xlsx) file — comma, semicolon, or tab-separated CSVs are all detected automatically</div></div></div>

  <div class="tl-card" style="margin-bottom:18px;">
    <h3 style="margin-top:0;">Expected column headers</h3>
    <p class="mono" style="font-size:12.5px;background:#f4f6f9;padding:10px;border-radius:6px;overflow-x:auto;"><?= e(implode(', ', $EXPECTED)) ?></p>
    <ul class="text-muted" style="font-size:12.5px;line-height:1.7;">
      <li>Accepts <b>.csv</b> (any delimiter — comma, semicolon, or tab, auto-detected) and native <b>.xlsx</b> Excel workbooks directly, no need to re-save.</li>
      <li><b>vehicle_type</b> and <b>branch</b> are matched to existing records (or created automatically if new).</li>
      <li><b>branch</b> can be a city name (e.g. NAKURU matches "GDL Nakuru") or a company name (GDL/GSL), which maps to the matching branch group.</li>
      <li><b>status</b> values recognised: Active, Breakdown, Escalate to Workshop, In Workshop, Out of Service, Written Off (Accident) — anything else defaults to Active.</li>
      <li>If a <b>plate_number</b> already exists, the vehicle is updated rather than duplicated.</li>
    </ul>
  </div>

  <?php if ($results): ?>
    <div class="tl-card" style="margin-bottom:18px;<?= isset($results['error']) ? 'background:#fde8e8;' : 'background:#e6f4ea;' ?>">
      <?php if (isset($results['error'])): ?>
        <b>Import failed:</b> <?= e($results['error']) ?>
      <?php else: ?>
        <b>Import complete.</b> <?= (int)$results['created'] ?> vehicle(s) created, <?= (int)$results['updated'] ?> updated.
        <?php if ($results['errors']): ?>
          <div style="margin-top:10px;"><b><?= count($results['errors']) ?> row(s) had issues:</b>
            <ul style="font-size:12.5px;">
              <?php foreach (array_slice($results['errors'],0,20) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <a href="vehicles.php" class="btn btn-primary btn-sm" style="margin-top:10px;">View Vehicles →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="tl-card" style="max-width:520px;">
    <form method="post" enctype="multipart/form-data">
      <label class="form-label">CSV or Excel File</label>
      <input class="form-control" type="file" name="file" accept=".csv,.xlsx,.xls,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required style="margin-bottom:14px;">
      <button class="btn btn-accent" type="submit">Upload & Import</button>
    </form>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/spreadsheet_reader.php';
require_role(['ADMIN','FLEET_MANAGER']);
$pageTitle = 'Import Tyres';

// Expected columns (exact order/names from the Fleetio parts export):
$EXPECTED = ['Fleetio ID','Inventory no.','Description','Category','Manufacturer','Manufacturer Part Number','Measurement Unit','Total Quantity','Unit Cost (KES)'];

/** Try to pull a tyre size (e.g. 215/60R16, 265/70 R16, 10.00R20, 10R17.5) out of a free-text description. */
function tl_extract_tyre_size(string $desc): string {
    if (preg_match('/(\d{2,3}\/\d{2}\s*R\s*\d{2}\.?\d*)/i', $desc, $m)) return preg_replace('/\s+/', '', strtoupper($m[1]));
    if (preg_match('/(\d{1,2}\.\d{2}\s*R\s*\d{2}\.?\d*)/i', $desc, $m)) return preg_replace('/\s+/', '', strtoupper($m[1]));
    if (preg_match('/(\d{1,2}R\s*\d{2}\.?\d*)/i', $desc, $m)) return preg_replace('/\s+/', '', strtoupper($m[1]));
    return 'UNSPECIFIED';
}

$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['tmp_name'])) {
    $onlyTyres = !empty($_POST['only_tyres']);
    try {
        $rows = tl_read_spreadsheet_rows($_FILES['file']['tmp_name'], $_FILES['file']['name']);
    } catch (TL_SpreadsheetError $e) {
        $rows = null;
        $results = ['error' => $e->getMessage()];
    }

    if ($rows !== null) {
        $header = array_map(fn($h) => trim((string)$h), array_shift($rows));
        $missing = array_diff($EXPECTED, $header);
        if ($missing) {
            $results = ['error' => 'The file is missing expected column(s): ' . implode(', ', $missing) . '. Expected header: ' . implode(', ', $EXPECTED)];
        } else {
            $colIndex = array_flip($header);
            $created = 0; $skipped = 0; $errors = [];
            $rowNum = 1;
            foreach ($rows as $row) {
                $rowNum++;
                if (!array_filter($row, fn($v) => trim((string)$v) !== '')) continue;
                $get = fn($key) => trim((string)($row[$colIndex[$key]] ?? ''));

                $category = $get('Category');
                if ($onlyTyres && stripos($category, 'tyre') === false && stripos($category, 'tire') === false) { $skipped++; continue; }

                $desc = $get('Description');
                $fleetioId = $get('Fleetio ID');
                $invNo = $get('Inventory no.');
                $manufacturer = $get('Manufacturer') ?: 'Unbranded';
                $partNo = $get('Manufacturer Part Number');
                $unitCost = (float) preg_replace('/[^0-9.]/', '', $get('Unit Cost (KES)')) ?: 0;
                $qty = (int) round((float) preg_replace('/[^0-9.]/', '', $get('Total Quantity'))) ?: 0;
                $size = tl_extract_tyre_size($desc);

                // Each Fleetio "part" is a tyre model/type; Total Quantity tells us how many physical units to
                // create as individual stock tyres (each gets its own serial so it can be tracked independently).
                $units = max(1, $qty); // always create at least 1 record so the part is represented even at 0 stock
                $baseSerial = $invNo !== '' ? $invNo : ('FLT-' . $fleetioId);

                for ($u = 1; $u <= $units; $u++) {
                    $serial = $units > 1 ? "{$baseSerial}-{$u}" : $baseSerial;
                    // skip if this exact serial already exists (re-running an import is then safe)
                    $exists = db()->prepare("SELECT id FROM tyres WHERE serial_number = ?");
                    $exists->execute([$serial]);
                    if ($exists->fetch()) { $skipped++; continue; }

                    try {
                        $stmt = db()->prepare("INSERT INTO tyres (serial_number, branding_code, brand, model, size, new_tread_mm,
                            recommended_psi, purchase_price, expected_mileage, storage_location, status)
                            VALUES (?,?,?,?,?,8.0,32,?,60000,'Imported stock','STOCK')");
                        $stmt->execute([$serial, $fleetioId, $manufacturer, $partNo ?: $desc, $size, $unitCost]);
                        $tid = db()->lastInsertId();
                        db()->prepare("INSERT INTO tyre_movements (tyre_id, movement_type, movement_date, reason, performed_by)
                            VALUES (?, 'STOCK_IN', CURDATE(), 'Imported from parts export', ?)")
                            ->execute([$tid, current_user()['id']]);
                        $created++;
                    } catch (Exception $e) {
                        $errors[] = "Row $rowNum ($desc): " . $e->getMessage();
                    }
                }
            }
            log_action('IMPORT', 'tyre', null, "$created created, $skipped skipped");
            $results = ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
        }
    }
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="tl-main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="tl-content">
  <div class="tl-page-head"><div><h1>Import Tyres</h1><div class="sub">Upload a CSV or Excel (.xlsx) file — comma, semicolon, or tab-separated CSVs are all detected automatically</div></div></div>

  <div class="tl-card" style="margin-bottom:18px;">
    <h3 style="margin-top:0;">Expected column headers</h3>
    <p class="mono" style="font-size:12.5px;background:#f4f6f9;padding:10px;border-radius:6px;overflow-x:auto;"><?= e(implode(', ', $EXPECTED)) ?></p>
    <ul class="text-muted" style="font-size:12.5px;line-height:1.7;">
      <li>Accepts <b>.csv</b> (any delimiter — comma, semicolon, or tab, auto-detected) and native <b>.xlsx</b> Excel workbooks directly, no need to re-save.</li>
      <li>Only rows where <b>Category</b> contains "Tyre" are imported when the filter below is checked (recommended, since a parts catalog usually has other categories too).</li>
      <li><b>Description</b> is parsed to detect the tyre size (e.g. 215/60R16); <b>Manufacturer</b> becomes the brand; <b>Manufacturer Part Number</b> becomes the model.</li>
      <li><b>Total Quantity</b> creates that many individual stock tyre records (each needs its own serial to be tracked and fitted separately) — 0 or blank still creates one placeholder record.</li>
      <li><b>Inventory no.</b> is used as the serial number (with a suffix if quantity &gt; 1); <b>Fleetio ID</b> is stored as the branding code.</li>
      <li>Re-running the same file is safe — rows whose serial number already exists are skipped, not duplicated.</li>
    </ul>
  </div>

  <?php if ($results): ?>
    <div class="tl-card" style="margin-bottom:18px;<?= isset($results['error']) ? 'background:#fde8e8;' : 'background:#e6f4ea;' ?>">
      <?php if (isset($results['error'])): ?>
        <b>Import failed:</b> <?= e($results['error']) ?>
      <?php else: ?>
        <b>Import complete.</b> <?= (int)$results['created'] ?> stock tyre(s) created, <?= (int)$results['skipped'] ?> skipped (non-tyre category or already imported).
        <?php if ($results['errors']): ?>
          <div style="margin-top:10px;"><b><?= count($results['errors']) ?> row(s) had issues:</b>
            <ul style="font-size:12.5px;"><?php foreach (array_slice($results['errors'],0,20) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
        <a href="stock.php" class="btn btn-primary btn-sm" style="margin-top:10px;">View Stock →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="tl-card" style="max-width:520px;">
    <form method="post" enctype="multipart/form-data">
      <label class="form-label">CSV or Excel File</label>
      <input class="form-control" type="file" name="file" accept=".csv,.xlsx,.xls,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required style="margin-bottom:12px;">
      <label style="font-size:12.5px;display:flex;align-items:center;gap:6px;margin-bottom:14px;">
        <input type="checkbox" name="only_tyres" value="1" checked> Only import rows where Category contains "Tyre"
      </label>
      <button class="btn btn-accent" type="submit">Upload & Import</button>
    </form>
  </div>
</div>
</div>
<?php include __DIR__ . '/includes/foot.php'; ?>

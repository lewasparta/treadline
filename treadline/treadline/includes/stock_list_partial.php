<?php
// Expects nothing special in scope besides db(); shows all STOCK tyres, draggable.
$stockTyres = db()->query("SELECT * FROM tyres WHERE status='STOCK' ORDER BY date_added DESC")->fetchAll();
if (!$stockTyres) {
    echo '<p class="text-muted" style="font-size:13px;">No stock tyres available. <a href="stock.php">Add one</a>.</p>';
} else {
    foreach ($stockTyres as $s) {
        echo '<div class="stock-tyre" draggable="true" data-tyre-id="'.(int)$s['id'].'" ondragstart="onStockDragStart(event,'.(int)$s['id'].')">
                <div><b>'.e($s['brand']).' '.e($s['size']).'</b><br><span class="text-muted">'.e($s['serial_number']).' · '.number_format($s['new_tread_mm'],1).'mm new</span></div>
                <span>&#8942;&#8942;</span>
              </div>';
    }
}

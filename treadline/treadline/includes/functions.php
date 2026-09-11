<?php
/**
 * TREADLINE - Core helper functions
 */

// ---------------------------------------------------------------
// AUTH
// ---------------------------------------------------------------
function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    $stmt = db()->prepare("SELECT u.*, r.name AS role_name, b.name AS branch_name
                           FROM users u JOIN roles r ON u.role_id = r.id
                           LEFT JOIN branches b ON u.branch_id = b.id
                           WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cache = $stmt->fetch() ?: null;
    return $cache;
}

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function require_role(array $roles): void {
    require_login();
    $u = current_user();
    if (!$u || !in_array($u['role_name'], $roles, true)) {
        http_response_code(403);
        die('<div style="padding:40px;font-family:sans-serif"><h2>403 - Access Denied</h2><p>Your role ('.htmlspecialchars($u['role_name'] ?? '').') does not have permission to view this page.</p><a href="dashboard.php">Back to dashboard</a></div>');
    }
}

function can_edit(): bool {
    $u = current_user();
    return $u && in_array($u['role_name'], ['ADMIN','FLEET_MANAGER','TYRE_INSPECTOR','SUPERVISOR'], true);
}

function is_readonly(): bool {
    $u = current_user();
    return $u && $u['role_name'] === 'MANAGEMENT';
}

function log_action(string $action, string $entity, ?int $entity_id, string $details = ''): void {
    $u = current_user();
    $stmt = db()->prepare("INSERT INTO audit_log (user_id, action, entity, entity_id, details) VALUES (?,?,?,?,?)");
    $stmt->execute([$u['id'] ?? null, $action, $entity, $entity_id, $details]);
}

// ---------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_check(): bool {
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

// ---------------------------------------------------------------
// SETTINGS (configurable thresholds)
// ---------------------------------------------------------------
function get_setting(string $key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        foreach (db()->query("SELECT setting_key, setting_value FROM settings") as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings[$key] ?? $default;
}

// ---------------------------------------------------------------
// TYRE STATUS / HEALTH CALCULATIONS
// ---------------------------------------------------------------
function tread_status(float $tread_mm): string {
    $critical = (float) get_setting('tread_critical_mm', 2.0);
    $watch = (float) get_setting('tread_watch_mm', 5.0);
    if ($tread_mm <= $critical) return 'CRITICAL';
    if ($tread_mm < $watch) return 'WATCH';
    return 'GOOD';
}

function status_color(string $status): string {
    return match ($status) {
        'CRITICAL' => '#dc3545',
        'WATCH' => '#e69138',
        'WARNING' => '#f0ad4e',
        'GOOD', 'EXCELLENT' => '#3c8a3c',
        default => '#888',
    };
}

function psi_status(float $actual, float $recommended): string {
    $tolerancePct = (float) get_setting('psi_tolerance_pct', 10);
    $low = $recommended * (1 - $tolerancePct / 100);
    $high = $recommended * (1 + $tolerancePct / 100);
    $criticalLow = $recommended * 0.7;
    if ($actual <= $criticalLow) return 'CRITICAL';
    if ($actual < $low) return 'LOW';
    if ($actual > $high) return 'HIGH';
    return 'NORMAL';
}

/**
 * Health score 0-100 based on tread %, PSI deviation, wear/damage flags, age, mileage, repairs.
 */
function calc_health_score(array $t): array {
    $score = 100;

    // Tread remaining (weight 40)
    $newTread = (float)($t['new_tread_mm'] ?? 8.0);
    $curTread = (float)($t['tread_avg'] ?? $t['current_tread_mm'] ?? $newTread);
    $remainPct = $newTread > 0 ? max(0, min(100, ($curTread / $newTread) * 100)) : 100;
    $score -= (100 - $remainPct) * 0.40;

    // PSI deviation (weight 15)
    if (!empty($t['psi_status'])) {
        $score -= match ($t['psi_status']) {
            'CRITICAL' => 15, 'LOW' => 8, 'HIGH' => 6, default => 0,
        };
    }

    // Condition flags (weight 20)
    $badSidewall = ['Cut','Crack','Bulge','Sidewall damage'];
    $badTread = ['Uneven wear','Cupping','Feathering','Chunking','Embedded object','Puncture','Centre wear','Shoulder wear','Inner wear','Outer wear'];
    if (!empty($t['sidewall_condition']) && in_array($t['sidewall_condition'], $badSidewall, true)) $score -= 12;
    if (!empty($t['tread_condition']) && in_array($t['tread_condition'], $badTread, true)) $score -= 8;
    if (!empty($t['foreign_object'])) $score -= 10;

    // Age (weight 10)
    if (!empty($t['purchase_date'])) {
        $days = (strtotime('now') - strtotime($t['purchase_date'])) / 86400;
        $expected = (float)($t['expected_lifespan_days'] ?? 730);
        if ($expected > 0) $score -= max(0, min(10, ($days / $expected) * 10));
    }

    // Mileage (weight 10)
    if (!empty($t['tyre_mileage']) && !empty($t['expected_mileage'])) {
        $pct = $t['expected_mileage'] > 0 ? $t['tyre_mileage'] / $t['expected_mileage'] : 0;
        $score -= max(0, min(10, $pct * 10));
    }

    // Repairs (weight 5)
    $repairs = (int)($t['total_repairs'] ?? 0);
    $score -= min(5, $repairs * 1.5);

    $score = (int) round(max(0, min(100, $score)));

    $excellent = (float) get_setting('health_excellent', 90);
    $good = (float) get_setting('health_good', 75);
    $watch = (float) get_setting('health_watch', 50);
    $warning = (float) get_setting('health_warning', 25);

    if ($score >= $excellent) $rating = 'EXCELLENT';
    elseif ($score >= $good) $rating = 'GOOD';
    elseif ($score >= $watch) $rating = 'WATCH';
    elseif ($score >= $warning) $rating = 'WARNING';
    else $rating = 'CRITICAL';

    return ['score' => $score, 'rating' => $rating];
}

function tyre_mileage(array $tyre, int $currentOdometer): int {
    // Simple: current vehicle odometer minus install odometer (if fitted).
    if (!empty($tyre['install_odometer']) && $tyre['current_vehicle_id']) {
        return max(0, $currentOdometer - (int)$tyre['install_odometer']);
    }
    return 0;
}

// ---------------------------------------------------------------
// MISC HELPERS
// ---------------------------------------------------------------
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function fmt_money($v): string {
    return get_setting('currency', 'KES') . ' ' . number_format((float)$v, 2);
}

function fmt_date(?string $d, string $fmt = 'd/m/Y'): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date($fmt, $ts) : '—';
}

function badge_class(string $status): string {
    return match (strtoupper($status)) {
        'CRITICAL' => 'badge-critical',
        'WATCH','WARNING','LOW','HIGH','MAINTENANCE','PENDING' => 'badge-watch',
        'GOOD','EXCELLENT','NORMAL','ACTIVE','APPROVED','IN_SERVICE' => 'badge-good',
        'STOCK' => 'badge-info',
        default => 'badge-muted',
    };
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond_json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function create_alert(?int $vehicleId, ?int $tyreId, string $type, string $priority, string $message, string $actionText = ''): void {
    $stmt = db()->prepare("INSERT INTO alerts (vehicle_id, tyre_id, type, priority, message, action_text) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$vehicleId, $tyreId, $type, $priority, $message, $actionText]);
}

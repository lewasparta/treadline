<?php
/**
 * TREADLINE - App bootstrap / global config
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Africa/Nairobi');

define('APP_NAME', 'TREADLINE');
define('APP_TAGLINE', 'SMART FLEET TYRE TRACKING');
define('BASE_URL', '');
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_URL', 'uploads');
define('SESSION_TIMEOUT_MIN', 60); // auto-logout after inactivity

if (file_exists(__DIR__ . '/env.php')) require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';

session_start();

// ---- Session timeout ----
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_MIN * 60)) {
        session_unset();
        session_destroy();
        header('Location: index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

require_once __DIR__ . '/../includes/functions.php';

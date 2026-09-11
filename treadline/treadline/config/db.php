<?php
/**
 * TREADLINE - Database Connection
 * Edit these values to match your MySQL server.
 */
define('DB_HOST', getenv('TREADLINE_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('TREADLINE_DB_NAME') ?: 'treadline');
define('DB_USER', getenv('TREADLINE_DB_USER') ?: 'root');
define('DB_PASS', getenv('TREADLINE_DB_PASS') ?: '');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die('<h2>Database connection failed</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p>Check <code>config/db.php</code> and run <code>sql/schema.sql</code> first. See README.md.</p>');
        }
    }
    return $pdo;
}

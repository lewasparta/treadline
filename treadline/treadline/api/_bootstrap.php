<?php
require_once __DIR__ . '/../config/config.php';
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !csrf_check()) {
    respond_json(['error' => 'Invalid session token. Please refresh the page and try again.'], 419);
}

$input = json_input();
$user = current_user();

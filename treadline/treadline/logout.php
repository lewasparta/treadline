<?php
require_once __DIR__ . '/config/config.php';
if (isset($_SESSION['user_id'])) log_action('LOGOUT', 'user', $_SESSION['user_id']);
session_unset();
session_destroy();
setcookie('tl_remember', '', time() - 3600, '/');
header('Location: index.php');
exit;

<?php
define('APP_RUNNING', true);

require_once __DIR__ . '/env.php';
loadEnv();

if (getenv('APP_DEBUG') === 'true') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => ($_SERVER['HTTPS'] ?? 'off') === 'on',
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';

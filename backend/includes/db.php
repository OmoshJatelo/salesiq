<?php
if (!defined('APP_RUNNING')) exit;

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'salesiq_db');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $pdo  = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false]
    );
    return $pdo;
}
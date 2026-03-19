<?php
if (!defined('APP_RUNNING')) exit;

function loadEnv(): void {
    $file = dirname(__DIR__, 2) . '/.env';
    if (!file_exists($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (!$key) continue;
        // putenv() is blocked on some hosts (suPHP) — use all three methods
        putenv("$key=$value");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
}

// Wrapper that checks all sources — works on suPHP hosts
function env(string $key, string $default = ''): string {
    return $_ENV[$key]
        ?? $_SERVER[$key]
        ?? getenv($key)
        ?: $default;
}
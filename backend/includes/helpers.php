<?php
if (!defined('APP_RUNNING')) exit;

// Base path of the project (e.g. '/Salesiq' or '' for web root).
// Set APP_BASE in your .env — no trailing slash.
define('BASE', rtrim(getenv('APP_BASE') ?: '', '/'));

/**
 * Build a URL relative to the project base.
 *   url('shop.php')       → /Salesiq/shop.php
 *   url('admin/orders.php') → /Salesiq/admin/orders.php
 */
function url(string $path): string {
    return BASE . '/' . ltrim($path, '/');
}

function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function flashSet(string $type, string $msg): void {
    $_SESSION['flash'][$type][] = $msg;
}

function flashGet(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function paginate(int $total, int $perPage = 12): array {
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min($page, $totalPages);
    return [
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'total_rows'  => $total,
        'offset'      => ($page - 1) * $perPage,
    ];
}

/** Delivery fee: free over KES 100, else KES 2 */
function deliveryFee(float $subtotal): float {
    return $subtotal >= 100 ? 0.0 : 2.0;
}
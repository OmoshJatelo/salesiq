<?php
/**
 * setup_db.php — One-time database setup script
 * Visit once, then DELETE immediately
 * Protects itself with a secret token
 */

// Simple protection — change this token
$secret = 'salesiq_setup_2024';
if (($_GET['token'] ?? '') !== $secret) {
    die('Access denied. Add ?token=salesiq_setup_2024 to the URL');
}

define('APP_RUNNING', true);
require_once __DIR__ . '/backend/includes/env.php';
loadEnv();
require_once __DIR__ . '/backend/includes/db.php';

echo "<pre style='font-family:monospace;background:#111;color:#0f0;padding:20px;'>";
echo "=== SalesIQ Database Setup ===\n\n";

try {
    $pdo = getDB();
    echo "✓ Connected to database\n\n";

    $sql = file_get_contents(__DIR__ . '/schema.sql');

    // Remove CREATE DATABASE and USE statements
    $sql = preg_replace('/^CREATE DATABASE.*?;/mi', '', $sql);
    $sql = preg_replace('/^USE .*?;/mi', '', $sql);

    // Split into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => strlen($s) > 5
    );

    $ok = 0; $skip = 0; $err = 0;
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
            echo "✓ OK\n";
            $ok++;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'already exists') ||
                str_contains($e->getMessage(), 'Duplicate entry')) {
                echo "~ SKIP (already exists)\n";
                $skip++;
            } else {
                echo "✗ ERROR: " . $e->getMessage() . "\n";
                $err++;
            }
        }
    }

    echo "\n=== Done: $ok OK, $skip skipped, $err errors ===\n\n";

    // Update prices to test values
    $prices = [
        ['Wireless Headphones', 2.00, 4.00],
        ['Running Shoes',       3.00, 6.00],
        ['Desk Lamp',           1.00, 3.00],
        ['Python Cookbook',     1.00, 2.00],
        ['Cotton T-Shirt',      1.00, 2.00],
        ['Mechanical Keyboard', 4.00, 8.00],
        ['Yoga Mat',            2.00, 5.00],
        ['Novel: Dust',         1.00, 3.00],
    ];
    foreach ($prices as [$name, $cost, $sell]) {
        $pdo->prepare('UPDATE products SET cost_price=?, selling_price=? WHERE name=?')
            ->execute([$cost, $sell, $name]);
    }
    echo "✓ Product prices updated to test values\n";

    // Fix admin password
    $pdo->prepare(
        "UPDATE users SET password_hash=? WHERE email='admin@salesiq.co.ke'"
    )->execute(['$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi']);
    echo "✓ Admin password set (admin123)\n";

    // Add M-Pesa columns if missing
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN mpesa_checkout_id VARCHAR(100) NULL AFTER mpesa_code");
        echo "✓ Added mpesa_checkout_id column\n";
    } catch (PDOException $e) {
        echo "~ mpesa_checkout_id already exists\n";
    }

    // Verify
    $counts = [
        'users'      => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'products'   => $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'categories' => $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    ];
    echo "\n=== Database contents ===\n";
    foreach ($counts as $table => $count) {
        echo "  $table: $count rows\n";
    }

    echo "\n✅ Setup complete! DELETE this file now.\n";
    echo "Login: admin@salesiq.co.ke / admin123\n";

} catch (Exception $e) {
    echo "❌ FATAL: " . $e->getMessage() . "\n";
}
echo "</pre>";
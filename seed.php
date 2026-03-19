<?php
/**
 * seed.php — Insert admin user and products
 * Visit once, then DELETE immediately
 */
if (($_GET['token'] ?? '') !== 'salesiq_setup_2024') {
    die('Access denied.');
}

define('APP_RUNNING', true);
require_once __DIR__ . '/backend/includes/env.php';
loadEnv();
require_once __DIR__ . '/backend/includes/db.php';

echo "<pre style='font-family:monospace;background:#111;color:#0f0;padding:20px;'>";
echo "=== SalesIQ Seed ===\n\n";

try {
    $pdo = getDB();
    echo "Connected to database\n\n";

    // Insert admin user
    $pdo->exec("
        INSERT IGNORE INTO users (name, email, password_hash, role) VALUES
        ('Admin', 'admin@salesiq.co.ke',
         '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
         'admin')
    ");
    $userId = $pdo->lastInsertId();
    echo "Admin user: " . ($userId ? "created (id=$userId)" : "already exists") . "\n";

    // Get admin user id
    $adminId = $pdo->query("SELECT id FROM users WHERE email='admin@salesiq.co.ke'")->fetchColumn();
    echo "Admin id: $adminId\n\n";

    // Insert products
    $products = [
        ['Wireless Headphones',  'Noise-cancelling Bluetooth, 30 hr battery.',         2.00, 4.00, 45, 10, 1],
        ['Running Shoes',        'Lightweight trainers, cushioned sole, sizes 38-46.',  3.00, 6.00, 30,  8, 4],
        ['Desk Lamp',            'LED, 3 brightness modes, USB charging port.',         1.00, 3.00, 60, 15, 3],
        ['Python Cookbook',      'OReilly 3rd edition, advanced recipes.',              1.00, 2.00, 20,  5, 5],
        ['Cotton T-Shirt',       'Organic cotton, sizes XS-XXL, 5 colours.',            1.00, 2.00,100, 20, 2],
        ['Mechanical Keyboard',  'TKL, Cherry MX switches, RGB, aluminium frame.',      4.00, 8.00, 25,  5, 1],
        ['Yoga Mat',             'Non-slip, 6mm thick, 183x61cm + carry strap.',        2.00, 5.00, 40, 10, 4],
        ['Novel: Dust',          'Yvonne Adhiambo Owuor. Award-winning. Paperback.',    1.00, 3.00, 50, 10, 5],
    ];

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO products
            (name, description, cost_price, selling_price, stock, reorder_level, category_id, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($products as [$name, $desc, $cost, $sell, $stock, $reorder, $cat]) {
        $stmt->execute([$name, $desc, $cost, $sell, $stock, $reorder, $cat, $adminId]);
        echo "Product '$name': " . ($pdo->lastInsertId() ? "inserted" : "skipped") . "\n";
    }

    // Verify
    echo "\n=== Counts ===\n";
    echo "users:      " . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()    . "\n";
    echo "products:   " . $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() . "\n";
    echo "categories: " . $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(). "\n";

    echo "\nDone! DELETE this file now.\n";
    echo "Login: admin@salesiq.co.ke / admin123\n";

} catch (Exception $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
}
echo "</pre>";
// Visit once, then DELETE immediately
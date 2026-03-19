<?php
if (($_GET['token'] ?? '') !== 'salesiq_setup_2024') die('Access denied.');

define('APP_RUNNING', true);
require_once __DIR__ . '/backend/includes/env.php';
loadEnv();
require_once __DIR__ . '/backend/includes/db.php';

echo "<pre style='font-family:monospace;background:#111;color:#0f0;padding:20px;'>";
echo "=== Fix M-Pesa Tables ===\n\n";

try {
    $pdo = getDB();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mpesa_transactions (
            id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id            INT UNSIGNED NOT NULL,
            checkout_request_id VARCHAR(100) NOT NULL UNIQUE,
            merchant_request_id VARCHAR(100) NOT NULL,
            phone               VARCHAR(20)  NOT NULL,
            amount              DECIMAL(10,2) NOT NULL,
            status              ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
            result_code         VARCHAR(10)  NULL,
            result_desc         VARCHAR(255) NULL,
            mpesa_receipt       VARCHAR(30)  NULL,
            created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_mpesa_order FOREIGN KEY (order_id) REFERENCES orders(id)
        ) ENGINE=InnoDB
    ");
    echo "✓ mpesa_transactions table created\n";

    // Add mpesa_checkout_id column to orders if missing
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN mpesa_checkout_id VARCHAR(100) NULL AFTER mpesa_code");
        echo "✓ mpesa_checkout_id column added to orders\n";
    } catch (PDOException $e) {
        echo "~ mpesa_checkout_id already exists\n";
    }

    // Show all tables
    echo "\n=== Tables in database ===\n";
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        echo "  $table\n";
    }

    echo "\nDone! DELETE this file now.\n";

} catch (Exception $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
}
echo "</pre>";
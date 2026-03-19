<?php
if (($_GET['token'] ?? '') !== 'salesiq_setup_2024') die('Access denied.');

define('APP_RUNNING', true);
require_once __DIR__ . '/backend/includes/env.php';
loadEnv();
require_once __DIR__ . '/backend/includes/db.php';

$pdo = getDB();

// Check current admin
$user = $pdo->query("SELECT id, email, password_hash FROM users WHERE email='admin@salesiq.co.ke'")->fetch();
echo "Current user: <pre>" . print_r($user, true) . "</pre>";

// Set a fresh hash for admin123
$hash = password_hash('admin123', PASSWORD_BCRYPT);
$pdo->prepare("UPDATE users SET password_hash=? WHERE email='admin@salesiq.co.ke'")->execute([$hash]);
echo "Hash updated to: $hash <br>";

// Verify it works
$updated = $pdo->query("SELECT password_hash FROM users WHERE email='admin@salesiq.co.ke'")->fetchColumn();
echo "Verify login: " . (password_verify('admin123', $updated) ? '✅ Password works' : '❌ Still broken');
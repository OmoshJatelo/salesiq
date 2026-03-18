<?php
require_once __DIR__ . '/backend/includes/bootstrap.php';
if (!isLoggedIn())          { header('Location: ' . url('shop.php'));            exit; }
if (isStaff())              { header('Location: ' . url('admin/dashboard.php')); exit; }
header('Location: ' . url('shop.php')); exit;

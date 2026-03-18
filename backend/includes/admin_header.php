<?php
if (!defined('APP_RUNNING')) exit;
$pageTitle    = $pageTitle ?? 'Admin';
$user         = getCurrentUser();
$flash        = flashGet();
$curPage      = basename($_SERVER['SCRIPT_FILENAME']);
$pendingCount = (int)getDB()
    ->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing')")
    ->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= esc($pageTitle) ?> | SalesIQ Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('frontend/css/admin.css') ?>">
</head>
<body class="d-flex flex-column min-vh-100 <?= ($user && $user['theme_pref']==='dark') ? 'theme-dark' : '' ?>">

<nav class="navbar navbar-expand-lg navbar-dark admin-nav shadow-sm">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= url('admin/dashboard.php') ?>">
      <i class="bi bi-bar-chart-line-fill"></i>
      SalesIQ
      <span class="badge bg-warning text-dark ms-1" style="font-size:.6rem;vertical-align:middle;">ADMIN</span>
    </a>
    <button class="navbar-toggler border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#adminNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav me-auto gap-1">
        <li class="nav-item">
          <a class="nav-link <?= $curPage==='dashboard.php'?'active':'' ?>" href="<?= url('admin/dashboard.php') ?>">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $curPage==='orders.php'?'active':'' ?>" href="<?= url('admin/orders.php') ?>">
            <i class="bi bi-bag me-1"></i>Orders
            <?php if ($pendingCount > 0): ?>
            <span class="badge bg-danger ms-1" style="font-size:.65rem;"><?= $pendingCount ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $curPage==='products.php'?'active':'' ?>" href="<?= url('admin/products.php') ?>">
            <i class="bi bi-box-seam me-1"></i>Products
          </a>
        </li>
        <?php if (isAdmin()): ?>
        <li class="nav-item">
          <a class="nav-link <?= $curPage==='customers.php'?'active':'' ?>" href="<?= url('admin/customers.php') ?>">
            <i class="bi bi-people me-1"></i>Customers
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $curPage==='categories.php'?'active':'' ?>" href="<?= url('admin/categories.php') ?>">
            <i class="bi bi-tags me-1"></i>Categories
          </a>
        </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav align-items-center gap-2">
        <li class="nav-item">
          <a class="nav-link text-white-50 small" href="<?= url('shop.php') ?>" target="_blank">
            <i class="bi bi-shop me-1"></i>View Shop
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= esc($user['name']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= url('settings.php') ?>">
              <i class="bi bi-gear me-2"></i>Settings
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>">
              <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<?php if (!empty($flash)): ?>
<div class="container-fluid px-4 mt-3">
  <?php foreach ($flash as $type => $msgs): foreach ($msgs as $msg): ?>
  <div class="alert alert-<?= esc($type) ?> alert-dismissible fade show">
    <?= esc($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endforeach; endforeach; ?>
</div>
<?php endif; ?>

<main class="container-fluid px-4 py-4 flex-grow-1">

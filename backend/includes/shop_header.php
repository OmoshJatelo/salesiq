<?php
if (!defined('APP_RUNNING')) exit;
$pageTitle = $pageTitle ?? 'Shop';
$user      = getCurrentUser();
$flash     = flashGet();

$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) $cartCount += $item['qty'];
}
$navCats = getDB()->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= esc($pageTitle) ?> | SalesIQ</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('frontend/css/shop.css') ?>">
</head>
<body class="d-flex flex-column min-vh-100 <?= ($user && $user['theme_pref']==='dark') ? 'theme-dark' : '' ?>">

<div class="topbar">
  <div class="container d-flex justify-content-between">
    <span><i class="bi bi-geo-alt me-1"></i>Nairobi, Kenya</span>
    <span class="d-none d-sm-inline">Free delivery on orders over KES 100</span>
  </div>
</div>

<nav class="shop-nav sticky-top">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between gap-3 py-3">
      <a href="<?= url('shop.php') ?>" class="shop-brand">
        <i class="bi bi-bag-heart-fill"></i><span>SalesIQ</span>
      </a>

      <form class="shop-search d-none d-md-flex" method="GET" action="<?= url('shop.php') ?>">
        <input type="text" name="q" class="shop-search-input"
               placeholder="Search products…" value="<?= esc($_GET['q'] ?? '') ?>">
        <button class="shop-search-btn" type="submit"><i class="bi bi-search"></i></button>
      </form>

      <div class="d-flex align-items-center gap-2">
        <?php if ($user): ?>
        <div class="dropdown">
          <button class="nav-icon-btn dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i>
            <span class="d-none d-sm-inline ms-1"><?= esc(explode(' ',$user['name'])[0]) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><h6 class="dropdown-header"><?= esc($user['name']) ?></h6></li>
            <li><a class="dropdown-item" href="<?= url('my_orders.php') ?>">
              <i class="bi bi-receipt me-2"></i>My Orders
            </a></li>
            <li><a class="dropdown-item" href="<?= url('settings.php') ?>">
              <i class="bi bi-gear me-2"></i>Settings
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>">
              <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a></li>
          </ul>
        </div>
        <?php else: ?>
        <a href="<?= url('login.php') ?>" class="nav-icon-btn">
          <i class="bi bi-person"></i>
          <span class="d-none d-sm-inline ms-1">Login</span>
        </a>
        <?php endif; ?>

        <a href="<?= url('cart.php') ?>" class="nav-icon-btn cart-btn">
          <i class="bi bi-cart3"></i>
          <?php if ($cartCount > 0): ?>
          <span class="cart-badge"><?= $cartCount ?></span>
          <?php endif; ?>
        </a>
      </div>
    </div>

    <div class="cat-strip pb-2 d-none d-md-flex flex-wrap gap-1">
      <a href="<?= url('shop.php') ?>" class="cat-pill <?= (empty($_GET['cat']) && empty($_GET['q'])) ? 'active' : '' ?>">All</a>
      <?php foreach ($navCats as $c): ?>
      <a href="<?= url('shop.php') ?>?cat=<?= $c['id'] ?>"
         class="cat-pill <?= (($_GET['cat'] ?? '') == $c['id']) ? 'active' : '' ?>">
        <?= esc($c['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <form class="d-md-none pb-2" method="GET" action="<?= url('shop.php') ?>">
      <div class="input-group input-group-sm">
        <input type="text" name="q" class="form-control" placeholder="Search…" value="<?= esc($_GET['q'] ?? '') ?>">
        <button class="btn btn-accent" type="submit"><i class="bi bi-search"></i></button>
      </div>
    </form>
  </div>
</nav>

<?php if (!empty($flash)): ?>
<div class="container mt-3">
  <?php foreach ($flash as $type => $msgs): foreach ($msgs as $msg): ?>
  <div class="alert alert-<?= esc($type) ?> alert-dismissible fade show">
    <?= esc($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endforeach; endforeach; ?>
</div>
<?php endif; ?>

<main class="container py-4 flex-grow-1">
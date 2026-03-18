<?php
require_once __DIR__ . '/backend/includes/bootstrap.php';

$pdo = getDB();
$q   = trim($_GET['q']   ?? '');
$cat = (int)($_GET['cat'] ?? 0);

// Build filtered query
$where  = ['p.is_active = 1', 'p.stock > 0'];
$params = [];
if ($q !== '') {
    $where[]  = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $cat;
}
$w = implode(' AND ', $where);

$total  = (int)$pdo->prepare("SELECT COUNT(*) FROM products p WHERE $w")
    ->execute($params) ?: 0;
$cq = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $w");
$cq->execute($params);
$total  = (int)$cq->fetchColumn();
$paging = paginate($total, 12);

$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS cat_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE $w ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$paging['per_page'], $paging['offset']]));
$products = $stmt->fetchAll();

// Category name for heading
$catName = '';
if ($cat > 0) {
    $cs = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
    $cs->execute([$cat]);
    $catName = $cs->fetchColumn() ?: '';
}

$catIcons = ['Electronics'=>'bi-cpu','Clothing'=>'bi-bag-fill',
             'Home & Garden'=>'bi-house-fill','Sports'=>'bi-trophy-fill','Books'=>'bi-book-fill'];

$pageTitle = $catName ?: ($q ? "\"$q\"" : 'Shop');
include __DIR__ . '/backend/includes/shop_header.php';
?>

<?php if (!$q && !$cat): ?>
<!-- Hero -->
<div class="rounded-3 mb-4 p-4 p-md-5"
     style="background:linear-gradient(135deg,#1a5276,#2e86c1);color:#fff;min-height:170px;display:flex;align-items:center;">
  <div>
    <h2 class="fw-bold mb-1" style="font-size:1.75rem;">Welcome to SalesIQ Shop</h2>
    <p class="mb-3" style="opacity:.8;">Quality products · Secure M-Pesa checkout · Fast delivery</p>
    <a href="#products" class="btn btn-light btn-sm fw-semibold px-3">
      <i class="bi bi-arrow-down me-1"></i>Browse Products
    </a>
  </div>
</div>
<?php endif; ?>

<!-- Heading -->
<div class="d-flex justify-content-between align-items-center mb-3" id="products">
  <div>
    <h5 class="fw-bold mb-0" style="color:var(--primary);">
      <?php if ($q): ?>
        <i class="bi bi-search me-1"></i>Results for "<?= esc($q) ?>"
      <?php elseif ($catName): ?>
        <i class="bi bi-tag me-1"></i><?= esc($catName) ?>
      <?php else: ?>
        <i class="bi bi-grid me-1"></i>All Products
      <?php endif; ?>
    </h5>
    <small style="color:var(--muted);"><?= number_format($total) ?> product<?= $total !== 1 ? 's' : '' ?></small>
  </div>
  <?php if ($q || $cat): ?>
  <a href="<?= url('shop.php') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-x me-1"></i>Clear
  </a>
  <?php endif; ?>
</div>

<!-- Grid -->
<?php if (empty($products)): ?>
<div class="empty-state">
  <i class="bi bi-search"></i>
  <p class="fw-semibold fs-5 mb-1">Nothing found</p>
  <p class="mb-3">Try a different search or browse all products.</p>
  <a href="<?= url('shop.php') ?>" class="btn-primary-shop">View all products</a>
</div>

<?php else: ?>
<div class="product-grid">
<?php foreach ($products as $p):
    $icon  = $catIcons[$p['cat_name'] ?? ''] ?? 'bi-box';
    $isLow = $p['stock'] > 0 && $p['stock'] <= $p['reorder_level'];
?>
<div class="product-card">
  <a href="<?= url('product.php?id=') ?><?= $p['id'] ?>" class="product-img d-block text-decoration-none">
    <?php if ($p['image_url']): ?>
    <img src="<?= esc($p['image_url']) ?>" alt="<?= esc($p['name']) ?>" loading="lazy">
    <?php else: ?>
    <div class="product-img-placeholder"><i class="bi <?= $icon ?>"></i></div>
    <?php endif; ?>
    <?php if ($isLow): ?><span class="stock-badge">Low stock</span><?php endif; ?>
  </a>
  <div class="product-body">
    <div class="product-cat"><?= esc($p['cat_name'] ?? '') ?></div>
    <a href="<?= url('product.php?id=') ?><?= $p['id'] ?>" class="product-name d-block text-decoration-none">
      <?= esc($p['name']) ?>
    </a>
    <p class="product-desc"><?= esc($p['description']) ?></p>
    <div class="product-price">
      KES <?= number_format($p['selling_price'], 2) ?>
      <small class="ms-2"><?= $p['stock'] ?> left</small>
    </div>
    <form method="POST" action="<?= url('cart.php?action=add') ?>">
      <input type="hidden" name="csrf_token"  value="<?= esc($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="product_id"  value="<?= $p['id'] ?>">
      <input type="hidden" name="qty"         value="1">
      <input type="hidden" name="redirect"    value="<?= url('shop.php') . ($q ? '?q='.urlencode($q) : ($cat ? '?cat='.$cat : '')) ?>">
      <button type="submit" class="btn-cart">
        <i class="bi bi-cart-plus"></i> Add to Cart
      </button>
    </form>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($paging['total_pages'] > 1): ?>
<nav class="mt-4">
  <ul class="pagination justify-content-center">
    <li class="page-item <?= $paging['page'] <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="?page=<?= $paging['page']-1 ?><?= $q?'&q='.urlencode($q):'' ?><?= $cat?'&cat='.$cat:'' ?>">
        <i class="bi bi-chevron-left"></i>
      </a>
    </li>
    <?php for ($i = 1; $i <= $paging['total_pages']; $i++): ?>
    <li class="page-item <?= $i === $paging['page'] ? 'active' : '' ?>">
      <a class="page-link" href="?page=<?= $i ?><?= $q?'&q='.urlencode($q):'' ?><?= $cat?'&cat='.$cat:'' ?>">
        <?= $i ?>
      </a>
    </li>
    <?php endfor; ?>
    <li class="page-item <?= $paging['page'] >= $paging['total_pages'] ? 'disabled' : '' ?>">
      <a class="page-link" href="?page=<?= $paging['page']+1 ?><?= $q?'&q='.urlencode($q):'' ?><?= $cat?'&cat='.$cat:'' ?>">
        <i class="bi bi-chevron-right"></i>
      </a>
    </li>
  </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/backend/includes/shop_footer.php'; ?>
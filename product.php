<?php
require_once __DIR__ . '/backend/includes/bootstrap.php';

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, c.name AS cat_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.id = ? AND p.is_active = 1'
);
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) { flashSet('danger','Product not found.'); header('Location: ' . url('shop.php')); exit; }

// Related products
$rel = $pdo->prepare(
    'SELECT * FROM products
     WHERE category_id = ? AND id != ? AND is_active = 1 AND stock > 0 LIMIT 4'
);
$rel->execute([$p['category_id'], $p['id']]);
$related = $rel->fetchAll();

$catIcons = ['Electronics'=>'bi-cpu','Clothing'=>'bi-bag-fill',
             'Home & Garden'=>'bi-house-fill','Sports'=>'bi-trophy-fill','Books'=>'bi-book-fill'];
$icon = $catIcons[$p['cat_name'] ?? ''] ?? 'bi-box';

$pageTitle = $p['name'];
include __DIR__ . '/backend/includes/shop_header.php';
?>

<!-- Breadcrumb -->
<nav class="mb-3" style="font-size:.82rem;">
  <ol class="breadcrumb mb-0">
    <li class="breadcrumb-item"><a href="<?= url('shop.php') ?>">Shop</a></li>
    <li class="breadcrumb-item">
      <a href="<?= url('shop.php?cat=') ?><?= $p['category_id'] ?>"><?= esc($p['cat_name']) ?></a>
    </li>
    <li class="breadcrumb-item active"><?= esc($p['name']) ?></li>
  </ol>
</nav>

<div class="row g-4 mb-5">

  <!-- Image -->
  <div class="col-md-5">
    <div class="rounded-3 overflow-hidden d-flex align-items-center justify-content-center"
         style="background:var(--bg);aspect-ratio:1;min-height:260px;">
      <?php if ($p['image_url']): ?>
      <img src="<?= esc($p['image_url']) ?>" alt="<?= esc($p['name']) ?>"
           style="width:100%;height:100%;object-fit:cover;">
      <?php else: ?>
      <i class="bi <?= $icon ?>" style="font-size:6rem;opacity:.2;color:var(--muted);"></i>
      <?php endif; ?>
    </div>
  </div>

  <!-- Info -->
  <div class="col-md-7">
    <div class="product-cat mb-1"><?= esc($p['cat_name']) ?></div>
    <h2 class="fw-bold mb-2" style="font-size:1.55rem;"><?= esc($p['name']) ?></h2>

    <div class="d-flex align-items-center gap-3 mb-3">
      <span style="font-size:1.7rem;font-weight:700;color:var(--primary);">
        KES <?= number_format($p['selling_price'], 2) ?>
      </span>
      <?php if ($p['stock'] > 0): ?>
      <span class="badge" style="background:rgba(39,174,96,.12);color:#1e8449;padding:.4em .8em;font-size:.8rem;">
        <i class="bi bi-check-circle me-1"></i><?= $p['stock'] ?> in stock
      </span>
      <?php else: ?>
      <span class="badge" style="background:rgba(231,76,60,.12);color:#a93226;padding:.4em .8em;font-size:.8rem;">
        Out of stock
      </span>
      <?php endif; ?>
    </div>

    <p style="color:var(--muted);line-height:1.75;" class="mb-4"><?= esc($p['description']) ?></p>

    <?php if ($p['stock'] > 0): ?>
    <form method="POST" action="<?= url('cart.php?action=add') ?>" class="d-flex gap-2 align-items-center mb-3">
      <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
      <input type="hidden" name="redirect"   value="<?= url('product.php?id=' . $p['id']) ?>">
      <div class="qty-ctrl">
        <button type="button" onclick="adjQty(-1)">−</button>
        <input type="number" name="qty" id="qtyInput" value="1" min="1" max="<?= $p['stock'] ?>">
        <button type="button" onclick="adjQty(1)">+</button>
      </div>
      <button type="submit" class="btn-cart" style="flex:1;max-width:200px;">
        <i class="bi bi-cart-plus"></i> Add to Cart
      </button>
    </form>

    <a href="<?= url('checkout.php?buy_now=') ?><?= $p['id'] ?>"
       class="btn-primary-shop mb-4" style="border-radius:var(--radius-sm);">
      <i class="bi bi-lightning-charge-fill"></i> Buy Now
    </a>
    <?php endif; ?>

    <div class="divider"></div>
    <div class="d-flex flex-wrap gap-4" style="font-size:.82rem;color:var(--muted);">
      <span><i class="bi bi-shield-check me-1" style="color:var(--green);"></i>Secure M-Pesa checkout</span>
      <span><i class="bi bi-truck me-1" style="color:var(--accent);"></i>Fast delivery</span>
      <span><i class="bi bi-arrow-counterclockwise me-1"></i>Easy returns</span>
    </div>
  </div>
</div>

<!-- Related -->
<?php if (!empty($related)): ?>
<h5 class="fw-bold mb-3" style="color:var(--primary);">You might also like</h5>
<div class="product-grid">
<?php foreach ($related as $r):
    $ri = $catIcons[$p['cat_name'] ?? ''] ?? 'bi-box';
?>
<div class="product-card">
  <a href="<?= url('product.php?id=') ?><?= $r['id'] ?>" class="product-img d-block text-decoration-none">
    <?php if ($r['image_url']): ?>
    <img src="<?= esc($r['image_url']) ?>" alt="<?= esc($r['name']) ?>" loading="lazy">
    <?php else: ?>
    <div class="product-img-placeholder"><i class="bi <?= $ri ?>"></i></div>
    <?php endif; ?>
  </a>
  <div class="product-body">
    <a href="<?= url('product.php?id=') ?><?= $r['id'] ?>" class="product-name d-block text-decoration-none">
      <?= esc($r['name']) ?>
    </a>
    <div class="product-price">KES <?= number_format($r['selling_price'], 2) ?></div>
    <form method="POST" action="<?= url('cart.php?action=add') ?>">
      <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="product_id" value="<?= $r['id'] ?>">
      <input type="hidden" name="qty"        value="1">
      <input type="hidden" name="redirect"   value="<?= url('product.php?id=' . $p['id']) ?>">
      <button type="submit" class="btn-cart"><i class="bi bi-cart-plus"></i> Add to Cart</button>
    </form>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$extraScript = <<<JS
function adjQty(d) {
    var i = document.getElementById('qtyInput');
    i.value = Math.max(1, Math.min(parseInt(i.value) + d, parseInt(i.max)));
}
JS;
include __DIR__ . '/backend/includes/shop_footer.php';
?>
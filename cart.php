<?php
require_once __DIR__ . '/backend/includes/bootstrap.php';

$pdo    = getDB();
$action = $_GET['action'] ?? 'view';

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// ── ADD ───────────────────────────────────────────────────────────────────
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { flashSet('danger','Invalid request.'); header('Location: ' . url('shop.php')); exit; }

    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'] ?? url('shop.php');
        flashSet('warning','Please sign in to add items to your cart.');
        header('Location: ' . url('login.php')); exit;
    }

    if (isStaff()) { header('Location: ' . url('admin/dashboard.php')); exit; }

    $pid      = (int)($_POST['product_id'] ?? 0);
    $qty      = max(1, (int)($_POST['qty'] ?? 1));
    $redirect = $_POST['redirect'] ?? url('shop.php');

    $s = $pdo->prepare('SELECT id,name,selling_price,stock FROM products WHERE id=? AND is_active=1');
    $s->execute([$pid]);
    $pr = $s->fetch();

    if (!$pr) { flashSet('danger','Product not found.'); header('Location: '.$redirect); exit; }

    $current  = $_SESSION['cart'][$pid]['qty'] ?? 0;
    $newQty   = min($current + $qty, $pr['stock']);
    $_SESSION['cart'][$pid] = ['qty'=>$newQty,'name'=>$pr['name'],'price'=>$pr['selling_price']];

    flashSet('success', '"'.htmlspecialchars($pr['name'],ENT_QUOTES).'" added to your cart.');
    header('Location: '.$redirect); exit;
}

// ── UPDATE ────────────────────────────────────────────────────────────────
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { header('Location: ' . url('cart.php')); exit; }
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    if ($qty <= 0) {
        unset($_SESSION['cart'][$pid]);
    } else {
        $s = $pdo->prepare('SELECT stock FROM products WHERE id=?');
        $s->execute([$pid]);
        $stock = (int)($s->fetchColumn() ?: 0);
        $_SESSION['cart'][$pid]['qty'] = min($qty, $stock);
    }
    header('Location: ' . url('cart.php')); exit;
}

// ── REMOVE ────────────────────────────────────────────────────────────────
if ($action === 'remove') {
    $pid = (int)($_GET['id'] ?? 0);
    unset($_SESSION['cart'][$pid]);
    flashSet('success','Item removed from cart.');
    header('Location: ' . url('cart.php')); exit;
}

// ── VIEW — validate cart against DB ──────────────────────────────────────
$items    = [];
$subtotal = 0.0;

if (!empty($_SESSION['cart'])) {
    $ids  = array_keys($_SESSION['cart']);
    $in   = implode(',', array_fill(0, count($ids), '?'));
    $rows = $pdo->prepare("SELECT * FROM products WHERE id IN ($in)");
    $rows->execute($ids);
    $db   = [];
    foreach ($rows->fetchAll() as $r) $db[$r['id']] = $r;

    foreach ($_SESSION['cart'] as $pid => $item) {
        $pr = $db[$pid] ?? null;
        if (!$pr || !$pr['is_active']) { unset($_SESSION['cart'][$pid]); continue; }
        $qty = min($item['qty'], $pr['stock']);
        if ($qty < 1) { unset($_SESSION['cart'][$pid]); continue; }
        $_SESSION['cart'][$pid]['qty']   = $qty;
        $_SESSION['cart'][$pid]['price'] = $pr['selling_price'];
        $line     = $qty * $pr['selling_price'];
        $items[]  = ['product'=>$pr, 'qty'=>$qty, 'line'=>$line];
        $subtotal += $line;
    }
}
$delivery = deliveryFee($subtotal);
$total    = $subtotal + $delivery;

$pageTitle = 'Shopping Cart';
include __DIR__ . '/backend/includes/shop_header.php';
?>

<h4 class="fw-bold mb-4" style="color:var(--primary);">
  <i class="bi bi-cart3 me-2"></i>Shopping Cart
  <?php if (!empty($items)): ?>
  <small class="fs-6 fw-normal" style="color:var(--muted);">(<?= count($items) ?> item<?= count($items)>1?'s':'' ?>)</small>
  <?php endif; ?>
</h4>

<?php if (empty($items)): ?>
<div class="empty-state">
  <i class="bi bi-cart-x"></i>
  <p class="fw-semibold fs-5 mb-1">Your cart is empty</p>
  <p class="mb-3">Browse our shop and add something you like.</p>
  <a href="<?= url('shop.php') ?>" class="btn-primary-shop">
    <i class="bi bi-bag"></i> Continue Shopping
  </a>
</div>

<?php else: ?>
<div class="row g-4">

  <!-- Items -->
  <div class="col-lg-8">
    <div class="cart-wrap">
      <table class="cart-table">
        <thead>
          <tr>
            <th>Product</th>
            <th class="text-center">Qty</th>
            <th class="text-end">Price</th>
            <th class="text-end">Total</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): $pr = $item['product']; ?>
        <tr>
          <td>
            <a href="<?= url('product.php?id=') ?><?= $pr['id'] ?>" class="fw-semibold text-decoration-none"
               style="color:var(--text);"><?= esc($pr['name']) ?></a>
            <br><small style="color:var(--muted);">KES <?= number_format($pr['selling_price'],2) ?> each</small>
          </td>
          <td class="text-center">
            <form method="POST" action="<?= url('cart.php?action=update') ?>" class="d-inline">
              <input type="hidden" name="csrf_token"  value="<?= esc($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="product_id"  value="<?= $pr['id'] ?>">
              <div class="qty-ctrl">
                <button type="submit" name="qty" value="<?= $item['qty']-1 ?>">−</button>
                <input type="number" value="<?= $item['qty'] ?>" min="0" max="<?= $pr['stock'] ?>"
                       onchange="this.form.elements['qty'].value=this.value;this.form.submit();">
                <button type="submit" name="qty" value="<?= $item['qty']+1 ?>">+</button>
              </div>
            </form>
          </td>
          <td class="text-end fw-semibold">KES <?= number_format($pr['selling_price'],2) ?></td>
          <td class="text-end fw-bold" style="color:var(--primary);">KES <?= number_format($item['line'],2) ?></td>
          <td class="text-end">
            <a href="<?= url('cart.php?action=remove&id=') ?><?= $pr['id'] ?>"
               class="text-danger" title="Remove"><i class="bi bi-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="mt-3">
      <a href="<?= url('shop.php') ?>" style="font-size:.87rem;color:var(--muted);">
        <i class="bi bi-arrow-left me-1"></i>Continue Shopping
      </a>
    </div>
  </div>

  <!-- Summary -->
  <div class="col-lg-4">
    <div class="summary-box">
      <h6 class="fw-bold mb-3" style="color:var(--primary);">Order Summary</h6>
      <div class="summary-row"><span>Subtotal</span><span>KES <?= number_format($subtotal,2) ?></span></div>
      <div class="summary-row">
        <span>Delivery</span>
        <?php if ($delivery == 0): ?>
        <span class="fw-semibold" style="color:var(--green);">Free</span>
        <?php else: ?>
        <span>KES <?= number_format($delivery,2) ?></span>
        <?php endif; ?>
      </div>
      <div class="summary-row total">
        <span>Total</span><span>KES <?= number_format($total,2) ?></span>
      </div>
      <?php if ($delivery > 0): ?>
      <p class="small mb-3 mt-1" style="color:var(--muted);">
        Add KES <?= number_format(5000 - $subtotal, 2) ?> more for free delivery.
      </p>
      <?php else: ?>
      <p class="small mb-3 mt-1" style="color:var(--green);">
        <i class="bi bi-check-circle me-1"></i>You qualify for free delivery!
      </p>
      <?php endif; ?>

      <?php if (isLoggedIn()): ?>
      <a href="<?= url('checkout.php') ?>"
         class="btn-primary-shop w-100 py-2 justify-content-center"
         style="border-radius:var(--radius-sm);font-size:.95rem;">
        <i class="bi bi-lock-fill"></i> Proceed to Checkout
      </a>
      <?php else: ?>
      <a href="<?= url('login.php') ?>"
         class="btn-primary-shop w-100 py-2 justify-content-center"
         style="border-radius:var(--radius-sm);">
        <i class="bi bi-person"></i> Sign In to Checkout
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/backend/includes/shop_footer.php'; ?>
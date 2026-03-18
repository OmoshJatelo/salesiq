<?php
require_once __DIR__ . '/backend/includes/bootstrap.php';
requireAuth();
if (isStaff()) { header('Location: ' . url('admin/dashboard.php')); exit; }

$pdo = getDB();
$uid = $_SESSION['user_id'];

$s = $pdo->prepare(
    'SELECT o.*, COUNT(oi.id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.customer_id = ?
     GROUP BY o.id ORDER BY o.created_at DESC'
);
$s->execute([$uid]);
$orders = $s->fetchAll();

$pageTitle = 'My Orders';
include __DIR__ . '/backend/includes/shop_header.php';
?>

<h4 class="fw-bold mb-4" style="color:var(--primary);">
  <i class="bi bi-receipt me-2"></i>My Orders
</h4>

<?php if (empty($orders)): ?>
<div class="empty-state">
  <i class="bi bi-bag-x"></i>
  <p class="fw-semibold fs-5 mb-1">No orders yet</p>
  <p class="mb-3">Your orders will appear here once you make a purchase.</p>
  <a href="<?= url('shop.php') ?>" class="btn-primary-shop"><i class="bi bi-bag"></i> Start Shopping</a>
</div>

<?php else: ?>
<?php foreach ($orders as $o):
    $sc = 'status-'.$o['status'];
    $its = $pdo->prepare(
        'SELECT product_name, quantity FROM order_items WHERE order_id=? LIMIT 3'
    );
    $its->execute([$o['id']]);
    $its = $its->fetchAll();
?>
<div class="order-card">
  <div class="order-head">
    <div>
      <span class="fw-bold" style="color:var(--primary);">Order #<?= $o['id'] ?></span>
      <span class="ms-2 small" style="color:var(--muted);">
        <?= date('d M Y, H:i', strtotime($o['created_at'])) ?>
      </span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="status <?= $sc ?>"><?= ucfirst($o['status']) ?></span>
      <a href="<?= url('order_confirmation.php?id=') ?><?= $o['id'] ?>"
         class="btn btn-sm btn-outline-secondary">Details</a>
    </div>
  </div>
  <div style="padding:.8rem 1.2rem;">
    <div class="row align-items-center g-2">
      <div class="col-md-6">
        <div style="font-size:.86rem;color:var(--muted);">
          <?php foreach ($its as $i => $it): ?>
          <?= esc($it['product_name']) ?> ×<?= $it['quantity'] ?>
          <?= $i < count($its)-1 ? ' · ' : '' ?>
          <?php endforeach; ?>
          <?php if ($o['item_count'] > 3): ?>
          <span style="color:var(--accent);"> +<?= $o['item_count']-3 ?> more</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-md-3 small" style="color:var(--muted);">
        <i class="bi bi-phone me-1"></i>
        <?= $o['payment_method'] === 'mpesa' ? 'M-Pesa' : ucfirst($o['payment_method']) ?>
        <?php if ($o['mpesa_code']): ?>
        · <span class="fw-semibold"><?= esc($o['mpesa_code']) ?></span>
        <?php endif; ?>
      </div>
      <div class="col-md-3 text-md-end">
        <span class="fw-bold" style="color:var(--primary);font-size:1.05rem;">
          KES <?= number_format($o['total'],2) ?>
        </span>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/backend/includes/shop_footer.php'; ?>

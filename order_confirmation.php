<?php
require_once __DIR__ . '/backend/includes/bootstrap.php';
requireAuth();
if (isStaff()) { header('Location: ' . url('admin/dashboard.php')); exit; }

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

$s = $pdo->prepare(
    'SELECT o.*, u.name AS cname FROM orders o JOIN users u ON u.id=o.customer_id
     WHERE o.id=? AND o.customer_id=?'
);
$s->execute([$id, $_SESSION['user_id']]);
$order = $s->fetch();

if (!$order) { flashSet('danger','Order not found.'); header('Location: ' . url('shop.php')); exit; }

$items = $pdo->prepare(
    'SELECT * FROM order_items WHERE order_id=?'
);
$items->execute([$id]);
$orderItems = $items->fetchAll();

$pageTitle = "Order #$id Confirmed";
include __DIR__ . '/backend/includes/shop_header.php';
?>

<div class="text-center py-4 mb-4">
  <i class="bi bi-check-circle-fill" style="font-size:3.8rem;color:var(--green);"></i>
  <h3 class="fw-bold mt-3 mb-1" style="color:var(--primary);">Order Confirmed!</h3>
  <p style="color:var(--muted);">
    Thank you, <?= esc(explode(' ', $order['cname'])[0]) ?>.
    Your order has been received and payment recorded.
  </p>
  <span class="badge px-3 py-2" style="background:rgba(39,174,96,.12);color:#1e8449;font-size:.9rem;">
    Order #<?= $id ?> &mdash; <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
  </span>
</div>

<div class="row justify-content-center g-4">
  <div class="col-lg-7">

    <!-- Items -->
    <div class="cart-wrap mb-4">
      <div style="padding:.75rem 1.2rem;border-bottom:1px solid var(--border);background:var(--bg);">
        <h6 class="fw-bold mb-0" style="color:var(--primary);">Items Ordered</h6>
      </div>
      <table class="cart-table">
        <thead>
          <tr>
            <th>Product</th>
            <th class="text-center">Qty</th>
            <th class="text-end">Unit Price</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($orderItems as $item): ?>
        <tr>
          <td class="fw-semibold"><?= esc($item['product_name']) ?></td>
          <td class="text-center"><?= $item['quantity'] ?></td>
          <td class="text-end">KES <?= number_format($item['unit_price'],2) ?></td>
          <td class="text-end fw-bold" style="color:var(--primary);">
            KES <?= number_format($item['quantity'] * $item['unit_price'],2) ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Payment summary -->
    <div class="summary-box mb-4">
      <h6 class="fw-bold mb-3" style="color:var(--primary);">Payment Summary</h6>
      <div class="summary-row"><span>Subtotal</span><span>KES <?= number_format($order['subtotal'],2) ?></span></div>
      <div class="summary-row">
        <span>Delivery</span>
        <?php if ($order['delivery_fee'] == 0): ?>
        <span style="color:var(--green);">Free</span>
        <?php else: ?>
        <span>KES <?= number_format($order['delivery_fee'],2) ?></span>
        <?php endif; ?>
      </div>
      <div class="summary-row total"><span>Total Paid</span><span>KES <?= number_format($order['total'],2) ?></span></div>

      <?php if ($order['mpesa_code']): ?>
      <div class="mt-3 p-3 rounded"
           style="background:rgba(0,168,80,.07);border:1.5px solid rgba(0,168,80,.2);">
        <div class="fw-semibold small mb-1" style="color:#007a3d;">
          <i class="bi bi-check-circle me-1"></i>M-Pesa Payment Confirmed
        </div>
        <div class="small" style="color:var(--muted);">
          Code: <strong><?= esc($order['mpesa_code']) ?></strong>
          &nbsp;·&nbsp; Phone: <?= esc($order['mpesa_phone']) ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= url('receipt.php?id='.$id) ?>" class="btn-primary-shop" style="border-radius:var(--radius-sm);">
        <i class="bi bi-download"></i> Download Receipt
      </a>
      <a href="<?= url('my_orders.php') ?>"
         style="border:1.5px solid var(--border);border-radius:var(--radius-sm);
                padding:.5rem 1rem;color:var(--text);font-size:.875rem;
                display:inline-flex;align-items:center;gap:.3rem;text-decoration:none;">
        <i class="bi bi-receipt"></i> My Orders
      </a>
      <a href="<?= url('shop.php') ?>"
         style="border:1.5px solid var(--border);border-radius:var(--radius-sm);
                padding:.5rem 1rem;color:var(--text);font-size:.875rem;
                display:inline-flex;align-items:center;gap:.3rem;text-decoration:none;">
        <i class="bi bi-bag"></i> Continue Shopping
      </a>
    </div>

  </div>
</div>

<?php include __DIR__ . '/backend/includes/shop_footer.php'; ?>
-<?php
/**
 * receipt.php — Printable/downloadable order receipt
 * Accessible by the customer who placed the order or admin/staff
 */
require_once __DIR__ . '/backend/includes/bootstrap.php';
requireAuth();

$pdo     = getDB();
$orderId = (int)($_GET['id'] ?? 0);
$user    = getCurrentUser();

// Staff can view any order, customers only their own
if (isStaff()) {
    $stmt = $pdo->prepare(
        'SELECT o.*, u.name AS cname, u.email AS cemail, u.phone AS cphone
         FROM orders o JOIN users u ON u.id = o.customer_id
         WHERE o.id = ?'
    );
    $stmt->execute([$orderId]);
} else {
    $stmt = $pdo->prepare(
        'SELECT o.*, u.name AS cname, u.email AS cemail, u.phone AS cphone
         FROM orders o JOIN users u ON u.id = o.customer_id
         WHERE o.id = ? AND o.customer_id = ?'
    );
    $stmt->execute([$orderId, $user['id']]);
}

$order = $stmt->fetch();
if (!$order) {
    flashSet('danger', 'Receipt not found.');
    header('Location: ' . url('my_orders.php'));
    exit;
}

$items = $pdo->prepare(
    'SELECT * FROM order_items WHERE order_id = ?'
);
$items->execute([$orderId]);
$orderItems = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Receipt — Order #<?= $orderId ?> | SalesIQ</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    color: #1a1a2e;
    background: #f0f2f5;
    padding: 20px;
}

.receipt-wrap {
    max-width: 680px;
    margin: 0 auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0,0,0,.1);
    overflow: hidden;
}

/* Header */
.receipt-header {
    background: linear-gradient(135deg, #1a5276, #2e86c1);
    color: #fff;
    padding: 32px 40px 24px;
}

.receipt-header .brand {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -.5px;
    margin-bottom: 4px;
}

.receipt-header .brand span {
    opacity: .7;
    font-weight: 400;
}

.receipt-header .receipt-title {
    font-size: 13px;
    opacity: .8;
    margin-top: 2px;
}

.status-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-top: 12px;
}

.status-paid      { background: rgba(39,174,96,.25); color: #a8f0c6; }
.status-pending   { background: rgba(243,156,18,.25); color: #fde68a; }
.status-delivered { background: rgba(39,174,96,.25); color: #a8f0c6; }
.status-cancelled { background: rgba(231,76,60,.25);  color: #fca5a5; }

/* Body */
.receipt-body { padding: 32px 40px; }

/* Info grid */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 28px;
    padding-bottom: 28px;
    border-bottom: 1px solid #e8ecf0;
}

.info-section h4 {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #8899aa;
    margin-bottom: 8px;
}

.info-section p {
    font-size: 13px;
    color: #1a1a2e;
    line-height: 1.7;
}

.info-section .val {
    font-weight: 600;
}

/* Items table */
.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 24px;
}

.items-table th {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: #8899aa;
    padding: 0 0 10px;
    border-bottom: 1.5px solid #e8ecf0;
    text-align: left;
}

.items-table th:last-child,
.items-table td:last-child { text-align: right; }

.items-table td {
    padding: 12px 0;
    border-bottom: 1px solid #f0f2f5;
    vertical-align: top;
}

.items-table tr:last-child td { border-bottom: none; }

.item-name { font-weight: 600; margin-bottom: 2px; }
.item-qty  { font-size: 12px; color: #8899aa; }
.item-price { font-weight: 600; }

/* Totals */
.totals {
    margin-left: auto;
    width: 260px;
    margin-bottom: 28px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    font-size: 13px;
    color: #4a5568;
}

.total-row.grand {
    border-top: 2px solid #1a5276;
    margin-top: 8px;
    padding-top: 10px;
    font-size: 15px;
    font-weight: 700;
    color: #1a5276;
}

/* Payment info */
.payment-box {
    background: #f0faf5;
    border: 1.5px solid #a8f0c6;
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.payment-box .mpesa-logo {
    width: 40px;
    height: 40px;
    background: #00a850;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    color: #fff;
    font-size: 16px;
    flex-shrink: 0;
}

.payment-box .pay-detail { font-size: 12px; color: #4a5568; line-height: 1.7; }
.payment-box .pay-detail strong { color: #1a1a2e; font-size: 13px; }

/* Footer */
.receipt-footer {
    background: #f8fafc;
    border-top: 1px solid #e8ecf0;
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: #8899aa;
}

/* Print button (hidden when printing) */
.no-print {
    text-align: center;
    padding: 20px;
    background: #f0f2f5;
}

.btn-print {
    background: #1a5276;
    color: #fff;
    border: none;
    padding: 10px 28px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    margin-right: 8px;
}

.btn-back {
    background: #fff;
    color: #1a5276;
    border: 1.5px solid #1a5276;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

@media print {
    body { background: #fff; padding: 0; }
    .no-print { display: none !important; }
    .receipt-wrap { box-shadow: none; border-radius: 0; }
    .receipt-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .payment-box    { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>

<!-- Print/Back buttons -->
<div class="no-print" style="margin-bottom:16px;">
  <button class="btn-print" onclick="window.print()">
    🖨️ Print Receipt
  </button>
  <button class="btn-print" style="background:#27ae60;" onclick="downloadPDF()">
    ⬇️ Save as PDF
  </button>
  <?php if (isStaff()): ?>
  <a href="<?= url('admin/orders.php?id='.$orderId) ?>" class="btn-back">← Back to Order</a>
  <?php else: ?>
  <a href="<?= url('my_orders.php') ?>" class="btn-back">← My Orders</a>
  <?php endif; ?>
</div>

<div class="receipt-wrap">

  <!-- Header -->
  <div class="receipt-header">
    <div class="brand">SalesIQ <span>Shop</span></div>
    <div class="receipt-title">Official Purchase Receipt</div>
    <div class="status-badge status-<?= $order['status'] ?>">
      <?= ucfirst($order['status']) ?>
    </div>
  </div>

  <!-- Body -->
  <div class="receipt-body">

    <!-- Order info grid -->
    <div class="info-grid">
      <div class="info-section">
        <h4>Order Details</h4>
        <p>
          <span class="val">Order #<?= $orderId ?></span><br>
          <?= date('d M Y, H:i', strtotime($order['created_at'])) ?><br>
          Payment: <?= $order['payment_method'] === 'mpesa' ? 'M-Pesa' : ucfirst($order['payment_method']) ?>
        </p>
      </div>
      <div class="info-section">
        <h4>Customer</h4>
        <p>
          <span class="val"><?= esc($order['cname']) ?></span><br>
          <?= esc($order['cemail']) ?><br>
          <?= $order['cphone'] ? esc($order['cphone']) : '' ?>
        </p>
      </div>
      <div class="info-section">
        <h4>Billing Address</h4>
        <p>Nairobi, Kenya</p>
      </div>
      <div class="info-section">
        <h4>Delivery</h4>
        <p>
          <?php if ($order['delivery_fee'] == 0): ?>
          <span style="color:#27ae60;font-weight:600;">Free Delivery</span>
          <?php else: ?>
          Standard Delivery<br>KES <?= number_format($order['delivery_fee'], 2) ?>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <!-- Items -->
    <table class="items-table">
      <thead>
        <tr>
          <th>Product</th>
          <th style="text-align:center;">Qty</th>
          <th style="text-align:right;">Unit Price</th>
          <th style="text-align:right;">Total</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orderItems as $item): ?>
      <tr>
        <td>
          <div class="item-name"><?= esc($item['product_name']) ?></div>
        </td>
        <td style="text-align:center;"><span class="item-qty"><?= $item['quantity'] ?></span></td>
        <td style="text-align:right;" class="item-price">KES <?= number_format($item['unit_price'], 2) ?></td>
        <td style="text-align:right;" class="item-price">
          KES <?= number_format($item['quantity'] * $item['unit_price'], 2) ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
      <div class="total-row">
        <span>Subtotal</span>
        <span>KES <?= number_format($order['subtotal'], 2) ?></span>
      </div>
      <div class="total-row">
        <span>Delivery Fee</span>
        <?php if ($order['delivery_fee'] == 0): ?>
        <span style="color:#27ae60;">Free</span>
        <?php else: ?>
        <span>KES <?= number_format($order['delivery_fee'], 2) ?></span>
        <?php endif; ?>
      </div>
      <div class="total-row grand">
        <span>Total Paid</span>
        <span>KES <?= number_format($order['total'], 2) ?></span>
      </div>
    </div>

    <!-- Payment confirmation -->
    <?php if ($order['mpesa_code']): ?>
    <div class="payment-box">
      <div class="mpesa-logo">M</div>
      <div class="pay-detail">
        <strong>M-Pesa Payment Confirmed</strong><br>
        Transaction Code: <strong><?= esc($order['mpesa_code']) ?></strong><br>
        Phone: <?= esc($order['mpesa_phone']) ?> &nbsp;·&nbsp;
        Amount: KES <?= number_format($order['total'], 2) ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($order['notes']): ?>
    <div style="background:#f8fafc;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:12px;color:#4a5568;">
      <strong>Notes:</strong> <?= esc($order['notes']) ?>
    </div>
    <?php endif; ?>

  </div><!-- /receipt-body -->

  <!-- Footer -->
  <div class="receipt-footer">
    <div>
      Thank you for shopping with <strong>SalesIQ</strong>!
    </div>
    <div>
      <?= date('d M Y') ?> &nbsp;·&nbsp; salesiq-production.up.railway.app
    </div>
  </div>

</div><!-- /receipt-wrap -->

<script>
function downloadPDF() {
    // Use browser's built-in print to PDF
    var style = document.createElement('style');
    style.textContent = '@page { size: A4; margin: 10mm; }';
    document.head.appendChild(style);
    window.print();
    document.head.removeChild(style);
}
</script>
</body>
</html>
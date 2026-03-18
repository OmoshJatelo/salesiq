<?php
/**
 * checkout.php — M-Pesa STK Push checkout
 *
 * Flow:
 *  1. Customer fills phone + notes, submits form
 *  2. Server creates a 'pending' order + mpesa_transactions row
 *  3. Server calls Daraja STK push — phone receives PIN prompt
 *  4. Customer enters PIN on phone
 *  5. Safaricom calls mpesa_callback.php — order updated to 'paid'
 *  6. Browser polls mpesa_status.php every 3s — redirects on paid/failed
 */
require_once __DIR__ . '/backend/includes/bootstrap.php';
set_time_limit(120); // allow 2 min for STK push round-trip
requireAuth();
if (isStaff()) { header('Location: ' . url('admin/dashboard.php')); exit; }

require_once __DIR__ . '/backend/Mpesa.php';

$pdo  = getDB();
$user = getCurrentUser();

// ── Build order items ─────────────────────────────────────────────────────
$buyNow     = (int)($_GET['buy_now'] ?? 0);
$orderItems = [];
$subtotal   = 0.0;

if ($buyNow > 0) {
    $s = $pdo->prepare('SELECT * FROM products WHERE id=? AND is_active=1 AND stock>0');
    $s->execute([$buyNow]);
    $pr = $s->fetch();
    if (!$pr) { flashSet('danger','Product unavailable.'); header('Location: ' . url('shop.php')); exit; }
    $orderItems[] = ['product'=>$pr,'qty'=>1,'line'=>(float)$pr['selling_price']];
    $subtotal     = (float)$pr['selling_price'];
} else {
    if (empty($_SESSION['cart'])) { header('Location: ' . url('cart.php')); exit; }
    $ids = array_keys($_SESSION['cart']);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $s   = $pdo->prepare("SELECT * FROM products WHERE id IN ($in)");
    $s->execute($ids);
    $db  = [];
    foreach ($s->fetchAll() as $r) $db[$r['id']] = $r;
    foreach ($_SESSION['cart'] as $pid => $item) {
        $pr = $db[$pid] ?? null;
        if (!$pr) continue;
        $qty  = min($item['qty'], $pr['stock']);
        $line = $qty * $pr['selling_price'];
        $orderItems[] = ['product'=>$pr,'qty'=>$qty,'line'=>$line];
        $subtotal    += $line;
    }
}

if (empty($orderItems)) { header('Location: ' . url('cart.php')); exit; }

$delivery = deliveryFee($subtotal);
$total    = $subtotal + $delivery;
$error    = null;

// ── POST: initiate STK push ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid request.';
    } else {
        $phone = trim($_POST['mpesa_phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if (!Mpesa::isValidPhone($phone)) {
            $error = 'Enter a valid Kenyan phone number (e.g. 0712 345 678).';
        }
    }

    if (!$error) {
        $orderId = null;
        try {
            $pdo->beginTransaction();

            // Lock rows and re-validate stock
            foreach ($orderItems as &$item) {
                $s = $pdo->prepare('SELECT stock,selling_price,cost_price FROM products WHERE id=? FOR UPDATE');
                $s->execute([$item['product']['id']]);
                $row = $s->fetch();
                if (!$row || $row['stock'] < $item['qty']) {
                    throw new Exception("Sorry — \"{$item['product']['name']}\" only has {$row['stock']} left.");
                }
                $item['product']['selling_price'] = $row['selling_price'];
                $item['product']['cost_price']    = $row['cost_price'];
                $item['line'] = $item['qty'] * $row['selling_price'];
            }
            unset($item);

            $subtotal = array_sum(array_column($orderItems, 'line'));
            $delivery = deliveryFee($subtotal);
            $total    = $subtotal + $delivery;

            // Create order as 'pending'
            $pdo->prepare(
                'INSERT INTO orders
                    (customer_id,status,payment_method,subtotal,delivery_fee,total,mpesa_phone,notes)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([
                $user['id'], 'pending', 'mpesa',
                number_format($subtotal, 2, '.', ''),
                number_format($delivery, 2, '.', ''),
                number_format($total,    2, '.', ''),
                Mpesa::normalizePhone($phone),
                $notes ?: null,
            ]);
            $orderId = $pdo->lastInsertId();

            // Insert items + decrement stock
            foreach ($orderItems as $item) {
                $pr = $item['product'];
                $pdo->prepare(
                    'INSERT INTO order_items
                        (order_id,product_id,product_name,quantity,unit_price,cost_price)
                     VALUES (?,?,?,?,?,?)'
                )->execute([
                    $orderId, $pr['id'], $pr['name'], $item['qty'],
                    number_format($pr['selling_price'], 2, '.', ''),
                    number_format($pr['cost_price'],    2, '.', ''),
                ]);
                $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=?')
                    ->execute([$item['qty'], $pr['id']]);
            }

            $pdo->commit();

            // ── Daraja STK push ───────────────────────────────────────────
            // Release session lock before the outbound HTTP call.
            // PHP holds a session file lock for the entire request — without
            // this, the 60s cURL call blocks all other tabs AND causes a
            // timeout on some servers.
            session_write_close();

            $mpesa     = new Mpesa();
            $stkResult = $mpesa->stkPush($phone, $total, 'SalesIQ Order', 'Order#' . $orderId);

            $checkoutId = $stkResult['CheckoutRequestID'];
            $merchantId = $stkResult['MerchantRequestID'];

            // Store tracking row
            $pdo->prepare(
                'INSERT INTO mpesa_transactions
                    (order_id,checkout_request_id,merchant_request_id,phone,amount,status)
                 VALUES (?,?,?,?,?,?)'
            )->execute([
                $orderId, $checkoutId, $merchantId,
                Mpesa::normalizePhone($phone), $total, 'pending',
            ]);

            $pdo->prepare('UPDATE orders SET mpesa_checkout_id=? WHERE id=?')
                ->execute([$checkoutId, $orderId]);

            unset($_SESSION['cart']);
            logAction('CREATE', 'order', $orderId, ['total'=>$total,'method'=>'mpesa_stk']);

            header('Location: ' . url('checkout_waiting.php?order_id=' . $orderId));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();

            // If order was created before STK failed, cancel it and restore stock
            if ($orderId) {
                try {
                    $pdo->prepare('UPDATE orders SET status=? WHERE id=?')
                        ->execute(['cancelled', $orderId]);
                    foreach ($orderItems as $item) {
                        $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?')
                            ->execute([$item['qty'], $item['product']['id']]);
                    }
                } catch (Exception $inner) {}
            }
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout';
include __DIR__ . '/backend/includes/shop_header.php';
?>

<h4 class="fw-bold mb-4" style="color:var(--primary);">
  <i class="bi bi-lock-fill me-2"></i>Secure Checkout
</h4>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= esc($error) ?></div>
<?php endif; ?>

<form method="POST" action="<?= url('checkout.php') . ($buyNow ? '?buy_now='.$buyNow : '') ?>" id="checkoutForm">
<input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">

<div class="row g-4">
  <!-- Payment -->
  <div class="col-lg-7">
    <div class="mpesa-banner">
      <div class="mpesa-logo">M</div>
      <div>
        <div class="fw-bold" style="font-size:1.05rem;">Pay with M-Pesa</div>
        <div style="opacity:.85;font-size:.83rem;">PIN prompt sent directly to your phone</div>
      </div>
      <span class="ms-auto badge" style="background:rgba(255,255,255,.2);font-size:.78rem;padding:.4em .8em;">
        <i class="bi bi-shield-check me-1"></i>Secured
      </span>
    </div>

    <div class="mpesa-steps mb-4">
      <p class="fw-semibold mb-2" style="font-size:.88rem;">
        <i class="bi bi-info-circle me-1" style="color:#00a850;"></i>How it works:
      </p>
      <div class="mpesa-step"><div class="step-num">1</div>
        <div>Enter your <strong>M-Pesa number</strong> and click Pay</div>
      </div>
      <div class="mpesa-step"><div class="step-num">2</div>
        <div>A <strong>PIN prompt appears on your phone</strong> automatically</div>
      </div>
      <div class="mpesa-step"><div class="step-num">3</div>
        <div>Enter your PIN to confirm <strong>KES <?= number_format($total, 2) ?></strong></div>
      </div>
      <div class="mpesa-step"><div class="step-num">4</div>
        <div>Order confirmed instantly — <strong>no code to copy!</strong></div>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">M-Pesa Phone Number <span class="text-danger">*</span></label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
        <input type="tel" name="mpesa_phone" class="form-control"
               placeholder="0712 345 678"
               value="<?= esc($_POST['mpesa_phone'] ?? $user['phone'] ?? '') ?>"
               required>
      </div>
      <div class="form-text">The number registered with M-Pesa</div>
    </div>

    <div class="mb-1">
      <label class="form-label fw-semibold small">Notes <small class="text-muted fw-normal">(optional)</small></label>
      <textarea name="notes" class="form-control" rows="2"
                placeholder="Delivery instructions…" maxlength="500"><?= esc($_POST['notes'] ?? '') ?></textarea>
    </div>
  </div>

  <!-- Summary -->
  <div class="col-lg-5">
    <div class="summary-box">
      <h6 class="fw-bold mb-3" style="color:var(--primary);">Your Order</h6>
      <?php foreach ($orderItems as $item): $pr = $item['product']; ?>
      <div class="d-flex justify-content-between align-items-start mb-2" style="font-size:.87rem;">
        <div>
          <span class="fw-semibold"><?= esc($pr['name']) ?></span>
          <br><span style="color:var(--muted);">× <?= $item['qty'] ?></span>
        </div>
        <span class="fw-semibold ms-2">KES <?= number_format($item['line'],2) ?></span>
      </div>
      <?php endforeach; ?>
      <div class="divider"></div>
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

      <button type="submit" id="placeBtn"
              class="btn-primary-shop w-100 py-2 mt-3 justify-content-center"
              style="border-radius:var(--radius-sm);font-size:.97rem;">
        <i class="bi bi-phone"></i> Pay KES <?= number_format($total,2) ?> via M-Pesa
      </button>
      <p class="text-center mt-2 mb-0" style="font-size:.74rem;color:var(--muted);">
        <i class="bi bi-shield-lock me-1"></i>Secured by Safaricom Daraja
      </p>
    </div>
  </div>
</div>
</form>

<?php
$extraScript = <<<JS
document.getElementById('checkoutForm').addEventListener('submit', function() {
    var b = document.getElementById('placeBtn');
    b.disabled = true;
    b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending request to your phone…';
});
JS;
include __DIR__ . '/backend/includes/shop_footer.php';
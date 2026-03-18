<?php
/**
 * checkout_waiting.php
 * Shown after STK push is sent. Polls mpesa_status.php every 3 seconds.
 * Auto-redirects to order_confirmation on success, shows error on failure.
 */
require_once __DIR__ . '/backend/includes/bootstrap.php';
requireAuth();
if (isStaff()) { header('Location: ' . url('admin/dashboard.php')); exit; }

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) { header('Location: ' . url('shop.php')); exit; }

// Verify order belongs to this user
$pdo  = getDB();
$stmt = $pdo->prepare(
    'SELECT o.*, u.phone AS cphone FROM orders o
     JOIN users u ON u.id = o.customer_id
     WHERE o.id = ? AND o.customer_id = ?'
);
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) { header('Location: ' . url('shop.php')); exit; }

// If already paid (e.g. customer refreshed), redirect to confirmation
if ($order['status'] === 'paid') {
    header('Location: ' . url('order_confirmation.php?id=' . $orderId)); exit;
}

$pageTitle = 'Waiting for Payment';
include __DIR__ . '/backend/includes/shop_header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-5 col-md-7">

    <div class="text-center py-4">

      <!-- Animated M logo -->
      <div id="waitingIcon" class="mb-4">
        <div style="width:80px;height:80px;border-radius:50%;background:#00a850;
                    display:flex;align-items:center;justify-content:center;
                    margin:0 auto;animation:pulse 1.5s ease-in-out infinite;">
          <span style="font-size:2.2rem;font-weight:900;color:#fff;line-height:1;">M</span>
        </div>
      </div>

      <h4 class="fw-bold mb-2" id="waitingTitle">Check your phone</h4>
      <p class="mb-1" style="color:var(--muted);" id="waitingMsg">
        A PIN prompt has been sent to <strong><?= esc($order['mpesa_phone']) ?></strong>
      </p>
      <p class="small mb-4" style="color:var(--muted);">
        Enter your M-Pesa PIN to complete the payment of
        <strong>KES <?= number_format($order['total'], 2) ?></strong>
      </p>

      <!-- Countdown / status -->
      <div class="mb-4">
        <div id="statusBadge" class="badge px-3 py-2"
             style="background:rgba(0,168,80,.1);color:#007a3d;font-size:.88rem;">
          <span class="spinner-border spinner-border-sm me-2" style="width:.8rem;height:.8rem;"></span>
          Waiting for payment…
        </div>
      </div>

      <!-- Timeout bar -->
      <div class="mb-4" style="background:var(--border);border-radius:4px;height:4px;overflow:hidden;">
        <div id="timeoutBar" style="height:100%;background:#00a850;width:100%;
             transition:width 1s linear;border-radius:4px;"></div>
      </div>
      <p class="small" style="color:var(--muted);">
        Expires in <span id="countdown">90</span>s
      </p>

      <!-- Retry / cancel buttons (hidden until needed) -->
      <div id="retryBtns" style="display:none;" class="d-flex gap-2 justify-content-center mt-3">
        <a href="<?= url('checkout.php') ?>" class="btn-primary-shop" style="border-radius:8px;">
          <i class="bi bi-arrow-counterclockwise me-1"></i> Try Again
        </a>
        <a href="<?= url('cart.php') ?>" style="border:1.5px solid var(--border);
           border-radius:8px;padding:.5rem 1rem;color:var(--text);
           font-size:.875rem;display:inline-flex;align-items:center;gap:.3rem;text-decoration:none;">
          <i class="bi bi-cart3"></i> Back to Cart
        </a>
      </div>
    </div>

  </div>
</div>

<style>
@keyframes pulse {
  0%,100% { transform:scale(1);   box-shadow:0 0 0 0   rgba(0,168,80,.4); }
  50%      { transform:scale(1.08);box-shadow:0 0 0 12px rgba(0,168,80,0);  }
}
</style>

<?php
$statusUrl  = url('mpesa_status.php?order_id=' . $orderId);
$successUrl = url('order_confirmation.php?id=' . $orderId);
$cartUrl    = url('cart.php');

$extraScript = <<<JS
(function() {
    var statusUrl  = '{$statusUrl}';
    var successUrl = '{$successUrl}';
    var timer      = 90;
    var bar        = document.getElementById('timeoutBar');
    var countdown  = document.getElementById('countdown');
    var badge      = document.getElementById('statusBadge');
    var retryBtns  = document.getElementById('retryBtns');
    var waitingMsg = document.getElementById('waitingMsg');
    var title      = document.getElementById('waitingTitle');
    var icon       = document.getElementById('waitingIcon').firstElementChild;
    var done       = false;

    // Countdown timer
    var timerInterval = setInterval(function() {
        timer--;
        countdown.textContent = timer;
        bar.style.width = (timer / 90 * 100) + '%';
        if (timer <= 0) {
            clearInterval(timerInterval);
            if (!done) showFailed('Request timed out. Please try again.');
        }
    }, 1000);

    // Poll every 3 seconds
    var pollInterval = setInterval(function() {
        fetch(statusUrl)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'paid') {
                    done = true;
                    clearInterval(pollInterval);
                    clearInterval(timerInterval);
                    showSuccess();
                    setTimeout(function() { window.location = successUrl; }, 1500);
                } else if (data.status === 'failed') {
                    done = true;
                    clearInterval(pollInterval);
                    clearInterval(timerInterval);
                    showFailed(data.message || 'Payment failed. Please try again.');
                }
            })
            .catch(function() {}); // silent — network blip, will retry
    }, 3000);

    function showSuccess() {
        icon.style.animation = 'none';
        icon.style.background = '#00a850';
        icon.innerHTML = '<span style="font-size:2.2rem;color:#fff;">✓</span>';
        title.textContent = 'Payment received!';
        waitingMsg.innerHTML = 'Redirecting to your order confirmation…';
        badge.style.background = 'rgba(0,168,80,.12)';
        badge.style.color = '#007a3d';
        badge.innerHTML = '<i class="bi bi-check-circle me-1"></i> Payment confirmed';
        bar.style.width = '100%';
        bar.style.background = '#00a850';
    }

    function showFailed(msg) {
        icon.style.animation = 'none';
        icon.style.background = '#e74c3c';
        icon.innerHTML = '<span style="font-size:2rem;color:#fff;">✕</span>';
        title.textContent = 'Payment unsuccessful';
        waitingMsg.innerHTML = msg;
        badge.style.background = 'rgba(231,76,60,.1)';
        badge.style.color = '#a93226';
        badge.innerHTML = '<i class="bi bi-x-circle me-1"></i> ' + msg;
        bar.style.width = '0%';
        bar.style.background = '#e74c3c';
        retryBtns.style.display = 'flex';
    }
})();
JS;

include __DIR__ . '/backend/includes/shop_footer.php';
?>
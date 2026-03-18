<?php
/**
 * mpesa_status.php
 * ─────────────────
 * JSON endpoint polled by the checkout page to check payment status.
 * Called every 3 seconds by the browser while customer is on the
 * "waiting for payment" screen.
 *
 * GET /mpesa_status.php?order_id=42
 * Returns: { "status": "pending|paid|failed|cancelled", "message": "..." }
 */
define('APP_RUNNING', true);
require_once __DIR__ . '/backend/includes/bootstrap.php';

header('Content-Type: application/json');

// Must be logged in
if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit;
}

$pdo = getDB();

// Verify this order belongs to the current user
$stmt = $pdo->prepare(
    'SELECT o.status, o.mpesa_code, t.status AS txn_status, t.result_desc, t.result_code
     FROM orders o
     LEFT JOIN mpesa_transactions t ON t.order_id = o.id
     WHERE o.id = ? AND o.customer_id = ?
     ORDER BY t.created_at DESC LIMIT 1'
);
$stmt->execute([$orderId, $_SESSION['user_id']]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

// Map to a simple status the frontend understands
if ($row['status'] === 'paid') {
    echo json_encode([
        'status'      => 'paid',
        'message'     => 'Payment received!',
        'mpesa_code'  => $row['mpesa_code'],
        'redirect'    => url('order_confirmation.php?id=' . $orderId),
    ]);
} elseif ($row['txn_status'] === 'failed') {
    $desc = $row['result_desc'] ?? 'Payment failed.';
    // Friendly messages for common result codes
    $friendly = match ((string)($row['result_code'] ?? '')) {
        '1032' => 'You cancelled the payment on your phone.',
        '1037' => 'Request timed out. Please try again.',
        '1'    => 'Insufficient M-Pesa balance.',
        default => $desc,
    };
    echo json_encode(['status' => 'failed', 'message' => $friendly]);
} elseif ($row['txn_status'] === 'cancelled') {
    echo json_encode(['status' => 'failed', 'message' => 'Payment was cancelled.']);
} else {
    // Still pending
    echo json_encode(['status' => 'pending', 'message' => 'Waiting for payment…']);
}
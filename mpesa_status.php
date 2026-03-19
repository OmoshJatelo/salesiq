<?php
/**
 * mpesa_status.php
 * Polls payment status — first checks DB, then queries Safaricom directly.
 * This works even when the callback URL is blocked (e.g. InfinityFree).
 */
define('APP_RUNNING', true);
require_once __DIR__ . '/backend/includes/bootstrap.php';
require_once __DIR__ . '/backend/Mpesa.php';

header('Content-Type: application/json');

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

// Verify order belongs to current user
$stmt = $pdo->prepare(
    'SELECT o.*, t.status AS txn_status, t.checkout_request_id,
            t.result_code, t.result_desc
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

// Already paid in DB — great
if ($row['status'] === 'paid') {
    echo json_encode([
        'status'    => 'paid',
        'message'   => 'Payment confirmed!',
        'redirect'  => url('order_confirmation.php?id=' . $orderId),
    ]);
    exit;
}

// Already marked failed in DB
if ($row['txn_status'] === 'failed') {
    $friendly = match ((string)($row['result_code'] ?? '')) {
        '1032' => 'You cancelled the payment.',
        '1037' => 'Request timed out. Please try again.',
        '1'    => 'Insufficient M-Pesa balance.',
        default => $row['result_desc'] ?: 'Payment failed.',
    };
    echo json_encode(['status' => 'failed', 'message' => $friendly]);
    exit;
}

// Still pending — query Safaricom directly to check real status
$checkoutId = $row['checkout_request_id'] ?? null;

if ($checkoutId) {
    try {
        $mpesa  = new Mpesa();
        $result = $mpesa->queryStatus($checkoutId);

        $resultCode = (int)($result['ResultCode'] ?? -1);
        $resultDesc = $result['ResultDesc'] ?? '';

        if ($resultCode === 0) {
            // Payment confirmed by Safaricom — update DB
            $pdo->prepare(
                'UPDATE mpesa_transactions
                 SET status="completed", result_code=?, result_desc=?, updated_at=NOW()
                 WHERE checkout_request_id=?'
            )->execute([$resultCode, $resultDesc, $checkoutId]);

            $pdo->prepare(
                'UPDATE orders SET status="paid" WHERE id=? AND status="pending"'
            )->execute([$orderId]);

            echo json_encode([
                'status'   => 'paid',
                'message'  => 'Payment confirmed!',
                'redirect' => url('order_confirmation.php?id=' . $orderId),
            ]);
            exit;

        } elseif (in_array($resultCode, [1032, 1037, 1, 2001], true)) {
            // Definitive failure codes
            $friendly = match ($resultCode) {
                1032 => 'You cancelled the payment.',
                1037 => 'Request timed out. Please try again.',
                1    => 'Insufficient M-Pesa balance.',
                2001 => 'Wrong PIN entered.',
                default => $resultDesc ?: 'Payment failed.',
            };

            $pdo->prepare(
                'UPDATE mpesa_transactions
                 SET status="failed", result_code=?, result_desc=?, updated_at=NOW()
                 WHERE checkout_request_id=?'
            )->execute([$resultCode, $resultDesc, $checkoutId]);

            echo json_encode(['status' => 'failed', 'message' => $friendly]);
            exit;
        }
        // Any other code = still processing, fall through to pending response

    } catch (Exception $e) {
        // Query failed — fall through, keep polling
    }
}

// Still genuinely pending
echo json_encode(['status' => 'pending', 'message' => 'Waiting for payment…']);
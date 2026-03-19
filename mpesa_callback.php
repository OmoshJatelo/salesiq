<?php
/**
 * mpesa_callback.php
 * Safaricom POSTs the payment result here after customer enters PIN.
 * Must always return HTTP 200 with JSON — even on errors.
 */

define('APP_RUNNING', true);
require_once __DIR__ . '/backend/includes/env.php';
loadEnv();
require_once __DIR__ . '/backend/includes/db.php';

// Always respond 200 immediately
header('Content-Type: application/json');

// ── Log everything to a file for debugging ────────────────────────────────
function cbLog(string $msg): void {
    $dir = __DIR__ . '/backend/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents(
        $dir . '/callback.log',
        date('Y-m-d H:i:s') . ' | ' . $msg . "\n",
        FILE_APPEND
    );
}

$raw = file_get_contents('php://input');
cbLog("Received: $raw");

try {
    $data = json_decode($raw, true);

    if (!$data) {
        cbLog("ERROR: Empty or invalid JSON body");
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        exit;
    }

    $body       = $data['Body']['stkCallback'] ?? null;
    $checkoutId = $body['CheckoutRequestID']   ?? null;
    $resultCode = (int)($body['ResultCode']    ?? -1);
    $resultDesc = $body['ResultDesc']          ?? '';

    cbLog("CheckoutID: $checkoutId | ResultCode: $resultCode | Desc: $resultDesc");

    if (!$checkoutId) {
        cbLog("ERROR: No CheckoutRequestID in callback");
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        exit;
    }

    $pdo = getDB();
    cbLog("DB connected OK");

    // Find transaction
    $stmt = $pdo->prepare(
        'SELECT * FROM mpesa_transactions WHERE checkout_request_id = ?'
    );
    $stmt->execute([$checkoutId]);
    $txn = $stmt->fetch();

    if (!$txn) {
        cbLog("ERROR: No transaction found for CheckoutID: $checkoutId");
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        exit;
    }

    cbLog("Found transaction ID: {$txn['id']} for order ID: {$txn['order_id']}");

    if ($resultCode === 0) {
        // Payment successful — extract receipt
        $items   = $body['CallbackMetadata']['Item'] ?? [];
        $receipt = '';

        foreach ($items as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $receipt = $item['Value'];
            }
        }

        cbLog("Payment SUCCESS. Receipt: $receipt");

        $pdo->beginTransaction();

        $pdo->prepare(
            'UPDATE mpesa_transactions
             SET status="completed", result_code=?, result_desc=?,
                 mpesa_receipt=?, updated_at=NOW()
             WHERE checkout_request_id=?'
        )->execute([$resultCode, $resultDesc, $receipt, $checkoutId]);

        $rows = $pdo->prepare(
            'UPDATE orders SET status="paid", mpesa_code=?
             WHERE id=? AND status="pending"'
        );
        $rows->execute([$receipt, $txn['order_id']]);
        $affected = $rows->rowCount();

        $pdo->commit();
        cbLog("Updated order {$txn['order_id']}. Rows affected: $affected");

    } else {
        // Payment failed
        cbLog("Payment FAILED. Code: $resultCode Desc: $resultDesc");

        $pdo->prepare(
            'UPDATE mpesa_transactions
             SET status="failed", result_code=?, result_desc=?, updated_at=NOW()
             WHERE checkout_request_id=?'
        )->execute([$resultCode, $resultDesc, $checkoutId]);
    }

} catch (Throwable $e) {
    cbLog("EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
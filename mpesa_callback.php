<?php
/**
 * mpesa_callback.php
 * ──────────────────
 * Safaricom POSTs the payment result to this URL after the customer
 * enters their PIN. This runs in the background — no browser session.
 *
 * URL: https://yourdomain.co.ke/salesiq/mpesa_callback.php
 * Set this as MPESA_CALLBACK_URL in .env
 *
 * IMPORTANT: This file must be publicly reachable by Safaricom's servers.
 * It will NOT work on localhost — use ngrok for local testing.
 */

define('APP_RUNNING', true);
require_once __DIR__ . '/backend/includes/env.php';
loadEnv();
require_once __DIR__ . '/backend/includes/db.php';

// Always respond 200 to Safaricom immediately (they retry on non-200)
header('Content-Type: application/json');
$response = ['ResultCode' => 0, 'ResultDesc' => 'Accepted'];

// ── Read the raw callback body ─────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Log raw payload for debugging (disable in production)
if (getenv('APP_DEBUG') === 'true') {
    $logDir = __DIR__ . '/backend/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    file_put_contents(
        $logDir . '/mpesa_callback.log',
        date('Y-m-d H:i:s') . "\n" . $raw . "\n\n",
        FILE_APPEND
    );
}

// ── Parse the STK callback structure ──────────────────────────────────────
try {
    $body       = $data['Body']['stkCallback']   ?? null;
    $merchantId = $body['MerchantRequestID']      ?? null;
    $checkoutId = $body['CheckoutRequestID']      ?? null;
    $resultCode = $body['ResultCode']             ?? null;
    $resultDesc = $body['ResultDesc']             ?? '';

    if (!$checkoutId) {
        echo json_encode($response); exit;
    }

    $pdo = getDB();

    // ── Find the pending transaction ───────────────────────────────────────
    $stmt = $pdo->prepare(
        'SELECT * FROM mpesa_transactions WHERE checkout_request_id = ?'
    );
    $stmt->execute([$checkoutId]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        // Unknown transaction — log and ignore
        echo json_encode($response); exit;
    }

    if ((int)$resultCode === 0) {
        // ── PAYMENT SUCCESSFUL ─────────────────────────────────────────────
        // Extract M-Pesa receipt from callback metadata
        $items   = $body['CallbackMetadata']['Item'] ?? [];
        $receipt = '';
        $amount  = 0;
        $phone   = '';

        foreach ($items as $item) {
            match ($item['Name'] ?? '') {
                'MpesaReceiptNumber' => $receipt = $item['Value'],
                'Amount'             => $amount  = $item['Value'],
                'PhoneNumber'        => $phone   = $item['Value'],
                default              => null,
            };
        }

        $pdo->beginTransaction();

        // Update transaction record
        $pdo->prepare(
            'UPDATE mpesa_transactions
             SET status=?, result_code=?, result_desc=?, mpesa_receipt=?, updated_at=NOW()
             WHERE checkout_request_id=?'
        )->execute(['completed', $resultCode, $resultDesc, $receipt, $checkoutId]);

        // Update order status to paid + store receipt code
        $pdo->prepare(
            'UPDATE orders
             SET status=?, mpesa_code=?
             WHERE id=? AND status=?'
        )->execute(['paid', $receipt, $transaction['order_id'], 'pending']);

        $pdo->commit();

    } else {
        // ── PAYMENT FAILED / CANCELLED ─────────────────────────────────────
        // ResultCode 1032 = user cancelled, 1037 = timeout, etc.
        $pdo->prepare(
            'UPDATE mpesa_transactions
             SET status=?, result_code=?, result_desc=?, updated_at=NOW()
             WHERE checkout_request_id=?'
        )->execute(['failed', $resultCode, $resultDesc, $checkoutId]);

        // Keep order as 'pending' so customer can retry
    }

} catch (Exception $e) {
    // Silently catch — we must always return 200
    if (getenv('APP_DEBUG') === 'true') {
        error_log('M-Pesa callback error: ' . $e->getMessage());
    }
}

echo json_encode($response);
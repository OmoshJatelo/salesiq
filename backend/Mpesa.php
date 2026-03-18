<?php
/**
 * backend/Mpesa.php
 * Safaricom Daraja API — Lipa Na M-Pesa Online (STK Push)
 */
if (!defined('APP_RUNNING')) exit;

class Mpesa {

    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private string $callbackUrl;
    private bool   $sandbox;
    private string $baseUrl;

    public function __construct() {
        $this->consumerKey    = getenv('MPESA_CONSUMER_KEY')    ?: '';
        $this->consumerSecret = getenv('MPESA_CONSUMER_SECRET') ?: '';
        $this->shortcode      = getenv('MPESA_SHORTCODE')       ?: '174379';
        $this->passkey        = getenv('MPESA_PASSKEY')         ?: '';
        $this->callbackUrl    = getenv('MPESA_CALLBACK_URL')    ?: '';
        $this->sandbox        = (getenv('MPESA_ENV') ?: 'sandbox') === 'sandbox';
        $this->baseUrl        = $this->sandbox
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';
    }

    // ── Execute a cURL request ─────────────────────────────────────────────
    // Forces HTTP/1.1 — critical for XAMPP's older cURL (7.53.x) which
    // drops Authorization headers when the server negotiates HTTP/2.
    private function request(string $url, array $headers, ?string $body = null): array {
        $ch = curl_init($url);

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,  // force HTTP/1.1
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($body !== null) {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        $curlNo   = curl_errno($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("cURL error ($curlNo): $curlErr");
        }

        $data = json_decode($response, true);

        return [
            'code'     => $httpCode,
            'raw'      => $response,
            'data'     => $data ?? [],
        ];
    }

    // ── Get OAuth access token ─────────────────────────────────────────────
    public function getAccessToken(): string {
        $url  = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
        $cred = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $result = $this->request($url, [
            'Authorization: Basic ' . $cred,
        ]);

        if (empty($result['data']['access_token'])) {
            throw new Exception(
                'Failed to get access token. ' .
                ($result['data']['error_description'] ?? $result['raw'])
            );
        }

        return $result['data']['access_token'];
    }

    // ── Initiate STK Push ──────────────────────────────────────────────────
    public function stkPush(string $phone, float $amount, string $desc, string $ref): array {
        $token     = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);
        $phone     = self::normalizePhone($phone);
        $amount    = (int)ceil($amount);

        $payload = json_encode([
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $this->shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $this->callbackUrl,
            'AccountReference'  => substr($ref, 0, 12),
            'TransactionDesc'   => substr($desc, 0, 13),
        ]);

        $result = $this->request(
            $this->baseUrl . '/mpesa/stkpush/v1/processrequest',
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            $payload
        );

        $data = $result['data'];

        // Success: ResponseCode = '0'
        if (($data['ResponseCode'] ?? '') === '0') {
            return $data;
        }

        // Error: build a meaningful message
        $msg = $data['ResponseDescription']
            ?? $data['errorMessage']
            ?? $data['ResultDesc']
            ?? ('STK push failed HTTP ' . $result['code'] . ': ' . $result['raw']);

        throw new Exception($msg);
    }

    // ── Query STK push status ──────────────────────────────────────────────
    public function queryStatus(string $checkoutRequestId): array {
        $token     = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = json_encode([
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ]);

        $result = $this->request(
            $this->baseUrl . '/mpesa/stkpushquery/v1/query',
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            $payload
        );

        return $result['data'];
    }

    // ── Normalize phone to 254XXXXXXXXX ──────────────────────────────────
    public static function normalizePhone(string $phone): string {
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '+')) {
            $phone = ltrim($phone, '+');
        }
        return $phone;
    }

    // ── Validate Kenyan phone number ──────────────────────────────────────
    public static function isValidPhone(string $phone): bool {
        $n = self::normalizePhone($phone);
        return (bool)preg_match('/^2547\d{8}$|^2541\d{8}$/', $n);
    }
}
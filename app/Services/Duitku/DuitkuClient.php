<?php

namespace App\Services\Duitku;

class DuitkuClient
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require dirname(__DIR__, 3) . '/config/duitku.php';
    }

    /**
     * Daftar metode pembayaran yang tersedia untuk nominal tertentu.
     *
     * Return: ['ok' => bool, 'methods' => [ ['code','name','image','fee'], ... ], 'error' => ?string]
     */
    public function getPaymentMethods(int $amount): array
    {
        if ($this->config['merchant_code'] === '' || $this->config['api_key'] === '') {
            return ['ok' => false, 'methods' => [], 'error' => 'Konfigurasi Duitku belum lengkap.'];
        }
        if ($amount < 1) {
            return ['ok' => false, 'methods' => [], 'error' => 'Nominal tidak valid.'];
        }

        $datetime = date('Y-m-d H:i:s');

        $payload = [
            'merchantcode' => $this->config['merchant_code'],
            'amount'       => $amount,
            'datetime'     => $datetime,
            'signature'    => hash('sha256', $this->config['merchant_code'] . $amount . $datetime . $this->config['api_key']),
        ];

        $res = $this->postJson(
            $this->config['classic_base_url'] . '/webapi/api/merchant/paymentmethod/getpaymentmethod',
            $payload
        );

        if ($res['body'] === false) {
            return ['ok' => false, 'methods' => [], 'error' => 'cURL error: ' . $res['curl_error']];
        }

        $data = json_decode($res['body'], true);

        if ($res['http_code'] !== 200 || !is_array($data) || ($data['responseCode'] ?? '') !== '00' || !isset($data['paymentFee'])) {
            $message = is_array($data)
                ? ($data['Message'] ?? $data['responseMessage'] ?? $res['body'])
                : $res['body'];
            return ['ok' => false, 'methods' => [], 'error' => (string) $message];
        }

        $methods = [];
        foreach ($data['paymentFee'] as $row) {
            $code = (string) ($row['paymentMethod'] ?? '');
            if ($code === '') {
                continue;
            }

            $image = (string) ($row['paymentImage'] ?? '');
            if (!str_starts_with($image, 'https://')) {
                $image = '';
            }

            $methods[] = [
                'code'  => $code,
                'name'  => (string) ($row['paymentName'] ?? $code),
                'image' => $image,
                'fee'   => (int) ($row['totalFee'] ?? 0),
            ];
        }

        return ['ok' => true, 'methods' => $methods, 'error' => null];
    }

    /**
     * Buat transaksi dengan metode pembayaran yang sudah dipilih (API klasik v2 inquiry).
     * Untuk metode VA, hasilnya langsung berisi nomor VA.
     *
     * $order:
     *  merchant_order_id, amount (int), payment_method (kode), product_details,
     *  email, phone, customer_name, expiry_minutes (opsional),
     *  items: [ ['name' => string, 'price' => int, 'quantity' => int], ... ]
     *
     * Return: ['ok','reference','payment_url','va_number','qr_string','error']
     */
    public function createTransaction(array $order): array
    {
        if ($this->config['merchant_code'] === '' || $this->config['api_key'] === '') {
            return $this->fail('Konfigurasi Duitku belum lengkap.');
        }

        $amount = (int) $order['amount'];

        $itemTotal = 0;
        $itemDetails = [];
        foreach ($order['items'] as $item) {
            $price = (int) $item['price'];
            $qty = (int) $item['quantity'];
            $itemTotal += $price * $qty;
            $itemDetails[] = [
                'name'     => mb_substr((string) $item['name'], 0, 50),
                'price'    => $price,
                'quantity' => $qty,
            ];
        }

        if ($itemTotal !== $amount) {
            return $this->fail("Total itemDetails ($itemTotal) tidak sama dengan paymentAmount ($amount).");
        }

        $merchantOrderId = (string) $order['merchant_order_id'];
        $nameParts = explode(' ', trim((string) $order['customer_name']), 2);

        $payload = [
            'merchantCode'    => $this->config['merchant_code'],
            'paymentAmount'   => $amount,
            'paymentMethod'   => (string) $order['payment_method'],
            'merchantOrderId' => $merchantOrderId,
            'productDetails'  => (string) $order['product_details'],
            'customerVaName'  => mb_substr((string) $order['customer_name'], 0, 20),
            'email'           => (string) $order['email'],
            'phoneNumber'     => (string) $order['phone'],
            'itemDetails'     => $itemDetails,
            'customerDetail'  => [
                'firstName'   => $nameParts[0],
                'lastName'    => $nameParts[1] ?? '',
                'email'       => (string) $order['email'],
                'phoneNumber' => (string) $order['phone'],
            ],
            'callbackUrl'     => $this->config['callback_url'],
            'returnUrl'       => $this->config['return_url'],
            'signature'       => md5($this->config['merchant_code'] . $merchantOrderId . $amount . $this->config['api_key']),
            'expiryPeriod'    => (int) ($order['expiry_minutes'] ?? $this->config['expiry_minutes']),
        ];

        $res = $this->postJson(
            $this->config['classic_base_url'] . '/webapi/api/merchant/v2/inquiry',
            $payload
        );

        if ($res['body'] === false) {
            return $this->fail('cURL error: ' . $res['curl_error']);
        }

        $data = json_decode($res['body'], true);

        if ($res['http_code'] !== 200 || !is_array($data) || ($data['statusCode'] ?? '') !== '00' || empty($data['reference'])) {
            $message = is_array($data)
                ? ($data['Message'] ?? $data['statusMessage'] ?? $res['body'])
                : $res['body'];
            return $this->fail((string) $message);
        }

        return [
            'ok'          => true,
            'reference'   => (string) $data['reference'],
            'payment_url' => (string) ($data['paymentUrl'] ?? ''),
            'va_number'   => (string) ($data['vaNumber'] ?? ''),
            'qr_string'   => (string) ($data['qrString'] ?? ''),
            'error'       => null,
        ];
    }

    /**
     * Verifikasi signature callback API klasik: MD5(merchantCode + amount + merchantOrderId + apiKey).
     * BELUM TERUJI: diverifikasi di Phase 7C dengan callback sandbox asli.
     */
    public function verifyCallbackSignature(array $post): bool
    {
        foreach (['merchantCode', 'amount', 'merchantOrderId', 'signature'] as $key) {
            if (!isset($post[$key]) || $post[$key] === '') {
                return false;
            }
        }

        if (!hash_equals($this->config['merchant_code'], (string) $post['merchantCode'])) {
            return false;
        }

        $expected = md5($post['merchantCode'] . $post['amount'] . $post['merchantOrderId'] . $this->config['api_key']);

        return hash_equals($expected, strtolower((string) $post['signature']));
    }

    private function postJson(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);

        return [
            'body'       => $body,
            'http_code'  => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'curl_error' => curl_error($ch),
        ];
    }

    private function fail(string $message): array
    {
        return [
            'ok'          => false,
            'reference'   => '',
            'payment_url' => '',
            'va_number'   => '',
            'qr_string'   => '',
            'error'       => $message,
        ];
    }
}
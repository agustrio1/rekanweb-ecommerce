<?php

namespace App\Services\Duitku;

use App\Core\Database;
use PDO;
use Throwable;

class CallbackService
{
    private PDO $db;
    private DuitkuClient $client;

    public function __construct(?PDO $db = null, ?DuitkuClient $client = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->client = $client ?? new DuitkuClient();
    }

    /**
     * Proses callback server-to-server dari Duitku.
     *
     * Return: ['status' => int (kode HTTP), 'message' => string]
     */
    public function handle(array $post): array
    {
        foreach (['merchantCode', 'amount', 'merchantOrderId', 'resultCode', 'signature'] as $field) {
            if (!isset($post[$field]) || !is_string($post[$field]) || $post[$field] === '') {
                return $this->respond(400, 'Bad request');
            }
        }

        $orderNumber = $post['merchantOrderId'];
        if (!preg_match('/^[A-Za-z0-9-]{1,50}$/', $orderNumber)) {
            return $this->respond(400, 'Bad request');
        }

        if (!$this->client->verifyCallbackSignature($post)) {
            error_log("[DuitkuCallback] Signature tidak valid untuk {$orderNumber}");
            return $this->respond(400, 'Invalid signature');
        }

        $amount = (int) round((float) $post['amount']);
        $resultCode = substr($post['resultCode'], 0, 10);
        $reference = isset($post['reference']) && is_string($post['reference']) ? $post['reference'] : '';

        try {
            $this->db->beginTransaction();

            // 1. Kunci order dan transaksi pembayarannya
            $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_number = ? FOR UPDATE");
            $stmt->execute([$orderNumber]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $this->db->rollBack();
                error_log("[DuitkuCallback] Order tidak ditemukan: {$orderNumber}");
                return $this->respond(404, 'Order not found');
            }

            $orderId = (int) $order['id'];

            $stmt = $this->db->prepare("SELECT * FROM payments WHERE order_id = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                return $this->finish($orderId, $orderNumber, $resultCode, $amount, $reference, 'no_payment_record', $post, 422, 'No payment record');
            }

            // 2. Validasi nominal dan referensi
            if ($amount !== (int) $order['total_amount'] || $amount !== (int) $payment['amount']) {
                error_log("[DuitkuCallback] Nominal tidak cocok {$orderNumber}: callback={$amount}, order={$order['total_amount']}, payment={$payment['amount']}");
                return $this->finish($orderId, $orderNumber, $resultCode, $amount, $reference, 'amount_mismatch', $post, 422, 'Amount mismatch');
            }

            if ($reference !== ''
                && !empty($payment['gateway_transaction_id'])
                && !hash_equals((string) $payment['gateway_transaction_id'], $reference)
            ) {
                error_log("[DuitkuCallback] Referensi tidak cocok {$orderNumber}");
                return $this->finish($orderId, $orderNumber, $resultCode, $amount, $reference, 'reference_mismatch', $post, 422, 'Reference mismatch');
            }

            // 3. Hanya resultCode 00 (sukses) yang mengubah status
            if ($resultCode !== '00') {
                return $this->finish($orderId, $orderNumber, $resultCode, $amount, $reference, "result_{$resultCode}_noted", $post, 200, 'OK');
            }

            // 4. Idempotency: sudah dibayar atau sudah direfund
            if ($payment['status'] === 'paid' || $order['payment_status'] === 'paid') {
                return $this->finish($orderId, $orderNumber, $resultCode, $amount, $reference, 'duplicate', $post, 200, 'OK');
            }
            if ($payment['status'] === 'refunded') {
                return $this->finish($orderId, $orderNumber, $resultCode, $amount, $reference, 'ignored_refunded', $post, 200, 'OK');
            }

            // 5. Pembayaran sukses
            $late = $order['order_status'] === 'cancelled';

            $payload = $this->payload($post);

            $this->db->prepare(
                "UPDATE payments
                 SET status = 'paid',
                     paid_at = NOW(),
                     callback_data = ?,
                     gateway_transaction_id = COALESCE(gateway_transaction_id, NULLIF(?, ''))
                 WHERE id = ?"
            )->execute([json_encode($payload, JSON_UNESCAPED_UNICODE), $reference, $payment['id']]);

            $this->db->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")
                ->execute([$orderId]);

            if ($late) {
                // Stok sudah dikembalikan saat order dibatalkan, jadi jangan disentuh lagi.
                $note = 'Pembayaran diterima setelah pesanan dibatalkan. Perlu refund manual.';
                error_log("[DuitkuCallback][REFUND-NEEDED] Order {$orderNumber} dibayar Rp {$amount} setelah dibatalkan");
            } else {
                $this->db->prepare(
                    "UPDATE stock_reservations SET status = 'consumed' WHERE order_id = ? AND status = 'active'"
                )->execute([$orderId]);
                $note = 'Pembayaran diterima';
            }

            $this->db->prepare(
                "INSERT INTO order_status_histories (order_id, old_status, new_status, notes)
                 VALUES (?, ?, 'paid', ?)"
            )->execute([$orderId, $order['order_status'], $note]);

            return $this->finish($orderId, $orderNumber, $resultCode, $amount, $reference, $late ? 'paid_late_refund_needed' : 'paid', $post, 200, 'OK');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("[DuitkuCallback] Error {$orderNumber}: " . $e->getMessage());
            // 500 supaya Duitku mengirim ulang callback-nya.
            return $this->respond(500, 'Server error');
        }
    }

    /**
     * Catat callback ke payment_callbacks, commit transaksi, lalu kembalikan respons.
     */
    private function finish(int $orderId, string $orderNumber, string $resultCode, int $amount, string $reference, string $note, array $post, int $status, string $message): array
    {
        $this->db->prepare(
            "INSERT INTO payment_callbacks
                (order_id, merchant_order_id, reference, result_code, amount, note, payload)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $orderId,
            $orderNumber,
            $reference !== '' ? mb_substr($reference, 0, 100) : null,
            $resultCode,
            max(0, $amount),
            mb_substr($note, 0, 255),
            json_encode($this->payload($post), JSON_UNESCAPED_UNICODE),
        ]);

        $this->db->commit();

        return $this->respond($status, $message);
    }

    /**
     * Hanya simpan field callback yang dikenal (tanpa signature).
     */
    private function payload(array $post): array
    {
        $allowed = [
            'merchantCode', 'amount', 'merchantOrderId', 'productDetail', 'additionalParam',
            'paymentCode', 'resultCode', 'merchantUserId', 'reference', 'publisherOrderId',
            'spUserHash', 'settlementDate', 'issuerCode',
        ];

        $clean = [];
        foreach ($allowed as $key) {
            if (isset($post[$key]) && is_string($post[$key])) {
                $clean[$key] = mb_substr($post[$key], 0, 255);
            }
        }

        return $clean;
    }

    private function respond(int $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }
}
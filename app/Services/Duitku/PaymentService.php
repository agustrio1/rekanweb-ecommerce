<?php

namespace App\Services\Duitku;

use App\Core\Database;
use PDO;
use Throwable;

class PaymentService
{
    private PDO $db;
    private array $config;

    public function __construct(?PDO $db = null, ?array $config = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->config = $config ?? require dirname(__DIR__, 3) . '/config/duitku.php';
    }

    /**
     * Buat (atau pakai ulang) transaksi Duitku sesuai metode yang dipilih di checkout.
     *
     * $userId diisi dari session saat dipanggil dari controller pelanggan
     * (proteksi IDOR). Isi null hanya untuk pemanggilan internal/CLI.
     *
     * Return: ['ok' => bool, 'reused' => bool, 'error' => ?string, 'detail' => ?string]
     */
    public function createForOrder(string $orderNumber, ?int $userId = null): array
    {
        try {
            $this->db->beginTransaction();

            // 1. Ambil dan kunci order. Sisa waktu dihitung oleh MySQL
            //    (expires_at juga diisi MySQL), jadi bebas masalah zona waktu.
            $sql = "SELECT *, TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS seconds_left
                    FROM orders WHERE order_number = ?";
            $params = [$orderNumber];
            if ($userId !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            $sql .= " FOR UPDATE";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                return $this->abort('Pesanan tidak ditemukan.');
            }

            // 2. Validasi status
            if ($order['order_status'] === 'cancelled') {
                return $this->abort('Pesanan ini sudah dibatalkan.');
            }
            if ($order['payment_status'] === 'paid') {
                return $this->abort('Pesanan ini sudah dibayar.');
            }
            if ($order['payment_status'] !== 'pending') {
                return $this->abort('Pesanan ini sudah tidak dapat dibayar.');
            }
            if (empty($order['payment_method'])) {
                return $this->abort('Metode pembayaran untuk pesanan ini belum dipilih.');
            }

            $hasExpiry = $order['expires_at'] !== null;
            $secondsLeft = $hasExpiry ? (int) $order['seconds_left'] : null;

            if ($hasExpiry && $secondsLeft <= 0) {
                return $this->abort('Pesanan sudah kedaluwarsa.');
            }

            // 3. Cek transaksi yang sudah ada (cegah transaksi ganda)
            $stmt = $this->db->prepare("SELECT * FROM payments WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $usable = $existing['status'] === 'pending'
                    && (!empty($existing['va_number']) || !empty($existing['qr_string']) || !empty($existing['payment_url']));

                if ($usable) {
                    $this->db->rollBack();
                    return ['ok' => true, 'reused' => true, 'error' => null, 'detail' => null];
                }
                return $this->abort('Transaksi pembayaran untuk pesanan ini sudah ada dan tidak dapat dibuat ulang.');
            }

            // 4. Item dari snapshot order_items + ongkir
            $stmt = $this->db->prepare(
                "SELECT product_name_snapshot, variant_name_snapshot, price_snapshot, quantity
                 FROM order_items WHERE order_id = ?"
            );
            $stmt->execute([$order['id']]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                return $this->abort('Pesanan tidak memiliki item.');
            }

            $items = [];
            $itemTotal = 0;
            foreach ($rows as $row) {
                $price = (int) $row['price_snapshot'];
                $qty = (int) $row['quantity'];
                $itemTotal += $price * $qty;
                $items[] = [
                    'name'     => $row['product_name_snapshot'] . ' - ' . $row['variant_name_snapshot'],
                    'price'    => $price,
                    'quantity' => $qty,
                ];
            }

            $shipping = (int) $order['shipping_cost'];
            if ($shipping > 0) {
                $items[] = ['name' => 'Ongkos Kirim', 'price' => $shipping, 'quantity' => 1];
                $itemTotal += $shipping;
            }

            $amount = (int) $order['total_amount'];
            if ($itemTotal !== $amount) {
                error_log("[PaymentService] Total tidak konsisten order {$orderNumber}: item={$itemTotal}, total={$amount}");
                return $this->abort('Total pesanan tidak konsisten. Hubungi admin.', "item={$itemTotal} total={$amount}");
            }

            // 5. Data pelanggan
            $stmt = $this->db->prepare("SELECT name, email, phone FROM users WHERE id = ?");
            $stmt->execute([$order['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("SELECT phone FROM order_addresses WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $address = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if (!$user || empty($user['email'])) {
                return $this->abort('Data pelanggan tidak lengkap.');
            }

            $phone = $user['phone'] ?: ($address['phone'] ?? '');
            if ($phone === '') {
                return $this->abort('Nomor telepon pelanggan belum ada.');
            }

            // 6. Masa berlaku transaksi nggak boleh lebih lama dari reservasi stok
            //    (dibulatkan ke bawah supaya transaksi habis lebih dulu dari order).
            $minutes = (int) $this->config['expiry_minutes'];
            if ($hasExpiry) {
                if ($secondsLeft < 60) {
                    return $this->abort('Batas waktu pembayaran hampir habis. Pesanan akan dibatalkan otomatis.');
                }
                $minutes = intdiv($secondsLeft, 60);
            }

            $client = new DuitkuClient($this->config);

            // 7. Pastikan metode masih tersedia dan ambil nama + logonya untuk ditampilkan
            $list = $client->getPaymentMethods($amount);
            if (!$list['ok']) {
                error_log("[PaymentService] Gagal memuat metode {$orderNumber}: " . $list['error']);
                return $this->abort('Metode pembayaran gagal diverifikasi. Coba lagi sebentar.', $list['error']);
            }

            $method = null;
            foreach ($list['methods'] as $m) {
                if ($m['code'] === $order['payment_method']) {
                    $method = $m;
                    break;
                }
            }
            if ($method === null) {
                return $this->abort('Metode pembayaran yang dipilih sudah tidak tersedia. Hubungi admin.');
            }

            // 8. Buat transaksi di Duitku
            $result = $client->createTransaction([
                'merchant_order_id' => $order['order_number'],
                'amount'            => $amount,
                'payment_method'    => $order['payment_method'],
                'product_details'   => 'Pesanan ' . $order['order_number'],
                'email'             => $user['email'],
                'phone'             => $phone,
                'customer_name'     => $user['name'],
                'expiry_minutes'    => $minutes,
                'items'             => $items,
            ]);

            if (!$result['ok']) {
                error_log("[PaymentService] Buat transaksi gagal {$orderNumber}: " . $result['error']);
                return $this->abort('Gagal membuat instruksi pembayaran. Coba lagi sebentar.', $result['error']);
            }

            // Simpan link Duitku hanya kalau benar-benar domain Duitku lewat HTTPS.
            $paymentUrl = null;
            if ($result['payment_url'] !== '') {
                $parts = parse_url($result['payment_url']);
                $host = strtolower($parts['host'] ?? '');
                if (($parts['scheme'] ?? '') === 'https' && str_ends_with($host, '.duitku.com')) {
                    $paymentUrl = $result['payment_url'];
                }
            }

            // 9. Simpan transaksi
            $stmt = $this->db->prepare(
                "INSERT INTO payments
                    (order_id, payment_gateway, gateway_transaction_id, payment_url, payment_method,
                     payment_name, payment_image, va_number, qr_string, amount, status)
                 VALUES (?, 'duitku', ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([
                $order['id'],
                $result['reference'],
                $paymentUrl,
                $order['payment_method'],
                $method['name'],
                $method['image'] !== '' ? $method['image'] : null,
                $result['va_number'] !== '' ? $result['va_number'] : null,
                $result['qr_string'] !== '' ? $result['qr_string'] : null,
                $amount,
            ]);

            $this->db->commit();

            return ['ok' => true, 'reused' => false, 'error' => null, 'detail' => null];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[PaymentService] ' . $e->getMessage());
            return [
                'ok'     => false,
                'reused' => false,
                'error'  => 'Terjadi kesalahan sistem saat membuat pembayaran.',
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function abort(string $message, ?string $detail = null): array
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        return ['ok' => false, 'reused' => false, 'error' => $message, 'detail' => $detail];
    }
}
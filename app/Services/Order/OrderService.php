<?php

namespace App\Services\Order;

use App\Core\Database;
use App\Models\Address;
use App\Models\Cart;
use App\Services\Biteship\ShippingService;
use App\Services\Duitku\DuitkuClient;
use PDO;

class OrderService
{
    /** Lama stok ditahan dan waktu bayar (menit). Samakan dengan expiryPeriod Duitku di Phase 7. */
    public const PAYMENT_WINDOW_MINUTES = 60;

    /**
     * Buat pesanan dari keranjang pelanggan yang login.
     *
     * @return array{ok: bool, message: string, redirect: string, order_number: string}
     */
    public static function place(int $userId, int $addressId, string $shippingKey, int $seenShipping, string $paymentMethod): array
    {
        self::releaseExpired();

        $cart = Cart::details();
        if (!$cart['canCheckout']) {
            return self::fail('Keranjang belum siap di-checkout. Periksa stok atau hapus produk yang tidak tersedia.', '/keranjang');
        }

        $address = Address::find($addressId, $userId);
        if ($address === null || (string) $address['area_id'] === '') {
            return self::fail('Pilih alamat pengiriman yang lengkap.');
        }

        if ($shippingKey === '') {
            return self::fail('Pilih metode pengiriman terlebih dahulu.');
        }

        if ($paymentMethod === '' || !preg_match('/^[A-Za-z0-9_]{1,20}$/', $paymentMethod)) {
            return self::fail('Pilih metode pembayaran terlebih dahulu.');
        }

        // Tarif selalu diminta ulang ke Biteship (tanpa cache) sebelum pesanan dibuat.
        $quote = ShippingService::quote($cart['lines'], $address, false);
        if (!$quote['ok']) {
            return self::fail($quote['message']);
        }

        $option = null;
        foreach ($quote['options'] as $candidate) {
            if ($candidate['key'] === $shippingKey) {
                $option = $candidate;
                break;
            }
        }

        if ($option === null) {
            return self::fail('Layanan pengiriman yang dipilih tidak tersedia lagi. Silakan pilih ulang.');
        }

        if ($seenShipping !== $option['price']) {
            return self::fail(
                'Ongkir berubah menjadi Rp ' . number_format($option['price'], 0, ',', '.') . '. Periksa kembali, lalu lanjutkan.'
            );
        }

        // Kode metode pembayaran divalidasi ulang ke Duitku untuk nominal sebenarnya.
        $methods = (new DuitkuClient())->getPaymentMethods((int) $cart['subtotal'] + (int) $option['price']);
        if (!$methods['ok']) {
            error_log('Order: gagal memverifikasi metode pembayaran: ' . $methods['error']);

            return self::fail('Metode pembayaran tidak dapat diverifikasi saat ini. Silakan coba lagi.');
        }

        $methodValid = false;
        foreach ($methods['methods'] as $m) {
            if ($m['code'] === $paymentMethod) {
                $methodValid = true;
                break;
            }
        }

        if (!$methodValid) {
            return self::fail('Metode pembayaran yang dipilih tidak tersedia. Silakan pilih ulang.');
        }

        $cartId = Cart::currentId(false);
        if ($cartId === null) {
            return self::fail('Keranjang kosong.', '/keranjang');
        }

        $expected = [];
        foreach ($cart['lines'] as $line) {
            if ($line['available']) {
                $expected[(int) $line['variant_id']] = (int) $line['qty'];
            }
        }
        ksort($expected);

        $pdo = Database::getInstance()->getConnection();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $number = self::createOrder($pdo, $userId, $cartId, $address, $option, $expected, $paymentMethod);

                return ['ok' => true, 'message' => '', 'redirect' => '', 'order_number' => $number];
            } catch (\DomainException $e) {
                self::rollback($pdo);

                return self::fail($e->getMessage(), $e->getCode() === 1 ? '/keranjang' : '/checkout');
            } catch (\PDOException $e) {
                self::rollback($pdo);

                // 1062 = nomor pesanan bentrok, 1213 = deadlock: aman diulang.
                $code = (int) ($e->errorInfo[1] ?? 0);
                if (in_array($code, [1062, 1213], true) && $attempt < 3) {
                    continue;
                }

                error_log('Order error: ' . $e->getMessage());

                return self::fail('Terjadi kesalahan saat membuat pesanan. Silakan coba lagi.');
            } catch (\Throwable $e) {
                self::rollback($pdo);
                error_log('Order error: ' . $e->getMessage());

                return self::fail('Terjadi kesalahan saat membuat pesanan. Silakan coba lagi.');
            }
        }

        return self::fail('Terjadi kesalahan saat membuat pesanan. Silakan coba lagi.');
    }

    /**
     * Batalkan pesanan yang belum dibayar dan sudah lewat batas waktu, lalu kembalikan stok.
     * Aman dipanggil berulang: tiap pesanan hanya diproses sekali.
     */
    public static function releaseExpired(int $limit = 50): int
    {
        $limit = max(1, $limit);
        $pdo = Database::getInstance()->getConnection();

        $ids = $pdo->query(
            "SELECT id FROM orders
             WHERE payment_status = 'pending' AND order_status = 'pending'
               AND expires_at IS NOT NULL AND expires_at < NOW()
             ORDER BY id ASC
             LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_COLUMN);

        $released = 0;
        foreach ($ids as $id) {
            if (self::expireOrder($pdo, (int) $id)) {
                $released++;
            }
        }

        return $released;
    }

    private static function expireOrder(PDO $pdo, int $orderId): bool
    {
        try {
            $pdo->beginTransaction();

            // Kunci pesanan dan cek ulang: bisa jadi baru saja dibayar atau sudah diproses proses lain.
            $stmt = $pdo->prepare(
                "SELECT id FROM orders
                 WHERE id = :id AND payment_status = 'pending' AND order_status = 'pending'
                   AND expires_at IS NOT NULL AND expires_at < NOW()
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $orderId]);

            if ($stmt->fetchColumn() === false) {
                $pdo->rollBack();
                return false;
            }

            $stmt = $pdo->prepare(
                "SELECT product_variant_id, quantity FROM stock_reservations
                 WHERE order_id = :id AND status = 'active'
                 ORDER BY product_variant_id ASC
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $orderId]);

            $restore = $pdo->prepare('UPDATE product_variants SET stock = stock + :q WHERE id = :v');
            foreach ($stmt->fetchAll() as $row) {
                $restore->execute(['q' => (int) $row['quantity'], 'v' => (int) $row['product_variant_id']]);
            }

            $pdo->prepare("UPDATE stock_reservations SET status = 'released' WHERE order_id = :id AND status = 'active'")
                ->execute(['id' => $orderId]);

            $pdo->prepare("UPDATE orders SET order_status = 'cancelled', payment_status = 'expired' WHERE id = :id")
                ->execute(['id' => $orderId]);

            // Transaksi pembayaran ikut kedaluwarsa (kalau sempat dibuat).
            $pdo->prepare("UPDATE payments SET status = 'expired' WHERE order_id = :id AND status = 'pending'")
                ->execute(['id' => $orderId]);

            $pdo->prepare("UPDATE shipments SET status = 'cancelled' WHERE order_id = :id")
                ->execute(['id' => $orderId]);

            $pdo->prepare(
                "INSERT INTO order_status_histories (order_id, old_status, new_status, notes)
                 VALUES (:id, 'pending', 'cancelled', 'Batas waktu pembayaran habis, stok dikembalikan')"
            )->execute(['id' => $orderId]);

            $pdo->commit();

            return true;
        } catch (\Throwable $e) {
            self::rollback($pdo);
            error_log('Expire order error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Seluruh pembuatan pesanan dalam satu transaksi. Melempar DomainException untuk
     * masalah bisnis (kode 1 = kembali ke keranjang, lainnya = kembali ke checkout).
     */
    private static function createOrder(PDO $pdo, int $userId, int $cartId, array $address, array $option, array $expected, string $paymentMethod): string
    {
        $pdo->beginTransaction();

        // 1. Kunci keranjang: klik ganda atau dua tab tidak bisa membuat dua pesanan.
        $pdo->prepare('SELECT id FROM carts WHERE id = :c FOR UPDATE')->execute(['c' => $cartId]);

        $stmt = $pdo->prepare('SELECT product_variant_id, quantity FROM cart_items WHERE cart_id = :c');
        $stmt->execute(['c' => $cartId]);

        $stored = [];
        foreach ($stmt->fetchAll() as $row) {
            $vid = (int) $row['product_variant_id'];
            $stored[$vid] = ($stored[$vid] ?? 0) + (int) $row['quantity'];
        }

        if (empty($stored)) {
            throw new \DomainException('Keranjang kosong. Pesananmu mungkin sudah dibuat, cek halaman Pesanan saya.', 1);
        }

        ksort($stored);
        $ids = array_keys($stored);
        $marks = implode(',', array_fill(0, count($ids), '?'));

        // 2. Kunci baris varian (urut ID agar tidak deadlock). Harga dan stok dibaca dari sini.
        $stmt = $pdo->prepare(
            "SELECT id, product_id, sku, variant_name, price, discount_price, stock
             FROM product_variants
             WHERE id IN ({$marks})
             ORDER BY id ASC
             FOR UPDATE"
        );
        $stmt->execute($ids);

        $variants = [];
        foreach ($stmt->fetchAll() as $row) {
            $variants[(int) $row['id']] = $row;
        }

        $productIds = array_values(array_unique(array_map(fn($v) => (int) $v['product_id'], $variants)));
        $products = [];

        if (!empty($productIds)) {
            $pMarks = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT p.id, p.name, p.weight_gram
                 FROM products p
                 JOIN categories c ON c.id = p.category_id
                 WHERE p.id IN ({$pMarks}) AND p.is_active = 1 AND c.is_active = 1"
            );
            $stmt->execute($productIds);

            foreach ($stmt->fetchAll() as $row) {
                $products[(int) $row['id']] = $row;
            }
        }

        // 3. Validasi stok dan hitung subtotal dari harga di database.
        $lines = [];
        $final = [];
        $subtotal = 0;

        foreach ($stored as $vid => $storedQty) {
            $variant = $variants[$vid] ?? null;
            $product = $variant ? ($products[(int) $variant['product_id']] ?? null) : null;

            if ($variant === null || $product === null) {
                throw new \DomainException('Ada produk yang sudah tidak tersedia. Hapus dari keranjang untuk melanjutkan.', 1);
            }

            $stock = (int) $variant['stock'];
            if ($stock <= 0) {
                throw new \DomainException('Stok "' . $product['name'] . '" baru saja habis. Hapus dari keranjang untuk melanjutkan.', 1);
            }

            $qty = min($storedQty, $stock);
            $price = (int) $variant['price'];
            $discount = $variant['discount_price'] !== null ? (int) $variant['discount_price'] : 0;
            $unit = ($discount > 0 && $discount < $price) ? $discount : $price;

            $final[$vid] = $qty;
            $subtotal += $unit * $qty;

            $lines[] = [
                'variant_id' => $vid,
                'product_name' => (string) $product['name'],
                'variant_name' => (string) $variant['variant_name'],
                'sku' => (string) $variant['sku'],
                'unit_price' => $unit,
                'qty' => $qty,
                'weight' => (int) $product['weight_gram'],
            ];
        }

        // Ongkir dihitung dari isi keranjang yang sama dengan yang dilihat pelanggan.
        if ($final !== $expected) {
            throw new \DomainException('Isi keranjang atau stok berubah saat diproses. Periksa kembali pesananmu.');
        }

        $shipping = (int) $option['price'];
        $total = $subtotal + $shipping;
        $number = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // 4. Pesanan. Batas bayar dihitung oleh MySQL agar zona waktunya konsisten.
        $window = (int) self::PAYMENT_WINDOW_MINUTES;
        $pdo->prepare(
            "INSERT INTO orders (order_number, user_id, subtotal, shipping_cost, payment_method, total_amount, order_status, payment_status, expires_at)
             VALUES (:number, :user, :subtotal, :shipping, :method, :total, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL {$window} MINUTE))"
        )->execute([
            'number' => $number,
            'user' => $userId,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'method' => $paymentMethod,
            'total' => $total,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        // 5. Item (snapshot), pengurangan stok, dan reservasi.
        $insertItem = $pdo->prepare(
            'INSERT INTO order_items
                (order_id, product_variant_id, product_name_snapshot, variant_name_snapshot, sku_snapshot, price_snapshot, quantity, weight_gram_snapshot)
             VALUES (:o, :v, :pname, :vname, :sku, :price, :qty, :weight)'
        );
        $deduct = $pdo->prepare('UPDATE product_variants SET stock = stock - :q1 WHERE id = :id AND stock >= :q2');
        $reserve = $pdo->prepare(
            "INSERT INTO stock_reservations (order_id, product_variant_id, quantity, status) VALUES (:o, :v, :q, 'active')"
        );

        foreach ($lines as $line) {
            $insertItem->execute([
                'o' => $orderId,
                'v' => $line['variant_id'],
                'pname' => $line['product_name'],
                'vname' => $line['variant_name'],
                'sku' => $line['sku'],
                'price' => $line['unit_price'],
                'qty' => $line['qty'],
                'weight' => $line['weight'],
            ]);

            $deduct->execute(['q1' => $line['qty'], 'id' => $line['variant_id'], 'q2' => $line['qty']]);
            if ($deduct->rowCount() !== 1) {
                throw new \DomainException('Stok "' . $line['product_name'] . '" tidak mencukupi. Periksa kembali pesananmu.', 1);
            }

            $reserve->execute(['o' => $orderId, 'v' => $line['variant_id'], 'q' => $line['qty']]);
        }

        // 6. Snapshot alamat dan kurir.
        $pdo->prepare(
            'INSERT INTO order_addresses
                (order_id, recipient_name, phone, area_id, area_name, province, city, district, postal_code, address_detail)
             VALUES (:o, :name, :phone, :area_id, :area_name, :province, :city, :district, :postal, :detail)'
        )->execute([
            'o' => $orderId,
            'name' => $address['recipient_name'],
            'phone' => $address['phone'],
            'area_id' => $address['area_id'],
            'area_name' => $address['area_name'],
            'province' => $address['province'],
            'city' => $address['city'],
            'district' => $address['district'],
            'postal' => $address['postal_code'],
            'detail' => $address['address_detail'],
        ]);

        $pdo->prepare(
            'INSERT INTO shipments (order_id, courier_company, courier_type, courier_name, service_name, estimate, shipping_price)
             VALUES (:o, :code, :service, :cname, :sname, :estimate, :price)'
        )->execute([
            'o' => $orderId,
            'code' => $option['courier_code'],
            'service' => $option['service_code'],
            'cname' => $option['courier_name'],
            'sname' => $option['service_name'],
            'estimate' => $option['estimate'],
            'price' => $shipping,
        ]);

        $pdo->prepare(
            "INSERT INTO order_status_histories (order_id, old_status, new_status, notes)
             VALUES (:o, NULL, 'pending', 'Pesanan dibuat, menunggu pembayaran')"
        )->execute(['o' => $orderId]);

        // 7. Kosongkan keranjang.
        $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :c')->execute(['c' => $cartId]);

        $pdo->commit();

        return $number;
    }

    private static function rollback(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    private static function fail(string $message, string $redirect = '/checkout'): array
    {
        return ['ok' => false, 'message' => $message, 'redirect' => $redirect, 'order_number' => ''];
    }
}
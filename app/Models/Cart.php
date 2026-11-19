<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Cart
{
    private const MAX_ADD_QTY = 99;

    /**
     * ID keranjang milik pengunjung saat ini.
     * User login: keranjang berdasarkan user_id (keranjang guest digabung otomatis).
     * Guest: keranjang berdasarkan token di session.
     */
    public static function currentId(bool $create = false): ?int
    {
        $pdo = Database::getInstance()->getConnection();
        $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
        $token = isset($_SESSION['cart_token']) && is_string($_SESSION['cart_token']) ? $_SESSION['cart_token'] : '';

        if ($userId > 0) {
            $cartId = self::findId($pdo, 'user_id = :v', $userId);

            if ($token !== '') {
                $guestId = self::findId($pdo, 'session_id = :v AND user_id IS NULL', $token);

                if ($guestId !== null) {
                    if ($cartId === null) {
                        $stmt = $pdo->prepare('UPDATE carts SET user_id = :u, session_id = NULL WHERE id = :id');
                        $stmt->execute(['u' => $userId, 'id' => $guestId]);
                        $cartId = $guestId;
                    } else {
                        self::merge($pdo, $guestId, $cartId);
                    }
                }
                unset($_SESSION['cart_token']);
            }

            if ($cartId === null && $create) {
                $stmt = $pdo->prepare('INSERT INTO carts (user_id) VALUES (:u)');
                $stmt->execute(['u' => $userId]);
                $cartId = (int) $pdo->lastInsertId();
            }

            return $cartId;
        }

        $cartId = $token !== '' ? self::findId($pdo, 'session_id = :v AND user_id IS NULL', $token) : null;

        if ($cartId === null && $create) {
            if ($token === '') {
                $token = bin2hex(random_bytes(16));
                $_SESSION['cart_token'] = $token;
            }
            $stmt = $pdo->prepare('INSERT INTO carts (session_id) VALUES (:s)');
            $stmt->execute(['s' => $token]);
            $cartId = (int) $pdo->lastInsertId();
        }

        return $cartId;
    }

    /**
     * Total quantity untuk badge header. Tanpa query jika pengunjung belum punya keranjang.
     */
    public static function count(): int
    {
        if (empty($_SESSION['user']['id']) && empty($_SESSION['cart_token'])) {
            return 0;
        }

        $cartId = self::currentId(false);
        if ($cartId === null) {
            return 0;
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = :c');
        $stmt->execute(['c' => $cartId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Isi keranjang dengan harga dan stok terbaru dari database.
     *
     * @return array{lines: array, subtotal: int, itemCount: int, hasUnavailable: bool, hasAdjusted: bool, canCheckout: bool}
     */
    public static function details(): array
    {
        $lines = [];
        $cartId = self::currentId(false);

        if ($cartId !== null) {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT ci.id AS item_id, ci.quantity,
                        v.id AS variant_id, v.variant_name, v.price, v.discount_price, v.stock,
                        p.name, p.slug, p.is_active AS product_active, c.is_active AS category_active,
                        (SELECT pi.image_path
                         FROM product_images pi
                         WHERE pi.product_id = p.id
                         ORDER BY pi.is_primary DESC, pi.id ASC
                         LIMIT 1) AS image
                 FROM cart_items ci
                 JOIN product_variants v ON v.id = ci.product_variant_id
                 JOIN products p ON p.id = v.product_id
                 JOIN categories c ON c.id = p.category_id
                 WHERE ci.cart_id = :c
                 ORDER BY ci.id ASC'
            );
            $stmt->execute(['c' => $cartId]);

            foreach ($stmt->fetchAll() as $row) {
                $price = (int) $row['price'];
                $discount = $row['discount_price'] !== null ? (int) $row['discount_price'] : 0;
                $hasSale = $discount > 0 && $discount < $price;
                $unit = $hasSale ? $discount : $price;

                $stock = (int) $row['stock'];
                $stored = (int) $row['quantity'];
                $available = (int) $row['product_active'] === 1 && (int) $row['category_active'] === 1 && $stock > 0;
                $qty = $available ? min($stored, $stock) : $stored;

                $lines[] = [
                    'item_id' => (int) $row['item_id'],
                    'variant_id' => (int) $row['variant_id'],
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'variant_name' => $row['variant_name'],
                    'image' => (string) ($row['image'] ?? ''),
                    'unit_price' => $unit,
                    'original_price' => $hasSale ? $price : null,
                    'stock' => $stock,
                    'qty' => $qty,
                    'available' => $available,
                    'adjusted' => $available && $stored > $stock,
                    'line_total' => $available ? $unit * $qty : 0,
                ];
            }
        }

        $subtotal = 0;
        $itemCount = 0;
        $hasUnavailable = false;
        $hasAdjusted = false;

        foreach ($lines as $line) {
            if ($line['available']) {
                $subtotal += $line['line_total'];
                $itemCount += $line['qty'];
            } else {
                $hasUnavailable = true;
            }
            if ($line['adjusted']) {
                $hasAdjusted = true;
            }
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'itemCount' => $itemCount,
            'hasUnavailable' => $hasUnavailable,
            'hasAdjusted' => $hasAdjusted,
            'canCheckout' => $itemCount > 0 && !$hasUnavailable,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function add(int $variantId, int $qty): array
    {
        $qty = max(1, min($qty, self::MAX_ADD_QTY));
        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare(
            'SELECT v.id, v.stock
             FROM product_variants v
             JOIN products p ON p.id = v.product_id AND p.is_active = 1
             JOIN categories c ON c.id = p.category_id AND c.is_active = 1
             WHERE v.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $variantId]);
        $variant = $stmt->fetch();

        if (!$variant) {
            return ['ok' => false, 'message' => 'Produk tidak tersedia.'];
        }

        $stock = (int) $variant['stock'];
        if ($stock <= 0) {
            return ['ok' => false, 'message' => 'Maaf, stok variasi ini sedang habis.'];
        }

        $cartId = self::currentId(true);

        $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = :c AND product_variant_id = :v LIMIT 1');
        $stmt->execute(['c' => $cartId, 'v' => $variantId]);
        $existing = $stmt->fetch();

        $current = $existing ? (int) $existing['quantity'] : 0;

        if ($current >= $stock) {
            return ['ok' => false, 'message' => 'Jumlah di keranjang sudah mencapai stok yang tersedia (' . $stock . ').'];
        }

        $newQty = min($current + $qty, $stock);
        $adjusted = $current + $qty > $stock;

        if ($existing) {
            $stmt = $pdo->prepare('UPDATE cart_items SET quantity = :q WHERE id = :id');
            $stmt->execute(['q' => $newQty, 'id' => $existing['id']]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO cart_items (cart_id, product_variant_id, quantity) VALUES (:c, :v, :q)');
            $stmt->execute(['c' => $cartId, 'v' => $variantId, 'q' => $newQty]);
        }

        return [
            'ok' => true,
            'message' => $adjusted
                ? 'Ditambahkan ke keranjang. Jumlah disesuaikan dengan stok yang tersedia (' . $stock . ').'
                : 'Produk ditambahkan ke keranjang.',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function updateQuantity(int $itemId, int $qty): array
    {
        $cartId = self::currentId(false);
        if ($cartId === null) {
            return ['ok' => false, 'message' => 'Keranjang tidak ditemukan.'];
        }

        if ($qty < 1) {
            return self::remove($itemId);
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT ci.id, v.stock
             FROM cart_items ci
             JOIN product_variants v ON v.id = ci.product_variant_id
             WHERE ci.id = :id AND ci.cart_id = :c
             LIMIT 1'
        );
        $stmt->execute(['id' => $itemId, 'c' => $cartId]);
        $item = $stmt->fetch();

        if (!$item) {
            return ['ok' => false, 'message' => 'Produk tidak ada di keranjang.'];
        }

        $stock = (int) $item['stock'];
        if ($stock <= 0) {
            return ['ok' => false, 'message' => 'Stok produk ini habis. Hapus dari keranjang untuk melanjutkan.'];
        }

        $newQty = min($qty, $stock);

        $stmt = $pdo->prepare('UPDATE cart_items SET quantity = :q WHERE id = :id AND cart_id = :c');
        $stmt->execute(['q' => $newQty, 'id' => $itemId, 'c' => $cartId]);

        if ($qty > $stock) {
            return ['ok' => true, 'message' => 'Jumlah disesuaikan dengan stok yang tersedia (' . $stock . ').'];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function remove(int $itemId): array
    {
        $cartId = self::currentId(false);
        if ($cartId === null) {
            return ['ok' => false, 'message' => 'Keranjang tidak ditemukan.'];
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE id = :id AND cart_id = :c');
        $stmt->execute(['id' => $itemId, 'c' => $cartId]);

        return ['ok' => true, 'message' => $stmt->rowCount() > 0 ? 'Produk dihapus dari keranjang.' : ''];
    }

    private static function findId(PDO $pdo, string $condition, $value): ?int
    {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE {$condition} LIMIT 1");
        $stmt->execute(['v' => $value]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Pindahkan isi keranjang guest ke keranjang user, lalu hapus keranjang guest.
     */
    private static function merge(PDO $pdo, int $guestId, int $targetId): void
    {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id, product_variant_id, quantity FROM cart_items WHERE cart_id = :g');
            $stmt->execute(['g' => $guestId]);
            $guestItems = $stmt->fetchAll();

            $find = $pdo->prepare('SELECT id FROM cart_items WHERE cart_id = :t AND product_variant_id = :v LIMIT 1');
            $add = $pdo->prepare('UPDATE cart_items SET quantity = quantity + :q WHERE id = :id');
            $move = $pdo->prepare('UPDATE cart_items SET cart_id = :t WHERE id = :id');

            foreach ($guestItems as $item) {
                $find->execute(['t' => $targetId, 'v' => $item['product_variant_id']]);
                $existingId = $find->fetchColumn();

                if ($existingId !== false) {
                    $add->execute(['q' => $item['quantity'], 'id' => $existingId]);
                } else {
                    $move->execute(['t' => $targetId, 'id' => $item['id']]);
                }
            }

            $stmt = $pdo->prepare('DELETE FROM carts WHERE id = :g');
            $stmt->execute(['g' => $guestId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Cart merge error: ' . $e->getMessage());
        }
    }
}
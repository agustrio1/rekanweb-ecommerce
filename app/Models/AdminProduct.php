<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;

class AdminProduct
{
    public const PER_PAGE = 15;
    public const MAX_IMAGES = 8;

    /**
     * Daftar produk untuk admin (aktif dan nonaktif).
     *
     * @return array{rows: array, total: int, page: int, pages: int}
     */
    public static function paginate(string $q, int $page): array
    {
        $pdo = Database::getInstance()->getConnection();

        $where = '1 = 1';
        $params = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where = '(p.name LIKE :q1 OR EXISTS (
                SELECT 1 FROM product_variants sv WHERE sv.product_id = p.id AND sv.sku LIKE :q2
            ))';
            $params = ['q1' => $like, 'q2' => $like];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$where}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $limit = self::PER_PAGE;
        $offset = ($page - 1) * self::PER_PAGE;

        $sql = "SELECT p.id, p.name, p.slug, p.is_active, c.name AS category_name,
                       (SELECT MIN(COALESCE(v.discount_price, v.price)) FROM product_variants v WHERE v.product_id = p.id) AS min_price,
                       (SELECT MAX(COALESCE(v.discount_price, v.price)) FROM product_variants v WHERE v.product_id = p.id) AS max_price,
                       (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id) AS stock,
                       (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count,
                       (SELECT pi.image_path FROM product_images pi
                        WHERE pi.product_id = p.id
                        ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE {$where}
                ORDER BY p.created_at DESC, p.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['is_active'] = (int) $row['is_active'];
            $row['min_price'] = $row['min_price'] !== null ? (int) $row['min_price'] : null;
            $row['max_price'] = $row['max_price'] !== null ? (int) $row['max_price'] : null;
            $row['stock'] = (int) $row['stock'];
            $row['variant_count'] = (int) $row['variant_count'];
        }
        unset($row);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Produk lengkap dengan variasi dan gambar (untuk form ubah).
     */
    public static function find(int $id): ?array
    {
        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare(
            'SELECT id, category_id, name, slug, description, weight_gram,
                    length_cm, width_cm, height_cm, is_active
             FROM products WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }

        foreach (['id', 'category_id', 'weight_gram', 'length_cm', 'width_cm', 'height_cm', 'is_active'] as $key) {
            $product[$key] = (int) $product[$key];
        }

        $stmt = $pdo->prepare(
            'SELECT id, sku, variant_name, price, discount_price, stock
             FROM product_variants WHERE product_id = :id ORDER BY id ASC'
        );
        $stmt->execute(['id' => $id]);
        $variants = $stmt->fetchAll();
        foreach ($variants as &$v) {
            $v['id'] = (int) $v['id'];
            $v['price'] = (int) $v['price'];
            $v['discount_price'] = $v['discount_price'] !== null ? (int) $v['discount_price'] : null;
            $v['stock'] = (int) $v['stock'];
        }
        unset($v);
        $product['variants'] = $variants;

        $stmt = $pdo->prepare(
            'SELECT id, image_path, is_primary
             FROM product_images WHERE product_id = :id
             ORDER BY is_primary DESC, id ASC'
        );
        $stmt->execute(['id' => $id]);
        $images = $stmt->fetchAll();
        foreach ($images as &$img) {
            $img['id'] = (int) $img['id'];
            $img['is_primary'] = (int) $img['is_primary'];
        }
        unset($img);
        $product['images'] = $images;

        return $product;
    }

    /**
     * Apakah SKU sudah dipakai variasi lain (selain variasi $ignoreVariantId).
     */
    public static function skuTaken(string $sku, int $ignoreVariantId): bool
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_variants WHERE sku = :sku AND id <> :id');
        $stmt->execute(['sku' => $sku, 'id' => $ignoreVariantId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Buat produk beserta variasinya dalam satu transaksi.
     * Melempar PDOException jika SKU bentrok.
     *
     * @param array $data Hasil validasi, termasuk kunci 'variants'.
     */
    public static function create(array $data): int
    {
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO products (category_id, name, slug, description, weight_gram, length_cm, width_cm, height_cm, is_active)
                 VALUES (:cat, :name, :slug, :desc, :weight, :len, :wid, :hei, :active)'
            );
            $stmt->execute([
                'cat' => $data['category_id'],
                'name' => $data['name'],
                'slug' => self::uniqueSlug($data['name']),
                'desc' => $data['description'],
                'weight' => $data['weight_gram'],
                'len' => $data['length_cm'],
                'wid' => $data['width_cm'],
                'hei' => $data['height_cm'],
                'active' => $data['is_active'],
            ]);
            $id = (int) $pdo->lastInsertId();

            foreach ($data['variants'] as $variant) {
                self::insertVariant($pdo, $id, $variant);
            }

            $pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ubah produk dan sinkronkan variasinya dalam satu transaksi.
     * - Variasi dengan id milik produk ini diperbarui.
     * - Variasi tanpa id baru ditambahkan.
     * - Variasi yang hilang dari form dihapus, kecuali sudah pernah dipesan.
     *
     * Slug sengaja tidak diubah.
     *
     * @throws \RuntimeException jika variasi yang dihapus sudah pernah dipesan
     * @throws PDOException      jika SKU bentrok
     */
    public static function update(int $id, array $data): void
    {
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'UPDATE products
                 SET category_id = :cat, name = :name, description = :desc,
                     weight_gram = :weight, length_cm = :len, width_cm = :wid, height_cm = :hei,
                     is_active = :active
                 WHERE id = :id'
            );
            $stmt->execute([
                'cat' => $data['category_id'],
                'name' => $data['name'],
                'desc' => $data['description'],
                'weight' => $data['weight_gram'],
                'len' => $data['length_cm'],
                'wid' => $data['width_cm'],
                'hei' => $data['height_cm'],
                'active' => $data['is_active'],
                'id' => $id,
            ]);

            $stmt = $pdo->prepare('SELECT id, variant_name FROM product_variants WHERE product_id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $existing = [];
            foreach ($stmt->fetchAll() as $row) {
                $existing[(int) $row['id']] = $row['variant_name'];
            }

            $keep = [];
            foreach ($data['variants'] as $variant) {
                if ($variant['id'] > 0 && isset($existing[$variant['id']])) {
                    $keep[$variant['id']] = true;
                }
            }

            foreach ($existing as $variantId => $variantName) {
                if (isset($keep[$variantId])) {
                    continue;
                }
                if (self::variantUsed($pdo, $variantId)) {
                    throw new \RuntimeException(
                        'Variasi "' . $variantName . '" sudah pernah dipesan sehingga tidak bisa dihapus. Ubah nama atau set stoknya 0.'
                    );
                }
                $pdo->prepare('DELETE FROM product_variants WHERE id = :id AND product_id = :pid')
                    ->execute(['id' => $variantId, 'pid' => $id]);
            }

            $updateStmt = $pdo->prepare(
                'UPDATE product_variants
                 SET sku = :sku, variant_name = :name, price = :price, discount_price = :disc, stock = :stock
                 WHERE id = :id AND product_id = :pid'
            );
            foreach ($data['variants'] as $variant) {
                if (isset($keep[$variant['id']])) {
                    $updateStmt->execute([
                        'sku' => $variant['sku'],
                        'name' => $variant['name'],
                        'price' => $variant['price'],
                        'disc' => $variant['discount'],
                        'stock' => $variant['stock'],
                        'id' => $variant['id'],
                        'pid' => $id,
                    ]);
                } else {
                    self::insertVariant($pdo, $id, $variant);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function setActive(int $id, bool $active): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('UPDATE products SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /**
     * Hapus produk. Mengembalikan daftar path gambar untuk dihapus dari disk,
     * atau null jika produk sudah punya riwayat pesanan (tidak boleh dihapus).
     */
    public static function delete(int $id): ?array
    {
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM order_items oi
                 JOIN product_variants pv ON pv.id = oi.product_variant_id
                 WHERE pv.product_id = :id'
            );
            $stmt->execute(['id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $pdo->rollBack();

                return null;
            }

            $stmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = :id');
            $stmt->execute(['id' => $id]);
            $paths = array_column($stmt->fetchAll(), 'image_path');

            $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
            $pdo->commit();

            return $paths;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null; // ditolak foreign key (misalnya reservasi stok)
        }
    }

    // ---------------------------------------------------------------- Gambar

    public static function imageCount(int $productId): int
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = :id');
        $stmt->execute(['id' => $productId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Tambah gambar. Gambar pertama otomatis menjadi gambar utama.
     */
    public static function addImage(int $productId, string $path): void
    {
        $primary = self::imageCount($productId) === 0 ? 1 : 0;

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO product_images (product_id, image_path, is_primary) VALUES (:pid, :path, :primary)'
        );
        $stmt->execute(['pid' => $productId, 'path' => $path, 'primary' => $primary]);
    }

    public static function findImage(int $productId, int $imageId): ?array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, image_path, is_primary FROM product_images WHERE id = :id AND product_id = :pid LIMIT 1'
        );
        $stmt->execute(['id' => $imageId, 'pid' => $productId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function setPrimaryImage(int $productId, int $imageId): void
    {
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        try {
            $pdo->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = :pid')
                ->execute(['pid' => $productId]);
            $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE id = :id AND product_id = :pid')
                ->execute(['id' => $imageId, 'pid' => $productId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Hapus gambar dari database dan kembalikan path-nya (untuk dihapus dari disk).
     * Jika yang dihapus gambar utama, gambar tertua yang tersisa dijadikan utama.
     */
    public static function deleteImage(int $productId, int $imageId): ?string
    {
        $image = self::findImage($productId, $imageId);
        if ($image === null) {
            return null;
        }

        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        try {
            $pdo->prepare('DELETE FROM product_images WHERE id = :id AND product_id = :pid')
                ->execute(['id' => $imageId, 'pid' => $productId]);

            if ((int) $image['is_primary'] === 1) {
                $pdo->prepare(
                    'UPDATE product_images SET is_primary = 1
                     WHERE product_id = :pid
                     ORDER BY id ASC LIMIT 1'
                )->execute(['pid' => $productId]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $image['image_path'];
    }

    // --------------------------------------------------------------- Internal

    private static function insertVariant(PDO $pdo, int $productId, array $variant): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO product_variants (product_id, sku, variant_name, price, discount_price, stock)
             VALUES (:pid, :sku, :name, :price, :disc, :stock)'
        );
        $stmt->execute([
            'pid' => $productId,
            'sku' => $variant['sku'],
            'name' => $variant['name'],
            'price' => $variant['price'],
            'disc' => $variant['discount'],
            'stock' => $variant['stock'],
        ]);
    }

    private static function variantUsed(PDO $pdo, int $variantId): bool
    {
        $stmt = $pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM order_items WHERE product_variant_id = :a)
                  + (SELECT COUNT(*) FROM stock_reservations WHERE product_variant_id = :b)'
        );
        $stmt->execute(['a' => $variantId, 'b' => $variantId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function uniqueSlug(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-');
        $base = $base !== '' ? mb_substr($base, 0, 180) : 'produk';

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = :slug');

        $slug = $base;
        $i = 2;
        while (true) {
            $stmt->execute(['slug' => $slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }
}
<?php

namespace App\Models;

use App\Core\Database;

class Product
{
    public const SORTS = ['terbaru', 'populer', 'termurah', 'termahal'];

    public static function latest(int $limit = 8): array
    {
        return self::fetch('', 'p.is_active = 1', [], '', 'p.created_at DESC, p.id DESC', $limit);
    }

    /**
     * Produk terlaris dari pesanan yang sudah lunas. Kosong jika belum ada penjualan.
     */
    public static function popular(int $limit = 4): array
    {
        return self::fetch(self::soldSelect(), 'p.is_active = 1', [], 'HAVING sold > 0', 'sold DESC, p.id DESC', $limit);
    }

    /**
     * Produk lain dalam kategori yang sama (untuk rekomendasi di halaman detail).
     */
    public static function related(int $categoryId, int $excludeId, int $limit = 4): array
    {
        return self::fetch(
            '',
            'p.is_active = 1 AND p.category_id = :cat AND p.id <> :id',
            ['cat' => $categoryId, 'id' => $excludeId],
            '',
            'p.created_at DESC, p.id DESC',
            $limit
        );
    }

    /**
     * Detail produk aktif beserta semua variasi dan gambar.
     * Mengembalikan null jika produk tidak ada atau tidak aktif.
     */
    public static function findBySlug(string $slug): ?array
    {
        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare(
            'SELECT p.id, p.category_id, p.name, p.slug, p.description,
                    p.weight_gram, p.length_cm, p.width_cm, p.height_cm,
                    c.name AS category_name, c.slug AS category_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id AND c.is_active = 1
             WHERE p.slug = :slug AND p.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, sku, variant_name, price, discount_price, stock
             FROM product_variants
             WHERE product_id = :id
             ORDER BY id ASC'
        );
        $stmt->execute(['id' => $product['id']]);
        $variants = $stmt->fetchAll();

        if (empty($variants)) {
            return null; // produk tanpa variasi tidak bisa dijual
        }

        foreach ($variants as &$variant) {
            $variant['id'] = (int) $variant['id'];
            $variant['price'] = (int) $variant['price'];
            $variant['discount_price'] = $variant['discount_price'] !== null ? (int) $variant['discount_price'] : null;
            $variant['stock'] = (int) $variant['stock'];
        }
        unset($variant);

        $stmt = $pdo->prepare(
            'SELECT image_path
             FROM product_images
             WHERE product_id = :id
             ORDER BY is_primary DESC, id ASC'
        );
        $stmt->execute(['id' => $product['id']]);

        $product['id'] = (int) $product['id'];
        $product['category_id'] = (int) $product['category_id'];
        $product['weight_gram'] = (int) $product['weight_gram'];
        $product['variants'] = $variants;
        $product['images'] = array_column($stmt->fetchAll(), 'image_path');

        return $product;
    }

    /**
     * Pencarian katalog dengan filter, sorting, dan pagination.
     * Pencarian hanya pada nama produk (deskripsi berisi HTML dari rich editor).
     *
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public static function search(
        string $q,
        ?int $categoryId,
        string $sort,
        bool $availableOnly,
        int $page,
        int $perPage
    ): array {
        $where = ['p.is_active = 1'];
        $params = [];

        // Setiap kata harus cocok di nama (maks 5 kata).
        $terms = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach (array_slice($terms, 0, 5) as $i => $term) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
            $where[] = "p.name LIKE :t{$i}";
            $params["t{$i}"] = $like;
        }

        if ($categoryId !== null) {
            $where[] = 'p.category_id = :cat';
            $params['cat'] = $categoryId;
        }

        if ($availableOnly) {
            $where[] = 's.stock > 0';
        }

        $whereSql = implode(' AND ', $where);

        $pdo = Database::getInstance()->getConnection();
        $countStmt = $pdo->prepare('SELECT COUNT(*) ' . self::fromSql() . ' WHERE ' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = max(1, $perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $orderBy = match ($sort) {
            'termurah' => 'COALESCE(v.discount_price, v.price) ASC, p.id DESC',
            'termahal' => 'COALESCE(v.discount_price, v.price) DESC, p.id DESC',
            'populer' => 'sold DESC, p.id DESC',
            default => 'p.created_at DESC, p.id DESC',
        };
        $extraSelect = $sort === 'populer' ? self::soldSelect() : '';

        $items = self::fetch($extraSelect, $whereSql, $params, '', $orderBy, $perPage, ($page - 1) * $perPage);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    private static function soldSelect(): string
    {
        return ", (SELECT COALESCE(SUM(oi.quantity), 0)
                   FROM order_items oi
                   JOIN orders o ON o.id = oi.order_id
                   JOIN product_variants pv ON pv.id = oi.product_variant_id
                   WHERE pv.product_id = p.id AND o.payment_status = 'paid') AS sold";
    }

    /**
     * Bagian FROM bersama: produk + kategori aktif + total stok + varian dengan harga efektif termurah.
     */
    private static function fromSql(): string
    {
        return "FROM products p
                JOIN categories c ON c.id = p.category_id AND c.is_active = 1
                JOIN (SELECT product_id, SUM(stock) AS stock
                      FROM product_variants
                      GROUP BY product_id) s ON s.product_id = p.id
                JOIN product_variants v ON v.id = (
                    SELECT v2.id
                    FROM product_variants v2
                    WHERE v2.product_id = p.id
                    ORDER BY COALESCE(v2.discount_price, v2.price) ASC, v2.id ASC
                    LIMIT 1
                )";
    }

    /**
     * Query kartu produk. Kunci hasil: id, name, slug, category_name, image, price, sale_price, stock.
     */
    private static function fetch(
        string $extraSelect,
        string $where,
        array $params,
        string $having,
        string $orderBy,
        int $limit,
        int $offset = 0
    ): array {
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $sql = "SELECT p.id, p.name, p.slug, c.name AS category_name,
                       v.price AS price,
                       v.discount_price AS sale_price,
                       s.stock AS stock,
                       (SELECT pi.image_path
                        FROM product_images pi
                        WHERE pi.product_id = p.id
                        ORDER BY pi.is_primary DESC, pi.id ASC
                        LIMIT 1) AS image
                       {$extraSelect}
                " . self::fromSql() . "
                WHERE {$where}
                {$having}
                ORDER BY {$orderBy}
                LIMIT {$limit} OFFSET {$offset}";

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['price'] = (int) $row['price'];
            $row['sale_price'] = $row['sale_price'] !== null ? (int) $row['sale_price'] : null;
            $row['stock'] = (int) $row['stock'];
        }
        unset($row);

        return $rows;
    }
}
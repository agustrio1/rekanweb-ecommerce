<?php

namespace App\Models;

use App\Core\Database;

class Category
{
    private static ?array $cache = null;

    /**
     * Kategori aktif beserta jumlah produk aktif di dalamnya.
     * Di-cache per request karena dipakai layout dan halaman.
     */
    public static function active(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $pdo = Database::getInstance()->getConnection();
        $sql = "SELECT c.id, c.name, c.slug, c.is_featured, COUNT(p.id) AS product_count
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
                WHERE c.is_active = 1
                GROUP BY c.id, c.name, c.slug, c.is_featured
                ORDER BY c.name ASC";

        self::$cache = $pdo->query($sql)->fetchAll();

        return self::$cache;
    }

    /**
     * Kategori unggulan untuk homepage. Jika belum ada yang ditandai,
     * semua kategori aktif dikembalikan.
     */
    public static function featured(): array
    {
        $all = self::active();
        $featured = array_values(array_filter($all, fn(array $c): bool => (int) $c['is_featured'] === 1));

        return $featured ?: $all;
    }

    public static function findBySlug(string $slug): ?array
    {
        foreach (self::active() as $category) {
            if ($category['slug'] === $slug) {
                return $category;
            }
        }

        return null;
    }
}
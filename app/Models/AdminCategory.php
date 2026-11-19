<?php

namespace App\Models;

use App\Core\Database;
use PDOException;

class AdminCategory
{
    /**
     * Semua kategori (aktif dan nonaktif) beserta jumlah produknya.
     */
    public static function all(): array
    {
        $pdo = Database::getInstance()->getConnection();
        $rows = $pdo->query(
            'SELECT c.id, c.name, c.slug, c.description, c.is_active, c.is_featured,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM categories c
             ORDER BY c.name ASC'
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['is_active'] = (int) $row['is_active'];
            $row['is_featured'] = (int) $row['is_featured'];
            $row['product_count'] = (int) $row['product_count'];
        }
        unset($row);

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.id, c.name, c.slug, c.description, c.is_active, c.is_featured,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM categories c
             WHERE c.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['is_active'] = (int) $row['is_active'];
        $row['is_featured'] = (int) $row['is_featured'];
        $row['product_count'] = (int) $row['product_count'];

        return $row;
    }

    public static function nameExists(string $name, int $ignoreId = 0): bool
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE name = :name AND id <> :id');
        $stmt->execute(['name' => $name, 'id' => $ignoreId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO categories (name, slug, description, is_active, is_featured)
             VALUES (:name, :slug, :description, :active, :featured)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'slug' => self::uniqueSlug($data['name']),
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'active' => $data['is_active'],
            'featured' => $data['is_featured'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Slug sengaja tidak diubah agar URL yang sudah terindeks tetap sama.
     */
    public static function update(int $id, array $data): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'UPDATE categories
             SET name = :name, description = :description, is_active = :active, is_featured = :featured
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'active' => $data['is_active'],
            'featured' => $data['is_featured'],
            'id' => $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('UPDATE categories SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    public static function setFeatured(int $id, bool $featured): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('UPDATE categories SET is_featured = :featured WHERE id = :id');
        $stmt->execute(['featured' => $featured ? 1 : 0, 'id' => $id]);
    }

    /**
     * Hapus kategori. Mengembalikan false jika masih punya produk.
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::getInstance()->getConnection();

        try {
            $stmt = $pdo->prepare(
                'DELETE FROM categories
                 WHERE id = :id
                   AND NOT EXISTS (SELECT 1 FROM products p WHERE p.category_id = :id2)'
            );
            $stmt->execute(['id' => $id, 'id2' => $id]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false; // ditolak foreign key
        }
    }

    private static function uniqueSlug(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-');
        $base = $base !== '' ? mb_substr($base, 0, 90) : 'kategori';

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = :slug');

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
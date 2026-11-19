<?php

namespace App\Models;

use App\Core\Database;

class Page
{
    /**
     * Halaman aktif untuk tautan footer.
     */
    public static function activeLinks(): array
    {
        $pdo = Database::getInstance()->getConnection();

        return $pdo->query('SELECT slug, title FROM pages WHERE is_active = 1 ORDER BY id ASC')->fetchAll();
    }

    public static function findActive(string $slug): ?array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT slug, title, content FROM pages WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
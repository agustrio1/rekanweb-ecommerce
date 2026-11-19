<?php

namespace App\Models;

use App\Core\Database;

class AdminPage
{
    public static function all(): array
    {
        $pdo = Database::getInstance()->getConnection();
        $rows = $pdo->query(
            'SELECT slug, title, is_active, updated_at FROM pages ORDER BY id ASC'
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['is_active'] = (int) $row['is_active'];
        }
        unset($row);

        return $rows;
    }

    public static function find(string $slug): ?array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT id, slug, title, content, is_active FROM pages WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['is_active'] = (int) $row['is_active'];

        return $row;
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'UPDATE pages SET title = :title, content = :content, is_active = :active WHERE id = :id'
        );
        $stmt->execute([
            'title' => $data['title'],
            'content' => $data['content'] !== '' ? $data['content'] : null,
            'active' => $data['is_active'],
            'id' => $id,
        ]);
    }
}
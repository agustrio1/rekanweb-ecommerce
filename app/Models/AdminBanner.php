<?php

namespace App\Models;

use App\Core\Database;

class AdminBanner
{
    public static function all(): array
    {
        $pdo = Database::getInstance()->getConnection();
        $rows = $pdo->query(
            'SELECT id, title, subtitle, image_path, link_url, sort_order, is_active
             FROM banners
             ORDER BY sort_order ASC, id DESC'
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['sort_order'] = (int) $row['sort_order'];
            $row['is_active'] = (int) $row['is_active'];
        }
        unset($row);

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, title, subtitle, image_path, link_url, sort_order, is_active
             FROM banners WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['is_active'] = (int) $row['is_active'];

        return $row;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO banners (title, subtitle, image_path, link_url, sort_order, is_active)
             VALUES (:title, :subtitle, :image, :link, :sort, :active)'
        );
        $stmt->execute([
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] !== '' ? $data['subtitle'] : null,
            'image' => $data['image_path'],
            'link' => $data['link_url'] !== '' ? $data['link_url'] : null,
            'sort' => $data['sort_order'],
            'active' => $data['is_active'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'UPDATE banners
             SET title = :title, subtitle = :subtitle, image_path = :image,
                 link_url = :link, sort_order = :sort, is_active = :active
             WHERE id = :id'
        );
        $stmt->execute([
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] !== '' ? $data['subtitle'] : null,
            'image' => $data['image_path'],
            'link' => $data['link_url'] !== '' ? $data['link_url'] : null,
            'sort' => $data['sort_order'],
            'active' => $data['is_active'],
            'id' => $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('UPDATE banners SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('DELETE FROM banners WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
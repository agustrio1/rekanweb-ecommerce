<?php

namespace App\Models;

use App\Core\Database;

class Customer
{
    public static function emailExists(string $email): bool
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Buat akun pelanggan baru dan kembalikan ID-nya.
     * Role selalu "customer"; akun admin tidak pernah dibuat dari form publik.
     * Melempar PDOException (kode 1062) jika email sudah terdaftar.
     */
    public static function create(string $name, string $email, string $phone, string $password): int
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password, phone, role)
             VALUES (:name, :email, :password, :phone, 'customer')"
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'phone' => $phone,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT id, name, email, phone, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function updateProfile(int $id, string $name, string $phone): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('UPDATE users SET name = :name, phone = :phone WHERE id = :id');
        $stmt->execute(['name' => $name, 'phone' => $phone, 'id' => $id]);
    }

    public static function passwordHash(int $id): ?string
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();

        return $hash === false ? null : (string) $hash;
    }

    public static function updatePassword(int $id, string $password): void
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
        $stmt->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    /**
     * Pesanan milik satu pelanggan, terbaru dulu.
     */
    public static function orders(int $userId, int $limit = 20): array
    {
        $limit = max(1, $limit);
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT o.id, o.order_number, o.total_amount, o.order_status, o.payment_status, o.created_at,
                    (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
             FROM orders o
             WHERE o.user_id = :u
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['u' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Normalisasi nomor HP ke format 08xxxxxxxxxx. Mengembalikan null jika tidak valid.
     */
    public static function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', $raw);

        if (str_starts_with($digits, '+62')) {
            $digits = '0' . substr($digits, 3);
        } elseif (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        }

        return preg_match('/^08[0-9]{8,13}$/', $digits) ? $digits : null;
    }
}
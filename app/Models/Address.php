<?php

namespace App\Models;

use App\Core\Database;

class Address
{
    public const MAX_PER_USER = 10;

    public static function forUser(int $userId): array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM user_addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC'
        );
        $stmt->execute(['u' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Ambil satu alamat milik user (anti-IDOR: selalu dicocokkan dengan user_id).
     */
    public static function find(int $id, int $userId): ?array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM user_addresses WHERE id = :id AND user_id = :u LIMIT 1');
        $stmt->execute(['id' => $id, 'u' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function count(int $userId): int
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id = :u');
        $stmt->execute(['u' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Data berisi: recipient_name, phone, area_id, area_name, province, city,
     * district, postal_code, address_detail, is_default (bool).
     */
    public static function create(int $userId, array $d): int
    {
        $pdo = Database::getInstance()->getConnection();

        try {
            $pdo->beginTransaction();

            $isDefault = !empty($d['is_default']) || self::count($userId) === 0;
            if ($isDefault) {
                $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :u')
                    ->execute(['u' => $userId]);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO user_addresses
                    (user_id, recipient_name, phone, area_id, area_name, province, city, district, postal_code, address_detail, is_default)
                 VALUES
                    (:u, :name, :phone, :area_id, :area_name, :province, :city, :district, :postal, :detail, :def)'
            );
            $stmt->execute(self::bind($d) + ['u' => $userId, 'def' => $isDefault ? 1 : 0]);
            $id = (int) $pdo->lastInsertId();

            $pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function update(int $id, int $userId, array $d): void
    {
        $pdo = Database::getInstance()->getConnection();

        try {
            $pdo->beginTransaction();

            if (!empty($d['is_default'])) {
                $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :u')
                    ->execute(['u' => $userId]);
            }

            $sql = 'UPDATE user_addresses SET
                        recipient_name = :name, phone = :phone, area_id = :area_id, area_name = :area_name,
                        province = :province, city = :city, district = :district, postal_code = :postal,
                        address_detail = :detail'
                . (!empty($d['is_default']) ? ', is_default = 1' : '')
                . ' WHERE id = :id AND user_id = :u';

            $pdo->prepare($sql)->execute(self::bind($d) + ['id' => $id, 'u' => $userId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function makeDefault(int $id, int $userId): bool
    {
        if (self::find($id, $userId) === null) {
            return false;
        }

        $pdo = Database::getInstance()->getConnection();

        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :u')
                ->execute(['u' => $userId]);
            $pdo->prepare('UPDATE user_addresses SET is_default = 1 WHERE id = :id AND user_id = :u')
                ->execute(['id' => $id, 'u' => $userId]);
            $pdo->commit();

            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function delete(int $id, int $userId): bool
    {
        $address = self::find($id, $userId);
        if ($address === null) {
            return false;
        }

        $pdo = Database::getInstance()->getConnection();

        try {
            $pdo->beginTransaction();

            $pdo->prepare('DELETE FROM user_addresses WHERE id = :id AND user_id = :u')
                ->execute(['id' => $id, 'u' => $userId]);

            // Jika yang dihapus alamat utama, alamat terbaru yang tersisa jadi utama.
            if ((int) $address['is_default'] === 1) {
                $pdo->prepare('UPDATE user_addresses SET is_default = 1 WHERE user_id = :u ORDER BY id DESC LIMIT 1')
                    ->execute(['u' => $userId]);
            }

            $pdo->commit();

            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function bind(array $d): array
    {
        return [
            'name' => $d['recipient_name'],
            'phone' => $d['phone'],
            'area_id' => $d['area_id'],
            'area_name' => $d['area_name'],
            'province' => $d['province'],
            'city' => $d['city'],
            'district' => $d['district'],
            'postal' => $d['postal_code'],
            'detail' => $d['address_detail'],
        ];
    }
}
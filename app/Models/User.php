<?php

namespace App\Models;

use App\Core\Database;

class User
{
    public static function findByEmail($email)
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Perbarui hash jika algoritma atau cost bawaan PHP sudah berubah.
     */
    public static function rehashIfNeeded(int $id, string $password, string $hash): void
    {
        if (!password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            return;
        }

        $db = Database::getInstance()->getConnection();
        $db->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }
}
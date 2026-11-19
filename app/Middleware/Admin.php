<?php

namespace App\Middleware;

use App\Controllers\Auth\AuthController;
use App\Core\Database;

class Admin
{
    /**
     * Peran selalu dicek ulang ke database, bukan hanya dari session,
     * sehingga admin yang diturunkan atau dihapus langsung kehilangan akses.
     */
    public static function handle()
    {
        $id = (int) ($_SESSION['user']['id'] ?? 0);
        if ($id <= 0) {
            self::deny();
        }

        $stmt = Database::getInstance()->getConnection()->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $role = $stmt->fetchColumn();

        if ($role === false) {
            AuthController::signOut();
            redirect('/login');
        }

        if ($role !== 'admin') {
            self::deny();
        }

        $_SESSION['user']['role'] = 'admin';
    }

    private static function deny(): void
    {
        http_response_code(403);
        die('Akses Ditolak: Halaman ini hanya untuk Administrator.');
    }
}
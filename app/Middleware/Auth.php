<?php

namespace App\Middleware;

class Auth
{
    public static function handle()
    {
        if (!isset($_SESSION['user'])) {
            // Simpan tujuan hanya untuk halaman biasa (GET), bukan POST atau endpoint JSON.
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !str_starts_with($uri, '/api/')) {
                $_SESSION['redirect_after_login'] = $uri;
            }

            redirect('/login');
        }
    }
}
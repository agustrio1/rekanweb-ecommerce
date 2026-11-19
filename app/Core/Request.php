<?php

namespace App\Core;

class Request
{
    public static function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public static function uri()
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($path)) {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }

    public static function input($key, $default = null)
    {
        if (self::method() === 'POST') {
            return $_POST[$key] ?? $default;
        }
        return $_GET[$key] ?? $default;
    }

    public static function validateCsrf()
    {
        if (self::method() === 'POST') {
            $token = $_POST['_token'] ?? '';
            if (!is_string($token)) {
                $token = ''; // mencegah TypeError jika dikirim sebagai array
            }

            $sessionToken = $_SESSION['csrf_token'] ?? '';

            if (empty($sessionToken)) {
                throw new \Exception('CSRF token tidak ditemukan di session. Refresh halaman.');
            }

            if (!hash_equals($sessionToken, $token)) {
                throw new \Exception('CSRF token tidak valid.');
            }
        }
    }
}
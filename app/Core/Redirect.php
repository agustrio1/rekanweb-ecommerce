<?php

namespace App\Core;

class Redirect
{
    /**
     * Pastikan tujuan redirect hanya path internal yang aman.
     * Menolak URL eksternal (//host, http://...), backslash, karakter kontrol,
     * dan halaman auth agar tidak terjadi loop.
     */
    public static function safePath(?string $path, string $fallback = '/'): string
    {
        if ($path === null || $path === '' || $path[0] !== '/') {
            return $fallback;
        }

        if (str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return $fallback;
        }

        $onlyPath = parse_url($path, PHP_URL_PATH) ?: '/';

        foreach (['/login', '/register', '/logout'] as $blocked) {
            if ($onlyPath === $blocked || str_starts_with($onlyPath, $blocked . '/')) {
                return $fallback;
            }
        }

        return $path;
    }
}
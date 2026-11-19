<?php

// 1. Escape output untuk mencegah XSS
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

// 2. Generate atau ambil CSRF Token dari session
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// 3. Output hidden input untuk form CSRF
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

// 4. Redirect halaman
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

// 5. Ambil nilai input lama (untuk menjaga nilai form saat validasi gagal)
if (!function_exists('old')) {
    function old($key, $default = '') {
        return $_POST[$key] ?? $default;
    }
}

// 6. Cek apakah user sedang login
if (!function_exists('auth')) {
    function auth() {
        return $_SESSION['user'] ?? null;
    }
}

// 7. Cek apakah user adalah admin
if (!function_exists('is_admin')) {
    function is_admin() {
        return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
    }
}
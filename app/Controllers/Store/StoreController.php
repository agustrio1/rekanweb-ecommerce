<?php

namespace App\Controllers\Store;

use App\Core\Request;
use App\Core\View;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Setting;

abstract class StoreController
{
    /**
     * Render view di dalam layout storefront dan kembalikan HTML-nya.
     */
    protected function render(string $view, array $data = []): string
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $content = View::render('components.flash', ['flash' => $flash])
            . View::render($view, $data);

        $layoutData = array_merge(
            [
                'title' => '',
                'storeName' => Setting::get('store_name', 'RekanWeb Commerce'),
                'navCategories' => Category::active(),
                'cartCount' => Cart::count(),
            ],
            $data,
            ['content' => $content]
        );

        return View::render('layouts.store', $layoutData);
    }

    /**
     * Simpan pesan sekali tampil. Tipe: success, error, info.
     */
    protected function flash(string $type, string $message): void
    {
        if ($message !== '') {
            $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        }
    }

    /**
     * Redirect ke path internal lalu hentikan eksekusi.
     */
    protected function redirect(string $path): void
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            $path = '/';
        }

        header('Location: ' . $path);
        exit;
    }

    /**
     * Kembali ke halaman sebelumnya (hanya path di domain yang sama).
     */
    protected function back(string $fallback = '/'): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer === '') {
            return $fallback;
        }

        $parts = parse_url($referer);
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (!$parts || ($parts['host'] ?? '') === '' || strcasecmp($parts['host'], explode(':', $host)[0]) !== 0) {
            return $fallback;
        }

        $path = $parts['path'] ?? '/';
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return $fallback;
        }

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /**
     * Validasi CSRF untuk request POST. Mengisi flash error jika gagal.
     */
    protected function csrfValid(): bool
    {
        try {
            Request::validateCsrf();
            return true;
        } catch (\Exception $e) {
            $this->flash('error', 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
            return false;
        }
    }
}
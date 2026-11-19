<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\View;

abstract class AdminController
{
    /**
     * Wajib login dan role admin.
     */
    protected function guard(): void
    {
        if (empty($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }

        \App\Middleware\Admin::handle();
    }

    /**
     * Render view di dalam layout admin dan kembalikan HTML-nya.
     * Flash ditampilkan sebagai toast (bawah tengah).
     */
    protected function render(string $title, string $view, array $data = []): string
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $toastHtml = View::render('components.toast', [
            'toasts' => is_array($flash) ? [$flash] : [],
        ]);

        return View::render('layouts.admin', [
            'title' => $title,
            'content' => View::render($view, $data),
            'toastHtml' => $toastHtml,
        ]);
    }

    /**
     * Pesan sekali tampil. Tipe: success, error, info.
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
            $path = '/admin';
        }

        header('Location: ' . $path);
        exit;
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
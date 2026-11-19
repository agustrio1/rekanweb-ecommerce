<?php

namespace App\Controllers\Auth;

use App\Core\RateLimiter;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\View;
use App\Models\Setting;
use App\Models\User;

class AuthController
{
    private const LOGIN_WINDOW = 900;   // 15 menit
    private const LOGIN_MAX_ACCOUNT = 5; // per email + IP
    private const LOGIN_MAX_IP = 20;     // per IP

    /**
     * Hash valid hanya untuk menyamakan waktu respons saat email tidak ditemukan.
     */
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    /**
     * Mulai sesi login. Dipakai juga oleh registrasi.
     */
    public static function signIn(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }

    /**
     * Akhiri sesi sepenuhnya, termasuk cookie-nya.
     */
    public static function signOut(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?: 'Lax',
            ]);
        }

        session_destroy();
    }

    public function showLogin()
    {
        $this->rememberNext();

        if (isset($_SESSION['user'])) {
            redirect($this->destination($_SESSION['user']['role']));
        }

        echo $this->renderLogin();
    }

    public function login()
    {
        if (!$this->csrfOk()) {
            echo $this->renderLogin('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
            return;
        }

        $email = mb_strtolower(trim($this->field('email')));
        $password = $this->field('password');

        if ($email === '' || $password === '') {
            echo $this->renderLogin('Email dan password wajib diisi.', $email);
            return;
        }

        $ip = RateLimiter::ip();
        $byAccount = RateLimiter::key('login', $ip, $email);
        $byIp = RateLimiter::key('login-ip', $ip);

        $wait = max(
            RateLimiter::blocked($byAccount, self::LOGIN_MAX_ACCOUNT, self::LOGIN_WINDOW),
            RateLimiter::blocked($byIp, self::LOGIN_MAX_IP, self::LOGIN_WINDOW)
        );
        if ($wait > 0) {
            header('Retry-After: ' . $wait);
            echo $this->renderLogin(
                'Terlalu banyak percobaan masuk. Coba lagi dalam ' . max(1, (int) ceil($wait / 60)) . ' menit.',
                $email,
                429
            );
            return;
        }

        $user = strlen($password) <= 200 ? User::findByEmail($email) : null;

        // Selalu jalankan verifikasi supaya waktu respons tidak membocorkan email terdaftar.
        $valid = password_verify($password, $user['password'] ?? self::DUMMY_HASH) && $user;

        if (!$valid) {
            RateLimiter::hit($byAccount, self::LOGIN_WINDOW);
            RateLimiter::hit($byIp, self::LOGIN_WINDOW);
            echo $this->renderLogin('Email atau password salah.', $email);
            return;
        }

        RateLimiter::clear($byAccount);
        User::rehashIfNeeded((int) $user['id'], $password, $user['password']);

        self::signIn($user);

        redirect($this->destination($user['role']));
    }

    /**
     * Simpan tujuan setelah login dari ?next= (hanya jika aman).
     */
    protected function rememberNext(): void
    {
        $next = $_GET['next'] ?? null;

        if (is_string($next)) {
            $safe = Redirect::safePath($next, '');
            if ($safe !== '') {
                $_SESSION['redirect_after_login'] = $safe;
            }
        }
    }

    /**
     * Tujuan setelah login/daftar, sekali pakai.
     */
    protected function destination(string $role): string
    {
        $fallback = $role === 'admin' ? '/admin' : '/';
        $saved = $_SESSION['redirect_after_login'] ?? null;
        unset($_SESSION['redirect_after_login']);

        return Redirect::safePath(is_string($saved) ? $saved : null, $fallback);
    }

    /**
     * Query string "?next=..." untuk tautan antar halaman auth.
     */
    protected function nextQuery(): string
    {
        $saved = $_SESSION['redirect_after_login'] ?? null;
        $safe = Redirect::safePath(is_string($saved) ? $saved : null, '');

        return $safe !== '' ? '?next=' . rawurlencode($safe) : '';
    }

    protected function field(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    protected function csrfOk(): bool
    {
        try {
            Request::validateCsrf();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function renderPage(string $view, array $data, string $title): string
    {
        $content = View::render($view, $data);

        return View::render('layouts.auth', [
            'title' => $title,
            'storeName' => Setting::get('store_name', 'RekanWeb Commerce'),
            'content' => $content,
        ]);
    }

    private function renderLogin(string $error = '', string $email = '', int $status = 422): string
    {
        if ($error !== '') {
            http_response_code($status);
        }

        return $this->renderPage('auth.login', [
            'error' => $error,
            'email' => $email,
            'nextQuery' => $this->nextQuery(),
        ], 'Masuk');
    }
}
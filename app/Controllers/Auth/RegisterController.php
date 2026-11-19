<?php

namespace App\Controllers\Auth;

use App\Core\RateLimiter;
use App\Models\Customer;
use App\Models\User;

class RegisterController extends AuthController
{
    private const WINDOW = 3600; // 1 jam
    private const MAX_PER_IP = 10;

    public function show()
    {
        $this->rememberNext();

        if (isset($_SESSION['user'])) {
            redirect($this->destination($_SESSION['user']['role']));
        }

        echo $this->renderForm();
    }

    public function store()
    {
        if (!$this->csrfOk()) {
            echo $this->renderForm([], [], 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
            return;
        }

        $limitKey = RateLimiter::key('register-ip', RateLimiter::ip());
        $wait = RateLimiter::blocked($limitKey, self::MAX_PER_IP, self::WINDOW);
        if ($wait > 0) {
            header('Retry-After: ' . $wait);
            echo $this->renderForm([], [], 'Terlalu banyak percobaan pendaftaran. Coba lagi dalam ' . max(1, (int) ceil($wait / 60)) . ' menit.', 429);
            return;
        }
        RateLimiter::hit($limitKey, self::WINDOW);

        $name = trim(preg_replace('/\s+/u', ' ', $this->field('name')));
        $email = mb_strtolower(trim($this->field('email')));
        $phone = Customer::normalizePhone($this->field('phone'));
        $password = $this->field('password');
        $confirm = $this->field('password_confirmation');

        $old = ['name' => $name, 'email' => $email, 'phone' => $this->field('phone')];
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Nama harus 2 sampai 100 karakter.';
        }

        if ($email === '' || mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Masukkan alamat email yang valid.';
        }

        if ($phone === null) {
            $errors['phone'] = 'Nomor HP tidak valid. Contoh: 081234567890.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password minimal 8 karakter.';
        } elseif (strlen($password) > 72) {
            $errors['password'] = 'Password maksimal 72 karakter.';
        } elseif ($password !== $confirm) {
            $errors['password_confirmation'] = 'Konfirmasi password tidak sama.';
        }

        if (!isset($errors['email']) && Customer::emailExists($email)) {
            $errors['email'] = 'Email ini sudah terdaftar. Silakan masuk.';
        }

        if (!empty($errors)) {
            echo $this->renderForm($errors, $old);
            return;
        }

        try {
            Customer::create($name, $email, $phone, $password);
        } catch (\PDOException $e) {
            // 1062 = duplicate entry (dua pendaftaran email sama bersamaan)
            if (($e->errorInfo[1] ?? 0) === 1062) {
                echo $this->renderForm(['email' => 'Email ini sudah terdaftar. Silakan masuk.'], $old);
                return;
            }

            error_log('Register error: ' . $e->getMessage());
            echo $this->renderForm([], $old, 'Terjadi kesalahan. Silakan coba lagi.');
            return;
        }

        $user = User::findByEmail($email);

        if (!$user) {
            redirect('/login');
        }

        self::signIn($user);

        redirect($this->destination($user['role']));
    }

    private function renderForm(array $errors = [], array $old = [], string $error = '', int $status = 422): string
    {
        if (!empty($errors) || $error !== '') {
            http_response_code($status);
        }

        return $this->renderPage('auth.register', [
            'errors' => $errors,
            'old' => $old,
            'error' => $error,
            'nextQuery' => $this->nextQuery(),
        ], 'Daftar');
    }
}
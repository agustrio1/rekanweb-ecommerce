<?php

namespace App\Controllers\Store;

use App\Middleware\Auth;
use App\Models\Customer;

class AccountController extends StoreController
{
    public function __construct()
    {
        Auth::handle();
    }

    public function index()
    {
        echo $this->render('account.orders', [
            'title' => 'Pesanan saya',
            'active' => 'orders',
            'orders' => Customer::orders($this->userId(), 20),
        ]);
    }

    public function profile()
    {
        echo $this->renderProfile();
    }

    public function updateProfile()
    {
        if (!$this->csrfValid()) {
            $this->redirect('/akun/profil');
        }

        $name = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')));
        $rawPhone = (string) ($_POST['phone'] ?? '');
        $phone = Customer::normalizePhone($rawPhone);
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Nama harus 2 sampai 100 karakter.';
        }

        if ($phone === null) {
            $errors['phone'] = 'Nomor HP tidak valid. Contoh: 081234567890.';
        }

        if (!empty($errors)) {
            echo $this->renderProfile($errors, ['name' => $name, 'phone' => $rawPhone]);
            return;
        }

        Customer::updateProfile($this->userId(), $name, $phone);
        $_SESSION['user']['name'] = $name;

        $this->flash('success', 'Profil berhasil diperbarui.');
        $this->redirect('/akun/profil');
    }

    public function password()
    {
        echo $this->renderPassword();
    }

    public function updatePassword()
    {
        if (!$this->csrfValid()) {
            $this->redirect('/akun/password');
        }

        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        $errors = [];

        $hash = Customer::passwordHash($this->userId());

        if ($hash === null || !password_verify($current, $hash)) {
            $errors['current_password'] = 'Password saat ini salah.';
        }

        if (strlen($new) < 8) {
            $errors['password'] = 'Password baru minimal 8 karakter.';
        } elseif (strlen($new) > 72) {
            $errors['password'] = 'Password baru maksimal 72 karakter.';
        } elseif ($new === $current) {
            $errors['password'] = 'Password baru harus berbeda dari yang lama.';
        } elseif ($new !== $confirm) {
            $errors['password_confirmation'] = 'Konfirmasi password tidak sama.';
        }

        if (!empty($errors)) {
            echo $this->renderPassword($errors);
            return;
        }

        Customer::updatePassword($this->userId(), $new);
        session_regenerate_id(true);

        $this->flash('success', 'Password berhasil diubah.');
        $this->redirect('/akun/password');
    }

    private function userId(): int
    {
        return (int) $_SESSION['user']['id'];
    }

    private function renderProfile(array $errors = [], array $old = []): string
    {
        $user = Customer::find($this->userId());

        if ($user === null) {
            $_SESSION = [];
            redirect('/login');
        }

        if (!empty($errors)) {
            http_response_code(422);
        }

        return $this->render('account.profile', [
            'title' => 'Profil',
            'active' => 'profile',
            'user' => $user,
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    private function renderPassword(array $errors = []): string
    {
        if (!empty($errors)) {
            http_response_code(422);
        }

        return $this->render('account.password', [
            'title' => 'Ubah password',
            'active' => 'password',
            'errors' => $errors,
        ]);
    }
}
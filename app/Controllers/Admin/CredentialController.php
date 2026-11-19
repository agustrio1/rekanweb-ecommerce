<?php

namespace App\Controllers\Admin;

use App\Helpers\Secret;
use App\Models\ActivityLog;
use App\Models\Setting;

class CredentialController extends AdminController
{
    private const BACK = '/admin/settings/credentials';

    public function index()
    {
        $this->guard();

        echo $this->page([], []);
    }

    public function update()
    {
        $this->guard();

        if (!$this->csrfValid()) {
            $this->redirect(self::BACK);
            return;
        }

        $in = fn(string $key): string => isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';

        $biteship = $in('biteship_api_key');
        $merchant = $in('duitku_merchant_code');
        $duitkuKey = $in('duitku_api_key');
        $mode = $in('duitku_env');

        $errors = [];

        if ($mode !== 'sandbox' && $mode !== 'production') {
            $errors['duitku_env'] = 'Pilih sandbox atau production.';
        }
        if ($merchant !== '' && !preg_match('/^[A-Za-z0-9]{3,30}$/', $merchant)) {
            $errors['duitku_merchant_code'] = 'Merchant code hanya huruf dan angka (3-30 karakter).';
        }

        $secrets = ['biteship_api_key' => $biteship, 'duitku_api_key' => $duitkuKey];
        $encrypted = [];

        foreach ($secrets as $key => $plain) {
            if ($plain === '') {
                continue; // kosong = tidak diganti
            }
            if (!preg_match('/^\S{8,300}$/', $plain)) {
                $errors[$key] = 'Kunci tidak valid: 8-300 karakter tanpa spasi.';
                continue;
            }
            $cipher = Secret::encrypt($plain);
            if ($cipher === null) {
                $errors[$key] = 'APP_KEY belum diisi di .env, jadi kunci tidak bisa disimpan dengan aman.';
                continue;
            }
            $encrypted[$key] = $cipher;
        }

        if ($errors) {
            echo $this->page([
                'duitku_merchant_code' => $merchant,
                'duitku_env' => $mode,
            ], $errors);
            return;
        }

        $changed = array_keys($encrypted);
        $save = $encrypted;

        $current = Setting::many(['duitku_merchant_code', 'duitku_env']);
        if ($merchant !== $current['duitku_merchant_code']) {
            $save['duitku_merchant_code'] = $merchant;
            $changed[] = 'duitku_merchant_code';
        }
        if ($mode !== $current['duitku_env']) {
            $save['duitku_env'] = $mode;
            $changed[] = 'duitku_env';
        }

        if (!$save) {
            $this->flash('info', 'Tidak ada perubahan.');
            $this->redirect(self::BACK);
            return;
        }

        Setting::setMany($save);

        ActivityLog::record(
            (int) ($_SESSION['user']['id'] ?? 0),
            'update',
            'credentials',
            null,
            'Mengubah kredensial: ' . implode(', ', $changed)
        );

        $this->flash('success', 'Kredensial disimpan.');
        $this->redirect(self::BACK);
    }

    /**
     * @param array<string, string> $override Nilai form yang ditampilkan ulang saat error.
     */
    private function page(array $override, array $errors): string
    {
        $stored = Setting::many(Setting::CREDENTIAL_KEYS);

        $status = function (string $key, string $envName) use ($stored): array {
            $raw = $stored[$key];
            if ($raw !== '') {
                $plain = str_starts_with($raw, Secret::PREFIX) ? Secret::decrypt($raw) : $raw;
                if ($plain !== null && $plain !== '') {
                    return ['db', Secret::mask($plain)];
                }
            }

            $env = $_ENV[$envName] ?? getenv($envName);
            if (is_string($env) && $env !== '' && !str_starts_with($env, 'your_')) {
                return ['env', ''];
            }

            return ['none', ''];
        };

        $envMode = $_ENV['DUITKU_ENV'] ?? getenv('DUITKU_ENV');
        $envMerchant = $_ENV['DUITKU_MERCHANT_CODE'] ?? getenv('DUITKU_MERCHANT_CODE');
        $envMerchant = is_string($envMerchant) && !str_starts_with($envMerchant, 'your_') ? $envMerchant : '';

        $values = [
            'duitku_merchant_code' => $override['duitku_merchant_code']
                ?? ($stored['duitku_merchant_code'] !== '' ? $stored['duitku_merchant_code'] : $envMerchant),
            'duitku_env' => $override['duitku_env']
                ?? ($stored['duitku_env'] !== '' ? $stored['duitku_env'] : (($envMode === 'production') ? 'production' : 'sandbox')),
        ];

        return $this->render('Kredensial API', 'admin.settings.credentials', [
            'values' => $values,
            'errors' => $errors,
            'biteship' => $status('biteship_api_key', 'BITESHIP_API_KEY'),
            'duitkuKey' => $status('duitku_api_key', 'DUITKU_API_KEY'),
            'canEncrypt' => Secret::available(),
        ]);
    }
}
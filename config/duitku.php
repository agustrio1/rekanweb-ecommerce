<?php

return (static function (): array {
    $env = static function (string $key, $default = null) {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        $value = getenv($key);
        return $value !== false ? $value : $default;
    };

    $setting = static fn(string $key, string $fallback): string => \App\Models\Setting::credential($key, $fallback);

    $mode = $setting('duitku_env', (string) $env('DUITKU_ENV', 'sandbox')) === 'production' ? 'production' : 'sandbox';
    $appUrl = rtrim((string) $env('APP_URL', ''), '/');

    return [
        'merchant_code'    => $setting('duitku_merchant_code', (string) $env('DUITKU_MERCHANT_CODE', '')),
        'api_key'          => $setting('duitku_api_key', (string) $env('DUITKU_API_KEY', '')),
        'env'              => $mode,
        // Duitku POP (create invoice)
        'base_url'         => $mode === 'production'
            ? 'https://api-prod.duitku.com'
            : 'https://api-sandbox.duitku.com',
        // API klasik (daftar metode pembayaran, buat transaksi)
        'classic_base_url' => $mode === 'production'
            ? 'https://passport.duitku.com'
            : 'https://sandbox.duitku.com',
        'callback_url'     => $appUrl . '/webhook/duitku',
        'return_url'       => $appUrl . '/pembayaran/selesai',
        'expiry_minutes'   => 60,
    ];
})();
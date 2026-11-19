<?php

namespace App\Helpers;

/**
 * Enkripsi sederhana untuk rahasia yang disimpan di database.
 * Kunci diturunkan dari APP_KEY di .env.
 */
class Secret
{
    public const PREFIX = 'enc:v1:';

    public static function available(): bool
    {
        return self::key() !== null && function_exists('openssl_encrypt');
    }

    public static function encrypt(string $plain): ?string
    {
        $key = self::key();
        if ($key === null || !function_exists('openssl_encrypt')) {
            return null;
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || strlen($tag) !== 16) {
            return null;
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Mengembalikan null jika bukan format terenkripsi, APP_KEY salah, atau data rusak.
     */
    public static function decrypt(string $stored): ?string
    {
        $key = self::key();
        if ($key === null || !str_starts_with($stored, self::PREFIX) || !function_exists('openssl_decrypt')) {
            return null;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }

        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return $plain === false ? null : $plain;
    }

    /**
     * Samarkan nilai: hanya 4 karakter terakhir yang terlihat.
     */
    public static function mask(string $plain): string
    {
        return strlen($plain) >= 8 ? '••••' . substr($plain, -4) : '••••••••';
    }

    private static function key(): ?string
    {
        $value = $_ENV['APP_KEY'] ?? getenv('APP_KEY');

        return is_string($value) && $value !== '' ? hash('sha256', $value, true) : null;
    }
}
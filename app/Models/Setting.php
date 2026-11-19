<?php

namespace App\Models;

use App\Core\Database;
use App\Helpers\Secret;

class Setting
{
    public const CREDENTIAL_KEYS = [
        'biteship_api_key', 'duitku_merchant_code', 'duitku_api_key', 'duitku_env',
    ];

    /** Seluruh isi store_settings, dimuat sekali per request. */
    private static ?array $all = null;

    private static function all(): array
    {
        if (self::$all === null) {
            $pdo = Database::getInstance()->getConnection();
            $rows = $pdo->query('SELECT setting_key, setting_value FROM store_settings')->fetchAll();

            $loaded = [];
            foreach ($rows as $row) {
                $loaded[$row['setting_key']] = (string) $row['setting_value'];
            }
            self::$all = $loaded;
        }

        return self::$all;
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Ambil beberapa pengaturan sekaligus. Kunci yang belum ada diisi ''.
     *
     * @param string[] $keys
     * @return array<string, string>
     */
    public static function many(array $keys): array
    {
        $all = self::all();
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $all[$key] ?? '';
        }

        return $out;
    }

    /**
     * Kredensial API: nilai dari pengaturan admin (didekripsi jika perlu),
     * atau $fallback (nilai .env) jika kosong, rusak, atau database belum siap.
     */
    public static function credential(string $key, string $fallback = ''): string
    {
        try {
            $stored = self::all()[$key] ?? '';
        } catch (\Throwable $e) {
            return $fallback;
        }

        if ($stored === '') {
            return $fallback;
        }

        $plain = str_starts_with($stored, Secret::PREFIX) ? Secret::decrypt($stored) : $stored;

        return $plain !== null && $plain !== '' ? $plain : $fallback;
    }

    /**
     * Simpan banyak pengaturan dalam satu transaksi.
     *
     * @param array<string, string> $values
     */
    public static function setMany(array $values): void
    {
        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO store_settings (setting_key, setting_value) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach ($values as $key => $value) {
                $stmt->execute(['k' => $key, 'v' => $value]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        self::$all = null;
    }
}
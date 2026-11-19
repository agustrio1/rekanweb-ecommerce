<?php

namespace App\Services\Biteship;

class AreaService
{
    private const CACHE_TTL = 86400; // 24 jam
    private const MAX_RESULTS = 10;

    /**
     * Cari wilayah (kecamatan/kelurahan + kode pos) lewat Biteship Maps API.
     *
     * @return array{ok: bool, areas: array, message: string}
     */
    public static function search(string $query): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query));

        if (mb_strlen($query) < 3) {
            return ['ok' => true, 'areas' => [], 'message' => ''];
        }

        $cacheFile = self::cacheFile($query);
        $cached = self::readCache($cacheFile);
        if ($cached !== null) {
            return ['ok' => true, 'areas' => $cached, 'message' => ''];
        }

        $client = BiteshipClient::make();
        if (!$client->isConfigured()) {
            return ['ok' => false, 'areas' => [], 'message' => 'Layanan wilayah belum dikonfigurasi.'];
        }

        $response = $client->get('/v1/maps/areas', [
            'countries' => 'ID',
            'input' => $query,
            'type' => 'single',
        ]);

        if (!$response['ok']) {
            return ['ok' => false, 'areas' => [], 'message' => 'Pencarian wilayah sedang tidak tersedia. Coba lagi sebentar.'];
        }

        $areas = [];
        foreach (($response['data']['areas'] ?? []) as $row) {
            if (!is_array($row) || empty($row['id']) || empty($row['name'])) {
                continue;
            }

            $areas[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'province' => (string) ($row['administrative_division_level_1_name'] ?? ''),
                'city' => (string) ($row['administrative_division_level_2_name'] ?? ''),
                'district' => (string) ($row['administrative_division_level_3_name'] ?? ''),
                'postal_code' => (string) ($row['postal_code'] ?? ''),
            ];

            if (count($areas) >= self::MAX_RESULTS) {
                break;
            }
        }

        self::writeCache($cacheFile, $areas);

        return ['ok' => true, 'areas' => $areas, 'message' => ''];
    }

    private static function cacheDir(): string
    {
        return dirname(__DIR__, 3) . '/storage/cache/areas';
    }

    private static function cacheFile(string $query): string
    {
        return self::cacheDir() . '/' . sha1(mb_strtolower($query)) . '.json';
    }

    private static function readCache(string $file): ?array
    {
        if (!is_file($file) || (time() - (int) filemtime($file)) > self::CACHE_TTL) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    private static function writeCache(string $file, array $areas): void
    {
        $dir = self::cacheDir();

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return; // cache gagal tidak boleh menggagalkan pencarian
        }

        @file_put_contents($file, json_encode($areas, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
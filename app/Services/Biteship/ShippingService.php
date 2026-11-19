<?php

namespace App\Services\Biteship;

use App\Core\Database;
use App\Models\Setting;

class ShippingService
{
    private const CACHE_TTL = 300; // 5 menit

    /**
     * Hitung tarif pengiriman untuk isi keranjang ke satu alamat.
     * Dipakai juga di 6C dengan $useCache = false untuk validasi ulang saat order dibuat.
     *
     * @param array $lines   baris keranjang dari Cart::details()['lines']
     * @param array $address baris dari user_addresses
     * @return array{ok: bool, options: array, weight: int, message: string}
     */
    public static function quote(array $lines, array $address, bool $useCache = true): array
    {
        $origin = Setting::get('origin_area_id');
        if ($origin === '') {
            return self::fail('Alamat asal pengiriman toko belum diatur.');
        }

        $destination = (string) ($address['area_id'] ?? '');
        if ($destination === '') {
            return self::fail('Alamat ini belum punya data wilayah. Ubah alamat lalu pilih kecamatannya.');
        }

        $pack = self::items($lines);
        if (!$pack['ok']) {
            return self::fail($pack['message']);
        }

        $config = require __DIR__ . '/../../../config/biteship.php';
        $payload = [
            'origin_area_id' => $origin,
            'destination_area_id' => $destination,
            'couriers' => $config['couriers'],
            'items' => $pack['items'],
        ];

        $cacheFile = self::cacheDir() . '/' . sha1(json_encode($payload)) . '.json';

        if ($useCache) {
            $cached = self::readCache($cacheFile);
            if ($cached !== null) {
                return ['ok' => true, 'options' => $cached, 'weight' => $pack['weight'], 'message' => ''];
            }
        }

        $client = BiteshipClient::make();
        if (!$client->isConfigured()) {
            return self::fail('Layanan ongkir belum dikonfigurasi.');
        }

        $response = $client->post('/v1/rates/couriers', $payload);
        if (!$response['ok']) {
            return self::fail('Tarif pengiriman sedang tidak tersedia. Coba lagi sebentar.');
        }

        $options = self::parse($response['data']['pricing'] ?? []);
        if (empty($options)) {
            return self::fail('Belum ada layanan kurir yang tersedia untuk alamat ini.');
        }

        self::writeCache($cacheFile, $options);

        return ['ok' => true, 'options' => $options, 'weight' => $pack['weight'], 'message' => ''];
    }

    /**
     * Susun item untuk Biteship dari berat/dimensi produk di database.
     *
     * @return array{ok: bool, items: array, weight: int, message: string}
     */
    private static function items(array $lines): array
    {
        $lines = array_values(array_filter($lines, fn($l) => !empty($l['available']) && (int) $l['qty'] > 0));
        if (empty($lines)) {
            return ['ok' => false, 'items' => [], 'weight' => 0, 'message' => 'Keranjang kosong.'];
        }

        $ids = array_map(fn($l) => (int) $l['variant_id'], $lines);
        $marks = implode(',', array_fill(0, count($ids), '?'));

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT v.id, p.weight_gram, p.length_cm, p.width_cm, p.height_cm
             FROM product_variants v
             JOIN products p ON p.id = v.product_id
             WHERE v.id IN ({$marks})"
        );
        $stmt->execute($ids);

        $specs = [];
        foreach ($stmt->fetchAll() as $row) {
            $specs[(int) $row['id']] = $row;
        }

        $items = [];
        $totalWeight = 0;

        foreach ($lines as $line) {
            $spec = $specs[(int) $line['variant_id']] ?? null;
            $weight = $spec ? (int) $spec['weight_gram'] : 0;

            if ($weight <= 0) {
                return [
                    'ok' => false,
                    'items' => [],
                    'weight' => 0,
                    'message' => 'Berat produk "' . $line['name'] . '" belum diatur, ongkir belum bisa dihitung. Hubungi toko.',
                ];
            }

            $item = [
                'name' => (string) $line['name'],
                'description' => (string) $line['variant_name'],
                'value' => (int) $line['unit_price'],
                'weight' => $weight,
                'quantity' => (int) $line['qty'],
            ];

            $l = (int) $spec['length_cm'];
            $w = (int) $spec['width_cm'];
            $h = (int) $spec['height_cm'];
            if ($l > 0 && $w > 0 && $h > 0) {
                $item['length'] = $l;
                $item['width'] = $w;
                $item['height'] = $h;
            }

            $items[] = $item;
            $totalWeight += $weight * (int) $line['qty'];
        }

        return ['ok' => true, 'items' => $items, 'weight' => $totalWeight, 'message' => ''];
    }

    /**
     * Ubah respons Biteship menjadi opsi sederhana, diurutkan dari termurah.
     */
    private static function parse($pricing): array
    {
        if (!is_array($pricing)) {
            return [];
        }

        $options = [];

        foreach ($pricing as $row) {
            if (!is_array($row)) {
                continue;
            }

            $courierCode = strtolower((string) ($row['courier_code'] ?? ''));
            $serviceCode = strtolower((string) ($row['courier_service_code'] ?? ''));
            $price = (int) round((float) ($row['price'] ?? 0));

            if ($courierCode === '' || $serviceCode === '' || $price <= 0) {
                continue;
            }

            $options[] = [
                'key' => $courierCode . '|' . $serviceCode,
                'courier_code' => $courierCode,
                'courier_name' => (string) ($row['courier_name'] ?? strtoupper($courierCode)),
                'service_code' => $serviceCode,
                'service_name' => (string) ($row['courier_service_name'] ?? $serviceCode),
                'description' => (string) ($row['description'] ?? ''),
                'estimate' => self::estimate($row),
                'price' => $price,
            ];
        }

        usort($options, fn($a, $b) => $a['price'] <=> $b['price']);

        return $options;
    }

    private static function estimate(array $row): string
    {
        $range = trim((string) ($row['shipment_duration_range'] ?? ''));
        $unit = strtolower(trim((string) ($row['shipment_duration_unit'] ?? '')));

        if ($range !== '') {
            $label = match ($unit) {
                'days', 'day' => 'hari',
                'hours', 'hour' => 'jam',
                default => $unit,
            };

            return trim($range . ' ' . $label);
        }

        $duration = trim((string) ($row['duration'] ?? ''));

        return $duration !== '' ? str_ireplace(['days', 'day', 'hours', 'hour'], ['hari', 'hari', 'jam', 'jam'], $duration) : '';
    }

    private static function fail(string $message): array
    {
        return ['ok' => false, 'options' => [], 'weight' => 0, 'message' => $message];
    }

    private static function cacheDir(): string
    {
        return dirname(__DIR__, 3) . '/storage/cache/rates';
    }

    private static function readCache(string $file): ?array
    {
        if (!is_file($file) || (time() - (int) filemtime($file)) > self::CACHE_TTL) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) && !empty($data) ? $data : null;
    }

    private static function writeCache(string $file, array $options): void
    {
        $dir = self::cacheDir();

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        @file_put_contents($file, json_encode($options, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
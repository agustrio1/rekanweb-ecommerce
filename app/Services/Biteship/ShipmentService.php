<?php

namespace App\Services\Biteship;

use App\Core\Database;
use App\Models\Setting;
use PDO;
use Throwable;

class ShipmentService
{
    /**
     * Buat order pengiriman di Biteship untuk pesanan yang sudah lunas.
     *
     * Return: ['ok' => bool, 'message' => string, 'warning' => ?string]
     */
    public static function create(string $orderNumber, int $adminId): array
    {
        $pdo = Database::getInstance()->getConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $client = BiteshipClient::make();
        if (!$client->isConfigured()) {
            return self::fail('API key Biteship belum diatur.');
        }

        // Tahap 1: kunci, validasi, tandai "creating", commit.
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ? FOR UPDATE');
            $stmt->execute([$orderNumber]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                return self::abort($pdo, 'Pesanan tidak ditemukan.');
            }
            if ($order['payment_status'] !== 'paid') {
                return self::abort($pdo, 'Pesanan belum dibayar, pengiriman belum bisa dibuat.');
            }
            if ($order['order_status'] === 'cancelled') {
                return self::abort($pdo, 'Pesanan sudah dibatalkan.');
            }
            if ($order['order_status'] !== 'pending') {
                return self::abort($pdo, 'Pesanan ini sudah diproses sebelumnya.');
            }

            $stmt = $pdo->prepare('SELECT * FROM shipments WHERE order_id = ? FOR UPDATE');
            $stmt->execute([$order['id']]);
            $shipment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$shipment) {
                return self::abort($pdo, 'Data kurir pesanan tidak ditemukan.');
            }
            if (!empty($shipment['biteship_order_id'])) {
                return self::abort($pdo, 'Pengiriman untuk pesanan ini sudah dibuat.');
            }
            if ($shipment['status'] === 'creating') {
                return self::abort($pdo, 'Pembuatan pengiriman sedang berjalan atau terhenti di tengah. Cek dulu dashboard Biteship, lalu gunakan tombol "Setel ulang" kalau order di sana memang belum terbentuk.');
            }
            if ($shipment['status'] !== 'pending') {
                return self::abort($pdo, 'Status pengiriman tidak memungkinkan pembuatan order baru.');
            }

            $built = self::buildPayload($pdo, $order, $shipment);
            if (!$built['ok']) {
                return self::abort($pdo, $built['message']);
            }
            $payload = $built['payload'];

            $pdo->prepare("UPDATE shipments SET status = 'creating' WHERE id = ?")->execute([$shipment['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Shipment] Persiapan gagal ' . $orderNumber . ': ' . $e->getMessage());

            return self::fail('Terjadi kesalahan sistem saat menyiapkan pengiriman.');
        }

        // Tahap 2: panggil Biteship (di luar transaksi).
        try {
            $response = $client->post('/v1/orders', $payload);
        } catch (Throwable $e) {
            $response = ['ok' => false, 'exception' => $e->getMessage()];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        // Tahap 3a: gagal.
        if (empty($response['ok']) || empty($data['id'])) {
            error_log('[Shipment] Biteship gagal ' . $orderNumber . ': ' . mb_substr((string) json_encode($response), 0, 1000));

            $detail = self::extractError($response);
            $http = (int) ($response['status'] ?? $response['http_code'] ?? 0);

            // Penolakan jelas (4xx): aman diulang. Selain itu, kemungkinan order terbentuk: kunci.
            if ($http >= 400 && $http < 500) {
                $pdo->prepare("UPDATE shipments SET status = 'pending' WHERE id = ? AND biteship_order_id IS NULL")
                    ->execute([$shipment['id']]);

                return self::fail('Biteship menolak permintaan' . ($detail !== '' ? ': ' . $detail : '.'));
            }

            return self::fail('Hasil dari Biteship tidak jelas' . ($detail !== '' ? ' (' . $detail . ')' : '')
                . '. Status pengiriman dikunci supaya tidak terbentuk ganda. Cek dashboard Biteship, lalu pakai "Setel ulang" kalau order belum ada di sana.');
        }

        // Tahap 3b: berhasil, simpan.
        $courier = is_array($data['courier'] ?? null) ? $data['courier'] : [];
        $biteshipId = (string) $data['id'];
        $waybill = (string) ($courier['waybill_id'] ?? '');
        $trackingId = (string) ($courier['tracking_id'] ?? '');
        $biteshipPrice = (int) round((float) ($data['price'] ?? 0));
        $billed = (int) $shipment['shipping_price'];
        $status = (string) ($data['status'] ?? 'confirmed');

        try {
            $pdo->beginTransaction();

            $pdo->prepare(
                "UPDATE shipments
                 SET biteship_order_id = ?, waybill_id = NULLIF(?, ''), status = ?, tracking_data = ?
                 WHERE id = ?"
            )->execute([
                $biteshipId,
                $waybill,
                mb_substr($status, 0, 50),
                json_encode([
                    'tracking_id' => $trackingId,
                    'biteship_price' => $biteshipPrice,
                    'response' => $data,
                ], JSON_UNESCAPED_UNICODE),
                $shipment['id'],
            ]);

            $pdo->prepare("UPDATE orders SET order_status = 'processing' WHERE id = ? AND order_status = 'pending'")
                ->execute([$order['id']]);

            $note = "Pengiriman Biteship dibuat oleh admin #{$adminId} (kurir "
                . strtoupper((string) $shipment['courier_company']) . '/' . $shipment['courier_type']
                . ($waybill !== '' ? ", resi {$waybill}" : '') . ')';

            $warning = null;
            if ($biteshipPrice > 0 && $biteshipPrice !== $billed) {
                $note .= ". Ongkir Biteship Rp {$biteshipPrice} berbeda dari yang ditagih ke pelanggan Rp {$billed}.";
                $warning = 'Perhatian: ongkir Biteship Rp ' . number_format($biteshipPrice, 0, ',', '.')
                    . ' berbeda dari yang ditagih ke pelanggan Rp ' . number_format($billed, 0, ',', '.')
                    . '. Tagihan pelanggan tidak diubah.';
            }

            $pdo->prepare(
                "INSERT INTO order_status_histories (order_id, old_status, new_status, notes)
                 VALUES (?, 'pending', 'processing', ?)"
            )->execute([$order['id'], $note]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("[Shipment][PERLU-DICEK] Order Biteship {$biteshipId} terbentuk untuk {$orderNumber}, tapi gagal disimpan: " . $e->getMessage());

            return self::fail("Pengiriman sudah terbentuk di Biteship (ID {$biteshipId}) tetapi gagal disimpan di sistem. Catat ID ini dan hubungi developer. Tombol tetap terkunci supaya tidak ganda.");
        }

        return [
            'ok' => true,
            'message' => 'Pengiriman berhasil dibuat di Biteship.'
                . ($waybill !== '' ? " Nomor resi: {$waybill}." : ' Nomor resi muncul setelah kurir mengonfirmasi.'),
            'warning' => $warning,
        ];
    }

    /**
     * Buka kunci "creating" setelah admin memastikan order belum ada di Biteship.
     */
    public static function reset(string $orderNumber, int $adminId): array
    {
        $pdo = Database::getInstance()->getConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id FROM orders WHERE order_number = ? FOR UPDATE');
            $stmt->execute([$orderNumber]);
            $orderId = $stmt->fetchColumn();

            if ($orderId === false) {
                return self::abort($pdo, 'Pesanan tidak ditemukan.');
            }

            $stmt = $pdo->prepare(
                "UPDATE shipments SET status = 'pending'
                 WHERE order_id = ? AND status = 'creating' AND biteship_order_id IS NULL"
            );
            $stmt->execute([$orderId]);

            if ($stmt->rowCount() !== 1) {
                return self::abort($pdo, 'Tidak ada status yang perlu disetel ulang.');
            }

            $pdo->prepare(
                "INSERT INTO order_status_histories (order_id, old_status, new_status, notes)
                 VALUES (?, 'pending', 'pending', ?)"
            )->execute([$orderId, "Admin #{$adminId} menyetel ulang status pembuatan pengiriman setelah memeriksa dashboard Biteship"]);

            $pdo->commit();

            return ['ok' => true, 'message' => 'Status pengiriman disetel ulang. Kamu bisa membuat pengiriman lagi.', 'warning' => null];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Shipment] Reset gagal ' . $orderNumber . ': ' . $e->getMessage());

            return self::fail('Terjadi kesalahan sistem saat menyetel ulang.');
        }
    }

    /**
     * Susun body POST /v1/orders dari data pesanan (snapshot) dan pengaturan toko.
     *
     * @return array{ok: bool, payload: array, message: string}
     */
    private static function buildPayload(PDO $pdo, array $order, array $shipment): array
    {
        $stmt = $pdo->prepare('SELECT * FROM order_addresses WHERE order_id = ? LIMIT 1');
        $stmt->execute([$order['id']]);
        $address = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$address || (string) $address['area_id'] === '') {
            return ['ok' => false, 'payload' => [], 'message' => 'Alamat tujuan pesanan belum punya data wilayah (area ID).'];
        }

        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$order['user_id']]);
        $email = (string) $stmt->fetchColumn();

        $origin = [
            'name' => (string) Setting::get('origin_name'),
            'phone' => (string) Setting::get('origin_phone'),
            'address' => (string) Setting::get('origin_address'),
            'postal' => (string) Setting::get('origin_postal_code'),
            'area' => (string) Setting::get('origin_area_id'),
        ];

        if ($origin['name'] === '' || $origin['phone'] === '' || $origin['address'] === '' || $origin['area'] === '') {
            return ['ok' => false, 'payload' => [], 'message' => 'Alamat asal toko belum lengkap di pengaturan (nama, telepon, alamat, dan area ID).'];
        }

        if (empty($shipment['courier_company']) || empty($shipment['courier_type'])) {
            return ['ok' => false, 'payload' => [], 'message' => 'Kurir atau layanan pesanan belum tersimpan.'];
        }

        $stmt = $pdo->prepare(
            "SELECT oi.product_name_snapshot, oi.variant_name_snapshot, oi.price_snapshot, oi.quantity,
                    oi.weight_gram_snapshot, p.length_cm, p.width_cm, p.height_cm
             FROM order_items oi
             LEFT JOIN product_variants v ON v.id = oi.product_variant_id
             LEFT JOIN products p ON p.id = v.product_id
             WHERE oi.order_id = ?"
        );
        $stmt->execute([$order['id']]);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $weight = (int) $row['weight_gram_snapshot'];
            if ($weight <= 0) {
                return ['ok' => false, 'payload' => [], 'message' => 'Berat produk "' . $row['product_name_snapshot'] . '" tidak valid.'];
            }

            $item = [
                'name' => mb_substr((string) $row['product_name_snapshot'], 0, 100),
                'description' => (string) $row['variant_name_snapshot'],
                'value' => (int) $row['price_snapshot'],
                'quantity' => (int) $row['quantity'],
                'weight' => $weight,
            ];

            $l = (int) $row['length_cm'];
            $w = (int) $row['width_cm'];
            $h = (int) $row['height_cm'];
            if ($l > 0 && $w > 0 && $h > 0) {
                $item['length'] = $l;
                $item['width'] = $w;
                $item['height'] = $h;
            }

            $items[] = $item;
        }

        if (empty($items)) {
            return ['ok' => false, 'payload' => [], 'message' => 'Pesanan tidak memiliki item.'];
        }

        $payload = [
            'origin_contact_name' => $origin['name'],
            'origin_contact_phone' => $origin['phone'],
            'origin_address' => $origin['address'],
            'origin_postal_code' => ctype_digit($origin['postal']) ? (int) $origin['postal'] : null,
            'origin_area_id' => $origin['area'],
            'destination_contact_name' => (string) $address['recipient_name'],
            'destination_contact_phone' => (string) $address['phone'],
            'destination_contact_email' => $email !== '' ? $email : null,
            'destination_address' => (string) $address['address_detail'],
            'destination_postal_code' => ctype_digit((string) $address['postal_code']) ? (int) $address['postal_code'] : null,
            'destination_area_id' => (string) $address['area_id'],
            'destination_note' => !empty($address['notes']) ? (string) $address['notes'] : null,
            'courier_company' => (string) $shipment['courier_company'],
            'courier_type' => (string) $shipment['courier_type'],
            'delivery_type' => 'now',
            'order_note' => 'Pesanan ' . $order['order_number'],
            'items' => $items,
        ];

        $payload = array_filter($payload, fn($v) => $v !== null && $v !== '');

        return ['ok' => true, 'payload' => $payload, 'message' => ''];
    }

    private static function extractError(array $response): string
    {
        $candidates = [
            $response['error'] ?? null,
            $response['message'] ?? null,
            $response['data']['error'] ?? null,
            $response['data']['message'] ?? null,
        ];

        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '') {
                return mb_substr($c, 0, 300);
            }
        }

        return '';
    }

    private static function abort(PDO $pdo, string $message): array
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return self::fail($message);
    }

    private static function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'warning' => null];
    }
}
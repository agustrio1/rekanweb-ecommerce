<?php

namespace App\Services\Biteship;

use App\Core\Database;
use PDO;
use PDOException;
use Throwable;

class WebhookService
{
    private const EVENTS = ['order.status', 'order.waybill_id', 'order.price'];

    /** Peringkat status: status tidak boleh mundur, dan status akhir (10) tidak bisa ditimpa. */
    private const FINAL_RANK = 10;

    private const RANK = [
        'confirmed' => 1,
        'scheduled' => 1,
        'courier_not_found' => 1,
        'allocated' => 2,
        'picking_up' => 3,
        'picked' => 4,
        'on_hold' => 4,
        'dropping_off' => 5,
        'return_in_transit' => 6,
        'delivered' => 10,
        'returned' => 10,
        'cancelled' => 10,
        'rejected' => 10,
        'disposed' => 10,
    ];

    private const ATTENTION = [
        'on_hold' => 'Pengiriman ditahan oleh kurir',
        'courier_not_found' => 'Kurir belum ditemukan',
        'cancelled' => 'Pengiriman dibatalkan',
        'rejected' => 'Pengiriman ditolak kurir',
        'disposed' => 'Paket dimusnahkan oleh kurir',
        'return_in_transit' => 'Paket sedang dikembalikan ke toko',
        'returned' => 'Paket dikembalikan ke toko',
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Cek header rahasia. Kalau belum dikonfigurasi, semua request ditolak.
     */
    public function authorized(array $server): bool
    {
        $name = (string) env('BITESHIP_WEBHOOK_HEADER', '');
        $secret = (string) env('BITESHIP_WEBHOOK_SECRET', '');

        if ($name === '' || $secret === '') {
            error_log('[BiteshipWebhook] BITESHIP_WEBHOOK_HEADER/SECRET belum diisi di .env');
            return false;
        }

        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $given = isset($server[$key]) && is_string($server[$key]) ? $server[$key] : '';

        return $given !== '' && hash_equals($secret, $given);
    }

    /**
     * Proses satu event webhook.
     *
     * Return: ['status' => int (kode HTTP), 'message' => string]
     */
    public function handle(array $data): array
    {
        $event = $this->str($data['event'] ?? null, 50);
        $biteshipOrderId = $this->str($data['order_id'] ?? null, 100);

        if (!in_array($event, self::EVENTS, true) || $biteshipOrderId === '') {
            return $this->respond(200, 'Ignored');
        }

        $status = strtolower($this->str($data['status'] ?? null, 50));
        $waybill = $this->str($data['courier_waybill_id'] ?? null, 100);
        $trackingId = $this->str($data['courier_tracking_id'] ?? null, 100);
        $price = isset($data['price']) && is_numeric($data['price']) ? (int) round((float) $data['price']) : 0;

        $hash = hash('sha256', implode('|', [$event, $biteshipOrderId, $status, $waybill, $price]));

        try {
            $stmt = $this->db->prepare('SELECT order_id FROM shipments WHERE biteship_order_id = ? LIMIT 1');
            $stmt->execute([$biteshipOrderId]);
            $orderId = $stmt->fetchColumn();

            if ($orderId === false) {
                error_log("[BiteshipWebhook] Order Biteship tidak dikenal: {$biteshipOrderId}");
                return $this->respond(200, 'Ignored');
            }

            $this->db->beginTransaction();

            // Urutan kunci sama dengan ShipmentService: order dulu, baru shipment.
            $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare('SELECT * FROM shipments WHERE order_id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $shipment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order || !$shipment) {
                $this->db->rollBack();
                return $this->respond(200, 'Ignored');
            }

            // Idempotency: event yang sama persis hanya dicatat sekali.
            try {
                $this->db->prepare(
                    "INSERT INTO shipment_tracking_histories
                        (shipment_id, order_id, event, status, waybill_id, price, payload, event_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $shipment['id'],
                    $orderId,
                    $event,
                    $status !== '' ? $status : null,
                    $waybill !== '' ? $waybill : null,
                    $price > 0 ? $price : null,
                    json_encode($this->payload($data), JSON_UNESCAPED_UNICODE),
                    $hash,
                ]);
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $this->db->rollBack();
                    return $this->respond(200, 'Duplicate');
                }
                throw $e;
            }

            $sets = [];
            $params = [];
            $tracking = [];

            // Nomor resi
            if ($waybill !== '' && $waybill !== (string) $shipment['waybill_id']) {
                $sets[] = 'waybill_id = ?';
                $params[] = $waybill;
            }

            // Tracking ID (sekali saja)
            if ($trackingId !== '' && empty($this->decode($shipment['tracking_data'])['tracking_id'])) {
                $tracking['tracking_id'] = $trackingId;
            }

            // Status pengiriman: tidak boleh mundur
            $applied = false;
            if ($status !== '' && isset(self::RANK[$status])) {
                $current = strtolower((string) $shipment['status']);
                $curRank = self::RANK[$current] ?? 0;

                if ($status !== $current && $curRank < self::FINAL_RANK && self::RANK[$status] >= $curRank) {
                    $sets[] = 'status = ?';
                    $params[] = $status;
                    $applied = true;
                }
            }

            // Ongkir berubah: tagihan pelanggan tidak diubah, hanya dicatat.
            $priceNote = null;
            if ($event === 'order.price' && $price > 0) {
                $tracking['biteship_price'] = $price;
                $billed = (int) $shipment['shipping_price'];

                if ($price !== $billed) {
                    $priceNote = 'Ongkir dari Biteship berubah menjadi Rp ' . number_format($price, 0, ',', '.')
                        . ' (ditagih ke pelanggan Rp ' . number_format($billed, 0, ',', '.')
                        . '). Tagihan pelanggan tidak diubah.';
                }
            }

            if ($sets) {
                $params[] = $shipment['id'];
                $this->db->prepare('UPDATE shipments SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
            }

            $this->mergeTracking((int) $shipment['id'], $tracking);

            $label = $waybill !== '' ? $waybill : (string) $shipment['waybill_id'];
            $orderStatus = (string) $order['order_status'];

            // Status pesanan mengikuti status pengiriman
            if ($applied) {
                $target = null;

                if (in_array($status, ['picked', 'dropping_off'], true) && $orderStatus === 'processing') {
                    $target = 'shipped';
                } elseif ($status === 'delivered' && in_array($orderStatus, ['processing', 'shipped'], true)) {
                    $target = 'completed';
                }

                if ($target !== null) {
                    $this->db->prepare('UPDATE orders SET order_status = ? WHERE id = ?')->execute([$target, $orderId]);
                    $this->addHistory($orderId, $orderStatus, $target, "Status kurir: {$status}" . ($label !== '' ? " (resi {$label})" : ''));
                } elseif (isset(self::ATTENTION[$status])) {
                    $this->addHistory($orderId, $orderStatus, $orderStatus, 'Perhatian: ' . self::ATTENTION[$status] . ($label !== '' ? " (resi {$label})" : ''));
                    error_log("[BiteshipWebhook][PERLU-TINDAKAN] Order {$order['order_number']}: {$status}");
                }
            }

            if ($priceNote !== null) {
                $this->addHistory($orderId, $orderStatus, $orderStatus, $priceNote);
                error_log("[BiteshipWebhook][PERLU-TINDAKAN] Order {$order['order_number']}: ongkir berubah ke {$price}");
            }

            $this->db->commit();

            return $this->respond(200, 'OK');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("[BiteshipWebhook] Error {$biteshipOrderId}: " . $e->getMessage());

            // 500 supaya Biteship mengirim ulang.
            return $this->respond(500, 'Server error');
        }
    }

    private function addHistory(int $orderId, string $old, string $new, string $notes): void
    {
        $this->db->prepare(
            'INSERT INTO order_status_histories (order_id, old_status, new_status, notes) VALUES (?, ?, ?, ?)'
        )->execute([$orderId, $old, $new, mb_substr($notes, 0, 1000)]);
    }

    /**
     * Gabungkan key ke tracking_data tanpa menghapus isi lain.
     */
    private function mergeTracking(int $shipmentId, array $pairs): void
    {
        if (!$pairs) {
            return;
        }

        $expr = 'COALESCE(tracking_data, JSON_OBJECT())';
        $params = [];

        foreach ($pairs as $key => $value) {
            // $key selalu konstanta dari kode ini, bukan input pengguna.
            $expr = "JSON_SET({$expr}, '$.{$key}', ?)";
            $params[] = $value;
        }

        $params[] = $shipmentId;
        $this->db->prepare("UPDATE shipments SET tracking_data = {$expr} WHERE id = ?")->execute($params);
    }

    private function decode($json): array
    {
        $data = json_decode((string) $json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Hanya simpan field yang dikenal dan bukan data pribadi (tanpa info driver).
     */
    private function payload(array $data): array
    {
        $allowed = [
            'event', 'order_id', 'status', 'courier_tracking_id', 'courier_waybill_id',
            'courier_company', 'courier_type', 'courier_link', 'price',
        ];

        $clean = [];
        foreach ($allowed as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $clean[$key] = mb_substr((string) $data[$key], 0, 255);
            }
        }

        return $clean;
    }

    private function str($value, int $max): string
    {
        return is_scalar($value) ? mb_substr(trim((string) $value), 0, $max) : '';
    }

    private function respond(int $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }
}
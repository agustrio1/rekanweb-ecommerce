<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Order
{
    /** Urutan perjalanan paket yang normal. */
    private const FLOW = ['confirmed', 'scheduled', 'allocated', 'picking_up', 'picked', 'dropping_off', 'delivered'];

    /** Label status kurir untuk pelanggan. */
    private const TRACKING_LABELS = [
        'confirmed' => 'Pesanan dikonfirmasi kurir',
        'scheduled' => 'Pengambilan paket dijadwalkan',
        'allocated' => 'Kurir sudah ditugaskan',
        'picking_up' => 'Kurir menuju lokasi pengambilan',
        'picked' => 'Paket sudah diambil kurir',
        'dropping_off' => 'Paket sedang diantar ke alamatmu',
        'delivered' => 'Paket sudah diterima',
        'on_hold' => 'Pengiriman sedang ditahan kurir',
        'courier_not_found' => 'Sedang mencari kurir',
        'cancelled' => 'Pengiriman dibatalkan',
        'rejected' => 'Pengiriman ditolak kurir',
        'disposed' => 'Paket tidak dapat dikirim',
        'return_in_transit' => 'Paket dikembalikan ke toko',
        'returned' => 'Paket dikembalikan ke toko',
    ];

    /**
     * Pesanan lengkap milik satu pelanggan (anti-IDOR: dicocokkan dengan user_id).
     */
    public static function findForUser(string $number, int $userId): ?array
    {
        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n AND user_id = :u LIMIT 1');
        $stmt->execute(['n' => $number, 'u' => $userId]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC');
        $stmt->execute(['id' => $order['id']]);
        $order['items'] = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT * FROM order_addresses WHERE order_id = :id LIMIT 1');
        $stmt->execute(['id' => $order['id']]);
        $order['address'] = $stmt->fetch() ?: null;

        $stmt = $pdo->prepare('SELECT * FROM shipments WHERE order_id = :id LIMIT 1');
        $stmt->execute(['id' => $order['id']]);
        $order['shipment'] = $stmt->fetch() ?: null;

        $stmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = :id LIMIT 1');
        $stmt->execute(['id' => $order['id']]);
        $order['payment'] = $stmt->fetch() ?: null;

        [$order['tracking'], $order['tracking_link']] = self::tracking($pdo, (int) $order['id']);

        return $order;
    }

    /**
     * Perjalanan paket untuk pelanggan: urut normal, tanpa duplikat, terbaru di atas.
     * Event harga/ongkir tidak ditampilkan. Link kurir hanya diterima kalau HTTPS.
     *
     * @return array{0: array, 1: ?string}
     */
    private static function tracking(PDO $pdo, int $orderId): array
    {
        $stmt = $pdo->prepare(
            'SELECT event, status, payload, created_at
             FROM shipment_tracking_histories
             WHERE order_id = :id
             ORDER BY id ASC
             LIMIT 100'
        );
        $stmt->execute(['id' => $orderId]);

        $flow = [];
        $other = [];
        $link = null;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            if (is_array($payload) && !empty($payload['courier_link'])) {
                $parts = parse_url((string) $payload['courier_link']);
                if (($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])) {
                    $link = (string) $payload['courier_link'];
                }
            }

            if ($row['event'] !== 'order.status') {
                continue;
            }

            $status = (string) $row['status'];
            if (!isset(self::TRACKING_LABELS[$status])) {
                continue;
            }

            if (in_array($status, self::FLOW, true)) {
                $flow[$status] ??= $row['created_at'];
            } else {
                $other[$status] ??= $row['created_at'];
            }
        }

        uksort($flow, fn($a, $b) => array_search($a, self::FLOW, true) <=> array_search($b, self::FLOW, true));

        $items = [];
        foreach ($flow + $other as $status => $time) {
            $items[] = ['status' => $status, 'label' => self::TRACKING_LABELS[$status], 'time' => $time];
        }

        return [array_reverse($items), $link];
    }

    /**
     * Label dan kelas badge status untuk pelanggan.
     *
     * @return array{0: string, 1: string}
     */
    public static function statusBadge(array $o): array
    {
        return match (true) {
            $o['order_status'] === 'cancelled' && $o['payment_status'] === 'paid' => ['Dibatalkan, menunggu refund', 'border-rose-200 bg-rose-50 text-rose-700'],
            $o['order_status'] === 'cancelled' && $o['payment_status'] === 'expired' => ['Kedaluwarsa', 'border-rose-200 bg-rose-50 text-rose-700'],
            $o['order_status'] === 'cancelled' => ['Dibatalkan', 'border-zinc-200 bg-zinc-50 text-zinc-700'],
            $o['payment_status'] === 'failed' => ['Pembayaran gagal', 'border-rose-200 bg-rose-50 text-rose-700'],
            $o['payment_status'] === 'refunded' => ['Dana dikembalikan', 'border-zinc-200 bg-zinc-50 text-zinc-700'],
            $o['payment_status'] === 'pending' => ['Menunggu pembayaran', 'border-yellow-300 bg-yellow-50 text-yellow-800'],
            $o['order_status'] === 'shipped' => ['Dikirim', 'border-sky-200 bg-sky-50 text-sky-700'],
            $o['order_status'] === 'completed' => ['Selesai', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
            default => ['Diproses', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
        };
    }
}
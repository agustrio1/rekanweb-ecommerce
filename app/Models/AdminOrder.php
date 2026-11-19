<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AdminOrder
{
    public const PER_PAGE = 20;

    public const TABS = [
        'all'        => ['label' => 'Semua',          'where' => '1 = 1'],
        'unpaid'     => ['label' => 'Menunggu bayar', 'where' => "o.payment_status = 'pending' AND o.order_status = 'pending'"],
        'to_process' => ['label' => 'Perlu diproses', 'where' => "o.payment_status = 'paid' AND o.order_status = 'pending'"],
        'processing' => ['label' => 'Diproses',       'where' => "o.order_status = 'processing'"],
        'shipped'    => ['label' => 'Dikirim',        'where' => "o.order_status = 'shipped'"],
        'done'       => ['label' => 'Selesai',        'where' => "o.order_status = 'completed'"],
        'cancelled'  => ['label' => 'Dibatalkan',     'where' => "o.order_status = 'cancelled'"],
    ];

    /**
     * @return array{rows: array, total: int, page: int, pages: int}
     */
    public static function paginate(string $tab, string $q, int $page): array
    {
        $pdo = Database::getInstance()->getConnection();

        $where = [self::TABS[$tab]['where'] ?? '1 = 1'];
        $params = [];

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(o.order_number LIKE :q1 OR u.name LIKE :q2 OR u.email LIKE :q3)';
            $params = ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }

        $from = 'FROM orders o
                 LEFT JOIN users u ON u.id = o.user_id
                 LEFT JOIN shipments s ON s.order_id = o.id
                 WHERE ' . implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT COUNT(*) {$from}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $limit = (int) self::PER_PAGE;
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare(
            "SELECT o.id, o.order_number, o.total_amount, o.order_status, o.payment_status, o.created_at,
                    u.name AS customer_name, s.status AS shipment_status, s.waybill_id
             {$from}
             ORDER BY o.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * Jumlah pesanan per tab.
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $pdo = Database::getInstance()->getConnection();

        $row = $pdo->query(
            "SELECT COUNT(*) AS c_all,
                    SUM(payment_status = 'pending' AND order_status = 'pending') AS c_unpaid,
                    SUM(payment_status = 'paid' AND order_status = 'pending') AS c_to_process,
                    SUM(order_status = 'processing') AS c_processing,
                    SUM(order_status = 'shipped') AS c_shipped,
                    SUM(order_status = 'completed') AS c_done,
                    SUM(order_status = 'cancelled') AS c_cancelled
             FROM orders"
        )->fetch(PDO::FETCH_ASSOC);

        $counts = [];
        foreach (array_keys(self::TABS) as $key) {
            $counts[$key] = (int) ($row['c_' . $key] ?? 0);
        }

        return $counts;
    }

    /**
     * Detail lengkap satu pesanan untuk admin.
     */
    public static function detail(string $number): ?array
    {
        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare(
            "SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
             FROM orders o LEFT JOIN users u ON u.id = o.user_id
             WHERE o.order_number = ? LIMIT 1"
        );
        $stmt->execute([$number]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        $id = (int) $order['id'];

        $fetchAll = function (string $sql) use ($pdo, $id): array {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        $fetchOne = function (string $sql) use ($pdo, $id): ?array {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        };

        $shipment = $fetchOne('SELECT * FROM shipments WHERE order_id = ? LIMIT 1');
        if ($shipment) {
            $tracking = json_decode((string) ($shipment['tracking_data'] ?? ''), true);
            $shipment['tracking'] = is_array($tracking) ? $tracking : [];
        }

        return [
            'order' => $order,
            'items' => $fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC'),
            'address' => $fetchOne('SELECT * FROM order_addresses WHERE order_id = ? LIMIT 1'),
            'shipment' => $shipment,
            'payment' => $fetchOne('SELECT * FROM payments WHERE order_id = ? LIMIT 1'),
            'histories' => $fetchAll('SELECT * FROM order_status_histories WHERE order_id = ? ORDER BY id DESC'),
            'callbacks' => $fetchAll('SELECT * FROM payment_callbacks WHERE order_id = ? ORDER BY id DESC LIMIT 10'),
        ];
    }

    /**
     * Label dan kelas badge status untuk admin.
     *
     * @return array{0: string, 1: string}
     */
    public static function badge(array $o): array
    {
        return match (true) {
            $o['order_status'] === 'cancelled' && $o['payment_status'] === 'paid' => ['Perlu refund', 'border-rose-200 bg-rose-50 text-rose-700'],
            $o['order_status'] === 'cancelled' && $o['payment_status'] === 'expired' => ['Kedaluwarsa', 'border-rose-200 bg-rose-50 text-rose-700'],
            $o['order_status'] === 'cancelled' => ['Dibatalkan', 'border-zinc-200 bg-zinc-50 text-zinc-700'],
            $o['payment_status'] === 'pending' => ['Menunggu pembayaran', 'border-yellow-300 bg-yellow-50 text-yellow-800'],
            $o['payment_status'] === 'failed' => ['Pembayaran gagal', 'border-rose-200 bg-rose-50 text-rose-700'],
            $o['payment_status'] === 'refunded' => ['Dana dikembalikan', 'border-zinc-200 bg-zinc-50 text-zinc-700'],
            $o['order_status'] === 'completed' => ['Selesai', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
            $o['order_status'] === 'shipped' => ['Dikirim', 'border-sky-200 bg-sky-50 text-sky-700'],
            $o['order_status'] === 'processing' => ['Diproses', 'border-sky-200 bg-sky-50 text-sky-700'],
            default => ['Perlu diproses', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
        };
    }

    public static function date(?string $value, bool $withTime = false): string
    {
        if (!$value) {
            return '-';
        }

        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $t = strtotime($value);
        $text = date('j', $t) . ' ' . $months[(int) date('n', $t)] . ' ' . date('Y', $t);

        return $withTime ? $text . ', ' . date('H:i', $t) : $text;
    }
}
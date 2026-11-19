<?php

namespace App\Models;

use App\Core\Database;

class AdminDashboard
{
    public const LOW_STOCK = 5;

    /**
     * Angka ringkasan pesanan. Penjualan hanya dari pesanan lunas yang tidak dibatalkan.
     */
    public static function stats(): array
    {
        $pdo = Database::getInstance()->getConnection();

        $row = $pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN payment_status = 'paid' AND order_status <> 'cancelled' THEN total_amount END), 0) AS total_sales,
                COUNT(*) AS total_orders,
                SUM(payment_status = 'pending') AS pending_payment,
                SUM(payment_status = 'paid' AND order_status = 'pending') AS to_process,
                SUM(order_status = 'processing') AS processing,
                SUM(order_status = 'shipped') AS shipped,
                SUM(payment_status = 'paid' AND order_status = 'cancelled') AS needs_refund
             FROM orders"
        )->fetch() ?: [];

        $stats = [];
        foreach (['total_sales', 'total_orders', 'pending_payment', 'to_process', 'processing', 'shipped', 'needs_refund'] as $key) {
            $stats[$key] = (int) ($row[$key] ?? 0);
        }

        return $stats;
    }

    /**
     * Penjualan harian untuk $days hari terakhir (hari ini termasuk). Hari kosong diisi 0.
     *
     * @return array<int, array{date: string, total: int, count: int}>
     */
    public static function salesSeries(int $days): array
    {
        $days = max(1, min(90, $days));
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT DATE(COALESCE(p.paid_at, o.created_at)) AS d,
                    SUM(o.total_amount) AS total,
                    COUNT(*) AS cnt
             FROM orders o
             LEFT JOIN payments p ON p.order_id = o.id
             WHERE o.payment_status = 'paid'
               AND o.order_status <> 'cancelled'
               AND COALESCE(p.paid_at, o.created_at) >= :from
             GROUP BY DATE(COALESCE(p.paid_at, o.created_at))"
        );
        $stmt->execute(['from' => $from . ' 00:00:00']);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['d']] = ['total' => (int) $row['total'], 'count' => (int) $row['cnt']];
        }

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $series[] = [
                'date' => $date,
                'total' => $map[$date]['total'] ?? 0,
                'count' => $map[$date]['count'] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Produk terlaris dari pesanan lunas (memakai snapshot nama, bukan data produk saat ini).
     */
    public static function topProducts(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $pdo = Database::getInstance()->getConnection();

        $rows = $pdo->query(
            "SELECT oi.product_name_snapshot AS name,
                    SUM(oi.quantity) AS qty,
                    SUM(oi.price_snapshot * oi.quantity) AS revenue
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.payment_status = 'paid' AND o.order_status <> 'cancelled'
             GROUP BY oi.product_name_snapshot
             ORDER BY qty DESC, revenue DESC
             LIMIT {$limit}"
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['qty'] = (int) $row['qty'];
            $row['revenue'] = (int) $row['revenue'];
        }
        unset($row);

        return $rows;
    }

    /**
     * Variasi aktif dengan stok menipis.
     *
     * @return array{total: int, rows: array}
     */
    public static function lowStock(int $limit = 6): array
    {
        $limit = max(1, $limit);
        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM product_variants v
             JOIN products p ON p.id = v.product_id
             WHERE p.is_active = 1 AND v.stock <= :t'
        );
        $stmt->execute(['t' => self::LOW_STOCK]);
        $total = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT p.id AS product_id, p.name, v.variant_name, v.sku, v.stock
             FROM product_variants v
             JOIN products p ON p.id = v.product_id
             WHERE p.is_active = 1 AND v.stock <= :t
             ORDER BY v.stock ASC, p.name ASC
             LIMIT {$limit}"
        );
        $stmt->execute(['t' => self::LOW_STOCK]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['product_id'] = (int) $row['product_id'];
            $row['stock'] = (int) $row['stock'];
        }
        unset($row);

        return ['total' => $total, 'rows' => $rows];
    }

    public static function recentOrders(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $pdo = Database::getInstance()->getConnection();

        $rows = $pdo->query(
            "SELECT o.order_number, o.total_amount, o.payment_status, o.order_status, o.created_at,
                    u.name AS customer_name
             FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT {$limit}"
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['total_amount'] = (int) $row['total_amount'];
        }
        unset($row);

        return $rows;
    }
}
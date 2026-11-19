<?php

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$orderNumber = $argv[1] ?? null;
if (!$orderNumber) {
    echo "Pemakaian: php scripts/show_order.php ORDER_NUMBER\n";
    exit(1);
}

$db = Database::getInstance()->getConnection();

$s = $db->prepare("SELECT id, order_status, payment_status, total_amount, payment_method FROM orders WHERE order_number = ?");
$s->execute([$orderNumber]);
$o = $s->fetch(PDO::FETCH_ASSOC);

if (!$o) {
    echo "Order {$orderNumber} tidak ditemukan.\n";
    exit(1);
}

echo "orders            : order_status={$o['order_status']}, payment_status={$o['payment_status']}, total={$o['total_amount']}, metode={$o['payment_method']}\n";

$s = $db->prepare("SELECT status, paid_at, va_number FROM payments WHERE order_id = ?");
$s->execute([$o['id']]);
$p = $s->fetch(PDO::FETCH_ASSOC);
echo "payments          : status=" . ($p['status'] ?? '-') . ", paid_at=" . ($p['paid_at'] ?? '-') . ", va=" . ($p['va_number'] ?? '-') . "\n";

$s = $db->prepare("SELECT status, COUNT(*) AS n FROM stock_reservations WHERE order_id = ? GROUP BY status");
$s->execute([$o['id']]);
$res = [];
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $res[] = "{$r['status']}={$r['n']}";
}
echo "stock_reservations: " . implode(', ', $res) . "\n";

$s = $db->prepare("SELECT result_code, note, created_at FROM payment_callbacks WHERE merchant_order_id = ? ORDER BY id ASC");
$s->execute([$orderNumber]);
echo "payment_callbacks :\n";
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "  - {$c['created_at']} | {$c['result_code']} | {$c['note']}\n";
}
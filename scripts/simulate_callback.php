<?php

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$orderNumber = $argv[1] ?? null;
$scenario = $argv[2] ?? 'ok';

if (!$orderNumber || !in_array($scenario, ['ok', 'badsig', 'wrongamount', 'failed'], true)) {
    echo "Pemakaian: php scripts/simulate_callback.php ORDER_NUMBER [ok|badsig|wrongamount|failed]\n";
    exit(1);
}

$cfg = require __DIR__ . '/../config/duitku.php';
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare(
    "SELECT o.id, o.total_amount, p.gateway_transaction_id
     FROM orders o LEFT JOIN payments p ON p.order_id = o.id
     WHERE o.order_number = ?"
);
$stmt->execute([$orderNumber]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "Order {$orderNumber} tidak ditemukan.\n";
    exit(1);
}

$amount = (int) $order['total_amount'];
if ($scenario === 'wrongamount') {
    $amount -= 1000;
}

$post = [
    'merchantCode'     => $cfg['merchant_code'],
    'amount'           => (string) $amount,
    'merchantOrderId'  => $orderNumber,
    'productDetail'    => 'Simulasi callback',
    'additionalParam'  => '',
    'paymentCode'      => 'VA',
    'resultCode'       => $scenario === 'failed' ? '01' : '00',
    'merchantUserId'   => '',
    'reference'        => (string) ($order['gateway_transaction_id'] ?? ''),
    'publisherOrderId' => 'SIM' . time(),
    'spUserHash'       => '',
    'settlementDate'   => date('Y-m-d'),
    'issuerCode'       => '',
];

$post['signature'] = $scenario === 'badsig'
    ? str_repeat('0', 32)
    : md5($post['merchantCode'] . $post['amount'] . $post['merchantOrderId'] . $cfg['api_key']);

$ch = curl_init($cfg['callback_url']);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($post),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
]);
$body = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "URL      : {$cfg['callback_url']}\n";
echo "Skenario : {$scenario}\n";
echo "Respons  : HTTP {$httpCode} | " . ($body === false ? curl_error($ch) : $body) . "\n\n";

$s = $db->prepare("SELECT order_status, payment_status FROM orders WHERE id = ?");
$s->execute([$order['id']]);
$o = $s->fetch(PDO::FETCH_ASSOC);
echo "orders            : order_status={$o['order_status']}, payment_status={$o['payment_status']}\n";

$s = $db->prepare("SELECT status, paid_at FROM payments WHERE order_id = ?");
$s->execute([$order['id']]);
$p = $s->fetch(PDO::FETCH_ASSOC);
echo "payments          : status=" . ($p['status'] ?? '-') . ", paid_at=" . ($p['paid_at'] ?? '-') . "\n";

$s = $db->prepare("SELECT status, COUNT(*) AS n FROM stock_reservations WHERE order_id = ? GROUP BY status");
$s->execute([$order['id']]);
$res = [];
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $res[] = "{$r['status']}={$r['n']}";
}
echo "stock_reservations: " . implode(', ', $res) . "\n";

$s = $db->prepare("SELECT result_code, note FROM payment_callbacks WHERE merchant_order_id = ? ORDER BY id ASC");
$s->execute([$orderNumber]);
echo "payment_callbacks :\n";
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "  - {$c['result_code']} | {$c['note']}\n";
}
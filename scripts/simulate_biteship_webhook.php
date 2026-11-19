<?php

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$orderNumber = $argv[1] ?? null;
$kind = $argv[2] ?? null;
$value = $argv[3] ?? '';
$baseUrl = rtrim($argv[4] ?? 'http://127.0.0.1:8000', '/');

$kinds = ['status', 'waybill', 'price', 'badauth'];

if (!$orderNumber || !in_array($kind, $kinds, true) || ($kind !== 'badauth' && $value === '')) {
    echo "Pemakaian:\n";
    echo "  php scripts/simulate_biteship_webhook.php ORDER status picked\n";
    echo "  php scripts/simulate_biteship_webhook.php ORDER waybill JNE123456\n";
    echo "  php scripts/simulate_biteship_webhook.php ORDER price 20000\n";
    echo "  php scripts/simulate_biteship_webhook.php ORDER badauth\n";
    exit(1);
}

$headerName = (string) env('BITESHIP_WEBHOOK_HEADER', '');
$secret = (string) env('BITESHIP_WEBHOOK_SECRET', '');

if ($headerName === '' || $secret === '') {
    echo "BITESHIP_WEBHOOK_HEADER dan BITESHIP_WEBHOOK_SECRET belum diisi di .env\n";
    exit(1);
}

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare(
    "SELECT s.id AS shipment_id, s.biteship_order_id, s.courier_company, s.courier_type, o.id AS order_id
     FROM orders o JOIN shipments s ON s.order_id = o.id
     WHERE o.order_number = ?"
);
$stmt->execute([$orderNumber]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['biteship_order_id'])) {
    echo "Order {$orderNumber} tidak ditemukan atau belum punya biteship_order_id.\n";
    exit(1);
}

$payload = [
    'event'              => 'order.status',
    'order_id'           => $row['biteship_order_id'],
    'status'             => 'allocated',
    'courier_tracking_id' => 'TRK-SIM-' . $row['shipment_id'],
    'courier_waybill_id' => '',
    'courier_company'    => $row['courier_company'],
    'courier_type'       => $row['courier_type'],
];

switch ($kind) {
    case 'status':
        $payload['status'] = $value;
        break;
    case 'waybill':
        $payload['event'] = 'order.waybill_id';
        $payload['courier_waybill_id'] = $value;
        break;
    case 'price':
        $payload['event'] = 'order.price';
        $payload['price'] = (float) $value;
        break;
    case 'badauth':
        $payload['status'] = $value !== '' ? $value : 'picked';
        break;
}

$sent = $kind === 'badauth' ? 'salah-' . $secret : $secret;

$ch = curl_init($baseUrl . '/webhook/biteship');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', "{$headerName}: {$sent}"],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "URL      : {$baseUrl}/webhook/biteship\n";
echo "Skenario : {$kind} {$value}\n";
echo "Respons  : HTTP {$code} | " . ($body === false ? curl_error($ch) : $body) . "\n\n";

$s = $db->prepare("SELECT status, waybill_id, tracking_data FROM shipments WHERE id = ?");
$s->execute([$row['shipment_id']]);
$sh = $s->fetch(PDO::FETCH_ASSOC);
$td = json_decode((string) ($sh['tracking_data'] ?? ''), true) ?: [];
echo "shipments       : status={$sh['status']}, resi=" . ($sh['waybill_id'] ?: '-')
    . ", tracking_id=" . ($td['tracking_id'] ?? '-') . ", harga_biteship=" . ($td['biteship_price'] ?? '-') . "\n";

$s = $db->prepare("SELECT order_status FROM orders WHERE id = ?");
$s->execute([$row['order_id']]);
echo "orders          : order_status=" . $s->fetchColumn() . "\n";

$s = $db->prepare("SELECT event, status, waybill_id, price FROM shipment_tracking_histories WHERE shipment_id = ? ORDER BY id ASC");
$s->execute([$row['shipment_id']]);
echo "tracking history:\n";
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $h) {
    echo "  - {$h['event']} | " . ($h['status'] ?? '-') . " | " . ($h['waybill_id'] ?? '-') . " | " . ($h['price'] ?? '-') . "\n";
}

$s = $db->prepare("SELECT old_status, new_status, notes FROM order_status_histories WHERE order_id = ? ORDER BY id DESC LIMIT 4");
$s->execute([$row['order_id']]);
echo "riwayat pesanan (terbaru di atas):\n";
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $h) {
    echo "  - {$h['old_status']} -> {$h['new_status']} | " . mb_substr((string) $h['notes'], 0, 90) . "\n";
}
<?php

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;
use App\Services\Duitku\PaymentService;

$number = $argv[1] ?? null;
if (!$number) {
    echo "Pemakaian: php scripts/test_payment.php INV-YYYYMMDD-XXXX\n";
    exit(1);
}

// Cek zona waktu: expires_at (DB) vs jam PHP vs jam MySQL
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT expires_at, payment_status, order_status, total_amount FROM orders WHERE order_number = ?");
$stmt->execute([$number]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "expires_at (DB) : " . ($row['expires_at'] ?? '-') . "\n";
echo "sekarang (PHP)  : " . date('Y-m-d H:i:s') . "\n";
echo "sekarang (MySQL): " . $db->query('SELECT NOW()')->fetchColumn() . "\n";
echo "status          : " . ($row ? "{$row['order_status']} / {$row['payment_status']} / {$row['total_amount']}" : '(order tidak ada)') . "\n\n";

$service = new PaymentService();
$result = $service->createForOrder($number);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "[1] mulai\n";

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e !== null) {
        echo "[FATAL] {$e['message']} di {$e['file']}:{$e['line']}\n";
    }
    echo "[selesai] script berhenti\n";
});

try {
    require __DIR__ . '/../bootstrap/app.php';
    echo "[2] bootstrap selesai\n";

    // Bootstrap bisa saja mematikan error display lagi
    ini_set('display_errors', '1');
    error_reporting(E_ALL);

    $config = require __DIR__ . '/../config/duitku.php';
    echo "[3] config dimuat\n";
    echo "    merchant_code : " . ($config['merchant_code'] !== '' ? $config['merchant_code'] : '(KOSONG)') . "\n";
    echo "    api_key       : " . ($config['api_key'] !== '' ? substr($config['api_key'], 0, 4) . '****' : '(KOSONG)') . "\n";
    echo "    env           : " . $config['env'] . "\n";
    echo "    base_url      : " . $config['base_url'] . "\n";
    echo "    callback_url  : " . $config['callback_url'] . "\n";

    $client = new App\Services\Duitku\DuitkuClient($config);
    echo "[4] client dibuat\n";

    $result = $client->createInvoice([
        'merchant_order_id' => 'TEST-' . time(),
        'amount'            => 150000,
        'product_details'   => 'Tes invoice RekanWeb Commerce',
        'email'             => 'tes@example.com',
        'phone'             => '081234567890',
        'customer_name'     => 'Budi Santoso',
        'items'             => [
            ['name' => 'Kaos Polos', 'price' => 100000, 'quantity' => 1],
            ['name' => 'Ongkir',     'price' => 50000,  'quantity' => 1],
        ],
    ]);

    echo "[5] hasil:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    echo "[ERROR] " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "        di " . $e->getFile() . ":" . $e->getLine() . "\n";
}
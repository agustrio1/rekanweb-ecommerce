<?php

require __DIR__ . '/../bootstrap/app.php';

$cfg = require __DIR__ . '/../config/duitku.php';
$file = __DIR__ . '/../storage/logs/duitku_callback_raw.log';

if (!is_file($file)) {
    echo "Belum ada log callback di storage/logs/duitku_callback_raw.log\n";
    exit(1);
}

$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$last = json_decode((string) end($lines), true);
$body = $last['body'] ?? '';

$data = json_decode($body, true);
if (!is_array($data)) {
    parse_str($body, $data);
}

$m = (string) ($data['merchantCode'] ?? '');
$a = (string) ($data['amount'] ?? '');
$o = (string) ($data['merchantOrderId'] ?? '');
$sig = strtolower((string) ($data['signature'] ?? ''));
$key = $cfg['api_key'];

echo "Waktu callback  : " . ($last['time'] ?? '-') . "\n";
echo "Content-Type    : " . ($last['content_type'] ?? '-') . "\n";
echo "merchantCode    : {$m}\n";
echo "amount          : {$a}\n";
echo "merchantOrderId : {$o}\n";
echo "resultCode      : " . ($data['resultCode'] ?? '-') . "\n";
echo "reference       : " . ($data['reference'] ?? '-') . "\n";
echo "signature       : {$sig}\n\n";

$candidates = [
    'md5(code.amount.order.key)'         => md5($m . $a . $o . $key),
    'sha256(code.amount.order.key)'      => hash('sha256', $m . $a . $o . $key),
    'hmac_sha256(code.amount.order, key)' => hash_hmac('sha256', $m . $a . $o, $key),
    'md5(code.order.amount.key)'         => md5($m . $o . $a . $key),
];

foreach ($candidates as $name => $hash) {
    echo ($hash === $sig ? 'COCOK  ' : 'tidak  ') . $name . "\n";
}
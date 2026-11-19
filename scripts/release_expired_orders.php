<?php

// Hanya boleh dijalankan lewat CLI / cron, bukan lewat browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/app.php';

$count = \App\Services\Order\OrderService::releaseExpired(200);

echo date('Y-m-d H:i:s') . " - {$count} pesanan kedaluwarsa dilepas\n";
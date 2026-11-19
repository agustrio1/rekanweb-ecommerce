<?php

namespace App\Controllers\Webhook;

use App\Services\Biteship\WebhookService;

class BiteshipController
{
    /**
     * Endpoint webhook Biteship (server-to-server).
     * Tanpa login dan tanpa CSRF: keamanannya dari header rahasia.
     */
    public function callback()
    {
        session_write_close();

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');

        $service = new WebhookService();

        $raw = (string) file_get_contents('php://input');
        $authorized = $service->authorized($_SERVER);

        $this->debugLog($raw, $authorized);

        if (!$authorized) {
            http_response_code(401);
            echo 'Unauthorized';
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo 'Bad request';
            return;
        }

        $result = $service->handle($data);

        http_response_code($result['status']);
        echo $result['message'];
    }

    /**
     * Log mentah untuk debugging. Hanya aktif kalau BITESHIP_WEBHOOK_DEBUG=1.
     * Yang dicatat: NAMA header (bukan nilainya) dan body.
     */
    private function debugLog(string $raw, bool $authorized): void
    {
        if ((string) env('BITESHIP_WEBHOOK_DEBUG', '0') !== '1') {
            return;
        }

        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $headers = [];
        foreach (array_keys($_SERVER) as $key) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[] = $key;
            }
        }

        $line = json_encode([
            'time'       => date('c'),
            'authorized' => $authorized,
            'headers'    => $headers,
            'body'       => mb_substr($raw, 0, 4000),
        ], JSON_UNESCAPED_SLASHES) . "\n";

        @file_put_contents($dir . '/biteship_webhook_raw.log', $line, FILE_APPEND | LOCK_EX);
    }
}
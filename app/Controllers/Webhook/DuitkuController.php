<?php

namespace App\Controllers\Webhook;

use App\Services\Duitku\CallbackService;

class DuitkuController
{
    /**
     * Endpoint callback server-to-server Duitku.
     * Tanpa login dan tanpa CSRF: keamanannya dari verifikasi signature.
     */
    public function callback()
    {
        // Callback tidak butuh session, lepas supaya tidak mengunci.
        session_write_close();

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');

        $raw = (string) file_get_contents('php://input');

        $post = $_POST;
        if (!$post) {
            parse_str($raw, $post);
        }

        $this->debugLog($raw);

        $result = (new CallbackService())->handle($post);

        http_response_code($result['status']);
        echo $result['message'];
    }

    /**
     * Simpan body mentah callback untuk debugging. HANYA aktif di mode sandbox.
     */
    private function debugLog(string $raw): void
    {
        $root = dirname(__DIR__, 3);
        $config = require $root . '/config/duitku.php';

        if (($config['env'] ?? '') !== 'sandbox') {
            return;
        }

        $dir = $root . '/storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('[DuitkuCallback] Folder storage/logs tidak bisa dibuat.');
            return;
        }

        $line = json_encode([
            'time'         => date('c'),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
            'body'         => mb_substr($raw, 0, 4000),
        ], JSON_UNESCAPED_SLASHES) . "\n";

        @file_put_contents($dir . '/duitku_callback_raw.log', $line, FILE_APPEND | LOCK_EX);
    }
}
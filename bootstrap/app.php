<?php

// 1. Load environment variables
require __DIR__ . '/../app/Helpers/env.php';
load_env(__DIR__ . '/../.env');

// 2. Mode aplikasi dan penanganan error.
// Tanpa APP_ENV dianggap production (aman secara default).
$appEnv = strtolower((string) (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) ?: 'production'));
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', $appEnv !== 'production');
}

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

$logDir = __DIR__ . '/../storage/logs';
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php-error.log');
}

set_exception_handler(function (\Throwable $e): void {
    error_log(sprintf('[%s] %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, (string) $e . PHP_EOL);
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($path) ? $path : '/';
    $wantsJson = str_starts_with($path, '/api/') || str_starts_with($path, '/webhook/');

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: ' . ($wantsJson ? 'application/json' : 'text/html') . '; charset=utf-8');
    }

    if ($wantsJson) {
        $body = ['ok' => false, 'message' => 'Terjadi kesalahan pada server.'];
        if (APP_DEBUG) {
            $body['debug'] = $e->getMessage();
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        return;
    }

    if (APP_DEBUG) {
        echo '<pre style="white-space:pre-wrap;padding:1rem;font:13px/1.5 monospace">'
            . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Terjadi kesalahan</title></head>'
        . '<body style="margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;'
        . 'font-family:system-ui,sans-serif;background:#fafafa;color:#18181b;padding:1rem;text-align:center">'
        . '<div><h1 style="font-size:1.25rem;margin:0 0 .5rem">Terjadi kesalahan</h1>'
        . '<p style="margin:0 0 1.25rem;color:#52525b;font-size:.9rem">Maaf, ada masalah di server. Coba lagi beberapa saat.</p>'
        . '<a href="/" style="display:inline-block;padding:.65rem 1.25rem;border-radius:.5rem;background:#facc15;'
        . 'color:#18181b;text-decoration:none;font-weight:600;font-size:.9rem">Kembali ke beranda</a></div>'
        . '</body></html>';
});

// 3. Simple PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 4. Load Global Helper Functions
require __DIR__ . '/../app/Helpers/functions.php';

// 5. Deteksi HTTPS (langsung atau di belakang proxy/CDN).
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

// 6. Session: cookie Secure otomatis saat HTTPS, tetap jalan di localhost HTTP.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax', // Lax agar cookie terkirim saat redirect balik dari pembayaran
    ]);
    session_start();
}

// 7. Header keamanan (dilewati di CLI)
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

    if ($isHttps && !APP_DEBUG) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// 8. Set timezone
date_default_timezone_set('Asia/Jakarta');

// 9. Vite Asset Helpers.
// Manifest dicari di document root (hosting) lalu di ../public (lokal).
// URL aset relatif, jadi tidak bergantung pada APP_URL.
if (!function_exists('vite_manifest')) {
    function vite_manifest(): array
    {
        static $manifest = null;

        if ($manifest !== null) {
            return $manifest;
        }

        $manifest = [];
        $roots = [rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'), __DIR__ . '/../public'];

        foreach ($roots as $root) {
            $file = $root . '/assets/.vite/manifest.json';
            if ($root !== '' && is_file($file)) {
                $data = json_decode((string) file_get_contents($file), true);
                if (is_array($data)) {
                    $manifest = $data;
                }
                break;
            }
        }

        return $manifest;
    }
}

if (!function_exists('vite_js')) {
    function vite_js($key = 'resources/js/app.js') {
        $manifest = vite_manifest();

        return isset($manifest[$key]['file']) ? '/assets/' . $manifest[$key]['file'] : '/assets/app.js';
    }
}

if (!function_exists('vite_css')) {
    function vite_css($key = 'resources/js/app.js') {
        $manifest = vite_manifest();

        return isset($manifest[$key]['css'][0]) ? '/assets/' . $manifest[$key]['css'][0] : '/assets/app.css';
    }
}
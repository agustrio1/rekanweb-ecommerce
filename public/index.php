<?php

// Lokasi source. Tata letak standar: folder ini ada di dalam proyek.
// Bila source di luar public_html, tulis path absolutnya di file ".base-path" (satu baris).
$base = dirname(__DIR__);
$override = __DIR__ . '/.base-path';

if (is_file($override)) {
    $custom = rtrim(trim((string) file_get_contents($override)), '/');
    if ($custom !== '' && is_file($custom . '/bootstrap/app.php')) {
        $base = $custom;
    }
}

define('BASE_PATH', $base);

require BASE_PATH . '/bootstrap/app.php';

use App\Core\Router;

$router = new Router();

// Load admin routes (prioritas lebih tinggi)
$router->loadRoutes(BASE_PATH . '/routes/admin.php');

// Load public routes
$router->loadRoutes(BASE_PATH . '/routes/web.php');

$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
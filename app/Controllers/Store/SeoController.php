<?php

namespace App\Controllers\Store;

use App\Core\Database;
use App\Helpers\Seo;

class SeoController
{
    public function sitemap()
    {
        $pdo = Database::getInstance()->getConnection();

        // [path, lastmod atau null]
        $urls = [['/', null], ['/produk', null]];

        foreach ($pdo->query('SELECT slug FROM categories WHERE is_active = 1 ORDER BY id ASC')->fetchAll() as $row) {
            $urls[] = ['/kategori/' . rawurlencode($row['slug']), null];
        }

        $products = $pdo->query(
            'SELECT p.slug, p.updated_at
             FROM products p
             JOIN categories c ON c.id = p.category_id AND c.is_active = 1
             WHERE p.is_active = 1
               AND EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id)
             ORDER BY p.id ASC
             LIMIT 45000'
        )->fetchAll();
        foreach ($products as $row) {
            $urls[] = ['/produk/' . rawurlencode($row['slug']), $row['updated_at']];
        }

        foreach ($pdo->query('SELECT slug, updated_at FROM pages WHERE is_active = 1 ORDER BY id ASC')->fetchAll() as $row) {
            $urls[] = ['/halaman/' . rawurlencode($row['slug']), $row['updated_at']];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as [$path, $updated]) {
            $xml .= '  <url><loc>' . htmlspecialchars(Seo::absolute($path), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
            if ($updated) {
                $xml .= '<lastmod>' . date('c', (int) strtotime((string) $updated)) . '</lastmod>';
            }
            $xml .= "</url>\n";
        }

        $xml .= '</urlset>' . "\n";

        $this->headers('application/xml');
        echo $xml;
    }

    public function robots()
    {
        $lines = ['User-agent: *'];

        if (defined('APP_DEBUG') && APP_DEBUG) {
            // Lingkungan pengembangan/uji: jangan diindeks.
            $lines[] = 'Disallow: /';
        } else {
            foreach (['/admin', '/akun', '/keranjang', '/checkout', '/pesanan', '/pembayaran', '/login', '/register', '/api/', '/webhook/'] as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: ' . Seo::absolute('/sitemap.xml');
        }

        $this->headers('text/plain');
        echo implode("\n", $lines) . "\n";
    }

    private function headers(string $type): void
    {
        // session_start() memasang header anti-cache, ganti dengan cache pendek.
        header_remove('Expires');
        header_remove('Pragma');
        header('Content-Type: ' . $type . '; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
    }
}
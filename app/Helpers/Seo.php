<?php

namespace App\Helpers;

use App\Core\Request;

class Seo
{
    /** Halaman yang tidak boleh diindeks. */
    private const PRIVATE_PATHS = [
        '/admin', '/akun', '/keranjang', '/checkout', '/pesanan', '/pembayaran', '/login', '/register',
    ];

    private static array $data = [];
    private static array $schemas = [];

    /**
     * Kunci: canonical, prev, next, noindex, type, image, og (array property => nilai).
     */
    public static function set(array $data): void
    {
        self::$data = array_merge(self::$data, $data);
    }

    /**
     * Tambah satu objek JSON-LD (tanpa @context).
     */
    public static function schema(array $schema): void
    {
        self::$schemas[] = $schema;
    }

    public static function base(): string
    {
        $base = rtrim((string) env('APP_URL', ''), '/');
        if ($base !== '') {
            return $base;
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $host)) {
            $host = 'localhost';
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        return ($https ? 'https' : 'http') . '://' . $host;
    }

    public static function absolute(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return self::base() . '/' . ltrim($path, '/');
    }

    /**
     * Teks polos dari HTML, dipotong ke $max karakter.
     */
    public static function plain(?string $html, int $max): string
    {
        $spaced = (string) preg_replace('#</(p|li|h[1-6]|blockquote|pre)>|<br\s*/?>#i', ' ', (string) $html);
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($spaced), ENT_QUOTES, 'UTF-8')));

        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
    }

    /**
     * @param array<int, array{0: string, 1: string}> $items [[nama, path], ...]
     */
    public static function breadcrumb(array $items): array
    {
        $list = [];
        foreach (array_values($items) as $i => [$name, $path]) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $name,
                'item' => self::absolute($path),
            ];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    /**
     * Harga efektif termurah dan termahal dari variasi (memakai harga diskon yang sah).
     *
     * @return array{0: int, 1: int}
     */
    public static function priceRange(array $variants): array
    {
        $prices = [];
        foreach ($variants as $v) {
            $sale = $v['discount_price'] !== null && $v['discount_price'] > 0 && $v['discount_price'] < $v['price'];
            $prices[] = (int) ($sale ? $v['discount_price'] : $v['price']);
        }

        return $prices ? [min($prices), max($prices)] : [0, 0];
    }

    public static function productSchema(array $product, string $description): array
    {
        $url = self::absolute('/produk/' . rawurlencode($product['slug']));
        [$low, $high] = self::priceRange($product['variants']);

        $stock = 0;
        foreach ($product['variants'] as $v) {
            $stock += (int) $v['stock'];
        }
        $availability = $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

        if ($low !== $high) {
            $offers = [
                '@type' => 'AggregateOffer',
                'url' => $url,
                'priceCurrency' => 'IDR',
                'lowPrice' => $low,
                'highPrice' => $high,
                'offerCount' => count($product['variants']),
                'availability' => $availability,
            ];
        } else {
            $offers = [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => 'IDR',
                'price' => $low,
                'availability' => $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
            ];
        }

        $schema = [
            '@type' => 'Product',
            'name' => $product['name'],
            'url' => $url,
            'category' => $product['category_name'],
            'offers' => $offers,
        ];

        if ($description !== '') {
            $schema['description'] = $description;
        }
        if (!empty($product['images'])) {
            $schema['image'] = array_map(fn($p) => self::absolute((string) $p), array_values($product['images']));
        }
        if (count($product['variants']) === 1 && $product['variants'][0]['sku'] !== '') {
            $schema['sku'] = $product['variants'][0]['sku'];
        }

        return $schema;
    }

    /**
     * Seluruh isi <head> terkait SEO: title, description, robots, canonical, Open Graph, JSON-LD.
     */
    public static function head(
        string $title,
        string $storeName,
        string $description,
        string $logo,
        string $favicon,
        string $tagline = ''
    ): string {
        $e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $path = Request::uri();
        $isHome = $path === '/';

        if ($isHome) {
            $joined = $storeName . ' - ' . $tagline;
            $fullTitle = ($tagline !== '' && mb_strlen($joined) <= 70) ? $joined : $storeName;
            $ogTitle = $fullTitle;
        } else {
            $fullTitle = $title . ' | ' . $storeName;
            $ogTitle = $title;
        }

        $description = self::plain($description, 160);
        $noindex = !empty(self::$data['noindex']) || (int) http_response_code() >= 400 || self::isPrivate($path);

        $out = ['<title>' . $e($fullTitle) . '</title>'];

        if ($description !== '') {
            $out[] = '<meta name="description" content="' . $e($description) . '">';
        }

        if ($favicon !== '') {
            $out[] = '<link rel="icon" href="' . $e($favicon) . '">';
        }

        if ($noindex) {
            $out[] = '<meta name="robots" content="noindex,follow">';
        } else {
            $canonical = self::absolute((string) (self::$data['canonical'] ?? $path));
            $out[] = '<link rel="canonical" href="' . $e($canonical) . '">';

            foreach (['prev', 'next'] as $rel) {
                if (!empty(self::$data[$rel])) {
                    $out[] = '<link rel="' . $rel . '" href="' . $e(self::absolute((string) self::$data[$rel])) . '">';
                }
            }

            $ownImage = !empty(self::$data['image']);
            $image = $ownImage ? (string) self::$data['image'] : $logo;

            $og = [
                'og:site_name' => $storeName,
                'og:locale' => 'id_ID',
                'og:type' => (string) (self::$data['type'] ?? 'website'),
                'og:title' => $ogTitle,
                'og:url' => $canonical,
            ];
            if ($description !== '') {
                $og['og:description'] = $description;
            }
            if ($image !== '') {
                $og['og:image'] = self::absolute($image);
            }
            foreach ((array) (self::$data['og'] ?? []) as $property => $value) {
                $og[(string) $property] = (string) $value;
            }

            foreach ($og as $property => $value) {
                $out[] = '<meta property="' . $e($property) . '" content="' . $e($value) . '">';
            }
            $out[] = '<meta name="twitter:card" content="' . ($ownImage ? 'summary_large_image' : 'summary') . '">';
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
        foreach (self::$schemas as $schema) {
            $json = json_encode(['@context' => 'https://schema.org'] + $schema, $flags);
            if ($json !== false) {
                $out[] = '<script type="application/ld+json">' . $json . '</script>';
            }
        }

        return implode("\n    ", $out);
    }

    private static function isPrivate(string $path): bool
    {
        foreach (self::PRIVATE_PATHS as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
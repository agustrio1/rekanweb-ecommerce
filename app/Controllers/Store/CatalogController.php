<?php

namespace App\Controllers\Store;

use App\Models\Category;
use App\Models\Product;

class CatalogController extends StoreController
{
    private const PER_PAGE = 12;

    public function index()
    {
        $this->listing(null);
    }

    public function category($slug)
    {
        $category = Category::findBySlug((string) $slug);

        if ($category === null) {
            http_response_code(404);
            echo $this->render('store.not-found', [
                'title' => 'Kategori tidak ditemukan',
                'message' => 'Kategori yang kamu cari tidak ada atau sudah tidak aktif.',
            ]);
            return;
        }

        $this->listing($category);
    }

    private function listing(?array $category): void
    {
        $q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';

        $sort = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : 'terbaru';
        if (!in_array($sort, Product::SORTS, true)) {
            $sort = 'terbaru';
        }

        $availableOnly = ($_GET['tersedia'] ?? '') === '1';
        $page = isset($_GET['halaman']) ? (int) $_GET['halaman'] : 1;

        $result = Product::search(
            $q,
            $category['id'] ?? null,
            $sort,
            $availableOnly,
            $page,
            self::PER_PAGE
        );

        if ($category !== null) {
            $title = $category['name'];
            $metaDescription = 'Belanja ' . $category['name'] . ' dengan harga jelas dan pengiriman ke seluruh Indonesia.';
        } elseif ($q !== '') {
            $title = 'Hasil pencarian "' . $q . '"';
            $metaDescription = 'Hasil pencarian produk untuk ' . $q . '.';
        } else {
            $title = 'Semua Produk';
            $metaDescription = 'Lihat semua produk pilihan dengan harga jelas dan pengiriman ke seluruh Indonesia.';
        }

        echo $this->render('store.catalog', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'products' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'categories' => Category::active(),
            'activeCategory' => $category,
            'basePath' => $category ? '/kategori/' . $category['slug'] : '/produk',
            'q' => $q,
            'sort' => $sort,
            'availableOnly' => $availableOnly,
        ]);
    }
}
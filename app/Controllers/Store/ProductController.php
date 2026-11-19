<?php

namespace App\Controllers\Store;

use App\Helpers\Seo;
use App\Models\Product;

class ProductController extends StoreController
{
    public function show($slug)
    {
        $product = Product::findBySlug((string) $slug);

        if ($product === null) {
            http_response_code(404);
            echo $this->render('store.not-found', [
                'title' => 'Produk tidak ditemukan',
                'message' => 'Produk yang kamu cari tidak ada atau sudah tidak dijual.',
            ]);
            return;
        }

        $description = Seo::plain((string) $product['description'], 155);
        $metaDescription = $description !== ''
            ? $description
            : 'Beli ' . $product['name'] . ' dengan harga jelas dan pengiriman ke seluruh Indonesia.';

        $path = '/produk/' . $product['slug'];
        [$low] = Seo::priceRange($product['variants']);

        Seo::set([
            'type' => 'product',
            'image' => $product['images'][0] ?? null,
            'og' => [
                'product:price:amount' => (string) $low,
                'product:price:currency' => 'IDR',
            ],
        ]);
        Seo::schema(Seo::productSchema($product, Seo::plain((string) $product['description'], 500)));
        Seo::schema(Seo::breadcrumb([
            ['Beranda', '/'],
            [$product['category_name'], '/kategori/' . $product['category_slug']],
            [$product['name'], $path],
        ]));

        echo $this->render('store.product', [
            'title' => $product['name'],
            'metaDescription' => $metaDescription,
            'product' => $product,
            'related' => Product::related($product['category_id'], $product['id'], 4),
        ]);
    }
}
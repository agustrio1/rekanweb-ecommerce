<?php

namespace App\Controllers\Store;

use App\Helpers\Seo;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;

class HomeController extends StoreController
{
    public function index()
    {
        $s = Setting::many(['store_name', 'store_logo', 'store_email', 'social_instagram', 'social_facebook', 'social_tiktok']);
        $name = $s['store_name'] !== '' ? $s['store_name'] : 'RekanWeb Commerce';
        $home = Seo::base() . '/';

        $org = ['@type' => 'Organization', 'name' => $name, 'url' => $home];
        if ($s['store_logo'] !== '') {
            $org['logo'] = Seo::absolute($s['store_logo']);
        }
        if ($s['store_email'] !== '') {
            $org['email'] = $s['store_email'];
        }
        $sameAs = array_values(array_filter([$s['social_instagram'], $s['social_facebook'], $s['social_tiktok']]));
        if ($sameAs) {
            $org['sameAs'] = $sameAs;
        }
        Seo::schema($org);

        Seo::schema([
            '@type' => 'WebSite',
            'name' => $name,
            'url' => $home,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => Seo::base() . '/produk?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ]);

        echo $this->render('store.home', [
            'title' => 'Beranda',
            'categories' => Category::active(),
            'latestProducts' => Product::latest(8),
            'popularProducts' => Product::popular(4),
        ]);
    }
}
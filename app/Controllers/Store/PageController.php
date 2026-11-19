<?php

namespace App\Controllers\Store;

use App\Core\View;
use App\Models\Page;

class PageController
{
    public function show($slug)
    {
        $page = Page::findActive((string) $slug);

        if ($page === null) {
            http_response_code(404);
            echo View::render('layouts.store', [
                'title' => 'Halaman tidak ditemukan',
                'content' => View::render('store.page', ['page' => null]),
            ]);

            return;
        }

        $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $page['content'])));

        echo View::render('layouts.store', [
            'title' => $page['title'],
            'metaDescription' => $plain !== '' ? mb_substr($plain, 0, 160) : null,
            'content' => View::render('store.page', ['page' => $page]),
        ]);
    }
}
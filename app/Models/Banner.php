<?php

namespace App\Models;

use App\Core\Database;

class Banner
{
    /**
     * Banner aktif untuk homepage, urutan terkecil lebih dulu.
     */
    public static function active(): array
    {
        $pdo = Database::getInstance()->getConnection();

        return $pdo->query(
            'SELECT title, subtitle, image_path, link_url
             FROM banners
             WHERE is_active = 1
             ORDER BY sort_order ASC, id DESC
             LIMIT 8'
        )->fetchAll();
    }
}
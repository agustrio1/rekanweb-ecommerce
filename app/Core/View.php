<?php

namespace App\Core;

class View
{
    public static function render($view, $data = [])
    {
        // Ubah data array menjadi variabel (extract)
        extract($data);
        
        // Konversi notasi titik menjadi path folder (misal: 'store.home' -> 'store/home.php')
        $viewPath = __DIR__ . '/../../resources/views/' . str_replace('.', '/', $view) . '.php';
        
        if (!file_exists($viewPath)) {
            throw new \Exception("View file tidak ditemukan: {$viewPath}");
        }
        
        // Mulai output buffering, include file, lalu kembalikan sebagai string
        ob_start();
        require $viewPath;
        return ob_get_clean();
    }
}
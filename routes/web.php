<?php

use App\Core\Router;

/** @var Router $router */

// Auth Routes
$router->get('/login', 'Auth/AuthController@showLogin');
$router->post('/login', 'Auth/AuthController@login');
$router->get('/register', 'Auth/RegisterController@show');
$router->post('/register', 'Auth/RegisterController@store');
//$router->get('/logout', 'Auth/AuthController@logout'); // sementara, masih dipakai layout admin
$router->post('/logout', 'Auth/LogoutController@store');

// Storefront
$router->get('/', 'Store/HomeController@index');
$router->get('/produk', 'Store/CatalogController@index');
$router->get('/produk/{slug}', 'Store/ProductController@show');
$router->get('/kategori/{slug}', 'Store/CatalogController@category');
$router->get('/halaman/{slug}', 'Store/PageController@show');

// Keranjang
$router->get('/keranjang', 'Store/CartController@index');
$router->post('/keranjang/tambah', 'Store/CartController@add');
$router->post('/keranjang/ubah', 'Store/CartController@update');
$router->post('/keranjang/hapus', 'Store/CartController@remove');

// Akun pelanggan (wajib login)
$router->get('/akun', 'Store/AccountController@index');
$router->get('/akun/profil', 'Store/AccountController@profile');
$router->post('/akun/profil', 'Store/AccountController@updateProfile');
$router->get('/akun/password', 'Store/AccountController@password');
$router->post('/akun/password', 'Store/AccountController@updatePassword');

// Alamat pengiriman (wajib login)
$router->get('/akun/alamat', 'Store/AddressController@index');
$router->get('/akun/alamat/tambah', 'Store/AddressController@create');
$router->post('/akun/alamat/tambah', 'Store/AddressController@store');
$router->get('/akun/alamat/{id}/ubah', 'Store/AddressController@edit');
$router->post('/akun/alamat/{id}/ubah', 'Store/AddressController@update');
$router->post('/akun/alamat/{id}/utama', 'Store/AddressController@makeDefault');
$router->post('/akun/alamat/{id}/hapus', 'Store/AddressController@destroy');

// Pencarian wilayah (JSON, Biteship Maps)
$router->get('/api/wilayah', 'Store/AddressController@searchArea');

// Checkout (wajib login)
$router->get('/checkout', 'Store/CheckoutController@index');
$router->post('/checkout', 'Store/CheckoutController@store');
$router->get('/api/ongkir', 'Store/CheckoutController@rates');
$router->get('/api/pembayaran', 'Store/CheckoutController@paymentMethods');

// Detail pesanan (wajib login, hanya milik sendiri)
$router->get('/pesanan/{number}', 'Store/OrderController@show');

// Pembayaran Duitku (wajib login)
$router->post('/pesanan/{number}/bayar', 'Store/OrderController@pay');
$router->get('/pembayaran/selesai', 'Store/OrderController@finish');

// Callback Duitku (server-to-server, tanpa login/CSRF)
$router->post('/webhook/duitku', 'Webhook/DuitkuController@callback');

// Webhook Biteship (server-to-server, tanpa login/CSRF, dijaga header rahasia)
$router->post('/webhook/biteship', 'Webhook/BiteshipController@callback');

$router->get('/sitemap.xml', 'Store/SeoController@sitemap');
$router->get('/robots.txt', 'Store/SeoController@robots');
<?php

use App\Core\Router;

/** @var Router $router */

// Admin Routes
$router->get('/admin', 'Admin/DashboardController@index');

// Pesanan
$router->get('/admin/orders', 'Admin/OrderController@index');
$router->get('/admin/orders/{number}', 'Admin/OrderController@show');
$router->post('/admin/orders/{number}/shipment', 'Admin/OrderController@createShipment');
$router->post('/admin/orders/{number}/shipment/reset', 'Admin/OrderController@resetShipment');

// Kategori
$router->get('/admin/categories', 'Admin/CategoryController@index');
$router->get('/admin/categories/create', 'Admin/CategoryController@create');
$router->post('/admin/categories', 'Admin/CategoryController@store');
$router->get('/admin/categories/{id}/edit', 'Admin/CategoryController@edit');
$router->post('/admin/categories/{id}', 'Admin/CategoryController@update');
$router->post('/admin/categories/{id}/feature', 'Admin/CategoryController@feature');
$router->post('/admin/categories/{id}/toggle', 'Admin/CategoryController@toggle');
$router->post('/admin/categories/{id}/delete', 'Admin/CategoryController@destroy');

// Produk
$router->get('/admin/products', 'Admin/ProductController@index');
$router->get('/admin/products/create', 'Admin/ProductController@create');
$router->post('/admin/products', 'Admin/ProductController@store');
$router->get('/admin/products/{id}/edit', 'Admin/ProductController@edit');
$router->post('/admin/products/{id}', 'Admin/ProductController@update');
$router->post('/admin/products/{id}/toggle', 'Admin/ProductController@toggle');
$router->post('/admin/products/{id}/delete', 'Admin/ProductController@destroy');
$router->post('/admin/products/{id}/images', 'Admin/ProductController@uploadImages');
$router->post('/admin/products/{id}/images/primary', 'Admin/ProductController@primaryImage');
$router->post('/admin/products/{id}/images/delete', 'Admin/ProductController@deleteImage');

// Banner
$router->get('/admin/banners', 'Admin/BannerController@index');
$router->get('/admin/banners/create', 'Admin/BannerController@create');
$router->post('/admin/banners', 'Admin/BannerController@store');
$router->get('/admin/banners/{id}/edit', 'Admin/BannerController@edit');
$router->post('/admin/banners/{id}', 'Admin/BannerController@update');
$router->post('/admin/banners/{id}/toggle', 'Admin/BannerController@toggle');
$router->post('/admin/banners/{id}/delete', 'Admin/BannerController@destroy');

// Halaman statis
$router->get('/admin/pages', 'Admin/PageController@index');
$router->get('/admin/pages/{slug}/edit', 'Admin/PageController@edit');
$router->post('/admin/pages/{slug}', 'Admin/PageController@update');

// Pengaturan
$router->get('/admin/settings', 'Admin/SettingController@index');
$router->post('/admin/settings', 'Admin/SettingController@update');
$router->get('/admin/settings/credentials', 'Admin/CredentialController@index');
$router->post('/admin/settings/credentials', 'Admin/CredentialController@update');
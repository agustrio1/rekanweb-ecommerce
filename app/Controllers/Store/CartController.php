<?php

namespace App\Controllers\Store;

use App\Models\Cart;

class CartController extends StoreController
{
    public function index()
    {
        echo $this->render('store.cart', array_merge(
            ['title' => 'Keranjang'],
            Cart::details()
        ));
    }

    public function add()
    {
        if (!$this->csrfValid()) {
            $this->redirect($this->back('/keranjang'));
        }

        $result = Cart::add(
            (int) ($_POST['variant_id'] ?? 0),
            (int) ($_POST['qty'] ?? 1)
        );

        if ($result['ok']) {
            $this->flash('success', $result['message']);
            $this->redirect('/keranjang');
        }

        $this->flash('error', $result['message']);
        $this->redirect($this->back('/produk'));
    }

    public function update()
    {
        if (!$this->csrfValid()) {
            $this->redirect('/keranjang');
        }

        $result = Cart::updateQuantity(
            (int) ($_POST['item_id'] ?? 0),
            (int) ($_POST['qty'] ?? 0)
        );

        $this->flash($result['ok'] ? 'info' : 'error', $result['message']);
        $this->redirect('/keranjang');
    }

    public function remove()
    {
        if (!$this->csrfValid()) {
            $this->redirect('/keranjang');
        }

        $result = Cart::remove((int) ($_POST['item_id'] ?? 0));

        $this->flash($result['ok'] ? 'info' : 'error', $result['message']);
        $this->redirect('/keranjang');
    }
}
<?php

namespace App\Controllers\Store;

use App\Middleware\Auth;
use App\Models\Address;
use App\Models\Cart;
use App\Services\Biteship\ShippingService;
use App\Services\Duitku\DuitkuClient;
use App\Services\Duitku\PaymentService;
use App\Services\Order\OrderService;

class CheckoutController extends StoreController
{
    public function index()
    {
        Auth::handle();

        $cart = Cart::details();

        if (!$cart['canCheckout']) {
            $this->flash('error', 'Keranjang belum siap di-checkout. Periksa stok atau hapus produk yang tidak tersedia.');
            $this->redirect('/keranjang');
        }

        $addresses = Address::forUser($this->userId());

        if (empty($addresses)) {
            $this->flash('info', 'Tambahkan alamat pengiriman terlebih dahulu.');
            $this->redirect('/akun/alamat/tambah?return=checkout');
        }

        $list = [];
        $defaultId = 0;

        foreach ($addresses as $a) {
            $hasArea = (string) $a['area_id'] !== '';

            if ($defaultId === 0 && $hasArea) {
                $defaultId = (int) $a['id']; // alamat pertama yang valid (utama ada di urutan teratas)
            }

            $list[] = [
                'id' => (int) $a['id'],
                'recipient_name' => $a['recipient_name'],
                'phone' => $a['phone'],
                'address_detail' => $a['address_detail'],
                'district' => $a['district'],
                'city' => $a['city'],
                'province' => $a['province'],
                'postal_code' => $a['postal_code'],
                'is_default' => (int) $a['is_default'] === 1,
                'has_area' => $hasArea,
            ];
        }

        echo $this->render('store.checkout', array_merge($cart, [
            'title' => 'Checkout',
            'addresses' => $list,
            'defaultId' => $defaultId,
        ]));
    }

    /**
     * Buat pesanan dari keranjang, lalu langsung buat instruksi pembayaran
     * sesuai metode yang dipilih (nomor VA dll tampil di halaman pesanan).
     */
    public function store()
    {
        Auth::handle();

        if (!$this->csrfValid()) {
            $this->redirect('/checkout');
        }

        $key = $_POST['shipping_key'] ?? '';
        $method = $_POST['payment_method'] ?? '';

        $result = OrderService::place(
            $this->userId(),
            (int) ($_POST['address_id'] ?? 0),
            is_string($key) ? $key : '',
            (int) ($_POST['seen_shipping'] ?? 0),
            is_string($method) ? $method : ''
        );

        if (!$result['ok']) {
            $this->flash('error', $result['message']);
            $this->redirect($result['redirect']);
        }

        $payment = (new PaymentService())->createForOrder($result['order_number'], $this->userId());

        if ($payment['ok']) {
            $this->flash('success', 'Pesanan berhasil dibuat. Selesaikan pembayaran sebelum batas waktu.');
        } else {
            $this->flash('error', 'Pesanan sudah dibuat, tapi instruksi pembayaran belum bisa dimuat. Klik "Coba lagi" di bawah.');
        }

        $this->redirect('/pesanan/' . rawurlencode($result['order_number']));
    }

    /**
     * Endpoint JSON: daftar kurir dan tarif untuk satu alamat milik pelanggan.
     */
    public function rates()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');

        if (empty($_SESSION['user']['id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Silakan masuk terlebih dahulu.']);
            return;
        }

        if (!$this->withinRateLimit('rates_rl')) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'message' => 'Terlalu banyak permintaan. Tunggu sebentar.']);
            return;
        }

        $address = Address::find((int) ($_GET['address_id'] ?? 0), $this->userId());
        if ($address === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Alamat tidak ditemukan.']);
            return;
        }

        $cart = Cart::details();
        if (!$cart['canCheckout']) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Keranjang belum siap di-checkout.']);
            return;
        }

        // Lepas kunci session sebelum memanggil Biteship.
        session_write_close();

        $result = ShippingService::quote($cart['lines'], $address);

        if (!$result['ok']) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $result['message']], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'weight' => $result['weight'],
            'options' => $result['options'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Endpoint JSON: metode pembayaran Duitku (kode, nama, logo) untuk keranjang saat ini.
     * Fee ditanggung toko, jadi tidak dikirim ke browser.
     */
    public function paymentMethods()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');

        if (empty($_SESSION['user']['id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Silakan masuk terlebih dahulu.']);
            return;
        }

        if (!$this->withinRateLimit('pm_rl')) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'message' => 'Terlalu banyak permintaan. Tunggu sebentar.']);
            return;
        }

        $cart = Cart::details();
        if (!$cart['canCheckout']) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Keranjang belum siap di-checkout.']);
            return;
        }

        $amount = (int) $cart['subtotal'];

        // Lepas kunci session sebelum memanggil Duitku.
        session_write_close();

        $result = (new DuitkuClient())->getPaymentMethods($amount);

        if (!$result['ok']) {
            error_log('[Checkout] Gagal memuat metode pembayaran: ' . $result['error']);
            http_response_code(502);
            echo json_encode(['ok' => false, 'message' => 'Metode pembayaran gagal dimuat. Coba lagi.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $methods = array_map(fn($m) => [
            'code' => $m['code'],
            'name' => $m['name'],
            'image' => $m['image'],
        ], $result['methods']);

        echo json_encode(['ok' => true, 'methods' => $methods], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Batas 20 permintaan per menit per sesi untuk satu kunci.
     */
    private function withinRateLimit(string $key): bool
    {
        $limit = $_SESSION[$key] ?? ['t' => 0, 'n' => 0];
        if (time() - (int) $limit['t'] > 60) {
            $limit = ['t' => time(), 'n' => 0];
        }
        $limit['n']++;
        $_SESSION[$key] = $limit;

        return $limit['n'] <= 20;
    }

    private function userId(): int
    {
        return (int) $_SESSION['user']['id'];
    }
}
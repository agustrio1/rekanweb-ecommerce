<?php

namespace App\Controllers\Store;

use App\Middleware\Auth;
use App\Models\Order;
use App\Services\Duitku\PaymentService;
use App\Services\Order\OrderService;

class OrderController extends StoreController
{
    public function show($number)
    {
        Auth::handle();

        // Pesanan yang lewat batas bayar dibatalkan dulu supaya statusnya akurat.
        OrderService::releaseExpired();

        $order = Order::findForUser((string) $number, (int) $_SESSION['user']['id']);

        if ($order === null) {
            http_response_code(404);
            echo $this->render('store.not-found', [
                'title' => 'Pesanan tidak ditemukan',
                'message' => 'Pesanan yang kamu cari tidak ada atau bukan milik akun ini.',
            ]);
            return;
        }

        echo $this->render('store.order', [
            'title' => 'Pesanan ' . $order['order_number'],
            'order' => $order,
        ]);
    }

    /**
     * Coba lagi membuat instruksi pembayaran (dipakai kalau saat checkout Duitku sedang gangguan).
     */
    public function pay($number)
    {
        Auth::handle();

        $orderUrl = '/pesanan/' . rawurlencode((string) $number);

        if (!$this->csrfValid()) {
            $this->redirect($orderUrl);
            return;
        }

        OrderService::releaseExpired();

        $result = (new PaymentService())->createForOrder((string) $number, (int) $_SESSION['user']['id']);

        if ($result['ok']) {
            $this->flash('success', 'Instruksi pembayaran siap. Selesaikan pembayaran sebelum batas waktu.');
        } else {
            $this->flash('error', $result['error']);
        }

        $this->redirect($orderUrl);
    }

    /**
     * Tujuan returnUrl Duitku. Hanya mengarahkan ke halaman pesanan.
     * Status pembayaran HANYA berubah lewat callback server-to-server.
     */
    public function finish()
    {
        Auth::handle();

        $number = $_GET['merchantOrderId'] ?? '';

        if (is_string($number) && preg_match('/^[A-Za-z0-9-]{1,50}$/', $number)) {
            $this->flash('info', 'Pembayaran sedang diverifikasi. Status pesanan akan diperbarui otomatis setelah konfirmasi dari penyedia pembayaran kami terima.');
            $this->redirect('/pesanan/' . rawurlencode($number));
            return;
        }

        $this->redirect('/akun');
    }
}
<?php

namespace App\Controllers\Admin;

use App\Models\ActivityLog;
use App\Models\AdminOrder;
use App\Services\Biteship\ShipmentService;

class OrderController extends AdminController
{
    public function index()
    {
        $this->guard();

        $tab = isset($_GET['tab']) && is_string($_GET['tab']) ? $_GET['tab'] : 'all';
        if (!isset(AdminOrder::TABS[$tab])) {
            $tab = 'all';
        }

        $q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 50) : '';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = AdminOrder::paginate($tab, $q, $page);

        echo $this->render('Pesanan', 'admin.orders.index', [
            'orders' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'counts' => AdminOrder::counts(),
            'tab' => $tab,
            'q' => $q,
        ]);
    }

    public function show($number)
    {
        $this->guard();

        $detail = AdminOrder::detail((string) $number);

        if ($detail === null) {
            $this->flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('/admin/orders');
        }

        echo $this->render('Pesanan ' . $detail['order']['order_number'], 'admin.orders.show', ['d' => $detail]);
    }

    public function createShipment($number)
    {
        $this->guard();

        $url = '/admin/orders/' . rawurlencode((string) $number);

        if (!$this->csrfValid()) {
            $this->redirect($url);
        }

        $adminId = (int) $_SESSION['user']['id'];
        $result = ShipmentService::create((string) $number, $adminId);

        if (!$result['ok']) {
            $this->flash('error', $result['message']);
        } else {
            ActivityLog::record($adminId, 'create_shipment', 'order', null, 'Membuat pengiriman Biteship untuk pesanan ' . $number);

            if (!empty($result['warning'])) {
                $this->flash('info', $result['message'] . ' ' . $result['warning']);
            } else {
                $this->flash('success', $result['message']);
            }
        }

        $this->redirect($url);
    }

    public function resetShipment($number)
    {
        $this->guard();

        $url = '/admin/orders/' . rawurlencode((string) $number);

        if (!$this->csrfValid()) {
            $this->redirect($url);
        }

        $adminId = (int) $_SESSION['user']['id'];
        $result = ShipmentService::reset((string) $number, $adminId);

        if ($result['ok']) {
            ActivityLog::record($adminId, 'reset_shipment', 'order', null, 'Menyetel ulang kunci pengiriman pesanan ' . $number);
        }

        $this->flash($result['ok'] ? 'success' : 'error', $result['message']);

        $this->redirect($url);
    }
}
<?php

namespace App\Controllers\Admin;

use App\Models\AdminDashboard;

class DashboardController extends AdminController
{
    public function index()
    {
        $this->guard();

        $range = (int) ($_GET['range'] ?? 30);
        if (!in_array($range, [7, 30], true)) {
            $range = 30;
        }

        echo $this->render('Dashboard', 'admin.dashboard', [
            'stats' => AdminDashboard::stats(),
            'series' => AdminDashboard::salesSeries($range),
            'range' => $range,
            'topProducts' => AdminDashboard::topProducts(5),
            'lowStock' => AdminDashboard::lowStock(6),
            'recentOrders' => AdminDashboard::recentOrders(5),
        ]);
    }
}
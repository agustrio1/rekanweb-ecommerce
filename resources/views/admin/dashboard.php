<?php
use App\Models\AdminDashboard;

$money = fn($n): string => 'Rp ' . number_format((int) $n, 0, ',', '.');
$num = fn($n): string => number_format((int) $n, 0, ',', '.');

$months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$dayLabel = function (string $date) use ($months): string {
    $t = strtotime($date);

    return (int) date('j', $t) . ' ' . $months[(int) date('n', $t) - 1];
};

$paths = [
    'wallet'  => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
    'bag'     => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'clock'   => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'package' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
    'truck'   => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
    'cog'     => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M12 22V12"/>',
    'alert'   => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'arrow'   => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    'inbox'   => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
];
$ico = fn(string $name, string $class = 'h-4 w-4'): string =>
    '<svg class="' . $class . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';

$cards = [
    ['Total penjualan', $money($stats['total_sales']), 'Dari pesanan lunas, tanpa yang dibatalkan', 'wallet', 'yellow', null],
    ['Jumlah pesanan', $num($stats['total_orders']), 'Semua pesanan yang pernah masuk', 'bag', 'zinc', '/admin/orders'],
    ['Perlu diproses', $num($stats['to_process']), 'Sudah dibayar, siap dikirim', 'package', $stats['to_process'] > 0 ? 'yellow' : 'zinc', '/admin/orders?tab=to_process'],
    ['Menunggu pembayaran', $num($stats['pending_payment']), 'Belum dibayar pelanggan', 'clock', 'zinc', null],
    ['Sedang diproses', $num($stats['processing']), 'Pengiriman sudah dibuat', 'cog', 'zinc', null],
    ['Terkirim', $num($stats['shipped']), 'Dalam perjalanan ke pelanggan', 'truck', 'zinc', null],
];

$card = 'rounded-xl border border-zinc-200 bg-white shadow-sm';
$badge = 'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold';

// Grafik
$periodTotal = array_sum(array_column($series, 'total'));
$periodCount = array_sum(array_column($series, 'count'));
$maxTotal = max(1, ...array_column($series, 'total'));
$lastIndex = count($series) - 1;
$labelAt = array_unique([0, (int) floor($lastIndex / 2), $lastIndex]);
$summaryJs = e((string) json_encode([
    'label' => $range . ' hari terakhir',
    'total' => $money($periodTotal),
    'count' => $periodCount . ' pesanan',
], JSON_UNESCAPED_UNICODE));

$rangeLink = fn(int $r, string $active): string =>
    'inline-flex h-8 items-center rounded-md px-3 text-xs font-medium transition-colors '
    . ($range === $r ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100');
?>
<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Dashboard</h2>
        <p class="mt-1 text-sm text-zinc-500">Ringkasan toko dan aktivitas pesanan terbaru.</p>
    </div>

    <?php if ($stats['needs_refund'] > 0): ?>
        <a href="/admin/orders" class="flex items-center gap-4 rounded-xl border border-rose-200 bg-rose-50 p-4 transition-colors hover:border-rose-300">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600"><?= $ico('alert', 'h-5 w-5') ?></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-rose-900"><?= $num($stats['needs_refund']) ?> pesanan perlu refund</span>
                <span class="block text-xs text-rose-800">Dibatalkan setelah dibayar. Lakukan refund manual lewat dashboard Duitku.</span>
            </span>
            <span class="shrink-0 text-rose-500"><?= $ico('arrow') ?></span>
        </a>
    <?php endif; ?>

    <!-- Kartu statistik -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($cards as [$label, $value, $hint, $icon, $tone, $href]): ?>
            <?php $tag = $href ? 'a' : 'div'; ?>
            <<?= $tag ?> <?= $href ? 'href="' . e($href) . '"' : '' ?> class="<?= $card ?> p-5 <?= $href ? 'transition-colors hover:border-zinc-300' : '' ?>">
                <div class="flex items-start justify-between gap-4">
                    <p class="text-sm font-medium text-zinc-600"><?= e($label) ?></p>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg <?= $tone === 'yellow' ? 'bg-yellow-100 text-yellow-700' : 'bg-zinc-100 text-zinc-500' ?>"><?= $ico($icon) ?></span>
                </div>
                <p class="mt-3 truncate text-2xl font-bold tracking-tight text-zinc-900"><?= e($value) ?></p>
                <p class="mt-1 text-xs text-zinc-500"><?= e($hint) ?></p>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </div>

    <!-- Grafik penjualan -->
    <section class="<?= $card ?> p-5 sm:p-6" x-data="{ summary: <?= $summaryJs ?>, tip: null, get shown() { return this.tip ?? this.summary; } }">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-zinc-900">Penjualan</h3>
                <p class="mt-0.5 text-sm text-zinc-500" x-text="shown.label"></p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-zinc-900" x-text="shown.total"></p>
                <p class="text-xs text-zinc-500" x-text="shown.count"></p>
            </div>
            <div class="inline-flex shrink-0 gap-1 self-start rounded-lg border border-zinc-200 bg-white p-1" role="group" aria-label="Rentang waktu">
                <a href="/admin?range=7" class="<?= $rangeLink(7, '') ?>" <?= $range === 7 ? 'aria-current="true"' : '' ?>>7 hari</a>
                <a href="/admin" class="<?= $rangeLink(30, '') ?>" <?= $range === 30 ? 'aria-current="true"' : '' ?>>30 hari</a>
            </div>
        </div>

        <?php if ($periodTotal === 0): ?>
            <div class="mt-6 flex h-48 flex-col items-center justify-center rounded-lg border border-dashed border-zinc-200 text-center">
                <p class="text-sm font-medium text-zinc-900">Belum ada penjualan</p>
                <p class="mt-1 text-xs text-zinc-500">Grafik muncul setelah ada pesanan lunas dalam <?= (int) $range ?> hari terakhir.</p>
            </div>
        <?php else: ?>
            <div class="mt-6" @mouseleave="tip = null">
                <div class="flex h-48 items-end gap-0.5 border-b border-zinc-200 sm:gap-1">
                    <?php foreach ($series as $point): ?>
                        <?php
                        $pct = $point['total'] > 0 ? max(4, (int) round($point['total'] / $maxTotal * 100)) : 0;
                        $tipJs = e((string) json_encode([
                            'label' => $dayLabel($point['date']),
                            'total' => $money($point['total']),
                            'count' => $point['count'] . ' pesanan',
                        ], JSON_UNESCAPED_UNICODE));
                        ?>
                        <button
                            type="button"
                            @mouseenter="tip = <?= $tipJs ?>"
                            @focus="tip = <?= $tipJs ?>"
                            @click="tip = <?= $tipJs ?>"
                            aria-label="<?= e($dayLabel($point['date']) . ': ' . $money($point['total'])) ?>"
                            class="group flex h-full min-w-0 flex-1 items-end focus-visible:outline-none"
                        >
                            <?php if ($pct > 0): ?>
                                <span class="block w-full rounded-t-sm bg-yellow-400 transition-colors group-hover:bg-yellow-500 group-focus-visible:bg-yellow-500" style="height: <?= $pct ?>%"></span>
                            <?php else: ?>
                                <span class="block w-full rounded-t-sm bg-zinc-100" style="height: 2px"></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2 flex justify-between text-xs text-zinc-500">
                    <?php foreach ($labelAt as $i): ?>
                        <span><?= e($dayLabel($series[$i]['date'])) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <!-- Produk terlaris -->
        <section class="<?= $card ?>">
            <div class="px-5 pt-5 sm:px-6">
                <h3 class="text-base font-semibold text-zinc-900">Produk terlaris</h3>
                <p class="mt-0.5 text-sm text-zinc-500">Berdasarkan jumlah terjual dari pesanan lunas.</p>
            </div>
            <?php if (empty($topProducts)): ?>
                <div class="px-6 py-12 text-center">
                    <p class="text-sm font-medium text-zinc-900">Belum ada data</p>
                    <p class="mt-1 text-sm text-zinc-500">Muncul setelah ada pesanan lunas.</p>
                </div>
            <?php else: ?>
                <ol class="mt-4 divide-y divide-zinc-100 border-t border-zinc-100">
                    <?php foreach ($topProducts as $i => $t): ?>
                        <li class="flex items-center gap-4 px-5 py-3.5 sm:px-6">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold <?= $i === 0 ? 'bg-yellow-400 text-zinc-900' : 'bg-zinc-100 text-zinc-600' ?>"><?= $i + 1 ?></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900"><?= e($t['name']) ?></p>
                                <p class="text-xs text-zinc-500"><?= e($money($t['revenue'])) ?></p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold text-zinc-900"><?= $num($t['qty']) ?> <span class="font-normal text-zinc-500">terjual</span></p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <!-- Stok menipis -->
        <section class="<?= $card ?>">
            <div class="flex items-start justify-between gap-3 px-5 pt-5 sm:px-6">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900">Stok menipis</h3>
                    <p class="mt-0.5 text-sm text-zinc-500">Variasi aktif dengan stok <?= AdminDashboard::LOW_STOCK ?> atau kurang.</p>
                </div>
                <?php if ($lowStock['total'] > 0): ?>
                    <span class="<?= $badge ?> border-yellow-300 bg-yellow-50 text-yellow-800"><?= $num($lowStock['total']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (empty($lowStock['rows'])): ?>
                <div class="px-6 py-12 text-center">
                    <p class="text-sm font-medium text-zinc-900">Stok aman</p>
                    <p class="mt-1 text-sm text-zinc-500">Tidak ada variasi yang hampir habis.</p>
                </div>
            <?php else: ?>
                <ul class="mt-4 divide-y divide-zinc-100 border-t border-zinc-100">
                    <?php foreach ($lowStock['rows'] as $s): ?>
                        <li>
                            <a href="/admin/products/<?= (int) $s['product_id'] ?>/edit" class="flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-zinc-50 sm:px-6">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-zinc-900"><?= e($s['name']) ?></p>
                                    <p class="truncate text-xs text-zinc-500"><?= e($s['variant_name']) ?> &middot; <?= e($s['sku']) ?></p>
                                </div>
                                <?php if ($s['stock'] === 0): ?>
                                    <span class="<?= $badge ?> shrink-0 border-rose-200 bg-rose-50 text-rose-700">Habis</span>
                                <?php else: ?>
                                    <span class="<?= $badge ?> shrink-0 border-yellow-300 bg-yellow-50 text-yellow-800"><?= (int) $s['stock'] ?> tersisa</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($lowStock['total'] > count($lowStock['rows'])): ?>
                    <p class="border-t border-zinc-100 px-5 py-3 text-xs text-zinc-500 sm:px-6">dan <?= $num($lowStock['total'] - count($lowStock['rows'])) ?> variasi lainnya.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>

    <!-- Pesanan terbaru -->
    <section class="<?= $card ?>">
        <div class="flex items-center justify-between gap-4 px-5 py-4 sm:px-6">
            <div>
                <h3 class="text-base font-semibold text-zinc-900">Pesanan terbaru</h3>
                <p class="mt-0.5 text-sm text-zinc-500">Transaksi terakhir yang masuk ke toko.</p>
            </div>
            <a href="/admin/orders" class="inline-flex h-9 shrink-0 items-center rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900 shadow-sm transition-colors hover:bg-zinc-50">Lihat semua</a>
        </div>

        <?php if (empty($recentOrders)): ?>
            <div class="flex flex-col items-center border-t border-zinc-200 px-6 py-14 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><?= $ico('inbox', 'h-5 w-5') ?></span>
                <p class="mt-4 text-sm font-medium text-zinc-900">Belum ada pesanan</p>
                <p class="mt-1 max-w-xs text-sm text-zinc-500">Pesanan dari pelanggan akan muncul di sini setelah checkout.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto border-t border-zinc-200">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 bg-zinc-50/60">
                            <th class="h-10 px-5 text-left text-xs font-medium text-zinc-500 sm:px-6">Pesanan</th>
                            <th class="h-10 px-4 text-left text-xs font-medium text-zinc-500">Total</th>
                            <th class="h-10 px-4 text-left text-xs font-medium text-zinc-500">Pembayaran</th>
                            <th class="hidden h-10 px-4 text-left text-xs font-medium text-zinc-500 sm:table-cell">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        <?php foreach ($recentOrders as $order): ?>
                            <?php
                            $ps = (string) $order['payment_status'];
                            $statusClass = match ($ps) {
                                'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                'pending' => 'border-yellow-300 bg-yellow-50 text-yellow-800',
                                'failed', 'expired' => 'border-rose-200 bg-rose-50 text-rose-700',
                                default => 'border-zinc-200 bg-zinc-50 text-zinc-700',
                            };
                            $statusLabel = match ($ps) {
                                'paid' => 'Lunas',
                                'pending' => 'Menunggu',
                                'failed' => 'Gagal',
                                'expired' => 'Kedaluwarsa',
                                'refunded' => 'Dikembalikan',
                                default => ucfirst($ps),
                            };
                            ?>
                            <tr class="transition-colors hover:bg-zinc-50">
                                <td class="px-5 py-3.5 sm:px-6">
                                    <a href="/admin/orders/<?= e(rawurlencode($order['order_number'])) ?>" class="block whitespace-nowrap font-medium text-zinc-900 hover:underline"><?= e($order['order_number']) ?></a>
                                    <p class="max-w-[12rem] truncate text-xs text-zinc-500"><?= e($order['customer_name'] ?? '-') ?></p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-zinc-700"><?= e($money($order['total_amount'])) ?></td>
                                <td class="px-4 py-3.5"><span class="<?= $badge ?> <?= $statusClass ?>"><?= e($statusLabel) ?></span></td>
                                <td class="hidden whitespace-nowrap px-4 py-3.5 text-zinc-500 sm:table-cell"><?= e($dayLabel(date('Y-m-d', strtotime($order['created_at'])))) ?> <?= e(date('Y', strtotime($order['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
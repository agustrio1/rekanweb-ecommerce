<?php
use App\Models\AdminOrder;

$o = $d['order'];
$items = $d['items'];
$addr = $d['address'];
$ship = $d['shipment'];
$pay = $d['payment'];
$histories = $d['histories'];
$callbacks = $d['callbacks'];

$money = fn($n): string => 'Rp ' . number_format((int) $n, 0, ',', '.');
[$label, $badge] = AdminOrder::badge($o);

$paths = [
    'back'     => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
    'copy'     => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
    'check'    => '<path d="M20 6 9 17l-5-5"/>',
    'package'  => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
    'truck'    => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
    'card'     => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    'pin'      => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
    'user'     => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'alert'    => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'history'  => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
    'chevdown' => '<path d="m6 9 6 6 6-6"/>',
    'ban'      => '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
];
$ico = fn(string $name, string $class = 'h-4 w-4'): string =>
    '<svg class="' . $class . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';

$copy = function (string $text) use ($ico): string {
    $js = e((string) json_encode($text, JSON_UNESCAPED_SLASHES));

    return '<button type="button" x-data="{ copied: false }" '
        . '@click="navigator.clipboard.writeText(' . $js . ').then(() => { copied = true; setTimeout(() => copied = false, 1800) })" '
        . 'class="inline-flex h-7 shrink-0 items-center rounded-md border border-zinc-200 bg-white px-2 text-xs font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900">'
        . '<span x-show="!copied" class="inline-flex items-center gap-1">' . $ico('copy', 'h-3.5 w-3.5') . 'Salin</span>'
        . '<span x-show="copied" x-cloak class="inline-flex items-center gap-1 text-emerald-600">' . $ico('check', 'h-3.5 w-3.5') . 'Tersalin</span>'
        . '</button>';
};

$number = rawurlencode($o['order_number']);
$paid = $o['payment_status'] === 'paid';
$cancelled = $o['order_status'] === 'cancelled';
$refund = $cancelled && $paid;
$hasBiteship = $ship && !empty($ship['biteship_order_id']);
$canCreate = $paid && $o['order_status'] === 'pending' && $ship && !$hasBiteship && $ship['status'] === 'pending';
$creating = $ship && !$hasBiteship && $ship['status'] === 'creating';
$biteshipPrice = (int) ($ship['tracking']['biteship_price'] ?? 0);
$itemCount = array_sum(array_map(fn($i) => (int) $i['quantity'], $items));

// Progres pesanan
$reached = 1;
if ($paid) {
    $reached = 2;
}
if (in_array($o['order_status'], ['processing', 'shipped', 'completed'], true)) {
    $reached = 3;
}
if (in_array($o['order_status'], ['shipped', 'completed'], true)) {
    $reached = 4;
}
if ($o['order_status'] === 'completed') {
    $reached = 5;
}
$steps = ['Dibuat', 'Dibayar', 'Diproses', 'Dikirim', 'Selesai'];

$payMap = [
    'paid'     => ['Lunas', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
    'pending'  => ['Menunggu', 'border-yellow-300 bg-yellow-50 text-yellow-800'],
    'failed'   => ['Gagal', 'border-rose-200 bg-rose-50 text-rose-700'],
    'expired'  => ['Kedaluwarsa', 'border-rose-200 bg-rose-50 text-rose-700'],
    'refunded' => ['Dikembalikan', 'border-zinc-200 bg-zinc-50 text-zinc-700'],
];
$shipMap = [
    'pending'   => 'Belum dibuat',
    'creating'  => 'Terkunci, hasil belum jelas',
    'cancelled' => 'Dibatalkan',
];

$card = 'rounded-2xl border border-zinc-200 bg-white shadow-sm';
$cardTitle = 'flex items-center gap-2 text-base font-semibold text-zinc-900';
?>
<div class="space-y-5">
    <!-- Header -->
    <div>
        <a href="/admin/orders" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-900">
            <?= $ico('back') ?> Semua pesanan
        </a>

        <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <h2 class="break-all text-2xl font-bold tracking-tight text-zinc-900"><?= e($o['order_number']) ?></h2>
                    <?= $copy($o['order_number']) ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium <?= $badge ?>">
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span><?= e($label) ?>
                    </span>
                </div>
                <p class="mt-1.5 text-sm text-zinc-500">Dibuat <?= e(AdminOrder::date($o['created_at'], true)) ?> WIB &middot; <?= (int) $itemCount ?> barang</p>
            </div>
            <div class="sm:text-right">
                <p class="text-xs font-medium text-zinc-500">Total pesanan</p>
                <p class="text-2xl font-bold tracking-tight text-zinc-900"><?= e($money($o['total_amount'])) ?></p>
            </div>
        </div>
    </div>

    <!-- Banner langkah berikutnya -->
    <?php if ($cancelled): ?>
        <div class="flex flex-col gap-4 rounded-2xl border p-4 sm:flex-row sm:items-center sm:p-5 <?= $refund ? 'border-rose-200 bg-rose-50' : 'border-zinc-200 bg-zinc-100/70' ?>">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full <?= $refund ? 'bg-rose-100 text-rose-600' : 'bg-zinc-200 text-zinc-600' ?>"><?= $ico($refund ? 'alert' : 'ban', 'h-5 w-5') ?></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold <?= $refund ? 'text-rose-900' : 'text-zinc-900' ?>"><?= $refund ? 'Perlu refund' : 'Pesanan dibatalkan' ?></p>
                <p class="mt-0.5 text-sm <?= $refund ? 'text-rose-800' : 'text-zinc-600' ?>">
                    <?php if ($refund): ?>
                        Pesanan dibatalkan, tetapi pembayaran <?= e($money($o['total_amount'])) ?> sudah diterima. Lakukan refund manual lewat dashboard Duitku.
                    <?php elseif ($o['payment_status'] === 'expired'): ?>
                        Batas waktu pembayaran habis dan stok sudah dikembalikan.
                    <?php else: ?>
                        Pesanan ini sudah dibatalkan dan tidak bisa diproses.
                    <?php endif; ?>
                </p>
            </div>
        </div>

    <?php elseif ($hasBiteship): ?>
        <div class="flex flex-col gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="flex items-center gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><?= $ico('truck', 'h-5 w-5') ?></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-emerald-900">Pengiriman sudah dibuat</p>
                    <p class="mt-0.5 text-sm text-emerald-800">
                        <?php if (!empty($ship['waybill_id'])): ?>
                            Nomor resi <span class="font-semibold"><?= e($ship['waybill_id']) ?></span>
                        <?php else: ?>
                            Nomor resi muncul setelah kurir mengonfirmasi.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if (!empty($ship['waybill_id'])): ?>
                <div class="shrink-0"><?= $copy($ship['waybill_id']) ?></div>
            <?php endif; ?>
        </div>

    <?php elseif ($creating): ?>
        <div class="flex flex-col gap-4 rounded-2xl border border-yellow-300 bg-yellow-50 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-yellow-100 text-yellow-700"><?= $ico('alert', 'h-5 w-5') ?></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-yellow-900">Pembuatan pengiriman terkunci</p>
                    <p class="mt-0.5 text-sm text-yellow-800">Hasilnya belum jelas. Cek dulu dashboard Biteship. Kalau order untuk <?= e($o['order_number']) ?> belum ada di sana, setel ulang lalu coba lagi.</p>
                </div>
            </div>
            <form method="POST" action="/admin/orders/<?= e($number) ?>/shipment/reset" class="shrink-0"
                  x-data="{ busy: false }"
                  @submit="if (busy || !confirm('Yakin order ini belum ada di dashboard Biteship?')) { $event.preventDefault(); return; } busy = true">
                <?= csrf_field() ?>
                <button type="submit" :disabled="busy" class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-yellow-400 bg-white px-5 text-sm font-semibold text-zinc-900 hover:bg-yellow-100 disabled:opacity-60 sm:w-auto">Setel ulang</button>
            </form>
        </div>

    <?php elseif ($canCreate): ?>
        <div class="flex flex-col gap-4 rounded-2xl border border-yellow-300 bg-gradient-to-r from-yellow-50 to-white p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="flex items-center gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-yellow-400 text-zinc-900"><?= $ico('truck', 'h-5 w-5') ?></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-zinc-900">Siap dikirim</p>
                    <p class="mt-0.5 text-sm text-zinc-600">Pembayaran sudah lunas. Buat order kurir di Biteship untuk pesanan ini.</p>
                </div>
            </div>
            <form method="POST" action="/admin/orders/<?= e($number) ?>/shipment" class="shrink-0"
                  x-data="{ busy: false }"
                  @submit="if (busy || !confirm('Buat pengiriman di Biteship sekarang? Ini membuat order kurir sungguhan.')) { $event.preventDefault(); return; } busy = true">
                <?= csrf_field() ?>
                <button type="submit" :disabled="busy" class="inline-flex h-12 w-full items-center justify-center rounded-xl bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-zinc-200 disabled:text-zinc-500 sm:w-auto">
                    <span x-text="busy ? 'Memproses...' : 'Buat pengiriman'">Buat pengiriman</span>
                </button>
            </form>
        </div>

    <?php elseif (!$paid): ?>
        <div class="flex items-center gap-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-5">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-500"><?= $ico('clock', 'h-5 w-5') ?></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-zinc-900">Menunggu pembayaran pelanggan</p>
                <p class="mt-0.5 text-sm text-zinc-600">Batas bayar <?= e(AdminOrder::date($o['expires_at'], true)) ?> WIB. Pengiriman baru bisa dibuat setelah lunas.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Progres -->
    <?php if (!$cancelled): ?>
        <div class="<?= $card ?> p-4 sm:p-6">
            <ol class="grid grid-cols-5">
                <?php foreach ($steps as $i => $stepLabel): ?>
                    <?php
                    $n = $i + 1;
                    $done = $n <= $reached;
                    $current = !$done && $n === $reached + 1;
                    ?>
                    <li class="relative flex flex-col items-center text-center">
                        <?php if ($n < count($steps)): ?>
                            <span class="absolute left-1/2 top-4 h-0.5 w-full <?= $n < $reached ? 'bg-emerald-500' : 'bg-zinc-200' ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold <?= $done ? 'bg-emerald-500 text-white' : ($current ? 'border-2 border-yellow-400 bg-white text-zinc-900 ring-4 ring-yellow-100' : 'border border-zinc-200 bg-white text-zinc-400') ?>">
                            <?= $done ? $ico('check', 'h-4 w-4') : (int) $n ?>
                        </span>
                        <span class="mt-2 text-[11px] font-medium sm:text-xs <?= ($done || $current) ? 'text-zinc-900' : 'text-zinc-400' ?>"><?= e($stepLabel) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endif; ?>

    <div class="grid gap-5 lg:grid-cols-3">
        <!-- Kolom kiri -->
        <div class="space-y-5 lg:col-span-2">
            <!-- Barang -->
            <section class="<?= $card ?>">
                <h3 class="<?= $cardTitle ?> px-5 pt-5 sm:px-6"><?= $ico('package', 'h-5 w-5 text-zinc-400') ?> Barang</h3>
                <ul class="mt-4 divide-y divide-zinc-100 border-t border-zinc-100">
                    <?php foreach ($items as $item): ?>
                        <li class="flex items-start gap-4 px-5 py-4 sm:px-6">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-400"><?= $ico('package', 'h-5 w-5') ?></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-zinc-900"><?= e($item['product_name_snapshot']) ?></p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500">
                                    <span class="rounded-md bg-zinc-100 px-1.5 py-0.5 font-medium text-zinc-600"><?= e($item['variant_name_snapshot']) ?></span>
                                    <span><?= e($item['sku_snapshot']) ?></span>
                                    <span><?= e((string) (int) $item['weight_gram_snapshot']) ?> g</span>
                                </div>
                                <p class="mt-1.5 text-xs text-zinc-500"><?= e($money($item['price_snapshot'])) ?> &times; <?= (int) $item['quantity'] ?></p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold text-zinc-900"><?= e($money((int) $item['price_snapshot'] * (int) $item['quantity'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <dl class="space-y-2 rounded-b-2xl border-t border-zinc-100 bg-zinc-50/60 px-5 py-4 text-sm sm:px-6">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Subtotal</dt><dd class="font-medium text-zinc-900"><?= e($money($o['subtotal'])) ?></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">Ongkos kirim</dt><dd class="font-medium text-zinc-900"><?= e($money($o['shipping_cost'])) ?></dd></div>
                    <div class="flex justify-between gap-4 border-t border-zinc-200 pt-2"><dt class="font-semibold text-zinc-900">Total</dt><dd class="text-base font-bold text-zinc-900"><?= e($money($o['total_amount'])) ?></dd></div>
                </dl>
            </section>

            <!-- Pelanggan dan alamat -->
            <section class="<?= $card ?> p-5 sm:p-6">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <h3 class="<?= $cardTitle ?>"><?= $ico('user', 'h-5 w-5 text-zinc-400') ?> Pelanggan</h3>
                        <div class="mt-3 flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-600"><?= e(mb_strtoupper(mb_substr(trim((string) ($o['customer_name'] ?? '')) ?: '?', 0, 1))) ?></span>
                            <div class="min-w-0 text-sm">
                                <p class="truncate font-medium text-zinc-900"><?= e($o['customer_name'] ?? '-') ?></p>
                                <p class="truncate text-zinc-500"><?= e($o['customer_email'] ?? '') ?></p>
                                <p class="truncate text-zinc-500"><?= e($o['customer_phone'] ?? '') ?></p>
                            </div>
                        </div>
                    </div>

                    <?php if ($addr): ?>
                        <div>
                            <h3 class="<?= $cardTitle ?>"><?= $ico('pin', 'h-5 w-5 text-zinc-400') ?> Dikirim ke</h3>
                            <div class="mt-3 text-sm">
                                <p class="font-medium text-zinc-900"><?= e($addr['recipient_name']) ?> <span class="font-normal text-zinc-500">&middot; <?= e($addr['phone']) ?></span></p>
                                <p class="mt-1 leading-relaxed text-zinc-600"><?= nl2br(e($addr['address_detail'])) ?></p>
                                <p class="mt-0.5 text-zinc-500"><?= e($addr['district']) ?>, <?= e($addr['city']) ?>, <?= e($addr['province']) ?> <?= e($addr['postal_code']) ?></p>
                                <?php if (!empty($addr['notes'])): ?>
                                    <p class="mt-2 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-600">Catatan: <?= e($addr['notes']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Kolom kanan -->
        <aside class="space-y-5 lg:col-span-1">
            <!-- Pembayaran -->
            <section class="<?= $card ?> p-5 sm:p-6">
                <h3 class="<?= $cardTitle ?>"><?= $ico('card', 'h-5 w-5 text-zinc-400') ?> Pembayaran</h3>
                <?php if ($pay): ?>
                    <?php [$payLabel, $payBadge] = $payMap[$pay['status']] ?? [ucfirst((string) $pay['status']), 'border-zinc-200 bg-zinc-50 text-zinc-700']; ?>
                    <div class="mt-4 flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <?php if (!empty($pay['payment_image'])): ?>
                                <span class="flex h-9 w-14 shrink-0 items-center justify-center rounded-md border border-zinc-100 bg-white p-1">
                                    <img src="<?= e($pay['payment_image']) ?>" alt="<?= e($pay['payment_name'] ?? '') ?>" loading="lazy" referrerpolicy="no-referrer" class="max-h-full max-w-full object-contain">
                                </span>
                            <?php endif; ?>
                            <p class="truncate text-sm font-medium text-zinc-900"><?= e($pay['payment_name'] ?: ($pay['payment_method'] ?: '-')) ?></p>
                        </div>
                        <span class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-xs font-medium <?= $payBadge ?>"><?= e($payLabel) ?></span>
                    </div>

                    <dl class="mt-4 space-y-3 border-t border-zinc-100 pt-4 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-zinc-500">Nominal</dt><dd class="font-semibold text-zinc-900"><?= e($money($pay['amount'])) ?></dd></div>
                        <?php if (!empty($pay['va_number'])): ?>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-zinc-500">No. VA</dt>
                                <dd class="flex min-w-0 items-center gap-2"><span class="break-all font-medium text-zinc-900"><?= e($pay['va_number']) ?></span><?= $copy($pay['va_number']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($pay['gateway_transaction_id'])): ?>
                            <div class="flex justify-between gap-4"><dt class="shrink-0 text-zinc-500">Referensi</dt><dd class="break-all text-right text-zinc-900"><?= e($pay['gateway_transaction_id']) ?></dd></div>
                        <?php endif; ?>
                        <div class="flex justify-between gap-4"><dt class="text-zinc-500">Dibayar</dt><dd class="text-right text-zinc-900"><?= e(AdminOrder::date($pay['paid_at'], true)) ?></dd></div>
                    </dl>
                <?php else: ?>
                    <p class="mt-3 text-sm text-zinc-500">Belum ada transaksi pembayaran.</p>
                <?php endif; ?>
            </section>

            <!-- Pengiriman -->
            <section class="<?= $card ?> p-5 sm:p-6">
                <h3 class="<?= $cardTitle ?>"><?= $ico('truck', 'h-5 w-5 text-zinc-400') ?> Pengiriman</h3>
                <?php if ($ship): ?>
                    <p class="mt-4 text-sm font-semibold text-zinc-900"><?= e(trim(($ship['courier_name'] ?? '') . ' - ' . ($ship['service_name'] ?? ''), ' -')) ?></p>
                    <?php if (!empty($ship['estimate'])): ?>
                        <p class="text-xs text-zinc-500">Estimasi <?= e($ship['estimate']) ?></p>
                    <?php endif; ?>

                    <dl class="mt-4 space-y-3 border-t border-zinc-100 pt-4 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-zinc-500">Ongkir ditagih</dt><dd class="font-medium text-zinc-900"><?= e($money($ship['shipping_price'])) ?></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-zinc-500">Status</dt><dd class="text-right font-medium text-zinc-900"><?= e($shipMap[$ship['status']] ?? ucfirst((string) $ship['status'])) ?></dd></div>
                        <?php if ($hasBiteship): ?>
                            <div class="flex justify-between gap-4"><dt class="shrink-0 text-zinc-500">ID Biteship</dt><dd class="break-all text-right text-zinc-900"><?= e($ship['biteship_order_id']) ?></dd></div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-zinc-500">No. resi</dt>
                                <dd class="flex min-w-0 items-center gap-2">
                                    <span class="break-all font-medium text-zinc-900"><?= e($ship['waybill_id'] ?: 'belum ada') ?></span>
                                    <?php if (!empty($ship['waybill_id'])): ?><?= $copy($ship['waybill_id']) ?><?php endif; ?>
                                </dd>
                            </div>
                            <?php if (!empty($ship['tracking']['tracking_id'])): ?>
                                <div class="flex justify-between gap-4"><dt class="shrink-0 text-zinc-500">Tracking ID</dt><dd class="break-all text-right text-zinc-900"><?= e($ship['tracking']['tracking_id']) ?></dd></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </dl>

                    <?php if ($hasBiteship && $biteshipPrice > 0 && $biteshipPrice !== (int) $ship['shipping_price']): ?>
                        <p class="mt-4 flex items-start gap-2 rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs text-yellow-900">
                            <?= $ico('alert', 'mt-0.5 h-3.5 w-3.5 shrink-0') ?>
                            Ongkir asli Biteship <?= e($money($biteshipPrice)) ?> berbeda dari yang ditagih ke pelanggan. Tagihan pelanggan tidak diubah.
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="mt-3 text-sm text-zinc-500">Data pengiriman tidak ditemukan.</p>
                <?php endif; ?>
            </section>

            <!-- Riwayat -->
            <section class="<?= $card ?> p-5 sm:p-6">
                <h3 class="<?= $cardTitle ?>"><?= $ico('history', 'h-5 w-5 text-zinc-400') ?> Riwayat status</h3>
                <?php if (empty($histories)): ?>
                    <p class="mt-3 text-sm text-zinc-500">Belum ada riwayat.</p>
                <?php else: ?>
                    <ol class="ml-2 mt-5 space-y-5 border-l border-zinc-200 pl-5">
                        <?php foreach ($histories as $idx => $h): ?>
                            <li class="relative text-sm">
                                <span class="absolute -left-[26px] top-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-white <?= $idx === 0 ? 'bg-yellow-400' : 'bg-zinc-300' ?>"></span>
                                <p class="font-semibold text-zinc-900">
                                    <?= e($h['new_status']) ?>
                                    <?php if (!empty($h['old_status'])): ?>
                                        <span class="font-normal text-zinc-400">dari <?= e($h['old_status']) ?></span>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($h['notes'])): ?>
                                    <p class="mt-0.5 leading-relaxed text-zinc-600"><?= e($h['notes']) ?></p>
                                <?php endif; ?>
                                <p class="mt-1 text-xs text-zinc-400"><?= e(AdminOrder::date($h['created_at'], true)) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <!-- Callback pembayaran -->
            <?php if (!empty($callbacks)): ?>
                <details class="group <?= $card ?>">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-zinc-900 sm:px-6">
                        <span>Callback pembayaran <span class="font-normal text-zinc-500">(<?= count($callbacks) ?>)</span></span>
                        <span class="text-zinc-400 transition-transform group-open:rotate-180"><?= $ico('chevdown') ?></span>
                    </summary>
                    <ul class="divide-y divide-zinc-100 border-t border-zinc-100">
                        <?php foreach ($callbacks as $c): ?>
                            <li class="px-5 py-3 text-sm sm:px-6">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-zinc-900"><?= e($c['note'] ?? '') ?></span>
                                    <span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-600"><?= e($c['result_code']) ?></span>
                                </div>
                                <p class="mt-0.5 text-xs text-zinc-400"><?= e(AdminOrder::date($c['created_at'], true)) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </aside>
    </div>
</div>
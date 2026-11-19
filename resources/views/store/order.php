<?php
$months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$formatDate = function (?string $value, bool $withTime = false) use ($months): string {
    if (!$value) {
        return '-';
    }
    $t = strtotime($value);
    $text = date('j', $t) . ' ' . $months[(int) date('n', $t)] . ' ' . date('Y', $t);

    return $withTime ? $text . ', ' . date('H:i', $t) . ' WIB' : $text;
};
$money = fn($n): string => 'Rp ' . number_format((int) $n, 0, ',', '.');

[$label, $badge] = \App\Models\Order::statusBadge($order);
$items = $order['items'];
$addr = $order['address'];
$ship = $order['shipment'];
$pay = $order['payment'] ?? null;
$tracking = $order['tracking'] ?? [];
$trackingLink = $order['tracking_link'] ?? null;

$isPending = $order['payment_status'] === 'pending' && $order['order_status'] === 'pending';
$refundCase = $order['order_status'] === 'cancelled' && $order['payment_status'] === 'paid';
$payUrl = '/pesanan/' . rawurlencode($order['order_number']) . '/bayar';
$hasInstruction = $pay && (!empty($pay['va_number']) || !empty($pay['qr_string']) || !empty($pay['payment_url']));
?>

<div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8 lg:pt-8">
    <a href="/akun" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-900">
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
        Pesanan saya
    </a>

    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl"><?= e($order['order_number']) ?></h1>
        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium <?= $badge ?>"><?= e($label) ?></span>
    </div>
    <p class="mt-1 text-sm text-zinc-500">Dibuat <?= e($formatDate($order['created_at'], true)) ?></p>

    <?php if ($refundCase): ?>
        <!-- Bayar telat: pesanan sudah batal -->
        <section class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-5" aria-labelledby="h-refund">
            <h2 id="h-refund" class="text-base font-semibold text-rose-900">Pesanan dibatalkan, pembayaran akan dikembalikan</h2>
            <p class="mt-1 text-sm leading-relaxed text-rose-800">
                Batas waktu pembayaran sudah lewat sehingga pesanan dibatalkan, tetapi pembayaranmu sebesar
                <span class="font-semibold"><?= e($money($order['total_amount'])) ?></span> sudah kami terima.
                Tim kami akan mengembalikan dana tersebut. Hubungi kami kalau kamu butuh bantuan.
            </p>
        </section>
    <?php endif; ?>

    <?php if ($isPending && $hasInstruction): ?>
        <!-- Instruksi pembayaran -->
        <section class="mt-6 rounded-xl border border-yellow-300 bg-yellow-50 p-5" aria-labelledby="h-instruksi">
            <div class="flex items-center gap-3">
                <?php if (!empty($pay['payment_image'])): ?>
                    <span class="flex h-10 w-16 shrink-0 items-center justify-center rounded-md border border-yellow-200 bg-white p-1">
                        <img src="<?= e($pay['payment_image']) ?>" alt="<?= e($pay['payment_name'] ?? '') ?>" referrerpolicy="no-referrer" class="max-h-full max-w-full object-contain">
                    </span>
                <?php endif; ?>
                <div class="min-w-0">
                    <h2 id="h-instruksi" class="text-base font-semibold text-zinc-900">Selesaikan pembayaran</h2>
                    <p class="text-sm text-zinc-700"><?= e($pay['payment_name'] ?? $pay['payment_method']) ?></p>
                </div>
            </div>

            <?php if (!empty($pay['va_number'])): ?>
                <div class="mt-4" x-data="{ copied: false, copy() { navigator.clipboard.writeText('<?= e($pay['va_number']) ?>').then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); } }">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-600">Nomor virtual account</p>
                    <div class="mt-1 flex items-center justify-between gap-3 rounded-lg border border-yellow-200 bg-white px-4 py-3">
                        <span class="break-all text-xl font-bold tracking-wider text-zinc-900"><?= e($pay['va_number']) ?></span>
                        <button type="button" @click="copy()" class="shrink-0 rounded-md border border-zinc-300 px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50" x-text="copied ? 'Tersalin' : 'Salin'">Salin</button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($pay['va_number']) && !empty($pay['payment_url'])): ?>
                <a href="<?= e($pay['payment_url']) ?>" rel="noopener" class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 hover:bg-yellow-300">Lanjutkan pembayaran</a>
            <?php endif; ?>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-600">Jumlah yang harus dibayar</dt>
                    <dd class="font-bold text-zinc-900"><?= e($money($order['total_amount'])) ?></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-600">Bayar sebelum</dt>
                    <dd class="font-semibold text-zinc-900"><?= e($formatDate($order['expires_at'], true)) ?></dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-zinc-600">Transfer tepat sesuai jumlah di atas. Status pesanan diperbarui otomatis setelah pembayaran kami terima.</p>
        </section>
    <?php endif; ?>

    <div class="mt-6 grid gap-8 lg:grid-cols-3 lg:gap-10">
        <div class="space-y-8 lg:col-span-2">
            <!-- Barang -->
            <section aria-labelledby="h-barang">
                <h2 id="h-barang" class="text-base font-semibold">Barang</h2>
                <ul class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200">
                    <?php foreach ($items as $item): ?>
                        <li class="flex items-start justify-between gap-4 p-4 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-zinc-900"><?= e($item['product_name_snapshot']) ?></p>
                                <p class="mt-0.5 text-xs text-zinc-500"><?= e($item['variant_name_snapshot']) ?> &middot; <?= e($money($item['price_snapshot'])) ?> &times; <?= (int) $item['quantity'] ?></p>
                            </div>
                            <p class="shrink-0 font-medium text-zinc-900"><?= e($money((int) $item['price_snapshot'] * (int) $item['quantity'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <!-- Pengiriman -->
            <section aria-labelledby="h-kirim">
                <h2 id="h-kirim" class="text-base font-semibold">Pengiriman</h2>
                <div class="mt-3 rounded-xl border border-zinc-200 p-4 text-sm">
                    <?php if ($ship): ?>
                        <p class="font-medium text-zinc-900"><?= e(trim(($ship['courier_name'] ?? '') . ' - ' . ($ship['service_name'] ?? ''), ' -')) ?></p>
                        <?php if (!empty($ship['estimate'])): ?>
                            <p class="mt-0.5 text-xs text-zinc-500">Estimasi <?= e($ship['estimate']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($ship['waybill_id'])): ?>
                            <p class="mt-2 text-zinc-600">Nomor resi: <span class="font-medium text-zinc-900"><?= e($ship['waybill_id']) ?></span></p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($addr): ?>
                        <div class="mt-3 border-t border-zinc-100 pt-3">
                            <p class="font-medium text-zinc-900"><?= e($addr['recipient_name']) ?> <span class="font-normal text-zinc-500">&middot; <?= e($addr['phone']) ?></span></p>
                            <p class="mt-1 leading-relaxed text-zinc-600"><?= nl2br(e($addr['address_detail'])) ?></p>
                            <p class="mt-0.5 text-zinc-500"><?= e($addr['district']) ?>, <?= e($addr['city']) ?>, <?= e($addr['province']) ?> <?= e($addr['postal_code']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($tracking)): ?>
                        <!-- Perjalanan paket -->
                        <div class="mt-3 border-t border-zinc-100 pt-3">
                            <p class="text-xs font-medium text-zinc-500">Perjalanan paket</p>
                            <ol class="ml-2 mt-4 space-y-4 border-l border-zinc-200 pl-5">
                                <?php foreach ($tracking as $idx => $step): ?>
                                    <li class="relative">
                                        <span class="absolute -left-[26px] top-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-white <?= $idx === 0 ? 'bg-yellow-400' : 'bg-zinc-300' ?>"></span>
                                        <p class="<?= $idx === 0 ? 'font-semibold text-zinc-900' : 'text-zinc-600' ?>"><?= e($step['label']) ?></p>
                                        <p class="mt-0.5 text-xs text-zinc-400"><?= e($formatDate($step['time'], true)) ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ol>

                            <?php if ($trackingLink): ?>
                                <a href="<?= e($trackingLink) ?>" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex h-10 items-center justify-center rounded-lg border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-900 hover:bg-zinc-50">Lacak di situs kurir</a>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($order['payment_status'] === 'paid' && !$refundCase): ?>
                        <p class="mt-3 border-t border-zinc-100 pt-3 text-zinc-600">
                            <?= $order['order_status'] === 'pending'
                                ? 'Pembayaran diterima. Pesananmu menunggu diproses oleh toko.'
                                : 'Pesananmu sedang disiapkan dan akan segera dikirim.' ?>
                        </p>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Ringkasan & pembayaran -->
        <aside class="lg:col-span-1" aria-label="Ringkasan pembayaran">
            <div class="rounded-xl border border-zinc-200 p-5 lg:sticky lg:top-36">
                <h2 class="text-base font-semibold">Ringkasan pembayaran</h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Subtotal</dt>
                        <dd class="font-medium text-zinc-900"><?= e($money($order['subtotal'])) ?></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Ongkos kirim</dt>
                        <dd class="font-medium text-zinc-900"><?= e($money($order['shipping_cost'])) ?></dd>
                    </div>
                </dl>

                <div class="mt-4 flex items-baseline justify-between gap-4 border-t border-zinc-200 pt-4">
                    <span class="text-sm font-medium">Total</span>
                    <span class="text-xl font-bold tracking-tight"><?= e($money($order['total_amount'])) ?></span>
                </div>

                <?php if ($refundCase): ?>
                    <p class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Pembayaran sudah kami terima dan akan dikembalikan karena pesanan dibatalkan.</p>
                <?php elseif ($isPending && !$hasInstruction): ?>
                    <div class="mt-5 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
                        Instruksi pembayaran belum tersedia. Selesaikan sebelum <span class="font-semibold"><?= e($formatDate($order['expires_at'], true)) ?></span>.
                    </div>

                    <form
                        method="POST"
                        action="<?= e($payUrl) ?>"
                        class="mt-4"
                        x-data="{ submitting: false }"
                        @pageshow.window="submitting = false"
                        @submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
                    >
                        <?= csrf_field() ?>
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-zinc-200 disabled:text-zinc-500"
                        >
                            <span x-text="submitting ? 'Memproses...' : 'Coba lagi'">Coba lagi</span>
                        </button>
                    </form>
                <?php elseif ($order['payment_status'] === 'paid'): ?>
                    <p class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Pembayaran sudah kami terima. Pesananmu sedang diproses.</p>
                <?php elseif ($order['payment_status'] === 'expired'): ?>
                    <p class="mt-5 text-sm text-zinc-500">Batas waktu pembayaran habis dan pesanan dibatalkan. Kamu bisa berbelanja lagi kapan saja.</p>
                    <a href="/produk" class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 hover:bg-yellow-300">Belanja lagi</a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
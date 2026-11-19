<?php
use App\Models\AdminOrder;

$money = fn($n): string => 'Rp ' . number_format((int) $n, 0, ',', '.');

$paths = [
    'search'  => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    'chevron' => '<path d="m9 18 6-6-6-6"/>',
    'arrow'   => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    'truck'   => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
    'bag'     => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'inbox'   => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
];
$ico = fn(string $name, string $class = 'h-4 w-4'): string =>
    '<svg class="' . $class . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';

$initial = function (?string $name): string {
    $name = trim((string) $name);

    return mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1));
};

$url = function (array $params) use ($tab, $q): string {
    $merged = array_merge(['tab' => $tab === 'all' ? null : $tab, 'q' => $q], $params);
    $merged = array_filter($merged, fn($v) => $v !== null && $v !== '' && $v !== 1);

    return '/admin/orders' . ($merged ? '?' . http_build_query($merged) : '');
};

$perPage = AdminOrder::PER_PAGE;
$from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
$to = min($page * $perPage, $total);
$attention = (int) ($counts['to_process'] ?? 0);
?>
<div class="space-y-5">
    <!-- Header + pencarian -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Pesanan</h2>
            <p class="mt-1 text-sm text-zinc-500">
                <?= (int) $total ?> pesanan<?= $q !== '' ? ' untuk &ldquo;' . e($q) . '&rdquo;' : '' ?>
            </p>
        </div>

        <form method="GET" action="/admin/orders" class="flex w-full gap-2 sm:w-96">
            <?php if ($tab !== 'all'): ?>
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <?php endif; ?>
            <div class="relative min-w-0 flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-zinc-400"><?= $ico('search') ?></span>
                <input
                    type="search"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Cari invoice, nama, atau email"
                    class="h-11 w-full rounded-xl border border-zinc-200 bg-white pl-10 pr-3 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus-visible:border-yellow-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400/40"
                >
            </div>
            <button type="submit" class="inline-flex h-11 shrink-0 items-center rounded-xl bg-yellow-400 px-5 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">Cari</button>
        </form>
    </div>

    <!-- Penanda pesanan yang perlu diproses -->
    <?php if ($attention > 0 && $tab !== 'to_process'): ?>
        <a
            href="<?= e($url(['tab' => 'to_process', 'page' => null])) ?>"
            class="group flex items-center gap-4 rounded-2xl border border-yellow-300 bg-gradient-to-r from-yellow-50 to-white p-4 transition-colors hover:border-yellow-400"
        >
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-yellow-400 text-zinc-900"><?= $ico('bag', 'h-5 w-5') ?></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-zinc-900"><?= $attention ?> pesanan siap diproses</span>
                <span class="block text-xs text-zinc-600">Sudah dibayar pelanggan, tinggal dibuatkan pengiriman.</span>
            </span>
            <span class="shrink-0 text-zinc-500 transition-transform group-hover:translate-x-0.5"><?= $ico('arrow') ?></span>
        </a>
    <?php endif; ?>

    <!-- Tab status -->
    <div class="-mx-4 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
        <div class="flex min-w-max gap-2">
            <?php foreach (AdminOrder::TABS as $key => $def): ?>
                <?php
                $active = $tab === $key;
                $count = (int) ($counts[$key] ?? 0);
                $hot = $key === 'to_process' && $count > 0;
                ?>
                <a
                    href="<?= e($url(['tab' => $key === 'all' ? null : $key, 'page' => null])) ?>"
                    <?= $active ? 'aria-current="page"' : '' ?>
                    class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition-colors <?= $active ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 hover:bg-zinc-50' ?>"
                >
                    <?= e($def['label']) ?>
                    <span class="rounded-full px-2 py-0.5 text-xs <?= $active ? 'bg-white/20 text-white' : ($hot ? 'bg-yellow-400 text-zinc-900' : 'bg-zinc-100 text-zinc-600') ?>"><?= $count ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <!-- Kosong -->
        <div class="flex flex-col items-center rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><?= $ico('inbox', 'h-6 w-6') ?></span>
            <p class="mt-4 text-sm font-semibold text-zinc-900">Tidak ada pesanan</p>
            <p class="mt-1 max-w-xs text-sm text-zinc-500">
                <?= $q !== '' ? 'Tidak ada hasil untuk pencarian ini. Coba kata kunci lain.' : 'Belum ada pesanan di tab ini.' ?>
            </p>
            <?php if ($q !== '' || $tab !== 'all'): ?>
                <a href="/admin/orders" class="mt-4 inline-flex h-10 items-center rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900 hover:bg-zinc-50">Reset filter</a>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <!-- Daftar kartu (HP) -->
        <ul class="space-y-3 md:hidden">
            <?php foreach ($orders as $o): ?>
                <?php [$label, $badge] = AdminOrder::badge($o); ?>
                <li>
                    <a href="/admin/orders/<?= e(rawurlencode($o['order_number'])) ?>" class="block rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition-colors active:bg-zinc-50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-600"><?= e($initial($o['customer_name'] ?? '')) ?></span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-zinc-900"><?= e($o['order_number']) ?></p>
                                    <p class="truncate text-xs text-zinc-500"><?= e($o['customer_name'] ?? '-') ?> &middot; <?= e(AdminOrder::date($o['created_at'])) ?></p>
                                </div>
                            </div>
                            <span class="mt-1 shrink-0 text-zinc-300"><?= $ico('chevron') ?></span>
                        </div>

                        <div class="mt-3 flex items-end justify-between gap-3 border-t border-zinc-100 pt-3">
                            <div>
                                <p class="text-xs text-zinc-500">Total</p>
                                <p class="text-base font-bold tracking-tight text-zinc-900"><?= e($money($o['total_amount'])) ?></p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium <?= $badge ?>">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span><?= e($label) ?>
                            </span>
                        </div>

                        <?php if (!empty($o['waybill_id'])): ?>
                            <p class="mt-3 flex items-center gap-2 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-600">
                                <?= $ico('truck', 'h-3.5 w-3.5 text-zinc-400') ?>
                                Resi <span class="font-medium text-zinc-900"><?= e($o['waybill_id']) ?></span>
                            </p>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Tabel (layar lebar) -->
        <div class="hidden overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50/80">
                        <th class="h-11 px-6 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Pesanan</th>
                        <th class="h-11 px-4 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanggal</th>
                        <th class="h-11 px-4 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">Total</th>
                        <th class="h-11 px-4 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                        <th class="hidden h-11 px-4 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 xl:table-cell">Resi</th>
                        <th class="h-11 w-12 px-4"><span class="sr-only">Buka</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($orders as $o): ?>
                        <?php
                        [$label, $badge] = AdminOrder::badge($o);
                        $detailUrl = '/admin/orders/' . rawurlencode($o['order_number']);
                        ?>
                        <tr
                            x-data
                            @click="if (!$event.target.closest('a')) window.location.href = '<?= e($detailUrl) ?>'"
                            class="group cursor-pointer transition-colors hover:bg-yellow-50/40"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-600"><?= e($initial($o['customer_name'] ?? '')) ?></span>
                                    <div class="min-w-0">
                                        <a href="<?= e($detailUrl) ?>" class="block whitespace-nowrap font-semibold text-zinc-900 hover:underline"><?= e($o['order_number']) ?></a>
                                        <p class="truncate text-xs text-zinc-500"><?= e($o['customer_name'] ?? '-') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-zinc-500"><?= e(AdminOrder::date($o['created_at'])) ?></td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-semibold text-zinc-900"><?= e($money($o['total_amount'])) ?></td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium <?= $badge ?>">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span><?= e($label) ?>
                                </span>
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-4 text-zinc-600 xl:table-cell"><?= e($o['waybill_id'] ?: '-') ?></td>
                            <td class="px-4 py-4 text-zinc-300 transition-colors group-hover:text-zinc-500"><?= $ico('chevron') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginasi -->
        <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-zinc-500">Menampilkan <span class="font-medium text-zinc-900"><?= (int) $from ?>-<?= (int) $to ?></span> dari <span class="font-medium text-zinc-900"><?= (int) $total ?></span></p>
            <?php if ($pages > 1): ?>
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="<?= e($url(['page' => $page - 1])) ?>" class="inline-flex h-10 items-center rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900 shadow-sm hover:bg-zinc-50">Sebelumnya</a>
                    <?php endif; ?>
                    <span class="px-2 text-sm text-zinc-500"><?= (int) $page ?> / <?= (int) $pages ?></span>
                    <?php if ($page < $pages): ?>
                        <a href="<?= e($url(['page' => $page + 1])) ?>" class="inline-flex h-10 items-center rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900 shadow-sm hover:bg-zinc-50">Berikutnya</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
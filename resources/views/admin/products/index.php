<?php
use App\Models\AdminProduct;

$money = fn($n): string => 'Rp ' . number_format((int) $n, 0, ',', '.');

$paths = [
    'plus'   => '<path d="M5 12h14"/><path d="M12 5v14"/>',
    'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    'pencil' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
    'trash'  => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    'power'  => '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>',
    'dots'   => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
    'box'    => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
];
$ico = fn(string $name, string $class = 'h-4 w-4'): string =>
    '<svg class="' . $class . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';

$pageUrl = function (int $p) use ($q): string {
    $params = array_filter(['q' => $q, 'page' => $p > 1 ? $p : null], fn($v) => $v !== null && $v !== '');

    return '/admin/products' . ($params ? '?' . http_build_query($params) : '');
};

$perPage = AdminProduct::PER_PAGE;
$from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
$to = min($page * $perPage, $total);
$lowStock = 5;

$badge = 'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold';
$item = 'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm text-zinc-900 transition-colors hover:bg-zinc-100 focus-visible:bg-zinc-100 focus-visible:outline-none';
$outlineBtn = 'inline-flex h-9 items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900 shadow-sm transition-colors hover:bg-zinc-50';
$outlineBtnOff = 'inline-flex h-9 cursor-not-allowed items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900 opacity-50';

/** Menu aksi per baris (dipakai di tabel dan kartu HP). */
$menu = function (array $p) use ($ico, $item): string {
    $id = (int) $p['id'];
    $active = $p['is_active'] === 1;

    ob_start();
    ?>
    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false">
        <button
            type="button"
            @click="open = !open"
            :aria-expanded="open"
            aria-haspopup="menu"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
        >
            <span class="sr-only">Buka menu</span>
            <?= $ico('dots') ?>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition.opacity.duration.100ms
            role="menu"
            class="absolute right-0 top-full z-20 mt-1 w-48 rounded-md border border-zinc-200 bg-white p-1 shadow-md"
        >
            <a role="menuitem" href="/admin/products/<?= $id ?>/edit" class="<?= $item ?>"><?= $ico('pencil') ?> Ubah</a>

            <form method="POST" action="/admin/products/<?= $id ?>/toggle">
                <?= csrf_field() ?>
                <button type="submit" role="menuitem" class="<?= $item ?>"><?= $ico('power') ?> <?= $active ? 'Nonaktifkan' : 'Aktifkan' ?></button>
            </form>

            <div class="-mx-1 my-1 h-px bg-zinc-100"></div>

            <form method="POST" action="/admin/products/<?= $id ?>/delete"
                  @submit="if (!confirm('Hapus produk ini beserta semua variasi dan fotonya?')) $event.preventDefault()">
                <?= csrf_field() ?>
                <button type="submit" role="menuitem" class="<?= $item ?> text-rose-600 hover:bg-rose-50 focus-visible:bg-rose-50"><?= $ico('trash') ?> Hapus</button>
            </form>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
};

$priceText = function (array $p) use ($money): string {
    if ($p['min_price'] === null) {
        return '-';
    }

    return $p['min_price'] === $p['max_price']
        ? $money($p['min_price'])
        : $money($p['min_price']) . ' - ' . $money($p['max_price']);
};
?>
<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Produk</h2>
            <p class="mt-1 text-sm text-zinc-500">Kelola produk, variasi, harga, dan stok toko.</p>
        </div>
        <a href="/admin/products/create" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-md bg-yellow-400 px-4 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">
            <?= $ico('plus') ?> Tambah produk
        </a>
    </div>

    <!-- Toolbar -->
    <form method="GET" action="/admin/products" class="relative max-w-sm">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-zinc-400"><?= $ico('search') ?></span>
        <input
            type="search"
            name="q"
            value="<?= e($q) ?>"
            placeholder="Cari nama atau SKU..."
            class="h-10 w-full rounded-md border border-zinc-200 bg-white pl-9 pr-3 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus-visible:border-yellow-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400/40"
        >
    </form>

    <?php if (empty($products)): ?>
        <div class="flex flex-col items-center rounded-md border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><?= $ico('box', 'h-6 w-6') ?></span>
            <p class="mt-4 text-sm font-semibold text-zinc-900"><?= $q !== '' ? 'Produk tidak ditemukan' : 'Belum ada produk' ?></p>
            <p class="mt-1 max-w-xs text-sm text-zinc-500"><?= $q !== '' ? 'Coba kata kunci lain.' : 'Tambahkan produk pertama toko Anda.' ?></p>
            <?php if ($q !== ''): ?>
                <a href="/admin/products" class="<?= $outlineBtn ?> mt-4">Reset pencarian</a>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <!-- Tabel (layar lebar) -->
        <div class="hidden rounded-md border border-zinc-200 bg-white shadow-sm md:block">
            <table class="w-full caption-bottom text-sm">
                <thead>
                    <tr class="border-b border-zinc-200">
                        <th class="h-11 px-4 text-left align-middle font-medium text-zinc-500">Produk</th>
                        <th class="hidden h-11 px-4 text-left align-middle font-medium text-zinc-500 xl:table-cell">Kategori</th>
                        <th class="h-11 px-4 text-left align-middle font-medium text-zinc-500">Harga</th>
                        <th class="h-11 px-4 text-left align-middle font-medium text-zinc-500">Stok</th>
                        <th class="h-11 px-4 text-left align-middle font-medium text-zinc-500">Status</th>
                        <th class="h-11 w-14 px-4"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <?php foreach ($products as $p): ?>
                        <?php $active = $p['is_active'] === 1; ?>
                        <tr class="transition-colors hover:bg-zinc-50/70">
                            <td class="p-4 align-middle">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="<?= e($p['image']) ?>" alt="" loading="lazy" class="h-10 w-10 shrink-0 rounded-md border border-zinc-200 object-cover">
                                    <?php else: ?>
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-zinc-100 text-zinc-400"><?= $ico('box') ?></span>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <a href="/admin/products/<?= (int) $p['id'] ?>/edit" class="block max-w-[16rem] truncate font-medium text-zinc-900 hover:underline"><?= e($p['name']) ?></a>
                                        <p class="text-xs text-zinc-500"><?= (int) $p['variant_count'] ?> variasi</p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden p-4 align-middle text-zinc-600 xl:table-cell"><?= e($p['category_name']) ?></td>
                            <td class="whitespace-nowrap p-4 align-middle font-medium text-zinc-900"><?= e($priceText($p)) ?></td>
                            <td class="p-4 align-middle">
                                <?php if ($p['stock'] === 0): ?>
                                    <span class="<?= $badge ?> border-rose-200 bg-rose-50 text-rose-700">Habis</span>
                                <?php elseif ($p['stock'] <= $lowStock): ?>
                                    <span class="<?= $badge ?> border-yellow-300 bg-yellow-50 text-yellow-800"><?= (int) $p['stock'] ?> tersisa</span>
                                <?php else: ?>
                                    <span class="text-zinc-900"><?= (int) $p['stock'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 align-middle">
                                <span class="<?= $badge ?> <?= $active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-zinc-200 bg-zinc-50 text-zinc-600' ?>"><?= $active ? 'Aktif' : 'Nonaktif' ?></span>
                            </td>
                            <td class="p-4 text-right align-middle"><?= $menu($p) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Kartu (HP) -->
        <ul class="space-y-3 md:hidden">
            <?php foreach ($products as $p): ?>
                <?php $active = $p['is_active'] === 1; ?>
                <li class="rounded-md border border-zinc-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <?php if (!empty($p['image'])): ?>
                            <img src="<?= e($p['image']) ?>" alt="" loading="lazy" class="h-14 w-14 shrink-0 rounded-md border border-zinc-200 object-cover">
                        <?php else: ?>
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md bg-zinc-100 text-zinc-400"><?= $ico('box', 'h-6 w-6') ?></span>
                        <?php endif; ?>

                        <div class="min-w-0 flex-1">
                            <a href="/admin/products/<?= (int) $p['id'] ?>/edit" class="block truncate text-sm font-medium text-zinc-900"><?= e($p['name']) ?></a>
                            <p class="mt-0.5 truncate text-xs text-zinc-500"><?= e($p['category_name']) ?> &middot; <?= (int) $p['variant_count'] ?> variasi</p>
                            <p class="mt-1 text-sm font-medium text-zinc-900"><?= e($priceText($p)) ?></p>
                        </div>

                        <?= $menu($p) ?>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-3">
                        <span class="<?= $badge ?> <?= $active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-zinc-200 bg-zinc-50 text-zinc-600' ?>"><?= $active ? 'Aktif' : 'Nonaktif' ?></span>
                        <?php if ($p['stock'] === 0): ?>
                            <span class="<?= $badge ?> border-rose-200 bg-rose-50 text-rose-700">Stok habis</span>
                        <?php elseif ($p['stock'] <= $lowStock): ?>
                            <span class="<?= $badge ?> border-yellow-300 bg-yellow-50 text-yellow-800">Stok <?= (int) $p['stock'] ?></span>
                        <?php else: ?>
                            <span class="text-xs text-zinc-500">Stok <?= (int) $p['stock'] ?></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Footer tabel -->
        <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-zinc-500">
                Menampilkan <span class="font-medium text-zinc-900"><?= (int) $from ?>-<?= (int) $to ?></span>
                dari <span class="font-medium text-zinc-900"><?= (int) $total ?></span> produk<?= $q !== '' ? ' untuk &ldquo;' . e($q) . '&rdquo;' : '' ?>
            </p>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="<?= e($pageUrl($page - 1)) ?>" class="<?= $outlineBtn ?>">Sebelumnya</a>
                <?php else: ?>
                    <span class="<?= $outlineBtnOff ?>" aria-disabled="true">Sebelumnya</span>
                <?php endif; ?>

                <?php if ($page < $pages): ?>
                    <a href="<?= e($pageUrl($page + 1)) ?>" class="<?= $outlineBtn ?>">Berikutnya</a>
                <?php else: ?>
                    <span class="<?= $outlineBtnOff ?>" aria-disabled="true">Berikutnya</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
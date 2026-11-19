<?php
$paths = [
    'plus'   => '<path d="M5 12h14"/><path d="M12 5v14"/>',
    'pencil' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
    'trash'  => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    'tag'    => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
    'star'   => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
];
$ico = fn(string $name, string $class = 'h-4 w-4'): string =>
    '<svg class="' . $class . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';

$btn = 'inline-flex h-10 items-center justify-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900 transition-colors hover:bg-zinc-50';
$featuredCount = count(array_filter($categories, fn($c) => $c['is_featured'] === 1));
?>
<div class="space-y-5">
    <div class="flex items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Kategori</h2>
            <p class="mt-1 text-sm text-zinc-500"><?= count($categories) ?> kategori, <?= $featuredCount ?> unggulan</p>
        </div>
        <a href="/admin/categories/create" class="inline-flex h-11 shrink-0 items-center gap-2 rounded-xl bg-yellow-400 px-5 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">
            <?= $ico('plus') ?> Tambah
        </a>
    </div>

    <?php if ($featuredCount === 0 && !empty($categories)): ?>
        <p class="rounded-xl bg-zinc-50 px-4 py-3 text-sm text-zinc-600">Belum ada kategori unggulan, jadi homepage menampilkan semua kategori aktif. Tandai bintang untuk memilih sendiri.</p>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="flex flex-col items-center rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><?= $ico('tag', 'h-6 w-6') ?></span>
            <p class="mt-4 text-sm font-semibold text-zinc-900">Belum ada kategori</p>
            <p class="mt-1 max-w-xs text-sm text-zinc-500">Buat kategori pertama sebelum menambahkan produk.</p>
        </div>
    <?php else: ?>
        <ul class="divide-y divide-zinc-100 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <?php foreach ($categories as $c): ?>
                <?php
                $active = $c['is_active'] === 1;
                $featured = $c['is_featured'] === 1;
                $locked = $c['product_count'] > 0;
                ?>
                <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-semibold text-zinc-900"><?= e($c['name']) ?></p>
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium <?= $active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-zinc-200 bg-zinc-50 text-zinc-600' ?>">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span><?= $active ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                            <?php if ($featured): ?>
                                <span class="inline-flex items-center gap-1 rounded-full border border-yellow-300 bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-800"><?= $ico('star', 'h-3 w-3') ?> Unggulan</span>
                            <?php endif; ?>
                        </div>
                        <p class="mt-1 truncate text-xs text-zinc-500">/<?= e($c['slug']) ?> &middot; <?= (int) $c['product_count'] ?> produk</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="/admin/categories/<?= (int) $c['id'] ?>/feature">
                            <?= csrf_field() ?>
                            <button
                                type="submit"
                                title="<?= $featured ? 'Lepas dari unggulan' : 'Jadikan unggulan' ?>"
                                aria-label="<?= $featured ? 'Lepas dari unggulan' : 'Jadikan unggulan' ?>"
                                aria-pressed="<?= $featured ? 'true' : 'false' ?>"
                                class="<?= $btn ?> w-10 px-0 <?= $featured ? 'border-yellow-300 bg-yellow-50 text-yellow-600' : 'text-zinc-400' ?>"
                            ><?= $ico('star') ?></button>
                        </form>

                        <a href="/admin/categories/<?= (int) $c['id'] ?>/edit" class="<?= $btn ?>"><?= $ico('pencil') ?> Ubah</a>

                        <form method="POST" action="/admin/categories/<?= (int) $c['id'] ?>/toggle">
                            <?= csrf_field() ?>
                            <button type="submit" class="<?= $btn ?>"><?= $active ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                        </form>

                        <form method="POST" action="/admin/categories/<?= (int) $c['id'] ?>/delete"
                              x-data
                              @submit="if (!confirm('Hapus kategori ini?')) $event.preventDefault()">
                            <?= csrf_field() ?>
                            <button
                                type="submit"
                                <?= $locked ? 'disabled title="Masih punya produk"' : '' ?>
                                class="<?= $btn ?> <?= $locked ? 'cursor-not-allowed opacity-40' : 'text-rose-600 hover:bg-rose-50' ?>"
                            ><?= $ico('trash') ?> Hapus</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
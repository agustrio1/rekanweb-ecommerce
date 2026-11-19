<?php
$paths = [
    'plus'   => '<path d="M5 12h14"/><path d="M12 5v14"/>',
    'pencil' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
    'trash'  => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    'image'  => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
];
$ico = fn(string $name, string $class = 'h-4 w-4'): string =>
    '<svg class="' . $class . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';

$btn = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900 shadow-sm transition-colors hover:bg-zinc-50';
$badge = 'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold';
?>
<div class="space-y-4">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Banner</h2>
            <p class="mt-1 text-sm text-zinc-500">Gambar slide di halaman depan toko. Urutan terkecil tampil lebih dulu.</p>
        </div>
        <a href="/admin/banners/create" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-md bg-yellow-400 px-4 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">
            <?= $ico('plus') ?> Tambah banner
        </a>
    </div>

    <?php if (empty($banners)): ?>
        <div class="flex flex-col items-center rounded-md border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400"><?= $ico('image', 'h-6 w-6') ?></span>
            <p class="mt-4 text-sm font-semibold text-zinc-900">Belum ada banner</p>
            <p class="mt-1 max-w-xs text-sm text-zinc-500">Tambahkan banner pertama untuk halaman depan toko.</p>
        </div>
    <?php else: ?>
        <ul class="divide-y divide-zinc-200 overflow-hidden rounded-md border border-zinc-200 bg-white shadow-sm">
            <?php foreach ($banners as $b): ?>
                <?php $active = $b['is_active'] === 1; ?>
                <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-4">
                        <img src="<?= e($b['image_path']) ?>" alt="" loading="lazy" class="h-16 w-28 shrink-0 rounded-md border border-zinc-200 object-cover">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-sm font-medium text-zinc-900"><?= e($b['title']) ?></p>
                                <span class="<?= $badge ?> <?= $active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-zinc-200 bg-zinc-50 text-zinc-600' ?>"><?= $active ? 'Aktif' : 'Nonaktif' ?></span>
                            </div>
                            <p class="mt-0.5 truncate text-xs text-zinc-500">Urutan <?= (int) $b['sort_order'] ?><?= $b['link_url'] ? ' &middot; ' . e($b['link_url']) : '' ?></p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="/admin/banners/<?= (int) $b['id'] ?>/edit" class="<?= $btn ?>"><?= $ico('pencil') ?> Ubah</a>

                        <form method="POST" action="/admin/banners/<?= (int) $b['id'] ?>/toggle">
                            <?= csrf_field() ?>
                            <button type="submit" class="<?= $btn ?>"><?= $active ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                        </form>

                        <form method="POST" action="/admin/banners/<?= (int) $b['id'] ?>/delete"
                              x-data
                              @submit="if (!confirm('Hapus banner ini?')) $event.preventDefault()">
                            <?= csrf_field() ?>
                            <button type="submit" class="<?= $btn ?> text-rose-600 hover:bg-rose-50"><?= $ico('trash') ?> Hapus</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
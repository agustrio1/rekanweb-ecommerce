<?php
$badge = 'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold';
$btn = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900 shadow-sm transition-colors hover:bg-zinc-50';
?>
<div class="space-y-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Halaman</h2>
        <p class="mt-1 text-sm text-zinc-500">Halaman informasi toko yang ditautkan dari footer.</p>
    </div>

    <ul class="divide-y divide-zinc-200 overflow-hidden rounded-md border border-zinc-200 bg-white shadow-sm">
        <?php foreach ($pages as $p): ?>
            <?php $active = $p['is_active'] === 1; ?>
            <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-medium text-zinc-900"><?= e($p['title']) ?></p>
                        <span class="<?= $badge ?> <?= $active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-zinc-200 bg-zinc-50 text-zinc-600' ?>"><?= $active ? 'Tampil' : 'Disembunyikan' ?></span>
                    </div>
                    <p class="mt-0.5 text-xs text-zinc-500"><?= e($p['slug']) ?> &middot; diubah <?= e(date('d/m/Y H:i', strtotime($p['updated_at']))) ?></p>
                </div>
                <a href="/admin/pages/<?= e(rawurlencode($p['slug'])) ?>/edit" class="<?= $btn ?>">Ubah</a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
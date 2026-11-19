<?php
$addresses = $addresses ?? [];
$canAdd = !empty($canAdd);

include __DIR__ . '/../components/account-nav.php';
?>

<div class="mx-auto max-w-3xl px-4 pt-6 sm:px-6">
    <?php if (empty($addresses)): ?>
        <div class="flex flex-col items-center rounded-xl border border-dashed border-zinc-300 px-6 py-14 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 text-yellow-700">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-900">Belum ada alamat tersimpan</p>
            <p class="mt-1 max-w-sm text-sm text-zinc-500">Simpan alamat sekali, lalu pakai berulang saat checkout.</p>
            <a href="/akun/alamat/tambah" class="mt-5 inline-flex h-11 items-center rounded-lg bg-yellow-400 px-5 text-sm font-medium text-zinc-900 hover:bg-yellow-300">Tambah alamat</a>
        </div>
    <?php else: ?>
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-zinc-500"><?= count($addresses) ?> alamat tersimpan</p>
            <?php if ($canAdd): ?>
                <a href="/akun/alamat/tambah" class="inline-flex h-10 items-center rounded-lg bg-yellow-400 px-4 text-sm font-medium text-zinc-900 hover:bg-yellow-300">Tambah alamat</a>
            <?php endif; ?>
        </div>

        <ul class="mt-4 space-y-3">
            <?php foreach ($addresses as $address): ?>
                <li class="rounded-xl border p-4 sm:p-5 <?= (int) $address['is_default'] === 1 ? 'border-yellow-400' : 'border-zinc-200' ?>">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-zinc-900"><?= e($address['recipient_name']) ?></p>
                            <p class="mt-0.5 text-sm text-zinc-500"><?= e($address['phone']) ?></p>
                        </div>
                        <?php if ((int) $address['is_default'] === 1): ?>
                            <span class="shrink-0 rounded-md bg-yellow-400 px-2 py-0.5 text-xs font-semibold text-zinc-900">Utama</span>
                        <?php endif; ?>
                    </div>

                    <p class="mt-3 text-sm leading-relaxed text-zinc-600"><?= nl2br(e($address['address_detail'])) ?></p>
                    <p class="mt-1 text-sm text-zinc-500"><?= e($address['district']) ?>, <?= e($address['city']) ?>, <?= e($address['province']) ?> <?= e($address['postal_code']) ?></p>

                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-3">
                        <a href="/akun/alamat/<?= (int) $address['id'] ?>/ubah" class="inline-flex h-9 items-center rounded-lg border border-zinc-200 px-3 text-sm font-medium text-zinc-900 hover:bg-zinc-100">Ubah</a>

                        <?php if ((int) $address['is_default'] !== 1): ?>
                            <form method="POST" action="/akun/alamat/<?= (int) $address['id'] ?>/utama">
                                <?= csrf_field() ?>
                                <button type="submit" class="inline-flex h-9 items-center rounded-lg px-3 text-sm text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900">Jadikan utama</button>
                            </form>
                        <?php endif; ?>

                        <form method="POST" action="/akun/alamat/<?= (int) $address['id'] ?>/hapus" x-data @submit="if (!confirm('Hapus alamat ini?')) $event.preventDefault()" class="ml-auto">
                            <?= csrf_field() ?>
                            <button type="submit" class="inline-flex h-9 items-center rounded-lg px-3 text-sm text-zinc-500 hover:bg-zinc-100 hover:text-rose-600">Hapus</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
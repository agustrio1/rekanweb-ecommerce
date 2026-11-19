<?php
$isEdit = $banner !== null;
$action = $isEdit ? '/admin/banners/' . (int) $banner['id'] : '/admin/banners';

$input = 'mt-1.5 w-full rounded-xl border bg-white px-3 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus-visible:outline-none focus-visible:ring-2';
$ok = 'border-zinc-200 focus-visible:border-yellow-400 focus-visible:ring-yellow-400/40';
$bad = 'border-rose-300 focus-visible:border-rose-400 focus-visible:ring-rose-400/30';

$err = fn(string $key): string => isset($errors[$key])
    ? '<p class="mt-1.5 text-xs text-rose-600">' . e($errors[$key]) . '</p>'
    : '';
$cls = fn(string $key): string => isset($errors[$key]) ? $bad : $ok;
?>
<div class="mx-auto max-w-xl space-y-5">
    <div>
        <a href="/admin/banners" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-900">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Semua banner
        </a>
        <h2 class="mt-3 text-2xl font-bold tracking-tight text-zinc-900"><?= $isEdit ? 'Ubah banner' : 'Tambah banner' ?></h2>
    </div>

    <form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <?= csrf_field() ?>

        <div>
            <label for="image" class="text-sm font-medium text-zinc-900">Gambar<?= $isEdit ? ' <span class="font-normal text-zinc-400">(kosongkan jika tidak diganti)</span>' : '' ?></label>

            <?php if ($isEdit): ?>
                <img src="<?= e($banner['image_path']) ?>" alt="" class="mt-2 aspect-[16/6] w-full rounded-xl border border-zinc-200 object-cover">
            <?php endif; ?>

            <input
                id="image"
                type="file"
                name="image"
                accept="image/jpeg,image/png,image/webp"
                <?= $isEdit ? '' : 'required' ?>
                class="mt-2 block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-900 hover:file:bg-zinc-200"
            >
            <?= $err('image') ?>
            <p class="mt-1.5 text-xs text-zinc-500">Saran ukuran 1600 x 600 px. JPG, PNG, atau WebP, maksimal 2 MB.</p>
        </div>

        <div>
            <label for="title" class="text-sm font-medium text-zinc-900">Judul</label>
            <input id="title" type="text" name="title" value="<?= e($values['title']) ?>" maxlength="150" required class="<?= $input ?> h-11 <?= $cls('title') ?>">
            <?= $err('title') ?>
        </div>

        <div>
            <label for="subtitle" class="text-sm font-medium text-zinc-900">Teks pendukung <span class="font-normal text-zinc-400">(opsional)</span></label>
            <input id="subtitle" type="text" name="subtitle" value="<?= e($values['subtitle']) ?>" maxlength="255" class="<?= $input ?> h-11 <?= $cls('subtitle') ?>">
            <?= $err('subtitle') ?>
        </div>

        <div>
            <label for="link_url" class="text-sm font-medium text-zinc-900">Link tujuan <span class="font-normal text-zinc-400">(opsional)</span></label>
            <input id="link_url" type="text" name="link_url" value="<?= e($values['link_url']) ?>" maxlength="255" placeholder="/kategori/elektronik atau https://..." class="<?= $input ?> h-11 <?= $cls('link_url') ?>">
            <?= $err('link_url') ?>
        </div>

        <div>
            <label for="sort_order" class="text-sm font-medium text-zinc-900">Urutan</label>
            <input id="sort_order" type="number" inputmode="numeric" step="1" name="sort_order" value="<?= e((string) $values['sort_order']) ?>" required class="<?= $input ?> h-11 <?= $cls('sort_order') ?>">
            <?= $err('sort_order') ?>
            <p class="mt-1.5 text-xs text-zinc-500">Angka kecil tampil lebih dulu.</p>
        </div>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="is_active" value="1" <?= (int) $values['is_active'] === 1 ? 'checked' : '' ?> class="h-5 w-5 rounded border-zinc-300 text-yellow-500 focus:ring-yellow-400">
            <span class="text-sm text-zinc-900">Tampilkan di toko</span>
        </label>

        <div class="flex flex-col-reverse gap-2 border-t border-zinc-100 pt-5 sm:flex-row sm:justify-end">
            <a href="/admin/banners" class="inline-flex h-11 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 text-sm font-medium text-zinc-900 hover:bg-zinc-50">Batal</a>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">
                <?= $isEdit ? 'Simpan perubahan' : 'Tambah banner' ?>
            </button>
        </div>
    </form>
</div>
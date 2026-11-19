<?php
$input = 'mt-1.5 w-full rounded-xl border bg-white px-3 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus-visible:outline-none focus-visible:ring-2';
$ok = 'border-zinc-200 focus-visible:border-yellow-400 focus-visible:ring-yellow-400/40';
$bad = 'border-rose-300 focus-visible:border-rose-400 focus-visible:ring-rose-400/30';
?>
<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <a href="/admin/pages" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-900">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Semua halaman
        </a>
        <h2 class="mt-3 text-2xl font-bold tracking-tight text-zinc-900">Ubah halaman</h2>
    </div>

    <form method="POST" action="/admin/pages/<?= e(rawurlencode($page['slug'])) ?>" class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <?= csrf_field() ?>

        <div>
            <label for="title" class="text-sm font-medium text-zinc-900">Judul</label>
            <input id="title" type="text" name="title" value="<?= e($values['title']) ?>" maxlength="150" required class="<?= $input ?> h-11 <?= isset($errors['title']) ? $bad : $ok ?>">
            <?php if (isset($errors['title'])): ?>
                <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['title']) ?></p>
            <?php endif; ?>
            <p class="mt-1.5 text-xs text-zinc-500">Alamat halaman (<?= e($page['slug']) ?>) tetap dan tidak bisa diubah.</p>
        </div>

        <?= \App\Core\View::render('components.rich-editor', [
            'name' => 'content',
            'value' => $values['content'],
            'error' => $errors['content'] ?? null,
            'label' => 'Isi halaman',
        ]) ?>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="is_active" value="1" <?= (int) $values['is_active'] === 1 ? 'checked' : '' ?> class="h-5 w-5 rounded border-zinc-300 text-yellow-500 focus:ring-yellow-400">
            <span class="text-sm text-zinc-900">Tampilkan halaman ini</span>
        </label>

        <div class="flex flex-col-reverse gap-2 border-t border-zinc-100 pt-5 sm:flex-row sm:justify-end">
            <a href="/admin/pages" class="inline-flex h-11 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 text-sm font-medium text-zinc-900 hover:bg-zinc-50">Batal</a>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">Simpan perubahan</button>
        </div>
    </form>
</div>
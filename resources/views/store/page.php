<?php
use App\Helpers\HtmlSanitizer;

$html = $page !== null ? HtmlSanitizer::clean((string) $page['content']) : '';
?>
<div class="mx-auto max-w-3xl px-4 pt-6 sm:px-6 lg:px-8 lg:pt-10">
    <?php if ($page === null): ?>
        <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center">
            <p class="text-sm font-semibold text-zinc-900">Halaman tidak ditemukan</p>
            <p class="mt-1 text-sm text-zinc-500">Halaman ini tidak ada atau sedang disembunyikan.</p>
            <a href="/" class="mt-5 inline-flex h-10 items-center rounded-lg border border-zinc-200 px-4 text-sm font-medium text-zinc-900 hover:bg-zinc-50">Kembali ke beranda</a>
        </div>
    <?php else: ?>
        <nav class="text-sm text-zinc-500" aria-label="Breadcrumb">
            <ol class="flex items-center gap-x-2">
                <li><a href="/" class="hover:text-zinc-900">Beranda</a></li>
                <li aria-hidden="true">/</li>
                <li class="truncate text-zinc-900" aria-current="page"><?= e($page['title']) ?></li>
            </ol>
        </nav>

        <h1 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl"><?= e($page['title']) ?></h1>

        <?php if ($html !== ''): ?>
            <div class="rich-content mt-6 text-base leading-relaxed text-zinc-700"><?= $html ?></div>
        <?php else: ?>
            <p class="mt-6 text-sm text-zinc-500">Isi halaman ini belum ditulis.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
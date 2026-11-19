<?php
$message = $message ?? 'Halaman yang kamu cari tidak ditemukan.';
?>
<div class="mx-auto flex max-w-xl flex-col items-center px-4 py-24 text-center sm:px-6">
    <p class="text-sm font-medium text-yellow-700">404</p>
    <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Tidak ditemukan</h1>
    <p class="mt-3 text-sm leading-relaxed text-zinc-500"><?= e($message) ?></p>
    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
        <a href="/produk" class="inline-flex h-11 items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-medium text-zinc-900 hover:bg-yellow-300">Lihat semua produk</a>
        <a href="/" class="inline-flex h-11 items-center justify-center rounded-lg border border-zinc-200 px-6 text-sm font-medium text-zinc-900 hover:bg-zinc-100">Ke beranda</a>
    </div>
</div>
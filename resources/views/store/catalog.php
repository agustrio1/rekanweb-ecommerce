<?php
$products = $products ?? [];
$categories = $categories ?? [];
$activeCategory = $activeCategory ?? null;
$basePath = $basePath ?? '/produk';
$q = $q ?? '';
$sort = $sort ?? 'terbaru';
$availableOnly = !empty($availableOnly);
$total = (int) ($total ?? 0);
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);

$sortLabels = [
    'terbaru' => 'Terbaru',
    'populer' => 'Terlaris',
    'termurah' => 'Harga terendah',
    'termahal' => 'Harga tertinggi',
];

// Susun URL dengan mempertahankan filter yang aktif.
$url = function (string $path, array $override = []) use ($q, $sort, $availableOnly): string {
    $params = array_merge([
        'q' => $q,
        'sort' => $sort !== 'terbaru' ? $sort : '',
        'tersedia' => $availableOnly ? '1' : '',
        'halaman' => '',
    ], $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);

    return $path . ($params ? '?' . http_build_query($params) : '');
};

$pageUrl = fn(int $p): string => $url($basePath, ['halaman' => $p > 1 ? (string) $p : '']);

$heading = $activeCategory['name'] ?? ($q !== '' ? 'Hasil pencarian' : 'Semua produk');
$hasFilter = $q !== '' || $availableOnly;

// Nomor halaman yang ditampilkan: jendela 5 halaman di sekitar halaman aktif.
$start = max(1, min($page - 2, $pages - 4));
$end = min($pages, $start + 4);
?>

<div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8 lg:pt-8">
    <!-- Breadcrumb -->
    <nav class="text-sm text-zinc-500" aria-label="Breadcrumb">
        <ol class="flex items-center gap-2">
            <li><a href="/" class="hover:text-zinc-900">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <?php if ($activeCategory): ?>
                <li><a href="/produk" class="hover:text-zinc-900">Produk</a></li>
                <li aria-hidden="true">/</li>
                <li class="text-zinc-900" aria-current="page"><?= e($activeCategory['name']) ?></li>
            <?php else: ?>
                <li class="text-zinc-900" aria-current="page">Produk</li>
            <?php endif; ?>
        </ol>
    </nav>

    <!-- Judul -->
    <div class="mt-4">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl"><?= e($heading) ?></h1>
        <?php if ($q !== ''): ?>
            <p class="mt-1 text-sm text-zinc-500">
                Menampilkan hasil untuk <span class="font-medium text-zinc-900">"<?= e($q) ?>"</span>
                <a href="<?= e($url($basePath, ['q' => ''])) ?>" class="ml-1 font-medium text-zinc-900 underline underline-offset-2 hover:text-yellow-700">Hapus</a>
            </p>
        <?php endif; ?>
    </div>

    <!-- Kategori -->
    <?php if (!empty($categories)): ?>
        <div class="-mx-4 mt-6 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <div class="flex w-max gap-2 sm:w-auto sm:flex-wrap">
                <?php $allActive = $activeCategory === null; ?>
                <a href="<?= e($url('/produk')) ?>"
                   class="inline-flex h-9 items-center rounded-full border px-4 text-sm font-medium transition-colors <?= $allActive ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 text-zinc-700 hover:border-zinc-400' ?>"
                   <?= $allActive ? 'aria-current="page"' : '' ?>>Semua</a>
                <?php foreach ($categories as $cat): ?>
                    <?php $isActive = $activeCategory && $activeCategory['slug'] === $cat['slug']; ?>
                    <a href="<?= e($url('/kategori/' . $cat['slug'])) ?>"
                       class="inline-flex h-9 items-center rounded-full border px-4 text-sm font-medium transition-colors <?= $isActive ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 text-zinc-700 hover:border-zinc-400' ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>><?= e($cat['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Toolbar: jumlah, filter stok, sorting -->
    <form action="<?= e($basePath) ?>" method="get" x-data class="mt-5 flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-y border-zinc-200 py-3">
        <?php if ($q !== ''): ?>
            <input type="hidden" name="q" value="<?= e($q) ?>">
        <?php endif; ?>

        <p class="text-sm text-zinc-500"><span class="font-medium text-zinc-900"><?= number_format($total, 0, ',', '.') ?></span> produk</p>

        <div class="flex items-center gap-4">
            <label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700">
                <input type="checkbox" name="tersedia" value="1" <?= $availableOnly ? 'checked' : '' ?> @change="$el.form.submit()" class="h-4 w-4 rounded border-zinc-300 accent-yellow-500">
                Tersedia saja
            </label>

            <div class="flex items-center gap-2">
                <label for="sort" class="sr-only">Urutkan</label>
                <select id="sort" name="sort" @change="$el.form.submit()" class="h-9 rounded-lg border border-zinc-200 bg-white pl-3 pr-8 text-sm text-zinc-900 focus:border-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-400/30">
                    <?php foreach ($sortLabels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <noscript>
            <button type="submit" class="h-9 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white">Terapkan</button>
        </noscript>
    </form>

    <!-- Grid produk -->
    <?php if (empty($products)): ?>
        <div class="mt-8 flex flex-col items-center rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-900">
                <?= $hasFilter ? 'Tidak ada produk yang cocok' : 'Belum ada produk di sini' ?>
            </p>
            <p class="mt-1 max-w-sm text-sm text-zinc-500">
                <?= $hasFilter ? 'Coba kata kunci lain atau hapus filter yang sedang aktif.' : 'Produk akan tampil setelah ditambahkan dari panel admin.' ?>
            </p>
            <?php if ($hasFilter): ?>
                <a href="<?= e($basePath) ?>" class="mt-5 inline-flex h-10 items-center rounded-lg bg-yellow-400 px-4 text-sm font-medium text-zinc-900 hover:bg-yellow-300">Reset pencarian</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3 lg:grid-cols-4">
            <?php foreach ($products as $product): ?>
                <?php include __DIR__ . '/../components/product-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
            <nav class="mt-12 flex items-center justify-between gap-3" aria-label="Halaman">
                <?php if ($page > 1): ?>
                    <a href="<?= e($pageUrl($page - 1)) ?>" rel="prev" class="inline-flex h-10 items-center rounded-lg border border-zinc-200 px-4 text-sm font-medium text-zinc-900 hover:bg-zinc-100">Sebelumnya</a>
                <?php else: ?>
                    <span class="inline-flex h-10 items-center rounded-lg border border-zinc-100 px-4 text-sm font-medium text-zinc-300">Sebelumnya</span>
                <?php endif; ?>

                <p class="text-sm text-zinc-500 sm:hidden">Halaman <?= $page ?> dari <?= $pages ?></p>

                <ul class="hidden items-center gap-1 sm:flex">
                    <?php if ($start > 1): ?>
                        <li><a href="<?= e($pageUrl(1)) ?>" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm text-zinc-700 hover:bg-zinc-100">1</a></li>
                        <?php if ($start > 2): ?><li class="px-1 text-zinc-400">…</li><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li>
                            <a href="<?= e($pageUrl($i)) ?>"
                               class="flex h-10 w-10 items-center justify-center rounded-lg text-sm <?= $i === $page ? 'bg-yellow-400 font-semibold text-zinc-900' : 'text-zinc-700 hover:bg-zinc-100' ?>"
                               <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $pages): ?>
                        <?php if ($end < $pages - 1): ?><li class="px-1 text-zinc-400">…</li><?php endif; ?>
                        <li><a href="<?= e($pageUrl($pages)) ?>" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm text-zinc-700 hover:bg-zinc-100"><?= $pages ?></a></li>
                    <?php endif; ?>
                </ul>

                <?php if ($page < $pages): ?>
                    <a href="<?= e($pageUrl($page + 1)) ?>" rel="next" class="inline-flex h-10 items-center rounded-lg border border-zinc-200 px-4 text-sm font-medium text-zinc-900 hover:bg-zinc-100">Berikutnya</a>
                <?php else: ?>
                    <span class="inline-flex h-10 items-center rounded-lg border border-zinc-100 px-4 text-sm font-medium text-zinc-300">Berikutnya</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
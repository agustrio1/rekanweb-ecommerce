<?php
$categories = \App\Models\Category::featured();
$latestProducts = $latestProducts ?? [];
$popularProducts = $popularProducts ?? [];
$banners = $banners ?? \App\Models\Banner::active();
$bannerCount = count($banners);

$perks = [
    [
        'title' => 'Pembayaran aman',
        'text' => 'Transfer bank, e-wallet, QRIS, dan kartu lewat Duitku.',
        'icon' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    ],
    [
        'title' => 'Ongkir transparan',
        'text' => 'Bandingkan kurir dan tarif asli sebelum membayar.',
        'icon' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
    ],
    [
        'title' => 'Pantau pesanan',
        'text' => 'Status pembayaran dan nomor resi bisa dicek kapan saja.',
        'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    ],
];
?>

<!-- Hero -->
<section class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8 lg:pt-8">
    <?php if ($bannerCount > 0): ?>
        <div
            class="relative overflow-hidden rounded-2xl bg-zinc-100"
            x-data="{
                i: 0,
                n: <?= (int) $bannerCount ?>,
                timer: null,
                go(k) { this.i = (k + this.n) % this.n; },
                start() {
                    if (this.n < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    this.stop();
                    this.timer = setInterval(() => this.go(this.i + 1), 6000);
                },
                stop() { clearInterval(this.timer); }
            }"
            x-init="start()"
            @mouseenter="stop()"
            @mouseleave="start()"
            role="region"
            aria-roledescription="carousel"
            aria-label="Banner promosi"
        >
            <div class="relative aspect-[16/9] sm:aspect-[16/6]">
                <?php foreach ($banners as $idx => $b): ?>
                    <?php
                    $tag = !empty($b['link_url']) ? 'a' : 'div';
                    $href = !empty($b['link_url']) ? ' href="' . e($b['link_url']) . '"' : '';
                    ?>
                    <<?= $tag ?><?= $href ?>
                        <?= $idx > 0 ? 'x-cloak' : '' ?>
                        x-show="i === <?= (int) $idx ?>"
                        x-transition.opacity.duration.300ms
                        class="absolute inset-0 block"
                        aria-label="<?= e($b['title']) ?>"
                    >
                        <img
                            src="<?= e($b['image_path']) ?>"
                            alt=""
                            <?= $idx > 0 ? 'loading="lazy"' : 'fetchpriority="high"' ?>
                            class="h-full w-full object-cover"
                        >
                        <span class="absolute inset-x-0 bottom-0 block bg-gradient-to-t from-zinc-950/70 via-zinc-950/30 to-transparent px-5 pb-10 pt-16 sm:px-10 sm:pb-12">
                            <span class="block max-w-xl text-xl font-bold leading-tight tracking-tight text-white sm:text-3xl"><?= e($b['title']) ?></span>
                            <?php if (!empty($b['subtitle'])): ?>
                                <span class="mt-1.5 block max-w-xl text-sm text-white/85 sm:text-base"><?= e($b['subtitle']) ?></span>
                            <?php endif; ?>
                        </span>
                    </<?= $tag ?>>
                <?php endforeach; ?>
            </div>

            <?php if ($bannerCount > 1): ?>
                <button type="button" @click="go(i - 1)" class="absolute left-3 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-zinc-900 shadow-sm transition-colors hover:bg-white sm:flex" aria-label="Banner sebelumnya">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <button type="button" @click="go(i + 1)" class="absolute right-3 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-zinc-900 shadow-sm transition-colors hover:bg-white sm:flex" aria-label="Banner berikutnya">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </button>

                <div class="absolute inset-x-0 bottom-3 flex justify-center gap-2">
                    <?php for ($d = 0; $d < $bannerCount; $d++): ?>
                        <button
                            type="button"
                            @click="go(<?= $d ?>)"
                            :class="i === <?= $d ?> ? 'w-6 bg-white' : 'w-2 bg-white/60 hover:bg-white/80'"
                            :aria-current="i === <?= $d ?>"
                            class="h-2 rounded-full transition-all"
                            aria-label="Banner <?= $d + 1 ?>"
                        ></button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="rounded-2xl bg-yellow-400 px-6 py-10 sm:px-10 sm:py-14 lg:px-16 lg:py-20">
            <p class="text-sm font-medium text-zinc-900/70">Toko online terpercaya</p>
            <h1 class="mt-3 max-w-2xl text-3xl font-bold leading-tight tracking-tight text-zinc-900 sm:text-4xl lg:text-5xl">
                Produk pilihan, harga jelas, sampai di depan pintu.
            </h1>
            <p class="mt-4 max-w-lg text-base leading-relaxed text-zinc-900/75">
                Pilih produk, hitung ongkir sesuai alamatmu, lalu bayar dengan metode favorit. Tanpa biaya tersembunyi.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="/produk" class="inline-flex h-12 items-center justify-center rounded-lg bg-zinc-900 px-6 text-sm font-medium text-white transition-colors hover:bg-zinc-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 focus-visible:ring-offset-2 focus-visible:ring-offset-yellow-400">Belanja sekarang</a>
                <?php if (!empty($categories)): ?>
                    <a href="#kategori" class="inline-flex h-12 items-center justify-center rounded-lg border border-zinc-900/20 px-6 text-sm font-medium text-zinc-900 transition-colors hover:bg-yellow-300">Lihat kategori</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- Kategori -->
<?php if (!empty($categories)): ?>
<section id="kategori" class="mx-auto mt-14 max-w-7xl scroll-mt-32 px-4 sm:px-6 lg:px-8">
    <div class="flex items-end justify-between gap-4">
        <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">Belanja per kategori</h2>
    </div>
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <?php foreach ($categories as $category): ?>
            <a href="/kategori/<?= e($category['slug']) ?>" class="group flex items-center justify-between gap-3 rounded-xl border border-zinc-200 p-4 transition-colors hover:border-yellow-400 hover:bg-yellow-50">
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-zinc-900"><?= e($category['name']) ?></span>
                    <?php if (isset($category['product_count'])): ?>
                        <span class="mt-0.5 block text-xs text-zinc-500"><?= (int) $category['product_count'] ?> produk</span>
                    <?php endif; ?>
                </span>
                <svg class="h-4 w-4 shrink-0 text-zinc-400 transition-transform group-hover:translate-x-0.5 group-hover:text-zinc-900" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Produk terbaru -->
<section class="mx-auto mt-14 max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="flex items-end justify-between gap-4">
        <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">Produk terbaru</h2>
        <a href="/produk?sort=terbaru" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Lihat semua</a>
    </div>
    <?php if (empty($latestProducts)): ?>
        <div class="mt-5 rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center">
            <p class="text-sm font-medium text-zinc-900">Belum ada produk</p>
            <p class="mt-1 text-sm text-zinc-500">Produk akan tampil di sini setelah ditambahkan dari panel admin.</p>
        </div>
    <?php else: ?>
        <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3 lg:grid-cols-4">
            <?php foreach ($latestProducts as $product): ?>
                <?php include __DIR__ . '/../components/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Produk populer -->
<?php if (!empty($popularProducts)): ?>
<section class="mx-auto mt-14 max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="flex items-end justify-between gap-4">
        <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">Paling banyak dibeli</h2>
        <a href="/produk?sort=populer" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Lihat semua</a>
    </div>
    <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3 lg:grid-cols-4">
        <?php foreach ($popularProducts as $product): ?>
            <?php include __DIR__ . '/../components/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Keunggulan -->
<section class="mx-auto mt-16 max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid gap-6 rounded-2xl border border-zinc-200 p-6 sm:grid-cols-3 sm:p-8">
        <?php foreach ($perks as $perk): ?>
            <div class="flex gap-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-yellow-100 text-yellow-700">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $perk['icon'] ?></svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-zinc-900"><?= e($perk['title']) ?></p>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-500"><?= e($perk['text']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
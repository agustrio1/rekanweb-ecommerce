<?php
use App\Helpers\HtmlSanitizer;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$related = $related ?? [];
$images = $product['images'];
$variants = $product['variants'];

// Data untuk Alpine (hanya untuk tampilan; backend tetap validasi ulang).
$variantsJs = array_map(function (array $v): array {
    $hasSale = $v['discount_price'] !== null && $v['discount_price'] > 0 && $v['discount_price'] < $v['price'];
    return [
        'id' => $v['id'],
        'name' => $v['variant_name'],
        'sku' => $v['sku'],
        'final' => $hasSale ? $v['discount_price'] : $v['price'],
        'original' => $hasSale ? $v['price'] : null,
        'stock' => $v['stock'],
    ];
}, $variants);

// Variasi awal: yang pertama masih ada stoknya.
$defaultId = $variants[0]['id'];
foreach ($variants as $v) {
    if ($v['stock'] > 0) {
        $defaultId = $v['id'];
        break;
    }
}

$imagesJs = array_values($images);
$multiVariant = count($variants) > 1;

$dimensions = array_filter([$product['length_cm'], $product['width_cm'], $product['height_cm']]);
$dimensionText = count($dimensions) === 3
    ? (int) $product['length_cm'] . ' × ' . (int) $product['width_cm'] . ' × ' . (int) $product['height_cm'] . ' cm'
    : '';
$weightText = $product['weight_gram'] > 0 ? number_format($product['weight_gram'], 0, ',', '.') . ' gram' : '';

// Deskripsi: HTML dari rich editor (dibersihkan lagi) atau teks biasa dari data lama.
$rawDescription = trim((string) $product['description']);
$descriptionHtml = '';
if ($rawDescription !== '') {
    $descriptionHtml = $rawDescription === strip_tags($rawDescription)
        ? nl2br(e($rawDescription))
        : HtmlSanitizer::clean($rawDescription);
}
?>

<div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8 lg:pt-8">
    <!-- Breadcrumb -->
    <nav class="text-sm text-zinc-500" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <li><a href="/" class="hover:text-zinc-900">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="/kategori/<?= e($product['category_slug']) ?>" class="hover:text-zinc-900"><?= e($product['category_name']) ?></a></li>
            <li aria-hidden="true">/</li>
            <li class="truncate text-zinc-900" aria-current="page"><?= e($product['name']) ?></li>
        </ol>
    </nav>

    <div
        class="mt-6 grid gap-8 lg:grid-cols-2 lg:gap-12"
        x-data="{
            variants: <?= e(json_encode($variantsJs, JSON_UNESCAPED_UNICODE)) ?>,
            images: <?= e(json_encode($imagesJs, JSON_UNESCAPED_UNICODE)) ?>,
            selectedId: <?= (int) $defaultId ?>,
            qty: 1,
            active: 0,
            get v() { return this.variants.find(x => x.id === this.selectedId); },
            get soldOut() { return this.v.stock <= 0; },
            money(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); },
            pick(id) { this.selectedId = id; this.qty = 1; },
            dec() { if (this.qty > 1) this.qty--; },
            inc() { if (this.qty < this.v.stock) this.qty++; },
            clamp() {
                this.qty = Math.floor(Number(this.qty)) || 1;
                if (this.qty < 1) this.qty = 1;
                if (this.qty > this.v.stock) this.qty = Math.max(1, this.v.stock);
            }
        }"
    >
        <!-- Galeri -->
        <div>
            <div class="relative aspect-square overflow-hidden rounded-2xl bg-zinc-100">
                <?php if (!empty($imagesJs)): ?>
                    <template x-for="(src, i) in images" :key="i">
                        <img
                            x-show="active === i"
                            :src="src"
                            alt="<?= e($product['name']) ?>"
                            class="h-full w-full object-cover"
                        >
                    </template>
                <?php else: ?>
                    <div class="flex h-full w-full items-center justify-center text-zinc-300">
                        <svg class="h-14 w-14" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($imagesJs) > 1): ?>
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    <template x-for="(src, i) in images" :key="'t' + i">
                        <button
                            type="button"
                            @click="active = i"
                            :class="active === i ? 'border-zinc-900' : 'border-transparent opacity-70 hover:opacity-100'"
                            class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 bg-zinc-100 transition"
                            :aria-label="'Lihat gambar ' + (i + 1)"
                        >
                            <img :src="src" alt="" loading="lazy" class="h-full w-full object-cover">
                        </button>
                    </template>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info & pembelian -->
        <div>
            <a href="/kategori/<?= e($product['category_slug']) ?>" class="text-sm font-medium text-yellow-700 hover:underline"><?= e($product['category_name']) ?></a>
            <h1 class="mt-1 text-2xl font-bold leading-tight tracking-tight sm:text-3xl"><?= e($product['name']) ?></h1>

            <!-- Harga -->
            <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="text-3xl font-bold tracking-tight" x-text="money(v.final)"></span>
                <template x-if="v.original">
                    <span class="text-base text-zinc-400 line-through" x-text="money(v.original)"></span>
                </template>
                <template x-if="v.original">
                    <span class="rounded-md bg-yellow-400 px-2 py-0.5 text-xs font-semibold text-zinc-900" x-text="'-' + Math.round((v.original - v.final) / v.original * 100) + '%'"></span>
                </template>
            </div>

            <form action="/keranjang/tambah" method="post" class="mt-6">
                <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="variant_id" :value="selectedId">

                <!-- Variasi -->
                <?php if ($multiVariant): ?>
                    <fieldset>
                        <legend class="text-sm font-medium text-zinc-900">
                            Pilih variasi: <span class="font-normal text-zinc-500" x-text="v.name"></span>
                        </legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <template x-for="item in variants" :key="item.id">
                                <button
                                    type="button"
                                    @click="pick(item.id)"
                                    :aria-pressed="selectedId === item.id"
                                    :class="[
                                        selectedId === item.id ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 text-zinc-800 hover:border-zinc-400',
                                        item.stock <= 0 && selectedId !== item.id ? 'text-zinc-400 line-through' : ''
                                    ]"
                                    class="inline-flex h-10 items-center rounded-lg border px-4 text-sm font-medium transition-colors"
                                    x-text="item.name"
                                ></button>
                            </template>
                        </div>
                    </fieldset>
                <?php endif; ?>

                <!-- Stok -->
                <p class="mt-4 text-sm" aria-live="polite">
                    <template x-if="soldOut"><span class="font-medium text-rose-600">Stok habis</span></template>
                    <template x-if="!soldOut && v.stock <= 5"><span class="font-medium text-amber-700" x-text="'Sisa ' + v.stock + ' lagi'"></span></template>
                    <template x-if="!soldOut && v.stock > 5"><span class="text-zinc-500" x-text="'Stok tersedia (' + v.stock + ')'"></span></template>
                </p>

                <!-- Jumlah -->
                <div class="mt-5">
                    <label for="qty" class="text-sm font-medium text-zinc-900">Jumlah</label>
                    <div class="mt-2 inline-flex items-center rounded-lg border border-zinc-200">
                        <button type="button" @click="dec()" :disabled="soldOut || qty <= 1" class="flex h-11 w-11 items-center justify-center text-zinc-700 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:text-zinc-300" aria-label="Kurangi jumlah">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg>
                        </button>
                        <input id="qty" name="qty" type="number" inputmode="numeric" min="1" :max="v.stock" x-model.number="qty" @change="clamp()" :disabled="soldOut" class="h-11 w-14 border-x border-zinc-200 text-center text-sm font-medium [appearance:textfield] focus:outline-none disabled:bg-zinc-50 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                        <button type="button" @click="inc()" :disabled="soldOut || qty >= v.stock" class="flex h-11 w-11 items-center justify-center text-zinc-700 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:text-zinc-300" aria-label="Tambah jumlah">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Subtotal & tombol -->
                <p class="mt-5 text-sm text-zinc-500" x-show="!soldOut">
                    Subtotal: <span class="font-semibold text-zinc-900" x-text="money(v.final * qty)"></span>
                </p>

                <button
                    type="submit"
                    :disabled="soldOut"
                    class="mt-4 inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-zinc-200 disabled:text-zinc-500"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                    <span x-text="soldOut ? 'Stok habis' : 'Tambah ke keranjang'"></span>
                </button>
            </form>

            <!-- Rincian -->
            <dl class="mt-8 divide-y divide-zinc-200 border-y border-zinc-200 text-sm">
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-zinc-500">SKU</dt>
                    <dd class="font-medium text-zinc-900" x-text="v.sku"></dd>
                </div>
                <?php if ($weightText !== ''): ?>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-zinc-500">Berat</dt>
                        <dd class="font-medium text-zinc-900"><?= e($weightText) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($dimensionText !== ''): ?>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-zinc-500">Dimensi paket</dt>
                        <dd class="font-medium text-zinc-900"><?= e($dimensionText) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-zinc-500">Kategori</dt>
                    <dd class="font-medium text-zinc-900"><?= e($product['category_name']) ?></dd>
                </div>
            </dl>

            <?php if ($descriptionHtml !== ''): ?>
                <div class="mt-8">
                    <h2 class="text-base font-semibold">Deskripsi</h2>
                    <div class="rich-content mt-3 text-sm leading-relaxed text-zinc-600"><?= $descriptionHtml ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Rekomendasi -->
<?php if (!empty($related)): ?>
<section class="mx-auto mt-16 max-w-7xl px-4 sm:px-6 lg:px-8">
    <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">Produk lain di <?= e($product['category_name']) ?></h2>
    <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3 lg:grid-cols-4">
        <?php foreach ($related as $item): ?>
            <?php
            // product-card.php membaca variabel $product, jadi tukar sementara.
            $currentProduct = $product;
            $product = $item;
            include __DIR__ . '/../components/product-card.php';
            $product = $currentProduct;
            ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
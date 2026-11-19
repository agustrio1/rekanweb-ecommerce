<?php
$price = (int) ($product['price'] ?? 0);
$salePrice = isset($product['sale_price']) && $product['sale_price'] !== null ? (int) $product['sale_price'] : 0;
$hasSale = $salePrice > 0 && $salePrice < $price;
$finalPrice = $hasSale ? $salePrice : $price;
$discountPercent = $hasSale ? (int) round((($price - $salePrice) / $price) * 100) : 0;
$stock = (int) ($product['stock'] ?? 0);
$soldOut = $stock <= 0;
$image = trim((string) ($product['image'] ?? ''));
$imageSrc = $image !== '' ? \App\Helpers\ImageUploader::thumb($image) : '';
?>
<a href="/produk/<?= e($product['slug']) ?>" class="group block">
    <div class="relative aspect-[4/5] overflow-hidden rounded-xl bg-zinc-100">
        <?php if ($image !== ''): ?>
            <img
                src="<?= e($imageSrc) ?>"
                alt="<?= e($product['name']) ?>"
                loading="lazy"
                decoding="async"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105 <?= $soldOut ? 'opacity-50' : '' ?>"
            >
        <?php else: ?>
            <div class="flex h-full w-full items-center justify-center text-zinc-300">
                <svg class="h-10 w-10" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
            </div>
        <?php endif; ?>

        <?php if ($hasSale && !$soldOut): ?>
            <span class="absolute left-2 top-2 rounded-md bg-yellow-400 px-2 py-0.5 text-xs font-semibold text-zinc-900">-<?= $discountPercent ?>%</span>
        <?php endif; ?>
        <?php if ($soldOut): ?>
            <span class="absolute left-2 top-2 rounded-md bg-zinc-900 px-2 py-0.5 text-xs font-medium text-white">Stok habis</span>
        <?php elseif ($stock <= 5): ?>
            <span class="absolute bottom-2 left-2 rounded-md bg-white/95 px-2 py-0.5 text-xs font-medium text-zinc-700">Sisa <?= $stock ?></span>
        <?php endif; ?>
    </div>

    <div class="mt-3 space-y-1">
        <?php if (!empty($product['category_name'])): ?>
            <p class="text-xs text-zinc-500"><?= e($product['category_name']) ?></p>
        <?php endif; ?>
        <h3 class="line-clamp-2 text-sm font-medium leading-snug text-zinc-900 group-hover:text-yellow-700"><?= e($product['name']) ?></h3>
        <div class="flex flex-wrap items-baseline gap-x-2">
            <span class="text-sm font-semibold text-zinc-900">Rp <?= number_format($finalPrice, 0, ',', '.') ?></span>
            <?php if ($hasSale): ?>
                <span class="text-xs text-zinc-400 line-through">Rp <?= number_format($price, 0, ',', '.') ?></span>
            <?php endif; ?>
        </div>
    </div>
</a>
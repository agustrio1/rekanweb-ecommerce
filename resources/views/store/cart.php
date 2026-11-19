<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['csrf_token'];

$lines = $lines ?? [];
$subtotal = (int) ($subtotal ?? 0);
$itemCount = (int) ($itemCount ?? 0);
$hasUnavailable = !empty($hasUnavailable);
$hasAdjusted = !empty($hasAdjusted);
$canCheckout = !empty($canCheckout);

$money = fn(int $n): string => 'Rp ' . number_format($n, 0, ',', '.');
?>

<div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8 lg:pt-8">
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Keranjang belanja</h1>

    <?php if (empty($lines)): ?>
        <div class="mt-8 flex flex-col items-center rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 text-yellow-700">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-900">Keranjangmu masih kosong</p>
            <p class="mt-1 max-w-sm text-sm text-zinc-500">Pilih produk yang kamu suka, lalu tambahkan ke keranjang.</p>
            <a href="/produk" class="mt-5 inline-flex h-11 items-center rounded-lg bg-yellow-400 px-5 text-sm font-medium text-zinc-900 hover:bg-yellow-300">Mulai belanja</a>
        </div>
    <?php else: ?>
        <?php if ($hasUnavailable || $hasAdjusted): ?>
            <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
                <?php if ($hasUnavailable): ?>
                    Ada produk yang sudah tidak tersedia. Hapus dari keranjang untuk melanjutkan ke checkout.
                <?php else: ?>
                    Jumlah beberapa produk disesuaikan dengan stok terbaru.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mt-6 grid gap-8 lg:grid-cols-3 lg:gap-10">
            <!-- Daftar item -->
            <section class="lg:col-span-2" aria-label="Daftar produk">
                <ul class="divide-y divide-zinc-200 border-y border-zinc-200">
                    <?php foreach ($lines as $line): ?>
                        <li class="flex gap-4 py-5">
                            <a href="/produk/<?= e($line['slug']) ?>" class="block h-24 w-20 shrink-0 overflow-hidden rounded-lg bg-zinc-100 sm:h-28 sm:w-24">
                                <?php if ($line['image'] !== ''): ?>
                                    <img src="<?= e($line['image']) ?>" alt="<?= e($line['name']) ?>" loading="lazy" class="h-full w-full object-cover <?= $line['available'] ? '' : 'opacity-50' ?>">
                                <?php else: ?>
                                    <span class="flex h-full w-full items-center justify-center text-zinc-300">
                                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
                                    </span>
                                <?php endif; ?>
                            </a>

                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="/produk/<?= e($line['slug']) ?>" class="line-clamp-2 text-sm font-medium leading-snug text-zinc-900 hover:text-yellow-700"><?= e($line['name']) ?></a>
                                        <p class="mt-0.5 text-sm text-zinc-500"><?= e($line['variant_name']) ?></p>
                                    </div>
                                    <p class="shrink-0 text-sm font-semibold text-zinc-900">
                                        <?= $line['available'] ? e($money($line['line_total'])) : '-' ?>
                                    </p>
                                </div>

                                <div class="mt-1 flex flex-wrap items-baseline gap-x-2 text-sm">
                                    <span class="text-zinc-700"><?= e($money($line['unit_price'])) ?></span>
                                    <?php if ($line['original_price'] !== null): ?>
                                        <span class="text-xs text-zinc-400 line-through"><?= e($money($line['original_price'])) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$line['available']): ?>
                                    <p class="mt-2 text-xs font-medium text-rose-600">Tidak tersedia atau stok habis</p>
                                <?php elseif ($line['adjusted']): ?>
                                    <p class="mt-2 text-xs font-medium text-amber-700">Jumlah disesuaikan, stok tersisa <?= (int) $line['stock'] ?></p>
                                <?php elseif ($line['stock'] <= 5): ?>
                                    <p class="mt-2 text-xs font-medium text-amber-700">Sisa <?= (int) $line['stock'] ?></p>
                                <?php endif; ?>

                                <div class="mt-auto flex items-center justify-between gap-3 pt-3">
                                    <?php if ($line['available']): ?>
                                        <form action="/keranjang/ubah" method="post" class="inline-flex items-center rounded-lg border border-zinc-200">
                                            <input type="hidden" name="_token" value="<?= e($token) ?>">
                                            <input type="hidden" name="item_id" value="<?= (int) $line['item_id'] ?>">
                                            <button type="submit" name="qty" value="<?= $line['qty'] - 1 ?>" <?= $line['qty'] <= 1 ? 'disabled' : '' ?> class="flex h-10 w-10 items-center justify-center text-zinc-700 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:text-zinc-300" aria-label="Kurangi jumlah">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg>
                                            </button>
                                            <span class="w-10 text-center text-sm font-medium" aria-live="polite"><?= (int) $line['qty'] ?></span>
                                            <button type="submit" name="qty" value="<?= $line['qty'] + 1 ?>" <?= $line['qty'] >= $line['stock'] ? 'disabled' : '' ?> class="flex h-10 w-10 items-center justify-center text-zinc-700 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:text-zinc-300" aria-label="Tambah jumlah">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span></span>
                                    <?php endif; ?>

                                    <form action="/keranjang/hapus" method="post">
                                        <input type="hidden" name="_token" value="<?= e($token) ?>">
                                        <input type="hidden" name="item_id" value="<?= (int) $line['item_id'] ?>">
                                        <button type="submit" class="inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm text-zinc-500 hover:bg-zinc-100 hover:text-rose-600">
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <a href="/produk" class="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-600 hover:text-zinc-900">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    Lanjut belanja
                </a>
            </section>

            <!-- Ringkasan -->
            <aside class="lg:col-span-1" aria-label="Ringkasan belanja">
                <div class="rounded-xl border border-zinc-200 p-5 lg:sticky lg:top-36">
                    <h2 class="text-base font-semibold">Ringkasan belanja</h2>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Subtotal (<?= $itemCount ?> barang)</dt>
                            <dd class="font-medium text-zinc-900"><?= e($money($subtotal)) ?></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">Ongkos kirim</dt>
                            <dd class="text-zinc-500">Dihitung saat checkout</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex items-baseline justify-between gap-4 border-t border-zinc-200 pt-4">
                        <span class="text-sm font-medium">Total sementara</span>
                        <span class="text-xl font-bold tracking-tight"><?= e($money($subtotal)) ?></span>
                    </div>

                    <?php if ($canCheckout): ?>
                        <a href="/checkout" class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">Lanjut ke checkout</a>
                    <?php else: ?>
                        <button type="button" disabled class="mt-5 inline-flex h-12 w-full cursor-not-allowed items-center justify-center rounded-lg bg-zinc-200 px-6 text-sm font-semibold text-zinc-500">Lanjut ke checkout</button>
                    <?php endif; ?>

                    <p class="mt-3 text-xs leading-relaxed text-zinc-500">Harga dan stok dicek ulang saat checkout untuk memastikan tagihanmu akurat.</p>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>
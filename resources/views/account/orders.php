<?php
$orders = $orders ?? [];
$months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

$formatDate = function (string $value) use ($months): string {
    $t = strtotime($value);
    return date('j', $t) . ' ' . $months[(int) date('n', $t)] . ' ' . date('Y', $t);
};

include __DIR__ . '/../components/account-nav.php';
?>

<div class="mx-auto max-w-3xl px-4 pt-6 sm:px-6">
    <?php if (empty($orders)): ?>
        <div class="flex flex-col items-center rounded-xl border border-dashed border-zinc-300 px-6 py-14 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 text-yellow-700">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-900">Belum ada pesanan</p>
            <p class="mt-1 max-w-sm text-sm text-zinc-500">Pesananmu akan muncul di sini lengkap dengan status pembayaran dan pengiriman.</p>
            <a href="/produk" class="mt-5 inline-flex h-11 items-center rounded-lg bg-yellow-400 px-5 text-sm font-medium text-zinc-900 hover:bg-yellow-300">Mulai belanja</a>
        </div>
    <?php else: ?>
        <ul class="space-y-3">
            <?php foreach ($orders as $order): ?>
                <?php [$label, $badge] = \App\Models\Order::statusBadge($order); ?>
                <li>
                    <a href="/pesanan/<?= e(rawurlencode($order['order_number'])) ?>" class="block rounded-xl border border-zinc-200 p-4 transition-colors hover:border-zinc-300 hover:bg-zinc-50 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-zinc-900"><?= e($order['order_number']) ?></p>
                                <p class="mt-0.5 text-xs text-zinc-500"><?= e($formatDate($order['created_at'])) ?> &middot; <?= (int) $order['item_count'] ?> barang</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center rounded-md border px-2 py-0.5 text-xs font-medium <?= $badge ?>"><?= e($label) ?></span>
                        </div>
                        <p class="mt-3 text-sm text-zinc-500">Total <span class="font-semibold text-zinc-900">Rp <?= number_format((int) $order['total_amount'], 0, ',', '.') ?></span></p>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
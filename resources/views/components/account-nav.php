<?php
$active = $active ?? 'orders';
$tabs = [
    'orders' => ['label' => 'Pesanan saya', 'href' => '/akun'],
    'addresses' => ['label' => 'Alamat', 'href' => '/akun/alamat'],
    'profile' => ['label' => 'Profil', 'href' => '/akun/profil'],
    'password' => ['label' => 'Ubah password', 'href' => '/akun/password'],
];
?>
<div class="mx-auto max-w-3xl px-4 pt-6 sm:px-6 lg:pt-8">
    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Akun saya</h1>
    <p class="mt-1 text-sm text-zinc-500"><?= e($_SESSION['user']['name'] ?? '') ?> &middot; <?= e($_SESSION['user']['email'] ?? '') ?></p>

    <nav class="-mx-4 mt-6 overflow-x-auto border-b border-zinc-200 px-4 sm:mx-0 sm:px-0" aria-label="Menu akun">
        <ul class="flex w-max gap-6 sm:w-auto">
            <?php foreach ($tabs as $key => $tab): ?>
                <li>
                    <a href="<?= e($tab['href']) ?>"
                       class="-mb-px inline-flex h-11 items-center border-b-2 text-sm font-medium transition-colors <?= $active === $key ? 'border-yellow-400 text-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-900' ?>"
                       <?= $active === $key ? 'aria-current="page"' : '' ?>><?= e($tab['label']) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</div>
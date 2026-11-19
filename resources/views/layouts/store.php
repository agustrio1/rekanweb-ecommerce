<?php
use App\Models\Category;
use App\Models\Page;
use App\Models\Setting;

$s = Setting::many([
    'store_name', 'store_tagline', 'store_logo', 'store_favicon',
    'store_email', 'store_whatsapp',
    'social_instagram', 'social_facebook', 'social_tiktok',
]);

$storeName = $storeName ?? ($s['store_name'] !== '' ? $s['store_name'] : 'RekanWeb Commerce');
$tagline = $s['store_tagline'] !== ''
    ? $s['store_tagline']
    : 'Belanja online dengan harga jelas, pembayaran aman, dan pengiriman ke seluruh Indonesia.';
$metaDescription = $metaDescription ?? $tagline;
$navCategories = $navCategories ?? Category::active();
$cartCount = (int) ($cartCount ?? 0);
$searchQuery = trim((string) ($_GET['q'] ?? ''));

$logo = $s['store_logo'];
$favicon = $s['store_favicon'];
$whatsapp = preg_replace('/\D+/', '', $s['store_whatsapp']);
$socials = array_filter([
    'Instagram' => $s['social_instagram'],
    'Facebook' => $s['social_facebook'],
    'TikTok' => $s['social_tiktok'],
]);
$infoPages = Page::activeLinks();

$sessionUser = $_SESSION['user'] ?? null;
$isLoggedIn = !empty($sessionUser);
$isAdmin = $isLoggedIn && ($sessionUser['role'] ?? '') === 'admin';
$firstName = $isLoggedIn ? (explode(' ', trim((string) $sessionUser['name']))[0] ?: 'Akun') : '';

$initial = mb_strtoupper(mb_substr($storeName, 0, 1));
$mark = $logo !== ''
    ? '<img src="' . e($logo) . '" alt="' . e($storeName) . '" class="h-8 w-auto max-w-[9rem] object-contain">'
    : '<span class="flex h-8 w-8 items-center justify-center rounded-md bg-yellow-400 text-sm font-bold text-zinc-900">' . e($initial) . '</span>';
$showName = $logo === '';

$iconUser = '<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
$iconSearch = '<svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= \App\Helpers\Seo::head((string) $title, $storeName, (string) $metaDescription, $logo, $favicon, $s['store_tagline']) ?>
    <style>[x-cloak]{display:none !important}</style>
    <link rel="stylesheet" href="<?= vite_css() ?>">
    <script type="module" src="<?= vite_js() ?>"></script>
</head>
<body class="bg-white font-sans text-zinc-900 antialiased">
<div
    x-data="{ menu: false }"
    x-effect="document.body.classList.toggle('overflow-hidden', menu)"
    @keydown.escape.window="menu = false"
    class="flex min-h-screen flex-col"
>
    <!-- Header -->
    <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
            <button type="button" @click="menu = true" class="-ml-2 rounded-md p-2 text-zinc-700 hover:bg-zinc-100 lg:hidden" aria-label="Buka menu">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
            </button>

            <a href="/" class="flex shrink-0 items-center gap-2.5">
                <?= $mark ?>
                <?php if ($showName): ?>
                    <span class="hidden text-sm font-semibold tracking-tight sm:block"><?= e($storeName) ?></span>
                <?php endif; ?>
            </a>

            <!-- Search desktop -->
            <form action="/produk" method="get" role="search" class="mx-6 hidden max-w-xl flex-1 lg:block">
                <label for="search-desktop" class="sr-only">Cari produk</label>
                <div class="relative">
                    <?= $iconSearch ?>
                    <input id="search-desktop" type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Cari produk..." class="h-10 w-full rounded-lg border border-zinc-200 bg-zinc-50 pl-9 pr-3 text-sm placeholder:text-zinc-400 focus:border-yellow-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400/30">
                </div>
            </form>

            <div class="ml-auto flex items-center gap-1">
                <?php if ($isLoggedIn): ?>
                    <!-- Menu akun desktop -->
                    <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative hidden sm:block">
                        <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu" class="inline-flex h-10 items-center gap-2 rounded-lg px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100">
                            <?= $iconUser ?>
                            <span class="max-w-[8rem] truncate"><?= e($firstName) ?></span>
                            <svg class="h-4 w-4 text-zinc-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div x-cloak x-show="open" x-transition.opacity.duration.100ms role="menu" class="absolute right-0 top-full mt-2 w-56 overflow-hidden rounded-xl border border-zinc-200 bg-white py-1 shadow-lg">
                            <div class="border-b border-zinc-100 px-4 py-3">
                                <p class="truncate text-sm font-medium text-zinc-900"><?= e($sessionUser['name']) ?></p>
                                <p class="truncate text-xs text-zinc-500"><?= e($sessionUser['email']) ?></p>
                            </div>
                            <a href="/akun" role="menuitem" class="block px-4 py-2.5 text-sm text-zinc-700 hover:bg-zinc-50">Pesanan saya</a>
                            <a href="/akun/profil" role="menuitem" class="block px-4 py-2.5 text-sm text-zinc-700 hover:bg-zinc-50">Profil</a>
                            <a href="/akun/password" role="menuitem" class="block px-4 py-2.5 text-sm text-zinc-700 hover:bg-zinc-50">Ubah password</a>
                            <?php if ($isAdmin): ?>
                                <a href="/admin" role="menuitem" class="block px-4 py-2.5 text-sm text-zinc-700 hover:bg-zinc-50">Panel admin</a>
                            <?php endif; ?>
                            <form method="POST" action="/logout" class="border-t border-zinc-100">
                                <?= csrf_field() ?>
                                <button type="submit" role="menuitem" class="block w-full px-4 py-2.5 text-left text-sm text-rose-600 hover:bg-zinc-50">Keluar</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/login" class="hidden h-10 items-center gap-2 rounded-lg px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 sm:inline-flex">
                        <?= $iconUser ?>
                        Masuk
                    </a>
                <?php endif; ?>

                <a href="/keranjang" class="relative rounded-lg p-2.5 text-zinc-700 hover:bg-zinc-100" aria-label="Keranjang, <?= $cartCount ?> item">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                    <?php if ($cartCount > 0): ?>
                        <span class="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-yellow-400 px-1 text-[10px] font-bold text-zinc-900"><?= $cartCount > 99 ? '99+' : $cartCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Search mobile -->
        <form action="/produk" method="get" role="search" class="border-t border-zinc-100 px-4 py-2.5 sm:px-6 lg:hidden">
            <label for="search-mobile" class="sr-only">Cari produk</label>
            <div class="relative">
                <?= $iconSearch ?>
                <input id="search-mobile" type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Cari produk..." class="h-10 w-full rounded-lg border border-zinc-200 bg-zinc-50 pl-9 pr-3 text-sm placeholder:text-zinc-400 focus:border-yellow-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400/30">
            </div>
        </form>

        <!-- Kategori desktop -->
        <?php if (!empty($navCategories)): ?>
            <nav class="hidden border-t border-zinc-100 lg:block" aria-label="Kategori">
                <div class="mx-auto flex h-11 max-w-7xl items-center gap-6 px-8 text-sm">
                    <a href="/produk" class="font-medium text-zinc-900 hover:text-yellow-700">Semua produk</a>
                    <?php foreach ($navCategories as $cat): ?>
                        <a href="/kategori/<?= e($cat['slug']) ?>" class="text-zinc-600 hover:text-zinc-900"><?= e($cat['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </nav>
        <?php endif; ?>
    </header>

    <!-- Drawer mobile -->
    <div x-cloak x-show="menu" @click="menu = false" x-transition.opacity.duration.200ms class="fixed inset-0 z-40 bg-zinc-950/50 lg:hidden" aria-hidden="true"></div>
    <aside
        x-cloak
        x-show="menu"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 z-50 flex w-80 max-w-[85%] flex-col bg-white shadow-xl lg:hidden"
        aria-label="Menu"
    >
        <div class="flex h-16 items-center justify-between border-b border-zinc-200 px-4">
            <?php if ($logo !== ''): ?>
                <?= $mark ?>
            <?php else: ?>
                <span class="text-sm font-semibold"><?= e($storeName) ?></span>
            <?php endif; ?>
            <button type="button" @click="menu = false" class="rounded-md p-2 text-zinc-500 hover:bg-zinc-100" aria-label="Tutup menu">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 py-4">
            <p class="mb-2 px-3 text-xs font-medium text-zinc-500">Belanja</p>
            <a href="/produk" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-900 hover:bg-zinc-100">Semua produk</a>
            <?php foreach ($navCategories as $cat): ?>
                <a href="/kategori/<?= e($cat['slug']) ?>" class="block rounded-lg px-3 py-2.5 text-sm text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900"><?= e($cat['name']) ?></a>
            <?php endforeach; ?>

            <?php if ($isLoggedIn): ?>
                <p class="mb-2 mt-6 px-3 text-xs font-medium text-zinc-500">Akun</p>
                <a href="/akun" class="block rounded-lg px-3 py-2.5 text-sm text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900">Pesanan saya</a>
                <a href="/akun/profil" class="block rounded-lg px-3 py-2.5 text-sm text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900">Profil</a>
                <a href="/akun/password" class="block rounded-lg px-3 py-2.5 text-sm text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900">Ubah password</a>
                <?php if ($isAdmin): ?>
                    <a href="/admin" class="block rounded-lg px-3 py-2.5 text-sm text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900">Panel admin</a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($infoPages)): ?>
                <p class="mb-2 mt-6 px-3 text-xs font-medium text-zinc-500">Informasi</p>
                <?php foreach ($infoPages as $p): ?>
                    <a href="/halaman/<?= e(rawurlencode($p['slug'])) ?>" class="block rounded-lg px-3 py-2.5 text-sm text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900"><?= e($p['title']) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>
        <div class="border-t border-zinc-200 p-3">
            <?php if ($isLoggedIn): ?>
                <p class="truncate px-1 pb-3 text-xs text-zinc-500">Masuk sebagai <?= e($sessionUser['email']) ?></p>
                <form method="POST" action="/logout">
                    <?= csrf_field() ?>
                    <button type="submit" class="flex h-11 w-full items-center justify-center rounded-lg border border-zinc-200 text-sm font-medium text-rose-600 hover:bg-zinc-50">Keluar</button>
                </form>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-2">
                    <a href="/login" class="flex h-11 items-center justify-center rounded-lg bg-yellow-400 text-sm font-medium text-zinc-900 hover:bg-yellow-300">Masuk</a>
                    <a href="/register" class="flex h-11 items-center justify-center rounded-lg border border-zinc-200 text-sm font-medium text-zinc-900 hover:bg-zinc-50">Daftar</a>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Konten -->
    <main class="flex-1">
        <?= $content ?>
    </main>

    <!-- Footer -->
    <footer class="mt-16 border-t border-zinc-200 bg-zinc-50">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
            <div>
                <a href="/" class="flex items-center gap-2.5">
                    <?= $mark ?>
                    <?php if ($showName): ?>
                        <span class="text-sm font-semibold"><?= e($storeName) ?></span>
                    <?php endif; ?>
                </a>
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-zinc-500"><?= e($tagline) ?></p>
            </div>
            <div>
                <p class="text-sm font-medium">Belanja</p>
                <ul class="mt-4 space-y-2.5 text-sm text-zinc-500">
                    <li><a href="/produk" class="hover:text-zinc-900">Semua produk</a></li>
                    <li><a href="/keranjang" class="hover:text-zinc-900">Keranjang</a></li>
                </ul>
            </div>
            <div>
                <p class="text-sm font-medium">Akun</p>
                <ul class="mt-4 space-y-2.5 text-sm text-zinc-500">
                    <?php if ($isLoggedIn): ?>
                        <li><a href="/akun" class="hover:text-zinc-900">Pesanan saya</a></li>
                        <li><a href="/akun/profil" class="hover:text-zinc-900">Profil</a></li>
                    <?php else: ?>
                        <li><a href="/login" class="hover:text-zinc-900">Masuk</a></li>
                        <li><a href="/register" class="hover:text-zinc-900">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <p class="text-sm font-medium">Informasi</p>
                <ul class="mt-4 space-y-2.5 text-sm text-zinc-500">
                    <?php foreach ($infoPages as $p): ?>
                        <li><a href="/halaman/<?= e(rawurlencode($p['slug'])) ?>" class="hover:text-zinc-900"><?= e($p['title']) ?></a></li>
                    <?php endforeach; ?>
                    <?php if ($whatsapp !== ''): ?>
                        <li><a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener" class="hover:text-zinc-900">WhatsApp</a></li>
                    <?php endif; ?>
                    <?php if ($s['store_email'] !== ''): ?>
                        <li><a href="mailto:<?= e($s['store_email']) ?>" class="break-all hover:text-zinc-900"><?= e($s['store_email']) ?></a></li>
                    <?php endif; ?>
                    <?php foreach ($socials as $name => $url): ?>
                        <li><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-zinc-900"><?= e($name) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="border-t border-zinc-200">
            <p class="mx-auto max-w-7xl px-4 py-5 text-xs text-zinc-500 sm:px-6 lg:px-8">&copy; <?= date('Y') ?> <?= e($storeName) ?>. Seluruh hak cipta dilindungi.</p>
        </div>
    </footer>

    <?php if ($whatsapp !== ''): ?>
        <a
            href="https://wa.me/<?= e($whatsapp) ?>"
            target="_blank"
            rel="noopener"
            aria-label="Chat WhatsApp"
            class="fixed bottom-[calc(env(safe-area-inset-bottom,0px)+1rem)] right-4 z-30 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-900 text-white shadow-lg transition-colors hover:bg-zinc-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
        </a>
    <?php endif; ?>
</div>
</body>
</html>
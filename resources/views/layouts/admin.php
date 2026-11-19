<?php
$user = $_SESSION['user'] ?? [];
$userName = $user['name'] ?? 'Admin';
$userEmail = $user['email'] ?? '';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));

$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
if ($currentPath === '') {
    $currentPath = '/';
}

$navGroups = [
    'Umum' => [
        [
            'label' => 'Dashboard',
            'href' => '/admin',
            'exact' => true,
            'icon' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        ],
    ],
    'Katalog' => [
        [
            'label' => 'Produk',
            'href' => '/admin/products',
            'exact' => false,
            'icon' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        ],
        [
            'label' => 'Kategori',
            'href' => '/admin/categories',
            'exact' => false,
            'icon' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
        ],
    ],
      'Konten' => [
        [
            'label' => 'Banner',
            'href' => '/admin/banners',
            'exact' => false,
            'icon' => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
        ],
        [
            'label' => 'Halaman',
            'href' => '/admin/pages',
            'exact' => false,
            'icon' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        ],
    ],
    'Penjualan' => [
        [
            'label' => 'Pesanan',
            'href' => '/admin/orders',
            'exact' => false,
            'icon' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        ],
    ],
    'Sistem' => [
        [
            'label' => 'Pengaturan',
            'href' => '/admin/settings',
            'exact' => false,
            'icon' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> - Admin RekanWeb</title>
    <style>[x-cloak]{display:none !important}</style>
    <link rel="stylesheet" href="<?= vite_css() ?>">
    <script type="module" src="<?= vite_js() ?>"></script>
</head>
<body class="bg-zinc-50 font-sans text-zinc-900 antialiased">
    <div
        x-data="{ open: false }"
        x-effect="document.body.classList.toggle('overflow-hidden', open)"
        @keydown.escape.window="open = false"
        class="min-h-screen"
    >
        <!-- Overlay (mobile) -->
        <div
            x-cloak
            x-show="open"
            @click="open = false"
            x-transition.opacity.duration.200ms
            class="fixed inset-0 z-40 bg-zinc-950/50 backdrop-blur-sm lg:hidden"
            aria-hidden="true"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="open ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-zinc-200 bg-white transition-transform duration-200 ease-out lg:translate-x-0"
            aria-label="Navigasi admin"
        >
            <!-- Brand -->
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-zinc-200 px-4">
                <a href="/admin" class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-yellow-400 text-sm font-bold text-zinc-900 shadow-sm">R</span>
                    <span class="leading-tight">
                        <span class="block text-sm font-semibold text-zinc-900">RekanWeb Commerce</span>
                        <span class="block text-xs text-zinc-500">Panel Admin</span>
                    </span>
                </a>
                <button
                    type="button"
                    @click="open = false"
                    class="rounded-md p-2 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 lg:hidden"
                    aria-label="Tutup menu"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                <?php foreach ($navGroups as $groupLabel => $items): ?>
                    <div>
                        <p class="mb-2 px-3 text-xs font-medium text-zinc-500"><?= e($groupLabel) ?></p>
                        <div class="space-y-1">
                            <?php foreach ($items as $item): ?>
                                <?php
                                $isActive = $item['exact']
                                    ? $currentPath === $item['href']
                                    : ($currentPath === $item['href'] || str_starts_with($currentPath, $item['href'] . '/'));
                                $linkClass = $isActive
                                    ? 'bg-yellow-100/70 text-zinc-900'
                                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900';
                                $iconClass = $isActive ? 'text-yellow-600' : 'text-zinc-400 group-hover:text-zinc-600';
                                ?>
                                <a
                                    href="<?= e($item['href']) ?>"
                                    <?= $isActive ? 'aria-current="page"' : '' ?>
                                    class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors <?= $linkClass ?>"
                                >
                                    <svg class="h-4 w-4 shrink-0 transition-colors <?= $iconClass ?>" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $item['icon'] ?></svg>
                                    <?= e($item['label']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </nav>

            <!-- User -->
            <div class="shrink-0 border-t border-zinc-200 p-3">
                <div class="flex items-center gap-3 rounded-lg p-2">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-sm font-semibold text-zinc-700"><?= e($userInitial) ?></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900"><?= e($userName) ?></p>
                        <p class="truncate text-xs text-zinc-500"><?= e($userEmail) ?></p>
                    </div>
                      <form method="POST" action="/logout">
                        <?= csrf_field() ?>
                        <button
                            type="submit"
                            class="rounded-md p-2 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900"
                            title="Keluar"
                            aria-label="Keluar"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="lg:pl-72">
            <!-- Topbar -->
            <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/80 backdrop-blur supports-[backdrop-filter]:bg-white/70">
                <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            @click="open = true"
                            class="-ml-2 rounded-md p-2 text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 lg:hidden"
                            aria-label="Buka menu"
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                        </button>
                        <h1 class="truncate text-base font-semibold tracking-tight text-zinc-900 sm:text-lg"><?= e($title) ?></h1>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <a
                            href="/"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex h-9 items-center gap-2 rounded-lg bg-yellow-400 px-3 text-sm font-medium text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                            <span class="hidden sm:inline">Lihat Toko</span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <?= $content ?>
            </main>
        </div>
    </div>

    <?= $toastHtml ?? '' ?>
</body>
</html>
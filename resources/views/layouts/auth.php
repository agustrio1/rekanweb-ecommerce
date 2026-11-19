<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | <?= e($storeName) ?></title>
    <meta name="robots" content="noindex">
    <style>[x-cloak]{display:none !important}</style>
    <link rel="stylesheet" href="<?= vite_css() ?>">
    <script type="module" src="<?= vite_js() ?>"></script>
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
        <a href="/" class="mx-auto flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-yellow-400 text-sm font-bold text-zinc-900">R</span>
            <span class="text-sm font-semibold tracking-tight"><?= e($storeName) ?></span>
        </a>

        <div class="mt-8 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
            <?= $content ?>
        </div>

        <a href="/" class="mx-auto mt-6 inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-900">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Kembali ke toko
        </a>
    </div>
</body>
</html>
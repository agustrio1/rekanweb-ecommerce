<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Core - <?= e($title ?? 'RekanWeb') ?></title>
    <!-- Load CSS dari Vite Manifest -->
    <link rel="stylesheet" href="<?= asset('resources/js/app.js') ?>"> 
</head>
<body class="p-8">
    <h1 class="text-2xl font-bold text-blue-600 mb-4"><?= e($title ?? 'Test Core') ?></h1>
    
    <p class="mb-4">Status User: <?= auth() ? e(auth()['name']) : 'Guest' ?></p>

    <form method="POST" action="/test-post" class="max-w-sm">
        <?= csrf_field() ?>
        <label class="block mb-2 text-sm font-medium">Nama:</label>
        <input type="text" name="nama" value="<?= old('nama') ?>" class="border p-2 rounded w-full mb-4">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Kirim Data (Test CSRF)
        </button>
    </form>
</body>
</html>
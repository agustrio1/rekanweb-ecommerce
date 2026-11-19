<?php
$error = $error ?? '';
$email = $email ?? '';
$nextQuery = $nextQuery ?? '';
?>
<h1 class="text-xl font-bold tracking-tight">Masuk ke akunmu</h1>
<p class="mt-1 text-sm text-zinc-500">Masuk untuk checkout dan memantau pesanan.</p>

<?php if ($error !== ''): ?>
    <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="/login" class="mt-6 space-y-4" x-data="{ show: false }">
    <?= csrf_field() ?>

    <div>
        <label for="email" class="block text-sm font-medium">Email</label>
        <input id="email" type="email" name="email" required autocomplete="email" autofocus value="<?= e($email) ?>"
               class="mt-1.5 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm placeholder:text-zinc-400 focus:border-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-400/30">
    </div>

    <div>
        <label for="password" class="block text-sm font-medium">Password</label>
        <div class="relative mt-1.5">
            <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password"
                   class="h-11 w-full rounded-lg border border-zinc-200 pl-3 pr-16 text-sm focus:border-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-400/30">
            <button type="button" @click="show = !show" class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-md px-2.5 py-1.5 text-xs font-medium text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" x-text="show ? 'Sembunyikan' : 'Tampilkan'"></button>
        </div>
    </div>

    <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">Masuk</button>
</form>

<p class="mt-6 text-center text-sm text-zinc-500">
    Belum punya akun?
    <a href="/register<?= e($nextQuery) ?>" class="font-medium text-zinc-900 underline underline-offset-2 hover:text-yellow-700">Daftar sekarang</a>
</p>
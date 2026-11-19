<?php
$errors = $errors ?? [];
$old = $old ?? [];
$error = $error ?? '';
$nextQuery = $nextQuery ?? '';

$input = 'mt-1.5 h-11 w-full rounded-lg border px-3 text-sm placeholder:text-zinc-400 focus:outline-none focus:ring-2';
$ok = 'border-zinc-200 focus:border-yellow-400 focus:ring-yellow-400/30';
$bad = 'border-rose-300 focus:border-rose-400 focus:ring-rose-400/30';
?>
<h1 class="text-xl font-bold tracking-tight">Buat akun baru</h1>
<p class="mt-1 text-sm text-zinc-500">Cukup satu menit. Datamu dipakai untuk pesanan dan pengiriman.</p>

<?php if ($error !== ''): ?>
    <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="/register" class="mt-6 space-y-4" x-data="{ show: false }" novalidate>
    <?= csrf_field() ?>

    <div>
        <label for="name" class="block text-sm font-medium">Nama lengkap</label>
        <input id="name" type="text" name="name" required maxlength="100" autocomplete="name" autofocus value="<?= e($old['name'] ?? '') ?>"
               class="<?= $input ?> <?= isset($errors['name']) ? $bad : $ok ?>">
        <?php if (isset($errors['name'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="email" class="block text-sm font-medium">Email</label>
        <input id="email" type="email" name="email" required maxlength="100" autocomplete="email" value="<?= e($old['email'] ?? '') ?>"
               class="<?= $input ?> <?= isset($errors['email']) ? $bad : $ok ?>">
        <?php if (isset($errors['email'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="phone" class="block text-sm font-medium">Nomor HP</label>
        <input id="phone" type="tel" name="phone" required inputmode="tel" autocomplete="tel" placeholder="081234567890" value="<?= e($old['phone'] ?? '') ?>"
               class="<?= $input ?> <?= isset($errors['phone']) ? $bad : $ok ?>">
        <?php if (isset($errors['phone'])): ?>
            <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['phone']) ?></p>
        <?php else: ?>
            <p class="mt-1.5 text-xs text-zinc-500">Dipakai kurir untuk menghubungi penerima.</p>
        <?php endif; ?>
    </div>

    <div>
        <label for="password" class="block text-sm font-medium">Password</label>
        <div class="relative">
            <input id="password" :type="show ? 'text' : 'password'" name="password" required minlength="8" maxlength="72" autocomplete="new-password"
                   class="<?= $input ?> pr-16 <?= isset($errors['password']) ? $bad : $ok ?>">
            <button type="button" @click="show = !show" class="absolute right-1.5 top-1/2 mt-[3px] -translate-y-1/2 rounded-md px-2.5 py-1.5 text-xs font-medium text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" x-text="show ? 'Sembunyikan' : 'Tampilkan'"></button>
        </div>
        <?php if (isset($errors['password'])): ?>
            <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['password']) ?></p>
        <?php else: ?>
            <p class="mt-1.5 text-xs text-zinc-500">Minimal 8 karakter.</p>
        <?php endif; ?>
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm font-medium">Ulangi password</label>
        <input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" required maxlength="72" autocomplete="new-password"
               class="<?= $input ?> <?= isset($errors['password_confirmation']) ? $bad : $ok ?>">
        <?php if (isset($errors['password_confirmation'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['password_confirmation']) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">Daftar</button>
</form>

<p class="mt-6 text-center text-sm text-zinc-500">
    Sudah punya akun?
    <a href="/login<?= e($nextQuery) ?>" class="font-medium text-zinc-900 underline underline-offset-2 hover:text-yellow-700">Masuk</a>
</p>
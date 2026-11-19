<?php
$errors = $errors ?? [];

$input = 'mt-1.5 h-11 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-2';
$ok = 'border-zinc-200 focus:border-yellow-400 focus:ring-yellow-400/30';
$bad = 'border-rose-300 focus:border-rose-400 focus:ring-rose-400/30';

include __DIR__ . '/../components/account-nav.php';
?>

<div class="mx-auto max-w-3xl px-4 pt-6 sm:px-6">
    <form method="POST" action="/akun/password" class="max-w-md space-y-4" x-data="{ show: false }" novalidate>
        <?= csrf_field() ?>

        <div>
            <label for="current_password" class="block text-sm font-medium">Password saat ini</label>
            <input id="current_password" :type="show ? 'text' : 'password'" name="current_password" required autocomplete="current-password"
                   class="<?= $input ?> <?= isset($errors['current_password']) ? $bad : $ok ?>">
            <?php if (isset($errors['current_password'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['current_password']) ?></p><?php endif; ?>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Password baru</label>
            <input id="password" :type="show ? 'text' : 'password'" name="password" required minlength="8" maxlength="72" autocomplete="new-password"
                   class="<?= $input ?> <?= isset($errors['password']) ? $bad : $ok ?>">
            <?php if (isset($errors['password'])): ?>
                <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['password']) ?></p>
            <?php else: ?>
                <p class="mt-1.5 text-xs text-zinc-500">Minimal 8 karakter.</p>
            <?php endif; ?>
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Ulangi password baru</label>
            <input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" required maxlength="72" autocomplete="new-password"
                   class="<?= $input ?> <?= isset($errors['password_confirmation']) ? $bad : $ok ?>">
            <?php if (isset($errors['password_confirmation'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['password_confirmation']) ?></p><?php endif; ?>
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-600">
            <input type="checkbox" x-model="show" class="h-4 w-4 rounded border-zinc-300 accent-yellow-500">
            Tampilkan password
        </label>

        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">Ubah password</button>
    </form>
</div>
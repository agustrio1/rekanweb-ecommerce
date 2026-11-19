<?php
$errors = $errors ?? [];
$old = $old ?? [];

$input = 'mt-1.5 h-11 w-full rounded-lg border px-3 text-sm placeholder:text-zinc-400 focus:outline-none focus:ring-2';
$ok = 'border-zinc-200 focus:border-yellow-400 focus:ring-yellow-400/30';
$bad = 'border-rose-300 focus:border-rose-400 focus:ring-rose-400/30';

include __DIR__ . '/../components/account-nav.php';
?>

<div class="mx-auto max-w-3xl px-4 pt-6 sm:px-6">
    <form method="POST" action="/akun/profil" class="max-w-md space-y-4" novalidate>
        <?= csrf_field() ?>

        <div>
            <label for="name" class="block text-sm font-medium">Nama lengkap</label>
            <input id="name" type="text" name="name" required maxlength="100" autocomplete="name" value="<?= e($old['name'] ?? $user['name']) ?>"
                   class="<?= $input ?> <?= isset($errors['name']) ? $bad : $ok ?>">
            <?php if (isset($errors['name'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>

        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" type="email" value="<?= e($user['email']) ?>" disabled
                   class="<?= $input ?> cursor-not-allowed border-zinc-200 bg-zinc-50 text-zinc-500">
            <p class="mt-1.5 text-xs text-zinc-500">Email dipakai untuk masuk dan tidak bisa diubah di sini.</p>
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium">Nomor HP</label>
            <input id="phone" type="tel" name="phone" required inputmode="tel" autocomplete="tel" value="<?= e($old['phone'] ?? (string) $user['phone']) ?>"
                   class="<?= $input ?> <?= isset($errors['phone']) ? $bad : $ok ?>">
            <?php if (isset($errors['phone'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['phone']) ?></p><?php endif; ?>
        </div>

        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">Simpan perubahan</button>
    </form>
</div>
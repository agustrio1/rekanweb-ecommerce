<?php
$input = 'mt-1.5 w-full rounded-xl border bg-white px-3 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus-visible:outline-none focus-visible:ring-2';
$ok = 'border-zinc-200 focus-visible:border-yellow-400 focus-visible:ring-yellow-400/40';
$bad = 'border-rose-300 focus-visible:border-rose-400 focus-visible:ring-rose-400/30';
$card = 'rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6';
$cardTitle = 'text-base font-semibold text-zinc-900';
$badge = 'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold';

$err = fn(string $key): string => isset($errors[$key])
    ? '<p class="mt-1.5 text-xs text-rose-600">' . e($errors[$key]) . '</p>'
    : '';
$cls = fn(string $key): string => isset($errors[$key]) ? $bad : $ok;

/** Lencana sumber nilai kunci. */
$sourceBadge = function (string $source) use ($badge): string {
    return match ($source) {
        'db' => '<span class="' . $badge . ' border-emerald-200 bg-emerald-50 text-emerald-700">Tersimpan di pengaturan</span>',
        'env' => '<span class="' . $badge . ' border-yellow-300 bg-yellow-50 text-yellow-800">Dari file .env</span>',
        default => '<span class="' . $badge . ' border-rose-200 bg-rose-50 text-rose-700">Belum diisi</span>',
    };
};

[$biteshipSource, $biteshipMask] = $biteship;
[$duitkuSource, $duitkuMask] = $duitkuKey;
?>
<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <a href="/admin/settings" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-900">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Pengaturan
        </a>
        <h2 class="mt-3 text-2xl font-bold tracking-tight text-zinc-900">Kredensial API</h2>
        <p class="mt-1 text-sm text-zinc-500">Kunci Biteship dan Duitku. Kunci yang sudah tersimpan tidak ditampilkan penuh.</p>
    </div>

    <?php if (!$canEncrypt): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <span class="font-semibold">APP_KEY belum diisi di .env.</span>
            API key tidak bisa disimpan dari halaman ini sampai APP_KEY ada. Merchant code dan mode tetap bisa diubah.
        </div>
    <?php endif; ?>

    <form
        method="POST"
        action="/admin/settings/credentials"
        autocomplete="off"
        class="space-y-5"
        x-data="{ mode: '<?= e($values['duitku_env']) ?>', initial: '<?= e($values['duitku_env']) ?>' }"
        @submit="if (mode === 'production' && initial !== 'production' && !confirm('Beralih ke production? Transaksi berikutnya memakai uang sungguhan.')) $event.preventDefault()"
    >
        <?= csrf_field() ?>

        <!-- Biteship -->
        <section class="<?= $card ?> space-y-4">
            <div class="flex items-start justify-between gap-3">
                <h3 class="<?= $cardTitle ?>">Biteship</h3>
                <?= $sourceBadge($biteshipSource) ?>
            </div>

            <div>
                <label for="biteship_api_key" class="text-sm font-medium text-zinc-900">API key</label>
                <input
                    id="biteship_api_key"
                    type="password"
                    name="biteship_api_key"
                    autocomplete="new-password"
                    spellcheck="false"
                    placeholder="<?= $biteshipMask !== '' ? e($biteshipMask . ' (kosongkan jika tidak diganti)') : 'Tempel API key' ?>"
                    class="<?= $input ?> h-11 <?= $cls('biteship_api_key') ?>"
                >
                <?= $err('biteship_api_key') ?>
                <p class="mt-1.5 text-xs text-zinc-500">Kunci sandbox dan live dibedakan oleh Biteship sendiri. Pakai kunci yang sesuai tujuan Anda.</p>
            </div>
        </section>

        <!-- Duitku -->
        <section class="<?= $card ?> space-y-4">
            <div class="flex items-start justify-between gap-3">
                <h3 class="<?= $cardTitle ?>">Duitku</h3>
                <?= $sourceBadge($duitkuSource) ?>
            </div>

            <div>
                <label for="duitku_merchant_code" class="text-sm font-medium text-zinc-900">Merchant code</label>
                <input
                    id="duitku_merchant_code"
                    type="text"
                    name="duitku_merchant_code"
                    value="<?= e($values['duitku_merchant_code']) ?>"
                    maxlength="30"
                    spellcheck="false"
                    class="<?= $input ?> h-11 <?= $cls('duitku_merchant_code') ?>"
                >
                <?= $err('duitku_merchant_code') ?>
            </div>

            <div>
                <label for="duitku_api_key" class="text-sm font-medium text-zinc-900">API key</label>
                <input
                    id="duitku_api_key"
                    type="password"
                    name="duitku_api_key"
                    autocomplete="new-password"
                    spellcheck="false"
                    placeholder="<?= $duitkuMask !== '' ? e($duitkuMask . ' (kosongkan jika tidak diganti)') : 'Tempel API key' ?>"
                    class="<?= $input ?> h-11 <?= $cls('duitku_api_key') ?>"
                >
                <?= $err('duitku_api_key') ?>
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-zinc-900">Mode</legend>
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <?php foreach (['sandbox' => ['Sandbox', 'Uji coba, tanpa uang sungguhan'], 'production' => ['Production', 'Transaksi nyata']] as $value => [$label, $hint]): ?>
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors"
                            :class="mode === '<?= $value ?>' ? 'border-zinc-900 bg-zinc-50' : 'border-zinc-200 hover:border-zinc-300'"
                        >
                            <input type="radio" name="duitku_env" value="<?= $value ?>" x-model="mode" class="mt-0.5 h-4 w-4 border-zinc-300 text-zinc-900 focus:ring-yellow-400">
                            <span>
                                <span class="block text-sm font-medium text-zinc-900"><?= e($label) ?></span>
                                <span class="block text-xs text-zinc-500"><?= e($hint) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= $err('duitku_env') ?>
            </fieldset>

            <div class="rounded-xl bg-zinc-50 px-4 py-3 text-xs leading-relaxed text-zinc-600">
                Merchant code, API key, dan mode harus dari lingkungan yang sama. Sandbox dan production memakai pasangan kunci berbeda.
                Jangan ganti saat masih ada pesanan menunggu pembayaran, karena callback lama akan gagal diverifikasi.
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">Simpan kredensial</button>
        </div>
    </form>
</div>
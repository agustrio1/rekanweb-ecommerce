<?php
$input = 'mt-1.5 w-full rounded-xl border bg-white px-3 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus-visible:outline-none focus-visible:ring-2';
$ok = 'border-zinc-200 focus-visible:border-yellow-400 focus-visible:ring-yellow-400/40';
$bad = 'border-rose-300 focus-visible:border-rose-400 focus-visible:ring-rose-400/30';
$card = 'rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6';
$cardTitle = 'text-base font-semibold text-zinc-900';
$file = 'mt-2 block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-900 hover:file:bg-zinc-200';

/** Satu kolom teks dengan label dan pesan error. */
$field = function (string $key, string $label, array $opt = []) use ($values, $errors, $input, $ok, $bad): string {
    $type = $opt['type'] ?? 'text';
    $hint = $opt['hint'] ?? '';
    $placeholder = $opt['placeholder'] ?? '';
    $required = !empty($opt['required']);
    $cls = isset($errors[$key]) ? $bad : $ok;

    $html = '<div class="' . e($opt['wrap'] ?? '') . '">'
        . '<label for="' . e($key) . '" class="text-sm font-medium text-zinc-900">' . e($label)
        . ($required ? '' : ' <span class="font-normal text-zinc-400">(opsional)</span>') . '</label>'
        . '<input id="' . e($key) . '" type="' . e($type) . '" name="' . e($key) . '" value="' . e($values[$key] ?? '') . '"'
        . ($placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '')
        . ($required ? ' required' : '')
        . ' class="' . $input . ' h-11 ' . $cls . '">';

    if (isset($errors[$key])) {
        $html .= '<p class="mt-1.5 text-xs text-rose-600">' . e($errors[$key]) . '</p>';
    } elseif ($hint !== '') {
        $html .= '<p class="mt-1.5 text-xs text-zinc-500">' . e($hint) . '</p>';
    }

    return $html . '</div>';
};

$logo = (string) ($values['store_logo'] ?? '');
$favicon = (string) ($values['store_favicon'] ?? '');

// Wilayah asal saat ini (dari pengaturan tersimpan)
$areaId = (string) ($values['origin_area_id'] ?? '');
$areaName = trim(
    implode(', ', array_filter([
        (string) ($values['origin_district'] ?? ''),
        (string) ($values['origin_city'] ?? ''),
        (string) ($values['origin_province'] ?? ''),
    ])) . ' ' . (string) ($values['origin_postal_code'] ?? '')
);
$selectedArea = $areaId !== ''
    ? ['id' => $areaId, 'name' => $areaName !== '' ? $areaName : $areaId]
    : null;
?>
<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Pengaturan</h2>
        <p class="mt-1 text-sm text-zinc-500">Identitas, kontak, dan alamat asal pengiriman toko.</p>
    </div>

    <a href="/admin/settings/credentials" class="flex items-center justify-between gap-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition-colors hover:border-zinc-300 sm:p-5">
        <span class="min-w-0">
            <span class="block text-sm font-semibold text-zinc-900">Kredensial API</span>
            <span class="block text-xs text-zinc-500">Kunci Biteship dan Duitku, serta mode sandbox atau production.</span>
        </span>
        <svg class="h-4 w-4 shrink-0 text-zinc-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
    </a>

    <form method="POST" action="/admin/settings" enctype="multipart/form-data" class="space-y-5">
        <?= csrf_field() ?>

        <!-- Identitas -->
        <section class="<?= $card ?> space-y-5">
            <h3 class="<?= $cardTitle ?>">Identitas toko</h3>

            <?= $field('store_name', 'Nama toko', ['required' => true]) ?>
            <?= $field('store_tagline', 'Slogan') ?>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="logo" class="text-sm font-medium text-zinc-900">Logo <span class="font-normal text-zinc-400">(opsional)</span></label>
                    <?php if ($logo !== ''): ?>
                        <img src="<?= e($logo) ?>" alt="Logo saat ini" class="mt-2 h-16 w-auto max-w-full rounded-lg border border-zinc-200 bg-zinc-50 object-contain p-2">
                    <?php endif; ?>
                    <input id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="<?= $file ?>">
                    <?php if (isset($errors['logo'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['logo']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="favicon" class="text-sm font-medium text-zinc-900">Favicon <span class="font-normal text-zinc-400">(opsional)</span></label>
                    <?php if ($favicon !== ''): ?>
                        <img src="<?= e($favicon) ?>" alt="Favicon saat ini" class="mt-2 h-16 w-16 rounded-lg border border-zinc-200 bg-zinc-50 object-contain p-2">
                    <?php endif; ?>
                    <input id="favicon" type="file" name="favicon" accept="image/jpeg,image/png,image/webp" class="<?= $file ?>">
                    <?php if (isset($errors['favicon'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['favicon']) ?></p><?php endif; ?>
                </div>
            </div>
            <p class="-mt-2 text-xs text-zinc-500">JPG, PNG, atau WebP, maksimal 2 MB. Kosongkan jika tidak diganti. Favicon sebaiknya persegi (PNG 512 x 512).</p>
        </section>

        <!-- Kontak -->
        <section class="<?= $card ?> space-y-5">
            <h3 class="<?= $cardTitle ?>">Kontak dan media sosial</h3>

            <div class="grid gap-5 sm:grid-cols-2">
                <?= $field('store_whatsapp', 'Nomor WhatsApp', ['type' => 'tel', 'placeholder' => '081234567890', 'hint' => 'Disimpan sebagai 62xxxxxxxxxx.']) ?>
                <?= $field('store_email', 'Email toko', ['type' => 'email']) ?>
            </div>

            <?= $field('social_instagram', 'Instagram', ['type' => 'url', 'placeholder' => 'https://instagram.com/namatoko']) ?>
            <?= $field('social_facebook', 'Facebook', ['type' => 'url', 'placeholder' => 'https://facebook.com/namatoko']) ?>
            <?= $field('social_tiktok', 'TikTok', ['type' => 'url', 'placeholder' => 'https://tiktok.com/@namatoko']) ?>
        </section>

        <!-- Alamat asal -->
        <section class="<?= $card ?> space-y-5">
            <div>
                <h3 class="<?= $cardTitle ?>">Alamat asal pengiriman</h3>
                <p class="mt-1 text-xs text-zinc-500">Dipakai untuk menghitung ongkir dan sebagai alamat pengirim saat membuat pengiriman di Biteship.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <?= $field('origin_name', 'Nama pengirim', ['required' => true]) ?>
                <?= $field('origin_phone', 'Nomor telepon', ['required' => true, 'type' => 'tel']) ?>
            </div>

            <!-- Pencarian wilayah -->
            <div
                class="relative"
                @click.outside="open = false"
                @keydown.escape="open = false"
                x-data="{
                    selected: <?= e(json_encode($selectedArea, JSON_UNESCAPED_UNICODE)) ?>,
                    q: '',
                    results: [],
                    open: false,
                    loading: false,
                    error: '',
                    timer: null,
                    ctrl: null,
                    seq: 0,
                    onInput() {
                        this.selected = null;
                        clearTimeout(this.timer);
                        const term = this.q.trim();
                        if (term.length < 3) {
                            this.seq++;
                            if (this.ctrl) this.ctrl.abort();
                            this.results = [];
                            this.open = false;
                            this.loading = false;
                            this.error = '';
                            return;
                        }
                        this.timer = setTimeout(() => this.fetchAreas(term), 450);
                    },
                    async fetchAreas(term) {
                        if (this.ctrl) this.ctrl.abort();
                        this.ctrl = new AbortController();
                        const id = ++this.seq;
                        this.loading = true;
                        this.error = '';
                        try {
                            const res = await fetch('/api/wilayah?q=' + encodeURIComponent(term), {
                                headers: { 'Accept': 'application/json' },
                                signal: this.ctrl.signal
                            });
                            const data = await res.json();
                            if (id !== this.seq) return;
                            if (!res.ok || !data.ok) {
                                this.error = data.message || 'Pencarian gagal.';
                                this.results = [];
                            } else {
                                this.results = data.areas;
                            }
                            this.open = true;
                            this.loading = false;
                        } catch (e) {
                            if (e.name === 'AbortError' || id !== this.seq) return;
                            this.error = 'Koneksi bermasalah. Coba lagi.';
                            this.results = [];
                            this.open = true;
                            this.loading = false;
                        }
                    },
                    choose(area) {
                        this.seq++;
                        if (this.ctrl) this.ctrl.abort();
                        this.selected = area;
                        this.q = area.name;
                        this.open = false;
                        this.loading = false;
                    }
                }"
                x-init="q = selected ? selected.name : ''"
            >
                <label for="area_search" class="text-sm font-medium text-zinc-900">Kecamatan / kelurahan asal</label>
                <input type="hidden" name="area_id" :value="selected ? selected.id : ''">
                <input
                    id="area_search"
                    type="text"
                    x-model="q"
                    @input="onInput()"
                    @focus="if (results.length || error) open = true"
                    autocomplete="off"
                    role="combobox"
                    :aria-expanded="open"
                    aria-controls="area_list"
                    placeholder="Ketik minimal 3 huruf, contoh: kebayoran"
                    class="<?= $input ?> h-11 <?= isset($errors['area']) ? $bad : $ok ?>"
                >

                <div x-cloak x-show="open" id="area_list" class="absolute left-0 right-0 z-20 mt-1 max-h-64 overflow-y-auto rounded-xl border border-zinc-200 bg-white py-1 shadow-lg">
                    <p x-show="loading" class="px-3 py-2.5 text-sm text-zinc-500">Mencari...</p>
                    <p x-show="!loading && error" class="px-3 py-2.5 text-sm text-rose-600" x-text="error"></p>
                    <p x-show="!loading && !error && results.length === 0" class="px-3 py-2.5 text-sm text-zinc-500">Wilayah tidak ditemukan. Coba nama kecamatan atau kode pos.</p>
                    <template x-for="area in results" :key="area.id">
                        <button type="button" @click="choose(area)" class="block w-full px-3 py-2.5 text-left text-sm text-zinc-800 hover:bg-yellow-50 focus:bg-yellow-50 focus:outline-none" x-text="area.name"></button>
                    </template>
                </div>

                <?php if (isset($errors['area'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['area']) ?></p>
                <?php else: ?>
                    <p class="mt-1.5 text-xs text-zinc-500">Provinsi, kota, dan kode pos terisi otomatis dari wilayah yang dipilih.</p>
                <?php endif; ?>
            </div>

            <div>
                <label for="origin_address" class="text-sm font-medium text-zinc-900">Alamat lengkap</label>
                <textarea id="origin_address" name="origin_address" rows="3" maxlength="500" required class="<?= $input ?> py-2.5 <?= isset($errors['origin_address']) ? $bad : $ok ?>"><?= e($values['origin_address'] ?? '') ?></textarea>
                <?php if (isset($errors['origin_address'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['origin_address']) ?></p><?php endif; ?>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">Simpan pengaturan</button>
        </div>
    </form>
</div>
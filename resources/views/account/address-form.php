<?php
$errors = $errors ?? [];
$old = $old ?? [];

$selected = !empty($old['area_id'])
    ? ['id' => (string) $old['area_id'], 'name' => (string) ($old['area_name'] ?? '')]
    : null;

$input = 'mt-1.5 w-full rounded-lg border px-3 text-sm placeholder:text-zinc-400 focus:outline-none focus:ring-2';
$ok = 'border-zinc-200 focus:border-yellow-400 focus:ring-yellow-400/30';
$bad = 'border-rose-300 focus:border-rose-400 focus:ring-rose-400/30';

include __DIR__ . '/../components/account-nav.php';
?>

<div class="mx-auto max-w-3xl px-4 pt-6 sm:px-6">
    <h2 class="text-lg font-semibold tracking-tight"><?= e($title) ?></h2>

    <form method="POST" action="<?= e($action) ?>" class="mt-5 max-w-md space-y-4" novalidate>
        <?= csrf_field() ?>
        <?php if (($_GET['return'] ?? '') === 'checkout' || ($_POST['return'] ?? '') === 'checkout'): ?>
            <input type="hidden" name="return" value="checkout">
        <?php endif; ?>

        <div>
            <label for="recipient_name" class="block text-sm font-medium">Nama penerima</label>
            <input id="recipient_name" type="text" name="recipient_name" required maxlength="100" autocomplete="name" value="<?= e($old['recipient_name'] ?? '') ?>"
                   class="h-11 <?= $input ?> <?= isset($errors['recipient_name']) ? $bad : $ok ?>">
            <?php if (isset($errors['recipient_name'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['recipient_name']) ?></p><?php endif; ?>
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium">Nomor HP penerima</label>
            <input id="phone" type="tel" name="phone" required inputmode="tel" autocomplete="tel" placeholder="081234567890" value="<?= e($old['phone'] ?? '') ?>"
                   class="h-11 <?= $input ?> <?= isset($errors['phone']) ? $bad : $ok ?>">
            <?php if (isset($errors['phone'])): ?>
                <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['phone']) ?></p>
            <?php else: ?>
                <p class="mt-1.5 text-xs text-zinc-500">Kurir akan menghubungi nomor ini.</p>
            <?php endif; ?>
        </div>

        <!-- Pencarian wilayah -->
        <div
            class="relative"
            @click.outside="open = false"
            @keydown.escape="open = false"
            x-data="{
                selected: <?= e(json_encode($selected, JSON_UNESCAPED_UNICODE)) ?>,
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
            <label for="area_search" class="block text-sm font-medium">Kecamatan / kelurahan</label>
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
                class="h-11 <?= $input ?> <?= isset($errors['area']) ? $bad : $ok ?>"
            >

            <div x-cloak x-show="open" id="area_list" class="absolute left-0 right-0 z-20 mt-1 max-h-64 overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
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
                <p class="mt-1.5 text-xs text-zinc-500">Pilih salah satu hasil. Provinsi, kota, dan kode pos terisi otomatis.</p>
            <?php endif; ?>
        </div>

        <div>
            <label for="address_detail" class="block text-sm font-medium">Alamat lengkap</label>
            <textarea id="address_detail" name="address_detail" required rows="3" maxlength="300" placeholder="Nama jalan, nomor rumah, RT/RW, patokan"
                      class="py-2.5 <?= $input ?> <?= isset($errors['address_detail']) ? $bad : $ok ?>"><?= e($old['address_detail'] ?? '') ?></textarea>
            <?php if (isset($errors['address_detail'])): ?><p class="mt-1.5 text-xs text-rose-600"><?= e($errors['address_detail']) ?></p><?php endif; ?>
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700">
            <input type="checkbox" name="is_default" value="1" <?= !empty($old['is_default']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-zinc-300 accent-yellow-500">
            Jadikan alamat utama
        </label>

        <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">Simpan alamat</button>
            <a href="/akun/alamat" class="inline-flex h-11 items-center rounded-lg px-4 text-sm font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900">Batal</a>
        </div>
    </form>
</div>
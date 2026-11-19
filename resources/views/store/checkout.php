<?php
$lines = array_values(array_filter($lines ?? [], fn($l) => !empty($l['available'])));
$subtotal = (int) ($subtotal ?? 0);
$itemCount = (int) ($itemCount ?? 0);
$addresses = $addresses ?? [];
$defaultId = (int) ($defaultId ?? 0);

$money = fn(int $n): string => 'Rp ' . number_format($n, 0, ',', '.');
?>

<div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8 lg:pt-8">
    <a href="/keranjang" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-900">
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
        Kembali ke keranjang
    </a>
    <h1 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">Checkout</h1>

    <div
        class="mt-6 grid gap-8 lg:grid-cols-3 lg:gap-10"
        @pageshow.window="submitting = false"
        x-data="{
            addressId: <?= $defaultId ?>,
            options: [],
            weight: 0,
            selectedKey: '',
            loading: false,
            submitting: false,
            error: '',
            seq: 0,
            ctrl: null,
            subtotal: <?= $subtotal ?>,
            methods: [],
            methodCode: '',
            loadingMethods: false,
            methodError: '',
            money(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); },
            get selected() { return this.options.find(o => o.key === this.selectedKey) || null; },
            get selectedMethod() { return this.methods.find(m => m.code === this.methodCode) || null; },
            get total() { return this.subtotal + (this.selected ? this.selected.price : 0); },
            get canSubmit() { return !!this.addressId && !!this.selected && !!this.methodCode && !this.loading && !this.submitting; },
            async loadMethods(attempt = 1) {
                this.loadingMethods = true;
                this.methodError = '';
                try {
                    const res = await fetch('/api/pembayaran', { headers: { 'Accept': 'application/json' } });
                    const text = await res.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (_) {
                        console.error('Respons metode pembayaran bukan JSON (HTTP ' + res.status + '):', text.slice(0, 500));
                        throw new Error('bad-json');
                    }
                    if (!res.ok || !data.ok) {
                        this.methodError = data.message || 'Metode pembayaran gagal dimuat.';
                        this.methods = [];
                    } else {
                        this.methods = data.methods;
                        if (!this.methods.some(m => m.code === this.methodCode)) { this.methodCode = ''; }
                    }
                    this.loadingMethods = false;
                } catch (e) {
                    console.error('Gagal memuat metode pembayaran (percobaan ' + attempt + '):', e);
                    if (attempt < 3) {
                        await new Promise(r => setTimeout(r, 800 * attempt));
                        return this.loadMethods(attempt + 1);
                    }
                    this.methodError = 'Koneksi bermasalah. Coba lagi.';
                    this.loadingMethods = false;
                }
            },
            async load(attempt = 1) {
                if (!this.addressId) { return; }
                if (this.ctrl) this.ctrl.abort();
                this.ctrl = new AbortController();
                const id = ++this.seq;
                this.loading = true;
                this.error = '';
                this.options = [];
                try {
                    const res = await fetch('/api/ongkir?address_id=' + this.addressId, {
                        headers: { 'Accept': 'application/json' },
                        signal: this.ctrl.signal
                    });
                    const text = await res.text();
                    if (id !== this.seq) return;

                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (_) {
                        console.error('Respons ongkir bukan JSON (HTTP ' + res.status + '):', text.slice(0, 500));
                        throw new Error('bad-json');
                    }

                    if (!res.ok || !data.ok) {
                        this.error = data.message || 'Ongkir gagal dihitung.';
                        this.selectedKey = '';
                    } else {
                        this.options = data.options;
                        this.weight = data.weight;
                        const keep = data.options.find(o => o.key === this.selectedKey);
                        this.selectedKey = keep ? keep.key : (data.options[0] ? data.options[0].key : '');
                    }
                    this.loading = false;
                } catch (e) {
                    if (e.name === 'AbortError' || id !== this.seq) return;
                    console.error('Gagal memuat ongkir (percobaan ' + attempt + '):', e);
                    if (attempt < 3) {
                        await new Promise(r => setTimeout(r, 800 * attempt));
                        if (id !== this.seq) return;
                        return this.load(attempt + 1);
                    }
                    this.error = 'Koneksi bermasalah. Coba lagi.';
                    this.loading = false;
                }
            }
        }"
        x-init="load(); loadMethods()"
    >
        <div class="space-y-8 lg:col-span-2">
            <!-- 1. Alamat -->
            <section aria-labelledby="h-alamat">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="h-alamat" class="text-base font-semibold">1. Alamat pengiriman</h2>
                    <a href="/akun/alamat/tambah?return=checkout" class="text-sm font-medium text-zinc-600 hover:text-zinc-900">Tambah alamat</a>
                </div>

                <div class="mt-3 space-y-3">
                    <?php foreach ($addresses as $a): ?>
                        <label
                            class="block rounded-xl border p-4 <?= $a['has_area'] ? 'cursor-pointer' : 'cursor-not-allowed bg-zinc-50' ?>"
                            :class="addressId === <?= (int) $a['id'] ?> ? 'border-yellow-400 ring-1 ring-yellow-400' : 'border-zinc-200'"
                        >
                            <div class="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="address"
                                    value="<?= (int) $a['id'] ?>"
                                    x-model.number="addressId"
                                    @change="load()"
                                    <?= $a['has_area'] ? '' : 'disabled' ?>
                                    class="mt-1 h-4 w-4 accent-yellow-500"
                                >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-zinc-900"><?= e($a['recipient_name']) ?></p>
                                        <span class="text-sm text-zinc-500"><?= e($a['phone']) ?></span>
                                        <?php if ($a['is_default']): ?>
                                            <span class="rounded-md bg-yellow-400 px-2 py-0.5 text-xs font-semibold text-zinc-900">Utama</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-600"><?= e($a['address_detail']) ?></p>
                                    <p class="mt-0.5 text-sm text-zinc-500"><?= e($a['district']) ?>, <?= e($a['city']) ?>, <?= e($a['province']) ?> <?= e($a['postal_code']) ?></p>
                                    <?php if (!$a['has_area']): ?>
                                        <p class="mt-2 text-xs font-medium text-amber-700">
                                            Alamat ini belum punya data wilayah.
                                            <a href="/akun/alamat/<?= (int) $a['id'] ?>/ubah" class="underline underline-offset-2">Ubah alamat</a>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php if ($defaultId === 0): ?>
                    <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
                        Belum ada alamat yang lengkap. Tambah alamat baru atau ubah alamat yang ada.
                    </p>
                <?php endif; ?>
            </section>

            <!-- 2. Kurir -->
            <section aria-labelledby="h-kurir">
                <h2 id="h-kurir" class="text-base font-semibold">2. Pilih pengiriman</h2>
                <p class="mt-1 text-sm text-zinc-500" x-show="weight > 0" x-cloak>
                    Berat total <span class="font-medium text-zinc-900" x-text="Number(weight).toLocaleString('id-ID') + ' gram'"></span>
                </p>

                <div class="mt-3" aria-live="polite">
                    <div x-show="loading" x-cloak class="space-y-3">
                        <div class="h-[72px] animate-pulse rounded-xl bg-zinc-100"></div>
                        <div class="h-[72px] animate-pulse rounded-xl bg-zinc-100"></div>
                        <div class="h-[72px] animate-pulse rounded-xl bg-zinc-100"></div>
                    </div>

                    <div x-show="!loading && error" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                        <p x-text="error"></p>
                        <button type="button" @click="load()" class="mt-2 text-sm font-medium underline underline-offset-2">Coba lagi</button>
                    </div>

                    <div x-show="!loading && !error && options.length" x-cloak class="space-y-3">
                        <template x-for="o in options" :key="o.key">
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-xl border p-4"
                                :class="selectedKey === o.key ? 'border-yellow-400 ring-1 ring-yellow-400' : 'border-zinc-200 hover:border-zinc-300'"
                            >
                                <input type="radio" name="shipping" :value="o.key" x-model="selectedKey" class="mt-1 h-4 w-4 accent-yellow-500">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-zinc-900" x-text="o.courier_name + ' - ' + o.service_name"></p>
                                    <p class="mt-0.5 text-xs text-zinc-500" x-show="o.estimate" x-text="'Estimasi ' + o.estimate"></p>
                                    <p class="mt-0.5 text-xs text-zinc-400" x-show="o.description" x-text="o.description"></p>
                                </div>
                                <p class="shrink-0 text-sm font-semibold text-zinc-900" x-text="money(o.price)"></p>
                            </label>
                        </template>
                    </div>

                    <p x-show="!loading && !error && !options.length && addressId" x-cloak class="text-sm text-zinc-500">Pilih alamat untuk melihat pilihan pengiriman.</p>
                </div>
            </section>

            <!-- 3. Pembayaran -->
            <section aria-labelledby="h-bayar">
                <h2 id="h-bayar" class="text-base font-semibold">3. Metode pembayaran</h2>

                <div class="mt-3" aria-live="polite">
                    <div x-show="loadingMethods" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="h-16 animate-pulse rounded-xl bg-zinc-100"></div>
                        <div class="h-16 animate-pulse rounded-xl bg-zinc-100"></div>
                        <div class="h-16 animate-pulse rounded-xl bg-zinc-100"></div>
                        <div class="h-16 animate-pulse rounded-xl bg-zinc-100"></div>
                    </div>

                    <div x-show="!loadingMethods && methodError" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                        <p x-text="methodError"></p>
                        <button type="button" @click="loadMethods()" class="mt-2 text-sm font-medium underline underline-offset-2">Coba lagi</button>
                    </div>

                    <div x-show="!loadingMethods && !methodError && methods.length" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <template x-for="m in methods" :key="m.code">
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-xl border p-3"
                                :class="methodCode === m.code ? 'border-yellow-400 ring-1 ring-yellow-400' : 'border-zinc-200 hover:border-zinc-300'"
                            >
                                <input type="radio" name="payment" :value="m.code" x-model="methodCode" class="h-4 w-4 shrink-0 accent-yellow-500">
                                <span class="flex h-10 w-16 shrink-0 items-center justify-center rounded-md border border-zinc-100 bg-white p-1">
                                    <img
                                        x-show="m.image"
                                        :src="m.image"
                                        :alt="m.name"
                                        loading="lazy"
                                        referrerpolicy="no-referrer"
                                        class="max-h-full max-w-full object-contain"
                                        @error="$el.style.display = 'none'"
                                    >
                                </span>
                                <span class="min-w-0 flex-1 text-sm font-medium text-zinc-900" x-text="m.name"></span>
                            </label>
                        </template>
                    </div>

                    <p x-show="!loadingMethods && !methodError && !methods.length" x-cloak class="text-sm text-zinc-500">Belum ada metode pembayaran yang tersedia untuk nominal ini.</p>
                </div>
            </section>
        </div>

        <!-- Ringkasan -->
        <aside class="lg:col-span-1" aria-label="Ringkasan pesanan">
            <div class="rounded-xl border border-zinc-200 p-5 lg:sticky lg:top-36">
                <h2 class="text-base font-semibold">Ringkasan pesanan</h2>

                <ul class="mt-4 space-y-3">
                    <?php foreach ($lines as $line): ?>
                        <li class="flex items-start justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="line-clamp-2 font-medium text-zinc-900"><?= e($line['name']) ?></p>
                                <p class="text-xs text-zinc-500"><?= e($line['variant_name']) ?> &times; <?= (int) $line['qty'] ?></p>
                            </div>
                            <p class="shrink-0 text-zinc-700"><?= e($money($line['line_total'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <dl class="mt-4 space-y-3 border-t border-zinc-200 pt-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Subtotal (<?= $itemCount ?> barang)</dt>
                        <dd class="font-medium text-zinc-900"><?= e($money($subtotal)) ?></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Ongkos kirim</dt>
                        <dd class="font-medium text-zinc-900" x-text="selected ? money(selected.price) : '-'"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Pembayaran</dt>
                        <dd class="text-right font-medium text-zinc-900" x-text="selectedMethod ? selectedMethod.name : '-'"></dd>
                    </div>
                </dl>

                <div class="mt-4 flex items-baseline justify-between gap-4 border-t border-zinc-200 pt-4">
                    <span class="text-sm font-medium">Total</span>
                    <span class="text-xl font-bold tracking-tight" x-text="money(total)"></span>
                </div>

                <form method="POST" action="/checkout" class="mt-5" @submit="if (!canSubmit) { $event.preventDefault(); return; } submitting = true">
                    <?= csrf_field() ?>
                    <input type="hidden" name="address_id" :value="addressId">
                    <input type="hidden" name="shipping_key" :value="selectedKey">
                    <input type="hidden" name="seen_shipping" :value="selected ? selected.price : 0">
                    <input type="hidden" name="payment_method" :value="methodCode">
                    <button
                        type="submit"
                        :disabled="!canSubmit"
                        class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-zinc-200 disabled:text-zinc-500"
                        x-text="submitting ? 'Memproses...' : 'Buat pesanan'"
                    ></button>
                </form>

                <p class="mt-3 text-xs leading-relaxed text-zinc-500">Harga, stok, ongkir, dan metode pembayaran dicek ulang oleh server saat pesanan dibuat. Stok ditahan selama 60 menit untuk pembayaran.</p>
            </div>
        </aside>
    </div>
</div>
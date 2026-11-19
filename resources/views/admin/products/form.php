<?php
use App\Models\AdminProduct;

$isEdit = $product !== null;
$action = $isEdit ? '/admin/products/' . (int) $product['id'] : '/admin/products';

$input = 'mt-1.5 w-full rounded-xl border bg-white px-3 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus-visible:outline-none focus-visible:ring-2';
$ok = 'border-zinc-200 focus-visible:border-yellow-400 focus-visible:ring-yellow-400/40';
$bad = 'border-rose-300 focus-visible:border-rose-400 focus-visible:ring-rose-400/30';
$card = 'rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6';
$cardTitle = 'text-base font-semibold text-zinc-900';
$smallBtn = 'inline-flex h-9 items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 text-xs font-medium text-zinc-900 transition-colors hover:bg-zinc-50';

$err = fn(string $key): string => isset($errors[$key])
    ? '<p class="mt-1.5 text-xs text-rose-600">' . e($errors[$key]) . '</p>'
    : '';
$cls = fn(string $key): string => isset($errors[$key]) ? $bad : $ok;

$svg = fn(string $path, string $class = 'h-4 w-4'): string =>
    '<svg class="' . $class . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';

// Variasi
$rows = [];
foreach (array_values($values['variants']) as $i => $row) {
    $rows[] = [
        'k' => $i,
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'sku' => (string) $row['sku'],
        'price' => (string) $row['price'],
        'discount' => (string) $row['discount'],
        'stock' => (string) $row['stock'],
    ];
}
$rowsJson = e((string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// Foto
$images = $isEdit ? $product['images'] : [];
$slots = AdminProduct::MAX_IMAGES - count($images);

// Kategori (search select)
$categoryOptions = [];
foreach ($categories as $c) {
    $categoryOptions[] = [
        'id' => (int) $c['id'],
        'name' => $c['name'] . ($c['is_active'] === 1 ? '' : ' (nonaktif)'),
    ];
}
$selectInit = e(
    'searchSelect('
    . json_encode($categoryOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ', '
    . json_encode((string) $values['category_id']) . ', '
    . json_encode('Pilih kategori', JSON_UNESCAPED_UNICODE)
    . ')'
);

// Rich editor
$editorInit = e(
    'richEditor(' . json_encode((string) $values['description'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ')'
);
$toolbar = [
    ['bold', 'Tebal', '<path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"/>'],
    ['italic', 'Miring', '<line x1="19" x2="10" y1="4" y2="4"/><line x1="14" x2="5" y1="20" y2="20"/><line x1="15" x2="9" y1="4" y2="20"/>'],
    ['strike', 'Coret', '<path d="M16 4H9a3 3 0 0 0-2.83 4"/><path d="M14 12a4 4 0 0 1 0 8H6"/><line x1="4" x2="20" y1="12" y2="12"/>'],
    '|',
    ['h2', 'Judul', '<path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M21 18h-4c0-4 4-3 4-6 0-1.5-2-2.5-4-1"/>'],
    ['h3', 'Subjudul', '<path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17.5 10.5c1.7-1 3.5 0 3.5 1.5a2 2 0 0 1-2 2"/><path d="M17 17.5c2 1.5 4 .3 4-1.5a2 2 0 0 0-2-2"/>'],
    '|',
    ['bullet', 'Daftar poin', '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>'],
    ['ordered', 'Daftar angka', '<path d="M10 12h11"/><path d="M10 18h11"/><path d="M10 6h11"/><path d="M4 10h2"/><path d="M4 6h1v4"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>'],
    ['quote', 'Kutipan', '<path d="M16 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/><path d="M5 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/>'],
    '|',
    ['undo', 'Urungkan', '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11"/>'],
    ['redo', 'Ulangi', '<path d="m15 14 5-5-5-5"/><path d="M20 9H9.5A5.5 5.5 0 0 0 4 14.5A5.5 5.5 0 0 0 9.5 20H13"/>'],
];
?>
<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <a href="/admin/products" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-900">
            <?= $svg('<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>') ?>
            Semua produk
        </a>
        <h2 class="mt-3 text-2xl font-bold tracking-tight text-zinc-900"><?= $isEdit ? 'Ubah produk' : 'Tambah produk' ?></h2>
    </div>

    <!-- Foto -->
    <section class="<?= $card ?>">
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="<?= $cardTitle ?>">Foto produk</h3>
            <?php if ($isEdit): ?>
                <span class="text-xs text-zinc-500"><?= count($images) ?> / <?= AdminProduct::MAX_IMAGES ?></span>
            <?php endif; ?>
        </div>

        <?php if (!$isEdit): ?>
            <p class="mt-3 rounded-xl bg-zinc-50 px-4 py-3 text-sm text-zinc-600">Foto bisa ditambahkan setelah produk disimpan.</p>
        <?php else: ?>
            <?php if (!empty($images)): ?>
                <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <?php foreach ($images as $img): ?>
                        <li class="overflow-hidden rounded-xl border border-zinc-200">
                            <div class="relative">
                                <img src="<?= e($img['image_path']) ?>" alt="" loading="lazy" class="aspect-square w-full object-cover">
                                <?php if ($img['is_primary'] === 1): ?>
                                    <span class="absolute left-2 top-2 rounded-full bg-yellow-400 px-2 py-0.5 text-[11px] font-semibold text-zinc-900">Utama</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-1.5 p-2">
                                <?php if ($img['is_primary'] !== 1): ?>
                                    <form method="POST" action="/admin/products/<?= (int) $product['id'] ?>/images/primary" class="flex-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                                        <button type="submit" class="<?= $smallBtn ?> w-full">Jadikan utama</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="/admin/products/<?= (int) $product['id'] ?>/images/delete"
                                      class="<?= $img['is_primary'] === 1 ? 'flex-1' : '' ?>"
                                      x-data
                                      @submit="if (!confirm('Hapus foto ini?')) $event.preventDefault()">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                                    <button type="submit" class="<?= $smallBtn ?> w-full text-rose-600 hover:bg-rose-50">Hapus</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="mt-3 rounded-xl bg-zinc-50 px-4 py-3 text-sm text-zinc-600">Belum ada foto. Produk tanpa foto tampil kosong di toko.</p>
            <?php endif; ?>

            <?php if ($slots > 0): ?>
                <form method="POST" action="/admin/products/<?= (int) $product['id'] ?>/images" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <?= csrf_field() ?>
                    <input
                        type="file"
                        name="images[]"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        required
                        class="min-w-0 flex-1 text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-900 hover:file:bg-zinc-200"
                    >
                    <button type="submit" class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl bg-zinc-900 px-5 text-sm font-semibold text-white transition-colors hover:bg-zinc-800">Unggah foto</button>
                </form>
                <p class="mt-2 text-xs text-zinc-500">JPG, PNG, atau WebP. Maksimal 2 MB per foto. Foto pertama otomatis jadi foto utama.</p>
            <?php else: ?>
                <p class="mt-4 text-xs text-zinc-500">Batas foto tercapai. Hapus foto lama untuk menambah yang baru.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <form method="POST" action="<?= e($action) ?>" class="space-y-5">
        <?= csrf_field() ?>

        <!-- Informasi dasar -->
        <section class="<?= $card ?> space-y-5">
            <h3 class="<?= $cardTitle ?>">Informasi dasar</h3>

            <div>
                <label for="name" class="text-sm font-medium text-zinc-900">Nama produk</label>
                <input id="name" type="text" name="name" value="<?= e($values['name']) ?>" maxlength="200" required class="<?= $input ?> h-11 <?= $cls('name') ?>">
                <?= $err('name') ?>
                <?php if ($isEdit): ?>
                    <p class="mt-1.5 text-xs text-zinc-500">URL tetap /produk/<?= e($product['slug']) ?> meskipun nama diubah.</p>
                <?php endif; ?>
            </div>

            <!-- Kategori: select dengan pencarian -->
            <div>
                <span id="category-label" class="text-sm font-medium text-zinc-900">Kategori</span>

                <div
                    class="relative"
                    x-data="<?= $selectInit ?>"
                    @keydown.escape="close()"
                    @click.outside="close()"
                >
                    <input type="hidden" name="category_id" :value="value">

                    <button
                        type="button"
                        x-ref="trigger"
                        @click="toggle()"
                        @keydown.arrow-down.prevent="show()"
                        role="combobox"
                        aria-haspopup="listbox"
                        aria-labelledby="category-label"
                        :aria-expanded="open"
                        class="<?= $input ?> flex h-11 items-center justify-between gap-2 text-left <?= $cls('category_id') ?>"
                    >
                        <span class="truncate" :class="current ? 'text-zinc-900' : 'text-zinc-400'" x-text="current ? current.name : placeholder"></span>
                        <?= $svg('<path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/>', 'h-4 w-4 shrink-0 text-zinc-400') ?>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition.opacity.duration.100ms
                        class="absolute z-30 mt-2 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg"
                    >
                        <div class="flex items-center gap-2 border-b border-zinc-100 px-3">
                            <?= $svg('<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>', 'h-4 w-4 shrink-0 text-zinc-400') ?>
                            <input
                                type="text"
                                x-ref="search"
                                x-model="query"
                                @input="cursor = 0"
                                @keydown.arrow-down.prevent="move(1)"
                                @keydown.arrow-up.prevent="move(-1)"
                                @keydown.enter.prevent="choose()"
                                placeholder="Cari kategori..."
                                autocomplete="off"
                                class="h-11 w-full bg-transparent text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none"
                            >
                        </div>

                        <ul x-ref="list" role="listbox" aria-labelledby="category-label" class="max-h-60 overflow-y-auto p-1">
                            <template x-for="(o, i) in filtered" :key="o.id">
                                <li
                                    role="option"
                                    :aria-selected="String(o.id) === value"
                                    @click="pick(o)"
                                    @mousemove="cursor = i"
                                    :class="cursor === i ? 'bg-zinc-100' : ''"
                                    class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm text-zinc-900"
                                >
                                    <span class="truncate" x-text="o.name"></span>
                                    <svg x-show="String(o.id) === value" class="h-4 w-4 shrink-0 text-yellow-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                </li>
                            </template>
                            <li x-show="filtered.length === 0" class="px-3 py-6 text-center text-sm text-zinc-500">Kategori tidak ditemukan.</li>
                        </ul>
                    </div>
                </div>
                <?= $err('category_id') ?>
            </div>

            <!-- Deskripsi: rich editor -->
            <div>
                <span class="text-sm font-medium text-zinc-900">Deskripsi <span class="font-normal text-zinc-400">(opsional)</span></span>

                <div
                    x-data="<?= $editorInit ?>"
                    class="mt-1.5 overflow-hidden rounded-xl border bg-white shadow-sm focus-within:ring-2 <?= isset($errors['description']) ? 'border-rose-300 focus-within:ring-rose-400/30' : 'border-zinc-200 focus-within:border-yellow-400 focus-within:ring-yellow-400/40' ?>"
                >
                    <input type="hidden" name="description" :value="html">

                    <div class="flex flex-wrap items-center gap-1 border-b border-zinc-100 bg-zinc-50/60 p-1.5" role="toolbar" aria-label="Format teks">
                        <?php foreach ($toolbar as $tool): ?>
                            <?php if ($tool === '|'): ?>
                                <span class="mx-1 h-5 w-px bg-zinc-200" aria-hidden="true"></span>
                            <?php else: ?>
                                <?php [$cmd, $label, $path] = $tool; ?>
                                <button
                                    type="button"
                                    @mousedown.prevent
                                    @click="run('<?= $cmd ?>')"
                                    :aria-pressed="active('<?= $cmd ?>')"
                                    :class="active('<?= $cmd ?>') ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-200/70 hover:text-zinc-900'"
                                    title="<?= e($label) ?>"
                                    aria-label="<?= e($label) ?>"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
                                ><?= $svg($path) ?></button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div x-ref="surface"></div>
                </div>
                <?= $err('description') ?>
            </div>

            <label class="flex items-center gap-3">
                <input type="checkbox" name="is_active" value="1" <?= (int) $values['is_active'] === 1 ? 'checked' : '' ?> class="h-5 w-5 rounded border-zinc-300 text-yellow-500 focus:ring-yellow-400">
                <span class="text-sm text-zinc-900">Tampilkan di toko</span>
            </label>
        </section>

        <!-- Pengiriman -->
        <section class="<?= $card ?>">
            <h3 class="<?= $cardTitle ?>">Berat dan ukuran paket</h3>
            <p class="mt-1 text-xs text-zinc-500">Dipakai menghitung ongkos kirim. Isi berat dan ukuran produk setelah dikemas.</p>

            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <?php foreach ([['weight_gram', 'Berat (gram)'], ['length_cm', 'Panjang (cm)'], ['width_cm', 'Lebar (cm)'], ['height_cm', 'Tinggi (cm)']] as [$key, $label]): ?>
                    <div>
                        <label for="<?= $key ?>" class="text-sm font-medium text-zinc-900"><?= e($label) ?></label>
                        <input id="<?= $key ?>" type="number" inputmode="numeric" min="1" step="1" name="<?= $key ?>" value="<?= e($values[$key]) ?>" required class="<?= $input ?> h-11 <?= $cls($key) ?>">
                        <?= $err($key) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Variasi -->
        <section
            class="<?= $card ?>"
            x-data="{
                rows: <?= $rowsJson ?>,
                next: <?= (int) count($rows) ?>,
                add() { this.rows.push({ k: this.next++, id: 0, name: '', sku: '', price: '', discount: '', stock: '0' }); },
                remove(i) { if (this.rows.length > 1) this.rows.splice(i, 1); }
            }"
        >
            <h3 class="<?= $cardTitle ?>">Variasi, harga, dan stok</h3>
            <p class="mt-1 text-xs text-zinc-500">Contoh variasi: &ldquo;Putih - M&rdquo;. Produk tanpa pilihan tetap butuh satu variasi.</p>

            <?php if (!empty($errors['variants'])): ?>
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3" role="alert">
                    <ul class="space-y-1 text-sm text-rose-800">
                        <?php foreach ($errors['variants'] as $message): ?>
                            <li><?= e($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="mt-4 space-y-4">
                <template x-for="(row, i) in rows" :key="row.k">
                    <div class="rounded-xl border border-zinc-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-zinc-900" x-text="'Variasi ' + (i + 1)"></p>
                            <button
                                type="button"
                                @click="remove(i)"
                                :disabled="rows.length === 1"
                                class="text-xs font-medium text-rose-600 hover:underline disabled:cursor-not-allowed disabled:text-zinc-300 disabled:no-underline"
                            >Hapus variasi</button>
                        </div>

                        <input type="hidden" :name="'variants[' + i + '][id]'" :value="row.id">

                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium text-zinc-900">Nama variasi</span>
                                <input type="text" maxlength="100" required x-model="row.name" :name="'variants[' + i + '][name]'" class="<?= $input ?> h-11 <?= $ok ?>">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-zinc-900">SKU</span>
                                <input type="text" maxlength="100" required x-model="row.sku" :name="'variants[' + i + '][sku]'" class="<?= $input ?> h-11 uppercase <?= $ok ?>">
                            </label>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <label class="block">
                                <span class="text-sm font-medium text-zinc-900">Harga (Rp)</span>
                                <input type="number" inputmode="numeric" min="1" step="1" required x-model="row.price" :name="'variants[' + i + '][price]'" class="<?= $input ?> h-11 <?= $ok ?>">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-zinc-900">Harga diskon <span class="font-normal text-zinc-400">(opsional)</span></span>
                                <input type="number" inputmode="numeric" min="1" step="1" x-model="row.discount" :name="'variants[' + i + '][discount]'" class="<?= $input ?> h-11 <?= $ok ?>">
                            </label>
                            <label class="col-span-2 block sm:col-span-1">
                                <span class="text-sm font-medium text-zinc-900">Stok</span>
                                <input type="number" inputmode="numeric" min="0" step="1" required x-model="row.stock" :name="'variants[' + i + '][stock]'" class="<?= $input ?> h-11 <?= $ok ?>">
                            </label>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" @click="add()" class="mt-4 inline-flex h-10 items-center gap-1.5 rounded-lg border border-dashed border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-900 transition-colors hover:border-zinc-400 hover:bg-zinc-50">
                <?= $svg('<path d="M5 12h14"/><path d="M12 5v14"/>') ?>
                Tambah variasi
            </button>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <a href="/admin/products" class="inline-flex h-11 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 text-sm font-medium text-zinc-900 hover:bg-zinc-50">Batal</a>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-yellow-400 px-6 text-sm font-semibold text-zinc-900 shadow-sm transition-colors hover:bg-yellow-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2">
                <?= $isEdit ? 'Simpan perubahan' : 'Simpan produk' ?>
            </button>
        </div>
    </form>
</div>
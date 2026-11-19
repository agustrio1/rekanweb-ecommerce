<?php
/**
 * Editor rich text (Tiptap) sebagai field form.
 *
 * @var string      $name   Nama field POST
 * @var string      $value  HTML awal
 * @var string|null $error  Pesan error
 * @var string      $label  Label di atas editor
 */
$error = $error ?? null;
$label = $label ?? '';

$svg = fn(string $path): string =>
    '<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';

$init = e('richEditor(' . json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ')');

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
<div>
    <?php if ($label !== ''): ?>
        <span class="text-sm font-medium text-zinc-900"><?= e($label) ?></span>
    <?php endif; ?>

    <div
        x-data="<?= $init ?>"
        class="mt-1.5 overflow-hidden rounded-xl border bg-white shadow-sm focus-within:ring-2 <?= $error ? 'border-rose-300 focus-within:ring-rose-400/30' : 'border-zinc-200 focus-within:border-yellow-400 focus-within:ring-yellow-400/40' ?>"
    >
        <input type="hidden" name="<?= e($name) ?>" :value="html">

        <div class="flex flex-wrap items-center gap-1 border-b border-zinc-100 bg-zinc-50/60 p-1.5" role="toolbar" aria-label="Format teks">
            <?php foreach ($toolbar as $tool): ?>
                <?php if ($tool === '|'): ?>
                    <span class="mx-1 h-5 w-px bg-zinc-200" aria-hidden="true"></span>
                <?php else: ?>
                    <?php [$cmd, $title, $path] = $tool; ?>
                    <button
                        type="button"
                        @mousedown.prevent
                        @click="run('<?= $cmd ?>')"
                        :aria-pressed="active('<?= $cmd ?>')"
                        :class="active('<?= $cmd ?>') ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-200/70 hover:text-zinc-900'"
                        title="<?= e($title) ?>"
                        aria-label="<?= e($title) ?>"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
                    ><?= $svg($path) ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div x-ref="surface"></div>
    </div>

    <?php if ($error): ?>
        <p class="mt-1.5 text-xs text-rose-600"><?= e($error) ?></p>
    <?php endif; ?>
</div>
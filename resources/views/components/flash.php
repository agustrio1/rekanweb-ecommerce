<?php
$flash = $flash ?? null;

if (!is_array($flash) || empty($flash['message'])) {
    return;
}

$styles = [
    'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'error' => 'border-rose-200 bg-rose-50 text-rose-800',
    'info' => 'border-zinc-200 bg-zinc-50 text-zinc-800',
];
$class = $styles[$flash['type'] ?? 'info'] ?? $styles['info'];
$role = ($flash['type'] ?? '') === 'error' ? 'alert' : 'status';
?>
<div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8" x-data="{ show: true }" x-show="show">
    <div class="flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm <?= $class ?>" role="<?= $role ?>">
        <p><?= e($flash['message']) ?></p>
        <button type="button" @click="show = false" class="-mr-1 shrink-0 rounded p-1 opacity-60 hover:opacity-100" aria-label="Tutup pesan">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>
</div>
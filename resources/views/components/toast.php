<?php
/**
 * Toast notifikasi (bawah tengah).
 *
 * @var array $toasts Daftar toast: [['type' => 'success|error|info', 'message' => '...'], ...]
 */
$toasts = $toasts ?? [];

if (empty($toasts)) {
    return;
}

$styles = [
    'success' => ['border-emerald-200', 'bg-emerald-500', '<path d="M20 6 9 17l-5-5"/>'],
    'error'   => ['border-rose-200', 'bg-rose-500', '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>'],
    'info'    => ['border-yellow-300', 'bg-yellow-400', '<path d="M12 16v-4"/><path d="M12 8h.01"/>'],
];
?>
<div
    class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 px-4 pb-[calc(env(safe-area-inset-bottom,0px)+1rem)]"
    role="region"
    aria-label="Notifikasi"
>
    <?php foreach ($toasts as $toast): ?>
        <?php
        $type = isset($styles[$toast['type'] ?? '']) ? $toast['type'] : 'info';
        [$border, $dot, $path] = $styles[$type];
        $duration = $type === 'error' ? 8000 : 4500;
        ?>
        <div
            x-data="{ open: false }"
            x-init="$nextTick(() => open = true); setTimeout(() => open = false, <?= (int) $duration ?>)"
            x-show="open"
            x-cloak
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-3 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-3 opacity-0"
            role="<?= $type === 'error' ? 'alert' : 'status' ?>"
            class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-2xl border <?= $border ?> bg-white p-3 pr-2 shadow-lg"
        >
            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full <?= $dot ?> text-white">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $path ?></svg>
            </span>
            <p class="min-w-0 flex-1 py-0.5 text-sm leading-snug text-zinc-900"><?= e($toast['message'] ?? '') ?></p>
            <button
                type="button"
                @click="open = false"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700"
                aria-label="Tutup notifikasi"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
    <?php endforeach; ?>
</div>
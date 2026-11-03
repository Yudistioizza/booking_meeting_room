{{--
    Bottom sheet on phones, centered dialog elsewhere. `close` is an Alpine
    expression such as "$wire.close()". Locks page scroll while open.
--}}
@props(['title', 'close', 'size' => 'sm:max-w-lg', 'subtitle' => null])

<div class="modal-backdrop" role="dialog" aria-modal="true" aria-label="{{ $title }}"
     x-data="{ init() { document.body.style.overflow = 'hidden' }, destroy() { document.body.style.overflow = '' } }"
     x-on:keydown.escape.window="{{ $close }}" x-on:click.self="{{ $close }}">
    <div {{ $attributes->class(['modal', $size]) }}>
        <div class="modal-head">
            <div class="min-w-0">
                <h2 class="text-lg font-bold tracking-tight">{{ $title }}</h2>
                @if ($subtitle)<p class="text-sm text-slate-500 mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            <button type="button" class="icon-btn -mr-2 -mt-1" x-on:click="{{ $close }}" aria-label="Tutup"><flux:icon.x-mark class="size-5" /></button>
        </div>
        {{ $slot }}
    </div>
</div>

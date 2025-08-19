@props([
    'model'      => 'search',
    'placeholder'=> 'Cari...',
    'debounce'   => '500ms',
    'clearable'  => false,
])

<div class="relative w-full sm:w-64">
    <flux:input
        type="text"
        icon="magnifying-glass"
        placeholder="{{ $placeholder }}"
        wire:model.live.debounce.{{ $debounce }}="{{ $model }}"
    />
    @if($clearable && $model)
        <button
            wire:click="$set('{{ $model }}', '')"
            class="absolute right-8 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
        >
            <flux:icon name="x-mark" class="w-4 h-4" />
        </button>
    @endif
</div>
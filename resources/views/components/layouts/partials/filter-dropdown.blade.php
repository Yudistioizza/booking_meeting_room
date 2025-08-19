@props([
    'statusModel'   => 'filterStatus',
    'perPageModel'  => 'perPage',
])

<div class="relative" x-data="{ open: false }">
    {{-- Trigger --}}
    <flux:button
        variant="outline"
        icon="adjustments-horizontal"
        @click="open = !open"
        class="relative"
    >
        Filter
        @if($statusModel !== 'all' || $perPageModel != 10)
            <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full"></span>
        @endif
    </flux:button>

    {{-- Dropdown --}}
    <div
        x-show="open"
        x-cloak
        @click.away="open = false"
        x-transition
        class="absolute right-0 z-50 mt-2 w-80 p-4 bg-white border border-gray-200 rounded-xl shadow-xl space-y-4"
    >

        {{-- Status (selalu ada) --}}
        <div>
            <label class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <flux:icon name="user-circle" class="w-4 h-4 text-blue-500" />
                Status
            </label>
            <select wire:model.live="{{ $statusModel }}" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm">
                <option value="all">Semua Status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Tidak Aktif</option>
                <option value="deleted">Terhapus</option>
            </select>
        </div>

        {{-- Slot tambahan (opsional) --}}
        {{ $slot }}

        {{-- Per-Page (selalu ada) --}}
        <div>
            <label class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <flux:icon name="list-bullet" class="w-4 h-4 text-purple-500" />
                Items / halaman
            </label>
            <select wire:model.live="{{ $perPageModel }}" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm">
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>

        {{-- Actions --}}
        <div class="flex justify-between pt-2 border-t">
            <button type="button" class="text-sm text-gray-500 hover:text-gray-700 underline" wire:click="clearFilters" @click="open = false">
                Reset Filter
            </button>
            <flux:button size="sm" @click="open = false">Tutup</flux:button>
        </div>
    </div>
</div>


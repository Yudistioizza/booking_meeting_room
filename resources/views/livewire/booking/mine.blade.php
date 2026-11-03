<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="seg self-start" role="tablist">
            @foreach (['upcoming' => 'Mendatang', 'pending' => 'Menunggu', 'invited' => 'Diundang', 'history' => 'Riwayat'] as $k => $l)
                <button role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}" wire:click="setTab('{{ $k }}')">{{ $l }}@if (($counts[$k] ?? 0) > 0) <span class="ml-1 text-xs text-slate-400 tnum">{{ $counts[$k] }}</span>@endif</button>
            @endforeach
        </div>
        <a href="{{ route('settings.profile') }}" wire:navigate class="text-sm font-semibold text-brand-700 hover:underline flex items-center gap-1.5"><flux:icon.calendar-days class="size-4" /> Langganan kalender pribadi</a>
    </div>

    <div class="card overflow-hidden">
        @forelse ($bookings as $b)
            <button wire:key="b-{{ $b->id }}" class="w-full text-left px-5 py-4 flex items-center gap-4 border-b border-slate-100 last:border-0 hover:bg-slate-50" wire:click="$dispatch('show-booking', { id: {{ $b->id }} })">
                <span class="w-14 shrink-0 text-center rounded-lg bg-slate-50 border border-slate-200 py-1.5">
                    <span class="block text-[10px] font-bold uppercase text-slate-400">{{ $b->start_time->translatedFormat('M') }}</span>
                    <span class="block text-lg font-bold leading-none tnum">{{ $b->start_time->format('d') }}</span>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold truncate">{{ $b->title }}</span>
                    <span class="block text-sm text-slate-500 tnum">{{ $b->start_time->translatedFormat('D') }} · {{ $b->start_time->format('H:i') }}–{{ $b->end_time->format('H:i') }} · {{ $b->room->name }}@if ($tab === 'invited') · oleh {{ $b->creator->name }}@endif</span>
                </span>
                @if ($b->recurring_booking_id)<flux:icon.arrow-path class="size-4 text-slate-400 hidden sm:block" title="Berulang" />@endif
                <span class="badge {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span>
                <flux:icon.chevron-right class="size-4 text-slate-300 hidden sm:block" />
            </button>
        @empty
            <div class="empty"><flux:icon.ticket class="size-9 text-slate-300" /><p class="font-semibold">Belum ada booking di sini.</p><button class="btn btn-primary btn-sm mt-1" onclick="Livewire.dispatch('book-room')">Pesan ruang</button></div>
        @endforelse
    </div>
    @if ($bookings->hasPages())<div>{{ $bookings->links() }}</div>@endif
</div>

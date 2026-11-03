@php $from = \App\Livewire\Booking\Calendar::FROM; @endphp
<div class="space-y-5" wire:poll.30s>
    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="flex items-center gap-2">
            <button wire:click="shift(-1)" class="icon-btn border border-slate-200 bg-white" aria-label="Hari sebelumnya"><flux:icon.chevron-left class="size-5" /></button>
            <button wire:click="shift(1)" class="icon-btn border border-slate-200 bg-white" aria-label="Hari berikutnya"><flux:icon.chevron-right class="size-5" /></button>
            <input type="date" wire:model.live="date" class="input !w-44" aria-label="Pilih tanggal">
            <button wire:click="today" class="btn btn-secondary" @disabled($dayCarbon->isToday())>Hari ini</button>
        </div>
        <h2 class="text-lg font-bold lg:ml-2">{{ $dayCarbon->translatedFormat('l, d F Y') }}</h2>
        <div class="flex-1"></div>
        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="location" class="input !w-44" aria-label="Lantai atau gedung">
                <option value="">Semua lokasi</option>
                @foreach ($locations as $l)<option>{{ $l }}</option>@endforeach
            </select>
            <div class="relative">
                <flux:icon.magnifying-glass class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" class="input !w-44 !pl-9" placeholder="Cari ruang">
            </div>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-600 cursor-pointer select-none"><input type="checkbox" wire:model.live="onlyMine" class="size-4 rounded accent-brand-600"> Hanya milik saya</label>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs font-medium text-slate-600">
        <span class="flex items-center gap-2"><i class="size-3 rounded-sm bg-brand-100 border-l-4 border-brand-600"></i>Terpakai</span>
        <span class="flex items-center gap-2"><i class="size-3 rounded-sm bg-mine-100 border-l-4 border-mine-600"></i>Milik saya</span>
        <span class="flex items-center gap-2"><i class="size-3 rounded-sm tl-wait border-l-4"></i>Menunggu persetujuan</span>
        <span class="flex items-center gap-2"><i class="size-3 rounded-sm bg-slate-200"></i>Di luar jam operasional</span>
        <span class="text-slate-400 ml-auto hidden md:block">Klik area kosong untuk memesan · klik blok untuk detail</span>
    </div>

    {{-- Scheduler --}}
    <div class="card overflow-hidden tl">
        <div class="overflow-x-auto">
            <div style="min-width: calc(var(--label-w) + {{ count($hours) }} * var(--hour-w))">
                {{-- Hour header --}}
                <div class="flex border-b border-slate-200 bg-slate-50 sticky top-0 z-30">
                    <div class="shrink-0 sticky left-0 z-40 bg-slate-50 border-r border-slate-200 px-4 py-2.5 eyebrow" style="width: var(--label-w)">Ruangan</div>
                    <div class="flex flex-1">
                        @foreach ($hours as $h)<div class="flex-1 px-2 py-2.5 text-xs font-semibold text-slate-500 tnum border-l border-slate-200 first:border-l-0">{{ sprintf('%02d:00', $h) }}</div>@endforeach
                    </div>
                </div>

                @forelse ($rows as $r)
                    <div class="flex border-b border-slate-100 last:border-b-0 group/row" wire:key="row-{{ $r->room->id }}">
                        <div class="shrink-0 sticky left-0 z-20 bg-white border-r border-slate-200 px-4 py-3 group-hover/row:bg-slate-50" style="width: var(--label-w)">
                            <p class="text-sm font-semibold truncate">{{ $r->room->name }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ $r->room->capacity }} orang{{ $r->room->requires_approval ? ' · perlu persetujuan' : '' }}</p>
                        </div>
                        <div class="relative flex-1 h-[60px] cursor-cell tl-grid" style="background-size: calc(100% / {{ count($hours) }}) 100%"
                             x-data
                             @click="
                                const r = $el.getBoundingClientRect();
                                const mins = Math.floor((($event.clientX - r.left) / r.width) * {{ $total }} / 30) * 30 + {{ $from * 60 }};
                                const f = m => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
                                Livewire.dispatch('book-room', { room: {{ $r->room->id }}, date: '{{ $dayCarbon->toDateString() }}', start: f(mins), end: f(mins + 60) });
                             ">
                            @foreach ($r->closed as $c)
                                <div class="absolute inset-y-0 bg-slate-100/90 pointer-events-none" style="left: {{ $c['left'] }}%; width: {{ $c['width'] }}%"></div>
                            @endforeach
                            @if ($nowPos !== null)<div class="tl-now" style="left: {{ $nowPos }}%"></div>@endif
                            @foreach ($r->blocks as $bl)
                                <button type="button" class="tl-block {{ $bl->tone }}" style="left: {{ $bl->left }}%; width: {{ $bl->width }}%"
                                        @click.stop="Livewire.dispatch('show-booking', { id: {{ $bl->b->id }} })"
                                        title="{{ $bl->b->title }} · {{ $bl->b->start_time->format('H:i') }}–{{ $bl->b->end_time->format('H:i') }} · {{ $bl->b->creator->name }}">
                                    <span class="block font-semibold truncate leading-tight">{{ $bl->b->title }}</span>
                                    <span class="block truncate opacity-80 tnum">{{ $bl->b->start_time->format('H:i') }}–{{ $bl->b->end_time->format('H:i') }} · {{ $bl->b->creator->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="empty"><p class="font-semibold">Tidak ada ruangan yang cocok.</p></div>
                @endforelse
            </div>
        </div>
    </div>
</div>

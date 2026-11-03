@php use App\Livewire\Booking\Find; @endphp
<div class="grid gap-6 lg:grid-cols-[19rem_1fr] items-start">
    {{-- Filters --}}
    <aside class="card card-pad space-y-5 lg:sticky lg:top-24">
        <div>
            <h2 class="font-bold">Kapan & berapa orang?</h2>
            <p class="text-sm text-slate-500">Hasil langsung diperbarui.</p>
        </div>
        <div>
            <label class="label" for="s-date">Tanggal</label>
            <input id="s-date" type="date" wire:model.live="date" min="{{ now()->toDateString() }}" class="input">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label" for="s-start">Mulai</label><select id="s-start" wire:model.live="start" class="input tnum">@foreach ($times as $t)<option>{{ $t }}</option>@endforeach</select></div>
            <div><label class="label" for="s-end">Selesai</label><select id="s-end" wire:model.live="end" class="input tnum">@foreach ($times as $t)<option>{{ $t }}</option>@endforeach</select></div>
        </div>
        <div class="flex flex-wrap gap-1.5 -mt-2">
            @foreach ([30 => '30m', 60 => '1j', 90 => '1,5j', 120 => '2j', 240 => '4j'] as $m => $l)<button type="button" wire:click="setDuration({{ $m }})" class="btn btn-secondary btn-sm">{{ $l }}</button>@endforeach
        </div>
        <div>
            <label class="label" for="s-people">Jumlah peserta</label>
            <input id="s-people" type="number" min="1" max="500" wire:model.live.debounce.300ms="people" class="input tnum">
        </div>
        <div>
            <p class="label">Fasilitas</p>
            <div class="space-y-2">
                @foreach (Find::EQUIPMENT as $eq)
                    <label class="flex items-center gap-2.5 text-sm cursor-pointer"><input type="checkbox" wire:model.live="equipment" value="{{ $eq }}" class="size-4 rounded accent-brand-600"> {{ $eq }}</label>
                @endforeach
            </div>
        </div>
    </aside>

    {{-- Results --}}
    <section class="min-w-0 space-y-4">
        @if ($error)
            <p class="flash bg-busy-50 border-busy-100 text-busy-700"><flux:icon.exclamation-triangle class="size-5 shrink-0" /> {{ $error }}</p>
        @else
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-lg font-bold"><span class="text-brand-600 tnum">{{ $availableCount }}</span> ruang tersedia <span class="font-normal text-slate-500">dari {{ $results->count() }} yang sesuai</span></h2>
                <p class="text-sm text-slate-500 tnum">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('D, d M') }} · {{ $start }}–{{ $end }}@if ($hidden) · {{ $hidden }} ruang disaring @endif</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($results as $x)
                    <article class="card p-5 flex flex-col gap-4 {{ $x->ok ? '' : 'bg-slate-50/60' }}" wire:key="res-{{ $x->room->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-bold truncate">{{ $x->room->name }}</h3>
                                <p class="text-sm text-slate-500 truncate">{{ $x->room->location }}</p>
                            </div>
                            @if ($x->ok)<span class="badge badge-brand">Tersedia</span>@else<span class="badge badge-busy">Tidak tersedia</span>@endif
                        </div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                            <span class="flex items-center gap-1.5"><flux:icon.users class="size-4 text-slate-400" /> {{ $x->room->capacity }} orang</span>
                            @if ($x->room->requires_approval)<span class="flex items-center gap-1.5 text-wait-700"><flux:icon.clock class="size-4" /> Perlu persetujuan</span>@endif
                            @if ($x->room->max_duration_minutes)<span class="text-slate-400">maks {{ $x->room->max_duration_minutes / 60 }} jam</span>@endif
                        </div>
                        @if ($x->room->equipment)
                            <div class="flex flex-wrap gap-1.5">@foreach (array_slice(array_map('trim', explode(',', $x->room->equipment)), 0, 5) as $eq)<span class="badge badge-slate !font-medium before:hidden">{{ $eq }}</span>@endforeach</div>
                        @endif
                        @if ($x->ok)
                            <button class="btn btn-primary w-full mt-auto" onclick="Livewire.dispatch('book-room', { room: {{ $x->room->id }}, date: '{{ $date }}', start: '{{ $start }}', end: '{{ $end }}' })">Pesan ruang ini</button>
                        @else
                            <div class="mt-auto space-y-2.5">
                                <p class="text-sm text-busy-700">{{ $x->reason }}</p>
                                @if ($x->slots)
                                    <div class="flex flex-wrap items-center gap-1.5"><span class="text-xs font-semibold text-slate-500">Kosong:</span>
                                        @foreach ($x->slots as [$a, $b])<button wire:click="useSlot('{{ $a->format('H:i') }}', '{{ $b->format('H:i') }}')" class="btn btn-secondary btn-sm tnum">{{ $a->format('H:i') }}–{{ $b->format('H:i') }}</button>@endforeach</div>
                                @endif
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="card empty md:col-span-2"><flux:icon.magnifying-glass class="size-8 text-slate-300" /><p class="font-semibold">Tidak ada ruang yang cocok.</p><p class="text-sm text-slate-500">Kurangi jumlah peserta atau fasilitas yang diminta.</p></div>
                @endforelse
            </div>
        @endif
    </section>
</div>

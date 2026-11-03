@php
    $user = auth()->user();
    $h = now()->hour;
    $greet = $h < 11 ? 'Selamat pagi' : ($h < 15 ? 'Selamat siang' : ($h < 19 ? 'Selamat sore' : 'Selamat malam'));
    $quickStart = now()->addMinutes(15 - now()->minute % 15)->second(0)->format('H:i');
@endphp

<div class="space-y-8" wire:poll.60s>
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">{{ now()->translatedFormat('l, d F Y') }}</p>
            <h2 class="mt-1 text-2xl sm:text-3xl font-bold tracking-tight">{{ $greet }}, {{ \Illuminate\Support\Str::before($user->name, ' ') }}.</h2>
            <p class="mt-1 text-slate-500">
                @if ($mineToday)Anda punya <b class="text-slate-800">{{ $mineToday }} pertemuan</b> hari ini.@else Tidak ada pertemuan hari ini.@endif
                @if ($freeNow) {{ $freeNow }} ruang kosong sekarang.@endif
            </p>
        </div>
        <div class="flex gap-2 mt-3 sm:mt-0">
            <a href="{{ route('calendar') }}" wire:navigate class="btn btn-secondary"><flux:icon.calendar-days class="size-4" /> Kalender</a>
            <a href="{{ route('book') }}" wire:navigate class="btn btn-primary"><flux:icon.magnifying-glass class="size-4" /> Cari ruang</a>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ([
            ['Ruang kosong sekarang', $freeNow . ' / ' . $board->count(), 'building-office-2', 'bg-brand-50 text-brand-600'],
            ['Pertemuan saya hari ini', $mineToday, 'calendar-days', 'bg-mine-50 text-mine-600'],
            ['Jam rapat minggu ini', $weekHours . ' jam', 'clock', 'bg-slate-100 text-slate-600'],
            [$user->isApprover() ? 'Perlu persetujuan Anda' : 'Permintaan menunggu', $user->isApprover() ? $toApproveCount : $myPending, 'check-badge', 'bg-wait-100 text-wait-700'],
        ] as [$label, $value, $icon, $tone])
            <div class="card card-pad flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:gap-4">
                <span class="size-11 shrink-0 grid place-items-center rounded-lg {{ $tone }}"><flux:icon :name="$icon" class="size-5" /></span>
                <div class="min-w-0"><p class="text-2xl font-bold tracking-tight tnum">{{ $value }}</p><p class="text-xs font-medium text-slate-500 leading-snug">{{ $label }}</p></div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Live room board --}}
        <section class="xl:col-span-2 min-w-0">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-bold">Status ruang sekarang</h3>
                <span class="flex items-center gap-2 text-xs text-slate-500"><span class="live-dot"></span> Diperbarui otomatis</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($board as $r)
                    <article class="card p-4 flex flex-col gap-3 {{ $r->state === 'closed' ? 'opacity-60' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold truncate">{{ $r->room->name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $r->room->location }} · {{ $r->room->capacity }} orang</p>
                            </div>
                            @if ($r->state === 'free')<span class="badge badge-brand">Tersedia</span>
                            @elseif ($r->state === 'busy')<span class="badge badge-busy">Terpakai</span>
                            @else<span class="badge badge-slate">Tutup</span>@endif
                        </div>
                        <div class="text-sm min-h-10">
                            @if ($r->state === 'busy')
                                <p class="font-medium truncate">{{ $r->current->title }}</p>
                                <p class="text-xs text-slate-500">{{ $r->current->creator->name }} · selesai {{ $r->current->end_time->format('H:i') }}</p>
                            @elseif ($r->state === 'free')
                                <p class="text-slate-600">Kosong sampai <b class="tnum text-slate-900">{{ $r->freeUntil->format('H:i') }}</b>@if ($r->next) <span class="text-slate-400">· berikutnya {{ $r->next->title }}</span>@endif</p>
                            @else
                                <p class="text-slate-500">Di luar jam operasional.</p>
                            @endif
                        </div>
                        @if ($r->state === 'free')
                            <div class="flex gap-2">
                                @foreach ([30, 60] as $m)
                                    <button class="btn btn-secondary btn-sm flex-1" onclick="Livewire.dispatch('book-room', { room: {{ $r->room->id }}, date: '{{ now()->toDateString() }}', start: '{{ $quickStart }}', end: '{{ \App\Support\Times::format(\App\Support\Times::minutes($quickStart) + $m) }}' })">Pesan {{ $m === 30 ? '30 mnt' : '1 jam' }}</button>
                                @endforeach
                            </div>
                        @elseif ($r->state === 'busy')
                            <button class="btn btn-ghost btn-sm justify-start px-0 text-brand-700" wire:click="$dispatch('show-booking', { id: {{ $r->current->id }} })">Lihat detail →</button>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <div class="space-y-6 min-w-0">
            @if ($user->isApprover() && $toApprove->isNotEmpty())
                <section class="card overflow-hidden border-wait-100">
                    <div class="px-5 py-4 flex items-center justify-between bg-wait-50 border-b border-wait-100">
                        <h3 class="font-bold text-wait-700">Perlu persetujuan</h3>
                        <a href="{{ route('approvals') }}" wire:navigate class="text-xs font-semibold text-wait-700 hover:underline">Lihat semua ({{ $toApproveCount }})</a>
                    </div>
                    @foreach ($toApprove as $p)
                        <button class="w-full text-left px-5 py-3 border-b border-slate-100 last:border-0 hover:bg-slate-50" wire:click="$dispatch('show-booking', { id: {{ $p->id }} })">
                            <p class="font-semibold text-sm truncate">{{ $p->title }}</p>
                            <p class="text-xs text-slate-500">{{ $p->creator->name }} · {{ $p->room->name }} · {{ $p->start_time->translatedFormat('D d M, H:i') }}</p>
                        </button>
                    @endforeach
                </section>
            @endif

            <section class="card overflow-hidden">
                <div class="px-5 py-4 flex items-center justify-between border-b border-slate-200">
                    <h3 class="font-bold">Jadwal saya berikutnya</h3>
                    <a href="{{ route('my-bookings') }}" wire:navigate class="text-xs font-semibold text-brand-700 hover:underline">Semua</a>
                </div>
                @forelse ($mine as $b)
                    <button class="w-full text-left px-5 py-3.5 flex gap-4 border-b border-slate-100 last:border-0 hover:bg-slate-50" wire:click="$dispatch('show-booking', { id: {{ $b->id }} })">
                        <span class="w-12 shrink-0 text-center">
                            <span class="block text-[11px] font-semibold uppercase text-slate-400">{{ $b->start_time->translatedFormat('D') }}</span>
                            <span class="block text-xl font-bold leading-tight tnum {{ $b->start_time->isToday() ? 'text-brand-600' : '' }}">{{ $b->start_time->format('d') }}</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-sm truncate">{{ $b->title }}</span>
                            <span class="block text-xs text-slate-500 tnum">{{ $b->start_time->format('H:i') }}–{{ $b->end_time->format('H:i') }} · {{ $b->room->name }}</span>
                            @if ($b->isLive())<span class="badge badge-brand mt-1.5">Berlangsung</span>
                            @elseif ($b->status === 'pending')<span class="badge badge-wait mt-1.5">Menunggu persetujuan</span>
                            @elseif ($b->start_time->isToday() && $b->start_time->diffInMinutes(now()) < 120)<span class="text-xs font-semibold text-mine-600 mt-1 block">mulai {{ $b->start_time->diffForHumans() }}</span>@endif
                        </span>
                    </button>
                @empty
                    <div class="empty"><flux:icon.calendar class="size-8 text-slate-300" /><p class="font-semibold">Belum ada jadwal.</p><button class="btn btn-primary btn-sm mt-1" onclick="Livewire.dispatch('book-room')">Pesan ruang</button></div>
                @endforelse
            </section>
        </div>
    </div>
</div>

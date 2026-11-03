@php
    $bg = ['free' => 'bg-brand-600', 'busy' => 'bg-busy-500', 'closed' => 'bg-slate-600'][$state];
    $label = ['free' => 'Tersedia', 'busy' => 'Sedang dipakai', 'closed' => 'Tutup'][$state];
@endphp
<div class="min-h-screen grid lg:grid-cols-[1.4fr_1fr]" wire:poll.15s>
    <section class="{{ $bg }} p-8 sm:p-14 flex flex-col justify-between transition-colors">
        <div class="flex items-start justify-between gap-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-white/70">{{ $room->location }}</p>
                <h1 class="mt-2 text-5xl sm:text-7xl font-bold tracking-tight">{{ $room->name }}</h1>
                <p class="mt-3 text-white/80 text-lg">{{ $room->capacity }} orang{{ $room->equipment ? ' · ' . $room->equipment : '' }}</p>
            </div>
            <p class="text-4xl sm:text-5xl font-bold tnum" x-data="{ t: '' }" x-init="const f = () => t = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':'); f(); setInterval(f, 10000)" x-text="t">{{ now()->format('H:i') }}</p>
        </div>

        <div>
            <p class="text-3xl sm:text-5xl font-bold">{{ $label }}</p>
            @if ($state === 'busy')
                <p class="mt-4 text-2xl sm:text-3xl font-semibold">{{ $current->title }}</p>
                <p class="mt-1 text-xl text-white/80">{{ $current->creator->name }} · sampai {{ $current->end_time->format('H:i') }} <span class="tnum">({{ $current->end_time->diffInMinutes(now()) }} menit lagi)</span></p>
            @elseif ($state === 'free')
                <p class="mt-4 text-2xl text-white/90">Kosong sampai <b class="tnum">{{ $freeUntil->format('H:i') }}</b></p>
            @else
                <p class="mt-4 text-2xl text-white/90">Di luar jam operasional.</p>
            @endif
        </div>
    </section>

    <section class="p-8 sm:p-12 bg-nav">
        <h2 class="text-sm font-semibold uppercase tracking-widest text-slate-400">Jadwal berikutnya hari ini</h2>
        <div class="mt-5 space-y-3">
            @forelse ($upcoming->take(6) as $b)
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <p class="text-lg font-semibold tnum text-brand-500">{{ $b->start_time->format('H:i') }} – {{ $b->end_time->format('H:i') }}</p>
                    <p class="font-semibold truncate">{{ $b->title }}</p>
                    <p class="text-sm text-slate-400 truncate">{{ $b->creator->name }}</p>
                </div>
            @empty
                <p class="text-slate-400 text-lg">Tidak ada pertemuan lagi hari ini.</p>
            @endforelse
        </div>
        <p class="mt-10 text-sm text-slate-500">Pesan ruang ini lewat aplikasi Ruang · {{ config('booking.company') }}</p>
    </section>
</div>

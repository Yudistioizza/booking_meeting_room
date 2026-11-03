<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    @fluxAppearance
</head>
<body class="min-h-screen font-sans antialiased bg-white">
    <div class="min-h-svh grid lg:grid-cols-[1.05fr_1fr]">
        <section class="hidden lg:flex flex-col justify-between bg-nav text-white p-12 relative overflow-hidden">
            <a href="{{ route('home') }}"><x-app-logo /></a>
            <div class="relative max-w-lg">
                <h2 class="text-5xl font-bold leading-[1.05] tracking-tight">Ruang rapat yang selalu siap, tanpa bentrok.</h2>
                <p class="mt-6 text-lg text-slate-400">Cari ruang kosong, pesan dalam hitungan detik, dan undangan langsung masuk ke Outlook. Satu tempat untuk seluruh kantor.</p>
                <ul class="mt-10 space-y-3 text-sm text-slate-300">
                    @foreach (['Otomatis menolak jadwal yang bentrok', 'Persetujuan untuk ruang eksekutif & auditorium', 'Terhubung ke Outlook, Google Calendar, dan email'] as $point)
                        <li class="flex items-center gap-3"><flux:icon.check-circle class="size-5 text-brand-500 shrink-0" /> {{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <p class="text-xs text-slate-500">{{ config('booking.company') }}</p>
            {{-- Quiet room-grid motif: slots on an hourly grid --}}
            <div class="absolute -right-24 -bottom-24 grid grid-cols-6 gap-2 opacity-[.12] rotate-12 pointer-events-none" aria-hidden="true">
                @foreach (range(1, 36) as $i)<span class="size-16 rounded-lg {{ in_array($i, [3, 4, 10, 17, 18, 25, 31]) ? 'bg-brand-500' : 'bg-white' }}"></span>@endforeach
            </div>
        </section>
        <section class="flex flex-col items-center justify-center p-6 sm:p-10">
            <a href="{{ route('home') }}" class="lg:hidden mb-8 text-ink"><x-app-logo /></a>
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </section>
    </div>
    @fluxScripts
</body>
</html>

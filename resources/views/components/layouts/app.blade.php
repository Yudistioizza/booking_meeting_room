@php
    $user = auth()->user();
    $pending = $user->isApprover() ? \App\Models\Booking::where('status', 'pending')->where('created_by', '!=', $user->id)->count() : 0;
    $nav = [
        'Ruang rapat' => [
            ['dashboard', 'squares-2x2', 'Dasbor'],
            ['book', 'magnifying-glass', 'Cari & pesan ruang'],
            ['calendar', 'calendar-days', 'Kalender ruang'],
            ['my-bookings', 'ticket', 'Booking saya'],
        ],
    ];
    if ($user->isApprover()) {
        $nav['Manajer'] = [['approvals', 'check-badge', 'Persetujuan', $pending], ['reports', 'chart-bar', 'Laporan & utilisasi']];
    }
    if ($user->isAdmin()) {
        $nav['Administrasi'] = [['rooms.index', 'building-office-2', 'Ruangan'], ['users.index', 'users', 'Pengguna'], ['integrations', 'puzzle-piece', 'Integrasi'], ['audit', 'shield-check', 'Audit log']];
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    @fluxAppearance
</head>
<body class="min-h-screen font-sans antialiased" x-data="{ nav: false }">
    <div x-show="nav" x-cloak x-transition.opacity class="lg:hidden fixed inset-0 z-40 bg-slate-900/60" @click="nav = false"></div>

    <aside class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col bg-nav text-slate-300 transition-transform duration-200 lg:translate-x-0"
           :class="nav ? 'translate-x-0' : '-translate-x-full'" @keydown.escape.window="nav = false">
        <div class="h-16 px-5 flex items-center justify-between border-b border-white/10">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-white"><x-app-logo /></a>
            <button class="lg:hidden icon-btn !text-slate-400 hover:!bg-white/10 hover:!text-white" @click="nav = false" aria-label="Tutup menu"><flux:icon.x-mark class="size-5" /></button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6" aria-label="Menu utama">
            @foreach ($nav as $group => $items)
                <div>
                    <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $group }}</p>
                    <div class="space-y-0.5">
                        @foreach ($items as $item)
                            @php $active = request()->routeIs($item[0]); @endphp
                            <a href="{{ route($item[0]) }}" wire:navigate @click="nav = false"
                               @class(['flex items-center gap-3 h-10 px-3 rounded-lg text-sm font-medium transition-colors',
                                       'bg-white/10 text-white' => $active,
                                       'text-slate-400 hover:bg-white/5 hover:text-white' => ! $active])
                               @if ($active) aria-current="page" @endif>
                                <flux:icon :name="$item[1]" class="size-[18px] {{ $active ? 'text-brand-500' : '' }}" />
                                <span class="flex-1">{{ $item[2] }}</span>
                                @if (($item[3] ?? 0) > 0)<span class="min-w-5 h-5 px-1.5 grid place-items-center rounded-full bg-wait-500 text-slate-900 text-[11px] font-bold tnum">{{ $item[3] }}</span>@endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="p-3 border-t border-white/10" x-data="{ menu: false }">
            <div class="relative">
                <button @click="menu = !menu" class="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-white/5 text-left">
                    <span class="size-9 grid place-items-center rounded-full bg-mine-600 text-white text-sm font-bold">{{ $user->initials() }}</span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-white truncate">{{ $user->name }}</span>
                        <span class="block text-xs text-slate-400 truncate">{{ $user->role?->name }} · {{ $user->department }}</span>
                    </span>
                    <flux:icon.chevron-up-down class="size-4 text-slate-500" />
                </button>
                <div x-show="menu" x-cloak @click.outside="menu = false" x-transition class="absolute bottom-full mb-2 inset-x-0 card p-1.5 text-slate-700 shadow-xl">
                    <a href="{{ route('settings.profile') }}" wire:navigate class="flex items-center gap-2 px-3 h-9 rounded-md text-sm font-medium hover:bg-slate-100"><flux:icon.user-circle class="size-4" /> Profil & kalender saya</a>
                    <a href="{{ route('settings.password') }}" wire:navigate class="flex items-center gap-2 px-3 h-9 rounded-md text-sm font-medium hover:bg-slate-100"><flux:icon.key class="size-4" /> Kata sandi</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit" class="w-full flex items-center gap-2 px-3 h-9 rounded-md text-sm font-medium text-busy-700 hover:bg-busy-50"><flux:icon.arrow-right-start-on-rectangle class="size-4" /> Keluar</button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <div class="lg:pl-64 min-h-screen flex flex-col">
        <header class="sticky top-0 z-30 h-16 bg-white/90 backdrop-blur border-b border-slate-200 px-4 sm:px-6 lg:px-8 flex items-center gap-3">
            <button class="lg:hidden icon-btn" @click="nav = true" aria-label="Buka menu"><flux:icon.bars-3 class="size-5" /></button>
            <h1 class="text-lg font-bold tracking-tight truncate">{{ $title ?? config('app.name') }}</h1>
            <div class="flex-1"></div>
            <button class="btn btn-primary btn-sm sm:btn" onclick="Livewire.dispatch('book-room')"><flux:icon.plus class="size-4" /> <span class="hidden sm:inline">Pesan ruang</span><span class="sm:hidden">Pesan</span></button>
            <livewire:notification-bell />
        </header>

        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1500px] w-full mx-auto">
            {{ $slot }}
        </main>
    </div>

    <livewire:booking.form />
    <livewire:booking.detail />

    <div x-data="{ items: [], push(d) { const id = Date.now() + Math.random(); this.items.push({ id, m: d.message, error: d.type === 'error' }); setTimeout(() => this.items = this.items.filter(i => i.id !== id), 4500) } }"
         @toast.window="push($event.detail)" class="fixed bottom-4 right-4 left-4 sm:left-auto z-[70] flex flex-col gap-2 items-end pointer-events-none" aria-live="polite">
        <template x-for="i in items" :key="i.id">
            <div x-transition class="pointer-events-auto flex items-center gap-3 max-w-sm rounded-lg bg-ink text-white text-sm font-medium px-4 py-3 shadow-xl">
                <span class="size-2 rounded-full shrink-0" :class="i.error ? 'bg-busy-500' : 'bg-brand-500'"></span><span x-text="i.m"></span>
            </div>
        </template>
    </div>
    @fluxScripts
</body>
</html>

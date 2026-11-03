<div class="max-w-3xl space-y-6">
    @if (session('success'))<p class="flash bg-brand-50 border-brand-100 text-brand-700"><flux:icon.check-circle class="size-5 shrink-0" />{{ session('success') }}</p>@endif

    <section class="card card-pad">
        <h2 class="text-base font-bold">Profil</h2>
        <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="name">Nama</label>
                <input id="name" wire:model="name" class="input @error('name') is-invalid @enderror">
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="department">Departemen</label>
                <input id="department" wire:model="department" class="input">
            </div>
            <div>
                <label class="label">Email</label>
                <input value="{{ $user->email }}" class="input" disabled>
                <p class="hint">{{ $user->is_sso ? 'Dikelola oleh Microsoft. Hubungi IT untuk mengubah.' : 'Hubungi administrator untuk mengubah email.' }}</p>
            </div>
            <div>
                <label class="label">Peran</label>
                <input value="{{ $user->role?->name }}" class="input" disabled>
            </div>
            <div class="sm:col-span-2"><button class="btn btn-primary">Simpan</button></div>
        </form>
    </section>

    <section class="card card-pad" x-data="{ ok: false }">
        <div class="flex items-start gap-3">
            <span class="size-10 shrink-0 grid place-items-center rounded-lg bg-mine-50 text-mine-600"><flux:icon.calendar-days class="size-5" /></span>
            <div>
                <h2 class="text-base font-bold">Kalender pribadi</h2>
                <p class="text-sm text-slate-500 mt-0.5">Langganan sekali, lalu semua booking Anda muncul otomatis di Outlook, Google Calendar, atau Apple Calendar dan selalu terbarui.</p>
            </div>
        </div>
        <div class="mt-4 flex gap-2">
            <input readonly value="{{ $feedUrl }}" class="input font-mono !text-xs" @focus="$el.select()" aria-label="URL langganan kalender">
            <button type="button" class="btn btn-primary shrink-0" @click="navigator.clipboard.writeText('{{ $feedUrl }}'); ok = true; setTimeout(() => ok = false, 1800)">
                <flux:icon.clipboard-document class="size-4" /><span x-text="ok ? 'Tersalin ✓' : 'Salin'">Salin</span>
            </button>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-3 text-sm">
            <a href="https://outlook.office.com/calendar/0/addfromweb?url={{ urlencode($feedUrl) }}&name={{ urlencode('Ruang rapat saya') }}" target="_blank" rel="noopener" class="btn btn-secondary">Tambah ke Outlook</a>
            <a href="https://calendar.google.com/calendar/r?cid={{ urlencode(str_replace(['https://', 'http://'], 'webcal://', $feedUrl)) }}" target="_blank" rel="noopener" class="btn btn-secondary">Tambah ke Google</a>
            <a href="{{ str_replace(['https://', 'http://'], 'webcal://', $feedUrl) }}" class="btn btn-secondary">Apple / aplikasi lain</a>
        </div>
        <p class="hint mt-4">Tautan ini rahasia: siapa pun yang memilikinya dapat melihat jadwal Anda. <button wire:click="regenerateFeed" wire:confirm="Buat tautan baru? Langganan lama akan berhenti." class="font-semibold text-busy-700 underline">Buat tautan baru</button></p>
    </section>
</div>

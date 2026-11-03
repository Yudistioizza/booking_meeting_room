@php $sso = filled(config('services.azure.client_id')); @endphp
<div class="flex flex-col gap-7">
    <div>
        <h1 class="text-3xl font-bold tracking-tight">Masuk</h1>
        <p class="mt-1.5 text-slate-500">Gunakan akun kantor Anda untuk memesan ruang rapat.</p>
    </div>

    @if ($errors->has('azure'))
        <p class="flash bg-busy-50 border-busy-100 text-busy-700" role="alert"><flux:icon.exclamation-triangle class="size-5 shrink-0" /> {{ $errors->first('azure') }}</p>
    @endif
    <x-auth-session-status :status="session('status')" />

    @if ($sso)
        <a href="{{ route('login.azure') }}" class="btn btn-secondary btn-lg w-full !h-12">
            <svg viewBox="0 0 23 23" class="size-5" aria-hidden="true"><path fill="#f25022" d="M1 1h10v10H1z"/><path fill="#7fba00" d="M12 1h10v10H12z"/><path fill="#00a4ef" d="M1 12h10v10H1z"/><path fill="#ffb900" d="M12 12h10v10H12z"/></svg>
            Masuk dengan Microsoft
        </a>
        <div class="flex items-center gap-3 text-xs font-medium text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>atau dengan email<span class="h-px flex-1 bg-slate-200"></span></div>
    @endif

    <form wire:submit="login" class="flex flex-col gap-5">
        <div>
            <label class="label" for="email">Email kantor</label>
            <input id="email" type="email" wire:model="email" class="input !h-11 @error('email') is-invalid @enderror" placeholder="nama@perusahaan.com" autocomplete="email" autofocus required>
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div x-data="{ show: false }">
            <div class="flex items-center justify-between">
                <label class="label" for="password">Kata sandi</label>
                @if (Route::has('password.request'))<a href="{{ route('password.request') }}" wire:navigate class="text-xs font-semibold text-brand-700 hover:underline -mt-1.5">Lupa kata sandi?</a>@endif
            </div>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" wire:model="password" class="input !h-11 pr-11" autocomplete="current-password" required>
                <button type="button" class="absolute right-1.5 top-1.5 icon-btn !size-8" @click="show = !show" :aria-label="show ? 'Sembunyikan' : 'Tampilkan'"><flux:icon.eye class="size-4" /></button>
            </div>
        </div>
        <label class="flex items-center gap-2.5 text-sm text-slate-600 cursor-pointer"><input type="checkbox" wire:model="remember" class="size-4 rounded accent-brand-600"> Ingat saya di perangkat ini</label>
        <button type="submit" class="btn btn-primary btn-lg w-full !h-12" wire:loading.attr="disabled" data-test="login-button">
            <span wire:loading.remove wire:target="login">Masuk</span><span wire:loading wire:target="login">Memeriksa…</span>
        </button>
    </form>

    <p class="text-center text-xs text-slate-400">Belum punya akun? Hubungi administrator IT atau masuk dengan Microsoft.</p>
</div>

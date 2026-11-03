<section>
    <x-settings.layout heading="Ubah kata sandi" subheading="Gunakan kata sandi panjang dan acak. Akun SSO Microsoft tidak memakai kata sandi ini.">
        <form wire:submit="updatePassword" class="space-y-4">
            @foreach (['current_password' => ['Kata sandi saat ini', 'current-password'], 'password' => ['Kata sandi baru', 'new-password'], 'password_confirmation' => ['Ulangi kata sandi baru', 'new-password']] as $field => [$label, $auto])
                <div>
                    <label class="label" for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" type="password" wire:model="{{ $field }}" autocomplete="{{ $auto }}" class="input @error($field) is-invalid @enderror" required>
                    @error($field)<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div class="flex items-center gap-3 pt-1">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <x-action-message class="text-sm font-medium text-brand-700" on="password-updated">Tersimpan.</x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>

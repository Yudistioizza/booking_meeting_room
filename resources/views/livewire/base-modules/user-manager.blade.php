@php $roleTone = ['Admin' => 'badge-mine', 'Manager' => 'badge-wait', 'Employee' => 'badge-slate']; @endphp
<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative lg:w-80"><flux:icon.magnifying-glass class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input type="search" wire:model.live.debounce.300ms="search" class="input !pl-9" placeholder="Cari nama, email, departemen"></div>
        <select wire:model.live="filterRole" class="input lg:!w-44" aria-label="Peran"><option value="">Semua peran</option>@foreach ($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>
        <select wire:model.live="filterStatus" class="input lg:!w-40" aria-label="Status"><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
        <p class="text-sm text-slate-500 lg:ml-2"><b class="text-slate-800 tnum">{{ $total }}</b> pengguna · <b class="tnum">{{ $sso }}</b> via SSO · <b class="tnum">{{ $inactive }}</b> nonaktif</p>
        <div class="flex-1"></div>
        <button wire:click="create" class="btn btn-primary"><flux:icon.user-plus class="size-4" /> Tambah pengguna</button>
    </div>

    <div class="card overflow-hidden"><div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Pengguna</th><th>Departemen</th><th>Peran</th><th>Login terakhir</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse ($users as $u)
                    <tr wire:key="u-{{ $u->id }}" class="{{ $u->is_active ? '' : 'opacity-60' }}">
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="size-9 shrink-0 grid place-items-center rounded-full bg-slate-100 text-slate-600 text-sm font-bold">{{ $u->initials() }}</span>
                                <span class="min-w-0"><span class="block font-semibold truncate">{{ $u->name }} @if ($u->is_sso)<span class="badge badge-slate ml-1 !h-5 before:hidden">SSO</span>@endif</span><span class="block text-xs text-slate-500 truncate">{{ $u->email }}</span></span>
                            </div>
                        </td>
                        <td class="text-slate-600">{{ $u->department ?: '—' }}</td>
                        <td><span class="badge {{ $roleTone[$u->role?->name] ?? 'badge-slate' }}">{{ $u->role?->name ?? '—' }}</span></td>
                        <td class="text-slate-500 whitespace-nowrap">{{ $u->last_login_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                        <td>
                            <button wire:click="toggle({{ $u->id }})" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $u->is_active ? 'bg-brand-600' : 'bg-slate-300' }}" role="switch" aria-checked="{{ $u->is_active ? 'true' : 'false' }}" aria-label="Aktif">
                                <span class="inline-block size-5 rounded-full bg-white shadow transition-transform {{ $u->is_active ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                            </button>
                        </td>
                        <td><div class="flex justify-end gap-1">
                            <button class="icon-btn" wire:click="edit({{ $u->id }})" title="Ubah"><flux:icon.pencil-square class="size-4" /></button>
                            <button class="icon-btn hover:!text-busy-700" wire:click="confirmDelete({{ $u->id }})" title="Hapus"><flux:icon.trash class="size-4" /></button>
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty">Tidak ada pengguna yang cocok.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>@if ($users->hasPages())<div class="px-4 py-3 border-t border-slate-200">{{ $users->links() }}</div>@endif</div>

    @if ($open)
        <x-modal :title="$editId ? 'Ubah pengguna' : 'Pengguna baru'" close="$wire.set('open', false)">
            <form wire:submit="save">
                <div class="modal-body grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="label" for="u-name">Nama lengkap</label><input id="u-name" wire:model="name" class="input @error('name') is-invalid @enderror">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label class="label" for="u-email">Email kantor</label><input id="u-email" type="email" wire:model="email" class="input @error('email') is-invalid @enderror" placeholder="nama@perusahaan.com">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="u-dept">Departemen</label><input id="u-dept" wire:model="department" class="input"></div>
                    <div><label class="label" for="u-role">Peran</label><select id="u-role" wire:model="role_id" class="input @error('role_id') is-invalid @enderror">@foreach ($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>@error('role_id')<p class="error">{{ $message }}</p>@enderror
                        <p class="hint">Employee: memesan · Manager: + menyetujui & laporan · Admin: kelola semua.</p></div>
                    <div class="sm:col-span-2" x-data="{ show: false }">
                        <label class="label" for="u-pass">Kata sandi {{ $editId ? '(kosongkan jika tidak diubah)' : '(kosongkan jika hanya SSO)' }}</label>
                        <div class="flex gap-2">
                            <input id="u-pass" :type="show || $wire.password ? 'text' : 'password'" wire:model="password" class="input font-mono @error('password') is-invalid @enderror" autocomplete="new-password">
                            <button type="button" class="btn btn-secondary shrink-0" wire:click="generatePassword">Buat acak</button>
                        </div>
                        @error('password')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <label class="sm:col-span-2 flex items-center gap-2.5 text-sm cursor-pointer"><input type="checkbox" wire:model="is_active" class="size-4 rounded accent-brand-600"> Akun aktif</label>
                </div>
                <div class="modal-foot"><button type="button" class="btn btn-secondary" wire:click="$set('open', false)">Batal</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">Simpan</button></div>
            </form>
        </x-modal>
    @endif

    @if ($deleteId)
        <x-modal title="Hapus pengguna?" close="$wire.set('deleteId', null)">
            <div class="modal-body"><p class="text-slate-600">Akun tidak bisa masuk lagi. Riwayat booking tetap tersimpan. Untuk sementara, lebih aman cukup menonaktifkan.</p></div>
            <div class="modal-foot"><button class="btn btn-secondary" wire:click="$set('deleteId', null)">Batal</button><button class="btn btn-danger" wire:click="delete">Ya, hapus</button></div>
        </x-modal>
    @endif
</div>

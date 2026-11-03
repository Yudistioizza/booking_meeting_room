@php $dayNames = ['Mon' => 'Sen', 'Tue' => 'Sel', 'Wed' => 'Rab', 'Thu' => 'Kam', 'Fri' => 'Jum', 'Sat' => 'Sab', 'Sun' => 'Min']; @endphp
<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative sm:w-72">
            <flux:icon.magnifying-glass class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input !pl-9" placeholder="Cari nama atau lokasi">
        </div>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-600 cursor-pointer"><input type="checkbox" wire:model.live="showArchived" class="size-4 rounded accent-brand-600"> Tampilkan arsip</label>
        <div class="flex-1"></div>
        <button wire:click="create" class="btn btn-primary"><flux:icon.plus class="size-4" /> Tambah ruangan</button>
    </div>

    <div class="card overflow-hidden"><div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Ruangan</th><th>Kapasitas</th><th>Aturan</th><th>Jam operasional</th><th>Booking aktif</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse ($rooms as $r)
                    @php $rule = $r->availabilityRules->where('is_active', true)->first(); @endphp
                    <tr wire:key="room-{{ $r->id }}" class="{{ $r->trashed() || ! $r->is_active ? 'opacity-60' : '' }}">
                        <td>
                            <p class="font-semibold">{{ $r->name }} @if (! $r->is_active && ! $r->trashed())<span class="badge badge-slate ml-1">Nonaktif</span>@endif @if ($r->trashed())<span class="badge badge-slate ml-1">Arsip</span>@endif</p>
                            <p class="text-xs text-slate-500">{{ $r->location }}</p>
                        </td>
                        <td class="tnum whitespace-nowrap">{{ $r->capacity }} orang</td>
                        <td class="text-xs text-slate-600 space-y-0.5">
                            @if ($r->requires_approval)<span class="badge badge-wait">Perlu persetujuan</span>@endif
                            @if ($r->buffer_minutes)<span class="badge badge-slate before:hidden">Jeda {{ $r->buffer_minutes }} mnt</span>@endif
                            @if ($r->max_duration_minutes)<span class="badge badge-slate before:hidden">Maks {{ $r->max_duration_minutes / 60 }} jam</span>@endif
                        </td>
                        <td class="text-xs text-slate-600 tnum">{{ $rule ? (($rule->available_days ? implode(', ', array_map(fn ($d) => $dayNames[$d] ?? $d, explode(',', $rule->available_days))) : 'Setiap hari') . ' · ' . str_replace(',', ', ', $rule->available_hours ?: 'jam standar')) : 'Standar ' . config('booking.hours.from') . '–' . config('booking.hours.to') }}</td>
                        <td class="tnum">{{ $r->upcoming }}</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                @if ($r->trashed())
                                    <button class="btn btn-secondary btn-sm" wire:click="restore({{ $r->id }})">Pulihkan</button>
                                @else
                                    <button class="icon-btn" wire:click="$set('displayRoom', {{ $r->id }})" title="Tablet di depan ruang"><flux:icon.tv class="size-4" /></button>
                                    <button class="icon-btn" wire:click="edit({{ $r->id }})" title="Ubah"><flux:icon.pencil-square class="size-4" /></button>
                                    <button class="icon-btn hover:!text-busy-700" wire:click="archive({{ $r->id }})" wire:confirm="Arsipkan {{ $r->name }}? Ruang tidak bisa dipesan lagi, riwayat tetap tersimpan." title="Arsipkan"><flux:icon.archive-box class="size-4" /></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty">Belum ada ruangan.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>

    @if ($displayUrl)
        <x-modal title="Layar di depan ruang" subtitle="Buka tautan ini di tablet yang dipasang di pintu. Tanpa login, hanya menampilkan status." close="$wire.set('displayRoom', null)">
            <div class="modal-body space-y-3" x-data="{ ok: false }">
                <input readonly value="{{ $displayUrl }}" class="input font-mono !text-xs" @focus="$el.select()">
                <div class="flex gap-2">
                    <button class="btn btn-primary" @click="navigator.clipboard.writeText('{{ $displayUrl }}'); ok = true; setTimeout(() => ok = false, 1800)"><span x-text="ok ? 'Tersalin ✓' : 'Salin tautan'">Salin tautan</span></button>
                    <a href="{{ $displayUrl }}" target="_blank" class="btn btn-secondary">Buka pratinjau</a>
                </div>
            </div>
        </x-modal>
    @endif

    @if ($open)
        <x-modal :title="$editId ? 'Ubah ruangan' : 'Ruangan baru'" close="$wire.set('open', false)" size="sm:max-w-2xl">
            <form wire:submit="save">
                <div class="modal-body grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="r-name">Nama</label><input id="r-name" wire:model="name" class="input @error('name') is-invalid @enderror" placeholder="mis. Borobudur">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="r-loc">Lokasi</label><input id="r-loc" wire:model="location" class="input" placeholder="Lantai 2 · Sayap timur"></div>
                    <div><label class="label" for="r-cap">Kapasitas</label><input id="r-cap" type="number" min="1" wire:model="capacity" class="input tnum @error('capacity') is-invalid @enderror">@error('capacity')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label" for="r-eq">Fasilitas</label><input id="r-eq" wire:model="equipment" class="input" placeholder="Proyektor, Video conference, Whiteboard"><p class="hint">Pisahkan dengan koma.</p></div>

                    <div class="sm:col-span-2 border-t border-slate-200 pt-4"><p class="eyebrow">Aturan pemesanan</p></div>
                    <div><label class="label" for="r-max">Durasi maksimal (menit)</label><input id="r-max" type="number" min="15" step="15" wire:model="max_duration_minutes" class="input tnum" placeholder="Tanpa batas khusus"></div>
                    <div><label class="label" for="r-buf">Jeda antar pertemuan (menit)</label><input id="r-buf" type="number" min="0" step="5" wire:model="buffer_minutes" class="input tnum"><p class="hint">Waktu bersih-bersih / persiapan.</p></div>
                    <label class="sm:col-span-2 flex items-start gap-3 rounded-lg border border-slate-200 p-3 cursor-pointer"><input type="checkbox" wire:model="requires_approval" class="mt-0.5 size-4 rounded accent-brand-600"><span><b class="block text-sm">Perlu persetujuan manajer</b><span class="text-xs text-slate-500">Cocok untuk ruang eksekutif dan auditorium. Manajer & admin tetap bisa langsung memesan.</span></span></label>

                    <div class="sm:col-span-2 border-t border-slate-200 pt-4"><p class="eyebrow">Jam operasional</p><p class="hint !mt-1">Kosongkan untuk memakai jam standar perusahaan ({{ config('booking.hours.from') }}–{{ config('booking.hours.to') }}, Senin–Jumat).</p></div>
                    <div class="sm:col-span-2 flex flex-wrap gap-1.5">
                        @foreach ($dayNames as $k => $l)<label class="has-[:checked]:bg-brand-600 has-[:checked]:text-white has-[:checked]:border-brand-600 size-10 grid place-items-center rounded-lg border border-slate-300 text-sm font-semibold cursor-pointer"><input type="checkbox" wire:model="days" value="{{ $k }}" class="sr-only">{{ $l }}</label>@endforeach
                    </div>
                    <div class="sm:col-span-2"><label class="label" for="r-hours">Jam buka</label><input id="r-hours" wire:model="hours" class="input tnum @error('hours') is-invalid @enderror" placeholder="08:00-12:00,13:00-17:00">@error('hours')<p class="error">{{ $message }}</p>@enderror<p class="hint">Beberapa rentang dipisah koma, misalnya untuk jeda makan siang.</p></div>

                    <div class="sm:col-span-2 border-t border-slate-200 pt-4"><p class="eyebrow">Integrasi</p></div>
                    <div class="sm:col-span-2"><label class="label" for="r-cal">Email kalender ruang (Exchange)</label><input id="r-cal" type="email" wire:model="calendar_email" class="input @error('calendar_email') is-invalid @enderror" placeholder="room.borobudur@perusahaan.com">@error('calendar_email')<p class="error">{{ $message }}</p>@enderror<p class="hint">Jika diisi, event Outlook menambahkan ruang ini sebagai resource sehingga terlihat sibuk di Room Finder.</p></div>
                    <label class="sm:col-span-2 flex items-center gap-2.5 text-sm cursor-pointer"><input type="checkbox" wire:model="is_active" class="size-4 rounded accent-brand-600"> Ruangan aktif (dapat dipesan)</label>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-secondary" wire:click="$set('open', false)">Batal</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">Simpan</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>

<div>
@if ($open)
    @php
        $room = $this->room;
        $av = $this->availability;
        $user = auth()->user();
        $needsApproval = $room && $room->requires_approval && ! $user->isApprover();
        $days = ['Mon' => 'Sen', 'Tue' => 'Sel', 'Wed' => 'Rab', 'Thu' => 'Kam', 'Fri' => 'Jum', 'Sat' => 'Sab', 'Sun' => 'Min'];
    @endphp

    @if ($result)
        <x-modal title="{{ $result['status'] === 'pending' ? 'Permintaan terkirim' : 'Ruang berhasil dipesan' }}" close="$wire.close()">
            <div class="modal-body text-center py-8">
                <span class="mx-auto size-14 grid place-items-center rounded-full {{ $result['status'] === 'pending' ? 'bg-wait-100 text-wait-700' : 'bg-brand-50 text-brand-600' }}">
                    <flux:icon :name="$result['status'] === 'pending' ? 'clock' : 'check'" class="size-7" />
                </span>
                <p class="mt-4 text-xl font-bold">{{ $result['room'] }}</p>
                <p class="text-slate-600">{{ $result['when'] }}@if ($result['count'] > 1) <span class="badge badge-mine ml-1">{{ $result['count'] }} pertemuan</span>@endif</p>
                <p class="mt-4 text-sm text-slate-500 max-w-sm mx-auto">
                    @if ($result['status'] === 'pending')
                        Ruang ini perlu persetujuan manajer. Kami akan mengabari Anda lewat email begitu diproses.
                    @else
                        Undangan kalender (.ics) sudah dikirim ke email Anda{{ trim($attendees) !== '' ? ' dan para tamu' : '' }}.
                    @endif
                </p>
                @if ($result['skipped'])
                    <div class="mt-5 text-left flash bg-wait-50 border-wait-100 text-wait-700">
                        <flux:icon.exclamation-triangle class="size-5 shrink-0" />
                        <div><b>{{ count($result['skipped']) }} tanggal dilewati karena bentrok:</b>
                            <ul class="mt-1 list-disc pl-4 font-normal">@foreach (array_slice($result['skipped'], 0, 5) as $s)<li>{{ $s['date'] }}</li>@endforeach</ul></div>
                    </div>
                @endif
            </div>
            <div class="modal-foot">
                <a href="{{ route('bookings.ics', $result['booking']) }}" class="btn btn-secondary"><flux:icon.arrow-down-tray class="size-4" /> Unduh .ics</a>
                <button class="btn btn-primary" wire:click="close">Selesai</button>
            </div>
        </x-modal>
    @else
        <x-modal title="Pesan ruang rapat" close="$wire.close()" size="sm:max-w-2xl">
            <form wire:submit="save">
                <div class="modal-body grid gap-5 sm:grid-cols-6">
                    <div class="sm:col-span-6">
                        <label class="label" for="f-title">Judul pertemuan</label>
                        <input id="f-title" wire:model="title" class="input @error('title') is-invalid @enderror" placeholder="mis. Sprint planning tim produk" autofocus>
                        @error('title')<p class="error">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-6">
                        <label class="label" for="f-room">Ruangan</label>
                        <select id="f-room" wire:model.live="room_id" class="input @error('room_id') is-invalid @enderror">
                            <option value="">Pilih ruangan…</option>
                            @foreach ($this->rooms->groupBy('location') as $loc => $group)
                                <optgroup label="{{ $loc ?: 'Lainnya' }}">
                                    @foreach ($group as $r)<option value="{{ $r->id }}">{{ $r->name }} · {{ $r->capacity }} orang{{ $r->requires_approval ? ' · perlu persetujuan' : '' }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('room_id')<p class="error">{{ $message }}</p>@enderror
                        @if ($room?->equipment)<p class="hint">{{ $room->equipment }}</p>@endif
                    </div>

                    <div class="sm:col-span-3">
                        <label class="label" for="f-date">Tanggal</label>
                        <input id="f-date" type="date" wire:model.live="date" min="{{ now()->toDateString() }}" class="input">
                    </div>
                    <div class="sm:col-span-3 grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="f-start">Mulai</label>
                            <select id="f-start" wire:model.live="start" class="input tnum">@foreach ($times as $t)<option>{{ $t }}</option>@endforeach</select>
                        </div>
                        <div>
                            <label class="label" for="f-end">Selesai</label>
                            <select id="f-end" wire:model.live="end" class="input tnum">@foreach ($times as $t)<option>{{ $t }}</option>@endforeach</select>
                        </div>
                    </div>
                    <div class="sm:col-span-6 -mt-2 flex flex-wrap gap-2">
                        @foreach ([30 => '30 mnt', 60 => '1 jam', 90 => '1,5 jam', 120 => '2 jam', 180 => '3 jam'] as $m => $l)
                            <button type="button" wire:click="setDuration({{ $m }})" class="btn btn-secondary btn-sm">{{ $l }}</button>
                        @endforeach
                    </div>

                    {{-- Live availability --}}
                    @if ($room)
                        <div class="sm:col-span-6">
                            @if ($av['ok'])
                                <p class="flash bg-brand-50 border-brand-100 text-brand-700"><flux:icon.check-circle class="size-5 shrink-0" /> {{ $room->name }} tersedia pada waktu ini.@if ($needsApproval) Perlu persetujuan manajer.@endif</p>
                            @else
                                <div class="flash bg-busy-50 border-busy-100 text-busy-700 flex-col !items-stretch">
                                    <div class="flex items-start gap-3"><flux:icon.exclamation-circle class="size-5 shrink-0" /> <span>{{ $av['message'] }}</span></div>
                                    @if ($av['slots'])
                                        <div class="flex flex-wrap items-center gap-2 pl-8">
                                            <span class="text-xs font-semibold text-slate-600">Waktu kosong:</span>
                                            @foreach ($av['slots'] as [$s, $e])
                                                <button type="button" wire:click="pick('{{ $s->format('H:i') }}', '{{ $e->format('H:i') }}')" class="btn btn-secondary btn-sm tnum">{{ $s->format('H:i') }}–{{ $e->format('H:i') }}</button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="sm:col-span-2">
                        <label class="label" for="f-people">Jumlah peserta</label>
                        <input id="f-people" type="number" min="1" max="500" wire:model.live.debounce.400ms="people" class="input tnum @error('people') is-invalid @enderror">
                        @if ($room)<p class="hint">Kapasitas {{ $room->capacity }} orang</p>@endif
                    </div>
                    <div class="sm:col-span-4">
                        <label class="label" for="f-att">Undang peserta <span class="font-normal text-slate-400">(opsional)</span></label>
                        <input id="f-att" wire:model.live.debounce.500ms="attendees" class="input" placeholder="email1@perusahaan.com, tamu@vendor.com">
                        <p class="hint">Mereka menerima undangan kalender lewat email.</p>
                    </div>
                    <div class="sm:col-span-6">
                        <label class="label" for="f-desc">Agenda / catatan <span class="font-normal text-slate-400">(opsional)</span></label>
                        <textarea id="f-desc" wire:model="description" rows="2" class="input"></textarea>
                    </div>

                    {{-- Recurrence --}}
                    <div class="sm:col-span-6 rounded-lg border border-slate-200 p-4 space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-sm font-semibold flex items-center gap-2"><flux:icon.arrow-path class="size-4 text-slate-500" /> Ulangi</span>
                            <div class="seg" role="tablist">
                                @foreach (['none' => 'Tidak', 'daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'] as $k => $l)
                                    <button type="button" role="tab" aria-selected="{{ $repeat === $k ? 'true' : 'false' }}" wire:click="$set('repeat', '{{ $k }}')">{{ $l }}</button>
                                @endforeach
                            </div>
                        </div>
                        @if ($repeat !== 'none')
                            <div class="grid gap-4 sm:grid-cols-2">
                                @if ($repeat === 'weekly')
                                    <div class="sm:col-span-2">
                                        <p class="label">Pada hari</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($days as $k => $l)
                                                <label class="has-[:checked]:bg-brand-600 has-[:checked]:text-white has-[:checked]:border-brand-600 size-10 grid place-items-center rounded-lg border border-slate-300 text-sm font-semibold cursor-pointer">
                                                    <input type="checkbox" wire:model="repeat_days" value="{{ $k }}" class="sr-only">{{ $l }}</label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div>
                                        <label class="label" for="f-int">Setiap (minggu)</label>
                                        <input id="f-int" type="number" min="1" max="12" wire:model="repeat_interval" class="input tnum">
                                    </div>
                                @endif
                                <div>
                                    <label class="label" for="f-until">Sampai tanggal</label>
                                    <input id="f-until" type="date" wire:model="repeat_until" class="input @error('repeat_until') is-invalid @enderror">
                                    @error('repeat_until')<p class="error">{{ $message }}</p>@enderror
                                </div>
                                <label class="sm:col-span-2 flex items-center gap-2.5 text-sm text-slate-600 cursor-pointer"><input type="checkbox" wire:model="skip_conflicts" class="size-4 rounded accent-brand-600"> Lewati tanggal yang bentrok (jangan batalkan seluruh seri)</label>
                            </div>
                        @endif
                    </div>

                    @if ($error)
                        <p class="sm:col-span-6 flash bg-busy-50 border-busy-100 text-busy-700" role="alert"><flux:icon.exclamation-triangle class="size-5 shrink-0" /> {{ $error }}</p>
                    @endif
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-secondary" wire:click="close">Batal</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save" @disabled($room && ! $av['ok'])>
                        <span wire:loading.remove wire:target="save">{{ $needsApproval ? 'Ajukan persetujuan' : 'Pesan ruang' }}</span><span wire:loading wire:target="save">Memproses…</span>
                    </button>
                </div>
            </form>
        </x-modal>
    @endif
@endif
</div>

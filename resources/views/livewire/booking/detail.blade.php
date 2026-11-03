<div>
@if ($b)
    <x-modal :title="$b->title" :subtitle="$b->room->name . ' · ' . $b->start_time->translatedFormat('l, d F Y')" close="$wire.close()" size="sm:max-w-xl">
        <div class="modal-body space-y-5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="badge {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span>
                @if ($b->isLive())<span class="badge badge-brand">Sedang berlangsung</span>@endif
                @if ($b->recurring_booking_id)<span class="badge badge-mine">Berulang</span>@endif
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div><dt class="eyebrow">Waktu</dt><dd class="mt-1 font-semibold tnum">{{ $b->start_time->format('H:i') }}–{{ $b->end_time->format('H:i') }} <span class="font-normal text-slate-500">· {{ $b->durationLabel() }}</span></dd></div>
                <div><dt class="eyebrow">Ruangan</dt><dd class="mt-1 font-semibold">{{ $b->room->name }}<span class="block font-normal text-slate-500 text-xs">{{ $b->room->location }}</span></dd></div>
                <div><dt class="eyebrow">Pemesan</dt><dd class="mt-1 font-semibold">{{ $b->creator->name }}<span class="block font-normal text-slate-500 text-xs">{{ $b->creator->department }}</span></dd></div>
                <div><dt class="eyebrow">Peserta</dt><dd class="mt-1 font-semibold">{{ $b->attendee_count }} orang</dd></div>
                @if ($b->description)<div class="col-span-2"><dt class="eyebrow">Agenda</dt><dd class="mt-1 text-slate-700 whitespace-pre-line">{{ $b->description }}</dd></div>@endif
                @if ($b->attendees)<div class="col-span-2"><dt class="eyebrow">Diundang</dt><dd class="mt-1.5 flex flex-wrap gap-1.5">@foreach ($b->attendees as $a)<span class="badge badge-slate !font-medium before:hidden">{{ $a }}</span>@endforeach</dd></div>@endif
                @if ($b->status === 'cancelled' && $b->cancel_reason)<div class="col-span-2"><dt class="eyebrow">Alasan batal</dt><dd class="mt-1">{{ $b->cancel_reason }}</dd></div>@endif
            </dl>

            @if ($b->approvals->isNotEmpty())
                <div>
                    <p class="eyebrow mb-2">Riwayat persetujuan</p>
                    @foreach ($b->approvals as $a)
                        <p class="text-sm flex items-start gap-2"><span class="mt-1.5 size-2 rounded-full {{ $a->status === 'approved' ? 'bg-brand-600' : 'bg-busy-500' }}"></span><span><b>{{ $a->approver->name }}</b> {{ $a->status === 'approved' ? 'menyetujui' : 'menolak' }} · {{ $a->created_at->translatedFormat('d M H:i') }}@if ($a->note)<span class="block text-slate-500">“{{ $a->note }}”</span>@endif</span></p>
                    @endforeach
                </div>
            @endif

            @if (in_array($b->status, ['approved', 'pending']))
                <div>
                    <p class="eyebrow mb-2">Tambah ke kalender</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $links['outlook'] }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">Outlook</a>
                        <a href="{{ $links['google'] }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">Google Calendar</a>
                        <a href="{{ route('bookings.ics', $b) }}" class="btn btn-secondary btn-sm"><flux:icon.arrow-down-tray class="size-4" /> Unduh .ics</a>
                    </div>
                </div>
            @endif

            @if ($mode === 'cancel')
                <div class="rounded-lg border border-busy-100 bg-busy-50 p-4 space-y-3">
                    <p class="font-semibold text-busy-700">Batalkan booking ini?</p>
                    <div>
                        <label class="label" for="d-reason">Alasan <span class="font-normal text-slate-500">(dikirim ke peserta)</span></label>
                        <input id="d-reason" wire:model="note" class="input" placeholder="mis. Agenda dipindahkan">
                    </div>
                    @if ($b->recurring_booking_id)<label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" wire:model="following" class="size-4 rounded accent-busy-500"> Batalkan juga semua pertemuan berikutnya dalam seri ini</label>@endif
                </div>
            @elseif ($mode === 'move')
                <div class="rounded-lg border border-slate-200 p-4 grid gap-3 sm:grid-cols-4">
                    <div class="sm:col-span-4"><p class="font-semibold">Ubah jadwal</p></div>
                    <div class="sm:col-span-2"><label class="label">Ruangan</label>
                        <select wire:model="room_id" class="input">@foreach ($rooms as $r)<option value="{{ $r->id }}">{{ $r->name }} ({{ $r->capacity }})</option>@endforeach</select></div>
                    <div class="sm:col-span-2"><label class="label">Tanggal</label><input type="date" wire:model="date" min="{{ now()->toDateString() }}" class="input"></div>
                    <div class="sm:col-span-2"><label class="label">Mulai</label><select wire:model.live="start" class="input tnum">@foreach ($times as $t)<option>{{ $t }}</option>@endforeach</select></div>
                    <div class="sm:col-span-2"><label class="label">Selesai</label><select wire:model="end" class="input tnum">@foreach ($times as $t)<option>{{ $t }}</option>@endforeach</select></div>
                </div>
            @elseif ($mode === 'decide')
                <div class="rounded-lg border p-4 space-y-3 {{ $decision === 'approve' ? 'border-brand-100 bg-brand-50' : 'border-busy-100 bg-busy-50' }}">
                    <p class="font-semibold {{ $decision === 'approve' ? 'text-brand-700' : 'text-busy-700' }}">{{ $decision === 'approve' ? 'Setujui permintaan ini' : 'Tolak permintaan ini' }}</p>
                    <div><label class="label" for="d-note">Catatan untuk pemesan <span class="font-normal text-slate-500">(opsional)</span></label><input id="d-note" wire:model="note" class="input"></div>
                </div>
            @endif

            @if ($error)<p class="flash bg-busy-50 border-busy-100 text-busy-700" role="alert"><flux:icon.exclamation-triangle class="size-5 shrink-0" /> {{ $error }}</p>@endif
        </div>

        <div class="modal-foot">
            @if ($mode === 'view')
                @if ($canDecide)
                    <button class="btn btn-secondary text-busy-700" wire:click="setMode('decide', 'reject')">Tolak</button>
                    <button class="btn btn-primary" wire:click="setMode('decide', 'approve')">Setujui</button>
                @endif
                @if ($canManage)<button class="btn btn-secondary text-busy-700 mr-auto" wire:click="setMode('cancel')">Batalkan</button>@endif
                @if ($canMove)<button class="btn btn-secondary" wire:click="setMode('move')">Ubah jadwal</button>@endif
                <button class="btn btn-dark" wire:click="close">Tutup</button>
            @else
                <button class="btn btn-secondary" wire:click="setMode('view')">Kembali</button>
                @if ($mode === 'cancel')<button class="btn btn-danger" wire:click="cancel" wire:loading.attr="disabled">Ya, batalkan</button>@endif
                @if ($mode === 'move')<button class="btn btn-primary" wire:click="move" wire:loading.attr="disabled">Simpan jadwal</button>@endif
                @if ($mode === 'decide')<button class="btn {{ $decision === 'approve' ? 'btn-primary' : 'btn-danger' }}" wire:click="decide" wire:loading.attr="disabled">{{ $decision === 'approve' ? 'Setujui' : 'Tolak' }}</button>@endif
            @endif
        </div>
    </x-modal>
@endif
</div>

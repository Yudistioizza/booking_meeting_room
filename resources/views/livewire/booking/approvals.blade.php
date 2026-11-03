<div class="space-y-5">
    <div class="seg" role="tablist">
        <button role="tab" aria-selected="{{ $tab === 'pending' ? 'true' : 'false' }}" wire:click="$set('tab', 'pending')">Menunggu <span class="ml-1 text-xs text-slate-400 tnum">{{ $pending->count() }}</span></button>
        <button role="tab" aria-selected="{{ $tab === 'history' ? 'true' : 'false' }}" wire:click="$set('tab', 'history')">Riwayat keputusan</button>
    </div>

    @if ($tab === 'pending')
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse ($pending as $b)
                <article class="card p-5 space-y-4" wire:key="ap-{{ $b->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold truncate">{{ $b->title }}</h3>
                            <p class="text-sm text-slate-500">{{ $b->creator->name }}@if ($b->creator->department) · {{ $b->creator->department }}@endif</p>
                        </div>
                        <span class="badge badge-wait">Menunggu</span>
                    </div>
                    <dl class="grid grid-cols-3 gap-3 text-sm">
                        <div><dt class="eyebrow">Ruang</dt><dd class="mt-1 font-semibold">{{ $b->room->name }}</dd></div>
                        <div><dt class="eyebrow">Waktu</dt><dd class="mt-1 font-semibold tnum">{{ $b->start_time->translatedFormat('D d M') }}<br>{{ $b->start_time->format('H:i') }}–{{ $b->end_time->format('H:i') }}</dd></div>
                        <div><dt class="eyebrow">Peserta</dt><dd class="mt-1 font-semibold tnum">{{ $b->attendee_count }} / {{ $b->room->capacity }}</dd></div>
                    </dl>
                    @if ($b->description)<p class="text-sm text-slate-600 line-clamp-2">{{ $b->description }}</p>@endif
                    <div class="flex gap-2 pt-1">
                        <button class="btn btn-primary flex-1" wire:click="$dispatch('show-booking', { id: {{ $b->id }} })">Tinjau & putuskan</button>
                    </div>
                </article>
            @empty
                <div class="card empty lg:col-span-2"><flux:icon.check-badge class="size-10 text-brand-500" /><p class="font-semibold">Semua sudah diproses.</p><p class="text-sm text-slate-500">Permintaan untuk ruang eksekutif akan muncul di sini.</p></div>
            @endforelse
        </div>
    @else
        <div class="card overflow-hidden"><div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Keputusan</th><th>Booking</th><th>Pemesan</th><th>Oleh</th><th>Catatan</th><th>Waktu</th></tr></thead>
                <tbody>
                    @forelse ($history as $a)
                        <tr>
                            <td><span class="badge {{ $a->status === 'approved' ? 'badge-brand' : 'badge-busy' }}">{{ $a->status === 'approved' ? 'Disetujui' : 'Ditolak' }}</span></td>
                            <td><span class="font-semibold">{{ $a->booking?->title }}</span><span class="block text-xs text-slate-500">{{ $a->booking?->room?->name }} · {{ $a->booking?->start_time->translatedFormat('d M, H:i') }}</span></td>
                            <td>{{ $a->booking?->creator?->name }}</td>
                            <td>{{ $a->approver?->name }}</td>
                            <td class="text-slate-500 max-w-60 truncate">{{ $a->note ?: '—' }}</td>
                            <td class="text-slate-500 whitespace-nowrap">{{ $a->created_at->translatedFormat('d M H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">Belum ada keputusan.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>@if ($history?->hasPages())<div class="px-4 py-3 border-t border-slate-200">{{ $history->links() }}</div>@endif</div>
    @endif
</div>

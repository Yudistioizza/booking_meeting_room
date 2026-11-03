@php $tones = ['created' => 'badge-brand', 'approved' => 'badge-brand', 'updated' => 'badge-mine', 'rejected' => 'badge-busy', 'cancelled' => 'badge-busy', 'archived' => 'badge-wait']; @endphp
<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row">
        <div class="relative sm:w-80"><flux:icon.magnifying-glass class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input type="search" wire:model.live.debounce.300ms="search" class="input !pl-9" placeholder="Cari kegiatan atau nama"></div>
        <select wire:model.live="group" class="input sm:!w-48" aria-label="Jenis"><option value="">Semua jenis</option>@foreach ($groups as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
    </div>
    <div class="card overflow-hidden"><div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Kegiatan</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($logs as $l)
                    <tr wire:key="log-{{ $l->id }}">
                        <td class="text-slate-500 whitespace-nowrap tnum">{{ $l->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td class="font-medium whitespace-nowrap">{{ $l->user?->name ?? 'Sistem' }}</td>
                        <td><span class="badge {{ $tones[\Illuminate\Support\Str::after($l->action, '.')] ?? 'badge-slate' }}">{{ $l->action }}</span></td>
                        <td class="text-slate-700">{{ $l->summary }}</td>
                        <td class="font-mono text-xs text-slate-400">{{ $l->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty">Belum ada catatan.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>@if ($logs->hasPages())<div class="px-4 py-3 border-t border-slate-200">{{ $logs->links() }}</div>@endif</div>
</div>

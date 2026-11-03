@php
    $t = $r['totals'];
    $heatMax = max(1, collect($r['heat'])->max() ?: 1);
    $dayLabels = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum'];
    $dailyMax = max(1, collect($r['daily'])->max() ?: 1);
    $tone = fn ($u) => $u >= 60 ? 'bg-brand-600' : ($u >= 30 ? 'bg-brand-500' : ($u >= 15 ? 'bg-wait-500' : 'bg-busy-500'));
@endphp
<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
        <div class="flex flex-wrap items-end gap-3">
            <div><label class="label" for="r-from">Dari</label><input id="r-from" type="date" wire:model.live="from" class="input !w-40"></div>
            <div><label class="label" for="r-to">Sampai</label><input id="r-to" type="date" wire:model.live="to" class="input !w-40"></div>
            <div class="seg">@foreach ([7 => '7 hari', 30 => '30 hari', 90 => '90 hari'] as $d => $l)<button wire:click="preset({{ $d }})">{{ $l }}</button>@endforeach</div>
        </div>
        <div class="flex-1"></div>
        <a href="{{ route('reports.export', ['from' => $fromD->toDateString(), 'to' => $toD->toDateString()]) }}" class="btn btn-secondary"><flux:icon.arrow-down-tray class="size-4" /> Ekspor CSV</a>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ([
            ['Utilisasi ruang', $t['utilization'] . '%', 'dari jam operasional'],
            ['Pertemuan', number_format($t['bookings']), $t['hours'] . ' jam terpakai'],
            ['Rata-rata durasi', $t['avg_minutes'] . ' mnt', 'per pertemuan'],
            ['Pembatalan', $t['cancel_rate'] . '%', $t['cancelled'] . ' booking dibatalkan'],
        ] as [$l, $v, $s])
            <div class="card card-pad"><p class="text-xs font-semibold text-slate-500">{{ $l }}</p><p class="mt-2 text-3xl font-bold tracking-tight tnum">{{ $v }}</p><p class="text-xs text-slate-500 mt-1">{{ $s }}</p></div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-5">
        <section class="card card-pad xl:col-span-3 min-w-0">
            <div class="flex items-baseline justify-between"><h2 class="font-bold">Utilisasi per ruangan</h2><span class="text-xs text-slate-500">jam terpakai ÷ jam buka</span></div>
            <div class="mt-4 space-y-3">
                @foreach ($r['rooms'] as $room)
                    <div class="grid grid-cols-[9.5rem_1fr_3.5rem] items-center gap-3 text-sm">
                        <span class="truncate font-medium" title="{{ $room['name'] }}">{{ $room['name'] }}</span>
                        <div class="h-3 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full {{ $tone($room['utilization']) }}" style="width: {{ max(1, $room['utilization']) }}%"></div></div>
                        <span class="text-right font-semibold tnum">{{ $room['utilization'] }}%</span>
                    </div>
                @endforeach
            </div>
            @if ($r['idle'])<p class="mt-5 flash bg-wait-50 border-wait-100 text-wait-700"><flux:icon.light-bulb class="size-5 shrink-0" /> <span><b>Kurang terpakai (&lt;15%):</b> {{ implode(', ', $r['idle']) }}. Pertimbangkan alih fungsi atau promosikan ruang ini.</span></p>@endif
        </section>

        <section class="card card-pad xl:col-span-2 min-w-0">
            <h2 class="font-bold">Jam tersibuk</h2>
            <p class="text-xs text-slate-500">Menit terpakai per jam, semua ruang.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-center text-[11px] tnum border-separate border-spacing-1">
                    <thead><tr><th></th>@foreach ($dayLabels as $l)<th class="font-semibold text-slate-500 pb-1">{{ $l }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach (range(8, 18) as $h)
                            <tr><th class="text-right pr-1 font-medium text-slate-400">{{ sprintf('%02d', $h) }}</th>
                                @foreach ($dayLabels as $d => $_)
                                    @php $v = $r['heat'][$d . '-' . $h] ?? 0; $a = $v / $heatMax; @endphp
                                    <td class="h-6 rounded" style="background: rgb(13 148 136 / {{ round(0.06 + $a * 0.94, 2) }})" title="{{ $dayLabels[$d] }} {{ $h }}:00 · {{ round($v) }} mnt"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="card card-pad">
        <h2 class="font-bold">Jam rapat per hari</h2>
        <div class="mt-4 flex items-end gap-1 h-32 overflow-x-auto">
            @php $cursor = $fromD->copy(); @endphp
            @while ($cursor->lte($toD) && $cursor->diffInDays($fromD) < 120)
                @php $v = $r['daily'][$cursor->toDateString()] ?? 0; @endphp
                <div class="flex-1 min-w-[6px] rounded-t {{ $cursor->isWeekend() ? 'bg-slate-200' : 'bg-brand-500' }}" style="height: {{ $v ? max(3, $v / $dailyMax * 100) : 2 }}%" title="{{ $cursor->translatedFormat('D d M') }} · {{ $v }} jam"></div>
                @php $cursor->addDay(); @endphp
            @endwhile
        </div>
        <div class="mt-2 flex justify-between text-xs text-slate-400 tnum"><span>{{ $fromD->translatedFormat('d M') }}</span><span>{{ $toD->translatedFormat('d M') }}</span></div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200"><h2 class="font-bold">Per departemen</h2></div>
            <table class="table"><thead><tr><th>Departemen</th><th class="text-right">Pertemuan</th><th class="text-right">Jam</th></tr></thead>
                <tbody>@foreach ($r['departments'] as $d)<tr><td class="font-medium">{{ $d['name'] }}</td><td class="text-right tnum">{{ $d['bookings'] }}</td><td class="text-right tnum font-semibold">{{ $d['hours'] }}</td></tr>@endforeach</tbody></table>
        </section>
        <section class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200"><h2 class="font-bold">Pengguna teraktif</h2></div>
            <table class="table"><thead><tr><th>Nama</th><th class="text-right">Pertemuan</th><th class="text-right">Jam</th></tr></thead>
                <tbody>@foreach ($r['users'] as $u)<tr><td><span class="font-medium">{{ $u['name'] }}</span><span class="block text-xs text-slate-500">{{ $u['department'] }}</span></td><td class="text-right tnum">{{ $u['bookings'] }}</td><td class="text-right tnum font-semibold">{{ $u['hours'] }}</td></tr>@endforeach</tbody></table>
        </section>
    </div>
</div>

<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Room utilisation analytics. Aggregation happens in PHP so the same code
 * runs on SQLite, MySQL, PostgreSQL and SQL Server.
 */
class ReportService
{
    public function __construct(private BookingService $svc)
    {
    }

    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->endOfDay();
        $rooms = Room::with('availabilityRules')->where('is_active', true)->orderBy('name')->get()->keyBy('id');

        $bookings = Booking::with('creator:id,name,department')
            ->whereBetween('start_time', [$from, $to])
            ->get(['id', 'room_id', 'created_by', 'status', 'start_time', 'end_time', 'attendee_count']);

        $done = $bookings->where('status', 'approved');
        $minutes = fn ($b) => $b->start_time->diffInMinutes($b->end_time);

        // Open minutes per room over the elapsed part of the range
        $elapsedTo = $to->isFuture() ? now()->endOfDay() : $to;
        $open = [];
        for ($d = $from->copy(); $d->lte($elapsedTo); $d->addDay()) {
            foreach ($rooms as $room) {
                $open[$room->id] = ($open[$room->id] ?? 0) + collect($this->svc->openIntervals($room, $d))->sum(fn ($r) => $r[0]->diffInMinutes($r[1]));
            }
        }

        $byRoom = $rooms->map(function (Room $room) use ($done, $open, $minutes) {
            $mine = $done->where('room_id', $room->id);
            $used = $mine->sum($minutes);
            $avail = max(1, $open[$room->id] ?? 0);

            return [
                'id' => $room->id, 'name' => $room->name, 'capacity' => $room->capacity, 'bookings' => $mine->count(),
                'hours' => round($used / 60, 1), 'utilization' => min(100, round($used / $avail * 100, 1)),
                'fill' => $mine->count() && $room->capacity ? min(100, round($mine->avg('attendee_count') / $room->capacity * 100)) : 0,
            ];
        })->sortByDesc('utilization')->values();

        $totalOpen = max(1, array_sum($open));
        $usedAll = $done->sum($minutes);

        // Busiest hours: minutes booked in each weekday x hour cell
        $heat = [];
        foreach ($done as $b) {
            for ($t = $b->start_time->copy()->startOfHour(); $t->lt($b->end_time); $t->addHour()) {
                $from2 = max($t->timestamp, $b->start_time->timestamp);
                $to2 = min($t->copy()->addHour()->timestamp, $b->end_time->timestamp);
                $key = $t->dayOfWeekIso . '-' . $t->hour;
                $heat[$key] = ($heat[$key] ?? 0) + max(0, ($to2 - $from2) / 60);
            }
        }

        $byDept = $done->groupBy(fn ($b) => $b->creator?->department ?: 'Lainnya')
            ->map(fn ($g, $dept) => ['name' => $dept, 'bookings' => $g->count(), 'hours' => round($g->sum($minutes) / 60, 1)])
            ->sortByDesc('hours')->values();

        $byUser = $done->groupBy('created_by')->map(fn ($g) => [
            'name' => $g->first()->creator?->name ?? '—', 'department' => $g->first()->creator?->department,
            'bookings' => $g->count(), 'hours' => round($g->sum($minutes) / 60, 1),
        ])->sortByDesc('hours')->values();

        $daily = [];
        foreach ($done->groupBy(fn ($b) => $b->start_time->toDateString()) as $day => $g) {
            $daily[$day] = round($g->sum($minutes) / 60, 1);
        }

        return [
            'from' => $from, 'to' => $to,
            'totals' => [
                'bookings' => $done->count(),
                'hours' => round($usedAll / 60, 1),
                'utilization' => min(100, round($usedAll / $totalOpen * 100, 1)),
                'cancelled' => $bookings->where('status', 'cancelled')->count(),
                'cancel_rate' => $bookings->count() ? round($bookings->where('status', 'cancelled')->count() / $bookings->count() * 100, 1) : 0,
                'avg_minutes' => $done->count() ? round($usedAll / $done->count()) : 0,
                'rooms' => $rooms->count(),
            ],
            'rooms' => $byRoom,
            'heat' => $heat,
            'departments' => $byDept,
            'users' => $byUser->take(8),
            'daily' => $daily,
            'idle' => $byRoom->where('utilization', '<', 15)->pluck('name')->all(),
        ];
    }

    /** One CSV row per booking for finance / facility management. */
    public function csv(CarbonInterface $from, CarbonInterface $to)
    {
        $rows = Booking::with('room:id,name,location', 'creator:id,name,email,department')
            ->whereBetween('start_time', [Carbon::instance($from)->startOfDay(), Carbon::instance($to)->endOfDay()])
            ->orderBy('start_time')->cursor();

        return (function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accents correctly
            fputcsv($out, ['ID', 'Tanggal', 'Mulai', 'Selesai', 'Durasi (menit)', 'Ruangan', 'Lokasi', 'Judul', 'Pemesan', 'Email', 'Departemen', 'Peserta', 'Status', 'Berulang']);
            foreach ($rows as $b) {
                fputcsv($out, [
                    $b->id, $b->start_time->toDateString(), $b->start_time->format('H:i'), $b->end_time->format('H:i'),
                    (int) $b->start_time->diffInMinutes($b->end_time), $b->room?->name, $b->room?->location, $b->title,
                    $b->creator?->name, $b->creator?->email, $b->creator?->department, $b->attendee_count, $b->statusLabel(), $b->recurring_booking_id ? 'ya' : 'tidak',
                ]);
            }
            fclose($out);
        });
    }
}

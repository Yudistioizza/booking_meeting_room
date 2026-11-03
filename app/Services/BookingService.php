<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\RecurringBooking;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * All booking rules live here so every entry point (web, API, import)
 * enforces the same ones: opening hours, duration, capacity, buffers,
 * double-booking, approval and recurrence.
 */
class BookingService
{
    /** Statuses that hold a room; pending counts so two people cannot race for one slot. */
    public const HOLDING = ['pending', 'approved'];

    public function __construct(private BookingNotifier $notifier, private CalendarSync $sync)
    {
    }

    /* ───────────────────────── availability ───────────────────────── */

    public function conflicts(Room $room, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): Collection
    {
        $buffer = (int) $room->buffer_minutes;

        return Booking::with('creator')
            ->where('room_id', $room->id)
            ->whereIn('status', self::HOLDING)
            ->where('start_time', '<', $end->copy()->addMinutes($buffer))
            ->where('end_time', '>', $start->copy()->subMinutes($buffer))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Conflicts for many rooms in a single query, keyed by room id.
     * Used by the search screen so 100 rooms do not mean 100 queries.
     *
     * @return Collection<int, Collection<int, Booking>>
     */
    public function busyMap(Collection $rooms, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $maxBuffer = (int) $rooms->max('buffer_minutes');
        $all = Booking::with('creator')->whereIn('room_id', $rooms->pluck('id'))->holding()
            ->overlapping($start->copy()->subMinutes($maxBuffer), $end->copy()->addMinutes($maxBuffer))
            ->orderBy('start_time')->get()->groupBy('room_id');

        return $rooms->mapWithKeys(fn (Room $r) => [$r->id => ($all[$r->id] ?? collect())->filter(
            fn (Booking $b) => $b->start_time->lt($end->copy()->addMinutes((int) $r->buffer_minutes)) && $b->end_time->gt($start->copy()->subMinutes((int) $r->buffer_minutes))
        )->values()]);
    }

    /** Rule violation as text (or null when the window is fine) without throwing. */
    public function windowError(Room $room, CarbonInterface $start, CarbonInterface $end, int $people = 1): ?string
    {
        try {
            $this->assertBookable($room, $start, $end, $people);
        } catch (BookingException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /** Bookable hour ranges of a room on a given day, as [[Carbon, Carbon], ...]. */
    public function openIntervals(Room $room, CarbonInterface $day): array
    {
        $rules = $room->relationLoaded('availabilityRules') ? $room->availabilityRules : $room->availabilityRules()->where('is_active', true)->get();
        $rules = $rules->where('is_active', true);
        $dow = $day->format('D');
        $ranges = [];

        if ($rules->isEmpty()) {
            if (in_array($dow, config('booking.hours.days'), true)) {
                $ranges[] = config('booking.hours.from') . '-' . config('booking.hours.to');
            }
        } else {
            foreach ($rules as $rule) {
                $days = $rule->available_days ? array_map('trim', explode(',', $rule->available_days)) : null;
                if ($days !== null && ! in_array($dow, $days, true)) {
                    continue;
                }
                foreach (array_filter(array_map('trim', explode(',', $rule->available_hours ?: config('booking.hours.from') . '-' . config('booking.hours.to')))) as $r) {
                    $ranges[] = $r;
                }
            }
        }

        $out = [];
        foreach ($ranges as $range) {
            [$from, $to] = array_map('trim', explode('-', $range) + [1 => '']);
            if (! preg_match('/^\d{1,2}:\d{2}$/', $from) || ! preg_match('/^\d{1,2}:\d{2}$/', $to)) {
                continue;
            }
            $out[] = [$day->copy()->setTimeFromTimeString($from), $day->copy()->setTimeFromTimeString($to)];
        }

        return $out;
    }

    public function withinOpeningHours(Room $room, CarbonInterface $start, CarbonInterface $end): bool
    {
        foreach ($this->openIntervals($room, $start) as [$from, $to]) {
            if ($start->gte($from) && $end->lte($to)) {
                return true;
            }
        }

        return false;
    }

    /** First free slots of $minutes on a day, aligned to the slot grid. Used for "try these times instead". */
    public function freeSlots(Room $room, CarbonInterface $day, int $minutes, int $limit = 4, ?CarbonInterface $after = null): array
    {
        $step = (int) config('booking.slot_minutes');
        $taken = Booking::where('room_id', $room->id)->whereIn('status', self::HOLDING)
            ->whereDate('start_time', $day->toDateString())->orderBy('start_time')->get();
        $buffer = (int) $room->buffer_minutes;
        $slots = [];

        foreach ($this->openIntervals($room, $day) as [$from, $to]) {
            $cursor = $from->copy();
            if ($after && $after->gt($cursor)) {
                $cursor = $after->copy();
                $cursor->minute(((int) ceil($cursor->minute / $step)) * $step % 60)->second(0);
                if ($cursor->minute === 0 && $after->minute > 45) {
                    $cursor->addHour();
                }
            }
            while ($cursor->copy()->addMinutes($minutes)->lte($to) && count($slots) < $limit) {
                $end = $cursor->copy()->addMinutes($minutes);
                $hit = $taken->first(fn ($b) => $b->start_time->lt($end->copy()->addMinutes($buffer)) && $b->end_time->gt($cursor->copy()->subMinutes($buffer)));
                if ($hit) {
                    $cursor = $hit->end_time->copy()->addMinutes($buffer);
                    $cursor->minute(((int) ceil($cursor->minute / $step)) * $step % 60)->second(0);
                    continue;
                }
                $slots[] = [$cursor->copy(), $end];
                $cursor->addMinutes($minutes);
            }
        }

        return $slots;
    }

    /* ───────────────────────── validation ───────────────────────── */

    public function assertBookable(Room $room, CarbonInterface $start, CarbonInterface $end, int $people = 1, int $maxAdvanceDays = 0): void
    {
        $fail = fn (string $m) => throw BookingException::because($m);
        $maxAdvanceDays = $maxAdvanceDays ?: (int) config('booking.max_advance_days');

        if ($room->is_active === false || $room->trashed()) {
            $fail("Ruang {$room->name} sedang tidak dapat dipesan.");
        }
        if ($end->lte($start)) {
            $fail('Jam selesai harus setelah jam mulai.');
        }
        if (! $start->isSameDay($end)) {
            $fail('Booking tidak boleh melewati tengah malam. Pisahkan menjadi beberapa hari.');
        }
        $minutes = (int) $start->diffInMinutes($end);
        if ($minutes < config('booking.min_duration_minutes')) {
            $fail('Durasi minimal ' . config('booking.min_duration_minutes') . ' menit.');
        }
        $max = $room->max_duration_minutes ?: config('booking.max_duration_minutes');
        if ($minutes > $max) {
            $fail('Durasi maksimal untuk ruang ini ' . floor($max / 60) . ' jam' . ($max % 60 ? ' ' . $max % 60 . ' menit' : '') . '.');
        }
        if ($start->lt(now()->subMinutes(5))) {
            $fail('Waktu mulai sudah lewat.');
        }
        if ($start->gt(now()->addDays($maxAdvanceDays))) {
            $fail("Booking hanya bisa dibuat maksimal {$maxAdvanceDays} hari ke depan.");
        }
        if ($people > $room->capacity && $room->capacity > 0) {
            $fail("Ruang {$room->name} hanya muat {$room->capacity} orang.");
        }
        if (! $this->withinOpeningHours($room, $start, $end)) {
            $fail("Di luar jam operasional ruang {$room->name} pada {$start->translatedFormat('l')}.");
        }
    }

    private function conflictMessage(Collection $conflicts, Room $room): string
    {
        $c = $conflicts->first();

        return "{$room->name} sudah dipakai {$c->start_time->format('H:i')}–{$c->end_time->format('H:i')} ({$c->title}" . ($c->creator ? ', ' . $c->creator->name : '') . ').';
    }

    /* ───────────────────────── create ───────────────────────── */

    public function book(User $user, Room $room, array $data): Booking
    {
        $booking = $this->persist($user, $room, $data, null);
        $this->notifier->created($booking);

        return $booking;
    }

    /**
     * Recurring booking. Occurrences that clash are skipped when $skipConflicts
     * is true, otherwise the whole series is refused.
     *
     * @return array{bookings: Collection, skipped: array<int, array{date: string, reason: string}>}
     */
    public function bookSeries(User $user, Room $room, array $data, array $rule, bool $skipConflicts = true): array
    {
        $start = Carbon::parse($data['start']);
        $end = Carbon::parse($data['end']);
        $occurrences = $this->occurrences($start, $end, $rule);

        $recurring = null;
        $created = collect();
        $skipped = [];

        DB::transaction(function () use (&$recurring, &$created, &$skipped, $user, $room, $data, $rule, $occurrences, $skipConflicts) {
            foreach ($occurrences as $i => [$s, $e]) {
                try {
                    $recurring ??= RecurringBooking::create([
                        'frequency' => $rule['frequency'], 'interval' => $rule['interval'] ?? 1,
                        'days_of_week' => isset($rule['days']) ? implode(',', $rule['days']) : null,
                        'end_date' => $rule['until'] ?? $occurrences[array_key_last($occurrences)][0]->toDateString(),
                        'created_by' => $user->id,
                    ]);
                    $created->push($this->persist($user, $room, array_merge($data, ['start' => $s, 'end' => $e]), $recurring->id, 366));
                } catch (BookingException $ex) {
                    if (! $skipConflicts) {
                        throw BookingException::because($s->translatedFormat('d M Y') . ': ' . $ex->getMessage(), $ex->conflicts);
                    }
                    $skipped[] = ['date' => $s->translatedFormat('D, d M Y'), 'reason' => $ex->getMessage()];
                }
            }
        });

        if ($created->isEmpty()) {
            throw BookingException::because('Tidak ada tanggal yang bisa dipesan. ' . ($skipped[0]['reason'] ?? ''));
        }
        $this->notifier->created($created->first(), $created);

        return ['bookings' => $created, 'skipped' => $skipped];
    }

    /** Expand a rule into [start, end] pairs, first occurrence included. */
    public function occurrences(CarbonInterface $start, CarbonInterface $end, array $rule): array
    {
        $freq = $rule['frequency'];
        $interval = max(1, (int) ($rule['interval'] ?? 1));
        $limit = (int) config('booking.max_recurrence');
        $until = isset($rule['until']) ? Carbon::parse($rule['until'])->endOfDay() : null;
        $count = isset($rule['count']) ? min((int) $rule['count'], $limit) : $limit;
        if (! $until && ! isset($rule['count'])) {
            throw BookingException::because('Tentukan batas pengulangan: sampai tanggal atau jumlah pertemuan.');
        }

        $dur = (int) $start->diffInMinutes($end);
        $out = [];
        $add = function (Carbon $s) use (&$out, $dur) {
            $out[] = [$s->copy(), $s->copy()->addMinutes($dur)];
        };
        $first = Carbon::instance($start);

        if ($freq === 'monthly') {
            for ($i = 0; count($out) < $count && $i < 200; $i++) {
                $s = $first->copy()->addMonthsNoOverflow($i * $interval);
                if ($until && $s->gt($until)) {
                    break;
                }
                $add($s);
            }

            return $out;
        }

        $days = ! empty($rule['days']) ? $rule['days'] : [$first->format('D')];
        $weekZero = $first->copy()->startOfWeek();
        $cursor = $first->copy();

        for ($guard = 0; count($out) < $count && $guard < 3000; $guard++) {
            if ($until && $cursor->gt($until)) {
                break;
            }
            if ($freq === 'weekly') {
                $weekIdx = intdiv((int) abs($cursor->copy()->startOfWeek()->diffInDays($weekZero)), 7);
                if ($weekIdx % $interval === 0 && in_array($cursor->format('D'), $days, true)) {
                    $add($cursor);
                }
                $cursor->addDay();
            } else {
                if (empty($rule['skip_weekends']) || ! $cursor->isWeekend()) {
                    $add($cursor);
                }
                $cursor->addDays($interval);
            }
        }

        return $out;
    }

    private function persist(User $user, Room $room, array $data, ?int $recurringId, int $maxAdvance = 0): Booking
    {
        $start = Carbon::parse($data['start']);
        $end = Carbon::parse($data['end']);
        $emails = $this->emails($data['attendees'] ?? [], $user);
        $people = max((int) ($data['attendee_count'] ?? 1), count($emails) + 1);

        return DB::transaction(function () use ($user, $room, $data, $recurringId, $start, $end, $emails, $people, $maxAdvance) {
            // Serialise bookings per room so the conflict check cannot race.
            Room::whereKey($room->id)->lockForUpdate()->first();

            $this->assertBookable($room, $start, $end, $people, $maxAdvance);
            $conflicts = $this->conflicts($room, $start, $end);
            if ($conflicts->isNotEmpty()) {
                throw BookingException::because($this->conflictMessage($conflicts, $room), $conflicts);
            }

            $status = $room->requires_approval && ! $user->isApprover() ? 'pending' : 'approved';

            $booking = Booking::create([
                'room_id' => $room->id,
                'created_by' => $user->id,
                'approved_by' => $status === 'approved' && $room->requires_approval ? $user->id : null,
                'recurring_booking_id' => $recurringId,
                'title' => trim($data['title']),
                'description' => $data['description'] ?? null,
                'start_time' => $start,
                'end_time' => $end,
                'status' => $status,
                'attendees' => $emails ?: null,
                'attendee_count' => $people,
                'ics_uid' => (string) Str::uuid() . '@' . parse_url(config('app.url'), PHP_URL_HOST),
            ]);

            AuditLog::record('booking.created', $booking, "{$user->name} memesan {$room->name} ({$start->format('d M H:i')}–{$end->format('H:i')}) · $status", [], $user);

            return $booking;
        });
    }

    /** @return array<int,string> lowercase, valid, unique, without the organizer */
    public function emails(array|string $raw, ?User $organizer = null): array
    {
        $list = is_array($raw) ? $raw : preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $clean = collect($list)->map(fn ($e) => strtolower(trim($e)))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->reject(fn ($e) => $organizer && $e === strtolower($organizer->email))
            ->unique()->values();

        if ($clean->count() > config('booking.max_attendees_emails')) {
            throw BookingException::because('Maksimal ' . config('booking.max_attendees_emails') . ' undangan per booking.');
        }

        return $clean->all();
    }

    /* ───────────────────────── approval ───────────────────────── */

    public function approve(Booking $b, User $by, ?string $note = null): Booking
    {
        $this->assertApprover($by, $b);
        $this->assertPending($b);
        if ($b->start_time->lt(now())) {
            throw BookingException::because('Booking ini sudah lewat waktunya. Tolak saja.');
        }

        DB::transaction(function () use ($b, $by, $note) {
            $b->update(['status' => 'approved', 'approved_by' => $by->id, 'updated_by' => $by->id]);
            Approval::create(['booking_id' => $b->id, 'approver_id' => $by->id, 'status' => 'approved', 'note' => $note]);
            AuditLog::record('booking.approved', $b, "{$by->name} menyetujui \"{$b->title}\"", ['note' => $note], $by);
        });
        $this->notifier->approved($b->fresh(['room', 'creator']), $note);

        return $b->fresh();
    }

    public function reject(Booking $b, User $by, ?string $note = null): Booking
    {
        $this->assertApprover($by, $b);
        $this->assertPending($b);

        DB::transaction(function () use ($b, $by, $note) {
            $b->update(['status' => 'rejected', 'approved_by' => $by->id, 'updated_by' => $by->id, 'cancel_reason' => $note]);
            Approval::create(['booking_id' => $b->id, 'approver_id' => $by->id, 'status' => 'rejected', 'note' => $note]);
            AuditLog::record('booking.rejected', $b, "{$by->name} menolak \"{$b->title}\"", ['note' => $note], $by);
        });
        $this->notifier->rejected($b->fresh(['room', 'creator']), $note);

        return $b->fresh();
    }

    private function assertApprover(User $by, Booking $b): void
    {
        if (! $by->isApprover()) {
            throw BookingException::because('Anda tidak berwenang menyetujui booking.');
        }
        if ($b->created_by === $by->id) {
            throw BookingException::because('Anda tidak dapat menyetujui booking Anda sendiri.');
        }
    }

    private function assertPending(Booking $b): void
    {
        if ($b->status !== 'pending') {
            throw BookingException::because('Booking ini sudah diproses.');
        }
    }

    /* ───────────────────────── cancel / reschedule ───────────────────────── */

    public function canManage(Booking $b, User $user): bool
    {
        return $b->created_by === $user->id || $user->isAdmin();
    }

    /** @param bool $following also cancel later occurrences of the same recurring series */
    public function cancel(Booking $b, User $by, ?string $reason = null, bool $following = false): int
    {
        if (! $this->canManage($b, $by)) {
            throw BookingException::because('Hanya pemesan atau admin yang dapat membatalkan.');
        }

        $targets = collect([$b]);
        if ($following && $b->recurring_booking_id) {
            $targets = Booking::where('recurring_booking_id', $b->recurring_booking_id)
                ->whereIn('status', self::HOLDING)->where('start_time', '>=', $b->start_time)->get();
        }

        $done = 0;
        foreach ($targets as $t) {
            if (! in_array($t->status, self::HOLDING, true)) {
                continue;
            }
            if ($t->start_time->lt(now()->subMinutes((int) config('booking.cancel_cutoff_minutes')))) {
                if ($targets->count() === 1) {
                    throw BookingException::because('Booking yang sudah berjalan tidak dapat dibatalkan.');
                }
                continue;
            }
            $t->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason, 'updated_by' => $by->id, 'ics_sequence' => $t->ics_sequence + 1]);
            AuditLog::record('booking.cancelled', $t, "{$by->name} membatalkan \"{$t->title}\"", ['reason' => $reason], $by);
            $done++;
        }

        if ($done > 0) {
            $first = $targets->first(fn ($t) => $t->status === 'cancelled');
            if ($done === 1) {
                $this->notifier->cancelled($first->fresh(['room', 'creator']), $reason);
            } else {
                foreach ($targets as $t) {
                    $this->sync->remove($t);
                }
                $this->notifier->cancelled($first->fresh(['room', 'creator']), ($reason ? $reason . ' · ' : '') . "{$done} pertemuan dibatalkan.");
            }
        }

        return $done;
    }

    public function reschedule(Booking $b, User $by, CarbonInterface $start, CarbonInterface $end, ?Room $room = null): Booking
    {
        if (! $this->canManage($b, $by)) {
            throw BookingException::because('Hanya pemesan atau admin yang dapat mengubah jadwal.');
        }
        if (! in_array($b->status, self::HOLDING, true) || $b->start_time->lt(now())) {
            throw BookingException::because('Booking ini tidak dapat diubah lagi.');
        }
        $room ??= $b->room;

        DB::transaction(function () use ($b, $by, $start, $end, $room) {
            Room::whereKey($room->id)->lockForUpdate()->first();
            $this->assertBookable($room, $start, $end, $b->attendee_count);
            $conflicts = $this->conflicts($room, $start, $end, $b->id);
            if ($conflicts->isNotEmpty()) {
                throw BookingException::because($this->conflictMessage($conflicts, $room), $conflicts);
            }

            $needsApproval = $room->requires_approval && ! $by->isApprover();
            $old = $b->start_time->format('d M H:i');
            $b->update([
                'room_id' => $room->id, 'start_time' => $start, 'end_time' => $end,
                'status' => $needsApproval ? 'pending' : 'approved',
                'updated_by' => $by->id, 'ics_sequence' => $b->ics_sequence + 1,
            ]);
            AuditLog::record('booking.updated', $b, "{$by->name} memindah \"{$b->title}\" dari $old ke {$start->format('d M H:i')}", [], $by);
        });

        $this->notifier->updated($b->fresh(['room', 'creator']));

        return $b->fresh();
    }
}

<?php

namespace App\Livewire\Booking;

use App\Exceptions\BookingException;
use App\Models\Room;
use App\Services\BookingService;
use App\Support\Times;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The one booking dialog, opened from anywhere with
 * Livewire.dispatch('book-room', { room, date, start, end }).
 * Shows availability live while the user picks a time.
 */
class Form extends Component
{
    public bool $open = false;

    public ?int $room_id = null;
    public string $date = '';
    public string $start = '09:00';
    public string $end = '10:00';
    public string $title = '';
    public string $description = '';
    public string $attendees = '';
    public int $people = 2;

    public string $repeat = 'none';          // none | daily | weekly | monthly
    public int $repeat_interval = 1;
    public array $repeat_days = [];
    public string $repeat_until = '';
    public bool $skip_conflicts = true;

    public ?string $error = null;
    public ?array $result = null;

    #[On('book-room')]
    public function show(?int $room = null, ?string $date = null, ?string $start = null, ?string $end = null): void
    {
        $this->reset(['title', 'description', 'attendees', 'repeat', 'repeat_days', 'error', 'result']);
        $this->repeat_interval = 1;
        $this->skip_conflicts = true;
        $this->people = 2;
        $this->room_id = $room ?: $this->room_id;
        $this->date = $date ?: now()->toDateString();

        if ($start) {
            $this->start = $start;
            $this->end = $end ?: Times::format(Times::minutes($start) + 60);
        } else {
            $next = now()->addMinutes(15 - now()->minute % 15)->second(0);
            if ($next->isWeekend() || $next->hour >= 19 || $next->hour < 7) {
                $this->date = ($next->hour >= 19 || $next->isWeekend() ? $next->copy()->addDay() : $next)->toDateString();
                $next = Carbon::parse($this->date)->setTime(9, 0);
                while ($next->isWeekend()) {
                    $next->addDay();
                    $this->date = $next->toDateString();
                }
            }
            $this->start = $next->format('H:i');
            $this->end = Times::format(Times::minutes($this->start) + 60);
        }
        $this->repeat_until = Carbon::parse($this->date)->addWeeks(8)->toDateString();
        $this->clampPeople();
        $this->open = true;
    }

    public function updatedRoomId(): void
    {
        $this->clampPeople();
    }

    /** Never start with more people than the chosen room holds. */
    private function clampPeople(): void
    {
        $capacity = $this->room_id ? (int) Room::whereKey($this->room_id)->value('capacity') : 0;
        if ($capacity > 0 && $this->people > $capacity) {
            $this->people = $capacity;
        }
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetValidation();
    }

    public function updatedStart(): void
    {
        $this->end = Times::format(min(Times::minutes($this->start) + $this->durationMinutes(), 23 * 60 + 45));
    }

    public function setDuration(int $minutes): void
    {
        $this->end = Times::format(min(Times::minutes($this->start) + $minutes, 23 * 60 + 45));
    }

    public function pick(string $start, string $end): void
    {
        $this->start = $start;
        $this->end = $end;
    }

    private function durationMinutes(): int
    {
        $d = Times::minutes($this->end) - Times::minutes($this->start);

        return $d > 0 ? $d : 60;
    }

    public function updatedRepeat(): void
    {
        if ($this->repeat === 'weekly' && ! $this->repeat_days && $this->date) {
            $this->repeat_days = [Carbon::parse($this->date)->format('D')];
        }
    }

    /* ───────────────────────── live availability ───────────────────────── */

    #[Computed]
    public function room(): ?Room
    {
        return $this->room_id ? Room::with('availabilityRules')->find($this->room_id) : null;
    }

    #[Computed]
    public function rooms()
    {
        return Room::where('is_active', true)->orderBy('location')->orderBy('name')->get();
    }

    private function window(): ?array
    {
        try {
            $s = Carbon::parse("{$this->date} {$this->start}");
            $e = Carbon::parse("{$this->date} {$this->end}");
        } catch (\Throwable) {
            return null;
        }

        return [$s, $e];
    }

    /** @return array{ok: bool, message: ?string, conflicts: \Illuminate\Support\Collection, slots: array} */
    #[Computed]
    public function availability(): array
    {
        $none = ['ok' => false, 'message' => null, 'conflicts' => collect(), 'slots' => []];
        $room = $this->room;
        $w = $this->window();
        if (! $room || ! $w) {
            return $none;
        }
        [$s, $e] = $w;
        $svc = app(BookingService::class);
        $emails = $this->emailList();
        $people = max($this->people, count($emails) + 1);

        $conflicts = $svc->conflicts($room, $s, $e);
        $message = $svc->windowError($room, $s, $e, $people);
        if (! $message && $conflicts->isNotEmpty()) {
            $c = $conflicts->first();
            $message = "Sudah dipakai {$c->start_time->format('H:i')}–{$c->end_time->format('H:i')}: {$c->title}.";
        }
        $slots = [];
        if ($message && $e->gt($s)) {
            $slots = $svc->freeSlots($room, $s, (int) $s->diffInMinutes($e), 4, $s->isToday() ? now() : null);
        }

        return ['ok' => ! $message, 'message' => $message, 'conflicts' => $conflicts, 'slots' => $slots];
    }

    private function emailList(): array
    {
        try {
            return app(BookingService::class)->emails($this->attendees, auth()->user());
        } catch (BookingException) {
            return [];
        }
    }

    /* ───────────────────────── save ───────────────────────── */

    public function save(): void
    {
        $this->error = null;
        $this->validate([
            'room_id' => 'required|exists:rooms,id',
            'title' => 'required|string|min:3|max:150',
            'date' => 'required|date',
            'start' => 'required|date_format:H:i',
            'end' => 'required|date_format:H:i',
            'description' => 'nullable|string|max:2000',
            'people' => 'required|integer|min:1|max:500',
            'repeat' => 'in:none,daily,weekly,monthly',
            'repeat_until' => 'required_unless:repeat,none|nullable|date|after:date',
        ], [
            'room_id.required' => 'Pilih ruangan.',
            'title.required' => 'Isi judul pertemuan.',
            'title.min' => 'Judul minimal 3 karakter.',
            'repeat_until.after' => 'Batas pengulangan harus setelah tanggal pertama.',
        ]);

        $user = auth()->user();
        $room = Room::findOrFail($this->room_id);
        $svc = app(BookingService::class);
        $data = [
            'title' => $this->title, 'description' => $this->description ?: null,
            'start' => Carbon::parse("{$this->date} {$this->start}"), 'end' => Carbon::parse("{$this->date} {$this->end}"),
            'attendees' => $this->attendees, 'attendee_count' => $this->people,
        ];

        try {
            if ($this->repeat === 'none') {
                $booking = $svc->book($user, $room, $data);
                $this->result = ['count' => 1, 'skipped' => [], 'status' => $booking->status, 'booking' => $booking->id, 'room' => $room->name, 'when' => $booking->start_time->translatedFormat('l, d F · H:i') . '–' . $booking->end_time->format('H:i')];
            } else {
                $res = $svc->bookSeries($user, $room, $data, [
                    'frequency' => $this->repeat, 'interval' => max(1, $this->repeat_interval),
                    'days' => $this->repeat === 'weekly' ? $this->repeat_days : null,
                    'until' => $this->repeat_until, 'skip_weekends' => $this->repeat === 'daily',
                ], $this->skip_conflicts);
                $first = $res['bookings']->first();
                $this->result = ['count' => $res['bookings']->count(), 'skipped' => $res['skipped'], 'status' => $first->status, 'booking' => $first->id, 'room' => $room->name, 'when' => $first->start_time->translatedFormat('l, d F · H:i') . '–' . $first->end_time->format('H:i')];
            }
        } catch (BookingException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->dispatch('booking-changed');
    }

    public function render()
    {
        return view('livewire.booking.form', ['times' => Times::options('06:00', '21:00')]);
    }
}

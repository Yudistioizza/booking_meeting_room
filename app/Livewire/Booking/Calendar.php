<?php

namespace App\Livewire\Booking;

use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Day scheduler: one row per room, one column per hour. */
#[Layout('components.layouts.app')]
#[Title('Kalender ruang')]
class Calendar extends Component
{
    public const FROM = 7;   // first visible hour
    public const TO = 20;    // last visible hour

    #[Url(as: 'tanggal')]
    public string $date = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'lokasi')]
    public string $location = '';

    #[Url(as: 'saya')]
    public bool $onlyMine = false;

    public function mount(): void
    {
        $this->date = $this->date ?: now()->toDateString();
    }

    #[On('booking-changed')]
    public function refresh(): void
    {
    }

    public function shift(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
    }

    public function today(): void
    {
        $this->date = now()->toDateString();
    }

    public function render(BookingService $svc)
    {
        try {
            $day = Carbon::parse($this->date)->startOfDay();
        } catch (\Throwable) {
            $day = now()->startOfDay();
            $this->date = $day->toDateString();
        }
        $user = auth()->user();

        $rooms = Room::with('availabilityRules')->where('is_active', true)
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->location, fn ($q) => $q->where('location', 'like', $this->location . '%'))
            ->orderBy('location')->orderBy('name')->get();

        $bookings = Booking::with('creator')->holding()->whereIn('room_id', $rooms->pluck('id'))
            ->whereBetween('start_time', [$day, $day->copy()->endOfDay()])
            ->when($this->onlyMine, fn ($q) => $q->involving($user))->orderBy('start_time')->get()->groupBy('room_id');

        $total = (self::TO - self::FROM) * 60;
        $pos = fn (Carbon $t) => max(0, min(100, (($t->hour * 60 + $t->minute) - self::FROM * 60) / $total * 100));

        $rows = $rooms->map(function (Room $room) use ($bookings, $day, $svc, $user, $pos) {
            $open = $svc->openIntervals($room, $day);
            $closed = [];
            $cursor = $day->copy()->setTime(self::FROM, 0);
            foreach (collect($open)->sortBy(fn ($r) => $r[0]->timestamp) as [$from, $to]) {
                if ($from->gt($cursor)) {
                    $closed[] = [$pos($cursor), $pos($from)];
                }
                $cursor = $to->gt($cursor) ? $to->copy() : $cursor;
            }
            if ($cursor->lt($day->copy()->setTime(self::TO, 0))) {
                $closed[] = [$pos($cursor), 100];
            }

            return (object) [
                'room' => $room,
                'closed' => array_map(fn ($c) => ['left' => $c[0], 'width' => $c[1] - $c[0]], $closed),
                'blocks' => ($bookings[$room->id] ?? collect())->map(fn (Booking $b) => (object) [
                    'b' => $b, 'left' => $pos($b->start_time), 'width' => max(1.5, $pos($b->end_time) - $pos($b->start_time)),
                    'tone' => $b->status === 'pending' ? 'tl-wait' : ($b->created_by === $user->id ? 'tl-mine' : 'tl-ok'),
                ]),
            ];
        });

        return view('livewire.booking.calendar', [
            'rows' => $rows,
            'dayCarbon' => $day,
            'locations' => Room::where('is_active', true)->whereNotNull('location')->pluck('location')->map(fn ($l) => trim(explode('·', $l)[0]))->unique()->values(),
            'hours' => range(self::FROM, self::TO - 1),
            'total' => $total,
            'nowPos' => $day->isToday() && now()->hour >= self::FROM && now()->hour < self::TO ? $pos(now()) : null,
        ]);
    }
}

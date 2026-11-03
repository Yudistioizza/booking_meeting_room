<?php

namespace App\Livewire\Booking;

use App\Models\Room;
use App\Services\BookingService;
use App\Support\Times;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Find a room for a given time, size and equipment, ranked by best fit. */
#[Layout('components.layouts.app')]
#[Title('Cari & pesan ruang')]
class Find extends Component
{
    public const EQUIPMENT = ['Proyektor', 'Video conference', 'Whiteboard', 'TV', 'Sound system'];

    #[Url(as: 'tanggal')]
    public string $date = '';
    #[Url(as: 'mulai')]
    public string $start = '';
    #[Url(as: 'selesai')]
    public string $end = '';
    #[Url(as: 'orang')]
    public int $people = 4;
    #[Url(as: 'fasilitas')]
    public array $equipment = [];

    public function mount(): void
    {
        $this->date = $this->date ?: now()->toDateString();
        if (! $this->start) {
            $n = now()->addMinutes(15 - now()->minute % 15);
            $ok = $n->hour >= 7 && $n->hour < 19 && $n->isWeekday() && $this->date === now()->toDateString();
            $this->start = $ok ? $n->format('H:i') : '09:00';
            $this->end = Times::format(Times::minutes($this->start) + 60);
        }
        $this->end = $this->end ?: Times::format(Times::minutes($this->start) + 60);
    }

    public function updatedStart(): void
    {
        $d = max(30, Times::minutes($this->end) - Times::minutes($this->start));
        $this->end = Times::format(min(Times::minutes($this->start) + $d, 23 * 60 + 45));
    }

    public function setDuration(int $m): void
    {
        $this->end = Times::format(min(Times::minutes($this->start) + $m, 23 * 60 + 45));
    }

    public function useSlot(string $start, string $end): void
    {
        $this->start = $start;
        $this->end = $end;
    }

    #[On('booking-changed')]
    public function refresh(): void
    {
    }

    public function render(BookingService $svc)
    {
        $results = collect();
        $error = null;
        try {
            $s = Carbon::parse("{$this->date} {$this->start}");
            $e = Carbon::parse("{$this->date} {$this->end}");
            if ($e->lte($s)) {
                $error = 'Jam selesai harus setelah jam mulai.';
            }
        } catch (\Throwable) {
            $error = 'Tanggal atau jam tidak valid.';
        }

        $all = Room::with('availabilityRules')->where('is_active', true)->get();
        if (! $error) {
            $people = max(1, $this->people);
            $rooms = $all->filter(fn (Room $r) => $r->capacity >= $people)
                ->filter(fn (Room $r) => collect($this->equipment)->every(fn ($eq) => stripos((string) $r->equipment, $eq) !== false))->values();
            $busy = $svc->busyMap($rooms, $s, $e);
            $minutes = (int) $s->diffInMinutes($e);

            $results = $rooms->map(function (Room $r) use ($svc, $busy, $s, $e, $people, $minutes) {
                $rule = $svc->windowError($r, $s, $e, $people);
                $conflicts = $busy[$r->id];
                $ok = ! $rule && $conflicts->isEmpty();
                $reason = $rule ?? ($conflicts->isNotEmpty() ? "Dipakai {$conflicts->first()->start_time->format('H:i')}–{$conflicts->first()->end_time->format('H:i')} ({$conflicts->first()->title})" : null);

                return (object) [
                    'room' => $r, 'ok' => $ok, 'reason' => $reason,
                    'slots' => $ok ? [] : $svc->freeSlots($r, $s, $minutes, 3, $s->isToday() ? now() : null),
                    'fit' => $r->capacity - $people,
                ];
            })->sortBy(fn ($x) => [$x->ok ? 0 : 1, $x->fit])->values();
        }

        return view('livewire.booking.find', [
            'results' => $results,
            'error' => $error,
            'times' => Times::options('06:00', '21:00'),
            'availableCount' => $results->where('ok', true)->count(),
            'hidden' => $all->count() - $results->count(),
        ]);
    }
}

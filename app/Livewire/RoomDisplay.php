<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Read-only door tablet. Reached through a signed URL, no login. */
#[Layout('components.layouts.display')]
class RoomDisplay extends Component
{
    public Room $room;

    public function mount(Room $room): void
    {
        abort_if($room->trashed() || $room->is_active === false, 404);
        $this->room = $room;
    }

    public function render(BookingService $svc)
    {
        $now = now();
        $today = Booking::with('creator')->where('room_id', $this->room->id)->where('status', 'approved')
            ->whereDate('start_time', $now->toDateString())->orderBy('start_time')->get();

        $current = $today->first(fn ($b) => $b->start_time->lte($now) && $b->end_time->gt($now));
        $upcoming = $today->filter(fn ($b) => $b->start_time->gt($now))->values();
        $open = collect($svc->openIntervals($this->room->loadMissing('availabilityRules'), $now))->first(fn ($r) => $now->gte($r[0]) && $now->lt($r[1]));

        return view('livewire.room-display', [
            'current' => $current,
            'upcoming' => $upcoming,
            'state' => $current ? 'busy' : ($open ? 'free' : 'closed'),
            'freeUntil' => ! $current && $open ? ($upcoming->first()?->start_time ?? $open[1]) : null,
        ]);
    }
}

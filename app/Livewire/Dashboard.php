<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dasbor')]
class Dashboard extends Component
{
    #[On('booking-changed')]
    public function refresh(): void
    {
    }

    public function render(BookingService $svc)
    {
        $user = auth()->user();
        $now = now();

        $rooms = Room::with('availabilityRules')->where('is_active', true)->orderBy('name')->get();
        $today = Booking::with('creator')->whereIn('status', ['approved', 'pending'])->whereDate('start_time', $now->toDateString())
            ->whereIn('room_id', $rooms->pluck('id'))->orderBy('start_time')->get()->groupBy('room_id');

        $board = $rooms->map(function (Room $room) use ($today, $now, $svc) {
            $list = $today[$room->id] ?? collect();
            $current = $list->first(fn ($b) => $b->start_time->lte($now) && $b->end_time->gt($now));
            $next = $list->first(fn ($b) => $b->start_time->gt($now));
            $open = collect($svc->openIntervals($room, $now))->first(fn ($r) => $now->gte($r[0]) && $now->lt($r[1]));
            $state = $current ? 'busy' : ($open ? 'free' : 'closed');

            return (object) [
                'room' => $room, 'state' => $state, 'current' => $current, 'next' => $next,
                'freeUntil' => $state === 'free' ? ($next?->start_time ?? $open[1]) : null,
                'count' => $list->count(),
            ];
        })->sortBy(fn ($r) => ['free' => 0, 'busy' => 1, 'closed' => 2][$r->state] . str_pad((string) $r->room->capacity, 4, '0', STR_PAD_LEFT))->values();

        $mine = Booking::with('room', 'creator')->involving($user)->whereIn('status', ['approved', 'pending'])
            ->where('end_time', '>', $now)->orderBy('start_time')->limit(6)->get();

        $weekMinutes = Booking::where('created_by', $user->id)->where('status', 'approved')
            ->whereBetween('start_time', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->get(['start_time', 'end_time'])
            ->sum(fn ($b) => $b->start_time->diffInMinutes($b->end_time));

        return view('livewire.dashboard', [
            'board' => $board,
            'mine' => $mine,
            'mineToday' => $mine->filter(fn ($b) => $b->start_time->isToday())->count(),
            'freeNow' => $board->where('state', 'free')->count(),
            'weekHours' => round($weekMinutes / 60, 1),
            'myPending' => Booking::where('created_by', $user->id)->where('status', 'pending')->where('end_time', '>', $now)->count(),
            'toApprove' => $user->isApprover() ? Booking::with('room', 'creator')->where('status', 'pending')->where('created_by', '!=', $user->id)->where('end_time', '>', $now)->orderBy('start_time')->limit(4)->get() : collect(),
            'toApproveCount' => $user->isApprover() ? Booking::where('status', 'pending')->where('created_by', '!=', $user->id)->where('end_time', '>', $now)->count() : 0,
        ]);
    }
}

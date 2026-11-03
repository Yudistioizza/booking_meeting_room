<?php

namespace App\Livewire\Booking;

use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Booking saya')]
class Mine extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $tab = 'upcoming';   // upcoming | pending | invited | history

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    #[On('booking-changed')]
    public function refresh(): void
    {
    }

    private function query()
    {
        $user = auth()->user();
        $q = Booking::with('room', 'creator');

        return match ($this->tab) {
            'pending' => $q->where('created_by', $user->id)->where('status', 'pending')->where('end_time', '>', now())->orderBy('start_time'),
            'invited' => $q->whereJsonContains('attendees', strtolower($user->email))->whereIn('status', ['approved'])->where('end_time', '>', now())->orderBy('start_time'),
            'history' => $q->involving($user)->where(fn ($w) => $w->where('end_time', '<=', now())->orWhereIn('status', ['cancelled', 'rejected']))->orderByDesc('start_time'),
            default => $q->where('created_by', $user->id)->whereIn('status', ['approved', 'pending'])->where('end_time', '>', now())->orderBy('start_time'),
        };
    }

    public function render()
    {
        $user = auth()->user();
        $counts = [
            'upcoming' => Booking::where('created_by', $user->id)->whereIn('status', ['approved', 'pending'])->where('end_time', '>', now())->count(),
            'pending' => Booking::where('created_by', $user->id)->where('status', 'pending')->where('end_time', '>', now())->count(),
            'invited' => Booking::whereJsonContains('attendees', strtolower($user->email))->where('status', 'approved')->where('end_time', '>', now())->count(),
        ];

        return view('livewire.booking.mine', ['bookings' => $this->query()->paginate(12), 'counts' => $counts]);
    }
}

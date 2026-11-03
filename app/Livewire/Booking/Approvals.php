<?php

namespace App\Livewire\Booking;

use App\Models\Approval;
use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Persetujuan')]
class Approvals extends Component
{
    use WithPagination;

    public string $tab = 'pending';

    #[On('booking-changed')]
    public function refresh(): void
    {
    }

    public function render()
    {
        $user = auth()->user();
        $pending = Booking::with('room', 'creator')->where('status', 'pending')->where('created_by', '!=', $user->id)
            ->where('end_time', '>', now())->orderBy('start_time');

        return view('livewire.booking.approvals', [
            'pending' => (clone $pending)->get(),
            'history' => $this->tab === 'history'
                ? Approval::with('booking.room', 'booking.creator', 'approver')->latest()->paginate(12)
                : null,
        ]);
    }
}

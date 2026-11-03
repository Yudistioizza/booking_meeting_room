<?php

namespace App\Livewire;

use App\Models\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function read(int $id): void
    {
        $n = Notification::where('user_id', auth()->id())->findOrFail($id);
        $n->update(['is_read' => true]);
        $this->open = false;
        if ($n->booking_id) {
            $this->dispatch('show-booking', id: $n->booking_id);
        }
    }

    public function readAll(): void
    {
        Notification::where('user_id', auth()->id())->where('is_read', false)->update(['is_read' => true]);
    }

    #[On('booking-changed')]
    public function refresh(): void
    {
    }

    public function render()
    {
        $q = Notification::where('user_id', auth()->id());

        return view('livewire.notification-bell', [
            'unread' => (clone $q)->where('is_read', false)->count(),
            'items' => $q->latest('id')->limit(8)->get(),
        ]);
    }
}

<?php

namespace App\Livewire\Booking;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use App\Support\Times;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

/** One dialog for everything you can do with an existing booking. */
class Detail extends Component
{
    public ?int $bookingId = null;
    public string $mode = 'view';        // view | cancel | move | decide
    public ?string $note = null;
    public bool $following = false;
    public string $decision = 'approve';

    public string $date = '';
    public string $start = '';
    public string $end = '';
    public ?int $room_id = null;

    public ?string $error = null;

    #[On('show-booking')]
    public function show(int $id): void
    {
        $this->reset(['mode', 'note', 'following', 'error']);
        $this->bookingId = $id;
        $b = Booking::findOrFail($id);
        $this->date = $b->start_time->toDateString();
        $this->start = $b->start_time->format('H:i');
        $this->end = $b->end_time->format('H:i');
        $this->room_id = $b->room_id;
    }

    public function close(): void
    {
        $this->reset(['bookingId', 'mode', 'error', 'note']);
    }

    public function setMode(string $mode, string $decision = 'approve'): void
    {
        $this->mode = $mode;
        $this->decision = $decision;
        $this->error = null;
    }

    public function updatedStart(): void
    {
        $d = max(15, Times::minutes($this->end) - Times::minutes($this->start));
        $this->end = Times::format(min(Times::minutes($this->start) + max($d, 15), 23 * 60 + 45));
    }

    private function booking(): Booking
    {
        return Booking::with(['room', 'creator', 'approver', 'approvals.approver'])->findOrFail($this->bookingId);
    }

    public function cancel(): void
    {
        $this->run(function (BookingService $svc, Booking $b) {
            $n = $svc->cancel($b, auth()->user(), $this->note, $this->following);
            $this->dispatch('toast', message: $n > 1 ? "$n pertemuan dibatalkan." : 'Booking dibatalkan. Peserta sudah diberi tahu.');
        });
    }

    public function move(): void
    {
        $this->validate(['date' => 'required|date', 'start' => 'required|date_format:H:i', 'end' => 'required|date_format:H:i']);
        $this->run(function (BookingService $svc, Booking $b) {
            $room = $this->room_id && $this->room_id !== $b->room_id ? Room::findOrFail($this->room_id) : null;
            $nb = $svc->reschedule($b, auth()->user(), Carbon::parse("{$this->date} {$this->start}"), Carbon::parse("{$this->date} {$this->end}"), $room);
            $this->dispatch('toast', message: $nb->status === 'pending' ? 'Jadwal diubah. Menunggu persetujuan ulang.' : 'Jadwal diubah. Undangan terbarui dikirim.');
        });
    }

    public function decide(): void
    {
        $this->run(function (BookingService $svc, Booking $b) {
            if ($this->decision === 'approve') {
                $svc->approve($b, auth()->user(), $this->note);
                $this->dispatch('toast', message: 'Booking disetujui.');
            } else {
                $svc->reject($b, auth()->user(), $this->note);
                $this->dispatch('toast', message: 'Booking ditolak.');
            }
        });
    }

    private function run(callable $fn): void
    {
        $this->error = null;
        try {
            $fn(app(BookingService::class), $this->booking());
        } catch (BookingException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->dispatch('booking-changed');
        $this->close();
    }

    public function render()
    {
        $b = $this->bookingId ? Booking::with(['room', 'creator', 'approver', 'approvals.approver'])->find($this->bookingId) : null;
        $user = auth()->user();
        $svc = app(BookingService::class);

        return view('livewire.booking.detail', [
            'b' => $b,
            'canManage' => $b && $svc->canManage($b, $user) && in_array($b->status, ['pending', 'approved'], true) && $b->end_time->gt(now()),
            'canMove' => $b && $svc->canManage($b, $user) && in_array($b->status, ['pending', 'approved'], true) && $b->start_time->gt(now()),
            'canDecide' => $b && $b->status === 'pending' && $user->isApprover() && $b->created_by !== $user->id,
            'times' => Times::options('06:00', '21:00'),
            'rooms' => $b ? Room::where('is_active', true)->orderBy('name')->get() : collect(),
            'links' => $b ? \App\Support\Ics::links($b) : [],
        ]);
    }
}

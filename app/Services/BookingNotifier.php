<?php

namespace App\Services;

use App\Mail\BookingMail;
use App\Models\Booking;
use App\Models\EmailLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Fan-out of booking events: in-app notification, e-mail (with .ics) and
 * an optional Teams/Slack webhook. Delivery problems are recorded in
 * email_logs and never break the booking itself.
 */
class BookingNotifier
{
    public function __construct(private CalendarSync $sync)
    {
    }

    /** Booking just created. $series holds all occurrences of a recurring booking. */
    public function created(Booking $b, ?Collection $series = null): void
    {
        $b->loadMissing('room', 'creator');
        $many = $series && $series->count() > 1;
        $note = $many ? 'Booking berulang: ' . $series->count() . ' pertemuan sampai ' . $series->last()->start_time->translatedFormat('d M Y') . '.' : null;

        if ($b->status === 'approved') {
            foreach ($series ?? [$b] as $each) {
                $this->sync->push($each);
            }
            $this->inApp($b->creator, 'booking_created', "Booking \"{$b->title}\" dikonfirmasi.", $b);
            $this->mail($b, $b->creator->email, 'created', $note, $series);
            $this->inviteGuests($b, 'created', $note, $series);
        } else {
            $this->inApp($b->creator, 'booking_pending', "Booking \"{$b->title}\" menunggu persetujuan.", $b);
            $this->mail($b, $b->creator->email, 'pending', $note);
            foreach ($this->approvers($b) as $approver) {
                $this->inApp($approver, 'booking_approval', "{$b->creator->name} meminta persetujuan: \"{$b->title}\" ({$b->room->name}).", $b);
                $this->mail($b, $approver->email, 'approval', $note);
            }
        }

        $this->webhook("📅 {$b->creator->name} " . ($b->status === 'approved' ? 'memesan' : 'meminta') . " {$b->room->name}: {$b->title}, " . $this->when($b));
    }

    public function approved(Booking $b, ?string $note = null): void
    {
        $b->loadMissing('room', 'creator');
        $this->sync->push($b);
        $this->inApp($b->creator, 'booking_approved', "Booking \"{$b->title}\" disetujui.", $b);
        $this->mail($b, $b->creator->email, 'approved', $note);
        $this->inviteGuests($b, 'approved');
        $this->webhook("✅ Disetujui: {$b->room->name} · {$b->title}, " . $this->when($b));
    }

    public function rejected(Booking $b, ?string $note = null): void
    {
        $b->loadMissing('room', 'creator');
        $this->inApp($b->creator, 'booking_rejected', "Booking \"{$b->title}\" ditolak." . ($note ? " Alasan: $note" : ''), $b);
        $this->mail($b, $b->creator->email, 'rejected', $note);
    }

    public function cancelled(Booking $b, ?string $reason = null): void
    {
        $b->loadMissing('room', 'creator');
        $this->sync->remove($b);
        $this->inApp($b->creator, 'booking_cancelled', "Booking \"{$b->title}\" dibatalkan.", $b);
        $this->mail($b, $b->creator->email, 'cancelled', $reason);
        $this->inviteGuests($b, 'cancelled', $reason);
        $this->webhook("🚫 Dibatalkan: {$b->room->name} · {$b->title}, " . $this->when($b));
    }

    public function updated(Booking $b): void
    {
        $b->loadMissing('room', 'creator');
        $this->sync->push($b);
        $this->inApp($b->creator, 'booking_updated', "Jadwal \"{$b->title}\" diubah ke " . $this->when($b) . '.', $b);
        $this->mail($b, $b->creator->email, $b->status === 'approved' ? 'updated' : 'pending');
        if ($b->status === 'approved') {
            $this->inviteGuests($b, 'updated');
        } else {
            foreach ($this->approvers($b) as $approver) {
                $this->mail($b, $approver->email, 'approval');
            }
        }
    }

    /* ───────────────────────── internals ───────────────────────── */

    /** Guests get the .ics themselves, unless Outlook already sent them an invite. */
    private function inviteGuests(Booking $b, string $kind, ?string $note = null, ?Collection $series = null): void
    {
        if ($b->outlook_invitation_sent && $kind !== 'cancelled') {
            return;
        }
        foreach ((array) $b->attendees as $email) {
            $this->mail($b, $email, $kind, $note, $series);
        }
    }

    private function approvers(Booking $b): Collection
    {
        return User::with('role')->where('is_active', true)->where('id', '!=', $b->created_by)->get()->filter->isApprover();
    }

    private function inApp(User $user, string $type, string $message, Booking $b): void
    {
        Notification::create(['user_id' => $user->id, 'booking_id' => $b->id, 'type' => $type, 'message' => $message]);
    }

    private function mail(Booking $b, string $to, string $kind, ?string $note = null, ?Collection $series = null): void
    {
        if (! config('booking.mail.enabled')) {
            return;
        }
        $status = 'sent';
        try {
            Mail::to($to)->send(new BookingMail($b, $kind, $note, true, $series));
        } catch (Throwable $e) {
            $status = 'failed';
            report($e);
        }
        EmailLog::create(['booking_id' => $b->id, 'to' => $to, 'status' => $status, 'sent_at' => now(), 'created_by' => auth()->user()?->email]);
    }

    private function webhook(string $text): void
    {
        $url = config('services.webhook.url');
        if (! $url) {
            return;
        }
        try {
            Http::timeout(5)->post($url, ['text' => $text]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function when(Booking $b): string
    {
        return $b->start_time->translatedFormat('D d M, H:i') . '–' . $b->end_time->format('H:i');
    }
}

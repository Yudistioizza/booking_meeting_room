<?php

namespace App\Mail;

use App\Models\Booking;
use App\Support\Ics;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * One e-mail for every booking event. Invitations and updates carry an .ics
 * attachment so the event lands in Outlook, Google or Apple Calendar.
 */
class BookingMail extends Mailable
{
    use SerializesModels;

    public const SUBJECTS = [
        'created' => 'Booking dikonfirmasi',
        'pending' => 'Menunggu persetujuan',
        'approval' => 'Perlu persetujuan Anda',
        'approved' => 'Booking disetujui',
        'rejected' => 'Booking ditolak',
        'cancelled' => 'Booking dibatalkan',
        'updated' => 'Jadwal booking diubah',
    ];

    public function __construct(
        public Booking $booking,
        public string $kind,
        public ?string $note = null,
        public bool $withIcs = true,
        public ?Collection $series = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $b = $this->booking;

        return new Envelope(
            subject: self::SUBJECTS[$this->kind] . ': ' . $b->title . ' · ' . $b->start_time->translatedFormat('D, d M H:i'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.booking', with: [
            'b' => $this->booking,
            'kind' => $this->kind,
            'heading' => self::SUBJECTS[$this->kind],
            'note' => $this->note,
            'links' => Ics::links($this->booking),
        ]);
    }

    public function attachments(): array
    {
        if (! $this->withIcs || in_array($this->kind, ['approval', 'pending', 'rejected'], true)) {
            return [];
        }
        $method = $this->kind === 'cancelled' ? 'CANCEL' : 'REQUEST';

        return [
            Attachment::fromData(fn () => $this->series && $this->series->count() > 1 ? Ics::set($this->series, $method) : Ics::booking($this->booking, $method), 'invite.ics')
                ->withMime('text/calendar; charset=UTF-8; method=' . $method),
        ];
    }
}

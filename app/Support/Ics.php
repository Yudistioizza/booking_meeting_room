<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Minimal RFC 5545 writer. Times are written in UTC so every calendar
 * client (Outlook, Google, Apple) places them correctly without VTIMEZONE.
 */
class Ics
{
    /** One booking as an invitation (REQUEST) or cancellation (CANCEL). */
    public static function booking(Booking $b, string $method = 'REQUEST'): string
    {
        return self::wrap([self::event($b, $method === 'CANCEL')], $method);
    }

    /** Every occurrence of a recurring booking in one file. */
    public static function set(Collection $bookings, string $method = 'REQUEST'): string
    {
        return self::wrap($bookings->map(fn (Booking $b) => self::event($b, $method === 'CANCEL'))->all(), $method);
    }

    /** Subscription feed for a user's calendar app. */
    public static function feed(Collection $bookings, string $name): string
    {
        return self::wrap(
            $bookings->map(fn (Booking $b) => self::event($b))->all(),
            'PUBLISH',
            ['X-WR-CALNAME:' . self::esc($name), 'X-PUBLISHED-TTL:PT15M', 'REFRESH-INTERVAL;VALUE=DURATION:PT15M'],
        );
    }

    private static function wrap(array $events, string $method, array $extra = []): string
    {
        $lines = array_merge(
            ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . config('booking.company') . '//Ruang//ID', 'CALSCALE:GREGORIAN', "METHOD:$method"],
            $extra,
            array_merge([], ...$events),
            ['END:VCALENDAR'],
        );

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    private static function event(Booking $b, bool $cancelled = false): array
    {
        $b->loadMissing('room', 'creator');
        $status = $cancelled || $b->status === 'cancelled' ? 'CANCELLED' : ($b->status === 'approved' ? 'CONFIRMED' : 'TENTATIVE');
        $where = trim($b->room->name . ($b->room->location ? ' — ' . $b->room->location : ''));

        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $b->ics_uid,
            'SEQUENCE:' . $b->ics_sequence,
            'DTSTAMP:' . self::utc(now()),
            'DTSTART:' . self::utc($b->start_time),
            'DTEND:' . self::utc($b->end_time),
            'SUMMARY:' . self::esc($b->title),
            'LOCATION:' . self::esc($where),
            'STATUS:' . $status,
            'TRANSP:OPAQUE',
        ];
        if ($b->description) {
            $lines[] = 'DESCRIPTION:' . self::esc($b->description);
        }
        if ($b->creator) {
            $lines[] = 'ORGANIZER;CN=' . self::param($b->creator->name) . ':mailto:' . $b->creator->email;
            $lines[] = 'ATTENDEE;CN=' . self::param($b->creator->name) . ';ROLE=CHAIR;PARTSTAT=ACCEPTED:mailto:' . $b->creator->email;
        }
        foreach ((array) $b->attendees as $email) {
            $lines[] = 'ATTENDEE;ROLE=REQ-PARTICIPANT;RSVP=TRUE:mailto:' . $email;
        }
        if ($status !== 'CANCELLED') {
            array_push($lines, 'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:' . self::esc($b->title), 'TRIGGER:-PT10M', 'END:VALARM');
        }
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private static function utc($t): string
    {
        return Carbon::parse($t)->utc()->format('Ymd\THis\Z');
    }

    private static function esc(string $s): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $s);
    }

    private static function param(string $s): string
    {
        return '"' . str_replace('"', "'", $s) . '"';
    }

    /** Lines longer than 75 octets continue on the next line, indented by one space. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $chunk = '';
        foreach (mb_str_split($line) as $ch) {
            if (strlen($chunk . $ch) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ") . $chunk;
                $chunk = '';
            }
            $chunk .= $ch;
        }

        return $out . "\r\n " . $chunk;
    }

    /** One-click "add to calendar" links for the web clients. */
    public static function links(Booking $b): array
    {
        $b->loadMissing('room');
        $where = trim($b->room->name . ($b->room->location ? ', ' . $b->room->location : ''));
        $s = $b->start_time->copy()->utc();
        $e = $b->end_time->copy()->utc();

        return [
            'google' => 'https://calendar.google.com/calendar/render?' . http_build_query([
                'action' => 'TEMPLATE', 'text' => $b->title, 'dates' => $s->format('Ymd\THis\Z') . '/' . $e->format('Ymd\THis\Z'),
                'details' => (string) $b->description, 'location' => $where,
            ]),
            'outlook' => 'https://outlook.office.com/calendar/0/deeplink/compose?' . http_build_query([
                'path' => '/calendar/action/compose', 'rru' => 'addevent', 'subject' => $b->title,
                'startdt' => $s->toIso8601ZuluString(), 'enddt' => $e->toIso8601ZuluString(),
                'body' => (string) $b->description, 'location' => $where,
            ]),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use App\Support\Ics;

class IcsController extends Controller
{
    /** Download one booking as an invitation file. Organizer, invitees and admins only. */
    public function booking(Booking $booking)
    {
        $user = auth()->user();
        $invited = in_array(strtolower($user->email), array_map('strtolower', (array) $booking->attendees), true);
        abort_unless($booking->created_by === $user->id || $invited || $user->isAdmin(), 403);

        return response(Ics::booking($booking->load('room', 'creator'), $booking->status === 'cancelled' ? 'CANCEL' : 'REQUEST'), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="booking-' . $booking->id . '.ics"',
        ]);
    }

    /** Personal subscription feed. Calendar apps poll this URL; the token is the credential. */
    public function feed(string $token)
    {
        $user = User::where('calendar_token', $token)->where('is_active', true)->first();
        abort_unless($user, 404);

        $bookings = Booking::with('room', 'creator')->involving($user)
            ->whereIn('status', ['approved', 'pending'])
            ->where('end_time', '>=', now()->subDays(30))->where('start_time', '<=', now()->addDays(180))
            ->orderBy('start_time')->get();

        return response(Ics::feed($bookings, 'Ruang rapat — ' . $user->name), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}

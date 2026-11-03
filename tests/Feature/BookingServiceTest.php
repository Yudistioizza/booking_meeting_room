<?php

namespace Tests\Feature;

use App\Exceptions\BookingException;
use App\Mail\BookingMail;
use App\Models\Booking;
use App\Models\EmailLog;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomAvailabilityRule;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Ics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $svc;
    private User $emp;
    private User $other;
    private User $manager;
    private User $admin;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00'); // a Tuesday, WIB
        Mail::fake();
        config(['services.graph.client_id' => null, 'services.graph.organizer' => null]);

        foreach (['Admin', 'Manager', 'Employee'] as $r) {
            Role::create(['name' => $r]);
        }
        $mk = fn ($name, $role) => User::create(['name' => $name, 'email' => strtolower($name) . '@corp.test', 'password' => bcrypt('x'), 'role_id' => Role::where('name', $role)->value('id')]);
        $this->emp = $mk('Ayu', 'Employee');
        $this->other = $mk('Budi', 'Employee');
        $this->manager = $mk('Citra', 'Manager');
        $this->admin = $mk('Dewi', 'Admin');
        $this->room = Room::create(['name' => 'Borobudur', 'location' => 'Lt 2', 'capacity' => 10]);
        $this->svc = app(BookingService::class);
    }

    private function data(string $start, string $end, array $extra = []): array
    {
        return array_merge(['title' => 'Sync', 'start' => "2026-11-04 $start", 'end' => "2026-11-04 $end"], $extra);
    }

    private function fails(callable $fn, string $contains = ''): BookingException
    {
        try {
            $fn();
        } catch (BookingException $e) {
            if ($contains) {
                $this->assertStringContainsStringIgnoringCase($contains, $e->getMessage());
            }

            return $e;
        }
        $this->fail('BookingException expected');
    }

    public function test_overlap_is_refused_and_adjacent_slots_are_fine(): void
    {
        $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00'));

        $e = $this->fails(fn () => $this->svc->book($this->other, $this->room, $this->data('10:30', '11:30')), 'sudah dipakai');
        $this->assertCount(1, $e->conflicts);
        $this->fails(fn () => $this->svc->book($this->other, $this->room, $this->data('09:00', '10:15')));
        $this->svc->book($this->other, $this->room, $this->data('11:00', '12:00'));
        $this->svc->book($this->other, $this->room, $this->data('09:00', '10:00'));
        $this->assertSame(3, Booking::count());
    }

    public function test_room_buffer_keeps_turnaround_time(): void
    {
        $this->room->update(['buffer_minutes' => 15]);
        $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00'));

        $this->fails(fn () => $this->svc->book($this->other, $this->room, $this->data('11:00', '12:00')));
        $this->svc->book($this->other, $this->room, $this->data('11:15', '12:00'));
        $this->assertSame(2, Booking::count());
    }

    public function test_cancelled_and_rejected_bookings_free_the_slot(): void
    {
        $b = $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00'));
        $this->svc->cancel($b, $this->emp);
        $this->svc->book($this->other, $this->room, $this->data('10:00', '11:00'));
        $this->assertSame('cancelled', $b->fresh()->status);
    }

    public function test_rules_hours_duration_capacity_and_dates(): void
    {
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, $this->data('06:00', '07:00')), 'luar jam operasional');
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, $this->data('19:30', '20:30')), 'luar jam operasional');
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, ['title' => 'x', 'start' => '2026-11-07 10:00', 'end' => '2026-11-07 11:00']), 'luar jam operasional'); // Saturday
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, $this->data('11:00', '10:00')), 'setelah jam mulai');
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, ['title' => 'x', 'start' => '2026-11-03 08:00', 'end' => '2026-11-03 09:00']), 'sudah lewat');
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, $this->data('10:00', '10:05')), 'minimal');
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, ['title' => 'x', 'start' => '2027-06-01 10:00', 'end' => '2027-06-01 11:00']), 'maksimal 90 hari');
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00', ['attendee_count' => 11])), 'hanya muat');
        $this->room->update(['max_duration_minutes' => 120]);
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, $this->data('10:00', '13:00')), 'maksimal untuk ruang');
        $this->room->update(['is_active' => false]);
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00')), 'tidak dapat dipesan');
    }

    public function test_availability_rules_override_default_hours(): void
    {
        RoomAvailabilityRule::create(['room_id' => $this->room->id, 'available_days' => 'Mon,Tue,Wed,Thu,Fri', 'available_hours' => '08:00-12:00,13:00-17:00']);

        $this->svc->book($this->emp, $this->room, $this->data('08:00', '12:00'));
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, $this->data('12:00', '13:00')), 'luar jam operasional');
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, $this->data('11:30', '13:30')), 'luar jam operasional'); // spans the lunch gap
        $this->svc->book($this->emp, $this->room, $this->data('13:00', '17:00'));
    }

    public function test_approval_flow_and_pending_blocks_the_slot(): void
    {
        $this->room->update(['requires_approval' => true]);

        $b = $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00'));
        $this->assertSame('pending', $b->status);
        $this->fails(fn () => $this->svc->book($this->other, $this->room, $this->data('10:00', '11:00')), 'sudah dipakai');

        $auto = $this->svc->book($this->manager, $this->room, $this->data('14:00', '15:00'));
        $this->assertSame('approved', $auto->status, 'approvers book without waiting');

        $this->fails(fn () => $this->svc->approve($b, $this->other), 'tidak berwenang');
        $this->fails(fn () => $this->svc->approve($auto, $this->manager), 'sendiri');
        $this->svc->approve($b, $this->manager, 'Silakan');
        $this->assertSame('approved', $b->fresh()->status);
        $this->assertDatabaseHas('approvals', ['booking_id' => $b->id, 'approver_id' => $this->manager->id, 'status' => 'approved', 'note' => 'Silakan']);
        $this->fails(fn () => $this->svc->approve($b->fresh(), $this->admin), 'sudah diproses');

        $c = $this->svc->book($this->emp, $this->room, $this->data('16:00', '17:00'));
        $this->svc->reject($c, $this->admin, 'Bentrok agenda direksi');
        $this->assertSame('rejected', $c->fresh()->status);
        $this->svc->book($this->other, $this->room, $this->data('16:00', '17:00'));
        $this->fails(fn () => $this->svc->approve($this->svc->book($this->emp, $this->room, $this->data('17:00', '18:00')), $this->emp), 'tidak berwenang');
    }

    public function test_only_owner_or_admin_can_cancel_and_started_meetings_cannot(): void
    {
        $b = $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00'));
        $this->fails(fn () => $this->svc->cancel($b, $this->other), 'pemesan atau admin');
        $this->fails(fn () => $this->svc->cancel($b, $this->manager), 'pemesan atau admin');
        $this->assertSame(1, $this->svc->cancel($b, $this->admin, 'Agenda berubah'));

        $now = Booking::create(['room_id' => $this->room->id, 'created_by' => $this->emp->id, 'title' => 'Running', 'start_time' => '2026-11-03 08:30', 'end_time' => '2026-11-03 09:30', 'status' => 'approved', 'ics_uid' => 'x']);
        $this->fails(fn () => $this->svc->cancel($now, $this->emp), 'sudah berjalan');
    }

    public function test_reschedule_checks_conflicts_ignores_itself_and_bumps_sequence(): void
    {
        $a = $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00'));
        $this->svc->book($this->other, $this->room, $this->data('13:00', '14:00'));

        $this->fails(fn () => $this->svc->reschedule($a, $this->emp, Carbon::parse('2026-11-04 13:30'), Carbon::parse('2026-11-04 14:30')), 'sudah dipakai');
        $moved = $this->svc->reschedule($a, $this->emp, Carbon::parse('2026-11-04 10:30'), Carbon::parse('2026-11-04 11:30')); // overlaps only itself
        $this->assertSame('11:30', $moved->end_time->format('H:i'));
        $this->assertSame(1, $moved->ics_sequence);
        $this->fails(fn () => $this->svc->reschedule($a, $this->other, Carbon::parse('2026-11-04 15:00'), Carbon::parse('2026-11-04 16:00')), 'pemesan atau admin');
    }

    public function test_moving_into_an_approval_room_requires_approval_again(): void
    {
        $exec = Room::create(['name' => 'Merapi', 'capacity' => 20, 'requires_approval' => true]);
        $b = $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00'));

        $moved = $this->svc->reschedule($b, $this->emp, Carbon::parse('2026-11-04 10:00'), Carbon::parse('2026-11-04 11:00'), $exec);

        $this->assertSame('pending', $moved->status);
        $this->assertSame($exec->id, $moved->room_id);
    }

    public function test_weekly_series_skips_conflicts_and_reports_them(): void
    {
        $this->svc->book($this->other, $this->room, ['title' => 'Blocker', 'start' => '2026-11-11 10:00', 'end' => '2026-11-11 11:00']); // second Wednesday

        $res = $this->svc->bookSeries($this->emp, $this->room, $this->data('10:00', '11:00'), ['frequency' => 'weekly', 'interval' => 1, 'days' => ['Wed'], 'count' => 4]);

        $this->assertCount(3, $res['bookings']);
        $this->assertCount(1, $res['skipped']);
        $this->assertStringContainsString('sudah dipakai', $res['skipped'][0]['reason']);
        $this->assertSame(1, \App\Models\RecurringBooking::count());
        $this->assertSame([4, 18, 25], $res['bookings']->map(fn ($b) => $b->start_time->day)->all());

        // Strict mode refuses the whole series and leaves nothing behind
        $r2 = Room::create(['name' => 'Prambanan', 'capacity' => 10]);
        $this->svc->book($this->other, $r2, ['title' => 'Blocker', 'start' => '2026-11-11 10:00', 'end' => '2026-11-11 11:00']);
        $this->fails(fn () => $this->svc->bookSeries($this->emp, $r2, $this->data('10:00', '11:00'), ['frequency' => 'weekly', 'days' => ['Wed'], 'count' => 4], false), 'sudah dipakai');
        $this->assertSame(1, Booking::where('room_id', $r2->id)->count());
    }

    public function test_recurrence_patterns(): void
    {
        $s = Carbon::parse('2026-11-04 10:00');
        $e = $s->copy()->addHour();
        $dates = fn (array $rule) => collect($this->svc->occurrences($s, $e, $rule))->map(fn ($o) => $o[0]->format('m-d'))->all();

        $this->assertSame(['11-04', '11-05', '11-06', '11-09'], $dates(['frequency' => 'daily', 'count' => 4, 'skip_weekends' => true]));
        $this->assertSame(['11-04', '11-18', '12-02'], $dates(['frequency' => 'weekly', 'interval' => 2, 'count' => 3]));
        $this->assertSame(['11-04', '11-05', '11-11', '11-12'], $dates(['frequency' => 'weekly', 'days' => ['Wed', 'Thu'], 'until' => '2026-11-12']));
        $this->assertSame(['11-04', '12-04', '01-04'], $dates(['frequency' => 'monthly', 'count' => 3]));
        $this->fails(fn () => $this->svc->occurrences($s, $e, ['frequency' => 'daily']), 'batas pengulangan');
        $this->assertCount(52, $this->svc->occurrences($s, $e, ['frequency' => 'daily', 'count' => 500]), 'series is capped');
    }

    public function test_free_slot_suggestions_skip_busy_times(): void
    {
        $this->svc->book($this->emp, $this->room, $this->data('07:00', '10:00'));
        $this->svc->book($this->other, $this->room, $this->data('10:30', '12:00'));

        $slots = $this->svc->freeSlots($this->room, Carbon::parse('2026-11-04'), 60, 3);

        $this->assertSame(['12:00', '13:00', '14:00'], collect($slots)->map(fn ($s) => $s[0]->format('H:i'))->all());
    }

    public function test_attendee_emails_are_cleaned(): void
    {
        $this->assertSame(['x@a.com', 'y@b.com'], $this->svc->emails("X@a.com, bad, y@b.com;x@a.com\nayu@corp.test", $this->emp));
    }

    public function test_ics_is_valid_utc_folded_and_escaped(): void
    {
        $b = $this->svc->book($this->emp, $this->room, $this->data('09:30', '10:30', ['title' => 'Review, Q4; "final" ' . str_repeat('panjang ', 15), 'attendees' => ['tamu@vendor.com']]));

        $ics = Ics::booking($b->fresh());

        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertStringContainsString('DTSTART:20261104T023000Z', $ics, '09:30 WIB is 02:30 UTC');
        $this->assertStringContainsString('STATUS:CONFIRMED', $ics);
        $this->assertStringContainsString('ATTENDEE;ROLE=REQ-PARTICIPANT;RSVP=TRUE:mailto:tamu@vendor.com', $ics);
        $this->assertStringContainsString('Review\\, Q4\\; ', str_replace("\r\n ", '', $ics));
        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), "line too long: $line");
        }
        $this->assertStringContainsString('METHOD:CANCEL', Ics::booking($b, 'CANCEL'));
        $this->assertStringContainsString('STATUS:CANCELLED', Ics::booking($b, 'CANCEL'));
    }

    public function test_emails_go_to_organizer_guests_and_approvers_with_ics(): void
    {
        $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00', ['attendees' => ['tamu@vendor.com']]));

        Mail::assertSent(BookingMail::class, 2);
        Mail::assertSent(BookingMail::class, fn ($m) => $m->hasTo('ayu@corp.test') && $m->kind === 'created' && count($m->attachments()) === 1);
        Mail::assertSent(BookingMail::class, fn ($m) => $m->hasTo('tamu@vendor.com'));
        $this->assertSame(2, EmailLog::where('status', 'sent')->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $this->emp->id, 'type' => 'booking_created']);

        $this->room->update(['requires_approval' => true]);
        $this->svc->book($this->emp, $this->room, $this->data('14:00', '15:00'));
        Mail::assertSent(BookingMail::class, fn ($m) => $m->hasTo('citra@corp.test') && $m->kind === 'approval' && $m->attachments() === []);
        Mail::assertSent(BookingMail::class, fn ($m) => $m->hasTo('ayu@corp.test') && $m->kind === 'pending');
        $this->assertDatabaseHas('notifications', ['user_id' => $this->manager->id, 'type' => 'booking_approval']);
    }

    public function test_a_mail_failure_does_not_block_the_booking(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $b = $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00'));

        $this->assertSame('approved', $b->status);
        $this->assertDatabaseHas('email_logs', ['status' => 'failed']);
    }

    public function test_outlook_sync_creates_updates_and_deletes_the_event(): void
    {
        config(['services.graph' => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's', 'organizer' => 'rooms@corp.test']]);
        $this->room->update(['calendar_email' => 'room.borobudur@corp.test']);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']),
            'graph.microsoft.com/v1.0/users/*/events' => Http::response(['id' => 'EVT1'], 201),
            'graph.microsoft.com/v1.0/users/*/events/*' => Http::response([], 200),
        ]);

        $b = $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00', ['attendees' => ['tamu@vendor.com']]));

        $b->refresh();
        $this->assertSame('EVT1', $b->outlook_event_id);
        $this->assertTrue($b->outlook_invitation_sent);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/users/rooms%40corp.test/events')
            && $r['subject'] === 'Sync' && $r['start']['dateTime'] === '2026-11-04T03:00:00'
            && collect($r['attendees'])->pluck('type', 'emailAddress.address')->all() === ['ayu@corp.test' => 'required', 'tamu@vendor.com' => 'required', 'room.borobudur@corp.test' => 'resource']);
        Mail::assertNotSent(BookingMail::class, fn ($m) => $m->hasTo('tamu@vendor.com')); // Outlook already invited them

        $this->svc->reschedule($b, $this->emp, Carbon::parse('2026-11-04 11:00'), Carbon::parse('2026-11-04 12:00'));
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), 'events/EVT1'));

        $this->svc->cancel($b->fresh(), $this->emp);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'events/EVT1'));
        $this->assertNull($b->fresh()->outlook_event_id);
    }

    public function test_outlook_outage_never_blocks_booking(): void
    {
        config(['services.graph' => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's', 'organizer' => 'rooms@corp.test']]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $b = $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00', ['attendees' => ['tamu@vendor.com']]));

        $this->assertSame('approved', $b->status);
        $this->assertNull($b->fresh()->outlook_event_id);
        Mail::assertSent(BookingMail::class, fn ($m) => $m->hasTo('tamu@vendor.com')); // falls back to e-mail + .ics
    }

    public function test_concurrent_looking_duplicate_is_refused_inside_the_transaction(): void
    {
        $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00'));
        $this->fails(fn () => $this->svc->book($this->emp, $this->room, $this->data('10:00', '11:00')));
        $this->assertSame(1, Booking::where('room_id', $this->room->id)->count());
    }
}

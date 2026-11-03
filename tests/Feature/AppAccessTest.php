<?php

namespace Tests\Feature;

use App\Livewire\Admin\Rooms;
use App\Livewire\BaseModules\UserManager;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Livewire\Livewire;
use Tests\TestCase;

class AppAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $emp;
    private User $mgr;
    private User $admin;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 10:00:00');
        config(['booking.sso_domains' => ['corp.test']]);
        foreach (['Admin', 'Manager', 'Employee'] as $r) {
            Role::create(['name' => $r]);
        }
        $mk = fn ($n, $r) => User::create(['name' => $n, 'email' => strtolower($n) . '@corp.test', 'password' => bcrypt('secret-pass-1'), 'role_id' => Role::where('name', $r)->value('id'), 'email_verified_at' => now()]);
        $this->emp = $mk('Ayu', 'Employee');
        $this->mgr = $mk('Citra', 'Manager');
        $this->admin = $mk('Dewi', 'Admin');
        $this->room = Room::create(['name' => 'Borobudur', 'capacity' => 10]);
    }

    private function booking(array $a = []): Booking
    {
        return Booking::create($a + ['room_id' => $this->room->id, 'created_by' => $this->emp->id, 'title' => 'Sync', 'start_time' => '2026-11-04 10:00', 'end_time' => '2026-11-04 11:00', 'status' => 'approved', 'ics_uid' => uniqid() . '@t', 'attendees' => ['tamu@vendor.com']]);
    }

    public function test_every_page_renders_for_its_role(): void
    {
        $this->booking();
        $common = ['dashboard', 'book', 'calendar', 'my-bookings', 'settings.profile', 'settings.password'];
        $manager = ['approvals', 'reports'];
        $admin = ['rooms.index', 'users.index', 'integrations', 'audit'];

        foreach ([[$this->emp, $common, array_merge($manager, $admin)], [$this->mgr, array_merge($common, $manager), $admin], [$this->admin, array_merge($common, $manager, $admin), []]] as [$user, $allowed, $denied]) {
            $this->actingAs($user);
            foreach ($allowed as $r) {
                $this->get(route($r))->assertOk();
            }
            foreach ($denied as $r) {
                $this->get(route($r))->assertForbidden();
            }
        }
    }

    public function test_guests_are_sent_to_login_and_public_registration_is_closed(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/calendar')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->get('/login')->assertOk()->assertDontSee('Daftar');
    }

    public function test_inactive_users_cannot_sign_in(): void
    {
        $this->emp->update(['is_active' => false]);

        \Livewire\Livewire::test(\App\Livewire\Auth\Login::class)->set('email', $this->emp->email)->set('password', 'secret-pass-1')->call('login')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_ics_download_is_limited_to_organizer_invitees_and_admins(): void
    {
        $b = $this->booking();
        $this->actingAs($this->emp)->get(route('bookings.ics', $b))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')->assertSee('BEGIN:VEVENT', false);
        $this->actingAs($this->admin)->get(route('bookings.ics', $b))->assertOk();
        $this->actingAs($this->mgr)->get(route('bookings.ics', $b))->assertForbidden();
    }

    public function test_personal_feed_lists_my_and_invited_bookings_only(): void
    {
        $mine = $this->booking(['title' => 'Milik Ayu']);
        $this->booking(['title' => 'Milik orang lain', 'created_by' => $this->mgr->id, 'attendees' => null, 'ics_uid' => 'other@t']);
        $this->booking(['title' => 'Saya diundang', 'created_by' => $this->mgr->id, 'attendees' => ['ayu@corp.test'], 'ics_uid' => 'inv@t']);
        $this->booking(['title' => 'Sudah batal', 'status' => 'cancelled', 'ics_uid' => 'c@t']);

        $res = $this->get(route('calendar.feed', $this->emp->calendarToken()))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');

        $res->assertSee('Milik Ayu')->assertSee('Saya diundang')->assertDontSee('Milik orang lain')->assertDontSee('Sudah batal')->assertSee('METHOD:PUBLISH');
        $this->get('/calendar/feed/' . str_repeat('a', 48) . '.ics')->assertNotFound();
        $this->get('/calendar/feed/short.ics')->assertNotFound();
    }

    public function test_room_display_needs_a_signed_url_and_shows_live_status(): void
    {
        $this->booking(['title' => 'Rapat berjalan', 'start_time' => '2026-11-03 09:30', 'end_time' => '2026-11-03 10:30']);

        $this->get(route('room.display', $this->room))->assertForbidden();
        $this->get(URL::signedRoute('room.display', $this->room))->assertOk()->assertSee('Sedang dipakai')->assertSee('Rapat berjalan');

        $this->room->update(['is_active' => false]);
        $this->get(URL::signedRoute('room.display', $this->room))->assertNotFound();
    }

    private function azure(string $email, string $name = 'Eko Prasetyo', string $id = 'az-1'): void
    {
        $u = new SocialUser();
        $u->map(['id' => $id, 'name' => $name, 'email' => $email]);
        Socialite::shouldReceive('driver->user')->andReturn($u);
    }

    public function test_sso_provisions_an_employee_on_first_sign_in(): void
    {
        $this->azure('eko@corp.test');

        $this->get('/login/azure/callback')->assertRedirect(route('dashboard'));

        $u = User::where('email', 'eko@corp.test')->first();
        $this->assertAuthenticatedAs($u);
        $this->assertSame('Employee', $u->role->name);
        $this->assertTrue($u->is_sso);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.provisioned']);
    }

    public function test_sso_links_existing_accounts_and_keeps_their_role(): void
    {
        $this->azure('citra@corp.test', 'Citra', 'az-9');

        $this->get('/login/azure/callback');

        $this->assertAuthenticatedAs($this->mgr);
        $this->assertSame('az-9', $this->mgr->fresh()->sso_id);
        $this->assertSame('Manager', $this->mgr->fresh()->role->name);
        $this->assertSame(1, User::where('email', 'citra@corp.test')->count());
    }

    public function test_sso_refuses_other_domains_and_deactivated_accounts(): void
    {
        $this->azure('hacker@evil.test');
        $this->get('/login/azure/callback')->assertRedirect('/login')->assertSessionHasErrors('azure');
        $this->assertGuest();

        $this->emp->update(['is_active' => false]);
        $this->azure('ayu@corp.test');
        $this->get('/login/azure/callback')->assertRedirect('/login')->assertSessionHasErrors('azure');
        $this->assertGuest();
    }

    public function test_user_manager_protects_the_last_admin_and_the_self_account(): void
    {
        $this->actingAs($this->admin);
        $adminRole = Role::where('name', 'Admin')->value('id');
        $empRole = Role::where('name', 'Employee')->value('id');

        Livewire::test(UserManager::class)->call('edit', $this->admin->id)->set('role_id', $empRole)->call('save')->assertHasErrors('role_id');
        Livewire::test(UserManager::class)->call('toggle', $this->admin->id);
        $this->assertTrue($this->admin->fresh()->is_active);

        $new = Livewire::test(UserManager::class)->call('create')->set('name', 'Fajar Nugroho')->set('email', 'fajar@corp.test')->set('department', 'IT')
            ->set('role_id', $adminRole)->set('password', 'rahasia-panjang-1')->call('save')->assertHasNoErrors();
        $fajar = User::where('email', 'fajar@corp.test')->first();
        $this->assertNotNull($fajar);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('rahasia-panjang-1', $fajar->password));

        // A second admin exists now, so the first may be demoted
        Livewire::test(UserManager::class)->call('edit', $this->admin->id)->set('role_id', $empRole)->call('save')->assertHasNoErrors();

        // Deleting someone with upcoming bookings is refused
        $this->booking();
        Livewire::test(UserManager::class)->call('confirmDelete', $this->emp->id)->call('delete');
        $this->assertNotNull($this->emp->fresh());
    }

    public function test_only_admins_manage_rooms_and_rules_are_saved(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Rooms::class)->call('create')->set('name', 'Auditorium')->set('capacity', 50)->set('requires_approval', true)
            ->set('days', ['Mon', 'Tue'])->set('hours', '08:00-12:00, 13:00-17:00')->call('save')->assertHasNoErrors();

        $room = Room::where('name', 'Auditorium')->first();
        $this->assertTrue($room->requires_approval);
        $rule = $room->availabilityRules->first();
        $this->assertSame('Mon,Tue', $rule->available_days);
        $this->assertSame('08:00-12:00,13:00-17:00', $rule->available_hours);

        Livewire::test(Rooms::class)->call('create')->set('name', 'Auditorium')->call('save')->assertHasErrors('name');
        Livewire::test(Rooms::class)->call('create')->set('name', 'X')->set('hours', 'abc')->call('save')->assertHasErrors('hours');

        // Archiving is blocked while upcoming bookings exist
        $this->booking();
        Livewire::test(Rooms::class)->call('archive', $this->room->id);
        $this->assertNull($this->room->fresh()->deleted_at);
        Booking::query()->update(['status' => 'cancelled']);
        Livewire::test(Rooms::class)->call('archive', $this->room->id);
        $this->assertNotNull(Room::withTrashed()->find($this->room->id)->deleted_at);
    }

    public function test_reports_and_csv_export(): void
    {
        $this->booking(['start_time' => '2026-11-02 10:00', 'end_time' => '2026-11-02 12:00']);
        $this->actingAs($this->mgr);

        $this->get(route('reports'))->assertOk()->assertSee('Utilisasi');
        $csv = $this->get(route('reports.export', ['from' => '2026-11-01', 'to' => '2026-11-03']))->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Borobudur', $body);
        $this->assertStringContainsString('Sync', $body);
        $this->assertStringContainsString('120', $body);

        $this->actingAs($this->emp)->get(route('reports.export', ['from' => '2026-11-01', 'to' => '2026-11-03']))->assertForbidden();
    }
}

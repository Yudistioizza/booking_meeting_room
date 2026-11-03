<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_with_a_personal_calendar_feed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/settings/profile')->assertOk()->assertSee('/calendar/feed/' . $user->fresh()->calendar_token, false);
    }

    public function test_name_and_department_can_be_updated_but_not_the_email(): void
    {
        $user = User::factory()->create(['email' => 'keep@corp.test']);
        $this->actingAs($user);

        Livewire::test(Profile::class)->set('name', 'Nama Baru')->set('department', 'Finance')->call('save')->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('Finance', $user->department);
        $this->assertSame('keep@corp.test', $user->email);
    }

    public function test_regenerating_the_feed_token_invalidates_the_old_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $old = $user->calendarToken();

        Livewire::test(Profile::class)->call('regenerateFeed');

        $this->assertNotSame($old, $user->fresh()->calendar_token);
        $this->get(route('calendar.feed', $old))->assertNotFound();
        $this->get(route('calendar.feed', $user->fresh()->calendar_token))->assertOk();
    }
}

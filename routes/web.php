<?php

use App\Http\Controllers\Auth\AzureAuthController;
use App\Http\Controllers\IcsController;
use App\Http\Controllers\ReportController;
use App\Livewire\Admin\Audit;
use App\Livewire\Admin\Integrations;
use App\Livewire\Admin\Reports;
use App\Livewire\Admin\Rooms;
use App\Livewire\Booking\Approvals;
use App\Livewire\Booking\Calendar;
use App\Livewire\Booking\Find;
use App\Livewire\Booking\Mine;
use App\Livewire\BaseModules\UserManager;
use App\Livewire\Dashboard;
use App\Livewire\RoomDisplay;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::get('book', Find::class)->name('book');
    Route::get('calendar', Calendar::class)->name('calendar');
    Route::get('my-bookings', Mine::class)->name('my-bookings');
    Route::get('bookings/{booking}/ics', [IcsController::class, 'booking'])->name('bookings.ics');

    Route::get('approvals', Approvals::class)->middleware('can:approve-bookings')->name('approvals');
    Route::get('reports', Reports::class)->middleware('can:view-reports')->name('reports');
    Route::get('reports/export', [ReportController::class, 'export'])->middleware('can:view-reports')->name('reports.export');

    Route::get('rooms', Rooms::class)->middleware('can:manage-rooms')->name('rooms.index');
    Route::get('users', UserManager::class)->middleware('can:manage-users')->name('users.index');
    Route::get('integrations', Integrations::class)->middleware('can:admin')->name('integrations');
    Route::get('audit', Audit::class)->middleware('can:admin')->name('audit');

    Route::redirect('settings', 'settings/profile');
    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
});

// Personal calendar subscription. The long random token is the credential.
Route::get('calendar/feed/{token}.ics', [IcsController::class, 'feed'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:60,1')->name('calendar.feed');

// Tablet outside a room. Signed link, no login, read-only.
Route::get('display/{room}', RoomDisplay::class)->middleware('signed')->name('room.display');

Route::get('/login/azure', [AzureAuthController::class, 'redirectToProvider'])->name('login.azure');
Route::get('/login/azure/callback', [AzureAuthController::class, 'handleProviderCallback']);

require __DIR__ . '/auth.php';

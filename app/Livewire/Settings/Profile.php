<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Profil & kalender saya')]
class Profile extends Component
{
    public string $name = '';
    public string $department = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->department = (string) Auth::user()->department;
    }

    public function save(): void
    {
        $data = $this->validate(['name' => 'required|string|min:2|max:120', 'department' => 'nullable|string|max:80']);
        Auth::user()->update($data);
        session()->flash('success', 'Profil diperbarui.');
    }

    /** Invalidates the old subscription URL, e.g. after it was shared by mistake. */
    public function regenerateFeed(): void
    {
        Auth::user()->forceFill(['calendar_token' => Str::random(48)])->save();
        session()->flash('success', 'Tautan kalender baru dibuat. Langganan lama tidak berlaku lagi.');
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.settings.profile', [
            'user' => $user,
            'feedUrl' => route('calendar.feed', ['token' => $user->calendarToken()]),
        ]);
    }
}

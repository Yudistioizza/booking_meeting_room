<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\Room;
use App\Models\RoomAvailabilityRule;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Ruangan')]
class Rooms extends Component
{
    public string $search = '';
    public bool $showArchived = false;

    public bool $open = false;
    public ?int $editId = null;
    public string $name = '';
    public string $location = '';
    public int $capacity = 8;
    public string $equipment = '';
    public bool $requires_approval = false;
    public ?int $max_duration_minutes = null;
    public int $buffer_minutes = 0;
    public string $calendar_email = '';
    public bool $is_active = true;
    public array $days = [];
    public string $hours = '';

    public ?int $displayRoom = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('rooms', 'name')->ignore($this->editId)->whereNull('deleted_at')],
            'location' => 'nullable|string|max:160',
            'capacity' => 'required|integer|min:1|max:1000',
            'equipment' => 'nullable|string|max:500',
            'max_duration_minutes' => 'nullable|integer|min:15|max:1440',
            'buffer_minutes' => 'required|integer|min:0|max:120',
            'calendar_email' => 'nullable|email|max:160',
            'hours' => ['nullable', 'regex:/^\d{1,2}:\d{2}-\d{1,2}:\d{2}(\s*,\s*\d{1,2}:\d{2}-\d{1,2}:\d{2})*$/'],
        ];
    }

    protected function messages(): array
    {
        return ['name.unique' => 'Nama ruangan sudah ada.', 'hours.regex' => 'Format jam: 08:00-12:00 atau 08:00-12:00,13:00-17:00'];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->open = true;
    }

    public function edit(int $id): void
    {
        $r = Room::withTrashed()->with('availabilityRules')->findOrFail($id);
        $this->resetForm();
        $this->editId = $r->id;
        foreach (['name', 'location', 'capacity', 'equipment', 'max_duration_minutes', 'buffer_minutes', 'calendar_email'] as $f) {
            $this->$f = $r->$f ?? ($f === 'capacity' ? 8 : '');
        }
        $this->requires_approval = (bool) $r->requires_approval;
        $this->is_active = (bool) $r->is_active;
        $rule = $r->availabilityRules->where('is_active', true)->first();
        $this->days = $rule && $rule->available_days ? explode(',', $rule->available_days) : [];
        $this->hours = $rule->available_hours ?? '';
        $this->open = true;
    }

    private function resetForm(): void
    {
        $this->reset(['editId', 'name', 'location', 'equipment', 'requires_approval', 'max_duration_minutes', 'buffer_minutes', 'calendar_email', 'days', 'hours']);
        $this->capacity = 8;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();
        $data = [
            'name' => trim($this->name), 'location' => $this->location ?: null, 'capacity' => $this->capacity,
            'equipment' => $this->equipment ?: null, 'requires_approval' => $this->requires_approval,
            'max_duration_minutes' => $this->max_duration_minutes ?: null, 'buffer_minutes' => $this->buffer_minutes,
            'calendar_email' => $this->calendar_email ?: null, 'is_active' => $this->is_active, 'updated_by' => auth()->user()->email,
        ];
        $room = $this->editId ? tap(Room::withTrashed()->findOrFail($this->editId))->update($data) : Room::create($data + ['created_by' => auth()->user()->email]);

        $room->availabilityRules()->delete();
        if ($this->days || $this->hours) {
            RoomAvailabilityRule::create([
                'room_id' => $room->id, 'available_days' => $this->days ? implode(',', $this->days) : null,
                'available_hours' => $this->hours ? preg_replace('/\s+/', '', $this->hours) : null,
            ]);
        }
        AuditLog::record($this->editId ? 'room.updated' : 'room.created', $room, ($this->editId ? 'Mengubah' : 'Menambah') . " ruangan {$room->name}");
        $this->open = false;
        $this->dispatch('toast', message: 'Ruangan disimpan.');
    }

    public function archive(int $id): void
    {
        $room = Room::findOrFail($id);
        if ($room->bookings()->holding()->where('end_time', '>', now())->exists()) {
            $this->dispatch('toast', message: 'Masih ada booking aktif di ruang ini. Batalkan atau pindahkan dulu.', type: 'error');

            return;
        }
        $room->delete();
        AuditLog::record('room.archived', $room, "Mengarsipkan ruangan {$room->name}");
    }

    public function restore(int $id): void
    {
        $room = Room::onlyTrashed()->findOrFail($id);
        $room->restore();
        AuditLog::record('room.restored', $room, "Memulihkan ruangan {$room->name}");
    }

    public function render()
    {
        $rooms = Room::withTrashed()->with('availabilityRules')->withCount(['bookings as upcoming' => fn ($q) => $q->holding()->where('end_time', '>', now())])
            ->when($this->showArchived, fn ($q) => $q->onlyTrashed(), fn ($q) => $q->whereNull('deleted_at'))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")->orWhere('location', 'like', "%{$this->search}%")))
            ->orderBy('location')->orderBy('name')->get();

        return view('livewire.admin.rooms', [
            'rooms' => $rooms,
            'displayUrl' => $this->displayRoom ? URL::signedRoute('room.display', ['room' => $this->displayRoom]) : null,
        ]);
    }
}

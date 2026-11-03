<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Audit log')]
class Audit extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';
    #[Url(as: 'aksi')]
    public string $group = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingGroup(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $logs = AuditLog::with('user:id,name')
            ->when($this->group, fn ($q) => $q->where('action', 'like', $this->group . '.%'))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('summary', 'like', "%{$this->search}%")->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$this->search}%"))))
            ->latest('id')->paginate(20);

        return view('livewire.admin.audit', ['logs' => $logs, 'groups' => ['booking' => 'Booking', 'room' => 'Ruangan', 'user' => 'Pengguna', 'integration' => 'Integrasi', 'report' => 'Laporan']]);
    }
}

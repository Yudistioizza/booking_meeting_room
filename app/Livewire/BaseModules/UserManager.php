<?php

namespace App\Livewire\BaseModules;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Pengguna')]
class UserManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';
    #[Url(as: 'peran')]
    public string $filterRole = '';
    #[Url(as: 'status')]
    public string $filterStatus = '';

    public bool $open = false;
    public ?int $editId = null;
    public string $name = '';
    public string $email = '';
    public string $department = '';
    public ?int $role_id = null;
    public bool $is_active = true;
    public string $password = '';
    public ?string $deleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:120',
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($this->editId)->whereNull('deleted_at')],
            'department' => 'nullable|string|max:80',
            'role_id' => 'required|exists:roles,id',
            'password' => [$this->editId ? 'nullable' : 'nullable', 'string', 'min:10', 'max:100'],
        ];
    }

    protected function messages(): array
    {
        return ['email.unique' => 'Email sudah terdaftar.', 'password.min' => 'Kata sandi minimal 10 karakter.', 'role_id.required' => 'Pilih peran.'];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->role_id = Role::where('name', config('booking.roles.employee'))->value('id');
        $this->open = true;
    }

    public function edit(int $id): void
    {
        $u = User::findOrFail($id);
        $this->resetForm();
        $this->editId = $u->id;
        $this->name = $u->name;
        $this->email = $u->email;
        $this->department = (string) $u->department;
        $this->role_id = $u->role_id;
        $this->is_active = (bool) $u->is_active;
        $this->open = true;
    }

    private function resetForm(): void
    {
        $this->reset(['editId', 'name', 'email', 'department', 'role_id', 'password']);
        $this->is_active = true;
        $this->resetValidation();
    }

    public function generatePassword(): void
    {
        $this->password = Str::password(14, symbols: false);
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editId && $this->wouldRemoveLastAdmin($this->editId, $this->role_id, $this->is_active)) {
            $this->addError('role_id', 'Harus ada minimal satu admin aktif.');

            return;
        }
        if ($this->editId === auth()->id() && ! $this->is_active) {
            $this->addError('email', 'Anda tidak dapat menonaktifkan akun sendiri.');

            return;
        }

        $data = [
            'name' => trim($this->name), 'email' => strtolower(trim($this->email)), 'department' => $this->department ?: null,
            'role_id' => $this->role_id, 'is_active' => $this->is_active,
        ];

        if ($this->editId) {
            $user = User::findOrFail($this->editId);
            $user->update($data + ['updated_by' => auth()->user()->email] + ($this->password ? ['password' => Hash::make($this->password)] : []));
            AuditLog::record('user.updated', $user, "Mengubah pengguna {$user->email}");
        } else {
            $temp = $this->password ?: null;
            $user = User::create($data + [
                'password' => $temp ? Hash::make($temp) : null, 'email_verified_at' => now(), 'created_by' => auth()->user()->email,
                'is_force_change_pass' => (bool) $temp,
            ]);
            AuditLog::record('user.created', $user, "Menambah pengguna {$user->email}" . ($temp ? '' : ' (hanya SSO)'));
        }

        $this->open = false;
        $this->dispatch('toast', message: $this->editId ? 'Pengguna diperbarui.' : 'Pengguna ditambahkan.');
    }

    public function toggle(int $id): void
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            $this->dispatch('toast', message: 'Anda tidak dapat menonaktifkan akun sendiri.', type: 'error');

            return;
        }
        if ($user->is_active && $this->wouldRemoveLastAdmin($user->id, $user->role_id, false)) {
            $this->dispatch('toast', message: 'Harus ada minimal satu admin aktif.', type: 'error');

            return;
        }
        $user->update(['is_active' => ! $user->is_active]);
        AuditLog::record($user->is_active ? 'user.activated' : 'user.deactivated', $user, ($user->is_active ? 'Mengaktifkan' : 'Menonaktifkan') . " {$user->email}");
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = (string) $id;
    }

    public function delete(): void
    {
        $user = User::findOrFail((int) $this->deleteId);
        $this->deleteId = null;

        if ($user->id === auth()->id()) {
            $this->dispatch('toast', message: 'Anda tidak dapat menghapus akun sendiri.', type: 'error');

            return;
        }
        if ($this->wouldRemoveLastAdmin($user->id, $user->role_id, false)) {
            $this->dispatch('toast', message: 'Harus ada minimal satu admin aktif.', type: 'error');

            return;
        }
        $future = $user->bookings()->holding()->where('end_time', '>', now())->count();
        if ($future > 0) {
            $this->dispatch('toast', message: "Pengguna punya {$future} booking mendatang. Batalkan dulu atau cukup nonaktifkan.", type: 'error');

            return;
        }
        $user->update(['deleted_by' => auth()->user()->email, 'is_active' => false]);
        $user->delete();
        AuditLog::record('user.deleted', $user, "Menghapus pengguna {$user->email}");
        $this->dispatch('toast', message: 'Pengguna dihapus.');
    }

    private function wouldRemoveLastAdmin(int $userId, ?int $newRole, bool $stillActive): bool
    {
        $adminRole = Role::where('name', config('booking.roles.admin'))->value('id');
        $isAdmin = User::whereKey($userId)->where('role_id', $adminRole)->where('is_active', true)->exists();
        if (! $isAdmin) {
            return false;
        }
        $keeps = $newRole === $adminRole && $stillActive;

        return ! $keeps && User::where('role_id', $adminRole)->where('is_active', true)->where('id', '!=', $userId)->doesntExist();
    }

    public function render()
    {
        $users = User::with('role')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")->orWhere('department', 'like', "%{$this->search}%")))
            ->when($this->filterRole, fn ($q) => $q->where('role_id', $this->filterRole))
            ->when($this->filterStatus === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->filterStatus === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')->paginate(12);

        return view('livewire.base-modules.user-manager', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'total' => User::count(),
            'inactive' => User::where('is_active', false)->count(),
            'sso' => User::where('is_sso', true)->count(),
        ]);
    }
}

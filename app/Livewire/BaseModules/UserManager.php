<?php

namespace App\Livewire\BaseModules;

use App\Models\User;
use App\Models\Role;
use Livewire\Component;
use Livewire\WithPagination;

use App\Traits\WithCrud;
use App\Traits\WithFilter;
use App\Traits\WithFlash;

use App\Services\CrudService;
use App\Services\FilterService;
use App\Services\UserService;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserManager extends Component
{
    use WithPagination, WithCrud, WithFilter, WithFlash;

    /* Form fields */
    public string $name = '';
    public string $email = '';
    public ?string $sso_id = null;
    public string $password = '';
    public string $password_confirmation = '';
    public ?int $role_id = null;
    public bool $is_active = true;
    public bool $isLoading = false;

    /* Dependencies */
    private CrudService $crud;
    private FilterService $filter;
    private UserService $userSvc;

    public string $filterStatus = 'all'; // all, active, inactive, deleted
    public string $filterRole   = 'all'; // opsional jika pakai role juga

    public function boot(
        CrudService $crud,
        FilterService $filter,
        UserService $userSvc
    ) {
        $this->crud   = $crud;
        $this->filter = $filter;
        $this->userSvc= $userSvc;
    }

    /* ───────────────────────── Validation ───────────────────────── */
    protected function rules(): array
    {
        $uniqueEmail = 'unique:users,email';
        if ($this->isEditMode) {
            $uniqueEmail .= ',' . $this->editId;
        }

        return [
            'name'      => 'required|string|min:2|max:255',
            'email'     => ['required', 'email', 'max:255', $uniqueEmail],
            'role_id'   => 'required|exists:roles,id',
            'password'  => ($this->isEditMode ? 'nullable' : 'required') . '|min:8|confirmed',
            'is_active' => 'boolean',
            'sso_id'    => 'nullable|string|max:255',
        ];
    }

    /* ───────────────────────── Traits hooks ───────────────────────── */
    protected function findModel(int $id, bool $trashed = false)
    {
        return $trashed
            ? User::onlyTrashed()->findOrFail($id)
            : User::withTrashed()->findOrFail($id);
    }

    protected function fillForm(int $id): void
    {
        $user = $this->findModel($id);
        $this->name      = $user->name;
        $this->email     = $user->email;
        $this->sso_id    = $user->sso_id;
        $this->role_id   = $user->role_id;
        $this->is_active = $user->is_active;
    }

    protected function resetForm(): void
    {
        $this->reset([
            'name', 'email', 'sso_id', 'password', 'password_confirmation',
            'role_id', 'is_active'
        ]);
        $this->is_active = true;
        $this->role_id   = null;
    }

    /* ───────────────────────── CRUD Actions ───────────────────────── */
    public function store()
    {
        $this->validate();
        $data = [
            'name'       => trim($this->name),
            'email'      => strtolower(trim($this->email)),
            'sso_id'     => $this->sso_id,
            'role_id'    => $this->role_id,
            'is_active'  => $this->is_active,
            'password'   => Hash::make($this->password),
            'created_by' => Auth::id(),
        ];
        $this->crud->create(User::class, $data);
        $this->flashSuccess('User berhasil ditambahkan!');
        $this->cancel();
    }

    public function update()
    {
        $this->validate();
        $data = [
            'name'       => trim($this->name),
            'email'      => strtolower(trim($this->email)),
            'sso_id'     => $this->sso_id,
            'role_id'    => $this->role_id,
            'is_active'  => $this->is_active,
            'updated_by' => Auth::id(),
        ];
        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }
        $this->crud->update($this->editId, $data);
        $this->flashSuccess('User berhasil diperbarui!');
        $this->cancel();
    }

    public function confirmDelete(): void
    {
        if ($this->modelToDelete?->id === auth()->id()) {
            $this->flashError('Anda tidak dapat menghapus akun sendiri.');
            return;
        }

        $this->crud->softDelete($this->modelToDelete->id);
        $this->flashSuccess('User berhasil dihapus.');
        $this->cancelDelete();
    }

    public function confirmForceDelete(): void
    {
        if ($this->modelToForceDelete?->id === auth()->id()) {
            $this->flashError('Anda tidak dapat menghapus akun sendiri secara permanen.');
            return;
        }

        if ($this->forceDeleteConfirmation !== 'HAPUS PERMANEN') return;

        $this->crud->forceDelete($this->modelToForceDelete->id);
        $this->flashSuccess('User berhasil dihapus permanen.');
        $this->cancelForceDelete();
    }

    public function restore(int $id)
    {
        $this->crud->restore($id);
        $this->flashSuccess('User berhasil direstore.');
    }

    public function toggleUserStatus(int $userId): void
    {
        // cegah non-aktifkan diri sendiri
        if ($userId === auth()->id()) {
            $this->flashError('Anda tidak dapat menonaktifkan akun sendiri.');
            return;
        }

        $user = User::findOrFail($userId);
        $new  = !$user->is_active;
        $user->update(['is_active' => $new]);

        $this->flashSuccess('Status user berhasil ' . ($new ? 'diaktifkan' : 'dinonaktifkan') . '.');
    }

    /* ───────────────────────── Render ───────────────────────── */
    public function render()
    {
        $query = $this->userSvc->query();

        // filter keyword
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($this->search) . '%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($this->search) . '%']);
            });
        }

        // filter status
        if ($this->filterStatus === 'active') {
            $query->where('is_active', true)->whereNull('deleted_at');
        } elseif ($this->filterStatus === 'inactive') {
            $query->where('is_active', false)->whereNull('deleted_at');
        } elseif ($this->filterStatus === 'deleted') {
            $query->onlyTrashed();
        }

        // sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return view('livewire.base-modules.user-manager', [
            'users' => $query->paginate($this->perPage),
            'stats' => $this->userSvc->stats(),
            'roles' => Role::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
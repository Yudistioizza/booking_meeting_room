<div>
    {{-- Card Wrapper --}}
    <x-layouts.partials.generic-card title="Manajemen User" :stats="[
        'Total'   => $stats['total'],
        'Aktif'   => $stats['active'],
        'Tidak Aktif' => $stats['inactive'],
        'Dihapus' => $stats['deleted'],
    ]">

        {{-- Flash Messages --}}
        @include('components.layouts.partials.flash-messages')

        {{-- Controls Row --}}
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">

            {{-- Add Button --}}
            <flux:modal.trigger name="crud-form-modal">
                <flux:button variant="primary" wire:click="create" wire:loading.attr="disabled" wire:target="create" icon="user-plus">
                    Tambah User
                </flux:button>
            </flux:modal.trigger>

            {{-- Search + Filters --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">

                {{-- Modular Search --}}
                <div class="relative w-full sm:w-64">
                    <flux:input
                        placeholder="Cari nama, email, atau SSO ID..."
                        icon="magnifying-glass"
                        wire:model.live.debounce.500ms="search"
                    />
                    @if($search)
                        <button wire:click="$set('search', '')" class="absolute right-8 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <flux:icon name="x-mark" class="w-4 h-4" />
                        </button>
                    @endif
                </div>

                {{-- Clear Filter (selalu tampil jika ada filter aktif) --}}
                @if($search || $filterStatus !== 'all' || $filterRole !== 'all')
                    <flux:button variant="ghost" wire:click="clearFilters" icon="x-mark">
                        Clear Filters
                    </flux:button>
                @endif

                {{-- Filter Dropdown --}}
                <x-layouts.partials.filter-dropdown
                    status-model="filterStatus"
                    per-page-model="perPage"
                >
                    <div>
                        <label class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <flux:icon name="shield-check" class="w-4 h-4 text-green-500" />
                            Role
                        </label>
                        <select wire:model.live="filterRole" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm">
                            <option value="all">Semua Role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-layouts.partials.filter-dropdown>
            </div>
        </div>

        {{-- Table --}}
        <x-layouts.partials.table-wrapper
            :headers="['Nama', 'Email', 'Role', 'Status', 'Aksi']"
            :items="$users"
            emptyMessage="Tidak ada user yang sesuai kriteria"
        >
            @foreach($users as $user)
                <tr class="hover:bg-gray-50 {{ $user->trashed() ? 'bg-red-50' : '' }}">
                    {{-- Nama --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-layouts.partials.user-avatar :name="$user->name" />
                            <div>
                                <div class="font-medium {{ $user->trashed() ? 'line-through text-red-600' : 'text-gray-900' }}">
                                    {{ $user->name }}
                                </div>
                                @if($user->sso_id)
                                    <div class="text-xs text-gray-500">SSO: {{ $user->sso_id }}</div>
                                @endif
                            </div>
                        </div>
                    </td>

                    {{-- Email --}}
                    <td class="px-4 py-3">
                        <span class="{{ $user->trashed() ? 'line-through text-red-600' : 'text-gray-900' }}">
                            {{ $user->email }}
                        </span>
                    </td>

                    {{-- Role --}}
                    <td class="px-4 py-3">
                        @if($user->role)
                            <x-layouts.partials.role-badge :role="$user->role->name" />
                        @else
                            <span class="text-xs text-gray-400">-</span>
                        @endif
                    </td>

                    {{-- Status --}}
                    <td class="px-4 py-3 text-center">
                        @if($user->trashed())
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                Dihapus
                            </span>
                        @else
                            <button
                                wire:click="toggleUserStatus({{ $user->id }})"
                                class="px-2 py-1 text-xs font-medium rounded-full transition
                                {{ $user->is_active
                                    ? 'bg-green-100 text-green-800 hover:bg-green-200'
                                    : 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' }}"
                            >
                                {{ $user->is_active ? 'Aktif' : 'Tidak Aktif' }}
                            </button>
                        @endif
                    </td>

                    {{-- Aksi --}}
                    <td class="px-4 py-3">
                        <div class="flex justify-center gap-1">
                            @if($user->trashed())
                                {{-- Restore --}}
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="arrow-path"
                                    wire:click="restore({{ $user->id }})"
                                    title="Restore"
                                    class="text-green-600 hover:text-green-700"
                                />

                                {{-- Force Delete --}}
                                <flux:modal.trigger name="force-delete-confirmation-modal">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="prepareForceDelete({{ $user->id }})"
                                        title="Hapus Permanen"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                </flux:modal.trigger>
                            @else
                                {{-- Edit --}}
                                <flux:modal.trigger name="crud-form-modal">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="pencil-square"
                                        wire:click="edit({{ $user->id }})"
                                        title="Edit"
                                        class="text-yellow-600 hover:text-yellow-700"
                                    />
                                </flux:modal.trigger>

                                {{-- Delete --}}
                                <flux:modal.trigger name="delete-confirmation-modal">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="prepareDelete({{ $user->id }})"
                                        title="Hapus"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                </flux:modal.trigger>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-layouts.partials.table-wrapper>

        {{-- Pagination --}}
        <x-layouts.partials.pagination-info :items="$users" />

    </x-layouts.partials.generic-card>

    {{-- Form Modal --}}
    <flux:modal name="crud-form-modal" class="md:w-[32rem]">
        <form wire:submit.prevent="{{ $isEditMode ? 'update' : 'store' }}" class="space-y-5">
            <flux:heading size="lg">{{ $isEditMode ? 'Edit User' : 'Tambah User Baru' }}</flux:heading>

            {{-- Name --}}
            <flux:field>
                <flux:label>Nama Lengkap *</flux:label>
                <flux:input wire:model="name" placeholder="Nama user" />
                <flux:error name="name" />
            </flux:field>

            {{-- Email --}}
            <flux:field>
                <flux:label>Email *</flux:label>
                <flux:input type="email" wire:model="email" placeholder="user@example.com" />
                <flux:error name="email" />
            </flux:field>

            {{-- SSO ID --}}
            <flux:field>
                <flux:label>SSO ID</flux:label>
                <flux:input wire:model="sso_id" placeholder="Opsional" />
            </flux:field>

            {{-- Role --}}
            <flux:field>
                <flux:label>Role *</flux:label>
                <select wire:model="role_id" class="w-full border rounded-lg px-3 py-2">
                    <option value="">Pilih Role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <flux:error name="role_id" />
            </flux:field>

            {{-- Password --}}
            <flux:field>
                <flux:label>Password {{ $isEditMode ? '' : '*' }}</flux:label>
                <flux:input type="password" wire:model="password" placeholder="{{ $isEditMode ? 'Kosongkan bila tidak diubah' : 'Min 8 karakter' }}" />
                <flux:error name="password" />
            </flux:field>

            @if($password)
                <flux:field>
                    <flux:label>Konfirmasi Password *</flux:label>
                    <flux:input type="password" wire:model="password_confirmation" />
                    <flux:error name="password_confirmation" />
                </flux:field>
            @endif

            {{-- Is Active --}}
            <flux:checkbox wire:model="is_active" label="Aktifkan user" />

            {{-- Actions --}}
            <div class="flex justify-end gap-3 pt-4 border-t">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" wire:click="cancel">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    {{ $isEditMode ? 'Simpan Perubahan' : 'Tambah User' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="delete-confirmation-modal" class="md:w-[28rem]">
        <div class="text-center space-y-4">
            <flux:icon name="exclamation-triangle" class="w-12 h-12 text-red-500 mx-auto" />
            <flux:heading size="lg">Konfirmasi Hapus</flux:heading>
            <p class="text-gray-600">
                User <strong>{{ $modelToDelete->name ?? '' }}</strong> akan dipindahkan ke trash.
            </p>
            <div class="flex justify-center gap-3">
                <flux:button variant="ghost" wire:click="cancelDelete">Batal</flux:button>
                <flux:button variant="danger" wire:click="confirmDelete">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Force Delete Confirmation Modal --}}
    <flux:modal name="force-delete-confirmation-modal" class="md:w-[30rem]">
        <div class="text-center space-y-4">
            <flux:icon name="exclamation-triangle" class="w-12 h-12 text-red-500 mx-auto" />
            <flux:heading size="lg" class="text-red-600">Hapus Permanen</flux:heading>
            <p class="text-sm text-gray-600">
                Menghapus permanen <strong>{{ $modelToForceDelete->name ?? '' }}</strong>. Tidak bisa dikembalikan.
            </p>
            <flux:input
                wire:model="forceDeleteConfirmation"
                placeholder="Ketik: HAPUS PERMANEN"
                class="font-mono"
            />
            <div class="flex justify-center gap-3">
                <flux:button variant="ghost" wire:click="cancelForceDelete">Batal</flux:button>
                <flux:button
                    variant="danger"
                    wire:click="confirmForceDelete"
                    :disabled="$forceDeleteConfirmation !== 'HAPUS PERMANEN'"
                >
                    Hapus Permanen
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
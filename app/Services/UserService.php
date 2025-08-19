<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;

class UserService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function query()
    {
        return User::with('roles')->withTrashed();
    }

    public function stats(): array
    {
        return [
            'total'     => User::withTrashed()->count(),
            'active'    => User::where('is_active', true)->whereNull('deleted_at')->count(),
            'inactive'  => User::where('is_active', false)->whereNull('deleted_at')->count(),
            'deleted'   => User::onlyTrashed()->count(),
        ];
    }

    public function rolesForFilter(): array
    {
        return Role::where('is_active', true)->whereNull('deleted_at')
                   ->orderBy('name')->pluck('name', 'id')->toArray();
    }
}

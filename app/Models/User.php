<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'sso_id',
        'is_sso',
        'is_force_change_pass',
        'email_verified_at',
        'password',
        'last_login_at',
        'role_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'restored_by',
        'restored_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'restored_at' => 'datetime',
        'is_sso' => 'boolean',
        'is_force_change_pass' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke role
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function initials(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';

        foreach ($words as $word) {
            $initials .= strtoupper($word[0]);
        }

        return $initials;
    }
}

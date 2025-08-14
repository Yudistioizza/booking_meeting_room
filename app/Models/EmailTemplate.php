<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'email_templates';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'name',
        'subject',
        'body',
        'created_by',
        'updated_by',
        'deleted_by',
        'restored_by',
        'restored_at',
        'is_active',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'is_active' => 'boolean',
        'restored_at' => 'datetime',
    ];

    /**
     * Relasi ke EmailLog
     */
    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class);
    }
}

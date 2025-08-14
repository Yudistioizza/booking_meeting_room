<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Approval extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'approvals';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'booking_id',
        'approver_id',
        'status',
        'note',
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
     * Relasi ke Booking
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Relasi ke Approver (User)
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'notifications';

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'user_id',
        'booking_id',
        'type',
        'message',
        'is_read',
        'created_by',
        'updated_by',
        'deleted_by',
        'restored_by',
        'restored_at',
        'is_active',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'is_read' => 'boolean',
        'is_active' => 'boolean',
        'restored_at' => 'datetime',
    ];

    /**
     * Relasi ke User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi opsional ke Booking.
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}

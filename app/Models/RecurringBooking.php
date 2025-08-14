<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'recurring_bookings';

    protected $fillable = [
        'frequency',
        'interval',
        'days_of_week',
        'end_date',
        'created_by',
        'updated_by',
        'deleted_by',
        'restored_by',
        'restored_at',
        'is_active',
    ];

    protected $casts = [
        'end_date' => 'date',
        'is_active' => 'boolean',
        'restored_at' => 'datetime',
    ];

    /**
     * Relasi ke bookings yang termasuk recurring ini
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'recurring_booking_id');
    }

    /**
     * Audit trail relasi ke user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function restorer()
    {
        return $this->belongsTo(User::class, 'restored_by');
    }
}

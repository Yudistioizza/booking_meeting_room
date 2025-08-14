<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bookings';

    protected $fillable = [
        'room_id',
        'created_by',
        'approved_by',
        'recurring_booking_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'status',
        'outlook_event_id',
        'outlook_invitation_sent',
        'updated_by',
        'deleted_by',
        'restored_by',
        'restored_at',
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'outlook_invitation_sent' => 'boolean',
        'is_active' => 'boolean',
        'restored_at' => 'datetime',
    ];

    /**
     * Relasi ke Room
     */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Relasi ke User yang membuat booking
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke User yang approve booking
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relasi ke Booking Recurring
     */
    public function recurringBooking()
    {
        return $this->belongsTo(RecurringBooking::class);
    }

    /**
     * Relasi ke audit trail users
     */
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

    /**
     * Relasi ke approvals
     */
    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }
}
